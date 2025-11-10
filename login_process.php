<?php

session_start();
include 'config.php';




if (file_exists('logger.php')) {
    include 'logger.php';
} else {
    
    if (!function_exists('log_activity')) {
        function log_activity($conn, $user_type, $user_id, $action, $details) {
            error_log("Logger function not found. Log attempt: $details");
        }
    }
}

function handle_failed_login($conn, $email, $role, $error_message, $user_id = null, $action = 'LOGIN_FAIL') {
    
    $log_details = "Failed login attempt for email '{$email}' as '{$role}'. Reason: {$error_message}";
    log_activity($conn, $role, $user_id, $action, $log_details);

    
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


$role = isset($_POST['role']) ? $_POST['role'] : '';
$email = isset($_POST['email']) ? trim($_POST['email']) : '';
$password = isset($_POST['password']) ? $_POST['password'] : '';

if (empty($role) || empty($email) || empty($password)) {
    handle_failed_login($conn, $email, $role, "Please fill in all fields.", null, 'LOGIN_FAIL_EMPTY');
}



$allowed_domains = ['@unikl.edu.my', '@t.unikl.edu.my', '@gmail.com'];
$is_valid_domain = false;
$lower_email = strtolower($email); 


foreach ($allowed_domains as $domain) {
    
    if (substr($lower_email, -strlen($domain)) === $domain) {
        $is_valid_domain = true;
        break;
    }
}

if (!$is_valid_domain) {
    
    handle_failed_login($conn, $email, $role, "Invalid email domain. Please use a valid UniKL email.", null, 'LOGIN_FAIL_INVALID_DOMAIN');
}


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
        handle_failed_login($conn, $email, $role, "Invalid role.", null, 'LOGIN_FAIL_ROLE');
}


$sql = "SELECT $id_column, $name_column, $password_column FROM $table_name WHERE email = ? LIMIT 1";

$stmt = $conn->prepare($sql);

if ($stmt === false) {
    
    error_log("SQL Prepare Error: " ." {$conn->error}");
    handle_failed_login($conn, $email, $role, "Server error. Please contact the admin.", null, 'SERVER_ERROR');
}

$stmt->bind_param("s", $email);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 1) {
    
    $row = $result->fetch_assoc();
    
    $user_id = $row[$id_column];
    $user_name = $row[$name_column];
    $hashed_password = $row[$password_column];

    
    if (password_verify($password, $hashed_password)) {
        
        
        
        
        
        
        session_regenerate_id(true); 
        $_SESSION[$session_key] = $user_id;
        $_SESSION['name'] = $user_name;
        $_SESSION['role'] = $role;

        
        $action_code = strtoupper($role) . '_LOGIN_SUCCESS'; 
        $log_details = "User '{$user_name}' (ID: {$user_id}) successfully logged in as '{$role}'.";
        log_activity($conn, $role, $user_id, $action_code, $log_details);
        
        
        $stmt->close();
        $conn->close();
        header("Location: $dashboard_redirect");
        exit();

    } else {
        
        $action_code = strtoupper($role) . '_LOGIN_FAIL_PASSWORD';
        $stmt->close();
        handle_failed_login($conn, $email, $role, "Incorrect email or password.", $user_id, $action_code);
    }

} else {
    
    $action_code = strtoupper($role) . '_LOGIN_FAIL_EMAIL';
    $stmt->close();
    handle_failed_login($conn, $email, $role, "Incorrect email or password.", null, $action_code);
}


$conn->close();
?>
