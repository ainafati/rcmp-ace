<?php
// Set content type to JSON for API response
header('Content-Type: application/json');

// --- 1. Database Configuration (ADJUST THESE VALUES) ---
$host = 'localhost';
$db   = 'inventory'; // Your database name from the schema image
$user = 'root';     // Your database username
$pass = ''; // <--- CHANGE ME
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
     $pdo = new PDO($dsn, $user, $pass, $options);
} catch (\PDOException $e) {
     http_response_code(500);
     echo json_encode(['success' => false, 'message' => 'Database connection failed.']);
     exit;
}

// --- 2. Input Validation ---
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['email'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid request method or missing email.']);
    exit;
}

$email = trim($_POST['email']);
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid email format.']);
    exit;
}

// --- 3. Check if User Exists ---
$stmt = $pdo->prepare('SELECT user_id, name FROM inventory_user WHERE email = ?');
$stmt->execute([$email]);
$user = $stmt->fetch();

if (!$user) {
    // Return success to prevent email enumeration, but log the failure
    error_log("Attempted OTP request for non-existent email: " . $email);
    echo json_encode(['success' => true, 'message' => 'If this email address is in our system, an OTP has been sent.']);
    exit;
}

// --- 4. Generate OTP and Expiry Time ---
$otp = rand(100000, 999999); // Generate a 6-digit number
$expiry_time = time() + (5 * 60); // 5 minutes from now (Unix timestamp)

// --- 5. Save OTP to Database (inventory_user table) ---
try {
    $stmt = $pdo->prepare(
        'UPDATE inventory_user SET otp_code_int = ?, otp_expiry_bignrt = ? WHERE email = ?'
    );
    // Use the exact column names from your schema
    $stmt->execute([$otp, $expiry_time, $email]);

} catch (\PDOException $e) {
    http_response_code(500);
    error_log("Database update failed: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'A server error occurred while processing your request.']);
    exit;
}

$mail_success = false;
$subject = "Password Reset OTP Code";
$body = "Hi " . htmlspecialchars($user['name']) . ",\n\nYour One-Time Password (OTP) is: " . $otp . "\n\nThis code expires in 5 minutes.";

// --- START: Email Placeholder ---
// In a real application, you would use a secure library here.
$mail_function_used = mail($email, $subject, $body, "From: no-reply@yourdomain.com");

if ($mail_function_used) {
    $mail_success = true;
} else {
    // Log the error but still tell the user it was successful, as the DB update succeeded.
    error_log("Failed to send OTP email to: " . $email);
}
// --- END: Email Placeholder ---


// --- 7. Final Response ---
if ($mail_success) {
    echo json_encode(['success' => true, 'message' => 'An OTP has been sent to your email. It is valid for 5 minutes.']);
} else {
     // If email fails, the DB has the OTP, so tell the user to check their spam/try again.
     echo json_encode(['success' => true, 'message' => 'An OTP has been generated, but we had trouble sending the email. Please check your spam folder.']);
}

?>
