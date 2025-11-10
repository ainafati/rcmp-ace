<?php
// File: login_process.php
session_start();
include 'config.php';

// Include logger file. You may need to fix the path
// If 'includes' folder is inside 'admin', this may be wrong
// Assuming 'includes' is at the root: /includes/logger.php
if (file_exists('logger.php')) {
    include 'logger.php';
} else {
    // Fallback if the path is different. If logger.php does not exist, create a dummy function
    if (!function_exists('log_activity')) {
        function log_activity($conn, $user_type, $user_id, $action, $details) {
            error_log("Logger function not found. Log attempt: $details");
        }
    }
}

function handle_failed_login($conn, $email, $role, $error_message, $user_id = null, $action = 'LOGIN_FAIL') {
    // Record the failed login attempt in the log
    $log_details = "Failed login attempt for email '{$email}' as '{$role}'. Reason: {$error_message}";
    log_activity($conn, $role, $user_id, $action, $log_details);

    // Set session for error message and pre-fill form
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

// 2. Get form data (sanitize and validate)
$role = isset($_POST['role']) ? $_POST['role'] : '';
$email = isset($_POST['email']) ? trim($_POST['email']) : '';
$password = isset($_POST['password']) ? $_POST['password'] : '';

if (empty($role) || empty($email) || empty($password)) {
    handle_failed_login($conn, $email, $role, "Please fill in all fields.", null, 'LOGIN_FAIL_EMPTY');
}

// 🚦 2A. ADDITIONAL EMAIL DOMAIN VALIDATION 🚦
// Allowed domains: UniKL staff/student emails and UniKL Techno (if this is meant by t.unikl.edu.my)
$allowed_domains = ['@unikl.edu.my', '@t.unikl.edu.my', '@gmail.com'];
$is_valid_domain = false;
$lower_email = strtolower($email); // Convert email to lowercase for comparison

// Check each allowed domain
foreach ($allowed_domains as $domain) {
    // FIXED CODE: Using substr() for compatibility with older PHP versions
    if (substr($lower_email, -strlen($domain)) === $domain) {
        $is_valid_domain = true;
        break;
    }
}

if (!$is_valid_domain) {
    // If email does not have a valid domain, treat it as failed
    handle_failed_login($conn, $email, $role, "Invalid email domain. Please use a valid UniKL email.", null, 'LOGIN_FAIL_INVALID_DOMAIN');
}

// 3. Determine table settings based on 'role'
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

// 4. Query database with Prepared Statements (Safe from SQL Injection)
$sql = "SELECT $id_column, $name_column, $password_column FROM $table_name WHERE email = ? LIMIT 1";

$stmt = $conn->prepare($sql);

if ($stmt === false) {
    // SQL error (e.g. wrong table/column name)
    error_log("SQL Prepare Error: " ." {$conn->error}");
    handle_failed_login($conn, $email, $role, "Server error. Please contact the admin.", null, 'SERVER_ERROR');
}

$stmt->bind_param("s", $email);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 1) {
    // Email found, check the password
    $row = $result->fetch_assoc();
    
    $user_id = $row[$id_column];
    $user_name = $row[$name_column];
    $hashed_password = $row[$password_column];

    // 5. Verify the password
    if (password_verify($password, $hashed_password)) {
        
        // ----------
        // SUCCESSFUL LOGIN!
        // ----------
        
        // 6. Set Session
        session_regenerate_id(true); // Security: Prevent session fixation
        $_SESSION[$session_key] = $user_id;
        $_SESSION['name'] = $user_name;
        $_SESSION['role'] = $role;

        // 7. LOG SUCCESSFUL LOGIN
        $action_code = strtoupper($role) . '_LOGIN_SUCCESS'; 
        $log_details = "User '{$user_name}' (ID: {$user_id}) successfully logged in as '{$role}'.";
        log_activity($conn, $role, $user_id, $action_code, $log_details);
        
        // 8. Close connection and redirect
        $stmt->close();
        $conn->close();
        header("Location: $dashboard_redirect");
        exit();

    } else {
        // Incorrect password
        $action_code = strtoupper($role) . '_LOGIN_FAIL_PASSWORD';
        $stmt->close();
        handle_failed_login($conn, $email, $role, "Incorrect email or password.", $user_id, $action_code);
    }

} else {
    // Email not found
    $action_code = strtoupper($role) . '_LOGIN_FAIL_EMAIL';
    $stmt->close();
    handle_failed_login($conn, $email, $role, "Incorrect email or password.", null, $action_code);
}

// Close connection if still open
$conn->close();
?>
