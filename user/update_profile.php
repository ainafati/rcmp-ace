<?php
session_start();
include 'config.php'; // Pastikan fail konfigurasi pangkalan data anda disertakan

// Pastikan pengguna sudah log masuk
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Pastikan borang dihantar melalui POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: profile.php");
    exit();
}

$user_id = (int) $_SESSION['user_id'];

// Ambil data dari borang
$name = trim($_POST['name']); // Dapatkan nama
$email = trim($_POST['email']);
$phoneNum = trim($_POST['phoneNum']);
$new_password = $_POST['new_password']; // Jangan trim password
$confirm_password = $_POST['confirm_password'];

// Pengesahan asas
if (empty($name) || empty($email) || empty($phoneNum)) {
    $_SESSION['error'] = "Name, email, and phone number cannot be empty.";
    header("Location: profile.php");
    exit();
}

// Semak jika e-mel baharu sudah digunakan oleh pengguna lain
$stmt_check = $conn->prepare("SELECT user_id FROM user WHERE email = ? AND user_id != ?");
$stmt_check->bind_param("si", $email, $user_id);
$stmt_check->execute();
if ($stmt_check->get_result()->num_rows > 0) {
    $_SESSION['error'] = "This email is already in use by another account.";
    $stmt_check->close();
    header("Location: profile.php");
    exit();
}
$stmt_check->close();

// --- Logik untuk kemas kini kata laluan (jika diisi) ---
if (!empty($new_password)) {
    // 1. Semak kata laluan baru vs pengesahan
    if ($new_password !== $confirm_password) {
        $_SESSION['error'] = "New passwords do not match.";
        header("Location: profile.php");
        exit();
    }
    
    // 2. Pengesahan Kekuatan Kata Laluan menggunakan regex (Sama seperti yang anda berikan)
    $uppercase = preg_match('@[A-Z]@', $new_password);
    $lowercase = preg_match('@[a-z]@', $new_password);
    $number    = preg_match('@[0-9]@', $new_password);
    // @[\W_]@ mencari sebarang aksara bukan perkataan atau garis bawah (_)
    $specialChars = preg_match('@[\W_]@', $new_password); 

    if (!$uppercase || !$lowercase || !$number || !$specialChars || strlen($new_password) < 8) {
        // Mesej Ralat Lebih Terperinci
        $_SESSION['error'] = 'New password does not meet the requirements. Please ensure it has <strong>8+ characters</strong>, <strong>uppercase</strong>, <strong>lowercase</strong>, <strong>number</strong>, and a <strong>special character</strong>.';
        header("Location: profile.php");
        exit();
    }
    
    // KOD JIKA PASSWORD DIISI DAN LULUS PENGESAHAN
    $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
    
    // Kueri: Kemas kini Nama, E-mel, Nombor Telefon, DAN Kata Laluan
    $sql = "UPDATE user SET name = ?, email = ?, phoneNum = ?, password = ? WHERE user_id = ?";
    $stmt = $conn->prepare($sql);
    // Jenis 'ssssi' -> string(name), string(email), string(phoneNum), string(password), integer(user_id)
    $stmt->bind_param("ssssi", $name, $email, $phoneNum, $hashed_password, $user_id);

} else {
    // KOD JIKA PASSWORD KOSONG (Hanya kemas kini maklumat lain)
    
    // Kueri: Kemas kini Nama, E-mel, dan Nombor Telefon SAHAJA
    $sql = "UPDATE user SET name = ?, email = ?, phoneNum = ? WHERE user_id = ?";
    $stmt = $conn->prepare($sql);
    // Jenis 'sssi' -> string(name), string(email), string(phoneNum), integer(user_id)
    $stmt->bind_param("sssi", $name, $email, $phoneNum, $user_id);
}

// Laksanakan query
if ($stmt->execute()) {
    $_SESSION['message'] = "Your profile has been updated successfully!";
} else {
    // Menambah ralat pangkalan data untuk penyahpepijatan
    $_SESSION['error'] = "Failed to update profile. Please try again. Error: " . $stmt->error;
}

$stmt->close();
$conn->close();

header("Location: profile.php");
exit();
?>