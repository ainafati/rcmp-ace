<?php
session_start();

// 1. PATH CONFIGURATION
include '../config.php';
include '../technician/config_email.php'; 
require '../technician/send_email.php';

header('Content-Type: application/json');

// --- 2. FUNGSI LOGGER (DIUBAH MENGIKUT STRUKTUR DB KAU) ---
function log_activity($conn, $user_type, $person_id, $action, $details) {
    // Dapatkan IP Address user
    $ip_address = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    
    // Ikut susunan column DB kau: user_type, person_id, action, details, ip_address
    $stmt = $conn->prepare("INSERT INTO activity_logs (user_type, person_id, action, details, ip_address) VALUES (?, ?, ?, ?, ?)");
    
    // Bind: s = string, i = integer
    $stmt->bind_param("sisss", $user_type, $person_id, $action, $details, $ip_address);
    $stmt->execute();
    $stmt->close();
}

function send_error($message) {
    echo json_encode(['status' => 'error', 'message' => $message]);
    exit();
}

if (!$conn) {
    send_error('Database connection failed.');
}

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

// 3. CHECK SESSION
if (!isset($_SESSION['person_id'])) { 
    send_error('Sesi tamat. Sila log masuk semula.'); 
}
$user_id = (int)$_SESSION['person_id'];

// 4. READ JSON INPUT
$json_input = file_get_contents('php://input');
$submission_data = json_decode($json_input, true);

if (json_last_error() !== JSON_ERROR_NONE) {
    send_error('Data tempahan tidak sah (JSON Error).');
}

$items_to_reserve = $submission_data['items'] ?? [];
$priority = (int)($submission_data['program_type'] ?? 3);
$request_reason = trim($submission_data['reason'] ?? '');
$reserve_date_context = $submission_data['reserve_date'] ?? null;
$return_date_context = $submission_data['return_date'] ?? null;

if (empty($items_to_reserve)) { 
    send_error('The order item list is empty. Please add items.'); 
}

// 5. DATABASE TRANSACTION
$conn->begin_transaction();
$reserve_id = 0; 

try {
    // --- INSERT KE reservations ---
    $stmt_res = $conn->prepare("INSERT INTO reservations (person_id, reserve_date, return_date, reason, created_at, priority) VALUES (?, ?, ?, ?, NOW(), ?)");
    $stmt_res->bind_param("isssi", $user_id, $reserve_date_context, $return_date_context, $request_reason, $priority);
    $stmt_res->execute();
    $reserve_id = $conn->insert_id;
    $stmt_res->close();

    // --- INSERT KE reservation_items ---
    foreach ($items_to_reserve as $item_data) {
        $item_id = (int)($item_data['item_id'] ?? 0); 
        $quantity = (int)($item_data['quantity'] ?? 0);
        
        $stmt_item = $conn->prepare("INSERT INTO reservation_items (reserve_id, item_id, quantity, status) VALUES (?, ?, ?, 'Pending')");
        $stmt_item->bind_param("iii", $reserve_id, $item_id, $quantity);
        $stmt_item->execute();
        $stmt_item->close();
    }

    $conn->commit();

    // --- 6. LOGGING (MENGGUNAKAN NAMA USER) ---
    $stmt_user = $conn->prepare("SELECT name FROM person WHERE person_id = ?");
    $stmt_user->bind_param("i", $user_id);
    $stmt_user->execute();
    $user_data = $stmt_user->get_result()->fetch_assoc();
    $user_name = $user_data['name'] ?? 'Unknown User';
    $stmt_user->close();

    $item_count = count($items_to_reserve);
    $log_details = "User $user_name submitted reservation #$reserve_id containing $item_count items.";
    
    // Panggil fungsi logger dengan user_type 'user' sesuai Enum DB kau
    log_activity($conn, 'user', $user_id, "Submit Reservation", $log_details);

    // --- 7. NOTIFICATIONS & EMAIL (SEPERTI ASAL) ---
    $tech_role_id = 2; 
    $notification_message = "New Reservation (#{$reserve_id}) from {$user_name} requires approval.";
    
    $sql_get_techs = "SELECT person_id FROM person_roles WHERE role_id = ?";
    $stmt_techs = $conn->prepare($sql_get_techs);
    $stmt_techs->bind_param("i", $tech_role_id);
    $stmt_techs->execute();
    $tech_ids = $stmt_techs->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt_techs->close();

    $sql_insert_notif = "INSERT INTO notifications (person_id, recipient_role_id, message, is_read, type, related_id) VALUES (?, ?, ?, 0, 'New_Reservation', ?)";
    $stmt_notif = $conn->prepare($sql_insert_notif);
    foreach ($tech_ids as $tech) {
        $stmt_notif->bind_param("iisi", $tech['person_id'], $tech_role_id, $notification_message, $reserve_id);
        $stmt_notif->execute();
    }
    $stmt_notif->close();

$email_sent = false;
if (defined('TECHNICIAN_GROUP_EMAIL')) {
    // Tukar bahagian hujung sekali kepada BASE_URL sahaja atau ke index.php
    $email_sent = sendNewReservationNotification(
        TECHNICIAN_GROUP_EMAIL, 
        $reserve_id, 
        $user_name, 
        "$item_count items", 
        $reserve_date_context, 
		BASE_URL . "login.php"
    );
}    
    $email_msg = $email_sent ? ' Email notification sent.' : ' Email failed.';
    echo json_encode(['status' => 'success', 'message' => 'Booking successfully sent! ID: #' . $reserve_id . $email_msg]);

} catch (Exception $e) {
    $conn->rollback();
    send_error("Submission failed: " . $e->getMessage()); 
}

$conn->close();