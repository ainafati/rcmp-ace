<?php
session_start();
include '../config.php'; 
include_once '../logger.php'; 

$allowed_role = 'Admin'; 

if (!isset($_SESSION['person_id']) || !isset($_SESSION['logged_in_role']) || $_SESSION['logged_in_role'] !== $allowed_role) {
    header("Location: ../login.php");
    exit();
}

$person_id_session = (int)$_SESSION['person_id']; 
$admin_data = ['name' => 'Admin']; 

$stmt_admin = $conn->prepare("
    SELECT name 
    FROM person 
    WHERE person_id = ?
");

if (!$stmt_admin) {
    die("Database Error in Admin fetch: " . $conn->error);
}

$stmt_admin->bind_param("i", $person_id_session);
$stmt_admin->execute();
$result_admin = $stmt_admin->get_result();

if ($data = $result_admin->fetch_assoc()) {
    $admin_data = $data;
}
$stmt_admin->close();

// === LOGIK DISINI UNTUK SET DISPLAY NAME ===
$fullName = $admin_data['name'] ?? 'Admin';
$lowerName = strtolower($fullName);
$posBinti = strpos($lowerName, ' binti ');
$posBin = strpos($lowerName, ' bin ');

if ($posBinti !== false) {
    $shortName = substr($fullName, 0, $posBinti);
} elseif ($posBin !== false) {
    $shortName = substr($fullName, 0, $posBin);
} else {
    // Tambahan: Jika tiada bin/binti, ambil 2 perkataan pertama sahaja
    $parts = explode(' ', trim($fullName));
    $shortName = isset($parts[1]) ? $parts[0] . ' ' . $parts[1] : $parts[0];
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
    header("Location: manageItem_admin.php"); exit();
}

// --- 4. LOGIC: ADD ITEM & UNITS ---
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['add_item_type_and_units'])) {
    $item_name = ucwords(trim($_POST['item_name']));
    $category_id = (int)$_POST['category_id'];
    $quantity = (int)$_POST['quantity'];
    $batch_brand = trim($_POST['batch_brand'] ?? '');
    $batch_model = trim($_POST['batch_model'] ?? '');
    $manual_codes = $_POST['manual_codes'] ?? [];
    $manual_serials = $_POST['manual_serials'] ?? []; // Tambah ini
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
        
        // KEMASKINI: Tambah serial_number dalam query (isssss = 5 strings)
        $insert_asset = $conn->prepare("INSERT INTO assets (item_id, asset_code, brand, model, serial_number, status) VALUES (?, ?, ?, ?, ?, 'Available')");

        if ($is_manual) {
            foreach ($manual_codes as $index => $code) {
                $code = trim($code);
                if (empty($code)) continue;
                
                $s_no = isset($manual_serials[$index]) ? trim($manual_serials[$index]) : '';

                $check_stmt->bind_param("s", $code);
                $check_stmt->execute();
                if ($check_stmt->get_result()->num_rows > 0) throw new Exception("Code '$code' already exists!");
                
                // Bind 5 parameter
                $insert_asset->bind_param("issss", $new_item_id, $code, $batch_brand, $batch_model, $s_no);
                $insert_asset->execute();
            }
        } else {
            $acr = generateItemAcronym($item_name);
            for ($i = 1; $i <= $quantity; $i++) {
                $a_code = $acr . "-" . str_pad($i, 4, '0', STR_PAD_LEFT);
                // Untuk auto, SN kita samakan dengan Code buat sementara
                $insert_asset->bind_param("issss", $new_item_id, $a_code, $batch_brand, $batch_model, $a_code);
                $insert_asset->execute();
            }
        }
        $conn->commit();
        $_SESSION['message'] = ['type' => 'success', 'title' => 'Success', 'text' => 'Item added successfully!'];
    } catch (Exception $e) {
        $conn->rollback();
        $_SESSION['message'] = ['type' => 'error', 'title' => 'Duplicate Alert', 'text' => $e->getMessage()];
    }
    header("Location: manageItem_admin.php"); exit();
}

