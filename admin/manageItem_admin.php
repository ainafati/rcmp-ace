<?php
session_start();
include '../config.php'; // Correct path to config
include_once '../logger.php'; // <-- Sertakan fail logger

// --- Pengesahan Admin ---
if (!isset($_SESSION['admin_id'])) {
    header("Location: login.php");
    exit();
}
$admin_id = (int)$_SESSION['admin_id'];

// --- Dapatkan Info Admin (untuk paparan & log) ---
$admin = ['name' => 'Admin'];
$stmt_admin = $conn->prepare("SELECT name FROM admin WHERE admin_id = ?");
$stmt_admin->bind_param("i", $admin_id);
$stmt_admin->execute();
$result_admin = $stmt_admin->get_result();
if ($admin_data = $result_admin->fetch_assoc()) {
    $admin = $admin_data;
}
$stmt_admin->close();

// Sediakan pembolehubah untuk log (lebih mudah dibaca)
$admin_id_session = (int)$_SESSION['admin_id'];
$admin_name_session = $admin['name'];

// --- Fungsi Bantuan ---
function safe_unlink($filepath) {
    if ($filepath && file_exists($filepath) && is_file($filepath)) {
        @unlink($filepath); // Guna @ untuk elak warning jika fail sedang digunakan
    }
}

// ===================================
// --- PENGURUSAN KATEGORI ---
// ===================================

// --- TAMBAH KATEGORI ---
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['add_category'])) {
    $category_name = trim($_POST['category_name']);
    $db_path = "";

    if (isset($_FILES['category_image']) && $_FILES['category_image']['error'] === 0) {
        $image = $_FILES['category_image'];
        $image_name = uniqid('cat_', true) . '.' . strtolower(pathinfo(basename($image['name']), PATHINFO_EXTENSION));
        $db_path = 'uploads/' . $image_name;
        $server_path = '../' . $db_path; 
        if (!move_uploaded_file($image['tmp_name'], $server_path)) {
            $db_path = ""; // Gagal muat naik, set laluan sebagai kosong
        }
    }
    
    $stmt = $conn->prepare("INSERT INTO categories (category_name, image_url) VALUES (?, ?)");
    $stmt->bind_param("ss", $category_name, $db_path);
    
    if ($stmt->execute()) {
        $new_cat_id = $stmt->insert_id;
        
        // --- MULA REKOD LOG ---
        $log_details = "Admin '{$admin_name_session}' (ID: {$admin_id_session}) telah menambah kategori baru: '{$category_name}' (ID: {$new_cat_id}).";
        log_activity($conn, 'admin', $admin_id_session, 'ADD_CATEGORY', $log_details);
        // --- TAMAT REKOD LOG ---
        
        $_SESSION['message'] = ['type' => 'success', 'text' => 'Category added successfully!'];
    } else {
        $_SESSION['message'] = ['type' => 'error', 'text' => 'Gagal menambah kategori.'];
    }
    $stmt->close();
    header("Location: manageItem_admin.php"); 
    exit();
}

// --- KEMAS KINI KATEGORI ---
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['edit_category'])) {
    $category_id = (int)$_POST['edit_category_id'];
    $category_name = trim($_POST['edit_category_name']);
    
    if (isset($_FILES['edit_category_image']) && $_FILES['edit_category_image']['error'] === 0) {
        // 1. Padam imej lama
        $stmt_old = $conn->prepare("SELECT image_url FROM categories WHERE category_id = ?");
        $stmt_old->bind_param("i", $category_id);
        $stmt_old->execute();
        $stmt_old->bind_result($old_image_url);
        if ($stmt_old->fetch() && !empty($old_image_url)) {
            safe_unlink('../' . $old_image_url);
        }
        $stmt_old->close();
        
        // 2. Muat naik imej baru
        $image = $_FILES['edit_category_image'];
        $image_name = uniqid('cat_', true) . '.' . strtolower(pathinfo(basename($image['name']), PATHINFO_EXTENSION));
        $db_path = 'uploads/' . $image_name;
        $server_path = '../' . $db_path;
        
        if (move_uploaded_file($image['tmp_name'], $server_path)) {
            // 3. Kemas kini DB dengan nama & imej baru
            $stmt = $conn->prepare("UPDATE categories SET category_name = ?, image_url = ? WHERE category_id = ?");
            $stmt->bind_param("ssi", $category_name, $db_path, $category_id);
        }
    } else {
        // 3. Kemas kini DB dengan nama sahaja
        $stmt = $conn->prepare("UPDATE categories SET category_name = ? WHERE category_id = ?");
        $stmt->bind_param("si", $category_name, $category_id);
    }
    
    if (isset($stmt)) {
        if ($stmt->execute()) {
            
            // --- MULA REKOD LOG ---
            $log_details = "Admin '{$admin_name_session}' (ID: {$admin_id_session}) telah mengemas kini kategori (ID: {$category_id}) kepada nama '{$category_name}'.";
            log_activity($conn, 'admin', $admin_id_session, 'EDIT_CATEGORY', $log_details);
            // --- TAMAT REKOD LOG ---
            
            $_SESSION['message'] = ['type' => 'success', 'text' => 'Category updated successfully!'];
        }
        $stmt->close();
    } else {
        $_SESSION['message'] = ['type' => 'error', 'text' => 'Category update failed.'];
    }
    header("Location: manageItem_admin.php"); 
    exit();
}

