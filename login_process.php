<?php

session_start();
include 'config.php'; // Sambungan pangkalan data

/**
 * Masukkan logger khas.
 */
if (file_exists('logger.php')) {
    include 'logger.php';
} else {
    // Fungsi logger sandaran
    if (!function_exists('log_activity')) {
        function log_activity($conn, $user_type, $user_id, $action, $details) {
            error_log("Logger function not found. Log attempt: $details");
        }
    }
}

/**
 * Mengendalikan semua percubaan log masuk yang gagal.
 */
function handle_failed_login($conn, $email, $role, $error_message, $user_id = null, $action = 'LOGIN_FAIL') {
    
    $log_details = "Login attempt failed for email '{$email}' as '{$role}'. Reason: {$error_message}";
    log_activity($conn, $role, $user_id, $action, $log_details);

    $_SESSION['error'] = $error_message;
    $_SESSION['login_attempt_email'] = $email;
    $_SESSION['login_attempt_role'] = $role;
    
    header("Location: login.php");
    exit();
}

/**
 * FUNGSI BANTUAN (1): Menyemak domain emel.
 */
function is_valid_domain($email, $allowed_domains) {
    $lower_email = strtolower($email);
    foreach ($allowed_domains as $domain) {
        if (substr($lower_email, -strlen($domain)) === $domain) {
            return true;
        }
    }
    return false;
}

/**
 * FUNGSI BANTUAN (2): Menyemak emel di jadual role yang lain.
 */
function check_email_in_other_roles($conn, $email, $current_role) {
    $roles_to_check = [
        'admin' => 'admin',
        'tech'  => 'technician',
        'user'  => 'user'
    ];
    unset($roles_to_check[$current_role]);

    foreach ($roles_to_check as $role_name => $table_name) {
        try {
            $sql = "SELECT email FROM $table_name WHERE email = ? LIMIT 1";
            $stmt = $conn->prepare($sql);
            if ($stmt) {
                $stmt->bind_param("s", $email);
                $stmt->execute();
                $result = $stmt->get_result();
                if ($result->num_rows === 1) {
                    $stmt->close();
                    return $role_name; // Dijumpai!
                }
                $stmt->close();
            }
        } catch (Exception $e) {
            error_log("Cross-check error for $table_name: " . $e->getMessage());
        }
    }
    return null; // Tidak dijumpai
}


// 1. Semak jika kaedah adalah POST
if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Location: login.php");
    exit();
}

// 2. Dapatkan data POST
$role = isset($_POST['role']) ? $_POST['role'] : '';
$email = isset($_POST['email']) ? trim($_POST['email']) : '';
$password = isset($_POST['password']) ? $_POST['password'] : '';

// 3. Semak jika ada medan kosong
if (empty($role) || empty($email) || empty($password)) {
    handle_failed_login($conn, $email, $role, "Please fill in all fields.", null, 'LOGIN_FAIL_EMPTY');
}

// 4. Tentukan jadual pengguna
$table_name = '';
$id_column = '';
$name_column = 'name';
$password_column = 'password';
$status_column = 'status'; // Guna nama kolum anda
$dashboard_redirect = '';
$session_key = '';
$allowed_domains = []; 

switch ($role) {
    case 'admin':
        $table_name = 'admin';
        $id_column = 'admin_id';
        $session_key = 'admin_id';
        $dashboard_redirect = 'admin/manageItem_admin.php';
        $allowed_domains = ['@unikl.edu.my','@t.unikl.edu.my','@gmail.com'];
        break;
    case 'tech':
        $table_name = 'technician';
        $id_column = 'tech_id';
        $session_key = 'tech_id';
        $dashboard_redirect = 'technician/dashboard_tech.php';
        $allowed_domains = ['@unikl.edu.my', '@t.unikl.edu.my','@gmail.com'];
        break;
    case 'user':
        $table_name = 'user';
        $id_column = 'user_id';
        $session_key = 'user_id';
        $dashboard_redirect = 'user/dashboard_user.php';
        $allowed_domains = ['@unikl.edu.my', '@t.unikl.edu.my','@gmail.com'];
        break;
    default:
        handle_failed_login($conn, $email, $role, "Invalid role selected.", null, 'LOGIN_FAIL_ROLE');
}