// --- 5. LOGIC: EDIT ITEM & ADD UNITS (FIXED) ---
if ($_SERVER["REQUEST_METHOD"] === "POST" && (isset($_POST['edit_item_type']) || isset($_POST['edit_item_type_btn']))) {
    $item_id = (int)$_POST['edit_item_id'];
    $item_name = ucwords(trim($_POST['edit_item_name']));
    $category_id = (int)$_POST['edit_category_id'];
    $qty_to_add = (int)($_POST['edit_item_quantity'] ?? 0);
    
    // Pastikan input manual codes dan serials ditangkap
    $manual_codes = $_POST['manual_codes'] ?? [];
    $manual_serials = $_POST['manual_serials'] ?? [];

    $conn->begin_transaction();
    try {
        // 1. Update info item (Sama seperti asal)
        $new_img = handleImageUpload('edit_item_image', 'assets/item_images');
        if ($new_img) {
            $upd = $conn->prepare("UPDATE item SET item_name=?, category_id=?, image_url=? WHERE item_id=?");
            $upd->bind_param("sisi", $item_name, $category_id, $new_img, $item_id);
        } else {
            $upd = $conn->prepare("UPDATE item SET item_name=?, category_id=? WHERE item_id=?");
            $upd->bind_param("sii", $item_name, $category_id, $item_id);
        }
        $upd->execute();

        // 2. Tambah Unit Baru jika Qty > 0
        if ($qty_to_add > 0) {
            // Ambil brand & model asal untuk unit baru (supaya tak kosong)
            $orig = $conn->query("SELECT brand, model FROM assets WHERE item_id=$item_id LIMIT 1")->fetch_assoc();
            $b_brand = $orig['brand'] ?? '';
            $b_model = $orig['model'] ?? '';

            $check_stmt = $conn->prepare("SELECT asset_code FROM assets WHERE asset_code = ?");
            $insert_asset = $conn->prepare("INSERT INTO assets (item_id, asset_code, brand, model, serial_number, status) VALUES (?, ?, ?, ?, ?, 'Available')");

            if (isset($_POST['enable_manual_code']) && !empty($manual_codes)) {
                foreach ($manual_codes as $idx => $m_code) {
                    $m_code = trim($m_code);
                    if (empty($m_code)) continue;
                    
                    $m_sn = isset($manual_serials[$idx]) ? trim($manual_serials[$idx]) : '';

                    $check_stmt->bind_param("s", $m_code);
                    $check_stmt->execute();
                    if ($check_stmt->get_result()->num_rows > 0) throw new Exception("Asset Code '$m_code' already exists!");
                    
                    $insert_asset->bind_param("issss", $item_id, $m_code, $b_brand, $b_model, $m_sn);
                    $insert_asset->execute();
                }
            } else {
                // Auto generate logic
                $curr_count = $conn->query("SELECT COUNT(*) FROM assets WHERE item_id=$item_id")->fetch_row()[0];
                for ($i = 1; $i <= $qty_to_add; $i++) {
                    $auto_code = date('Y') . str_pad($curr_count + $i, 3, '0', STR_PAD_LEFT);
                    $insert_asset->bind_param("issss", $item_id, $auto_code, $b_brand, $b_model, $auto_code);
                    $insert_asset->execute();
                }
            }
        }
        $conn->commit();
        $_SESSION['message'] = ['type' => 'success', 'title' => 'Updated', 'text' => 'Inventory updated!'];
    } catch (Exception $e) {
        $conn->rollback();
        $_SESSION['message'] = ['type' => 'error', 'title' => 'Error', 'text' => $e->getMessage()];
    }
    header("Location: manageItem_admin.php"); exit();
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
    header("Location: manageItem_admin.php"); exit();
}

// --- LOGIC: DELETE CATEGORY ---
if (isset($_GET['delete_cat_id'])) {
    $cat_id = (int)$_GET['delete_cat_id'];
    
    // Check dulu kalau ada item dalam kategori ni
    $checkItem = $conn->query("SELECT COUNT(*) FROM item WHERE category_id = $cat_id")->fetch_row()[0];
    
    if ($checkItem > 0) {
        $_SESSION['message'] = ['type' => 'error', 'title' => 'Gagal', 'text' => 'The category cannot be deleted because it still contains items!'];
    } else {
        $stmt = $conn->prepare("DELETE FROM categories WHERE category_id = ?");
        $stmt->bind_param("i", $cat_id);
        if ($stmt->execute()) {
            $_SESSION['message'] = ['type' => 'success', 'title' => 'Success', 'text' => 'Category deleted!'];
        }
        $stmt->close();
    }
    header("Location: manageItem_admin.php"); exit();
}

