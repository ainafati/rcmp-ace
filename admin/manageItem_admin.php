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
    
    
    if ($file["size"] > 5000000) { $_SESSION['message'] = ['type' => 'error', 'text' => 'Image size is too large (max 5MB).']; return NULL; }
    if (!in_array($fileExt, ['jpg', 'jpeg', 'png', 'webp'])) { $_SESSION['message'] = ['type' => 'error', 'text' => 'Only JPG, JPEG, PNG, & WEBP files are allowed.']; return NULL; }

    $newFileName = uniqid('img_', true) . "." . $fileExt;
    $server_path = $targetDir . $newFileName; 
    $db_path = $dbSubDir . $newFileName;     

    
    if (!is_dir($targetDir)) {
        if (!mkdir($targetDir, 0777, true)) {
             $_SESSION['message'] = ['type' => 'error', 'text' => 'Ralat mencipta direktori muat naik atau kebenaran tidak mencukupi.'];
             return NULL;
        }
    }

    if (move_uploaded_file($file["tmp_name"], $server_path)) {
        return $db_path; 
    } else {
        $_SESSION['message'] = ['type' => 'error', 'text' => 'Ralat memuat naik imej (Semak kebenaran fail).'];
        return NULL;
    }
}

function generateItemAcronym($itemName) {
    
    $cleanedName = preg_replace('/[^a-zA-Z\s]/', '', $itemName);
    
    
    $words = explode(' ', $cleanedName);
    $acronym = '';
    
    
    foreach ($words as $word) {
        if (!empty($word)) {
            $acronym .= strtoupper($word[0]);
        }
    }
    
    
    if (empty($acronym) && strlen($cleanedName) >= 2) {
        $acronym = strtoupper(substr($cleanedName, 0, 2));
    } elseif (empty($acronym)) {
        $acronym = 'XX'; 
    }

    
    return substr($acronym, 0, 3);
}



function refValues($arr){
    if (version_compare(PHP_VERSION, '5.3.0') >= 0) {
        $refs = array();
        foreach($arr as $key => $value)
            $refs[$key] = &$arr[$key];
        return $refs;
    }
    return $arr;
}






$pending_count_for_badge = 0; 

$stmt_badge = $conn->prepare("SELECT COUNT(DISTINCT reserve_id) FROM reservation_items WHERE status = 'Pending'");

if ($stmt_badge) {
    $stmt_badge->execute();
    $stmt_badge->bind_result($pending_count_for_badge);
    $stmt_badge->fetch();
    $stmt_badge->close();
}



$categories = $conn->query("SELECT category_id, category_name FROM categories ORDER BY category_name ASC")->fetch_all(MYSQLI_ASSOC);


$item_details_query = "
    SELECT 
        i.item_id,
        i.item_name,
        i.image_url,
        c.category_name,
        c.category_id,
        COUNT(a.asset_id) AS total_units,
        SUM(CASE WHEN a.status = 'Available' THEN 1 ELSE 0 END) AS available_units
    FROM item i
    JOIN categories c ON i.category_id = c.category_id
    LEFT JOIN assets a ON i.item_id = a.item_id
    GROUP BY i.item_id
    ORDER BY i.item_name ASC
";
$item_details = [];
$result = $conn->query($item_details_query);
if ($result) {
    $item_details = $result->fetch_all(MYSQLI_ASSOC);
} 




if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['add_category'])) {
    $category_name = trim($_POST['category_name']);
    
    if (!empty($category_name)) {
        
        $stmt = $conn->prepare("INSERT INTO categories (category_name) VALUES (?)");
        
        if ($stmt === FALSE) {
            $_SESSION['message'] = ['type' => 'error', 'text' => 'SQL Prepare Error (Add Cat): ' . $conn->error];
        } else {
            $stmt->bind_param("s", $category_name);
            
            if ($stmt->execute()) {
                $_SESSION['message'] = ['type' => 'success', 'text' => 'Category added successfully!'];
            } else {
                $_SESSION['message'] = ['type' => 'error', 'text' => 'Error adding category: ' . $stmt->error];
            }
            $stmt->close();
        }
    } else {
        $_SESSION['message'] = ['type' => 'error', 'text' => 'Category name cannot be empty.'];
    }
    header("Location: manageItem_admin.php");
    exit();
}


