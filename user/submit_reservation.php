<?php
session_start();

// PASTIKAN SEMUA PATH ADALAH BETUL
include '../config.php';
include '../technician/config_email.php'; 
require '../technician/send_email.php';

header('Content-Type: application/json');

function send_error($message) {
    // Pastikan ini adalah satu-satunya output selain JSON
    echo json_encode(['status' => 'error', 'message' => $message]);
    exit();
}

if (!$conn) {
    send_error('Database connection failed. Check config.php path and settings.');
}

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

// Pastikan tiada output sebelum header dan session_start()
if (!isset($_SESSION['person_id'])) { 
    send_error('Sesi tamat. Sila log masuk semula.'); 
}
$user_id = (int)$_SESSION['person_id'];

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
    send_error('The order item list is empty. Please add items in Step 2.'); 
}

if (empty($request_reason) || empty($reserve_date_context) || empty($return_date_context)) {
    send_error('Booking context data is incomplete (Borrow/Return Date or Purpose of Loan is required).');
}


$conn->begin_transaction();

$reserve_id = 0; 

try {
    
    // --- 1. KEMAS KINI KRITIKAL: INSERT KE JADUAL reservations ---
    // Tambah reserve_date, return_date, dan reason ke dalam kuerti reservations
    $stmt_res = $conn->prepare("INSERT INTO reservations (person_id, reserve_date, return_date, reason, created_at, priority) VALUES (?, ?, ?, ?, NOW(), ?)");
    if (!$stmt_res) {
        throw new Exception("Error preparing reservation statement: " . $conn->error);
    }
    
    // Pastikan jenis pemboleh ubah sepadan: i, s, s, s, i
    $stmt_res->bind_param("isssi", 
        $user_id, 
        $reserve_date_context, // Tarikh Mula Pinjaman
        $return_date_context,  // Tarikh Pulangan Dijangka
        $request_reason,       // Tujuan Pinjaman (Reason)
        $priority
    );
    $stmt_res->execute();
    $reserve_id = $conn->insert_id;
    $stmt_res->close();

    
    // 2. INSERT KE reservation_items
    foreach ($items_to_reserve as $item_data) {
        
        $item_id = (int)($item_data['item_id'] ?? 0); 
        $item_name = $item_data['item_name'] ?? 'Unknown Item';
        $quantity = (int)($item_data['quantity'] ?? 0);
        
        
        if ($quantity <= 0) { 
            throw new Exception("Quantity for '" . htmlspecialchars($item_name) . "' must be greater than zero."); 
        }

        
        if ($item_id <= 0) {
            throw new Exception("Invalid item ID for the item: " . htmlspecialchars($item_name));
        }
        
        
        $stmt_item = $conn->prepare(
            "INSERT INTO reservation_items (reserve_id, item_id, quantity, status) VALUES (?, ?, ?, 'Pending')"
        );
        if (!$stmt_item) {
            throw new Exception("Error preparing reservation item statement: " . $conn->error);
        }
        
        
        $stmt_item->bind_param("iii", 
            $reserve_id, 
            $item_id, 
            $quantity
        );
        $stmt_item->execute();
        $stmt_item->close();
    }

    // 3. LOGIK NOTIFIKASI
    $conn->commit();
    
    $stmt_user_name = $conn->prepare("SELECT name FROM person WHERE person_id = ?");
    $stmt_user_name->bind_param("i", $user_id);
    $stmt_user_name->execute();
    $user_data = $stmt_user_name->get_result()->fetch_assoc();
    $user_name = $user_data['name'] ?? 'User Unknown';
    $stmt_user_name->close();
    
    
    $tech_role_id = 2; 
    $total_items_count = count($items_to_reserve);
    
    $notification_message = "New Reservation (#{$reserve_id}) from {$user_name} requires approval ({$total_items_count} item).";
    $notification_type = "New_Reservation"; 
    
    try {
        $sql_get_techs = "SELECT person_id FROM person_roles WHERE role_id = ?";
        $stmt_techs = $conn->prepare($sql_get_techs);
        $stmt_techs->bind_param("i", $tech_role_id);
        $stmt_techs->execute();
        $result_techs = $stmt_techs->get_result();
        $tech_ids = $result_techs->fetch_all(MYSQLI_ASSOC);
        $stmt_techs->close();
        
        $sql_insert_notif = "INSERT INTO notifications (person_id, recipient_role_id, message, is_read, type, related_id) VALUES (?, ?, ?, 0, ?, ?)";
        $stmt_insert = $conn->prepare($sql_insert_notif);

        if ($stmt_insert) {
            foreach ($tech_ids as $tech) {
                $target_tech_id = $tech['person_id'];
                
                $stmt_insert->bind_param("iissi", 
                    $target_tech_id,
                    $tech_role_id,
                    $notification_message,
                    $notification_type,
                    $reserve_id 
                );
                $stmt_insert->execute();
            }
            $stmt_insert->close();
        } else {
            error_log("Failed to prepare notification statement: " . $conn->error);
        }
        
    } catch (Exception $e) {
        error_log("Failed to create in-system notifications: " . $e->getMessage());
    }
    
    
    // E-mel Notifikasi ke Technician
    $technician_email = TECHNICIAN_GROUP_EMAIL; 
    $count = count($items_to_reserve);
    $item_summary = '';

    if ($count === 1) {
        $item_summary = $items_to_reserve[0]['item_name'] ?? 'Single Item';
    } elseif ($count >= 2) {
        $first_item = htmlspecialchars($items_to_reserve[0]['item_name']);
        $second_item = $items_to_reserve[1]['item_name'] ?? null;
        
        if ($second_item) {
            $second_item = htmlspecialchars($second_item);
        }
        
        if ($count === 2) {
            $item_summary = "2 Items ($first_item, $second_item)";
        } else {
            $other_count = $count - 2;
            $item_summary = "$count Items ($first_item, $second_item, +$other_count more)";
        }
    } else {
        $item_summary = 'No Items';
    }

    
    $reserve_date_str = $reserve_date_context;
    $link_to_approval = BASE_URL . 'index.php?page=approvals&reserve_id=' . $reserve_id; 
    
    $email_sent = false;
    
    if (defined('TECHNICIAN_GROUP_EMAIL') && defined('BASE_URL')) {
        // Fungsi ini harus ada dalam send_email.php
        $email_sent = sendNewReservationNotification(
            $technician_email, 
            $reserve_id, 
            $user_name, 
            $item_summary, 
            $reserve_date_str, 
            $link_to_approval
        );
    }
    
    $email_message = $email_sent ? ' Technician notification sent.' : ' Warning: Failed to send technician email notification. Please check the error log.';
    
    
    echo json_encode(['status' => 'success', 'message' => 'Booking successfully sent! ' . $email_message]);

} catch (Exception $e) {
    
    $conn->rollback();
    
    send_error("Submission failed: " . $e->getMessage()); 
}

$conn->close();