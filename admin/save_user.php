<?php

session_start();
include '../config.php'; // Anggap fail ini terletak di subfolder (admin/)

// ******************************************************
// 1. SEMAKAN SESI ADMIN YANG DIBETULKAN
// ******************************************************
$allowed_role = 'Admin';
if (!isset($_SESSION['person_id']) || $_SESSION['logged_in_role'] !== $allowed_role) {
    header("Location: ../login.php");
    exit();
}

// Logging info (guna untuk log aktiviti jika perlu)
$admin_id_session = (int)$_SESSION['person_id'];
$admin_name_session = htmlspecialchars(isset($_SESSION['name']) ? $_SESSION['name'] : 'Admin');


if ($_SERVER["REQUEST_METHOD"] == "POST") {

// Ambil input POST
// Gunakan isset() operator ternary untuk keserasian PHP lama

$username = trim(isset($_POST['username']) ? $_POST['username'] : '');
$email = trim(isset($_POST['email']) ? $_POST['email'] : '');
$id = trim(isset($_POST['id']) ? $_POST['id'] : '');
$phoneNumber = trim(isset($_POST['phoneNumber']) ? $_POST['phoneNumber'] : '');
$password = isset($_POST['password']) ? $_POST['password'] : '';
$role = isset($_POST['role']) ? $_POST['role'] : '';

    // Cari ID Peranan (Role ID) berdasarkan nama peranan
    $stmt_role = $conn->prepare("SELECT role_id FROM roles WHERE role_name = ?");
    $stmt_role->bind_param("s", $role);
    $stmt_role->execute();
    $result_role = $stmt_role->get_result();
    $role_data = $result_role->fetch_assoc();
    $stmt_role->close();

$role_id = isset($role_data['role_id']) ? $role_data['role_id'] : null;
    
    // ******************************************************
    // 2. PENGESAHAN (VALIDATION)
    // ******************************************************
    $needle = '@unikl.edu.my';
    $email_ends_with_unikl = (substr($email, -strlen($needle)) === $needle);
    
    if (empty($username) || empty($email) || empty($id) || empty($phoneNumber) || empty($password) || $role_id === null) {
        $_SESSION['error_message'] = "Semua medan wajib diisi.";
        header("Location: manage_accounts.php");
        exit();
    }
    
    if (!filter_var($email, FILTER_VALIDATE_EMAIL) || !$email_ends_with_unikl) {
        $_SESSION['error_message'] = "Format e-mel UniKL tidak sah.";
        header("Location: manage_accounts.php");
        exit();
    }
    
    // DITUKAR: Menggunakan $id untuk semakan format 12 digit
    if (!preg_match('/^\d{12}$/', $id)) {
         $_SESSION['error_message'] = "Format Nombor Pengenalan (IC/ID) tidak sah. Mesti 12 digit.";
         header("Location: manage_accounts.php");
         exit();
    }
    
    if ($role !== 'Technician' && $role !== 'User' && $role !== 'Admin') { 
        $_SESSION['error_message'] = "Peranan tidak sah.";
        header("Location: manage_accounts.php");
        exit();
    }
    
    $status = 'Active'; 
    $hashed_password = password_hash($password, PASSWORD_DEFAULT);

    // ******************************************************
    // 3. LOGIK INSERT KE JADUAL 'person' DAN 'person_roles'
    // ******************************************************
    
    $conn->begin_transaction();
    try {
        // 3a. Masukkan data ke jadual 'person'
        // CATATAN: Pastikan lajur dalam jadual DB anda masih dipanggil 'ic_num' jika anda ingin menggunakan $id sebagai nombor IC.
        $sql_person = "INSERT INTO person (name, email, ic_num, phoneNum, password, status) VALUES (?, ?, ?, ?, ?, ?)";
        $stmt_person = $conn->prepare($sql_person);
        
        if ($stmt_person === false) {
             throw new Exception("Gagal menyediakan pernyataan: " . $conn->error);
        }
        
        // DITUKAR: Mengikat $id ke lajur 'ic_num'
        $stmt_person->bind_param("ssssss", $username, $email, $id, $phoneNumber, $hashed_password, $status);
        
        if (!$stmt_person->execute()) {
             // Tangani ralat pendua (Duplicate entry)
             if ($conn->errno == 1062) {
                 throw new Exception("Ralat: E-mel atau Nombor Pengenalan (ID) telah wujud.");
             }
             throw new Exception("Ralat semasa mencipta akaun: " . $stmt_person->error);
        }
        
        $new_person_id = $conn->insert_id;
        $stmt_person->close();
        
        // 3b. Masukkan peranan ke jadual 'person_roles'
        $sql_role = "INSERT INTO person_roles (person_id, role_id) VALUES (?, ?)";
        $stmt_role_insert = $conn->prepare($sql_role);
        
        if ($stmt_role_insert === false) {
             throw new Exception("Gagal menyediakan pernyataan peranan: " . $conn->error);
        }
        
        $stmt_role_insert->bind_param("ii", $new_person_id, $role_id);
        $stmt_role_insert->execute();
        $stmt_role_insert->close();
        
        $conn->commit();
        
        // ******************************************************
        // 4. Logging
        // ******************************************************
        // log_activity($conn, 'admin', $admin_id_session, 'CREATE_ACCOUNT', "Admin {$admin_name_session} mencipta akaun '{$username}' dengan peranan {$role}.");


        $_SESSION['success_message'] = "$role account created successfully!";

    } catch (Exception $e) {
        $conn->rollback();
        $_SESSION['error_message'] = $e->getMessage();
    }

    $conn->close();

} else {
    // Permintaan bukan POST
    $_SESSION['error_message'] = "Kaedah permintaan tidak sah.";
}


header("Location: manage_accounts.php");
exit();
?>