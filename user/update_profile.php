<?php
session_start();
include '../config.php';

if (!$conn) {
    $_SESSION['error'] = "Database connection error.";
    header("Location: profile.php");
    exit();
}


if (!isset($_SESSION['person_id'])) {
    header("Location: login.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: profile.php");
    exit();
}


$user_id = (int) $_SESSION['person_id'];


// Hanya mengambil email, phoneNum, dan data password. Nama diabaikan.
$email = trim($_POST['email']);
$phoneNum = trim($_POST['phoneNum']);
$new_password = $_POST['new_password'];
$confirm_password = $_POST['confirm_password'];


// Semakan kosong: hanya email dan phoneNum diperlukan
if (empty($email) || empty($phoneNum)) {
    $_SESSION['error'] = "Email and phone number cannot be empty.";
    header("Location: profile.php");
    exit();
}


// Semakan format email
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $_SESSION['error'] = "Invalid email format.";
    header("Location: profile.php");
    exit();
}


// Semakan format phoneNum
if (!preg_match('/^[0-9\-\+\s\(\)]+$/', $phoneNum)) {
    $_SESSION['error'] = "Invalid phone number format. Only numbers and basic symbols (+-()) are allowed.";
    header("Location: profile.php");
    exit();
}


// Semak jika email sudah digunakan oleh pengguna lain
$stmt_check = $conn->prepare("SELECT person_id FROM person WHERE email = ? AND person_id != ?");
$stmt_check->bind_param("si", $email, $user_id);
$stmt_check->execute();
if ($stmt_check->get_result()->num_rows > 0) {
    $_SESSION['error'] = "This email is already in use by another account.";
    $stmt_check->close();
    header("Location: profile.php");
    exit();
}
$stmt_check->close();


$stmt = null;
if (!empty($new_password)) {

    // Semak padanan kata laluan
    if ($new_password !== $confirm_password) {
        $_SESSION['error'] = "New passwords do not match.";
        header("Location: profile.php");
        exit();
    }
    
    // Semakan kekuatan kata laluan
    $uppercase = preg_match('@[A-Z]@', $new_password);
    $lowercase = preg_match('@[a-z]@', $new_password);
    $number    = preg_match('@[0-9]@', $new_password);
    $specialChars = preg_match('@[\W_]@', $new_password);

    if (!$uppercase || !$lowercase || !$number || !$specialChars || strlen($new_password) < 8) {
        $_SESSION['error'] = 'New password does not meet the requirements. Please ensure it has 8+ characters, uppercase, lowercase, number, and a special character.';
        header("Location: profile.php");
        exit();
    }
    
    $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
    
    // KEMASKINI dengan password dan password_updated_at (menggunakan NOW())
    $sql = "UPDATE person SET email = ?, phoneNum = ?, password = ?, password_updated_at = NOW() WHERE person_id = ?";
    $stmt = $conn->prepare($sql);
    
    $stmt->bind_param("sssi", $email, $phoneNum, $hashed_password, $user_id);
    

} else {
    
    // KEMASKINI tanpa password dan password_updated_at (Hanya email dan phoneNum)
    $sql = "UPDATE person SET email = ?, phoneNum = ? WHERE person_id = ?";
    $stmt = $conn->prepare($sql);
    
    $stmt->bind_param("ssi", $email, $phoneNum, $user_id);
}


if ($stmt === false) {
    $_SESSION['error'] = "Internal error: Failed to prepare database statement.";
} elseif ($stmt->execute()) {
    $_SESSION['message'] = "Your profile has been updated successfully!";
} else {
    
    $_SESSION['error'] = "Failed to update profile. Please try again. Error: " . $stmt->error;
}

if ($stmt) {
    $stmt->close();
}
$conn->close();

header("Location: profile.php");
exit();
?>