// --- LOGIC: EDIT CATEGORY (Boleh terima POST dan GET) ---
if (isset($_GET['edit_cat_id']) && isset($_GET['new_name'])) {
    $cat_id = (int)$_GET['edit_cat_id'];
    $cat_name = trim($_GET['new_name']);
    
    if (!empty($cat_name)) {
        $stmt = $conn->prepare("UPDATE categories SET category_name = ? WHERE category_id = ?");
        $stmt->bind_param("si", $cat_name, $cat_id);
        if ($stmt->execute()) {
            $_SESSION['message'] = ['type' => 'success', 'title' => 'Success', 'text' => 'Category updated!'];
        }
        $stmt->close();
    }
    header("Location: manageItem_admin.php"); exit();
}// --- 7. FINAL DATA FETCHING (DENGAN AVAILABLE UNITS) ---
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
    <link rel="stylesheet" href="../css/style.css">
	
	<style>
	.main-content {
    /* Gunakan min-height supaya background sentiasa sekurang-kurangnya setinggi skrin */
    min-height: 100vh; 
    
    /* Warna latar belakang utama */
    background-color: #f1f5f9; 
    
    /* Pattern texture */
    background-image: url('https://www.transparenttextures.com/patterns/cubes.png');
    
    /* PENTING: Supaya pattern tidak bergerak bila kita scroll (nampak lebih kemas) */
    background-attachment: fixed;
    
    /* Tambah padding supaya content tidak rapat sangat dengan tepi skrin */
    padding: 2rem;
    
    /* Memastikan background meliputi seluruh ruang */
    background-repeat: repeat;
}

/* Fix untuk mobile supaya padding tidak terlalu besar */
@media (max-width: 768px) {
    .main-content {
        padding: 1rem;
    }
}

	
/* 1. Paksa SweetAlert duduk paling depan */
.swal2-container {
    z-index: 100001 !important; 
}

/* 2. Pastikan teks input warna gelap supaya nampak apa kita taip */
.swal2-input {
    color: #1e293b !important;
}
	/* Container styling */
.main-content {
    background-color: #f8fafc !important; /* Warna background grey lembut */
}

/* Card table styling */
.inventory-card {
    background: white;
    border-radius: 16px;
    border: 1px solid #e2e8f0;
    box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05);
}

/* Header table styling */
.table thead th {
    background: transparent;
    color: #64748b;
    font-size: 0.85rem;
    font-weight: 600;
    text-transform: none;
    letter-spacing: normal;
    padding: 20px 24px;
    border-bottom: 1px solid #f1f5f9;
}

/* Row styling */
.table tbody tr td {
    padding: 20px 24px;
    border-bottom: 1px solid #f1f5f9;
    color: #1e293b;
}

/* Icon box styling (Macam dalam gambar) */
.item-icon-box {
    width: 45px;
    height: 45px;
    background-color: #eef2ff; /* Biru cair lembut */
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #6366f1;
    font-size: 1.2rem;
}

/* Category Pill Styling */
.category-pill {
    background: white;
    border: 1px solid #e2e8f0;
    color: #475569;
    padding: 4px 16px;
    border-radius: 20px;
    font-size: 0.8rem;
    font-weight: 500;
}

