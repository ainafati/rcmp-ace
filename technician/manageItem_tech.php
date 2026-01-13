<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();
include '../config.php'; 

// 1. CHECK ACCESS
if (!isset($_SESSION['person_id']) || $_SESSION['logged_in_role'] !== 'Technician') {
    session_unset();
    session_destroy();
    $_SESSION['error'] = "Akses ditolak atau sesi tamat. Sila log masuk sebagai Technician.";
    header("Location: ../login.php");
    exit();
}

$person_id = (int) $_SESSION['person_id']; 

// 2. Tarik Data Technician (Satu query sahaja)
$stmt = $conn->prepare("SELECT name, email FROM person WHERE person_id = ?");
if ($stmt) {
    $stmt->bind_param("i", $person_id);
    $stmt->execute();
    $tech = $stmt->get_result()->fetch_assoc(); // Simpan dalam $tech
    $stmt->close();
}

if (!$tech) {
    session_unset();
    session_destroy();
    header("Location: ../login.php");
    exit();
}

// 3. Logik Nama Pendek (Gunakan $tech, bukan $user)
$fullName = $tech['name'] ?? 'Guest User';
$lowerName = strtolower($fullName);

$posBinti = strpos($lowerName, ' binti ');
$posBin = strpos($lowerName, ' bin ');

if ($posBinti !== false) {
    $shortName = substr($fullName, 0, $posBinti);
} elseif ($posBin !== false) {
    $shortName = substr($fullName, 0, $posBin);
} else {
    $shortName = $fullName;
}

$displayName = trim($shortName);

// --- HELPER FUNCTIONS ---
function safe_unlink($db_filepath) {
    if (!$db_filepath) return;
    $server_path = '../' . $db_filepath; 
    if (file_exists($server_path) && is_file($server_path)) {
        @unlink($server_path);
    }
}

function handleImageUpload($fileInputName, $dbSubDir) {
    global $conn;
    if (empty($dbSubDir)) $dbSubDir = 'assets/'; 
    if (substr($dbSubDir, -1) !== '/') { $dbSubDir .= '/'; }
    $targetDir = '../' . $dbSubDir; 

    if (!isset($_FILES[$fileInputName]) || $_FILES[$fileInputName]['error'] != UPLOAD_ERR_OK) {
        return NULL; 
    }

    $file = $_FILES[$fileInputName];
    $fileExt = strtolower(pathinfo(basename($file["name"]), PATHINFO_EXTENSION));
    if ($file["size"] > 5000000) return NULL;
    if (!in_array($fileExt, ['jpg', 'jpeg', 'png', 'webp'])) return NULL;

    $newFileName = uniqid('img_', true) . "." . $fileExt;
    $server_path = $targetDir . $newFileName; 
    $db_path = $dbSubDir . $newFileName;     

    if (!is_dir($targetDir)) mkdir($targetDir, 0777, true);
    if (move_uploaded_file($file["tmp_name"], $server_path)) return $db_path; 
    return NULL;
}

function generateItemAcronym($itemName) {
    $cleanedName = preg_replace('/[^a-zA-Z\s]/', '', $itemName);
    $words = explode(' ', $cleanedName);
    $acronym = '';
    foreach ($words as $word) { if (!empty($word)) $acronym .= strtoupper($word[0]); }
    if (empty($acronym)) $acronym = 'ITEM';
    return substr($acronym, 0, 3);
}

// --- 3. LOGIC: ADD CATEGORY ---
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['add_category'])) {
    $category_name = trim($_POST['category_name']);
    if (!empty($category_name)) {
        $stmt = $conn->prepare("INSERT INTO categories (category_name) VALUES (?)");
        $stmt->bind_param("s", $category_name);
        if ($stmt->execute()) $_SESSION['message'] = ['type' => 'success', 'title' => 'Success', 'text' => 'Category added!'];
        else $_SESSION['message'] = ['type' => 'error', 'title' => 'Error', 'text' => $stmt->error];
        $stmt->close();
    }
    header("Location: manageItem_tech.php"); exit();
}

