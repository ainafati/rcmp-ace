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

// --- 5. LOGIC: EDIT ITEM & ADD UNITS ---
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['edit_item_type'])) {
    $item_id = (int)$_POST['edit_item_id'];
    $item_name = ucwords(trim($_POST['edit_item_name']));
    $category_id = (int)$_POST['edit_category_id'];
    $qty_to_add = (int)($_POST['edit_item_quantity'] ?? 0);
    $batch_brand = trim($_POST['batch_brand'] ?? '');
    $batch_model = trim($_POST['batch_model'] ?? '');

    $conn->begin_transaction();
    try {
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
            $insert_asset = $conn->prepare("INSERT INTO assets (item_id, asset_code, status) VALUES (?, ?, 'Available')");

            if (isset($_POST['enable_manual_code']) && !empty($_POST['manual_codes'])) {
                foreach ($_POST['manual_codes'] as $m_code) {
                    $m_code = trim($m_code);
                    if (empty($m_code)) continue;

                    // SEMAK JIKA KOD DAH ADA DALAM DB
                    $check_stmt->bind_param("s", $m_code);
                    $check_stmt->execute();
                    $result = $check_stmt->get_result();
                    
                    if ($result->num_rows > 0) {
                        // Jika jumpa, batalkan semua proses (rollback) dan lempar error
                        throw new Exception("Asset Code '$m_code' already exists in database!");
                    }
                    
                    $insert_asset->bind_param("is", $item_id, $m_code);
                    $insert_asset->execute();
                }
            
            } else {
                $curr_count = $conn->query("SELECT COUNT(*) FROM assets WHERE item_id=$item_id")->fetch_row()[0];
                for ($i = 1; $i <= $qty_to_add; $i++) {
                    $auto_code = date('Y') . str_pad($curr_count + $i, 3, '0', STR_PAD_LEFT);
                    $insert_asset->bind_param("isss", $item_id, $auto_code, $batch_brand, $batch_model);
                    $insert_asset->execute();
                }
            }
        }
        $conn->commit();
        $_SESSION['message'] = ['type' => 'success', 'title' => 'Updated', 'text' => 'Inventory updated!'];
    } catch (Exception $e) {
        $conn->rollback(); // Batalkan semua kalau ada satu kod pun yang duplicate
        $_SESSION['message'] = [
            'type' => 'error', 
            'title' => 'Duplicate Entry!', 
            'text' => $e->getMessage() 
        ];
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

        /* BASE & TYPOGRAPHY */
        body { font-family: 'Inter', 'Segoe UI', sans-serif; background-color: var(--bg-light-gray); color: #334155; min-height: 100vh; }
        
        /* SIDEBAR */
        .sidebar { 
            width: 250px; position: fixed; top: 0; bottom: 0; left: 0; 
            background: var(--card-bg); padding: 20px; 
            border-right: 1px solid var(--border-color); z-index: 1000; 
            display: flex; flex-direction: column; justify-content: space-between; 
        }
        .sidebar-header { display: flex; align-items: center; gap: 12px; margin-bottom: 30px; }
        
        /* LOGO ICON (Using --primary-color) */
        .logo-icon { 
            width: 40px; height: 40px; 
            background-color: var(--primary-color); /* Cyan */
            color: white; border-radius: 8px; 
            display: flex; align-items: center; justify-content: center; font-size: 20px; 
        }
        
        .logo-text strong { display: block; font-size: 16px; color: var(--text-dark); }
        .logo-text span { font-size: 12px; color: #94a3b8; }
        
        .sidebar a { 
            display: flex; align-items: center; gap: 12px; 
            color: var(--text-muted); text-decoration: none; padding: 12px 15px; 
            margin-bottom: 8px; border-radius: 8px; font-weight: 500; font-size: 15px; transition: all 0.2s ease-in-out; 
        }
        
        /* ACTIVE & HOVER LINK (Using --primary-color) */
        .sidebar a.active, .sidebar a:hover { 
            background: var(--primary-color); /* Cyan */
            color: #fff; 
        }

        /* LOGOUT LINK */
        .sidebar a.logout-link { 
            color: var(--danger-color); 
            font-weight: 600; 
            margin-top: auto; 
        } 
		
		/* Dalam Bahagian 2, di dalam tag <style> */

/* LOGOUT LINK */
.sidebar a.logout-link { 
    color: var(--danger-color); 
    font-weight: 600; 
    margin-top: 10px; /* Tukar 10px ini jika ada */
}
/* Tambah atau pastikan baris ini ada: */
.sidebar > a.logout-link { /* Guna selector yang lebih spesifik jika perlu */
    margin-top: auto; /* KUNCI UNTUK MENDAPATKANNYA DI BAWAH */
}
        .sidebar a.logout-link:hover { 
            color: #fff; 
            background: var(--danger-color); 
        }
        
        /* SIDEBAR BADGE STYLE */
        .sidebar a .badge {
            margin-left: auto; 
            font-size: 0.75rem;
            padding: 0.4em 0.6em;
            font-weight: 700;
            border-radius: 10px;
            background-color: var(--danger-color); 
            color: white;
        }

        /* Badge on Active/Hover state */
        .sidebar a.active .badge, .sidebar a:hover .badge {
            background-color: #ffffff;
            color: var(--danger-color); 
        }

        /* MAIN CONTENT & TOPBAR */
        .main-content { margin-left: 250px; }
        .topbar { background: var(--card-bg); padding: 15px 30px; display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid var(--border-color); } 
        .topbar h3 { font-weight: 600; margin: 0; color: var(--text-dark); font-size: 22px; }
        .topbar .user-profile { display: flex; align-items: center; gap: 12px; }
        .topbar .user-name { font-weight: 600; font-size: 15px; color: #334155; }
        .container-fluid { padding: 30px; }
        
        /* CARD & TABLE */
        .card { border-radius: 16px; padding: 25px; box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05); background: var(--card-bg); margin-bottom: 25px; border: 1px solid #e2e8f0; }
        .card h5, .modal-title { font-weight: 600; color: var(--text-dark); }
        .table thead th { background: var(--bg-light-gray); color: var(--text-muted); border: none; font-weight: 600; text-transform: uppercase; font-size: 12px; }
        .table tbody td { border-bottom: 1px solid #f1f5f9; }
        .table tbody tr:last-child td { border-bottom: none; }
        .badge.rounded-pill { padding: .4em .8em; font-weight: 500; }
        
        /* FORM & BUTTONS */
        .form-label { font-weight: 500; color: #334155; }
        .form-control, .form-select { border-radius: 8px; }
        .btn { border-radius: 8px; padding: 10px 20px; font-weight: 500; }
        
        /* PRIMARY BUTTON (Using --primary-color and --primary-hover) */
        .btn-primary { 
            background-color: var(--primary-color); /* Cyan */
            border: none; 
        }
        .btn-primary:hover { 
            background-color: var(--primary-hover); /* Darker Cyan */
        }
        
        .item-img-sm { width: 40px; height: 40px; object-fit: cover; border-radius: 8px; margin-right: 15px; }
        
        /* Hide menu toggle on desktop */
        .menu-toggle {
            display: none;
        }

        /* --- MOBILE VIEW CODE (RESPONSIVE) STARTS --- */
        @media (max-width: 992px) {
            .sidebar {
                transform: translateX(-100%);
                transition: transform 0.3s ease-in-out;
                width: 280px; 
                display: block; 
            }

            /* New class for active mobile sidebar */
            .sidebar.active {
                transform: translateX(0);
                box-shadow: 4px 0 10px rgba(0,0,0,0.1);
            }

            /* Main content uses 100% width, no left margin */
            .main-content {
                margin-left: 0;
            }
            
            /* Add top padding for topbar clearance */
            body {
                padding-top: 70px;
            }

            /* Fix topbar to the top */
            .topbar {
                position: fixed; 
                top: 0; 
                left: 0; 
                right: 0;
                z-index: 999;
                padding: 15px 20px;
                flex-wrap: wrap; 
                gap: 10px;
            }
            .topbar h3 {
                font-size: 20px;
                margin-left: 15px; 
                flex-grow: 1; 
            }

            /* Show menu toggle on mobile */
            .menu-toggle {
                display: block !important; 
                font-size: 24px;
                cursor: pointer;
                color: #334155;
            }

            /* Ensure form (col-lg-4) is stacked above table (col-lg-8) on mobile */
            .col-lg-4, .col-lg-8 {
                width: 100% !important;
            }

            /* Adjust container padding */
            .container-fluid {
                padding: 20px;
            }

            /* Table: Ensure horizontal scrollability */
            .table-responsive {
                overflow-x: auto;
            }
        }
        /* --- MOBILE VIEW CODE (RESPONSIVE) ENDS --- */
    </style>
</head>
<body>
<?php 

if (isset($_SESSION['message'])):
    $message = $_SESSION['message'];
    $type = $message['type'] === 'success' ? 'success' : 'error';
    $text = $message['text'];
    
    unset($_SESSION['message']);
    
    echo "<script>
        document.addEventListener('DOMContentLoaded', function() {
            Swal.fire({
                icon: '{$type}',
                title: 'Operation Status', 
                text: '{$text}', 
                showConfirmButton: true
            });
        });
    </script>";
endif;
?>

<div class="sidebar" id="offcanvasSidebar">
    <div>
        <div class="sidebar-header">
            <div class="logo-icon"><i class="fa-solid fa-wrench"></i></div>
            <div class="logo-text"><strong>UniKL Technician</strong><span>System Support</span></div>
        </div>
        <a href="dashboard_tech.php"><i class="fa-solid fa-table-columns"></i> Dashboard</a>
        <a href="check_out.php">
            <i class="fa-solid fa-dolly"></i> Manage Requests
            <?php if ($pending_count_for_badge > 0): ?>
                <span class="badge rounded-pill bg-danger"><?= $pending_count_for_badge ?></span>
            <?php endif; ?>
        </a>
        <a href="manageItem_tech.php" class="active"><i class="fa-solid fa-box-archive"></i> Manage Items</a>
        <a href="report.php"><i class="fa-solid fa-chart-line"></i> Report</a>
    </div>
    <a href="logout.php" class="logout-link"><i class="fa-solid fa-right-from-bracket"></i> Logout</a>
</div>

<div class="main-content">
    <div class="topbar">
        <i class="fa fa-bars menu-toggle" id="sidebarToggle"></i>
        
        <h3>Inventory Management</h3>
        <div class="d-flex align-items-center gap-3">
            <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#categoryModal"><i class="fa fa-list me-2"></i> Manage Categories</button>
            <div class="user-profile">
<span class="user-name me-2" style="text-transform: capitalize; font-weight: 600;">
    <?= htmlspecialchars($displayName) ?>
</span>            
                <a href="profile_tech.php" title="Go to My Profile" style="color: inherit; text-decoration: none;">
                    <i class="fa-solid fa-user-circle fa-2x text-secondary"></i>
                </a>
            </div>
        </div>
    </div>
	
    <div class="container-fluid">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <p class="text-muted small">Manage your equipment types and stock levels.</p>
            </div>
            <a href="addItem_tech.php" class="btn btn-primary px-4 shadow-sm">
                <i class="fa fa-plus-circle me-2"></i> Add New Item
            </a>
        </div>

        <div class="card">
            <div class="table-responsive">
                <table class="table table-hover align-middle">
                    <thead class="table-light">
                        <tr>
                            <th>Item Type</th>
                            <th>Category</th>
                            <th class="text-center">Total Units</th>
                            <th class="text-center">Available</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($item_details)): ?>
                            <tr><td colspan="5" class="text-center py-5 text-muted">No items found.</td></tr>
                        <?php else: foreach($item_details as $item): ?>
                            <tr>
                                <td>
                                    <div class="d-flex align-items-center">
                                        <?php if (!empty($item['image_url'])): ?>
                                            <img src="../<?= htmlspecialchars($item['image_url']) ?>" class="item-img-sm">
                                        <?php endif; ?>
                                        <strong><?= ucwords(htmlspecialchars($item['item_name'])) ?></strong>
                                    </div>
                                </td>
                                <td><span class="badge bg-light text-dark border"><?= htmlspecialchars($item['category_name']) ?></span></td>
                                <td class="text-center"><span class="fw-bold"><?= $item['total_units'] ?></span></td>
                                <td class="text-center"><span class="badge rounded-pill bg-success"><?= $item['available_units'] ?></span></td>
                                <td class="text-end">
                                    <div class="btn-group">
                                        <a href="assets_technician.php?item_id=<?= $item['item_id'] ?>" class="btn btn-sm btn-outline-info"><i class="fa fa-eye"></i></a>
                                        <button class="btn btn-sm btn-outline-warning" onclick='openEditItemModal(<?= json_encode($item) ?>)'><i class="fa fa-edit"></i></button>
                                        <button class="btn btn-sm btn-outline-danger" onclick="deleteItem(<?= $item['item_id'] ?>, '<?= addslashes($item['item_name']) ?>')"><i class="fa fa-trash"></i></button>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="categoryModal" tabindex="-1" aria-labelledby="categoryModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="categoryModalLabel">Manage Categories</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="row">
                    <div class="col-md-5">
                        <h6>Add New Category</h6>
                        <hr>
                        <form method="post" action="manageItem_tech.php">
                            <input type="hidden" name="add_category" value="1">
                            <div class="mb-3">
                                <label for="category_name" class="form-label">Category Name</label>
                                <input type="text" class="form-control" id="category_name" name="category_name" required>
                            </div>
                            <button type="submit" class="btn btn-primary w-100">Add Category</button>
                        </form>
                    </div>
                    <div class="col-md-7">
                        <h6>Existing Categories</h6>
                        <hr>
                        <div class="list-group" style="max-height: 300px; overflow-y: auto;">
                            <?php if (empty($categories)): ?>
                                <p class="text-center text-muted">No categories found.</p>
                            <?php else: foreach($categories as $cat): ?>
                                <div class="list-group-item d-flex justify-content-between align-items-center">
                                    <div class="d-flex align-items-center">
                                        <span><?= htmlspecialchars($cat['category_name']) ?></span>
                                    </div>
                                    <div>
                                        <button class="btn btn-sm btn-outline-warning" onclick='openEditCategoryModal(<?= htmlspecialchars(json_encode($cat), ENT_QUOTES, 'UTF-8') ?>)'><i class="fa fa-edit"></i></button>
                                        <button class="btn btn-sm btn-outline-danger" onclick="deleteCategory(<?= $cat['category_id'] ?>, '<?= htmlspecialchars(addslashes($cat['category_name']), ENT_QUOTES, 'UTF-8') ?>')"><i class="fa fa-trash"></i></button>
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

<div class="modal fade" id="editCategoryModal" tabindex="-1" aria-labelledby="editCategoryModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="editCategoryModalLabel">Edit Category</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="post" action="manageItem_tech.php">
                <div class="modal-body">
                    <input type="hidden" name="edit_category" value="1">
                    <input type="hidden" id="edit_category_id" name="edit_category_id">
                    <div class="mb-3">
                        <label for="edit_category_name" class="form-label">Category Name</label>
                        <input type="text" class="form-control" id="edit_category_name" name="edit_category_name" required>
                    </div>
                    </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save Changes</button>
                </div>
            </form>
        </div>
    </div>
</div>


<div class="modal fade" id="editItemModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Edit Item Type</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="post" action="manageItem_tech.php" enctype="multipart/form-data">
                <div class="modal-body">
                    <input type="hidden" name="edit_item_type" value="1">
                    <input type="hidden" id="edit_item_id" name="edit_item_id">

                    <div class="mb-3">
                        <label class="form-label">Item Name</label>
                        <input type="text" id="edit_item_name" name="edit_item_name" class="form-control" required>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">New Item Image (Optional)</label>
                        <input type="file" name="edit_item_image" class="form-control" accept="image/*">
                        <small class="text-muted">Upload a new image to replace the old one.</small>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Category</label>
                        <select id="edit_category_id_select" name="edit_category_id" class="form-select" required>
                            <?php foreach($categories as $cat): ?>
                                <option value="<?= $cat['category_id'] ?>"><?= htmlspecialchars($cat['category_name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>


                    <hr>
                    <h6 class="mt-3">Add More Units (Optional)</h6>
                    <p class="small text-muted">Kuantiti baru akan ditambah ke dalam sistem untuk item ini.</p>
                    
                    <div class="mb-3">
                        <label class="form-label">Number of New Units to Add</label>
<input type="number" id="edit_item_quantity" name="edit_item_quantity" class="form-control" min="0" value="0" required>
                    </div>

                    <div class="mb-3 form-check">
                        <input type="checkbox" class="form-check-input" id="edit_enable_manual_code" name="enable_manual_code">
                        <label class="form-check-label" for="edit_enable_manual_code">I want to enter asset codes manually for these new units</label>
                    </div>

                    <div id="edit_manual_assets_container" style="display: none;">
                        <label class="form-label text-primary fw-bold">Enter Manual Codes:</label>
                        <div id="edit_dynamic_asset_inputs"></div>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Brand for New Units</label>
                            <input type="text" id="edit_item_brand" name="batch_brand" class="form-control" placeholder="e.g., Dell">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Model for New Units</label>
                            <input type="text" id="edit_item_model" name="batch_model" class="form-control" placeholder="e.g., Latitude 5420">
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save Changes</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<script>
document.addEventListener('DOMContentLoaded', function() {
    
    // --- 1. SWEETALERT PHP NOTIFICATION ---
    <?php if (isset($_SESSION['message'])): ?>
        Swal.fire({
            icon: '<?= $_SESSION['message']['type'] ?>',
            title: '<?= addslashes($_SESSION['message']['title'] ?? "Notification") ?>',
            text: '<?= addslashes($_SESSION['message']['text']) ?>',
            confirmButtonColor: '#06b6d4',
            timer: 4000,
            timerProgressBar: true
        });
        <?php unset($_SESSION['message']); ?>
    <?php endif; ?>

    // --- 2. SIDEBAR TOGGLE (MOBILE) ---
    const sidebarToggle = document.getElementById('sidebarToggle');
    const sidebar = document.getElementById('offcanvasSidebar');
    if (sidebarToggle && sidebar) {
        sidebarToggle.addEventListener('click', () => {
            sidebar.classList.toggle('active');
        });
    }

    // --- 3. DYNAMIC INPUTS FOR MANUAL ASSET CODES ---
    const editQtyInput = document.getElementById('edit_item_quantity');
    const editCheckbox = document.getElementById('edit_enable_manual_code');
    const editContainer = document.getElementById('edit_manual_assets_container');
    const editDynamicInputs = document.getElementById('edit_dynamic_asset_inputs');

    function updateEditAssetInputs() {
        if (!editDynamicInputs) return; 
        const qty = parseInt(editQtyInput.value) || 0;

        if (editCheckbox && editCheckbox.checked && qty > 0) {
            editContainer.style.display = 'block';
            let htmlContent = '';
            const safeQty = Math.min(qty, 50); 

            for (let i = 1; i <= safeQty; i++) {
                htmlContent += `
                    <div class="input-group mb-2">
                        <span class="input-group-text text-primary small">Unit ${i}</span>
                        <input type="text" name="manual_codes[]" class="form-control manual-code-input" 
                               placeholder="Enter Asset Code..." required>
                    </div>`;
            }
            editDynamicInputs.innerHTML = htmlContent;
            attachDuplicateChecker(); // Panggil checker lepas generate input
        } else {
            if (editContainer) editContainer.style.display = 'none';
            editDynamicInputs.innerHTML = '';
        }
    }

    // --- 4. ULTIMATE DUPLICATE CHECKER (AJAX + CLIENT SIDE) ---
    function attachDuplicateChecker() {
        const inputs = document.querySelectorAll('.manual-code-input');
        const saveBtn = document.querySelector('button[name="edit_item_type"]');

        inputs.forEach(input => {
            input.addEventListener('input', function() {
                const currentInput = this;
                const codeValue = currentInput.value.trim().toUpperCase();

                if (codeValue === '') {
                    currentInput.classList.remove('is-invalid', 'is-valid');
                    return;
                }

                // A. Check duplicate dalam list modal (Client-side)
                const allValues = Array.from(inputs).map(i => i.value.trim().toUpperCase());
                const selfDuplicate = allValues.filter(v => v === codeValue).length > 1;

                if (selfDuplicate) {
                    showStatus(currentInput, 'Duplicate in list!', 'error');
                    saveBtn.disabled = true;
                    return;
                }

                // B. Check duplicate dengan Database (AJAX)
                fetch('check_asset_code.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: 'asset_code=' + encodeURIComponent(codeValue)
                })
                .then(response => response.text())
                .then(data => {
                    if (data === 'exists') {
                        showStatus(currentInput, 'Already exists!', 'error');
                    } else {
                        showStatus(currentInput, 'Valid code & can be used', 'success');
                    }
                    
                    // Enable/Disable butang berdasarkan keadaan semua input
                    const anyInvalid = Array.from(inputs).some(i => i.classList.contains('is-invalid'));
                    saveBtn.disabled = anyInvalid;
                });
            });
        });
    }

    function showStatus(el, msg, type) {
        let feedback = el.nextElementSibling;
        if (!feedback || !feedback.classList.contains('dynamic-feedback')) {
            feedback = document.createElement('div');
            feedback.className = 'dynamic-feedback small mt-1';
            el.parentNode.appendChild(feedback);
        }

        if (type === 'error') {
            el.classList.add('is-invalid');
            el.classList.remove('is-valid');
            feedback.className = 'dynamic-feedback invalid-feedback d-block';
            feedback.innerText = msg;
        } else {
            el.classList.remove('is-invalid');
            el.classList.add('is-valid');
            feedback.className = 'dynamic-feedback valid-feedback d-block';
            feedback.innerText = msg;
        }
    }

    if (editQtyInput) editQtyInput.addEventListener('input', updateEditAssetInputs);
    if (editCheckbox) editCheckbox.addEventListener('change', updateEditAssetInputs);
});

// --- 5. GLOBAL MODAL FUNCTIONS ---
function openEditItemModal(item) {
    const modalElement = document.getElementById('editItemModal');
    if (!modalElement) return;

    document.getElementById('edit_item_id').value = item.item_id;
    document.getElementById('edit_item_name').value = item.item_name;
    document.getElementById('edit_category_id_select').value = item.category_id;
    
    const qtyInput = document.getElementById('edit_item_quantity');
    const manualCheck = document.getElementById('edit_enable_manual_code');
    const saveBtn = document.querySelector('button[name="edit_item_type"]');
    
    if(qtyInput) qtyInput.value = 0;
    if(manualCheck) manualCheck.checked = false;
    if(saveBtn) saveBtn.disabled = false; // Reset butang
    
    const container = document.getElementById('edit_manual_assets_container');
    if(container) container.style.display = 'none';

    document.getElementById('edit_item_brand').value = '';
    document.getElementById('edit_item_model').value = '';

    const myModal = new bootstrap.Modal(modalElement);
    myModal.show();
}

function deleteItem(id, name) {
    Swal.fire({
        title: 'Are you sure?',
        text: `Delete "${name}" and all its units?`,
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