// --- PADAM KATEGORI ---
if (isset($_GET['delete_category_id'])) {
    $delete_id = (int)$_GET['delete_category_id'];
    
    // 1. Dapatkan info untuk log & padam imej
    $stmt_info = $conn->prepare("SELECT category_name, image_url FROM categories WHERE category_id = ?");
    $stmt_info->bind_param("i", $delete_id);
    $stmt_info->execute();
    $stmt_info->bind_result($category_name_to_delete, $image_url_to_delete);
    $stmt_info->fetch();
    $stmt_info->close();
    
    // 2. Padam imej
    if (!empty($image_url_to_delete)) {
        safe_unlink('../' . $image_url_to_delete);
    }
    
    // 3. Padam dari DB
    $stmt = $conn->prepare("DELETE FROM categories WHERE category_id = ?");
    $stmt->bind_param("i", $delete_id);
    
    if ($stmt->execute()) {
        
        // --- MULA REKOD LOG ---
        $log_details = "Admin '{$admin_name_session}' (ID: {$admin_id_session}) telah memadam kategori: '{$category_name_to_delete}' (ID: {$delete_id}).";
        log_activity($conn, 'admin', $admin_id_session, 'DELETE_CATEGORY', $log_details);
        // --- TAMAT REKOD LOG ---
        
        $_SESSION['message'] = ['type' => 'success', 'text' => 'Category deleted.'];
    } else {
        // Gagal (kemungkinan kerana 'foreign key constraint')
        $_SESSION['message'] = ['type' => 'error', 'text' => 'Could not delete category. It is likely in use.'];
    }
    $stmt->close();
    header("Location: manageItem_admin.php"); 
    exit();
}


// ===================================
// --- PENGURUSAN ITEM & ASET ---
// ===================================

// --- TAMBAH ITEM BARU & UNIT ASET ---
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['add_item_type_and_units'])) {
    $item_name = trim($_POST['item_name']);
    $category_id = (int)$_POST['category_id'];
    $description = trim($_POST['description']);
    $quantity = (int)$_POST['quantity'];
    $batch_brand = isset($_POST['batch_brand']) ? trim($_POST['batch_brand']) : '';
    $batch_model = isset($_POST['batch_model']) ? trim($_POST['batch_model']) : '';

    $conn->begin_transaction();
    try {
        // 1. Cipta 'item' (jenis item)
        $stmt_item = $conn->prepare("INSERT INTO item (item_name, category_id, description) VALUES (?, ?, ?)");
        $stmt_item->bind_param("sis", $item_name, $category_id, $description);
        $stmt_item->execute();
        $new_item_id = $conn->insert_id;
        $stmt_item->close();

        if ($new_item_id > 0 && $quantity > 0) {
            // 2. Dapatkan prefix kategori
            $stmt_cat = $conn->prepare("SELECT category_name FROM categories WHERE category_id = ?");
            $stmt_cat->bind_param("i", $category_id);
            $stmt_cat->execute();
            $stmt_cat->bind_result($category_name);
            $stmt_cat->fetch();
            $stmt_cat->close();
            
            $prefix = strtoupper(substr($category_name, 0, 3));
            $like_prefix = $prefix . '-%';

            // 3. Dapatkan nombor siri aset terakhir
            $stmt_last = $conn->prepare("SELECT asset_code FROM assets WHERE asset_code LIKE ? ORDER BY CAST(SUBSTRING(asset_code, 5) AS UNSIGNED) DESC LIMIT 1");
            $stmt_last->bind_param("s", $like_prefix);
            $stmt_last->execute();
            $stmt_last->bind_result($last_code);
            $stmt_last->fetch();
            $stmt_last->close();
            
            $last_num = $last_code ? (int)substr($last_code, -4) : 0;
            
            // 4. Loop untuk cipta unit 'aset'
            for ($i = 0; $i < $quantity; $i++) {
                $next_num = $last_num + 1 + $i;
                $new_asset_code = $prefix . '-' . str_pad($next_num, 4, '0', STR_PAD_LEFT);
                
                $stmt_insert = $conn->prepare("INSERT INTO assets (item_id, asset_code, status, brand, model) VALUES (?, ?, 'Available', ?, ?)");
                $stmt_insert->bind_param("isss", $new_item_id, $new_asset_code, $batch_brand, $batch_model);
                $stmt_insert->execute();
                $stmt_insert->close();
            }
        }
        
        $conn->commit(); // Sahkan transaksi

        // --- MULA REKOD LOG ---
        $log_details = "Admin '{$admin_name_session}' (ID: {$admin_id_session}) telah menambah item baru: '{$item_name}' (ID: {$new_item_id}) dengan {$quantity} unit.";
        log_activity($conn, 'admin', $admin_id_session, 'ADD_ITEM_WITH_UNITS', $log_details);
        // --- TAMAT REKOD LOG ---

        $_SESSION['message'] = ['type' => 'success', 'text' => 'Successfully created ' . htmlspecialchars($item_name) . ' with ' . $quantity . ' units.'];

    } catch (Exception $e) {
        $conn->rollback(); // Batalkan jika gagal
        $_SESSION['message'] = ['type' => 'error', 'text' => 'Database error: ' . $e->getMessage()];
    }
    header("Location: manageItem_admin.php"); 
    exit();
}

