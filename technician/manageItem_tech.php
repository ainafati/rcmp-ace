<?php
session_start();

include '../config.php'; // Pastikan fail konfigurasi ini berfungsi dengan baik

// --- SECURITY CHECK ---
if (!isset($_SESSION['person_id']) || $_SESSION['logged_in_role'] !== 'Technician') {
    session_unset();
    session_destroy();
    header("Location: ../login.php");
    exit();
}
$person_id = (int)$_SESSION['person_id'];

// Dapatkan nama Technician
$stmt_tech = $conn->prepare("SELECT name FROM person WHERE person_id = ?");
$stmt_tech->bind_param("i", $person_id);
$stmt_tech->execute();
$result_tech = $stmt_tech->get_result();
$tech_data = $result_tech->fetch_assoc();
$stmt_tech->close();

if ($tech_data) {
    $tech_name = $tech_data['name'];
} else {
    $tech_name = 'Technician Unknown'; 
}

// FUNGSI UTILITY
function safe_unlink($path) {
    if (file_exists($path) && is_file($path)) {
        return unlink($path);
    }
    return false;
}

function log_activity($conn, $user_type, $user_id, $action_code, $details) {
    // Funtion placeholder (Pastikan fungsi ini wujud dalam projek anda)
    return true; 
}
// ----------------------------------------------------------------------


// =================================================================================================
// 1. HANDLER: TAMBAH ITEM BARU (TERMASUK AUTO-GENERATE ASSET CODE)

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['add_item_type_and_units'])) {
    $item_name = trim($_POST['item_name']);
    $category_id = (int)$_POST['category_id'];
    $description = trim($_POST['description']);
    $quantity = (int)$_POST['quantity'];
    $batch_brand = trim($_POST['batch_brand']);
    $batch_model = trim($_POST['batch_model']);

    $db_path_item = ""; 
    
    // --- START LOGIK MUAT NAIK IMEJ ITEM BARU ---
    if (isset($_FILES['item_image']) && $_FILES['item_image']['error'] === 0) { 
        $image = $_FILES['item_image'];
        $image_name = uniqid('item_', true) . '.' . strtolower(pathinfo(basename($image['name']), PATHINFO_EXTENSION));
        $db_path_item = 'uploads/' . $image_name;
        $server_path = '../' . $db_path_item;

        if (!is_dir('../uploads')) { mkdir('../uploads', 0777, true); }
        
        if (!move_uploaded_file($image['tmp_name'], $server_path)) {
            $db_path_item = ""; 
        }
    }
    // --- END LOGIK MUAT NAIK IMEJ ITEM BARU ---

    $conn->begin_transaction();
    try {
        // 1. Masukkan Jenis Item ke jadual 'item'
        $stmt_item = $conn->prepare("INSERT INTO item (item_name, category_id, description, image_url) VALUES (?, ?, ?, ?)");
        $stmt_item->bind_param("siss", $item_name, $category_id, $description, $db_path_item);
        $stmt_item->execute();
        $new_item_id = $conn->insert_id;
        $stmt_item->close();

        // 2. Masukkan Unit Aset ke jadual 'assets' dengan AUTO-GENERATE CODE
        if ($quantity > 0) {
            // A. Dapatkan Prefix Kategori dari jadual categories
            $stmt_prefix = $conn->prepare("SELECT prefix FROM categories WHERE category_id = ?");
            $stmt_prefix->bind_param("i", $category_id);
            $stmt_prefix->execute();
            $result_prefix = $stmt_prefix->get_result();
            
            // Penggantian untuk '??' - Versi Lama
            $prefix_data = $result_prefix->fetch_assoc();
            if ($prefix_data && isset($prefix_data['prefix'])) {
                $category_prefix = $prefix_data['prefix'];
            } else {
                $category_prefix = 'AST'; // Default 'AST' jika tiada prefix
            }
            $stmt_prefix->close();


            // B. Dapatkan Nombor Siri Terakhir bagi KATEGORI ini (berdasarkan prefix)
            $sql_last_serial = "
                SELECT MAX(CAST(SUBSTRING_INDEX(a.asset_code, '-', -1) AS UNSIGNED)) as max_serial
                FROM assets a
                WHERE a.asset_code LIKE ?
            ";
            
            $stmt_last_serial = $conn->prepare($sql_last_serial);
            $prefix_like = $category_prefix . '-%';
            $stmt_last_serial->bind_param("s", $prefix_like);
            $stmt_last_serial->execute();
            $result_serial = $stmt_last_serial->get_result();
            
            // Penggantian untuk '??' - Versi Lama
            $serial_data = $result_serial->fetch_assoc();
            $last_serial = (isset($serial_data['max_serial']) && $serial_data['max_serial'] !== null) ? $serial_data['max_serial'] : 0;

            $stmt_last_serial->close();


            // C. Gelung untuk masukkan unit baru dengan asset_code yang dijana
            $stmt_asset = $conn->prepare("INSERT INTO assets (item_id, asset_code, brand, model) VALUES (?, ?, ?, ?)");
            for ($i = 0; $i < $quantity; $i++) {
                $last_serial++; // Tambah satu (increment)
                
                // Format Nombor Siri (Contoh: LPT-0001) - Guna 4 digit padding
                $new_asset_serial = str_pad($last_serial, 4, '0', STR_PAD_LEFT);
                $new_asset_code = $category_prefix . '-' . $new_asset_serial;

                // KEMASKINI BIND PARAM: tambah $new_asset_code
                $stmt_asset->bind_param("isss", $new_item_id, $new_asset_code, $batch_brand, $batch_model);
                $stmt_asset->execute();
            }
            $stmt_asset->close();
        }

        $conn->commit();
        $log_details = "Technician '{$tech_name}' (ID: {$person_id}) telah menambah item baru: '{$item_name}' (ID: {$new_item_id}) dengan {$quantity} unit.";
        log_activity($conn, 'tech', $person_id, 'TECH_ADD_ITEM', $log_details);
        $_SESSION['message'] = ['type' => 'success', 'text' => 'Successfully created ' . htmlspecialchars($item_name) . ' with ' . $quantity . ' units.'];
    } catch (Exception $e) {
        $conn->rollback();
        safe_unlink('../' . $db_path_item); 
        $_SESSION['message'] = ['type' => 'error', 'text' => 'Database error: Item creation failed. ' . $e->getMessage()];
    }
    header("Location: manageItem_tech.php"); exit();
}