/* Stock Pill Styling */
.stock-badge {
    padding: 6px 12px;
    border-radius: 8px;
    font-weight: 600;
    font-size: 0.85rem;
    min-width: 40px;
    display: inline-block;
}
.bg-stock-total { background-color: #f1f5f9; color: #475569; }
.bg-stock-avail { background-color: #f0fdf4; color: #16a34a; }

/* Action buttons styling */
.btn-action {
    color: #94a3b8;
    transition: all 0.2s;
    font-size: 1.1rem;
}
.btn-action:hover { color: #1e293b; transform: translateY(-1px); }
.btn-delete:hover { color: #ef4444; }

/* --- MOBILE BOTTOM NAV (THEMED DARK) --- */
@media (max-width: 991px) {
    body {
        padding-bottom: 80px; /* Ruang supaya content tak kena sorok dek bar */
    }

    .mobile-bottom-nav {
        display: flex !important;
        position: fixed;
        bottom: 0;
        left: 0;
        width: 100%;
        /* TUKAR: Warna gelap macam sidebar laptop */
        background: #1e293b !important; 
        border-top: 1px solid rgba(255, 255, 255, 0.1); 
        z-index: 10000;
        justify-content: space-around;
        padding: 12px 0;
        box-shadow: 0 -8px 25px rgba(0,0,0,0.2);
    }

    .mobile-bottom-nav a {
        /* Warna icon & teks masa tak aktif */
        color: #94a3b8 !important; 
        text-decoration: none !important;
        text-align: center;
        font-size: 11px;
        font-weight: 600;
        display: flex;
        flex-direction: column;
        align-items: center;
        gap: 4px;
        flex: 1;
        transition: 0.3s;
    }

    /* Warna Cyan bila menu aktif/tekan */
    .mobile-bottom-nav a.active {
        color: #06b6d4 !important;
    }

    .mobile-bottom-nav a i {
        font-size: 20px;
    }

    /* Tambah sikit effect bila user touch */
    .mobile-bottom-nav a:active {
        transform: scale(0.9);
        opacity: 0.8;
    }
}

    /* Sembunyikan kalau kat PC */
    @media (min-width: 992px) {
        .mobile-bottom-nav {
            display: none !important;
        }
    }
	
	.toast-container {
    z-index: 1060; /* Pastikan dia duduk atas sekali dari modal/sidebar */
}

.toast {
    border-radius: 12px;
    overflow: hidden;
    animation: slideInRight 0.5s ease-out;
}

@keyframes slideInRight {
    from { transform: translateX(100%); opacity: 0; }
    to { transform: translateX(0); opacity: 1; }
}

.toast-header {
    border-bottom: none;
}
</style>
	
</head>
<body>
<div class="sidebar-overlay" id="sidebarOverlay"></div> 
<div class="sidebar" id="admin-sidebar">
    <div> <div class="sidebar-header">
    <div class="logo-icon"><i class="fa-solid fa-wrench"></i></div>
    <div class="logo-text">
        <strong>UniKL Admin</strong>
        <span class="d-block">System Control</span> </div>
</div>
        
        <div class="sidebar-nav"> 
<a href="manageItem_admin.php"class="active" ><i class="fa-solid fa-box-archive"></i> Manage Items</a>
        <a href="manage_accounts.php" ><i class="fa-solid fa-users-cog"></i> Manage Accounts</a>
        <a href="report_admin.php" ><i class="fa-solid fa-chart-pie"></i> System Report</a>        </div>
    </div>
    
    <div class="sidebar-footer">
        <a href="logout.php" class="logout-link"><i class="fa-solid fa-sign-out-alt"></i> Logout</a> 
    </div>
</div>

    <div class="main-content">
        <div class="topbar">
            <div class="topbar-left d-flex align-items-center">
                <h3 class="mb-0 fw-bold">Inventory Management</h3>
            </div>

            <div class="topbar-right">
                <button class="btn btn-outline-primary btn-sm fw-medium px-3 rounded-pill me-3 shadow-sm" data-bs-toggle="modal" data-bs-target="#categoryModal">
                    <i class="fa fa-list-ul me-1"></i> Categories
                </button>

                <a href="profile_admin.php" class="user-pill text-decoration-none shadow-sm">
                    <div class="text-end me-2 d-none d-md-block">
                        <div class="user-name" style="text-transform: capitalize; font-weight: 600; color: #1e293b; line-height: 1;">
                            <?= htmlspecialchars($displayName) ?>
                        </div>
                        <small class="text-muted" style="font-size: 0.75rem;">Administrator</small>
                    </div>
                    <div class="profile-avatar">
                        <img src="https://ui-avatars.com/api/?name=<?= urlencode($displayName) ?>&background=06b6d4&color=fff" class="rounded-circle" width="35">
                    </div>
                </a>
            </div>
        </div>

        <div class="container-fluid py-4">
            <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-3">
                <div>
                    <h4 class="fw-bold mb-0 text-dark">Equipment List</h4>
                    <p class="text-muted small mb-0">Manage your tech assets and stock levels efficiently.</p>
                </div>
                <a href="add_item.php" class="btn btn-primary px-4 py-2 rounded-3 shadow-sm border-0" style="background: var(--primary-color);">
                    <i class="fa fa-plus-circle me-2"></i> Add New Item
                </a>
            </div>

            <div class="card border-0 shadow-sm rounded-4 overflow-hidden">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="bg-light">
                            <tr style="font-size: 0.8rem; text-transform: uppercase; letter-spacing: 0.5px;">
                                <th class="ps-4 py-3 border-0 text-muted">Item / Type</th>
                                <th class="border-0 text-muted">Category</th>
                                <th class="text-center border-0 text-muted">Total</th>
                                <th class="text-center border-0 text-muted">Available</th>
                                <th class="text-end pe-4 border-0 text-muted">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($item_details)): foreach($item_details as $item): ?>
                            <tr>
                                <td class="ps-4 py-3">
                                    <div class="d-flex align-items-center">
                                        <div class="position-relative">
                                            <img src="../<?= htmlspecialchars($item['image_url'] ?: 'assets/default.png') ?>" 
                                                 class="rounded-3 shadow-sm border" 
                                                 style="width: 48px; height: 48px; object-fit: cover;">
                                        </div>
                                        <div class="ms-3">
                                            <span class="fw-bold text-dark d-block mb-0"><?= htmlspecialchars($item['item_name']) ?></span>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <span class="badge bg-light text-primary border border-primary-subtle px-3 py-2 rounded-pill fw-medium" style="font-size: 0.75rem;">
                                        <?= htmlspecialchars($item['category_name']) ?>
                                    </span>
                                </td>
                                <td class="text-center">
                                    <span class="fw-bold text-dark"><?= $item['total_units'] ?></span>
                                </td>
                                <td class="text-center">
                                    <?php 
                                        $stockColor = ($item['available_units'] <= 0) ? 'bg-danger-subtle text-danger' : 'bg-success-subtle text-success';
                                    ?>
                                    <span class="badge <?= $stockColor ?> px-3 py-2 rounded-pill fw-bold" style="min-width: 45px;">
                                        <?= $item['available_units'] ?>
                                    </span>
                                </td>
                                <td class="text-end pe-4">
                                    <div class="d-flex justify-content-end gap-2">
                                        <a href="assets.php?item_id=<?= $item['item_id'] ?>" 
                                           class="btn btn-sm btn-light rounded-circle shadow-sm border" 
                                           style="width: 34px; height: 34px; display: flex; align-items: center; justify-content: center;"
                                           title="View Detail">
                                            <i class="fa fa-eye text-primary small"></i>
                                        </a>
                                        <button type="button" 
                                                class="btn btn-sm btn-light rounded-circle shadow-sm border"
                                                onclick='openEditItemModal(<?= json_encode($item) ?>)'
                                                style="width: 34px; height: 34px; display: flex; align-items: center; justify-content: center;"
                                                title="Edit">
                                            <i class="fa fa-pen text-warning small"></i>
                                        </button>
                                        <button type="button" 
                                                class="btn btn-sm btn-light rounded-circle shadow-sm border"
                                                onclick="deleteItem(<?= $item['item_id'] ?>, '<?= addslashes($item['item_name']) ?>')"
                                                style="width: 34px; height: 34px; display: flex; align-items: center; justify-content: center;"
                                                title="Delete">
                                            <i class="fa-solid fa-trash-can text-danger small"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; else: ?>
                            <tr>
                                <td colspan="5" class="text-center py-5">
                                    <div class="py-4">
                                        <div class="mb-3" style="font-size: 3rem; color: #cbd5e1;"><i class="fa-solid fa-box-open"></i></div>
                                        <h6 class="text-muted fw-bold">No items found.</h6>
                                        <p class="text-muted small">Start adding your inventory to see them here.</p>
                                    </div>
                                </td>
                            </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

<div class="modal fade" id="categoryModal" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content rounded-4 border-0 shadow-lg">
                <div class="modal-header border-bottom-0 pb-0">
                    <h5 class="modal-title fw-bold text-dark"><i class="fa-solid fa-list-ul me-2 text-primary"></i>Manage Categories</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-4">
                    <div class="row">
                        <div class="col-md-5">
                            <div class="p-3 bg-light rounded-4 h-100">
                                <form method="post" action="manageItem_admin.php">
                                    <input type="hidden" name="add_category" value="1">
                                    <div class="mb-3">
                                        <label class="form-label fw-bold small">New Category Name</label>
                                        <input type="text" class="form-control rounded-3 border-0 shadow-sm" name="category_name" placeholder="e.g. Projector" required>
                                    </div>
                                    <button type="submit" class="btn btn-primary w-100 rounded-pill shadow-sm">Add Category</button>
                                </form>
                            </div>
                        </div>
                        <div class="col-md-7 ps-md-4 mt-4 mt-md-0">
                            <label class="form-label fw-bold small mb-3">Existing Categories</label>
                            <div class="list-group list-group-flush" style="max-height: 250px; overflow-y: auto; border: 1px solid #f1f5f9; border-radius: 15px;">
                                <?php if (!empty($categories)): foreach($categories as $cat): ?>
                                    <div class="list-group-item d-flex justify-content-between align-items-center py-3">
                                        <span class="fw-medium text-dark"><?= htmlspecialchars($cat['category_name']) ?></span>
                                        <div class="btn-group">
                                            <button class="btn btn-sm btn-link text-warning p-0 me-3" onclick='openEditCategoryModal(<?= json_encode($cat) ?>)'><i class="fa fa-edit"></i></button>
                                            <button class="btn btn-sm btn-link text-danger p-0" onclick="deleteCategory(<?= $cat['category_id'] ?>, '<?= addslashes($cat['category_name']) ?>')"><i class="fa fa-trash"></i></button>
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
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content rounded-4 border-0 shadow-lg">
                <div class="modal-header border-bottom-0">
                    <h5 class="modal-title fw-bold text-dark"><i class="fa-solid fa-pen-to-square me-2 text-warning"></i>Edit Item Details</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="post" action="manageItem_admin.php" enctype="multipart/form-data">
                    <div class="modal-body px-4 pt-0">
                        <input type="hidden" name="edit_item_type" value="1">
                        <input type="hidden" id="edit_item_id" name="edit_item_id">
                        
                        <div class="mb-3">
                            <label class="form-label small fw-bold">Item Name</label>
                            <input type="text" id="edit_item_name" name="edit_item_name" class="form-control rounded-3 bg-light border-0 shadow-sm" required>
                        </div>

                        <div class="mb-3">
                            <label class="form-label small fw-bold">Category</label>
                            <select id="edit_category_id_select" name="edit_category_id" class="form-select rounded-3 bg-light border-0 shadow-sm">
                                <?php foreach($categories as $cat): ?>
                                    <option value="<?= $cat['category_id'] ?>"><?= htmlspecialchars($cat['category_name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="row g-2 mb-4">
                            <div class="col-md-6">
                                <label class="form-label small fw-bold">Brand</label>
                                <input type="text" name="batch_brand" class="form-control rounded-3 bg-light border-0 shadow-sm" placeholder="e.g. Epson">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small fw-bold">Model</label>
                                <input type="text" name="batch_model" class="form-control rounded-3 bg-light border-0 shadow-sm" placeholder="e.g. EB-X06">
                            </div>
                        </div>

                        <div class="p-3 bg-primary-subtle rounded-4 mb-3">
                            <label class="form-label small fw-bold text-primary mb-1">Add New Units (Inventory Expansion)</label>
                            <input type="number" id="edit_item_quantity" name="edit_item_quantity" class="form-control mb-2 rounded-3 border-0 shadow-sm" min="0" value="0">
                            
                            <div class="form-check form-switch small">
                                <input type="checkbox" class="form-check-input" id="edit_enable_manual_code" name="enable_manual_code">
                                <label class="form-check-label text-dark" for="edit_enable_manual_code">Manual asset codes for new units</label>
                            </div>
                        </div>

                        <div id="edit_manual_assets_container" style="display: none;" class="mt-2 border-top pt-3">
                            <div id="edit_dynamic_asset_inputs" class="row g-2"></div>
                        </div>
                    </div>

                    <div class="modal-footer border-top-0 pb-4">
                        <button type="button" class="btn btn-light rounded-pill px-4" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="edit_item_type_btn" class="btn btn-primary rounded-pill px-4 shadow-sm">Update Item</button>
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

    if (!editQtyInput || !editCheckbox) return;

    const qty = parseInt(editQtyInput.value) || 0;

    if (editCheckbox.checked && qty > 0) {
        editContainer.style.display = 'block';
        let html = '';
        for (let i = 1; i <= Math.min(qty, 50); i++) {
            html += `
                <div class="col-6 mb-2">
                    <input type="text" name="manual_codes[]" class="form-control form-control-sm" placeholder="Code #${i}" required>
                </div>
                <div class="col-6 mb-2">
                    <input type="text" name="manual_serials[]" class="form-control form-control-sm" placeholder="Serial #${i}">
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

    <?php if(isset($_SESSION['message'])): ?>
        Swal.fire({
            icon: '<?= $_SESSION['message']['type'] ?>',
            title: '<?= $_SESSION['message']['title'] ?>',
            text: '<?= $_SESSION['message']['text'] ?>',
            timer: 2500,
            showConfirmButton: false
        });
        <?php unset($_SESSION['message']); ?>
    <?php endif; ?>
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
            window.location.href = 'manageItem_admin.php?delete_item_id=' + id;
        }
    });
}

// --- LOGIK UNTUK MANAGE CATEGORIES ---

// 1. Fungsi Padam Kategori
function deleteCategory(id, name) {
    Swal.fire({
        title: 'Delete Category?',
        text: "Are you sure you want to delete the category '" + name + "'? Make sure there are no items in this category.",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#ef4444',
        cancelButtonColor: '#64748b',
        confirmButtonText: 'Ye!',
        cancelButtonText: 'Batal'
    }).then((result) => {
        if (result.isConfirmed) {
            // Hantar parameter delete_cat_id ke PHP
            window.location.href = "manageItem_admin.php?delete_cat_id=" + id;
        }
    });
}

function openEditCategoryModal(cat) {
    // 1. Simpan rujukan modal Bootstrap
    const catModal = bootstrap.Modal.getInstance(document.getElementById('categoryModal'));
    
    // 2. Sembunyikan modal kategori sekejap supaya keyboard 'bebas'
    catModal.hide();

    Swal.fire({
        title: 'Edit Nama Kategori',
        input: 'text',
        inputValue: cat.category_name,
        showCancelButton: true,
        confirmButtonText: 'Simpan'
    }).then((result) => {
        if (result.isConfirmed) {
            window.location.href = `manageItem_admin.php?edit_cat_id=${cat.category_id}&new_name=${encodeURIComponent(result.value)}`;
        } else {
            // 3. Kalau user cancel, buka balik modal kategori tadi
            catModal.show();
        }
    });
}
// Kod untuk 'paksa' modal bagi pelepasan kepada SweetAlert
$(document).on('focusin', function(e) {
    if ($(e.target).closest(".swal2-container").length) {
        e.stopImmediatePropagation();
    }
});

// Versi Vanilla JS kalau kau tak pakai jQuery
document.addEventListener('focusin', (e) => {
  if (e.target.closest(".swal2-container")) {
    e.stopImmediatePropagation();
  }
}, true);

</script>

<nav class="mobile-bottom-nav">
   
    <a href="manageItem_admin.php"class="active" ><i class="fa-solid fa-box-archive"></i> Manage Items</a>
        <a href="manage_accounts.php" ><i class="fa-solid fa-users-cog"></i> Manage Accounts</a>
        <a href="report_admin.php" ><i class="fa-solid fa-chart-pie"></i> System Report</a>        </div>

    <a href="profile_admin.php" class="nav-item">
        <i class="fa-solid fa-user"></i>
        <span>Profile</span>
    </a>
</nav></body>

</html>

