<?php
session_start();

include '../config.php'; 
include_once '../logger.php'; 


if (!isset($_SESSION['tech_id'])) {
    header("Location: ../login.php");
    exit();
}
$tech_id = (int)$_SESSION['tech_id'];
$tech = ['name' => 'Technician'];
$stmt_tech = $conn->prepare("SELECT name FROM technician WHERE tech_id = ?");
$stmt_tech->bind_param("i", $tech_id);
$stmt_tech->execute();
$result_tech = $stmt_tech->get_result();
if ($tech_data = $result_tech->fetch_assoc()) {
    $tech = $tech_data;
}
$stmt_tech->close();


$tech_id_session = (int)$_SESSION['tech_id'];
$tech_name_session = $tech['name'];

function safe_unlink($filepath) {
    if ($filepath && file_exists($filepath) && is_file($filepath)) {
        @unlink($filepath);
    }
}

function get_reservation_item_count($conn, $status) {
    
    $sql = "SELECT COUNT(id) AS count 
            FROM reservation_items 
            WHERE status = ?";
    
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




if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['add_category'])) {
    $category_name = trim($_POST['category_name']);
    $db_path = "";
    if (isset($_FILES['category_image']) && $_FILES['category_image']['error'] === 0) {
        $image = $_FILES['category_image'];
        $image_name = uniqid('cat_', true) . '.' . strtolower(pathinfo(basename($image['name']), PATHINFO_EXTENSION));
        $db_path = 'uploads/' . $image_name;
        $server_path = '../' . $db_path;
        if (!move_uploaded_file($image['tmp_name'], $server_path)) {
            $db_path = "";
        }
    }
    $stmt = $conn->prepare("INSERT INTO categories (category_name, image_url) VALUES (?, ?)");
    $stmt->bind_param("ss", $category_name, $db_path);
    if ($stmt->execute()) {
        $new_cat_id = $stmt->insert_id;
        
        
        $log_details = "Technician '{$tech_name_session}' (ID: {$tech_id_session}) telah menambah kategori baru: '{$category_name}' (ID: {$new_cat_id}).";
        log_activity($conn, 'tech', $tech_id_session, 'TECH_ADD_CATEGORY', $log_details);
        

        $_SESSION['message'] = ['type' => 'success', 'text' => 'Category added successfully!'];
    }
    $stmt->close();
    header("Location: manageItem_tech.php"); exit();
}


if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['edit_category'])) {
    $category_id = (int)$_POST['edit_category_id'];
    $category_name = trim($_POST['edit_category_name']);
    if (isset($_FILES['edit_category_image']) && $_FILES['edit_category_image']['error'] === 0) {
        $stmt_old = $conn->prepare("SELECT image_url FROM categories WHERE category_id = ?");
        $stmt_old->bind_param("i", $category_id);
        $stmt_old->execute();
        $stmt_old->bind_result($old_image_url);
        if ($stmt_old->fetch() && !empty($old_image_url)) {
            safe_unlink('../' . $old_image_url);
        }
        $stmt_old->close();
        $image = $_FILES['edit_category_image'];
        $image_name = uniqid('cat_', true) . '.' . strtolower(pathinfo(basename($image['name']), PATHINFO_EXTENSION));
        $db_path = 'uploads/' . $image_name;
        $server_path = '../' . $db_path;
        if (move_uploaded_file($image['tmp_name'], $server_path)) {
            $stmt = $conn->prepare("UPDATE categories SET category_name = ?, image_url = ? WHERE category_id = ?");
            $stmt->bind_param("ssi", $category_name, $db_path, $category_id);
        }
    } else {
        $stmt = $conn->prepare("UPDATE categories SET category_name = ? WHERE category_id = ?");
        $stmt->bind_param("si", $category_name, $category_id);
    }
    if (isset($stmt)) {
        if ($stmt->execute()) {
            
            
            $log_details = "Technician '{$tech_name_session}' (ID: {$tech_id_session}) telah mengemas kini kategori (ID: {$category_id}) kepada nama '{$category_name}'.";
            log_activity($conn, 'tech', $tech_id_session, 'TECH_EDIT_CATEGORY', $log_details);
            
            
            $_SESSION['message'] = ['type' => 'success', 'text' => 'Category updated successfully!'];
        }
        $stmt->close();
    } else {
        $_SESSION['message'] = ['type' => 'error', 'text' => 'Category update failed.'];
    }
    header("Location: manageItem_tech.php"); exit();
}