// =================================================================================================
// 2. HANDLER: KEMASKINI ITEM JENIS (TERMASUK AUTO-GENERATE ASSET CODE)

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['edit_item_type'])) {
    $item_id = (int)$_POST['edit_item_id'];
    $item_name = trim($_POST['edit_item_name']);
    $category_id = (int)$_POST['edit_category_id'];
    $description = trim($_POST['edit_description']);
    $quantity_to_add = (int)$_POST['quantity'];
    $batch_brand = trim($_POST['batch_brand']);
    $batch_model = trim($_POST['batch_model']);

    $update_fields = ["item_name = ?", "category_id = ?", "description = ?"];
    $bind_params = ["s", $item_name, "i", $category_id, "s", $description]; // Type, value pairs
    $db_path_item = "";
    $old_image_url = null;

    // --- START LOGIK MUAT NAIK IMEJ ITEM SUNTINGAN ---
    if (isset($_FILES['edit_item_image']) && $_FILES['edit_item_image']['error'] === 0) {
        // Dapatkan imej lama sebelum overwrite
        $stmt_old = $conn->prepare("SELECT image_url FROM item WHERE item_id = ?");
        $stmt_old->bind_param("i", $item_id);
        $stmt_old->execute();
        $stmt_old->bind_result($old_image_url);
        $stmt_old->fetch();
        $stmt_old->close();

        $image = $_FILES['edit_item_image'];
        $image_name = uniqid('item_', true) . '.' . strtolower(pathinfo(basename($image['name']), PATHINFO_EXTENSION));
        $db_path_item = 'uploads/' . $image_name;
        $server_path = '../' . $db_path_item;
        
        if (move_uploaded_file($image['tmp_name'], $server_path)) {
            $update_fields[] = "image_url = ?";
            array_push($bind_params, "s", $db_path_item);
        }
    }
    // --- END LOGIK MUAT NAIK IMEJ ITEM SUNTINGAN ---
    
    // Siapkan query kemaskini
    $sql_update = "UPDATE item SET " . implode(", ", $update_fields) . " WHERE item_id = ?";
    array_push($bind_params, "i", $item_id);

    $conn->begin_transaction();
    try {
        // 1. Kemaskini Jenis Item
        $stmt_update = $conn->prepare($sql_update);
        
        // Bind parameters secara dinamik
        $types = ''; $values = [];
        foreach ($bind_params as $param) {
            if (strlen($param) == 1 && in_array($param, ['s', 'i', 'd'])) {
                $types .= $param;
            } else {
                $values[] = &$param; // Gunakan pass by reference
            }
        }
        // Perlu menggunakan call_user_func_array untuk bind_param dinamik
        call_user_func_array(array($stmt_update, 'bind_param'), array_merge(array($types), $values));

        $stmt_update->execute();
        $stmt_update->close();
        
        // Padam imej lama secara fizikal HANYA jika muat naik baru berjaya
        if (!empty($db_path_item) && !empty($old_image_url)) { safe_unlink('../' . $old_image_url); }


        // 2. Tambah Unit Aset Baru (Jika Kuantiti > 0) dengan AUTO-GENERATE CODE
        if ($quantity_to_add > 0) {
            // A. Dapatkan Prefix Kategori dari jadual categories
            $stmt_prefix = $conn->prepare("SELECT prefix FROM categories WHERE category_id = ?");
            $stmt_prefix->bind_param("i", $category_id); // Gunakan $category_id dari form edit
            $stmt_prefix->execute();
            $result_prefix = $stmt_prefix->get_result();
            
            // Penggantian untuk '??' - Versi Lama
            $prefix_data = $result_prefix->fetch_assoc();
            if ($prefix_data && isset($prefix_data['prefix'])) {
                $category_prefix = $prefix_data['prefix'];
            } else {
                $category_prefix = 'AST'; // Default 'AST'
            }
            $stmt_prefix->close();

            // B. Dapatkan Nombor Siri Terakhir bagi KATEGORI ini
            $sql_last_serial = "
                SELECT MAX(CAST(SUBSTRING_INDEX(a.asset_code, '-', -1) AS UNSIGNED)) as max_serial
                FROM assets a
                WHERE a.asset_code LIKE ?
            ";
            
            $stmt_last_serial = $conn->prepare($sql_last_serial);
            $prefix_like = $category_prefix . '-%';
            $stmt_last_serial->bind_param("s", $prefix_like);
            $stmt_last_serial->execute();
            $result_serial = $stmt_last_serial->get_result();
            
            // Penggantian untuk '??' - Versi Lama (ini yang menyebabkan ralat di baris 240)
            $serial_data = $result_serial->fetch_assoc();
            $last_serial = (isset($serial_data['max_serial']) && $serial_data['max_serial'] !== null) ? $serial_data['max_serial'] : 0;

            $stmt_last_serial->close();

            // C. Gelung untuk masukkan unit baru dengan asset_code yang dijana
            $stmt_asset = $conn->prepare("INSERT INTO assets (item_id, asset_code, brand, model) VALUES (?, ?, ?, ?)");
            for ($i = 0; $i < $quantity_to_add; $i++) {
                $last_serial++;
                $new_asset_serial = str_pad($last_serial, 4, '0', STR_PAD_LEFT);
                $new_asset_code = $category_prefix . '-' . $new_asset_serial;

                // KEMASKINI BIND PARAM
                $stmt_asset->bind_param("isss", $item_id, $new_asset_code, $batch_brand, $batch_model);
                $stmt_asset->execute();
            }
            $stmt_asset->close();
        }


        $conn->commit();
        $log_details = "Technician '{$tech_name}' (ID: {$person_id}) telah mengemas kini item '{$item_name}' (ID: {$item_id}). Ditambah {$quantity_to_add} unit.";
        log_activity($conn, 'tech', $person_id, 'TECH_EDIT_ITEM', $log_details);
        $_SESSION['message'] = ['type' => 'success', 'text' => 'Item updated successfully! Added ' . $quantity_to_add . ' new units.'];
    } catch (Exception $e) {
        $conn->rollback();
        safe_unlink('../' . $db_path_item); 
        $_SESSION['message'] = ['type' => 'error', 'text' => 'Database error: Item update failed. ' . $e->getMessage()];
    }
    header("Location: manageItem_tech.php"); exit();
}