if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['edit_category'])) {
    $category_id = (int)$_POST['edit_category_id'];
    $category_name = trim($_POST['edit_category_name']);
    
    $update_fields = ["category_name = ?"];
    $update_params = [$category_name];
    $types = "s";
    
    
    $update_params[] = $category_id;
    $types .= "i";
    
    $update_query = "UPDATE categories SET " . implode(", ", $update_fields) . " WHERE category_id = ?";
    $stmt = $conn->prepare($update_query);

    if ($stmt === FALSE) {
        $_SESSION['message'] = ['type' => 'error', 'text' => 'SQL Prepare Error (Edit Cat): ' . $conn->error];
    } else {
        call_user_func_array([$stmt, 'bind_param'], refValues(array_merge([$types], $update_params)));

        if ($stmt->execute()) {
            $_SESSION['message'] = ['type' => 'success', 'text' => 'Category updated successfully!'];
        } else {
            $_SESSION['message'] = ['type' => 'error', 'text' => 'Error updating category: ' . $stmt->error];
        }
        $stmt->close();
    }
    
    header("Location: manageItem_admin.php"); exit();
}


if (isset($_GET['delete_category_id'])) {
    $delete_id = (int)$_GET['delete_category_id'];
    
    
    $stmt_info = $conn->prepare("SELECT category_name FROM categories WHERE category_id = ?"); 
    $stmt_info->bind_param("i", $delete_id);
    $stmt_info->execute();
    $stmt_info->bind_result($category_name_to_delete);
    $stmt_info->fetch();
    $stmt_info->close();
    
    $check_item = $conn->prepare("SELECT COUNT(*) FROM item WHERE category_id = ?");
    $check_item->bind_param("i", $delete_id);
    $check_item->execute();
    $check_item->bind_result($item_count);
    $check_item->fetch();
    $check_item->close();

    if ($item_count > 0) {
        $_SESSION['message'] = ['type' => 'error', 'text' => 'Cannot delete category: ' . $item_count . ' item type(s) are still linked to it.'];
        header("Location: manageItem_admin.php"); exit();
    }
    
    $stmt = $conn->prepare("DELETE FROM categories WHERE category_id = ?");
    $stmt->bind_param("i", $delete_id);
    if ($stmt->execute()) {
        $_SESSION['message'] = ['type' => 'success', 'text' => 'Category deleted.'];
    } else {
        $_SESSION['message'] = ['type' => 'error', 'text' => 'Could not delete category: ' . $stmt->error];
    }
    $stmt->close();
    header("Location: manageItem_admin.php"); exit();
}



if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['add_item_type_and_units'])) {
    
    $item_name = ucwords (trim($_POST['item_name']));
    $category_id = (int)$_POST['category_id'];
    $quantity = (int)$_POST['quantity'];
    $batch_brand = isset($_POST['batch_brand']) ? trim($_POST['batch_brand']) : '';
    $batch_model = isset($_POST['batch_model']) ? trim($_POST['batch_model']) : '';
    
    
    $image_path = handleImageUpload('item_image', 'assets/item_images'); 

    $conn->begin_transaction();
    try {
        
        $stmt_item = $conn->prepare("INSERT INTO item (item_name, category_id, image_url) VALUES (?, ?, ?, ?)");
        if ($stmt_item === FALSE) throw new Exception("SQL Prepare Error (Add Item): " . $conn->error);
        
        
        $image_path_to_bind = isset($image_path) ? $image_path : '';          
        $stmt_item->bind_param("siss", $item_name, $category_id, $image_path_to_bind);
        if (!$stmt_item->execute()) throw new Exception("Error adding item type: " . $stmt_item->error);
        $new_item_id = $conn->insert_id;
        $stmt_item->close();
        
        if ($new_item_id > 0 && $quantity > 0) {
            
            
            $akronim = generateItemAcronym($item_name); 

            
            
            $asset_status = 'Available';
            $asset_insert_stmt = $conn->prepare("INSERT INTO assets (item_id, asset_code, brand, model, status) VALUES (?, ?, ?, ?, ?)");
            if ($asset_insert_stmt === FALSE) throw new Exception("SQL Prepare Error (Add Asset): " . $conn->error);
            
            
            $start_count = 0; 
            $first_code = '';
            $last_code = '';

            for ($i = 1; $i <= $quantity; $i++) {
                $asset_number = $start_count + $i;
                
                $asset_code = $akronim . "-" . str_pad($asset_number, 4, '0', STR_PAD_LEFT);

                if ($i == 1) $first_code = $asset_code;
                if ($i == $quantity) $last_code = $asset_code;

                $asset_insert_stmt->bind_param("issss", $new_item_id, $asset_code, $batch_brand, $batch_model, $asset_status);
                if (!$asset_insert_stmt->execute()) throw new Exception("Error adding asset unit: " . $asset_insert_stmt->error);
            }
            $asset_insert_stmt->close();
        }

        $conn->commit();
        $_SESSION['message'] = ['type' => 'success', 'text' => 'Item Type and ' . $quantity . ' unit successfully added! (Code: ' . $first_code . ' until ' . $last_code . ')'];

    } catch (Exception $e) {
        $conn->rollback();
        safe_unlink($image_path); 
        $_SESSION['message'] = ['type' => 'error', 'text' => 'Database error: ' . $e->getMessage()];
    }

    header("Location: manageItem_admin.php");
    exit();
}