// --- 4. LOGIC: ADD ITEM & UNITS ---
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['add_item_type_and_units'])) {
    $item_name = ucwords(trim($_POST['item_name']));
    $category_id = (int)$_POST['category_id'];
    $quantity = (int)$_POST['quantity'];
    $batch_brand = trim($_POST['batch_brand'] ?? '');
    $batch_model = trim($_POST['batch_model'] ?? '');
    $manual_codes = $_POST['manual_codes'] ?? [];
    $is_manual = (isset($_POST['enable_manual_code']) && !empty($manual_codes));

    $conn->begin_transaction();
    try {
        $image_path = handleImageUpload('item_image', 'assets/item_images');
        $stmt = $conn->prepare("INSERT INTO item (item_name, category_id, image_url) VALUES (?, ?, ?)");
        $stmt->bind_param("sis", $item_name, $category_id, $image_path);
        $stmt->execute();
        $new_item_id = $conn->insert_id;
        $stmt->close();

        $check_stmt = $conn->prepare("SELECT asset_code FROM assets WHERE asset_code = ?");
        $insert_asset = $conn->prepare("INSERT INTO assets (item_id, asset_code, brand, model, status) VALUES (?, ?, ?, ?, 'Available')");

        if ($is_manual) {
            foreach ($manual_codes as $code) {
                $code = trim($code);
                if (empty($code)) continue;
                $check_stmt->bind_param("s", $code);
                $check_stmt->execute();
                if ($check_stmt->get_result()->num_rows > 0) throw new Exception("Code '$code' already exists!");
                $insert_asset->bind_param("isss", $new_item_id, $code, $batch_brand, $batch_model);
                $insert_asset->execute();
            }
        } else {
            $acr = generateItemAcronym($item_name);
            for ($i = 1; $i <= $quantity; $i++) {
                $a_code = $acr . "-" . str_pad($i, 4, '0', STR_PAD_LEFT);
                $insert_asset->bind_param("isss", $new_item_id, $a_code, $batch_brand, $batch_model);
                $insert_asset->execute();
            }
        }
        $conn->commit();
        $_SESSION['message'] = ['type' => 'success', 'title' => 'Success', 'text' => 'Item added successfully!'];
    } catch (Exception $e) {
        $conn->rollback();
        $_SESSION['message'] = ['type' => 'error', 'title' => 'Duplicate Alert', 'text' => $e->getMessage()];
    }
    header("Location: manageItem_tech.php"); exit();
}

// --- 5. LOGIC: EDIT ITEM & ADD UNITS (FIXED) ---
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['edit_item_type'])) {
    $item_id = (int)$_POST['edit_item_id'];
    $item_name = ucwords(trim($_POST['edit_item_name']));
    $category_id = (int)$_POST['edit_category_id'];
    $qty_to_add = (int)($_POST['edit_item_quantity'] ?? 0);
    $batch_brand = trim($_POST['batch_brand'] ?? '');
    $batch_model = trim($_POST['batch_model'] ?? '');

    $conn->begin_transaction();
    try {
        // 1. Update info item
        $new_img = handleImageUpload('edit_item_image', 'assets/item_images');
        if ($new_img) {
            $upd = $conn->prepare("UPDATE item SET item_name=?, category_id=?, image_url=? WHERE item_id=?");
            $upd->bind_param("sisi", $item_name, $category_id, $new_img, $item_id);
        } else {
            $upd = $conn->prepare("UPDATE item SET item_name=?, category_id=? WHERE item_id=?");
            $upd->bind_param("sii", $item_name, $category_id, $item_id);
        }
        $upd->execute();

if ($qty_to_add > 0) {
    $check_stmt = $conn->prepare("SELECT asset_code FROM assets WHERE asset_code = ?");
    
    // TAMBAH brand dan model dalam query INSERT ini
    $insert_asset = $conn->prepare("INSERT INTO assets (item_id, asset_code, brand, model, status) VALUES (?, ?, ?, ?, 'Available')");

    if (isset($_POST['enable_manual_code']) && !empty($_POST['manual_codes'])) {
        foreach ($_POST['manual_codes'] as $m_code) {
            $m_code = trim($m_code);
            if (empty($m_code)) continue;

            // Semak duplicate
            $check_stmt->bind_param("s", $m_code);
            $check_stmt->execute();
            if ($check_stmt->get_result()->num_rows > 0) {
                throw new Exception("Asset Code '$m_code' already exists!");
            }
            
            // BIND 4 parameter: isss (integer, string, string, string)
            $insert_asset->bind_param("isss", $item_id, $m_code, $batch_brand, $batch_model);
            $insert_asset->execute();
        }
    } else {
        // Auto generate logic
        $curr_count = $conn->query("SELECT COUNT(*) FROM assets WHERE item_id=$item_id")->fetch_row()[0];
        for ($i = 1; $i <= $qty_to_add; $i++) {
            $auto_code = date('Y') . str_pad($curr_count + $i, 3, '0', STR_PAD_LEFT);
            
            // BIND 4 parameter juga untuk auto-generate
            $insert_asset->bind_param("isss", $item_id, $auto_code, $batch_brand, $batch_model);
            $insert_asset->execute();
        }
    }
}
        $conn->commit();
        $_SESSION['message'] = ['type' => 'success', 'title' => 'Updated', 'text' => 'Inventory updated!'];
    } catch (Exception $e) {
        $conn->rollback();
        $_SESSION['message'] = ['type' => 'error', 'title' => 'Duplicate Entry!', 'text' => $e->getMessage()];
    }
    header("Location: manageItem_tech.php"); exit();
}

