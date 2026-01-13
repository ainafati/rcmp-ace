<?php

session_start();
include '../config.php'; 

$allowed_role = 'Admin';
if (!isset($_SESSION['person_id']) || $_SESSION['logged_in_role'] !== $allowed_role) {
    header("Location: ../login.php");
    exit();
}

$admin_id_session = (int)$_SESSION['person_id'];
$admin_name_session = htmlspecialchars(isset($_SESSION['name']) ? $_SESSION['name'] : 'Admin');


if ($_SERVER["REQUEST_METHOD"] == "POST") {

    $username = trim(isset($_POST['username']) ? $_POST['username'] : '');
    $email = trim(isset($_POST['email']) ? $_POST['email'] : '');
    $id = trim(isset($_POST['id']) ? $_POST['id'] : '');
    $phoneNumber = trim(isset($_POST['phoneNumber']) ? $_POST['phoneNumber'] : '');
    $password = isset($_POST['password']) ? $_POST['password'] : '';
    $role = isset($_POST['role']) ? $_POST['role'] : '';

    
    
    
// Kita cari role yang dipilih DAN role 'User' secara tetap
$roles_to_find = [$role, 'User'];

// Kalau Admin, kita cari juga role 'Technician'
if (strtolower($role) === 'admin') {
    $roles_to_find[] = 'Technician';
}

$placeholders = implode(',', array_fill(0, count($roles_to_find), '?'));


    $stmt_roles = $conn->prepare("SELECT role_id, role_name FROM roles WHERE role_name IN ($placeholders)");
    
    
    $types = str_repeat('s', count($roles_to_find));
    $stmt_roles->bind_param($types, ...$roles_to_find);
    
    $stmt_roles->execute();
    $result_role = $stmt_roles->get_result();
    $roles_map = [];

    while ($row = $result_role->fetch_assoc()) {
        $roles_map[$row['role_name']] = $row['role_id'];
    }
    $stmt_roles->close();

    $role_id_main = isset($roles_map[$role]) ? $roles_map[$role] : null;
    $role_id_user = isset($roles_map['User']) ? $roles_map['User'] : null; 

    
    
    
    
    $needle = '@unikl.edu.my';
    
    $email_ends_with_unikl = (substr($email, -strlen($needle)) === $needle);
    
    
    if (empty($username) || empty($email) || empty($id) || empty($phoneNumber) || empty($password) || $role_id_main === null) {
        $_SESSION['error_message'] = "Semua medan wajib diisi atau Peranan tidak sah.";
        header("Location: manage_accounts.php");
        exit();
    }
    
    if (!filter_var($email, FILTER_VALIDATE_EMAIL) || !$email_ends_with_unikl) {
        $_SESSION['error_message'] = "Format e-mel UniKL tidak sah.";
        header("Location: manage_accounts.php");
        exit();
    }
    
    
    if (!preg_match('/^\d{6,12}$/', $id)) {
        $_SESSION['error_message'] = "Format Nombor Pengenalan (ID) tidak sah. Mesti antara 6 hingga 12 digit.";
        header("Location: manage_accounts.php");
        exit();
    }
    
    
    if ($role !== 'Technician' && $role !== 'User' && $role !== 'Admin') { 
        $_SESSION['error_message'] = "Peranan tidak sah.";
        header("Location: manage_accounts.php");
        exit();
    }
    
    
    if (strtolower($role) === 'technician' && !$role_id_user) {
        $_SESSION['error_message'] = "Ralat Konfigurasi DB: Peranan 'User' diperlukan tetapi tidak ditemui.";
        header("Location: manage_accounts.php");
        exit();
    }

    $status = 'Active'; 
    $hashed_password = password_hash($password, PASSWORD_DEFAULT);

    
    
    
    
    $conn->begin_transaction();
    try {
        
        
        $sql_person = "INSERT INTO person (name, email, id, phoneNum, password, status) VALUES (?, ?, ?, ?, ?, ?)";
        $stmt_person = $conn->prepare($sql_person);
        
        if ($stmt_person === false) {
             throw new Exception("Gagal menyediakan pernyataan: " . $conn->error);
        }
        
        
        $stmt_person->bind_param("ssssss", $username, $email, $id, $phoneNumber, $hashed_password, $status);
        
        if (!$stmt_person->execute()) {
             
             if ($conn->errno == 1062) {
                 throw new Exception("Ralat: E-mel atau Nombor Pengenalan (ID) telah wujud.");
             }
             throw new Exception("Ralat semasa mencipta akaun: " . $stmt_person->error);
        }
        
        $new_person_id = $conn->insert_id;
        $stmt_person->close();
        
        
        
$roles_to_insert = [$role_id_main]; // Role utama (Admin atau Tech)
$role_message = $role;

// SENTIASA tambah role User
if (isset($roles_map['User'])) {
    $roles_to_insert[] = $roles_map['User'];
}

// Jika Admin, tambah role Technician juga
if (strtolower($role) === 'admin' && isset($roles_map['Technician'])) {
    $roles_to_insert[] = $roles_map['Technician'];
    $role_message = "Admin (Full Access)";
}


        $sql_role = "INSERT INTO person_roles (person_id, role_id) VALUES (?, ?)";
        $stmt_role_insert = $conn->prepare($sql_role);

        foreach ($roles_to_insert as $current_role_id) {
            $stmt_role_insert->bind_param("ii", $new_person_id, $current_role_id);
            $stmt_role_insert->execute();
        }
        
        $stmt_role_insert->close();
        
        $conn->commit();
        
        
        
        
        
        
        $log_message = "Admin ID: {$admin_id_session} ({$admin_name_session}) telah menambah akaun baru (ID Person: {$new_person_id}, Role: {$role_message}).";
        
        
        
        $_SESSION['success_message'] = "{$role_message} account created successfully!";

    } catch (Exception $e) {
        $conn->rollback();
        $_SESSION['error_message'] = $e->getMessage();
    }

    $conn->close();

} else {
    
    $_SESSION['error_message'] = "Kaedah permintaan tidak sah.";
}


header("Location: manage_accounts.php");
exit();
?>