// =================================================================================================
// 3. HANDLER: PADAM ITEM JENIS 

if (isset($_GET['delete_item_id'])) {
    $delete_id = (int)$_GET['delete_item_id'];
    
    // Dapatkan nama dan URL imej item untuk log & pemadaman
    $stmt_info = $conn->prepare("SELECT item_name, image_url FROM item WHERE item_id = ?");
    $stmt_info->bind_param("i", $delete_id);
    $stmt_info->execute();
    $stmt_info->bind_result($item_name_to_delete, $image_url_to_delete);
    $stmt_info->fetch();
    $stmt_info->close();

    $conn->begin_transaction();
    try {
        // Hapus aset berkaitan dari reservation_assets dahulu
        $conn->query("DELETE FROM reservation_assets WHERE asset_id IN (SELECT asset_id FROM assets WHERE item_id = $delete_id)");
        
        // Hapus aset (unit) yang berkaitan
        $conn->query("DELETE FROM assets WHERE item_id = $delete_id");
        
        // Hapus jenis item
        $stmt = $conn->prepare("DELETE FROM item WHERE item_id = ?");
        $stmt->bind_param("i", $delete_id);
        $stmt->execute();
        $stmt->close();
        
        $conn->commit();

        // Padam imej secara fizikal
        if (!empty($image_url_to_delete)) { 
            safe_unlink('../' . $image_url_to_delete);
        }

        $log_details = "Technician '{$tech_name}' (ID: {$person_id}) telah memadam item: '{$item_name_to_delete}' (ID: {$delete_id}) dan semua unit asetnya.";
        log_activity($conn, 'tech', $person_id, 'TECH_DELETE_ITEM', $log_details);
        $_SESSION['message'] = ['type' => 'success', 'text' => 'Item Type and all associated units deleted successfully!'];
    } catch (Exception $e) {
        $conn->rollback();
        $_SESSION['message'] = ['type' => 'error', 'text' => 'Database error: Item deletion failed. ' . $e->getMessage()];
    }
    header("Location: manageItem_tech.php"); exit();
}


