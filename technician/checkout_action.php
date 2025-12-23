<?php

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();

include __DIR__ . '/../config.php';
include 'config_email.php';
require 'send_email.php';
include_once '../logger.php'; // --- TAMBAHAN: Hubungkan logger ---

if (!isset($_SESSION['person_id'])) {
 header('Content-Type: application/json');
 http_response_code(403);
 echo json_encode(['message' => 'Access Denied. Please log in again.']);
 exit();
}

$person_id = (int)$_SESSION['person_id'];
// Set role untuk logger (mengikut enum db kau)
$user_role = strtolower($_SESSION['logged_in_role'] ?? 'tech');
if($user_role == 'technician') $user_role = 'tech'; 

$action = '';

if (isset($_POST['action'])) {
 $action = $_POST['action'];
} elseif (isset($_GET['action'])) {
 $action = $_GET['action'];
}

if (empty($action)) {
 header('Content-Type: application/json');
 http_response_code(400);
 echo json_encode(['message' => 'Invalid request. No action specified.']);
 exit();
}

header('Content-Type: application/json');
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

switch ($action) {
 case 'approve':

$reservation_item_id = isset($_POST['reservation_item_id']) ? (int)$_POST['reservation_item_id'] : 0;
$selectedAssets = isset($_POST['selectedAssets']) ? $_POST['selectedAssets'] : array();
$new_quantity = isset($_POST['approved_quantity']) ? (int)$_POST['approved_quantity'] : 0;
$partial_reason = isset($_POST['partial_reason']) ? trim($_POST['partial_reason']) : '';

$technician_id = isset($_SESSION['person_id']) ? (int)$_SESSION['person_id'] : 0;


$original_qty = 0;
$reserve_id = 0;

// Fetch original quantity and reserve_id
try {
 $stmt_original = $conn->prepare("SELECT quantity, reserve_id FROM reservation_items WHERE id = ?");
 $stmt_original->bind_param("i", $reservation_item_id);
 $stmt_original->execute();
 $res_original = $stmt_original->get_result();
 if ($row = $res_original->fetch_assoc()) {
$original_qty = (int)$row['quantity'];
$reserve_id = (int)$row['reserve_id'];
 }
 $stmt_original->close();
 if ($reserve_id === 0) throw new Exception("Reserve ID not found for item ID: " . $reservation_item_id);
} catch (Exception $e) {
 error_log("ORIGINAL_QTY_FETCH_ERROR: " . $e->getMessage());
 http_response_code(500);
 echo json_encode(['message' => 'System error while retrieving original quantity/Reserve ID.']);
 exit();
}


// --- LOGIC REJECT/FULL REJECTION (Approved Quantity is 0) ---
if ($new_quantity == 0) {

 if (strlen($partial_reason) < 5) {
http_response_code(400);
echo json_encode(['message' => 'Full rejection reason is required if the Approved Quantity is 0.']);
exit();
 }

 $reason = $partial_reason;

 $user_info = null;
 try {
// Fetch user info and reservation details
$stmt_info = $conn->prepare("SELECT r.person_id, i.item_name, p.email, p.name AS user_name, r.reserve_id, r.reserve_date, r.return_date
FROM reservations r
JOIN reservation_items ri ON r.reserve_id = ri.reserve_id
JOIN item i ON ri.item_id = i.item_id
JOIN person p ON r.person_id = p.person_id
WHERE ri.id = ?");
if (!$stmt_info) throw new Exception("Prepare failed (reject info): " . $conn->error);

$stmt_info->bind_param("i", $reservation_item_id);
$stmt_info->execute();
$user_info = $stmt_info->get_result()->fetch_assoc();
$stmt_info->close();

if (!$user_info) throw new Exception("User info not found.");

$person_id_applicant = (int)$user_info['person_id'];
$item_name = htmlspecialchars($user_info['item_name']);
$user_email = $user_info['email'];
$user_name = $user_info['user_name'];
$reserve_id = (int)$user_info['reserve_id'];

 } catch (Exception $e) {
error_log("Reject Info Fetch Error: " . $e->getMessage());
http_response_code(500);
echo json_encode(['success' => false, 'message' => 'Failed to retrieve user information for full rejection.']);
exit();
 }


 $conn->begin_transaction();
 try {

// 1. UPDATE reservation_items status to Rejected (PEMBETULAN: SET partial_reason = NULL)
$stmt_r = $conn->prepare("UPDATE reservation_items 
 SET status = 'Rejected', 
rejection_reason = ?, 
partial_reason = NULL
 WHERE id = ?");
if (!$stmt_r) throw new Exception("Prepare failed (reject update): " . $conn->error);
$stmt_r->bind_param("si", $reason, $reservation_item_id);
$stmt_r->execute();
$stmt_r->close();


// 2. INSERT notification
$message_notify = "Your request for " . $item_name . " has been rejected. Reason: " . htmlspecialchars($reason);
$stmt_notify = $conn->prepare("INSERT INTO notifications (person_id, message, type, related_id) VALUES (?, ?, 'reject', ?)");
if (!$stmt_notify) throw new Exception("Prepare failed (notify reject): " . $conn->error);
$stmt_notify->bind_param("isi", $person_id_applicant, $message_notify, $reservation_item_id);
$stmt_notify->execute();
$stmt_notify->close();

// --- TAMBAHAN LOGGER ---
log_activity($conn, $user_role, $person_id, "Reject Item", "Rejected Item: $item_name (Booking #$reserve_id). Reason: $reason");

$conn->commit();
$response_message = 'The request was completely rejected because the Approved Quantity is 0.';


// 3. CHECK PENDING ITEMS for GROUP EMAIL
$stmt_check_pending = $conn->prepare("SELECT COUNT(id) FROM reservation_items WHERE reserve_id = ? AND status = 'Pending'");
$stmt_check_pending->bind_param("i", $reserve_id);
$stmt_check_pending->execute();
$pending_count = 0;
$stmt_check_pending->bind_result($pending_count);
$stmt_check_pending->fetch();
$stmt_check_pending->close();

$email_sent = false;

if ($pending_count === 0) {
 // Semua item telah diproses (Rejected atau Approved)

 $reservation_items_list = fetch_reservation_items_by_id($conn, $reserve_id);

 if (!empty($reservation_items_list) && defined('SMTP_USER') && defined('SMTP_PASS')) {

$email_sent = sendGroupedRejectionEmail(
 $user_email,
 $user_name,
 $reserve_id,
 $reservation_items_list, // List yang mengandungi status item/sebab reject
 SMTP_USER,
 SMTP_PASS
);


$response_message .= ' The Group Email Notification has been sent.';

 } else {
$response_message .= ' Warning: Email Notification failed to send (Data/SMTP Error).';
 }
} else {
 $response_message .= " Individual item rejected. Waiting for {$pending_count} the items remaining in this order are being processed.";
}


echo json_encode(['success' => true, 'message' => $response_message]);
exit();

 } catch (Exception $e) {
$conn->rollback();
http_response_code(500);
error_log("DB_TRANSACTION_ERROR (Full Reject - Approve Case): " . $e->getMessage());
echo json_encode(['success' => false, 'message' => 'Database Transaction Failed during full rejection: ' . $e->getMessage()]);
exit();
 }
}


// --- LOGIC APPROVAL (Partial or Full Approval) ---

if (empty($reservation_item_id) || empty($selectedAssets) || $new_quantity <= 0) {
 http_response_code(400);
 echo json_encode(['message' => 'Incomplete information (ID, Asset, or Quantity).']);
 exit();
}

if ($new_quantity < $original_qty && strlen($partial_reason) < 5) {
 http_response_code(400);
 echo json_encode(['message' => 'Rejection reason is necessary if the quantity is reduced (Partial Rejection).']);
 exit();
}

if (count($selectedAssets) !== $new_quantity) {
 http_response_code(400);
 echo json_encode(['message' => 'Mismatch Error: The total assets do not match the approved quantity.']);
 exit();
}


$conn->begin_transaction();
try {

 // 1. UPDATE reservation_items status to Approved (PEMBETULAN: GUNA partial_reason DAN SET rejection_reason = NULL)
 $stmt = $conn->prepare("UPDATE reservation_items
SET status = 'Approved',
 approved_by = ?,
 quantity = ?,
 partial_reason = ?, 
 rejection_reason = NULL, 
 approved_on = CURDATE()
WHERE id = ?");
 if (!$stmt) throw new Exception("Prepare failed (update item): " . $conn->error);

 if ($technician_id === 0) throw new Exception("Person ID (Technician) is missing from session.");

 $stmt->bind_param("iisi", $technician_id, $new_quantity, $partial_reason, $reservation_item_id);
 $stmt->execute();
 $stmt->close();


 // 2. DELETE existing assets (for safety, although status is 'Pending')
 $stmt_delete_ra = $conn->prepare("DELETE FROM reservation_assets WHERE reservation_item_id = ?");
 if (!$stmt_delete_ra) throw new Exception("Prepare failed (delete ra): " . $conn->error);
 $stmt_delete_ra->bind_param("i", $reservation_item_id);
 $stmt_delete_ra->execute();
 $stmt_delete_ra->close();


 // 3. INSERT new assets (reservation_assets) & UPDATE assets status
 $stmt_asset_insert = $conn->prepare("INSERT INTO reservation_assets (reservation_item_id, asset_id) VALUES (?, ?)");
 if (!$stmt_asset_insert) throw new Exception("Prepare failed (insert asset): " . $conn->error);


 $stmt_asset_update = $conn->prepare("UPDATE assets SET `status` = 'Reserved' WHERE asset_id = ?");
 if (!$stmt_asset_update) throw new Exception("Prepare failed (update asset status): " . $conn->error);

 foreach ($selectedAssets as $asset_id) {
$asset_id_int = (int)$asset_id;

$stmt_asset_insert->bind_param("ii", $reservation_item_id, $asset_id_int);
$stmt_asset_insert->execute();

$stmt_asset_update->bind_param("i", $asset_id_int);
$stmt_asset_update->execute();
 }
 $stmt_asset_insert->close();
 $stmt_asset_update->close();

 // --- TAMBAHAN LOGGER ---
 log_activity($conn, $user_role, $person_id, "Approve Item", "Approved $new_quantity units for Item ID: $reservation_item_id (Booking #$reserve_id)");

 $conn->commit();


 // 4. CHECK PENDING ITEMS for GROUP EMAIL
 $stmt_check_pending = $conn->prepare("SELECT COUNT(id) FROM reservation_items WHERE reserve_id = ? AND status = 'Pending'");
 $stmt_check_pending->bind_param("i", $reserve_id);
 $stmt_check_pending->execute();
 $pending_count = 0;
 $stmt_check_pending->bind_result($pending_count);
 $stmt_check_pending->fetch();
 $stmt_check_pending->close();


 $email_sent = false;
 $message = "Request approved for {$new_quantity} unit!";

 if ($pending_count === 0) {
// Semua item telah diproses

$reservation_items_list = fetch_reservation_items_by_id($conn, $reserve_id);

if (!empty($reservation_items_list) && defined('SMTP_USER') && defined('SMTP_PASS')) {

 $user_email = $reservation_items_list[0]['user_email'];
 $user_name = $reservation_items_list[0]['user_name'];

 $email_sent = sendGroupedNotificationEmail(
$user_email,
$user_name,
$reserve_id,
$reservation_items_list,
SMTP_USER,
SMTP_PASS
 );

 $message .= ' Email Notification has been sent.';

} else {
 $message .= ' Warning: Email Notification failed to send (Data/SMTP Error).';
}
 } else {
$message .= " Individual item approved. Waiting {$pending_count} the items remaining in this order are being processed.";
 }


 // 5. INSERT INTERNAL NOTIFICATION
 $info = null;
 try {
// Fetch info for user notification
$stmt_info_db = $conn->prepare("
 SELECT r.person_id, i.item_name AS item_name FROM reservation_items ri
 JOIN reservations r ON ri.reserve_id = r.reserve_id
 JOIN item i ON ri.item_id = i.item_id WHERE ri.id = ?
");
 $stmt_info_db->bind_param("i", $reservation_item_id);
 $stmt_info_db->execute();
 $info = $stmt_info_db->get_result()->fetch_assoc();
 $stmt_info_db->close();
 } catch (Exception $e) { /* Abaikan ralat ini, ia hanya untuk notifikasi dalaman */ }


 if ($info && isset($info['person_id'])) {
$message_notify = "Your request for " . htmlspecialchars($info['item_name']) . " has been approved and is ready to be collected. ";
if ($original_qty > $new_quantity) {
 $message_notify .= " (Quantity reduced from {$original_qty} to {$new_quantity}).";
}

$stmt_notify = $conn->prepare("INSERT INTO notifications (person_id, message, type, related_id) VALUES (?, ?, 'approve', ?)");
if (!$stmt_notify) { error_log("Notification Prepare Error: " . $conn->error); }
else {
 $stmt_notify->bind_param("isi", $info['person_id'], $message_notify, $reservation_item_id);
 $stmt_notify->execute();
 $stmt_notify->close();
}
 }

 echo json_encode(['success' => true, 'message' => $message]);

} catch (Exception $e) {
 $conn->rollback();
 http_response_code(500);
 error_log("DB_TRANSACTION_ERROR (Approve): " . $e->getMessage());
 echo json_encode(['success' => false, 'message' => 'Database Transaction Failed: ' . $e->getMessage()]);
}
break;

 case 'reject':
$reservation_item_id = isset($_POST['reservation_item_id']) ? (int)$_POST['reservation_item_id'] : 0;
$reason = isset($_POST['reason']) ? trim($_POST['reason']) : '';

if (strlen($reason) < 5) {
 http_response_code(400);
 echo json_encode(['success' => false, 'message' => 'Reason for rejection is required (min 5 characters).']);
 exit();
}

$conn->begin_transaction();
try {

 // 1. UPDATE reservation_items status to Rejected (PEMBETULAN: SET partial_reason = NULL)
 $stmt_r = $conn->prepare("UPDATE reservation_items 
SET status = 'Rejected', 
 rejection_reason = ?,
 partial_reason = NULL
WHERE id = ?");
 if (!$stmt_r) throw new Exception("Prepare failed (reject update): " . $conn->error);
 $stmt_r->bind_param("si", $reason, $reservation_item_id);
 $stmt_r->execute();
 $stmt_r->close();


 // 2. GET USER INFO for notification
 // Fetch user info
 $stmt_user_info = $conn->prepare("
SELECT r.person_id, p.email, p.name AS user_name, i.item_name
FROM reservation_items ri
JOIN reservations r ON ri.reserve_id = r.reserve_id
JOIN person p ON r.person_id = p.person_id
JOIN item i ON ri.item_id = i.item_id
WHERE ri.id = ?
 ");
 if (!$stmt_user_info) throw new Exception("Prepare failed (get info): " . $conn->error);

 $stmt_user_info->bind_param("i", $reservation_item_id);
 $stmt_user_info->execute();
 $user_info = $stmt_user_info->get_result()->fetch_assoc();
 $stmt_user_info->close();

 $email_sent = false;
 if ($user_info) {
$person_id_applicant = (int)$user_info['person_id'];
$item_name = htmlspecialchars($user_info['item_name']);
$user_email = $user_info['email'];
$user_name = $user_info['user_name'];


// 3. INSERT internal notification
$message_db = "Your request for " . $item_name . " has been REJECTED. Reason: " . htmlspecialchars($reason);
$stmt_notify = $conn->prepare("INSERT INTO notifications (person_id, message, type, related_id) VALUES (?, ?, 'reject', ?)");
if (!$stmt_notify) throw new Exception("Notification INSERT Prepare Error: " . $conn->error);
$stmt_notify->bind_param("isi", $person_id_applicant, $message_db, $reservation_item_id);
$stmt_notify->execute();
$stmt_notify->close();


// 4. SEND EMAIL
if (!empty($user_email) && defined('SMTP_USER') && defined('SMTP_PASS')) {
 $email_sent = sendRejectionEmail(
$user_email,
$user_name,
$item_name,
$reason,
SMTP_USER,
SMTP_PASS
 );
}
 }

 // --- TAMBAHAN LOGGER ---
 log_activity($conn, $user_role, $person_id, "Reject Item", "Rejected Item: $item_name (ID: $reservation_item_id). Reason: $reason");

 $conn->commit();
 $message = 'The request was successfully declined and the user has been informed.';
 $message .= $email_sent ? ' Email notification has been sent.' : ' Warning: Email notification failed to send.';
 echo json_encode(['success' => true, 'message' => $message]);

} catch (Exception $e) {
 $conn->rollback();
 http_response_code(500);
 error_log("DB_TRANSACTION_ERROR (Reject): " . $e->getMessage());
 echo json_encode(['success' => false, 'message' => 'Failed to decline request: ' . $e->getMessage()]);
}
break;

 case 'approve_all_items':
$reserve_id = isset($_POST['reserve_id']) ? (int)$_POST['reserve_id'] : 0;
$technician_id = isset($_SESSION['person_id']) ? (int)$_SESSION['person_id'] : 0;

if ($reserve_id === 0) {
 http_response_code(400);
 echo json_encode(['message' => 'Missing Reserve ID.']);
 exit();
}

$conn->begin_transaction();
$total_approved_items = 0;

try {

 // 1. SELECT Pending Items
 $stmt_pending = $conn->prepare("
SELECT
 ri.id AS reservation_item_id,
 ri.item_id,
 ri.quantity,
 i.item_name
FROM
 reservation_items ri
JOIN
 item i ON ri.item_id = i.item_id
WHERE
 ri.reserve_id = ? AND ri.status = 'Pending'
 ");
 if (!$stmt_pending) throw new Exception("Prepare failed (select pending items): " . $conn->error);
 $stmt_pending->bind_param("i", $reserve_id);
 $stmt_pending->execute();
 $pending_items = $stmt_pending->get_result()->fetch_all(MYSQLI_ASSOC);
 $stmt_pending->close();

 if (empty($pending_items)) {
$conn->rollback();
echo json_encode(['success' => true, 'message' => 'There are no pending items for approval under this Booking ID.']);
exit();
 }


 foreach ($pending_items as $item) {
$ri_id = (int)$item['reservation_item_id'];
$item_id = (int)$item['item_id'];
$quantity = (int)$item['quantity'];


// 2. SELECT Available Assets
$stmt_assets = $conn->prepare("
 SELECT asset_id
 FROM assets
 WHERE item_id = ? AND status = 'Available'
 LIMIT ?
");
if (!$stmt_assets) throw new Exception("Prepare failed (select assets): " . $conn->error);
$stmt_assets->bind_param("ii", $item_id, $quantity);
$stmt_assets->execute();
$available_assets = $stmt_assets->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt_assets->close();

$asset_ids_to_assign = array_column($available_assets, 'asset_id');
$approved_quantity = count($asset_ids_to_assign);

if ($approved_quantity < $quantity) {
 // PARTIAL/FULL REJECTION DUE TO LACK OF ASSETS

 $reason = "Automatically Rejected: Only {$approved_quantity} assets available for the requested quantity ({$quantity}).";

 $stmt_reject = $conn->prepare("UPDATE reservation_items 
 SET status = 'Rejected', 
rejection_reason = ?, 
partial_reason = NULL,
quantity = ? 
 WHERE id = ?"); // Set quantity to actual approved (0)
 $stmt_reject->bind_param("sii", $reason, $approved_quantity, $ri_id); 
 $stmt_reject->execute();
 $stmt_reject->close();

 $total_approved_items += 0;

} else {
 // FULL APPROVAL (quantity = approved_quantity)


 // 3. UPDATE reservation_items status to Approved
 $stmt_update_item = $conn->prepare("UPDATE reservation_items
 SET status = 'Approved',
approved_by = ?,
approved_on = CURDATE(),
partial_reason = NULL, 
rejection_reason = NULL, 
quantity = ?
 WHERE id = ?");
 $stmt_update_item->bind_param("iii", $technician_id, $quantity, $ri_id);
 $stmt_update_item->execute();
 $stmt_update_item->close();


 // 4. INSERT reservation_assets & UPDATE assets status
 $stmt_asset_insert = $conn->prepare("INSERT INTO reservation_assets (reservation_item_id, asset_id) VALUES (?, ?)");
 $stmt_asset_update = $conn->prepare("UPDATE assets SET `status` = 'Reserved' WHERE asset_id = ?");

 foreach ($asset_ids_to_assign as $asset_id) {
$stmt_asset_insert->bind_param("ii", $ri_id, $asset_id);
$stmt_asset_insert->execute();

$stmt_asset_update->bind_param("i", $asset_id);
$stmt_asset_update->execute();
 }
 $stmt_asset_insert->close();
 $stmt_asset_update->close();

 $total_approved_items += 1;
}
 }

 // --- TAMBAHAN LOGGER (Bulk) ---
 log_activity($conn, $user_role, $person_id, "Approve All", "Approved $total_approved_items items for Booking #$reserve_id");

 $conn->commit();
 $message = "Successfully processed {$total_approved_items} item approved for Booking ID {$reserve_id}.";


 // 5. CHECK PENDING ITEMS for GROUP EMAIL (Check again, although loop has processed 'Pending' items, it checks the overall status)
 $stmt_check_pending = $conn->prepare("SELECT COUNT(id) FROM reservation_items WHERE reserve_id = ? AND status = 'Pending'");
 $stmt_check_pending->bind_param("i", $reserve_id);
 $stmt_check_pending->execute();
 $pending_count_final = 0;
 $stmt_check_pending->bind_result($pending_count_final);
 $stmt_check_pending->fetch();
 $stmt_check_pending->close();

 if ($pending_count_final === 0) {

$reservation_items_list = fetch_reservation_items_by_id($conn, $reserve_id);

if (!empty($reservation_items_list)) {
 $message .= " All items have been processed. Notification email sent.";
 $user_email = $reservation_items_list[0]['user_email'];
 $user_name = $reservation_items_list[0]['user_name'];
 // Send notification
 sendGroupedNotificationEmail($user_email, $user_name, $reserve_id, $reservation_items_list, SMTP_USER, SMTP_PASS);
}
 } else {
$message .= " Some items were automatically rejected due to lack of assets. The overall status is still 'Pending' for items that have not been processed. (Final Pending Count: {$pending_count_final})";
 }

 echo json_encode(['success' => true, 'message' => $message]);

} catch (Exception $e) {
 $conn->rollback();
 http_response_code(500);
 error_log("DB_TRANSACTION_ERROR (Approve All): " . $e->getMessage());
 echo json_encode(['success' => false, 'message' => 'Database Transaction Failed during approval: ' . $e->getMessage()]);
}
break;

// --- CHECKOUT ACTIONS ---
case 'checkout_all_items':
 $reserve_id = isset($_POST['reserve_id']) ? (int)$_POST['reserve_id'] : 0;
 $technician_id = isset($_SESSION['person_id']) ? (int)$_SESSION['person_id'] : 0;

 if ($reserve_id === 0) {
http_response_code(400);
echo json_encode(['success' => false, 'message' => 'Missing Reservation ID for action.']);
exit();
 }
 if ($technician_id === 0) {
http_response_code(401);
echo json_encode(['success' => false, 'message' => 'Unauthorized: Technician ID not found in session.']);
exit();
 }

 $conn->begin_transaction();
 $total_assets_updated = 0;
 $total_items_updated = 0;

 try {

// 1. SELECT all Approved items and their assigned assets for this reserve_id
$stmt_items = $conn->prepare("
 SELECT ri.id, i.item_name, GROUP_CONCAT(ra.asset_id) AS asset_ids
 FROM reservation_items ri
 JOIN item i ON ri.item_id = i.item_id
 LEFT JOIN reservation_assets ra ON ri.id = ra.reservation_item_id
 WHERE ri.reserve_id = ? AND ri.status = 'Approved'
 GROUP BY ri.id, i.item_name
");
if (!$stmt_items) throw new Exception("Prepare failed (select items): " . $conn->error);

$stmt_items->bind_param("i", $reserve_id);
$stmt_items->execute();
$items_to_checkout = $stmt_items->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt_items->close();

if (empty($items_to_checkout)) {
 $conn->rollback();
 echo json_encode(['success' => false, 'message' => 'No approved items found to check out for this reservation.']);
 exit();
}


// 2. Prepare Update Statements
$stmt_item_update = $conn->prepare("UPDATE reservation_items
 SET status = 'Checked Out',
checked_out_by = ?,
checked_out_on = NOW()
 WHERE id = ?"); 
if (!$stmt_item_update) throw new Exception("Prepare failed (update item status): " . $conn->error);

$stmt_asset_update = $conn->prepare("UPDATE assets SET `status` = 'Checked Out' WHERE asset_id = ?");
if (!$stmt_asset_update) throw new Exception("Prepare failed (update asset status): " . $conn->error);


// 3. Loop through items and update DB
$asset_log_details = [];
foreach ($items_to_checkout as $item) {
 $reservation_item_id = (int)$item['id'];
 $item_name = htmlspecialchars($item['item_name']);
 $asset_ids_str = $item['asset_ids'];

 if (empty($asset_ids_str)) continue;

 $asset_ids = explode(',', $asset_ids_str);


 // Update reservation_items
 $stmt_item_update->bind_param("ii", $technician_id, $reservation_item_id);
 $stmt_item_update->execute();
 $total_items_updated++;


 // Update assets
 foreach($asset_ids as $asset_id) {
$asset_id_int = (int)$asset_id;


$stmt_asset_update->bind_param("i", $asset_id_int);
$stmt_asset_update->execute();
$total_assets_updated++;


 }


 $asset_log_details[] = "Item: {$item_name} (IDs: {$asset_ids_str})";


}

$stmt_item_update->close();
$stmt_asset_update->close();

// --- TAMBAHAN LOGGER ---
log_activity($conn, $user_role, $person_id, "Bulk Checkout", "Issued $total_assets_updated assets for Booking #$reserve_id");

$conn->commit();
echo json_encode([
 'success' => true,
 'message' => "Bulk Check-Out success. {$total_items_updated} item updated, {$total_assets_updated} asset updated to 'Checked Out'. Technician ID {$technician_id} recorded."
]);

 } catch (Exception $e) {
$conn->rollback();
http_response_code(500);
error_log("DB_TRANSACTION_ERROR (Checkout All): " . $e->getMessage());
echo json_encode(['success' => false, 'message' => 'Check-out gagal: ' . $e->getMessage()]);
 }
 break;


case 'get_assets_for_checkout':

 $reservation_item_id = isset($_GET['reservation_item_id']) ? (int)$_GET['reservation_item_id'] : 0;
 if ($reservation_item_id === 0) {
http_response_code(400);
echo json_encode(['message' => 'Missing ID for check-out asset fetching.']);
exit();
 }

 $stmt = $conn->prepare("
SELECT a.asset_id, a.asset_code
FROM assets a
JOIN reservation_assets ra ON a.asset_id = ra.asset_id
WHERE ra.reservation_item_id = ? AND a.status = 'Reserved'
 ");

 if (!$stmt) {
http_response_code(500);
echo json_encode(['message' => 'Prepare failed (get assets for checkout): ' . $conn->error]);
exit();
 }

 $stmt->bind_param("i", $reservation_item_id);
 $stmt->execute();
 $result = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
 $stmt->close();

 echo json_encode($result);
 break;


case 'checkout_multi':
 $reservation_item_id = isset($_POST['reservation_item_id']) ? (int)$_POST['reservation_item_id'] : 0;
 $asset_ids_json = isset($_POST['asset_ids']) ? $_POST['asset_ids'] : '[]';
 $technician_id = isset($_SESSION['person_id']) ? (int)$_SESSION['person_id'] : 0;

 $asset_ids = json_decode($asset_ids_json, true);

 if (empty($reservation_item_id) || empty($asset_ids) || !is_array($asset_ids)) {
http_response_code(400);
echo json_encode(['message' => 'Missing required information (ID or Asset IDs).']);
exit();
 }
 if ($technician_id === 0) {
http_response_code(401);
echo json_encode(['success' => false, 'message' => 'Unauthorized: Technician ID not found in session.']);
exit();
 }

 $conn->begin_transaction();
 try {

// 1. Get Item Name (for logging/message)
$stmt_item_info = $conn->prepare("SELECT i.item_name FROM reservation_items ri JOIN item i ON ri.item_id = i.item_id WHERE ri.id = ?");
if (!$stmt_item_info) throw new Exception("Prepare failed (item info): " . $conn->error);
$stmt_item_info->bind_param("i", $reservation_item_id);
$stmt_item_info->execute();
$item_info = $stmt_item_info->get_result()->fetch_assoc();
$stmt_item_info->close();
$item_name = $item_info ? htmlspecialchars($item_info['item_name']) : 'N/A';

// 2. Update reservation_items status
$stmt_item = $conn->prepare("UPDATE reservation_items
 SET status = 'Checked Out',
checked_out_by = ?,
checked_out_on = NOW()
 WHERE id = ? AND status = 'Approved'");
if (!$stmt_item) throw new Exception("Prepare failed (update item status): " . $conn->error);
$stmt_item->bind_param("ii", $technician_id, $reservation_item_id);
$stmt_item->execute();

// Check if item was actually updated (status was 'Approved')
if ($stmt_item->affected_rows === 0) {
 $stmt_item->close();
 $conn->rollback();
 http_response_code(409); // Conflict
 echo json_encode(['message' => "Item status is not 'Approved' or ID is invalid."]);
 exit();
}
$stmt_item->close();


// 3. Update assets status
$asset_placeholders = implode(',', array_fill(0, count($asset_ids), '?'));
$stmt_asset_update = $conn->prepare("UPDATE assets SET `status` = 'Checked Out' WHERE asset_id IN ($asset_placeholders)");
if (!$stmt_asset_update) throw new Exception("Prepare failed (update assets status): " . $conn->error);

$types = str_repeat('i', count($asset_ids));
// Ensure all IDs are integer
$asset_ids_int = array_map('intval', $asset_ids);

$stmt_asset_update->bind_param($types, ...$asset_ids_int);
$stmt_asset_update->execute();
$stmt_asset_update->close();


// 4. Log/Message
$asset_ids_str = implode(',', $asset_ids);
$log_desc = "Checked Out Item: {$item_name}. Assigned Assets: {$asset_ids_str}."; // Logik log sedia ada

// --- TAMBAHAN LOGGER ---
log_activity($conn, $user_role, $person_id, "Checkout", "Issued Assets ($asset_ids_str) for Item ID: $reservation_item_id");

$conn->commit();
echo json_encode(['message' => "Item successfully issued. " . count($asset_ids) . " the asset's status has been updated."]);
 } catch (Exception $e) {
$conn->rollback();
http_response_code(500);
error_log("DB_TRANSACTION_ERROR (Checkout Multi): " . $e->getMessage());
echo json_encode(['message' => 'Check-out failed: ' . $e->getMessage()]);
 }
 break;
 
// --- CHECK-IN ACTIONS ---
case 'get_assets_for_checkin':

 $reservation_item_id = isset($_GET['reservation_item_id']) ? (int)$_GET['reservation_item_id'] : 0;
 if ($reservation_item_id === 0) {
http_response_code(400); echo json_encode(['message' => 'Missing ID.']); exit();
 }

 $stmt = $conn->prepare("
SELECT a.asset_id, a.asset_code
FROM assets a
JOIN reservation_assets ra ON a.asset_id = ra.asset_id
WHERE ra.reservation_item_id = ? AND a.status = 'Checked Out'
 ");
 if (!$stmt) {
http_response_code(500); echo json_encode(['message' => 'Prepare failed (get assets): ' . $conn->error]); exit();
 }

 $stmt->bind_param("i", $reservation_item_id);
 $stmt->execute();
 $result = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
 $stmt->close();

 echo json_encode($result);
 break;

case 'checkin_multi':
        // Dapatkan data dari permintaan POST
        $reservation_item_id = isset($_POST['reservation_item_id']) ? (int)$_POST['reservation_item_id'] : 0;
        $asset_conditions_json = isset($_POST['asset_conditions']) ? $_POST['asset_conditions'] : '[]';
        $technician_id = isset($_SESSION['person_id']) ? (int)$_SESSION['person_id'] : 0;

        $asset_conditions = json_decode($asset_conditions_json, true);

        // --- VALIDASI AWAL ---
        if (empty($reservation_item_id) || empty($asset_conditions) || !is_array($asset_conditions)) {
            http_response_code(400);
            echo json_encode(['message' => 'Missing required information (ID or Asset Conditions).']);
            exit();
        }
        if ($technician_id === 0) {
            http_response_code(401);
            echo json_encode(['success' => false, 'message' => 'Unauthorized: Technician ID not found in session.']);
            exit();
        }

        // --- TRANSAKSI PANGKALAN DATA ---
        $conn->begin_transaction();
        try {
            $damaged_count = 0;
            $checked_in_count = 0;
            $available_asset_codes = [];
            $damaged_asset_codes = [];

            // 1. Dapatkan Nama Item (untuk log)
            $stmt_item_info = $conn->prepare("SELECT i.item_name 
                                             FROM reservation_items ri 
                                             JOIN item i ON ri.item_id = i.item_id 
                                             WHERE ri.id = ?");
            if (!$stmt_item_info) throw new Exception("Prepare failed (item info): " . $conn->error);
            $stmt_item_info->bind_param("i", $reservation_item_id);
            $stmt_item_info->execute();
            $item_info = $stmt_item_info->get_result()->fetch_assoc();
            $stmt_item_info->close();
            $item_name = $item_info ? htmlspecialchars($item_info['item_name']) : 'N/A';

            // 2. Sediakan Pernyataan untuk Kemas Kini Aset & Dapatkan Kod Aset
            $stmt_asset_update = $conn->prepare("UPDATE assets 
                                                 SET `status` = ?, last_return_date = CURDATE() 
                                                 WHERE asset_id = ?");
            $stmt_get_asset_code = $conn->prepare("SELECT asset_code FROM assets WHERE asset_id = ?");

            if (!$stmt_asset_update) throw new Exception("Prepare failed (asset update): " . $conn->error);
            if (!$stmt_get_asset_code) throw new Exception("Prepare failed (get asset code): " . $conn->error);


            // 3. Proses Setiap Keadaan Aset (Mengemas kini status aset individu)
            foreach ($asset_conditions as $asset) {
                if (!isset($asset['asset_id']) || !isset($asset['condition'])) continue; 

                $asset_id = (int)$asset['asset_id'];
                $condition = $asset['condition']; 

                $new_asset_status = 'Checked Out'; 

                if ($condition === 'Good' || $condition === 'Available') { 
                    $new_asset_status = 'Available';
                } elseif ($condition === 'Damaged') {
                    $new_asset_status = 'Maintenance';
                    $damaged_count++;
                } elseif ($condition === 'Not_Returned_Yet') {
                    // Abaikan aset ini; ia masih dalam status 'Checked Out' dalam jadual `assets`
                    continue;
                }

                if ($new_asset_status !== 'Checked Out') {
                    
                    // A. Kemas Kini Jadual `assets`
                    $stmt_asset_update->bind_param("si", $new_asset_status, $asset_id);
                    if (!$stmt_asset_update->execute()) {
                        throw new Exception("Asset update failed for asset_id {$asset_id}: " . $stmt_asset_update->error);
                    }

                    // B. Dapatkan Kod Aset untuk Tujuan Log
                    $stmt_get_asset_code->bind_param("i", $asset_id);
                    $stmt_get_asset_code->execute();
                    $code_row = $stmt_get_asset_code->get_result()->fetch_assoc();

                    if ($code_row) {
                        if ($new_asset_status === 'Maintenance') {
                            $damaged_asset_codes[] = $code_row['asset_code'];
                        } else {
                            $available_asset_codes[] = $code_row['asset_code'];
                        }
                    }

                    $checked_in_count++;
                }
            }

            $stmt_asset_update->close();
            $stmt_get_asset_code->close();


            // 4. Semak Baki Aset 'Checked Out' 
            $stmt_check_remaining = $conn->prepare("
                SELECT COUNT(a.asset_id) AS remaining_count
                FROM reservation_assets ra
                JOIN assets a ON ra.asset_id = a.asset_id
                WHERE ra.reservation_item_id = ? AND a.status = 'Checked Out'
            ");
            if (!$stmt_check_remaining) throw new Exception("Prepare failed (check remaining): " . $conn->error);

            $stmt_check_remaining->bind_param("i", $reservation_item_id);
            $stmt_check_remaining->execute();
            $remaining_row = $stmt_check_remaining->get_result()->fetch_assoc();
            $remaining_count = (int)$remaining_row['remaining_count'];
            $stmt_check_remaining->close();


            // 5. Kemas Kini Status `reservation_items` (Butiran Item Tempahan)
            $final_item_status = ($remaining_count > 0) ? 'Checked Out' : 'Returned';
            $final_condition = ($damaged_count > 0) ? "{$damaged_count} Under Maintenance" : "Good";
            $final_remarks = "Checked in {$checked_in_count} asset(s). Final item status: {$final_item_status}.";

            if ($final_item_status === 'Returned') {
                // KEMAS KINI PENUH: Tetapkan status, checked_in_on, dll. (return_date TIDAK di sini)
                $stmt_item = $conn->prepare("UPDATE reservation_items
                    SET status = ?,
                        return_condition = ?,
                        return_remarks = ?,
                        checked_in_by = ?, 
                        checked_in_on = NOW()
                    WHERE id = ?");
                
                if (!$stmt_item) throw new Exception("Prepare failed (update item returned): " . $conn->error);
                $stmt_item->bind_param("sssii", $final_item_status, $final_condition, $final_remarks, $technician_id, $reservation_item_id);

            } else {
                // KEMAS KINI SEPARA: Hanya kemas kini status/kondisi/catatan
                $stmt_item = $conn->prepare("UPDATE reservation_items
                    SET status = ?,
                        return_condition = ?,
                        return_remarks = ?
                    WHERE id = ?");

                if (!$stmt_item) throw new Exception("Prepare failed (update item checked out): " . $conn->error);
                $stmt_item->bind_param("sssi", $final_item_status, $final_condition, $final_remarks, $reservation_item_id);
            }

            if (!$stmt_item->execute()) throw new Exception("Execute failed (update item): " . $stmt_item->error);
            $stmt_item->close();


            // 6. Kemas Kini Jadual `reservations` (Induk) - Logik return_date
            if ($final_item_status === 'Returned') {
                
                // A. Dapatkan reserve_id daripada reservation_items
                $stmt_get_reserve_id = $conn->prepare("SELECT reserve_id FROM reservation_items WHERE id = ?");
                if (!$stmt_get_reserve_id) throw new Exception("Prepare failed (get reserve id): " . $conn->error);
                $stmt_get_reserve_id->bind_param("i", $reservation_item_id);
                $stmt_get_reserve_id->execute();
                $reserve_row = $stmt_get_reserve_id->get_result()->fetch_assoc();
                $reserve_id = $reserve_row ? (int)$reserve_row['reserve_id'] : 0;
                $stmt_get_reserve_id->close();
                
                // B. Semak sama ada SEMUA item tempahan di bawah reserve_id ini telah 'Returned'
                $stmt_check_all_items = $conn->prepare("
                    SELECT COUNT(id) AS unreturned_count
                    FROM reservation_items
                    WHERE reserve_id = ? AND status != 'Returned'
                ");
                if (!$stmt_check_all_items) throw new Exception("Prepare failed (check all items): " . $conn->error);
                $stmt_check_all_items->bind_param("i", $reserve_id);
                $stmt_check_all_items->execute();
                $unreturned_row = $stmt_check_all_items->get_result()->fetch_assoc();
                $unreturned_count = (int)$unreturned_row['unreturned_count'];
                $stmt_check_all_items->close();

                // C. Jika TIADA item lain yang belum dipulangkan, kemas kini reservations.return_date
                if ($reserve_id > 0 && $unreturned_count === 0) {
                    $stmt_reserve_update = $conn->prepare("UPDATE reservations
                        SET return_date = CURDATE()
                        WHERE reserve_id = ?");
                    
                    if (!$stmt_reserve_update) throw new Exception("Prepare failed (update reservation): " . $conn->error);
                    $stmt_reserve_update->bind_param("i", $reserve_id);
                    if (!$stmt_reserve_update->execute()) throw new Exception("Execute failed (update reservation): " . $stmt_reserve_update->error);
                    $stmt_reserve_update->close();
                }
            }


            // 7. Log dan Commit
            $log_desc = "Checked In Item: {$item_name}. Returned: " . implode(', ', $available_asset_codes) . ". Maintenance: " . implode(', ', $damaged_asset_codes) . ". Item Status: {$final_item_status}.";
            
            // --- TAMBAHAN LOGGER ---
            log_activity($conn, $user_role, $person_id, "Check-in", $log_desc);

            $conn->commit();
            echo json_encode([
                'success' => true,
                'message' => "Check-in success. {$checked_in_count} processed assets. Booking status updated to '{$final_item_status}'"
            ]);

        } catch (Exception $e) {
            $conn->rollback();
            http_response_code(500);
            error_log("Multi Check-in Error for reservation_item_id {$reservation_item_id}: " . $e->getMessage());
            echo json_encode(['message' => 'Check-in gagal: ' . $e->getMessage()]);
        }
        break;
}