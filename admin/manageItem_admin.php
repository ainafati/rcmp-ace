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
    $shortName = $fullName;
}

// Variable ini yang dipanggil pada baris 741
$displayName = trim($shortName); 

$admin_id_for_log = $person_id_session;
$admin_name_for_log = $admin_data['name']; 

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

<div class="sidebar-overlay" id="sidebar-overlay"></div>

<div class="sidebar" id="admin-sidebar">
    <div>
        <div class="sidebar-header">
            <div class="logo-icon"><i class="fa-solid fa-user-shield"></i></div>
            <div class="logo-text"><strong>UniKL Admin</strong><span>System Control</span></div>
        </div>
        <a href="manageItem_admin.php" class="active" ><i class="fa-solid fa-box-archive"></i> Manage Items</a>
        <a href="manage_accounts.php" ><i class="fa-solid fa-users-cog"></i> Manage Accounts</a>
        <a href="report_admin.php" ><i class="fa-solid fa-chart-pie"></i> System Report</a>
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
            <a href="add_item.php" class="btn btn-primary px-4 shadow-sm">
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