// --- KEMAS KINI ITEM & TAMBAH UNIT ASET ---
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['edit_item_type'])) {
    $item_id = (int)$_POST['edit_item_id'];
    $item_name = trim($_POST['edit_item_name']);
    $category_id = (int)$_POST['edit_category_id'];
    $description = trim($_POST['edit_description']);
    
    // Kuantiti untuk unit BARU
    $quantity = isset($_POST['quantity']) ? (int)$_POST['quantity'] : 0;
    $batch_brand = isset($_POST['batch_brand']) ? trim($_POST['batch_brand']) : '';
    $batch_model = isset($_POST['batch_model']) ? trim($_POST['batch_model']) : '';

    $conn->begin_transaction();
    try {
        // 1. Kemas kini 'item' (jenis item)
        $stmt_update = $conn->prepare("UPDATE item SET item_name = ?, category_id = ?, description = ? WHERE item_id = ?");
        $stmt_update->bind_param("sisi", $item_name, $category_id, $description, $item_id);
        $stmt_update->execute();
        $stmt_update->close();

        $message_part_2 = "";

        // 2. Jika ada kuantiti, tambah unit 'aset' baru
        if ($quantity > 0) {
            // (Logik sama seperti 'tambah item')
            $stmt_cat = $conn->prepare("SELECT category_name FROM categories WHERE category_id = ?");
            $stmt_cat->bind_param("i", $category_id);
            $stmt_cat->execute();
            $stmt_cat->bind_result($category_name);
            $stmt_cat->fetch();
            $stmt_cat->close();
            
            $prefix = strtoupper(substr($category_name, 0, 3));
            $like_prefix = $prefix . '-%';

            $stmt_last = $conn->prepare("SELECT asset_code FROM assets WHERE asset_code LIKE ? ORDER BY CAST(SUBSTRING(asset_code, 5) AS UNSIGNED) DESC LIMIT 1");
            $stmt_last->bind_param("s", $like_prefix);
            $stmt_last->execute();
            $stmt_last->bind_result($last_code);
            $stmt_last->fetch();
            $stmt_last->close();
            
            $last_num = $last_code ? (int)substr($last_code, -4) : 0;
            
            for ($i = 0; $i < $quantity; $i++) {
                $next_num = $last_num + 1 + $i;
                $new_asset_code = $prefix . '-' . str_pad($next_num, 4, '0', STR_PAD_LEFT);
                
                $stmt_insert = $conn->prepare("INSERT INTO assets (item_id, asset_code, status, brand, model) VALUES (?, ?, 'Available', ?, ?)");
                $stmt_insert->bind_param("isss", $item_id, $new_asset_code, $batch_brand, $batch_model);
                $stmt_insert->execute();
                $stmt_insert->close();
            }
            $message_part_2 = " and added " . $quantity . " new units";
        }

        $conn->commit(); // Sahkan transaksi

        // --- MULA REKOD LOG ---
        $log_details = "Admin '{$admin_name_session}' (ID: {$admin_id_session}) telah mengemas kini item '{$item_name}' (ID: {$item_id}).{$message_part_2}.";
        log_activity($conn, 'admin', $admin_id_session, 'EDIT_ITEM_WITH_UNITS', $log_details);
        // --- TAMAT REKOD LOG ---

        $_SESSION['message'] = ['type' => 'success', 'text' => 'Item updated successfully' . $message_part_2 . '!'];

    } catch (Exception $e) {
        $conn->rollback();
        $_SESSION['message'] = ['type' => 'error', 'text' => 'Database error: ' . $e->getMessage()];
    }
    
    header("Location: manageItem_admin.php"); 
    exit();
}