// =================================================================================================
// 4. HANDLER: TAMBAH/EDIT/PADAM KATEGORI 

// Tambah Kategori 
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['add_category'])) {
    $category_name = trim($_POST['category_name']);
    $category_prefix = trim($_POST['category_prefix']); 
    
    $stmt = $conn->prepare("INSERT INTO categories (category_name, prefix) VALUES (?, ?)"); // UPDATE SQL
    $stmt->bind_param("ss", $category_name, $category_prefix);
    if ($stmt->execute()) {
        $new_cat_id = $stmt->insert_id;
        $log_details = "Technician '{$tech_name}' (ID: {$person_id}) telah menambah kategori baru: '{$category_name}' (ID: {$new_cat_id}). Prefix: {$category_prefix}";
        log_activity($conn, 'tech', $person_id, 'TECH_ADD_CATEGORY', $log_details);
        $_SESSION['message'] = ['type' => 'success', 'text' => 'Category added successfully!'];
    } else {
        $_SESSION['message'] = ['type' => 'error', 'text' => 'Failed to add category.'];
    }
    $stmt->close();
    header("Location: manageItem_tech.php"); exit();
}

// Edit Kategori 
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['edit_category'])) {
    $category_id = (int)$_POST['edit_category_id'];
    $category_name = trim($_POST['edit_category_name']);
    $category_prefix = trim($_POST['edit_category_prefix']);
    
    $stmt = $conn->prepare("UPDATE categories SET category_name = ?, prefix = ? WHERE category_id = ?"); // UPDATE SQL
    $stmt->bind_param("ssi", $category_name, $category_prefix, $category_id);
    if ($stmt->execute()) {
        $log_details = "Technician '{$tech_name}' (ID: {$person_id}) telah mengemas kini kategori (ID: {$category_id}) kepada nama '{$category_name}' dan prefix '{$category_prefix}'.";
        log_activity($conn, 'tech', $person_id, 'TECH_EDIT_CATEGORY', $log_details);
        $_SESSION['message'] = ['type' => 'success', 'text' => 'Category updated successfully!'];
    } else {
        $_SESSION['message'] = ['type' => 'error', 'text' => 'Failed to update category.'];
    }
    $stmt->close();
    header("Location: manageItem_tech.php"); exit();
}