// --- 6. LOGIC: DELETE ITEM ---
if (isset($_GET['delete_item_id'])) {
    $del_id = (int)$_GET['delete_item_id'];
    try {
        $conn->begin_transaction();
        $check = $conn->query("SELECT COUNT(*) FROM reservation_assets WHERE asset_id IN (SELECT asset_id FROM assets WHERE item_id=$del_id)")->fetch_row()[0];
        if ($check > 0) throw new Exception("Cannot delete. Item is in booking records.");
        $conn->query("DELETE FROM assets WHERE item_id=$del_id");
        $conn->query("DELETE FROM item WHERE item_id=$del_id");
        $conn->commit();
        $_SESSION['message'] = ['type' => 'success', 'title' => 'Deleted', 'text' => 'Item deleted.'];
    } catch (Exception $e) {
        $conn->rollback();
        $_SESSION['message'] = ['type' => 'error', 'title' => 'Failed', 'text' => $e->getMessage()];
    }
    header("Location: manageItem_tech.php"); exit();
}

// --- 7. FINAL DATA FETCHING (DENGAN AVAILABLE UNITS) ---
$categories = $conn->query("SELECT * FROM categories ORDER BY category_name ASC")->fetch_all(MYSQLI_ASSOC);

$query = "
    SELECT 
        i.*, 
        c.category_name, 
        COUNT(a.asset_id) as total_units,
        SUM(CASE WHEN a.status = 'Available' THEN 1 ELSE 0 END) as available_units
    FROM item i 
    JOIN categories c ON i.category_id = c.category_id 
    LEFT JOIN assets a ON i.item_id = a.item_id 
    GROUP BY i.item_id 
    ORDER BY i.item_name ASC
";
$item_details = $conn->query($query)->fetch_all(MYSQLI_ASSOC);

function get_reservation_item_count($conn, $status) {
    $sql = "SELECT COUNT(id) AS count FROM reservation_items WHERE status = ?";
    
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        error_log("Prepare failed: (" . $conn->errno . ") " . $conn->error);
        return 0;
    }
    
    $stmt->bind_param("s", $status);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    
    return $result ? (int) $result['count'] : 0;
}