if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['edit_item_type'])) {
    
    $item_id = (int)$_POST['edit_item_id'];
    $item_name = ucwords(trim($_POST['edit_item_name']));
    $category_id = (int)$_POST['edit_category_id'];
    $quantity_to_add = isset($_POST['quantity']) ? (int)$_POST['quantity'] : 0;
    $batch_brand = isset($_POST['batch_brand']) ? trim($_POST['batch_brand']) : '';
    $batch_model = isset($_POST['batch_model']) ? trim($_POST['batch_model']) : '';

    if ($item_id <= 0 || $category_id <= 0) {
        $_SESSION['message'] = ['type' => 'error', 'text' => 'Error: Invalid Item ID or Category ID selected for update.'];
        header("Location: manageItem_admin.php"); exit();
    }

    $conn->begin_transaction();

    try {
        $update_fields = ["item_name = ?", "category_id = ?"];
        $update_params = [$item_name, $category_id];
        $types = "sis";
        $old_image_path = NULL;
        
        
        $new_image_path = handleImageUpload('edit_item_image', 'assets/item_images');

        $old_img_stmt = $conn->prepare("SELECT image_url FROM item WHERE item_id = ?");
        $old_img_stmt->bind_param("i", $item_id);
        $old_img_stmt->execute();
        $old_img_stmt->bind_result($old_image_path);
        $old_img_stmt->fetch();
        $old_img_stmt->close();

        if ($new_image_path) {
            $update_fields[] = "image_url = ?";
            $update_params[] = $new_image_path;
            $types .= "s";
            safe_unlink($old_image_path); 
        }

        $update_params[] = $item_id;
        $types .= "i";

        $update_query = "UPDATE item SET " . implode(", ", $update_fields) . " WHERE item_id = ?";
        $stmt = $conn->prepare($update_query);
        if ($stmt === FALSE) throw new Exception("SQL Prepare Error (Update Item): " . $conn->error);
        
        call_user_func_array([$stmt, 'bind_param'], refValues(array_merge([$types], $update_params)));

        if (!$stmt->execute()) throw new Exception("Error updating item type: " . $stmt->error);
        $stmt->close();
        
        $message_part_2 = "";
        $first_new_code = '';

        if ($quantity_to_add > 0) {
            
            
            $akronim = generateItemAcronym($item_name); 

            
            
            
            $asset_stmt = $conn->prepare("SELECT COUNT(*) AS max_num FROM assets WHERE item_id = ?");
            if ($asset_stmt === FALSE) throw new Exception("SQL Prepare Error (Edit Asset Max Count): " . $conn->error);
            
            $asset_stmt->bind_param("i", $item_id);
            $asset_stmt->execute();
            $asset_stmt->bind_result($max_num);
            $asset_stmt->fetch();
            $asset_stmt->close();
            
            $start_count = intval($max_num); 

            
            
            $asset_status = 'Available';
            $asset_insert_stmt = $conn->prepare("INSERT INTO assets (item_id, asset_code, brand, model, status) VALUES (?, ?, ?, ?, ?)");
            if ($asset_insert_stmt === FALSE) throw new Exception("SQL Prepare Error (Edit Add Asset): " . $conn->error);
            
            for ($i = 1; $i <= $quantity_to_add; $i++) {
                
                $asset_number = $start_count + $i;
                $asset_code = $akronim . "-" . str_pad($asset_number, 4, '0', STR_PAD_LEFT);

                if ($i == 1) $first_new_code = $asset_code;

                $asset_insert_stmt->bind_param("issss", $item_id, $asset_code, $batch_brand, $batch_model, $asset_status);
                if (!$asset_insert_stmt->execute()) throw new Exception("Error adding new asset unit: " . $asset_insert_stmt->error);
            }
            $asset_insert_stmt->close();
            $message_part_2 = " and add " . $quantity_to_add . " new unit (Asset Code starts: " . $first_new_code . ").";
        }

        $conn->commit();
        $_SESSION['message'] = ['type' => 'success', 'text' => 'Item berjaya dikemas kini' . $message_part_2 . '!'];

    } catch (Exception $e) {
        $conn->rollback();
        safe_unlink($new_image_path);
        $_SESSION['message'] = ['type' => 'error', 'text' => 'Database error: ' . $e->getMessage()];
    }

    header("Location: manageItem_admin.php");
    exit();
}