// Padam Kategori 
if (isset($_GET['delete_category_id'])) {
    $delete_id = (int)$_GET['delete_category_id'];
    
    $stmt_name = $conn->prepare("SELECT category_name FROM categories WHERE category_id = ?");
    $stmt_name->bind_param("i", $delete_id);
    $stmt_name->execute();
    $stmt_name->bind_result($category_name_to_delete);
    $stmt_name->fetch();
    $stmt_name->close();

    $stmt = $conn->prepare("DELETE FROM categories WHERE category_id = ?");
    $stmt->bind_param("i", $delete_id);
    if ($stmt->execute()) {
        $log_details = "Technician '{$tech_name}' (ID: {$person_id}) telah memadam kategori: '{$category_name_to_delete}' (ID: {$delete_id}).";
        log_activity($conn, 'tech', $person_id, 'TECH_DELETE_CATEGORY', $log_details);
        $_SESSION['message'] = ['type' => 'success', 'text' => 'Category deleted successfully!'];
    } else {
         $_SESSION['message'] = ['type' => 'error', 'text' => 'Failed to delete category. Ensure no items are linked.'];
    }
    $stmt->close();
    header("Location: manageItem_tech.php"); exit();
}


// =================================================================================================
// 5. DATA FETCHING 

$categories = [];
// UPDATE: Ambil prefix juga
$stmt_cat = $conn->prepare("SELECT category_id, category_name, prefix FROM categories ORDER BY category_name ASC"); 
$stmt_cat->execute();
$result_cat = $stmt_cat->get_result();
while($row = $result_cat->fetch_assoc()) {
    $categories[] = $row;
}
$stmt_cat->close();