// --- PADAM ITEM & SEMUA ASET BERKAITAN ---
if (isset($_GET['delete_item_id'])) {
    $delete_id = (int)$_GET['delete_item_id'];
    
    // 1. Dapatkan nama item untuk log
    $stmt_name = $conn->prepare("SELECT item_name FROM item WHERE item_id = ?");
    $stmt_name->bind_param("i", $delete_id);
    $stmt_name->execute();
    $stmt_name->bind_result($item_name_to_delete);
    $stmt_name->fetch();
    $stmt_name->close();

    $conn->begin_transaction();
    try {
        // 2. Dapatkan senarai aset untuk dipadam dari jadual 'reservation_assets'
        $assets_to_delete_res = $conn->query("SELECT asset_id FROM assets WHERE item_id = $delete_id");
        $asset_ids = [];
        while ($row = $assets_to_delete_res->fetch_assoc()) { 
            $asset_ids[] = $row['asset_id']; 
        }
        
        if (!empty($asset_ids)) {
            // 3. Padam rekod tempahan berkaitan aset
            $asset_id_list = implode(',', $asset_ids);
            $conn->query("DELETE FROM reservation_assets WHERE asset_id IN ($asset_id_list)");
        }
        
        // 4. Padam semua 'aset'
        $conn->query("DELETE FROM assets WHERE item_id = $delete_id");
        
        // 5. Padam 'item' (jenis item)
        $stmt = $conn->prepare("DELETE FROM item WHERE item_id = ?");
        $stmt->bind_param("i", $delete_id);
        $stmt->execute();
        $stmt->close();
        
        $conn->commit(); // Sahkan transaksi

        // --- MULA REKOD LOG ---
        $log_details = "Admin '{$admin_name_session}' (ID: {$admin_id_session}) telah memadam item '{$item_name_to_delete}' (ID: {$delete_id}) dan semua unit asetnya.";
        log_activity($conn, 'admin', $admin_id_session, 'DELETE_ITEM_TYPE', $log_details);
        // --- TAMAT REKOD LOG ---

        $_SESSION['message'] = ['type' => 'success', 'text' => 'Item type and all its units have been deleted.'];

    } catch (Exception $e) {
        $conn->rollback();
        // Gagal (kemungkinan kerana rekod lama dalam 'reservation_items' yang tidak dipadam)
        $_SESSION['message'] = ['type' => 'error', 'text' => 'Could not delete. The item may be part of an old reservation record.'];
    }
    header("Location: manageItem_admin.php"); 
    exit();
}
$categories = $conn->query("SELECT * FROM categories ORDER BY category_name ASC")->fetch_all(MYSQLI_ASSOC);