if (isset($_GET['delete_item_id'])) {
    
    $delete_id = (int)$_GET['delete_item_id'];
    
    $stmt_info = $conn->prepare("SELECT item_name, image_url FROM item WHERE item_id = ?");
    $stmt_info->bind_param("i", $delete_id);
    $stmt_info->execute();
    $stmt_info->bind_result($item_name_to_delete, $image_to_delete);
    $stmt_info->fetch();
    $stmt_info->close();
    
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
        
        safe_unlink($image_to_delete);
        $_SESSION['message'] = ['type' => 'success', 'text' => 'The item type and all its units have been deleted.'];
        
    } catch (Exception $e) {
        $conn->rollback();
        $_SESSION['message'] = ['type' => 'error', 'text' => 'Cannot deleted item.It may be part of booking record: ' . $e->getMessage()];
    }
    header("Location: manageItem_admin.php"); exit();
}


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

<div class="sidebar-overlay" id="overlay"></div>

<div class="sidebar" id="admin-sidebar">
    <div class="sidebar-header">
        <div class="logo-icon"><i class="fa-solid fa-user-shield"></i></div>
        <div class="logo-text"><strong>UniKL Admin</strong><span>System Control</span></div>
    </div>
    <a href="manageItem_admin.php" class="active"><i class="fa-solid fa-box-archive"></i> Manage Items</a>
    <a href="manage_accounts.php"><i class="fa-solid fa-users-cog"></i> Manage Accounts</a>
    <a href="report_admin.php"><i class="fa-solid fa-chart-pie"></i> System Report</a>
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
            <a href="add_item.php" class="btn btn-primary px-4 py-2 rounded-3 shadow-sm">
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
                                    <a href="assets.php?item_id=<?= $item['item_id'] ?>" class="action-btn btn-view" title="View"><i class="fa fa-eye small"></i></a>
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

                    <div class="p-3 bg-light rounded-3 mb-3">
                        <label class="form-label small fw-bold">Add New Units (Quantity)</label>
                        <input type="number" id="edit_item_quantity" name="edit_item_quantity" class="form-control mb-2" min="0" value="0">
                        
                        <div class="form-check small">
                            <input type="checkbox" class="form-check-input" id="edit_enable_manual_code" name="enable_manual_code">
                            <label class="form-check-label" for="edit_enable_manual_code">Manual asset codes for new units</label>
                        </div>
                    </div>

                    <div id="edit_manual_assets_container" style="display: none;">
                        <div id="edit_dynamic_asset_inputs"></div>
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
document.addEventListener('DOMContentLoaded', function() {
    // 1. SIDEBAR & OVERLAY LOGIC
    const sidebarToggle = document.getElementById('sidebarToggle');
    const sidebar = document.getElementById('admin-sidebar');
    const overlay = document.getElementById('overlay');

    if (sidebarToggle) {
        sidebarToggle.addEventListener('click', function() {
            sidebar.classList.toggle('active');
            overlay.classList.toggle('active');
        });
    }

    overlay.addEventListener('click', function() {
        sidebar.classList.remove('active');
        overlay.classList.remove('active');
    });

    // 2. DYNAMIC INPUTS LOGIC
    const editQtyInput = document.getElementById('edit_item_quantity');
    const editCheckbox = document.getElementById('edit_enable_manual_code');
    const editContainer = document.getElementById('edit_manual_assets_container');
    const editDynamicInputs = document.getElementById('edit_dynamic_asset_inputs');

    function updateEditAssetInputs() {
        const qty = parseInt(editQtyInput.value) || 0;
        if (editCheckbox.checked && qty > 0) {
            editContainer.style.display = 'block';
            let html = '';
            for (let i = 1; i <= Math.min(qty, 50); i++) {
                html += `<div class="mb-2"><input type="text" name="manual_codes[]" class="form-control manual-code-input small" placeholder="Asset Code ${i}" required></div>`;
            }
            editDynamicInputs.innerHTML = html;
        } else {
            editContainer.style.display = 'none';
            editDynamicInputs.innerHTML = '';
        }
    }

    editQtyInput.addEventListener('input', updateEditAssetInputs);
    editCheckbox.addEventListener('change', updateEditAssetInputs);
});

// GLOBAL FUNCTIONS
function openEditItemModal(item) {
    document.getElementById('edit_item_id').value = item.item_id;
    document.getElementById('edit_item_name').value = item.item_name;
    document.getElementById('edit_category_id_select').value = item.category_id;
    
    var myModal = new bootstrap.Modal(document.getElementById('editItemModal'));
    myModal.show();
}

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