// 5. Lakukan pengesahan domain
if (!is_valid_domain($email, $allowed_domains)) {
    $error_message = "Invalid email domain for '{$role}' role. Please use an authorized email.";
    handle_failed_login($conn, $email, $role, $error_message, null, 'LOGIN_FAIL_INVALID_DOMAIN');
}

// 6. Sediakan query SQL
$sql = "SELECT $id_column, $name_column, $password_column, $status_column FROM $table_name WHERE email = ? LIMIT 1";
$stmt = $conn->prepare($sql);

if ($stmt === false) {
    error_log("SQL Prepare Error: " . $conn->error);
    handle_failed_login($conn, $email, $role, "Server error. Please contact the administrator.", null, 'SERVER_ERROR');
}

$stmt->bind_param("s", $email);
$stmt->execute();
$result = $stmt->get_result();

// 7. Proses hasil query
if ($result->num_rows === 1) {
    $row = $result->fetch_assoc();
    
    $user_id = $row[$id_column];
    $user_name = $row[$name_column];
    $hashed_password = $row[$password_column];
    $status = $row[$status_column]; // Dapatkan status

    // -----------------------------------------------------------------
    // LANGKAH 8: LOGIK STATUS DIKEMAS KINI (LEBIH MUDAH)
    // -----------------------------------------------------------------
    // Semak jika status BUKAN 'Active'
    if (strtolower($status) !== 'active') {
        
        // Cipta mesej ralat (cth: "Your account is currently Suspended...")
        $error_message = "Your account is currently '{$status}'. Please contact the administrator for assistance.";
        $action_code = strtoupper($role) . '_LOGIN_FAIL_STATUS_' . strtoupper($status);
        
        $stmt->close();
        handle_failed_login($conn, $email, $role, $error_message, $user_id, $action_code);
    }
    // -----------------------------------------------------------------
    // AKHIR LOGIK BARU
    // -----------------------------------------------------------------

    // 9. Sahkan kata laluan
    if (password_verify($password, $hashed_password)) {
        // Kata laluan betul
        session_regenerate_id(true); 
        $_SESSION[$session_key] = $user_id;
        $_SESSION['name'] = $user_name;
        $_SESSION['role'] = $role;

        // Log
        $action_code = strtoupper($role) . '_LOGIN_SUCCESS'; 
        $log_details = "User '{$user_name}' (ID: {$user_id}) successfully logged in as '{$role}'.";
        log_activity($conn, $role, $user_id, $action_code, $log_details);
        
        $stmt->close();
        $conn->close();
        header("Location: $dashboard_redirect");
        exit();

    } else {
        // Kata laluan gagal
        $action_code = strtoupper($role) . '_LOGIN_FAIL_PASSWORD';
        $stmt->close();
        handle_failed_login($conn, $email, $role, "Incorrect email or password.", $user_id, $action_code);
    }

} else {
    // Emel tidak dijumpai di jadual ini.
    $stmt->close(); 

    // Semak jika emel ini wujud di role lain.
    $found_in_role = check_email_in_other_roles($conn, $email, $role);

    if ($found_in_role !== null) {
        // Emel wujud, tetapi di role yang salah
        $correct_role_name = ucfirst($found_in_role); 
        $current_role_name = ucfirst($role);
        $error_message = "That email is registered as '{$correct_role_name}', not '{$current_role_name}'. Please select the '{$correct_role_name}' role and try again.";
        $action_code = strtoupper($role) . '_LOGIN_FAIL_WRONG_ROLE';
        handle_failed_login($conn, $email, $role, $error_message, null, $action_code);

    } else {
        // Emel memang tidak wujud
        $action_code = strtoupper($role) . '_LOGIN_FAIL_EMAIL';
        handle_failed_login($conn, $email, $role, "Incorrect email or password.", null, $action_code);
    }
}

$conn->close();
?>