$pending_count_for_badge = get_reservation_item_count($conn, 'Pending');

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Inventory — UniKL Technician</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary-color: #06b6d4; 
            --primary-hover: #0891b2; 
            --danger-color: #ef4444; 
            --bg-light-gray: #f8fafc;
            --card-bg: #ffffff;
            --text-dark: #1e293b; 
            --text-muted: #64748b; 
            --border-color: #e5e7eb;
        }

        body { font-family: 'Inter', sans-serif; background-color: var(--bg-light-gray); color: #334155; min-height: 100vh; margin: 0; }
        
        /* SIDEBAR */
        .sidebar { 
            width: 250px; position: fixed; top: 0; bottom: 0; left: 0; 
            background: var(--card-bg); padding: 20px; 
            border-right: 1px solid var(--border-color); z-index: 1050; 
            display: flex; flex-direction: column; transition: transform 0.3s ease-in-out;
        }
        .sidebar-header { display: flex; align-items: center; gap: 12px; margin-bottom: 30px; }
        .logo-icon { width: 40px; height: 40px; background-color: var(--primary-color); color: white; border-radius: 8px; display: flex; align-items: center; justify-content: center; font-size: 20px; }
        .logo-text strong { display: block; font-size: 16px; color: var(--text-dark); }
        .logo-text span { font-size: 12px; color: #94a3b8; }
        
        .sidebar a { display: flex; align-items: center; gap: 12px; color: var(--text-muted); text-decoration: none; padding: 12px 15px; margin-bottom: 8px; border-radius: 8px; font-weight: 500; transition: 0.2s; }
        .sidebar a.active, .sidebar a:hover { background: var(--primary-color); color: #fff; }
        .sidebar a.logout-link { color: var(--danger-color); margin-top: auto; font-weight: 600; }
        .sidebar a.logout-link:hover { background: var(--danger-color); color: #fff; }

        /* MAIN CONTENT */
        .main-content { margin-left: 250px; transition: margin 0.3s; }
        .topbar { background: var(--card-bg); padding: 15px 30px; display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid var(--border-color); }
        
        /* MODERN ACTION BUTTONS */
        .action-btn {
            width: 36px; height: 36px; border-radius: 10px;
            display: inline-flex; align-items: center; justify-content: center;
            transition: 0.2s; border: 1px solid transparent; margin: 0 2px;
            text-decoration: none; box-shadow: 0 2px 4px rgba(0,0,0,0.05);
        }
        .btn-view { background-color: #ecfeff; color: #0891b2; border-color: #cffafe; }
        .btn-view:hover { background-color: #0891b2; color: white; }
        .btn-edit { background-color: #fffbeb; color: #d97706; border-color: #fef3c7; }
        .btn-edit:hover { background-color: #d97706; color: white; }
        .btn-delete { background-color: #fef2f2; color: #dc2626; border-color: #fee2e2; }
        .btn-delete:hover { background-color: #dc2626; color: white; }

        /* RESPONSIVE & OVERLAY */
        .menu-toggle { font-size: 22px; cursor: pointer; color: var(--text-dark); display: none; }
        .sidebar-overlay { display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.4); z-index: 1040; }

        @media (max-width: 992px) {
            .sidebar { transform: translateX(-100%); }
            .sidebar.active { transform: translateX(0); }
            .main-content { margin-left: 0; }
            .menu-toggle { display: block; }
            .sidebar-overlay.active { display: block; }
            .topbar { padding: 15px 20px; }
            .topbar h3 { font-size: 18px; margin-left: 10px; }
        }
    </style>
</head>
<body>

<div class="sidebar-overlay" id="sidebarOverlay"></div> 
<div class="sidebar" id="admin-sidebar">
    <div>
        <div class="sidebar-header">
            <div class="logo-icon"><i class="fa-solid fa-wrench"></i></div>
            <div class="logo-text"><strong>UniKL Technician</strong><span>System Support</span></div>
        </div>
        <a href="dashboard_tech.php"><i class="fa-solid fa-table-columns"></i> Dashboard</a>
        <a href="check_out.php" ><i class="fa-solid fa-dolly"></i> Manage Requests</a>
        <a href="manageItem_tech.php" class="active"><i class="fa-solid fa-box-archive"></i> Manage Items</a>
        <a href="report.php"><i class="fa-solid fa-chart-line"></i> Report</a>
    </div>
    <a href="logout.php" class="logout-link"><i class="fa-solid fa-right-from-bracket"></i> Logout</a>
</div>


<div class="main-content">
    <div class="topbar">
        <div class="d-flex align-items-center">
            <i class="fa fa-bars menu-toggle" id="sidebarToggle"></i>
            <h3 class="mb-0 ms-2 fw-bold">Inventory Management</h3>
        </div>
        <div class="d-flex align-items-center gap-2">
            <button class="btn btn-outline-info btn-sm fw-medium px-3 rounded-pill" data-bs-toggle="modal" data-bs-target="#categoryModal">
                <i class="fa fa-list-ul me-1"></i> Categories
            </button>
<span class="user-name"><?= $displayName ?></span>
            <div class="user-profile ms-2">
                <a href="profile_admin.php" class="text-secondary"><i class="fa-solid fa-circle-user fa-2x"></i></a>
            </div>
        </div>
    </div>

    <div class="container-fluid py-4">
        <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-3">
            <div>
                <h4 class="fw-bold mb-0">Equipment List</h4>
                <p class="text-muted small">Manage your tech assets and stock levels.</p>
            </div>
            <a href="addItem_tech.php" class="btn btn-primary px-4 py-2 rounded-3 shadow-sm">
                <i class="fa fa-plus-circle me-2"></i> Add New Item
            </a>
        </div>

        <div class="card border-0 shadow-sm rounded-4 overflow-hidden p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="bg-light">
                        <tr>
                            <th class="ps-4 py-3">Item Type</th>
                            <th>Category</th>
                            <th class="text-center">Total</th>
                            <th class="text-center">Available</th>
                            <th class="text-end pe-4">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($item_details)): foreach($item_details as $item): ?>
                        <tr>
                            <td class="ps-4">
                                <div class="d-flex align-items-center">
                                    <img src="../<?= htmlspecialchars($item['image_url'] ?: 'assets/default.png') ?>" class="rounded-3 me-3" style="width: 45px; height: 45px; object-fit: cover; border: 1px solid #eee;">
                                    <span class="fw-semibold"><?= htmlspecialchars($item['item_name']) ?></span>
                                </div>
                            </td>
                            <td><span class="badge bg-light text-secondary border px-3 py-2 rounded-pill"><?= htmlspecialchars($item['category_name']) ?></span></td>
                            <td class="text-center fw-bold"><?= $item['total_units'] ?></td>
                            <td class="text-center"><span class="badge bg-success-subtle text-success px-3 py-2 rounded-pill"><?= $item['available_units'] ?></span></td>
                            <td class="text-end pe-4">
                                <div class="d-flex justify-content-end gap-1">
                                    <a href="assets_technician.php?item_id=<?= $item['item_id'] ?>" class="action-btn btn-view" title="View"><i class="fa fa-eye small"></i></a>
                                    <button type="button" class="action-btn btn-edit" onclick='openEditItemModal(<?= json_encode($item) ?>)' title="Edit"><i class="fa fa-pen small"></i></button>
                                    <button type="button" class="action-btn btn-delete" onclick="deleteItem(<?= $item['item_id'] ?>, '<?= addslashes($item['item_name']) ?>')" title="Delete"><i class="fa-solid fa-trash-can small"></i></button>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; else: ?>
                        <tr><td colspan="5" class="text-center py-5 text-muted">No items found.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="categoryModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content rounded-4 border-0 shadow">
            <div class="modal-header">
                <h5 class="modal-title fw-bold">Manage Categories</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="row">
                    <div class="col-md-5 border-end">
                        <form method="post" action="manageItem_tech.php">
                            <input type="hidden" name="add_category" value="1">
                            <div class="mb-3">
                                <label class="form-label fw-bold">New Category</label>
                                <input type="text" class="form-control rounded-3" name="category_name" placeholder="e.g. Laptop" required>
                            </div>
                            <button type="submit" class="btn btn-primary w-100 rounded-3">Add Category</button>
                        </form>
                    </div>
                    <div class="col-md-7 ps-md-4">
                        <div class="list-group" style="max-height: 300px; overflow-y: auto;">
                            <?php if (!empty($categories)): foreach($categories as $cat): ?>
                                <div class="list-group-item d-flex justify-content-between align-items-center border-0 border-bottom">
                                    <span><?= htmlspecialchars($cat['category_name']) ?></span>
                                    <div>
                                        <button class="btn btn-sm text-warning" onclick='openEditCategoryModal(<?= json_encode($cat) ?>)'><i class="fa fa-edit"></i></button>
                                        <button class="btn btn-sm text-danger" onclick="deleteCategory(<?= $cat['category_id'] ?>, '<?= addslashes($cat['category_name']) ?>')"><i class="fa fa-trash"></i></button>
                                    </div>
                                </div>
                            <?php endforeach; endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="editItemModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content rounded-4 border-0 shadow">
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title fw-bold">Edit Item Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="post" action="manageItem_tech.php" enctype="multipart/form-data">
                <div class="modal-body">
                    <input type="hidden" name="edit_item_type" value="1">
                    <input type="hidden" id="edit_item_id" name="edit_item_id">
                    
                    <div class="mb-3">
                        <label class="form-label small fw-bold">Item Name</label>
                        <input type="text" id="edit_item_name" name="edit_item_name" class="form-control rounded-3" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-bold">Category</label>
                        <select id="edit_category_id_select" name="edit_category_id" class="form-select rounded-3">
                            <?php foreach($categories as $cat): ?>
                                <option value="<?= $cat['category_id'] ?>"><?= htmlspecialchars($cat['category_name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="row g-2 mb-3">
                        <div class="col-md-6">
                            <label class="form-label small fw-bold">Brand</label>
                            <input type="text" name="batch_brand" class="form-control rounded-3" placeholder="e.g. Epson">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-bold">Model/Series</label>
                            <input type="text" name="batch_model" class="form-control rounded-3" placeholder="e.g. EB-X06">
                        </div>
                    </div>

<div class="p-3 bg-light rounded-3 mb-3">
    <label class="form-label small fw-bold">Add New Units (Quantity)</label>
    <input type="number" id="edit_item_quantity" name="edit_item_quantity" class="form-control mb-2 rounded-3" min="0" value="0">
    
    <div class="form-check small">
        <input type="checkbox" class="form-check-input" id="edit_enable_manual_code" name="enable_manual_code">
        <label class="form-check-label" for="edit_enable_manual_code">Manual asset codes for new units</label>
    </div>
</div>

<div id="edit_manual_assets_container" style="display: none;" class="mt-2 border-top pt-2">
    <p class="small fw-bold text-primary mb-2">Enter New Asset Codes:</p>
    <div id="edit_dynamic_asset_inputs" class="row g-2"></div>
</div>
                </div>

                <div class="modal-footer border-0">
                    <button type="button" class="btn btn-light rounded-pill px-4" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="edit_item_type_btn" class="btn btn-primary rounded-pill px-4">Update Item</button>
                </div>
            </form>
        </div>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<script>
// 1. Letak fungsi ini di paling ATAS (Global Scope)
function updateEditAssetInputs() {
    const editQtyInput = document.getElementById('edit_item_quantity');
    const editCheckbox = document.getElementById('edit_enable_manual_code');
    const editContainer = document.getElementById('edit_manual_assets_container');
    const editDynamicInputs = document.getElementById('edit_dynamic_asset_inputs');

    if (!editQtyInput || !editCheckbox) return; // Guard clause

    const qty = parseInt(editQtyInput.value) || 0;

    if (editCheckbox.checked && qty > 0) {
        editContainer.style.display = 'block';
        let html = '';
        for (let i = 1; i <= Math.min(qty, 50); i++) {
            html += `
                <div class="col-6 mb-2">
                    <input type="text" name="manual_codes[]" class="form-control form-control-sm" placeholder="Code #${i}" required>
                </div>`;
        }
        editDynamicInputs.innerHTML = html;
    } else {
        editContainer.style.display = 'none';
        editDynamicInputs.innerHTML = '';
    }
}

// 2. Fungsi Buka Modal (Global Scope)
function openEditItemModal(item) {
    document.getElementById('edit_item_id').value = item.item_id;
    document.getElementById('edit_item_name').value = item.item_name;
    document.getElementById('edit_category_id_select').value = item.category_id;
    
    // Reset inputs
    const qtyInput = document.getElementById('edit_item_quantity');
    const checkbox = document.getElementById('edit_enable_manual_code');
    
    qtyInput.value = 0; 
    checkbox.checked = false;
    
    // SEKARANG fungsi ni dah boleh dipanggil sbb dua-dua kat luar
    updateEditAssetInputs(); 
    
    var myModal = new bootstrap.Modal(document.getElementById('editItemModal'));
    myModal.show();
}

// 3. Logik Sidebar & Event Listeners (Dalam DOMContentLoaded)
document.addEventListener('DOMContentLoaded', function() {
    // Sidebar logic
    const sidebarToggle = document.getElementById('sidebarToggle');
    const sidebar = document.getElementById('admin-sidebar');
    const overlay = document.getElementById('sidebarOverlay'); // Ikut ID kat HTML kau

    if (sidebarToggle) {
        sidebarToggle.addEventListener('click', function() {
            sidebar.classList.toggle('active');
            overlay.classList.toggle('active');
        });
    }

    if (overlay) {
        overlay.addEventListener('click', function() {
            sidebar.classList.remove('active');
            overlay.classList.remove('active');
        });
    }

    // Pasang listener pada input manual
    const editQtyInput = document.getElementById('edit_item_quantity');
    const editCheckbox = document.getElementById('edit_enable_manual_code');

    if (editQtyInput) editQtyInput.addEventListener('input', updateEditAssetInputs);
    if (editCheckbox) editCheckbox.addEventListener('change', updateEditAssetInputs);
});

// 4. Delete logic
function deleteItem(id, name) {
    Swal.fire({
        title: 'Are you sure?',
        text: "Delete " + name + " and all units?",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#ef4444',
        confirmButtonText: 'Yes, delete it!'
    }).then((result) => {
        if (result.isConfirmed) {
            window.location.href = 'manageItem_tech.php?delete_item_id=' + id;
        }
    });
}
</script>
</body>
</html>