if (isset($_GET['delete_category_id'])) {
    $delete_id = (int)$_GET['delete_category_id'];
    
    
    $stmt_info = $conn->prepare("SELECT category_name, image_url FROM categories WHERE category_id = ?");
    $stmt_info->bind_param("i", $delete_id);
    $stmt_info->execute();
    $stmt_info->bind_result($category_name_to_delete, $image_url_to_delete);
    $stmt_info->fetch();
    $stmt_info->close();
    
    
    if (!empty($image_url_to_delete)) {
        safe_unlink('../' . $image_url_to_delete);
    }

    $stmt = $conn->prepare("DELETE FROM categories WHERE category_id = ?");
    $stmt->bind_param("i", $delete_id);
    if ($stmt->execute()) {
        
        
        $log_details = "Technician '{$tech_name_session}' (ID: {$tech_id_session}) telah memadam kategori: '{$category_name_to_delete}' (ID: {$delete_id}).";
        log_activity($conn, 'tech', $tech_id_session, 'TECH_DELETE_CATEGORY', $log_details);
        
        
        $_SESSION['message'] = ['type' => 'success', 'text' => 'Category deleted.'];
    } else {
        $_SESSION['message'] = ['type' => 'error', 'text' => 'Could not delete category. It is likely in use.'];
    }
    $stmt->close();
    header("Location: manageItem_tech.php"); exit();
}