$item_details = [];
// Query untuk mendapatkan ringkasan item termasuk bilangan unit tersedia dan jumlah
$stmt_item = $conn->prepare("
    SELECT 
        i.item_id, i.item_name, i.description, i.category_id, i.image_url, 
        c.category_name, c.prefix, -- Tambah prefix di sini
        COUNT(a.asset_id) AS total_units,
        SUM(CASE WHEN a.status = 'Available' THEN 1 ELSE 0 END) AS available_units
    FROM item i
    JOIN categories c ON i.category_id = c.category_id
    LEFT JOIN assets a ON i.item_id = a.item_id
    GROUP BY i.item_id
    ORDER BY i.item_name ASC
");
$stmt_item->execute();
$result_item = $stmt_item->get_result();
while ($row = $result_item->fetch_assoc()) {
    $item_details[] = $row;
}
$stmt_item->close();

// Placeholder for pending requests count (e.g., from check_out table)
$pending_count_for_badge = 5; // Gantikan dengan logik carian sebenar jika ada

// =================================================================================================
?><!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Inventory — UniKL Technician</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        /* --- DEFINISI WARNA TEMA (ROOT VARIABLES) --- */
        :root {
            --primary-color: #06b6d4; /* Cyan 600 (Biru Teal Gelap) */
            --primary-hover: #0891b2; /* Cyan 700 (Warna Hover/Gelap) */
            --danger-color: #ef4444; /* Merah */
            
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
        
        /* LOGO ICON (Menggunakan --primary-color) */
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
        
        /* ACTIVE & HOVER LINK (Menggunakan --primary-color) */
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
        .sidebar a.logout-link:hover { 
            color: #fff; 
            background: var(--danger-color); 
        }
        
        /* 5. SIDEBAR BADGE STYLE */
        .sidebar a .badge {
            margin-left: auto; 
            font-size: 0.75rem;
            padding: 0.4em 0.6em;
            font-weight: 700;
            border-radius: 10px;
            background-color: var(--danger-color); 
            color: white;
        }

        /* Badge pada Active/Hover state */
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
        
        /* PRIMARY BUTTON (Menggunakan --primary-color dan --primary-hover) */
        .btn-primary { 
            background-color: var(--primary-color); /* Cyan */
            border: none; 
        }
        .btn-primary:hover { 
            background-color: var(--primary-hover); /* Cyan Gelap */
        }
        
        /* DIBUANG: .category-img-sm (Kerana imej berpindah ke Item) */
        .item-img-sm { width: 40px; height: 40px; object-fit: cover; border-radius: 8px; margin-right: 15px; }
        
        /* Sembunyikan toggle menu di desktop */
        .menu-toggle {
            display: none;
        }

        /* --- KOD MOBILE VIEW (RESPONSIF) MULA --- */
        @media (max-width: 992px) {
            .sidebar {
                transform: translateX(-100%);
                transition: transform 0.3s ease-in-out;
                width: 280px; 
                display: block; 
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
                flex-wrap: wrap; 
                gap: 10px;
            }
            .topbar h3 {
                font-size: 20px;
                margin-left: 15px; 
                flex-grow: 1; 
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
                <span class="user-name d-none d-sm-inline"><?= htmlspecialchars($tech_name) ?></span> 
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
                            <label class="form-label">Item Image (Optional)</label>
                            <input type="file" name="item_image" class="form-control" accept="image/*">
                            <small class="text-muted">Upload an image representing this item type.</small>
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
                                    <td>
                                        <?php if (!empty($item['image_url'])): ?>
                                            <img src="../<?= htmlspecialchars($item['image_url']) ?>" class="item-img-sm" alt="Item Image">
                                        <?php endif; ?>
                                        <strong><?= htmlspecialchars($item['item_name']) ?></strong>
                                    </td>
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
            <form method="post" action="manageItem_tech.php" enctype="multipart/form-data">
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
            <div class="modal-header"><h5 class="modal-title">Edit Item Type</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <form method="post" action="manageItem_tech.php" enctype="multipart/form-data">
                <div class="modal-body">
                    <input type="hidden" name="edit_item_type" value="1">
                    <input type="hidden" id="edit_item_id" name="edit_item_id">

                    <h6>Item Details</h6>
                    <div class="mb-3"><label class="form-label">Item Name</label><input type="text" id="edit_item_name" name="edit_item_name" class="form-control" required></div>
                    
                    <div class="mb-3">
                        <label class="form-label">New Item Image (Optional)</label>
                        <input type="file" name="edit_item_image" class="form-control" accept="image/*">
                        <small class="text-muted">Upload a new image to replace the old one.</small>
                    </div>

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

    // Mengendalikan Edit Modal Kategori (Logik bersarang dibuang)
    function openEditCategoryModal(category) {
        document.getElementById('edit_category_id').value = category.category_id;
        document.getElementById('edit_category_name').value = category.category_name;
        
        var editModal = new bootstrap.Modal(document.getElementById('editCategoryModal'));
        editModal.show();
    }

    function deleteCategory(id, name) {
        Swal.fire({
            title: `Delete '${name}'?`,
            text: "This action cannot be undone! Ensure no active items are linked to this category.",
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
        
        // Tetapkan nilai Category ID (Dropdown)
        document.getElementById('edit_category_id_select').value = item.category_id; 
        document.getElementById('edit_description').value = item.description;

        // Reset fields Add Units
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

            // Tutup sidebar pada skrin kecil apabila link diklik
            links.forEach(link => {
                link.addEventListener('click', () => {
                    
                    if (window.innerWidth <= 992) {
                        sidebar.classList.remove('active');
                    }
                });
            });

        }
    });
    
</script>
</body>
</html>