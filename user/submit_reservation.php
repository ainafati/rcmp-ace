<?php
session_start();


include '../config.php'; 
include '../technician/config_email.php'; 
require '../technician/send_email.php';

header('Content-Type: application/json');

function send_error($message) {
    echo json_encode(['status' => 'error', 'message' => $message]);
    exit();
}


if (!$conn) {
    send_error('Database connection failed. Check config.php path and settings.');
}



mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);



if (!isset($_SESSION['person_id'])) { 
    send_error('Sesi tamat.'); 
}
$user_id = (int)$_SESSION['person_id'];

if (!isset($_POST['all_items']) || empty($_POST['all_items'])) { 
    send_error('Tiada item dihantar.'); 
}
$items_to_reserve = json_decode($_POST['all_items'], true);

if (empty($items_to_reserve)) { 
    send_error('Senarai item tempahan kosong.'); 
}


$priority = isset($_POST['program_type']) ? (int)$_POST['program_type'] : 3;


$request_reason = isset($_POST['reason']) ? trim($_POST['reason']) : '';

if (empty($request_reason)) {
    send_error('Sebab tempahan (Purpose of Loan) diperlukan.');
}


$conn->begin_transaction();

try {
    
    $stmt_res = $conn->prepare("INSERT INTO reservations (person_id, created_at, priority) VALUES (?, NOW(), ?)");
    if (!$stmt_res) {
        throw new Exception("Error preparing reservation statement: " . $conn->error);
    }
    $stmt_res->bind_param("ii", $user_id, $priority);
    $stmt_res->execute();
    $reserve_id = $conn->insert_id;
    $stmt_res->close();

    
    
    foreach ($items_to_reserve as $item_data) {
        $item_name = $item_data['item_name'];
        $quantity = (int)$item_data['quantity'];
        
        
        if ($quantity <= 0) { 
            throw new Exception("Kuantiti untuk '" . htmlspecialchars($item_name) . "' mesti lebih besar daripada sifar."); 
        }

        
        if (!isset($item_data['reserve_date']) || !isset($item_data['return_date']) || !isset($item_data['reason'])) {
            throw new Exception("Data tarikh atau sebab tidak lengkap untuk item " . htmlspecialchars($item_name) . ".");
        }


        
        $stmt_find_id = $conn->prepare("SELECT item_id FROM item WHERE item_name = ? LIMIT 1");
        if (!$stmt_find_id) {
            throw new Exception("Error preparing item_id statement: " . $conn->error);
        }
        $stmt_find_id->bind_param("s", $item_name);
        $stmt_find_id->execute();
        $result_id = $stmt_find_id->get_result()->fetch_assoc();
        $stmt_find_id->close();
        
        if (!$result_id) { 
            throw new Exception("Item '" . htmlspecialchars($item_name) . "' tidak wujud dalam pangkalan data master."); 
        }
        $item_id = $result_id['item_id'];

        
        $stmt_item = $conn->prepare(
            "INSERT INTO reservation_items (reserve_id, item_id, quantity, reserve_date, return_date, reason, status) VALUES (?, ?, ?, ?, ?, ?, 'Pending')"
        );
        if (!$stmt_item) {
            throw new Exception("Error preparing reservation item statement: " . $conn->error);
        }
        
        
        $stmt_item->bind_param("iiisss", 
            $reserve_id, 
            $item_id, 
            $quantity, 
            $item_data['reserve_date'], 
            $item_data['return_date'], 
            $item_data['reason'] 
        );
        $stmt_item->execute();
        $stmt_item->close();
    }

    $conn->commit();
	$technician_email = TECHNICIAN_GROUP_EMAIL; 
$stmt_user_name = $conn->prepare("SELECT name FROM person WHERE person_id = ?");
 $stmt_user_name->bind_param("i", $user_id);
 $stmt_user_name->execute();
 $user_data = $stmt_user_name->get_result()->fetch_assoc();
 $user_name = $user_data['name'] ?? 'User Unknown';
 $stmt_user_name->close();
 $first_item_name = $items_to_reserve[0]['item_name'] ?? 'Multiple Items';
 $reserve_date_str = $items_to_reserve[0]['reserve_date'] ?? date('Y-m-d');
 $link_to_approval = BASE_URL . 'index.php?page=approvals&reserve_id=' . $reserve_id; 
 $email_sent = false;
    
    if (defined('TECHNICIAN_GROUP_EMAIL') && defined('BASE_URL')) {
        $email_sent = sendNewReservationNotification(
            $technician_email, 
            $reserve_id, 
            $user_name, 
            $first_item_name, 
            $reserve_date_str, 
            $link_to_approval
        );
    }
 $email_message = $email_sent ? ' Notifikasi juruteknik dihantar.' : ' Amaran: Gagal hantar notifikasi e-mel juruteknik. Sila semak log ralat.';
 echo json_encode(['status' => 'success', 'message' => 'Tempahan berjaya dihantar!' . $email_message]);

} catch (Exception $e) {
    $conn->rollback();
    
    send_error("Submission failed: " . $e->getMessage()); 
}

$conn->close();
?>