if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['add_item_type_and_units'])) {
    $item_name = trim($_POST['item_name']);
    $category_id = (int)$_POST['category_id'];
    $description = trim($_POST['description']);
    $quantity = (int)$_POST['quantity'];
    $batch_brand = isset($_POST['batch_brand']) ? trim($_POST['batch_brand']) : '';
    $batch_model = isset($_POST['batch_model']) ? trim($_POST['batch_model']) : '';
    
    $conn->begin_transaction();
    try {
        $stmt_item = $conn->prepare("INSERT INTO item (item_name, category_id, description) VALUES (?, ?, ?)");
        $stmt_item->bind_param("sis", $item_name, $category_id, $description);
        $stmt_item->execute();
        $new_item_id = $conn->insert_id;
        $stmt_item->close();
        
        if ($new_item_id > 0 && $quantity > 0) {
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
                $stmt_insert->bind_param("isss", $new_item_id, $new_asset_code, $batch_brand, $batch_model);
                $stmt_insert->execute();
                $stmt_insert->close();
            }
        }
        $conn->commit();
        
        
        $log_details = "Technician '{$tech_name_session}' (ID: {$tech_id_session}) telah menambah item baru: '{$item_name}' (ID: {$new_item_id}) dengan {$quantity} unit.";
        log_activity($conn, 'tech', $tech_id_session, 'TECH_ADD_ITEM_UNITS', $log_details);
        
        
        $_SESSION['message'] = ['type' => 'success', 'text' => 'Successfully created ' . htmlspecialchars($item_name) . ' with ' . $quantity . ' units.'];
    } catch (Exception $e) {
        $conn->rollback();
        $_SESSION['message'] = ['type' => 'error', 'text' => 'Database error: ' . $e->getMessage()];
    }
    header("Location: manageItem_tech.php"); exit();
}

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['edit_item_type'])) {
    $item_id = (int)$_POST['edit_item_id'];
    $item_name = trim($_POST['edit_item_name']);
    $category_id = (int)$_POST['edit_category_id'];
    $description = trim($_POST['edit_description']);
    
    $quantity = isset($_POST['quantity']) ? (int)$_POST['quantity'] : 0;
    $batch_brand = isset($_POST['batch_brand']) ? trim($_POST['batch_brand']) : '';
    $batch_model = isset($_POST['batch_model']) ? trim($_POST['batch_model']) : '';

    $conn->begin_transaction();
    try {
        $stmt_update = $conn->prepare("UPDATE item SET item_name = ?, category_id = ?, description = ? WHERE item_id = ?");
        $stmt_update->bind_param("sisi", $item_name, $category_id, $description, $item_id);
        $stmt_update->execute();
        $stmt_update->close();

        $message_part_2 = "";

        if ($quantity > 0) {
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

        $conn->commit();
        
        
        $log_details = "Technician '{$tech_name_session}' (ID: {$tech_id_session}) telah mengemas kini item '{$item_name}' (ID: {$item_id}).{$message_part_2}.";
        log_activity($conn, 'tech', $tech_id_session, 'TECH_EDIT_ITEM_UNITS', $log_details);
        
        
        $_SESSION['message'] = ['type' => 'success', 'text' => 'Item updated successfully' . $message_part_2 . '!'];
    } catch (Exception $e) {
        $conn->rollback();
        $_SESSION['message'] = ['type' => 'error', 'text' => 'Database error: ' . $e->getMessage()];
    }
    
    header("Location: manageItem_tech.php"); exit();
}

if (isset($_GET['delete_item_id'])) {
    $delete_id = (int)$_GET['delete_item_id'];
    
    
    $stmt_name = $conn->prepare("SELECT item_name FROM item WHERE item_id = ?");
    $stmt_name->bind_param("i", $delete_id);
    $stmt_name->execute();
    $stmt_name->bind_result($item_name_to_delete);
    $stmt_name->fetch();
    $stmt_name->close();
    
    $conn->begin_transaction();
    try {
        $assets_to_delete_res = $conn->query("SELECT asset_id FROM assets WHERE item_id = $delete_id");
        $asset_ids = [];
        while ($row = $assets_to_delete_res->fetch_assoc()) { $asset_ids[] = $row['asset_id']; }
        if (!empty($asset_ids)) {
            $asset_id_list = implode(',', $asset_ids);
            $conn->query("DELETE FROM reservation_assets WHERE asset_id IN ($asset_id_list)");
        }
        $conn->query("DELETE FROM assets WHERE item_id = $delete_id");
        $stmt = $conn->prepare("DELETE FROM item WHERE item_id = ?");
        $stmt->bind_param("i", $delete_id);
        $stmt->execute();
        $stmt->close();
        $conn->commit();
        
        
        $log_details = "Technician '{$tech_name_session}' (ID: {$tech_id_session}) telah memadam item '{$item_name_to_delete}' (ID: {$delete_id}) dan semua unit asetnya.";
        log_activity($conn, 'tech', $tech_id_session, 'TECH_DELETE_ITEM_TYPE', $log_details);
        
        
        $_SESSION['message'] = ['type' => 'success', 'text' => 'Item type and all its units have been deleted.'];
    } catch (Exception $e) {
        $conn->rollback();
        $_SESSION['message'] = ['type' => 'error', 'text' => 'Could not delete. The item may be part of a reservation record.'];
    }
    header("Location: manageItem_tech.php"); exit();
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
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Manage Inventory — UniKL Technician</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', 'Segoe UI', sans-serif; background-color: #f8fafc; color: #334155; min-height: 100vh; }
        .sidebar { width: 250px; position: fixed; top: 0; bottom: 0; left: 0; background: #ffffff; padding: 20px; border-right: 1px solid #e5e7eb; z-index: 1000; display: flex; flex-direction: column; justify-content: space-between; }
        .sidebar-header { display: flex; align-items: center; gap: 12px; margin-bottom: 30px; }
        .logo-icon { width: 40px; height: 40px; background-color: #3b82f6; color: white; border-radius: 8px; display: flex; align-items: center; justify-content: center; font-size: 20px; }
        .logo-text strong { display: block; font-size: 16px; color: #1e293b; }
        .logo-text span { font-size: 12px; color: #94a3b8; }
        .sidebar a { display: flex; align-items: center; gap: 12px; color: #64748b; text-decoration: none; padding: 12px 15px; margin-bottom: 8px; border-radius: 8px; font-weight: 500; font-size: 15px; transition: all 0.2s ease-in-out; }
        .sidebar a.active, .sidebar a:hover { background: #3b82f6; color: #fff; }
/* Pastikan kod ini ada (di luar media query) */
.sidebar a.logout-link { 
    color: #ef4444; 
    font-weight: 600; 
    /* KOD PENTING INI MENOLAKNYA KE BAWAH SEKALI */
    margin-top: auto; 
}        .sidebar a.logout-link:hover { color: #fff; background: #ef4444; }
        /* 5. SIDEBAR BADGE STYLE (Penambahan) */
.sidebar a .badge {
    margin-left: auto; /* Tolak badge ke kanan */
    font-size: 0.75rem;
    padding: 0.4em 0.6em;
    font-weight: 700;
    border-radius: 10px;
    background-color: #ef4444; /* Merah untuk menarik perhatian */
    color: white;
}

/* Pastikan badge tidak hilang apabila item menu di-hover atau aktif */
.sidebar a.active .badge, .sidebar a:hover .badge {
    background-color: #ffffff;
    color: #ef4444; /* Warna terbalik agar kontras */
}

		.main-content { margin-left: 250px; }
        /* Topbar tidak lagi fixed di desktop, hanya di mobile */
        .topbar { background: #ffffff; padding: 15px 30px; display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid #e5e7eb; } 
        .topbar h3 { font-weight: 600; margin: 0; color: #1e293b; font-size: 22px; }
        .topbar .user-profile { display: flex; align-items: center; gap: 12px; }
        .topbar .user-name { font-weight: 600; font-size: 15px; color: #334155; }
        .container-fluid { padding: 30px; }
        .card { border-radius: 16px; padding: 25px; box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05); background: #fff; margin-bottom: 25px; border: 1px solid #e2e8f0; }
        .card h5, .modal-title { font-weight: 600; color: #1e293b; }
        .table thead th { background: #f8fafc; color: #64748b; border: none; font-weight: 600; text-transform: uppercase; font-size: 12px; }
        .table tbody td { border-bottom: 1px solid #f1f5f9; }
        .table tbody tr:last-child td { border-bottom: none; }
        .badge.rounded-pill { padding: .4em .8em; font-weight: 500; }
        .form-label { font-weight: 500; color: #334155; }
        .form-control, .form-select { border-radius: 8px; }
        .btn { border-radius: 8px; padding: 10px 20px; font-weight: 500; }
        .btn-primary { background-color: #3b82f6; border: none; }
        .btn-primary:hover { background-color: #2563eb; }
        .category-img-sm { width: 40px; height: 40px; object-fit: cover; border-radius: 8px; margin-right: 15px; }
        
        /* Sembunyikan toggle menu di desktop */
        .menu-toggle {
            display: none;
        }

        /* --- KOD MOBILE VIEW (RESPONSIF) MULA --- */
        @media (max-width: 992px) {
            .sidebar {
                /* Sembunyikan secara lalai (guna transformasi) */
                transform: translateX(-100%);
                transition: transform 0.3s ease-in-out;
                width: 280px; /* Lebar sidebar mobile */
                display: block; /* Kekalkan sebagai block untuk di-toggle */
            }

            /* Kelas baru untuk mobile yang AKTIF */
            .sidebar.active {
                transform: translateX(0);
                box-shadow: 4px 0 10px rgba(0,0,0,0.1);
            }

            /* Main content guna 100% lebar skrin, tiada margin kiri */
            .main-content {
                margin-left: 0;
            }
            
            /* Tambah padding di atas untuk memberi ruang kepada topbar */
            body {
                padding-top: 70px;
            }

            /* Jadikannya fixed di atas */
            .topbar {
                position: fixed; 
                top: 0; 
                left: 0; 
                right: 0;
                z-index: 999;
                padding: 15px 20px;
                /* Benarkan wrapping untuk butang dan profile */
                flex-wrap: wrap; 
                gap: 10px;
            }
            .topbar h3 {
                font-size: 20px;
                margin-left: 15px; /* Jarakkan dari toggle button */
                flex-grow: 1; /* Biar ia ambil ruang */
            }

            
            /* Tunjukkan toggle menu di mobile */
            .menu-toggle {
                display: block !important; 
                font-size: 24px;
                cursor: pointer;
                color: #334155;
            }

            /* Pastikan form di col-lg-4 diletakkan di atas table di col-lg-8 pada mobile */
            .col-lg-4, .col-lg-8 {
                width: 100% !important;
            }

            /* Laraskan padding container */
            .container-fluid {
                padding: 20px;
            }

             /* Jadual: Pastikan scrollable secara mendatar */
            .table-responsive {
                overflow-x: auto;
            }
        }
        /* --- KOD MOBILE VIEW (RESPONSIF) TAMAT --- */

    </style>
</head>
<body>

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
                <span class="user-name d-none d-sm-inline"><?= htmlspecialchars($tech['name']) ?></span> 
                <a href="profile_tech.php" title="Go to My Profile" style="color: inherit; text-decoration: none;">
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
                    <form method="post" action="manageItem_tech.php" enctype="multipart/form-data">
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
                        <h6 class="mt-4">B. Item Details (Optional)</h6>
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
                            <thead><tr><th>Item Type</th><th>Category</th><th class="text-center">Total</th><th class="text-center">Available</th><th>Actions</th></tr></thead>
                            <tbody>
                            <?php if (empty($item_details)): ?>
                                <tr><td colspan="5" class="text-center text-muted py-5"><i class="fa-solid fa-box-open fa-2x mb-2"></i><br>No items found. Add one using the form.</td></tr>
                            <?php else: foreach($item_details as $item): ?>
                                <tr>
                                    <td><strong><?= htmlspecialchars($item['item_name']) ?></strong></td>
                                    <td><?= htmlspecialchars($item['category_name']) ?></td>
                                    <td class="text-center"><span class="badge rounded-pill text-bg-secondary"><?= $item['total_units'] ?></span></td>
                                    <td class="text-center"><span class="badge rounded-pill text-bg-success"><?= $item['available_units'] ?></span></td>
                                    
                                    <td class="d-flex gap-2">
                                        <a href="assets_technician.php?item_id=<?= $item['item_id'] ?>" class="btn btn-sm btn-outline-info" title="View Units"><i class="fa fa-eye"></i></a>
                                        <button class="btn btn-sm btn-outline-warning" title="Edit Item Type" onclick='openEditItemModal(<?= htmlspecialchars(json_encode($item), ENT_QUOTES, 'UTF-8') ?>)'><i class="fa fa-edit"></i></button>
                                        <button class="btn btn-sm btn-outline-danger" title="Delete Item Type" onclick="deleteItem(<?= $item['item_id'] ?>, '<?= htmlspecialchars(addslashes($item['item_name']), ENT_QUOTES, 'UTF-8') ?>')"><i class="fa fa-trash"></i></button>
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
                        <form method="post" action="manageItem_tech.php" enctype="multipart/form-data">
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
                                    <div class="d-flex align-items-center">
                                        <?php if (!empty($cat['image_url'])): ?>
                                            <img src="../<?= htmlspecialchars($cat['image_url']) ?>" class="category-img-sm" alt="Category Image">
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
            <form method="post" action="manageItem_tech.php" enctype="multipart/form-data">
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
            <form method="post" action="manageItem_tech.php" enctype="multipart/form-data">
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

<script src="https:
<script src="https:
<script>
    <?php
    if (isset($_SESSION['message'])) {
        $message = $_SESSION['message'];
        
        $text_escaped = str_replace("'", "\'", $message['text']);
        echo "Swal.fire({
            icon: '{$message['type']}',
            title: '{$text_escaped}',
            toast: true,
            position: 'top-end',
            showConfirmButton: false,
            timer: 3500,
            timerProgressBar: true
        });";
        unset($_SESSION['message']);
    }
    ?>

    function openEditCategoryModal(category) {
        document.getElementById('edit_category_id').value = category.category_id;
        document.getElementById('edit_category_name').value = category.category_name;
        
        var editModal = new bootstrap.Modal(document.getElementById('editCategoryModal'));
        
        
        var mainModalEl = document.getElementById('categoryModal');
        var mainModal = bootstrap.Modal.getInstance(mainModalEl);
        if (mainModal) {
            mainModal.hide();
        }
        
        editModal.show();
    }

    function deleteCategory(id, name) {
        Swal.fire({
            title: `Delete '${name}'?`,
            text: "This action cannot be undone! Ensure no active assets are linked to this category.",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            cancelButtonColor: '#6c757d',
            confirmButtonText: 'Yes, delete it!'
        }).then((result) => {
            if (result.isConfirmed) {
                window.location.href = 'manageItem_tech.php?delete_category_id=' + id;
            }
        });
    }

    function openEditItemModal(item) {
        document.getElementById('edit_item_id').value = item.item_id;
        document.getElementById('edit_item_name').value = item.item_name;
        
        document.getElementById('edit_category_id_select').value = item.category_id; 
        document.getElementById('edit_description').value = item.description;

        
        document.getElementById('edit_item_quantity').value = 0;
        document.getElementById('edit_item_brand').value = '';
        document.getElementById('edit_item_model').value = '';

        new bootstrap.Modal(document.getElementById('editItemModal')).show();
    }

    function deleteItem(id, name) {
        Swal.fire({
            title: `Delete '${name}' and all its units?`,
            text: "This will permanently delete the item type AND all of its associated asset units. This action cannot be undone!",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            cancelButtonColor: '#6c757d',
            confirmButtonText: 'Yes, delete everything!'
        }).then((result) => {
            if (result.isConfirmed) {
                window.location.href = 'manageItem_tech.php?delete_item_id=' + id;
            }
        });
    }

    
    document.addEventListener('DOMContentLoaded', function() {
        const sidebar = document.querySelector('.sidebar');
        const toggleButton = document.getElementById('sidebarToggle');
        const links = document.querySelectorAll('.sidebar a'); 

        if (toggleButton && sidebar) {
            toggleButton.addEventListener('click', function() {
                sidebar.classList.toggle('active');
            });

            
            links.forEach(link => {
                link.addEventListener('click', () => {
                    
                    if (window.innerWidth <= 992) {
                        sidebar.classList.remove('active');
                    }
                });
            });

            
            const editCatModal = document.getElementById('editCategoryModal');
            editCatModal.addEventListener('hidden.bs.modal', function () {
                const mainModalEl = document.getElementById('categoryModal');
                if (mainModalEl) {
                    const mainModal = bootstrap.Modal.getInstance(mainModalEl);
                    if (!mainModal) {
                        new bootstrap.Modal(mainModalEl).show();
                    } else {
                        mainModal.show();
                    }
                }
            });
        }
    });
    
</script>
</body>
</html>