<?php
// File: login_process.php
session_start();
include 'config.php';

// Sertakan fail logger. Anda mungkin perlu betulkan path
// Jika folder 'includes' anda di dalam 'admin', ini mungkin salah
// Saya andaikan 'includes' ada di root: /includes/logger.php
if (file_exists('logger.php')) {
    include 'logger.php';
} else {
    // Fallback jika path berbeza. Jika logger.php tidak wujud, cipta fungsi dummy
    if (!function_exists('log_activity')) {
        function log_activity($conn, $user_type, $user_id, $action, $details) {
            error_log("Logger function not found. Log attempt: $details");
        }
    }
}

function handle_failed_login($conn, $email, $role, $error_message, $user_id = null, $action = 'LOGIN_FAIL') {
    
    // Rekod percubaan gagal ke dalam log
    $log_details = "Percubaan log masuk gagal untuk emel '{$email}' sebagai '{$role}'. Sebab: {$error_message}";
    log_activity($conn, $role, $user_id, $action, $log_details);

    // Tetapkan session untuk mesej ralat dan pra-isi borang
    $_SESSION['error'] = $error_message;
    $_SESSION['login_attempt_email'] = $email;
    $_SESSION['login_attempt_role'] = $role;
    
    header("Location: login.php");
    exit();
}

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Location: login.php");
    exit();
}

// 2. Dapatkan data borang (bersih dan sahkan)
$role = isset($_POST['role']) ? $_POST['role'] : '';
$email = isset($_POST['email']) ? trim($_POST['email']) : '';
$password = isset($_POST['password']) ? $_POST['password'] : '';

if (empty($role) || empty($email) || empty($password)) {
    handle_failed_login($conn, $email, $role, "Sila isi semua medan.", null, 'LOGIN_FAIL_EMPTY');
}

// 🚦 2A. VALIDASI DOMAIN EMEL TAMBAHAN 🚦
// Domain yang dibenarkan: UniKL staff/pelajar biasa dan UniKL Techno (jika ini maksud t.unikl.edu.my)
$allowed_domains = ['@unikl.edu.my', '@t.unikl.edu.my', '@gmail.com'];
$is_valid_domain = false;
$lower_email = strtolower($email); // Tukar emel kepada huruf kecil untuk semakan

// Semak setiap domain yang dibenarkan
foreach ($allowed_domains as $domain) {
    // KOD DIBETULKAN: Menggunakan substr() untuk keserasian PHP yang lebih lama
    if (substr($lower_email, -strlen($domain)) === $domain) {
        $is_valid_domain = true;
        break;
    }
}

if (!$is_valid_domain) {
    // Jika emel tidak mempunyai domain yang dibenarkan, anggap ia gagal
    handle_failed_login($conn, $email, $role, "Domain emel tidak sah. Sila gunakan emel UniKL rasmi.", null, 'LOGIN_FAIL_INVALID_DOMAIN');
}

// 3. Tentukan tetapan berdasarkan 'role'
$table_name = '';
$id_column = '';
$name_column = 'name'; 
$password_column = 'password'; 
$dashboard_redirect = '';
$session_key = '';

switch ($role) {
    case 'admin':
        $table_name = 'admin';
        $id_column = 'admin_id';
        $session_key = 'admin_id';
        $dashboard_redirect = 'admin/manageItem_admin.php';
        break;
    case 'tech':
        $table_name = 'technician';
        $id_column = 'tech_id';
        $session_key = 'tech_id';
        $dashboard_redirect = 'technician/dashboard_tech.php';
        break;
    case 'user':
        $table_name = 'user';
        $id_column = 'user_id';
        $session_key = 'user_id';
        $dashboard_redirect = 'user/dashboard_user.php';
        break;
    default:
        handle_failed_login($conn, $email, $role, "Peranan (role) tidak sah.", null, 'LOGIN_FAIL_ROLE');
}

// 4. Query database dengan Prepared Statements (Selamat dari SQL Injection)
$sql = "SELECT $id_column, $name_column, $password_column FROM $table_name WHERE email = ? LIMIT 1";

$stmt = $conn->prepare($sql);

if ($stmt === false) {
    // Ralat pada SQL (cth: nama jadual/kolum salah)
    error_log("SQL Prepare Error: " ." {$conn->error}");
    handle_failed_login($conn, $email, $role, "Ralat pada pelayan. Sila hubungi admin.", null, 'SERVER_ERROR');
}

$stmt->bind_param("s", $email);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 1) {
    // Emel Ditemui, semak kata laluan
    $row = $result->fetch_assoc();
    
    $user_id = $row[$id_column];
    $user_name = $row[$name_column];
    $hashed_password = $row[$password_column];

    // 5. Sahkan kata laluan
    if (password_verify($password, $hashed_password)) {
        
        // ----------
        // BERJAYA LOG MASUK!
        // ----------
        
        // 6. Tetapkan Sesi
        session_regenerate_id(true); // Keselamatan: Elak session fixation
        $_SESSION[$session_key] = $user_id;
        $_SESSION['name'] = $user_name;
        $_SESSION['role'] = $role;

        // 7. REKOD LOG KEJAYAAN
        $action_code = strtoupper($role) . '_LOGIN_SUCCESS'; 
        $log_details = "Pengguna '{$user_name}' (ID: {$user_id}) berjaya log masuk sebagai '{$role}'.";
        log_activity($conn, $role, $user_id, $action_code, $log_details);
        
        // 8. Tutup sambungan dan redirect
        $stmt->close();
        $conn->close();
        header("Location: $dashboard_redirect");
        exit();

    } else {
        // Kata laluan salah
        $action_code = strtoupper($role) . '_LOGIN_FAIL_PASSWORD';
        $stmt->close();
        handle_failed_login($conn, $email, $role, "Emel atau kata laluan tidak sah.", $user_id, $action_code);
    }

} else {
    // Emel tidak ditemui
    $action_code = strtoupper($role) . '_LOGIN_FAIL_EMAIL';
    $stmt->close();
    handle_failed_login($conn, $email, $role, "Emel atau kata laluan tidak sah.", null, $action_code);
}

// Tutup sambungan jika masih terbuka
$conn->close();
?>