$item_details = $conn->query("
    SELECT 
        i.item_id, i.item_name, i.category_id, i.description, c.category_name, 
        COUNT(a.asset_id) AS total_units,
        SUM(CASE WHEN a.status = 'Available' THEN 1 ELSE 0 END) AS available_units
    FROM item i
    JOIN categories c ON i.category_id = c.category_id
    LEFT JOIN assets a ON i.item_id = a.item_id
    GROUP BY i.item_id
    ORDER BY i.item_name ASC
")->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Manage Inventory — UniKL Admin</title>
    <meta name="viewport" content="width=device-width, initial-scale=1"> 
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body { 
            font-family: 'Inter', 'Segoe UI', sans-serif; 
            background-color: #f8fafc; 
            color: #334155; 
            min-height: 100vh; 
            overflow-x: hidden; 
        }
        
        /* CSS Sidebar (Desktop View) */
        .sidebar { 
            width: 250px; 
            position: fixed; 
            top: 0; 
            bottom: 0; 
            left: 0; 
            background: #ffffff; 
            padding: 20px; 
            border-right: 1px solid #e5e7eb; 
            z-index: 1000; 
            display: flex; 
            flex-direction: column; 
            justify-content: space-between; 
            transition: transform 0.3s ease; 
        }
        .sidebar-header { display: flex; align-items: center; gap: 12px; margin-bottom: 30px; }
        .logo-icon { width: 40px; height: 40px; background-color: #3b82f6; color: white; border-radius: 8px; display: flex; align-items: center; justify-content: center; font-size: 20px; }
        .logo-text strong { display: block; font-size: 16px; color: #1e293b; }
        .logo-text span { font-size: 12px; color: #94a3b8; }
        .sidebar a { display: flex; align-items: center; gap: 12px; color: #64748b; text-decoration: none; padding: 12px 15px; margin-bottom: 8px; border-radius: 8px; font-weight: 500; font-size: 15px; transition: all 0.2s ease-in-out; }
        .sidebar a.active, .sidebar a:hover { background: #3b82f6; color: #fff; }
        .sidebar a.logout-link { color: #ef4444; font-weight: 600; margin-top: auto; }
        .sidebar a.logout-link:hover { color: #fff; background: #ef4444; }
        
        /* CSS Main Content (Desktop View) */
        .main-content { 
            margin-left: 250px; 
            transition: margin-left 0.3s ease;
        }
        .topbar { 
            background: #ffffff; 
            padding: 15px 30px; 
            display: flex; 
            justify-content: space-between; 
            align-items: center; 
            border-bottom: 1px solid #e5e7eb; 
        }
        .topbar h3 { font-weight: 600; margin: 0; color: #1e293b; font-size: 22px; }
        .topbar .user-profile { display: flex; align-items: center; gap: 12px; }
        .container-fluid { padding: 30px; }
        .card { border-radius: 16px; padding: 25px; box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05); background: #fff; margin-bottom: 25px; border: 1px solid #e2e8f0; }
        
        /* Gaya Umum Jadual */
        .table thead th { background: #f8fafc; color: #64748b; border: none; font-weight: 600; text-transform: uppercase; font-size: 12px; }
        .table tbody td { border-bottom: 1px solid #f1f5f9; }
        
        /* Gaya Button Actions (Desktop - Kekal Sebaris) */
        .table td.action-cell {
            white-space: nowrap; 
            padding-right: 10px;
            padding-left: 10px;
        }
        .table td.action-cell .btn {
            padding: 0.5rem 0.8rem; 
            font-size: 0.85rem; 
            margin-right: 2px; 
            display: inline-block;
        }
        .badge.rounded-pill { padding: .4em .8em; font-weight: 500; }

        /* --- CSS MOBILE VIEW --- */
        #sidebar-toggle-btn {
            display: none; /* Sembunyi secara default */
            background: none;
            border: none;
            color: #334155;
            font-size: 20px;
            padding: 0;
            margin-right: 15px;
        }
        
        @media (max-width: 768px) {
            #sidebar-toggle-btn {
                display: block; 
            }
            
            /* Sidebar */
            .sidebar {
                transform: translateX(-100%); 
                box-shadow: 0 0 10px rgba(0, 0, 0, 0.15);
                z-index: 1050; 
            }
            .sidebar.open {
                transform: translateX(0);
            }
            .main-content {
                margin-left: 0; 
                width: 100%;
            }
            
            /* Topbar - KEKALKAN TAJUK */
            .topbar {
                padding: 10px 15px;
                justify-content: space-between;
            }
            .topbar .d-flex:first-child h3 {
                display: block; /* Kekalkan Tajuk */
                font-size: 16px; 
                margin-right: auto;
            }
.topbar .user-profile {
    /* display: none; */ /* Komenkan baris ini */
    display: flex; /* Tukar kepada flex untuk memastikan ikon dan nama dipaparkan */
    font-size: 14px; /* Laraskan saiz font supaya muat */
}
.topbar .user-profile .user-name {
     /* Sembunyikan nama, kekalkan ikon sahaja jika ruang tak cukup */
     display: none;
}            
            /* Pelarasan Content & Cards */
            .container-fluid {
                padding: 10px 5px; /* Kurangkan padding keseluruhan */
            }
            .card {
                padding: 15px; 
                margin-bottom: 15px;
            }
            
            /* JADUAL PADAT */
            .table thead th {
                font-size: 10px; 
                padding: 0.5rem 0.3rem; 
            }
            .table tbody td {
                padding: 0.4rem 0.3rem;
            }
            
            /* BADGE PADAT */
            .badge.rounded-pill {
                padding: .3em .6em; 
                font-size: 0.75rem;
                display: block; 
                width: 100%;
            }

            /* BUTANG ACTIONS MENEGAK */
            .table td.action-cell {
                white-space: normal; /* Benarkan butang turun baris */
                padding-right: 0px; 
                padding-left: 0px; 
                text-align: center; /* Butang di tengah sel */
                width: 50px; 
            }
            .table td.action-cell .btn {
                padding: 0.3rem 0.4rem; /* Padding butang minimum */
                font-size: 0.7rem;      /* Font butang sangat kecil */
                margin: 1px auto; /* Margin menegak di antara butang */
                display: block; /* MESTI 'block' untuk susunan menegak */
                width: 80%; 
            }
            /* Sembunyikan kolum Kategori di mobile untuk jimat ruang */
            /* Kolum kedua adalah Category */
            .table tbody td:nth-child(2) {
                display: none; 
            }
            /* Ubah struktur Item Type di kolum pertama untuk masukkan Category (sebab kolum kedua disembunyi) */
            .table tbody td:first-child {
                max-width: 150px;
                white-space: normal;
            }
        }
    </style>
</head>
<body>

<div class="sidebar-overlay" id="sidebar-overlay"></div>

<div class="sidebar" id="admin-sidebar">
    <div>
        <div class="sidebar-header">
            <div class="logo-icon"><i class="fa-solid fa-user-shield"></i></div>
            <div class="logo-text"><strong>UniKL Admin</strong><span>System Control</span></div>
        </div>
        <a href="manageItem_admin.php" class="active"><i class="fa-solid fa-box-archive"></i> Manage Items</a>
        <a href="manage_accounts.php"><i class="fa-solid fa-users-cog"></i> Manage Accounts</a>
        <a href="report_admin.php"><i class="fa-solid fa-chart-pie"></i> System Report</a>
    </div>
    <a href="logout.php" class="logout-link"><i class="fa-solid fa-right-from-bracket"></i> Logout</a>
</div>

<div class="main-content">
    <div class="topbar">
        <div class="d-flex align-items-center">
             <button id="sidebar-toggle-btn" class="me-3"><i class="fa fa-bars"></i></button>
            <h3>Inventory Management</h3>
        </div>
        
        <div class="d-flex align-items-center gap-3">
            <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#categoryModal"><i class="fa fa-list me-2"></i> Manage Categories</button>
            <div class="user-profile">
                <span class="user-name"><?= htmlspecialchars($admin['name']) ?></span>
                <a href="profile_admin.php" title="Go to My Profile" style="color: inherit; text-decoration: none;">
                    <i class="fa-solid fa-user-circle fa-2x text-secondary"></i>
                </a>
            </div>
        </div>
    </div>

    <div class="container-fluid">
        <div class="row g-4">
            <div class="col-lg-4">
                <div class="card shadow-sm p-4 mb-4">
                    <h5 class="mb-3"><i class="fa fa-cubes"></i> 1. Add New Item Type & Units</h5>
                    <p class="text-muted small">Create a new item type and add initial stock in one step.</p>
                    <form method="post" action="manageItem_admin.php" enctype="multipart/form-data">
                        <input type="hidden" name="add_item_type_and_units" value="1">
                        <h6 class="mt-3">A. Item Type Information</h6>
                        <hr class="mt-1">
                        <div class="mb-3">
                            <label class="form-label">Item Type Name</label>
                            <input type="text" name="item_name" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Category</label>
                            <select name="category_id" class="form-select" required>
                                <option value="">Select Category</option>
                                <?php foreach($categories as $cat): ?>
                                <option value="<?= $cat['category_id'] ?>"><?= htmlspecialchars($cat['category_name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Description</label>
                            <textarea name="description" class="form-control" rows="2"></textarea>
                        </div>
                        <h6 class="mt-4">B. Bulk Unit Details (Optional)</h6>
                        <hr class="mt-1">
                        <div class="mb-3">
                            <label class="form-label">Brand</label>
                            <input type="text" name="batch_brand" class="form-control">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Model</label>
                            <input type="text" name="batch_model" class="form-control">
                        </div>
                        <h6 class="mt-4">C. Initial Stock (Units)</h6>
                        <hr class="mt-1">
                        <div class="mb-3">
                            <label class="form-label">Number of Units to Add</label>
                            <input type="number" name="quantity" class="form-control" min="1" value="1" required>
                            <small class="text-muted">Each unit will get a unique asset code.</small>
                        </div>
                        <button type="submit" class="btn btn-success w-100 mt-3">Create Item & Add Units</button>
                    </form>
                </div>
            </div>
            <div class="col-lg-8">
                <div class="card h-100">
                    <h5><i class="fa fa-list-check me-2 text-primary"></i> Item Type Summary</h5>
                    <p class="text-muted small">Overview of all item types. Click <i class="fa fa-eye"></i> to view individual units.</p>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle">
                            <thead><tr><th>Item Type</th><th class="d-none d-sm-table-cell">Category</th><th class="text-center">Total</th><th class="text-center">Available</th><th>Actions</th></tr></thead>
                            <tbody>
                            <?php if (empty($item_details)): ?>
                                <tr><td colspan="5" class="text-center text-muted py-5"><i class="fa-solid fa-box-open fa-2x mb-2"></i><br>No items found. Add one using the form.</td></tr>
                            <?php else: foreach($item_details as $item): ?>
                                <tr>
                                    <td>
                                        <strong><?= htmlspecialchars($item['item_name']) ?></strong>
                                        <span class="d-block d-sm-none text-muted small">(<?= htmlspecialchars($item['category_name']) ?>)</span>
                                    </td>
                                    <td class="d-none d-sm-table-cell"><?= htmlspecialchars($item['category_name']) ?></td>
                                    
                                    <td class="text-center"><span class="badge rounded-pill text-bg-secondary"><?= $item['total_units'] ?></span></td>
                                    <td class="text-center"><span class="badge rounded-pill text-bg-success"><?= $item['available_units'] ?></span></td>
                                    <td class="action-cell">
                                        <a href="view_assets.php?item_id=<?= $item['item_id'] ?>" class="btn btn-outline-info" title="View Units"><i class="fa fa-eye"></i></a>
                                        <button class="btn btn-outline-warning" title="Edit Item Type" onclick='openEditItemModal(<?= htmlspecialchars(json_encode($item), ENT_QUOTES, 'UTF-8') ?>)'><i class="fa fa-edit"></i></button>
                                        <button class="btn btn-outline-danger" title="Delete Item Type" onclick="deleteItem(<?= $item['item_id'] ?>, '<?= htmlspecialchars(addslashes($item['item_name']), ENT_QUOTES, 'UTF-8') ?>')"><i class="fa fa-trash"></i></button>
                                    </td>
                                </tr>
                            <?php endforeach; endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
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
                        <form method="post" action="manageItem_admin.php" enctype="multipart/form-data">
                            <input type="hidden" name="add_category" value="1">
                            <div class="mb-3">
                                <label for="category_name" class="form-label">Category Name</label>
                                <input type="text" class="form-control" id="category_name" name="category_name" required>
                            </div>
                            <div class="mb-3">
                                <label for="category_image" class="form-label">Category Image (Optional)</label>
                                <input type="file" class="form-control" id="category_image" name="category_image" accept="image/*">
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
                                    <div>
                                        <?php if (!empty($cat['image_url'])): ?>
                                            <img src="../<?= htmlspecialchars($cat['image_url']) ?>" class="category-img-sm" alt="">
                                        <?php endif; ?>
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
            <form method="post" action="manageItem_admin.php" enctype="multipart/form-data">
                <div class="modal-body">
                    <input type="hidden" name="edit_category" value="1">
                    <input type="hidden" id="edit_category_id" name="edit_category_id">
                    <div class="mb-3">
                        <label for="edit_category_name" class="form-label">Category Name</label>
                        <input type="text" class="form-control" id="edit_category_name" name="edit_category_name" required>
                    </div>
                    <div class="mb-3">
                        <label for="edit_category_image" class="form-label">New Image (Optional)</label>
                        <input type="file" class="form-control" id="edit_category_image" name="edit_category_image" accept="image/*">
                        <small class="text-muted">Uploading a new image will replace the old one.</small>
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
            <div class="modal-header"><h5 class="modal-title">Edit Item Type</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <form method="post" action="manageItem_admin.php" enctype="multipart/form-data">
                <div class="modal-body">
                    <input type="hidden" name="edit_item_type" value="1">
                    <input type="hidden" id="edit_item_id" name="edit_item_id">

                    <h6>Item Details</h6>
                    <div class="mb-3"><label class="form-label">Item Name</label><input type="text" id="edit_item_name" name="edit_item_name" class="form-control" required></div>
                    <div class="mb-3"><label class="form-label">Category</label>
                        <select id="edit_category_id_select" name="edit_category_id" class="form-select" required>
                            <?php foreach($categories as $cat): ?><option value="<?= $cat['category_id'] ?>"><?= htmlspecialchars($cat['category_name']) ?></option><?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3"><label class="form-label">Description</label><textarea id="edit_description" name="edit_description" class="form-control" rows="2"></textarea></div>

                    <hr>
                    <h6 class="mt-3">Add More Units (Optional)</h6>
                    <p class="small text-muted">Fill this section only if you want to add new stock for this item.</p>
                    <div class="mb-3">
                        <label class="form-label">Number of New Units to Add</label>
                        <input type="number" id="edit_item_quantity" name="quantity" class="form-control" min="0" value="0" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Brand for New Units</label>
                        <input type="text" id="edit_item_brand" name="batch_brand" class="form-control" placeholder="e.g., Dell">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Model for New Units</label>
                        <input type="text" id="edit_item_model" name="batch_model" class="form-control" placeholder="e.g., Latitude 5420">
                    </div>
                </div>
                <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button><button type="submit" class="btn btn-primary">Save Changes</button></div>
            </form>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
    // --- FUNGSI JAVASCRIPT UNTUK TOGGLE SIDEBAR (MOBILE ONLY) ---
    document.addEventListener('DOMContentLoaded', function() {
        const sidebar = document.getElementById('admin-sidebar');
        const toggleBtn = document.getElementById('sidebar-toggle-btn');
        const overlay = document.getElementById('sidebar-overlay');
        
        if (toggleBtn) {
            // Fungsi untuk buka/tutup sidebar
            function toggleSidebar() {
                sidebar.classList.toggle('open');
                overlay.classList.toggle('active');
            }

            // Pasang event listeners
            toggleBtn.addEventListener('click', toggleSidebar);
            overlay.addEventListener('click', toggleSidebar);
            
            // Tutup sidebar apabila pautan diklik (untuk mobile experience yang lebih baik)
            const sidebarLinks = sidebar.querySelectorAll('a');
            sidebarLinks.forEach(link => {
                link.addEventListener('click', function() {
                    // Semak jika dalam mobile view (lebar kurang dari 768px)
                    if (window.innerWidth <= 768) {
                        setTimeout(() => { // Tunda sedikit supaya link sempat diproses
                            sidebar.classList.remove('open');
                            overlay.classList.remove('active');
                        }, 100);
                    }
                });
            });
        }
    });

    // --- 1. HANDLE NOTIFICATION TOAST (SweetAlert2) ---
    <?php
    // Memaparkan mesej (Success/Error) selepas redirect
    if (isset($_SESSION['message'])) {
        $message = $_SESSION['message'];
        // Pastikan teks diletakkan dalam petikan bagi string JS
        $message_text_js = str_replace("'", "\'", $message['text']);
        echo "Swal.fire({
            icon: '{$message['type']}',
            title: '{$message_text_js}', 
            toast: true,
            position: 'top-end',
            showConfirmButton: false,
            timer: 3500,
            timerProgressBar: true
        });";
        unset($_SESSION['message']);
    }
    ?>

    // --- 2. FUNGSI EDIT KATEGORI (Membuka Modal) ---
    function openEditCategoryModal(category) {
        document.getElementById('edit_category_id').value = category.category_id;
        document.getElementById('edit_category_name').value = category.category_name;
        
        var editModal = new bootstrap.Modal(document.getElementById('editCategoryModal'));
        
        // Sembunyikan modal utama sebelum memaparkan modal edit
        var mainModalEl = document.getElementById('categoryModal');
        var mainModal = bootstrap.Modal.getInstance(mainModalEl);
        if (mainModal) {
            mainModal.hide();
        }
        
        // Paparkan modal edit
        editModal.show();
    }

    // --- 3. FUNGSI PADAM KATEGORI (SweetAlert Confirmation) ---
    function deleteCategory(id, name) {
        Swal.fire({
            title: `Delete '${name}'?`,
            text: "This action cannot be undone. Items under this category might prevent deletion.",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            cancelButtonColor: '#6c757d',
            confirmButtonText: 'Yes, delete it!'
        }).then((result) => {
            if (result.isConfirmed) {
                // Redirect ke skrip PHP untuk tindakan padam
                window.location.href = 'manageItem_admin.php?delete_category_id=' + id;
            }
        });
    }

    // --- 4. FUNGSI EDIT ITEM (Membuka Modal) ---
    function openEditItemModal(item) {
        // Isi butiran item sedia ada
        document.getElementById('edit_item_id').value = item.item_id;
        document.getElementById('edit_item_name').value = item.item_name;
        // Pilih kategori yang betul
        document.getElementById('edit_category_id_select').value = item.category_id;
        document.getElementById('edit_description').value = item.description;

        // Kosongkan dan tetapkan semula medan untuk stok baru (penting)
        document.getElementById('edit_item_quantity').value = 0;
        document.getElementById('edit_item_brand').value = '';
        document.getElementById('edit_item_model').value = '';

        new bootstrap.Modal(document.getElementById('editItemModal')).show();
    }

    // --- 5. FUNGSI PADAM ITEM (SweetAlert Confirmation) ---
    function deleteItem(id, name) {
        Swal.fire({
            title: `Delete '${name}' and all its units?`,
            text: "This will permanently delete the item type AND all of its associated asset units. This action cannot be undone!",
            icon: 'error', // Guna 'error' atau 'warning' untuk tindakan serius
            showCancelButton: true,
            confirmButtonColor: '#d33',
            cancelButtonColor: '#6c757d',
            confirmButtonText: 'Yes, delete everything!'
        }).then((result) => {
            if (result.isConfirmed) {
                // Redirect ke skrip PHP untuk tindakan padam
                window.location.href = 'manageItem_admin.php?delete_item_id=' + id;
            }
        });
    }
</script>
</body>
</html>