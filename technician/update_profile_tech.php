<?php
session_start();
include '../config.php';

// --- FUNGSI VALIDASI KATA LALUAN KRITIKAL ---
function validatePassword($password) {
    // 1. Mesti sekurang-kurangnya 8 aksara
    if (strlen($password) < 8) {
        return "Password must be at least 8 characters long.";
    }

    // 2. Mesti termasuk nombor (0-9)
    if (!preg_match("/[0-9]/", $password)) {
        return "Password must include at least one number (0-9).";
    }

    // 3. Mesti termasuk huruf besar (A-Z)
    if (!preg_match("/[A-Z]/", $password)) {
        return "Password must include at least one uppercase letter (A-Z).";
    }

    // 4. Mesti termasuk huruf kecil (a-z)
    if (!preg_match("/[a-z]/", $password)) {
        return "Password must include at least one lowercase letter (a-z).";
    }

    // 5. Mesti termasuk aksara khas (!@#$%, dsb.)
    if (!preg_match("/[!@#$%^&*()\-_=+{};:,<.>]/", $password)) {
        return "Password must include at least one special character (!@#\$%..).";
    }

    return true; // Lulus pengesahan
}


// --- 1. Sesi & Sambungan DB ---
if (!isset($_SESSION['person_id'])) {
    $_SESSION['error'] = "Sila log masuk semula.";
    header("Location: ../login.php");
    exit();
}

$tech_id = (int) $_SESSION['person_id'];

if (!isset($conn) || $conn->connect_error) {
    $_SESSION['error'] = "Database Connection Error.";
    header("Location: profile_tech.php");
    exit();
}

// --- 2. Ambil & Bersihkan Input ---
$name = isset($_POST['name']) ? trim($_POST['name']) : '';
$phoneNum = isset($_POST['phoneNum']) ? trim($_POST['phoneNum']) : '';
$new_password = isset($_POST['new_password']) ? trim($_POST['new_password']) : '';
$confirm_password = isset($_POST['confirm_password']) ? trim($_POST['confirm_password']) : '';


// --- 3. Validasi Nama dan Nombor Telefon ---
if (empty($name) || empty($phoneNum)) {
    $_SESSION['error'] = "Full Name and Phone Number cannot be empty.";
    $_SESSION['keep_edit_mode'] = true; // Kekalkan mod edit
    header("Location: profile_tech.php");
    exit();
}


// --- 4. Tentukan SQL dan Parameter Awal (Update Name & Phone) ---
$sql_query_parts = ["name = ?", "phoneNum = ?"];
$types = "ss";
$params = [$name, $phoneNum];


// --- 5. Validasi dan Tambah Password (Jika Disediakan) ---
if (!empty($new_password)) {
    if ($new_password !== $confirm_password) {
        $_SESSION['error'] = "New passwords do not match.";
        $_SESSION['keep_edit_mode'] = true; // Kekalkan mod edit
        header("Location: profile_tech.php");
        exit();
    }
    
    // PENGUATKUASAAN SEMUA 5 KEPERLUAN KATA LALUAN
    $validation_result = validatePassword($new_password);
    
    if ($validation_result !== true) {
        $_SESSION['error'] = $validation_result;
        $_SESSION['keep_edit_mode'] = true; // Kekalkan mod edit
        header("Location: profile_tech.php");
        exit();
    }
    
    // Jika LULUS
    $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
    
    $sql_query_parts[] = "password = ?";
    $types .= "s";
    $params[] = $hashed_password;
}


// --- 6. Prepare dan Bind Parameters ---

// Bina SQL akhir: UPDATE person SET name = ?, phoneNum = ?, [password = ?] WHERE person_id = ?
$sql = "UPDATE person SET " . implode(", ", $sql_query_parts) . " WHERE person_id = ?";
$types .= "i";
$params[] = $tech_id;

$stmt = $conn->prepare($sql);

if ($stmt === false) {
    $_SESSION['error'] = "SQL Prepare Error: " . htmlspecialchars($conn->error);
    $_SESSION['keep_edit_mode'] = true; // Kekalkan mod edit
    $conn->close();
    header("Location: profile_tech.php");
    exit();
}

// Binding parameters
if (count($params) > 0 && !$stmt->bind_param($types, ...$params)) { 
    $_SESSION['error'] = "SQL Bind Param Error: " . htmlspecialchars($stmt->error);
    $_SESSION['keep_edit_mode'] = true; // Kekalkan mod edit
    $stmt->close();
    $conn->close();
    header("Location: profile_tech.php");
    exit();
}


// --- 7. Execute dan Result ---
if ($stmt->execute()) {
    if ($stmt->affected_rows > 0 || count($sql_query_parts) == 0) { 
        $_SESSION['message'] = "Your profile has been updated successfully!";
    } else {
        $_SESSION['message'] = "Profile data is already up to date (or no changes were made).";
    }
} else {
    $_SESSION['error'] = "Failed to update profile. Execution Error: " . htmlspecialchars($stmt->error);
    $_SESSION['keep_edit_mode'] = true; // Kekalkan mod edit
}

$stmt->close();
$conn->close();

header("Location: profile_tech.php");
exit();
?>