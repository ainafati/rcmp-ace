<?php

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();



include __DIR__ . '/../config.php';

include 'config_email.php';
require 'send_email.php';



if (!isset($_SESSION['person_id'])) {
    header('Content-Type: application/json');
    http_response_code(403);
    echo json_encode(['message' => 'Access Denied. Please log in again.']);
    exit();
}

$person_id = (int)$_SESSION['person_id']; 

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

switch ($action) {
case 'approve':
    
    $reservation_item_id = isset($_POST['reservation_item_id']) ? (int)$_POST['reservation_item_id'] : 0;
    $selectedAssets = isset($_POST['selectedAssets']) ? $_POST['selectedAssets'] : array(); 
    $new_quantity = isset($_POST['approved_quantity']) ? (int)$_POST['approved_quantity'] : 0;
    $partial_reason = isset($_POST['partial_reason']) ? trim($_POST['partial_reason']) : '';
    
    
    $person_id = isset($_SESSION['person_id']) ? (int)$_SESSION['person_id'] : 0; 
    
    
    $original_qty = 0;
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
    } catch (Exception $e) {
        error_log("ORIGINAL_QTY_FETCH_ERROR: " . $e->getMessage());
        http_response_code(500);
        echo json_encode(['message' => 'Ralat sistem semasa mengambil kuantiti asal.']);
        exit();
    }

    
    
    
    if ($new_quantity == 0) {
        if (strlen($partial_reason) < 5) {
            http_response_code(400);
            echo json_encode(['message' => 'Sebab penolakan penuh diperlukan jika Kuantiti Diluluskan ialah 0.']);
            exit();
        }

        
        $reason = $partial_reason; 
        
        $conn->begin_transaction();
        try {
            
            $stmt_r = $conn->prepare("UPDATE reservation_items SET status = 'Rejected', rejection_reason = ? WHERE id = ?");
            if (!$stmt_r) throw new Exception("Prepare failed (reject update): " . $conn->error);
            $stmt_r->bind_param("si", $reason, $reservation_item_id);
            $stmt_r->execute();
            $stmt_r->close();

            
            $person_id_applicant = null;
            $item_name = '';
            
            $stmt_info = $conn->prepare("SELECT r.person_id, i.item_name 
                                        FROM reservations r 
                                        JOIN reservation_items ri ON r.reserve_id = ri.reserve_id
                                        JOIN item i ON ri.item_id = i.item_id
                                        WHERE ri.id = ?");
            
            if (!$stmt_info) throw new Exception("Prepare failed (reject info): " . $conn->error);

            $stmt_info->bind_param("i", $reservation_item_id);
            $stmt_info->execute();
            $res_info = $stmt_info->get_result();
            if ($row_info = $res_info->fetch_assoc()) {
                $person_id_applicant = (int)$row_info['person_id'];
                $item_name = htmlspecialchars($row_info['item_name']);
            }
            $stmt_info->close();

            
            if ($person_id_applicant !== null) {
                $message_notify = "Your request for" . $item_name . "has been rejected. Reason: " . htmlspecialchars($reason);
                $stmt_notify = $conn->prepare("INSERT INTO notifications (person_id, message, type, related_id) VALUES (?, ?, 'reject', ?)");
                if (!$stmt_notify) throw new Exception("Prepare failed (notify reject): " . $conn->error);
                $stmt_notify->bind_param("isi", $person_id_applicant, $message_notify, $reservation_item_id);
                $stmt_notify->execute();
                $stmt_notify->close();
            }
            
            $conn->commit();
            echo json_encode(['success' => true, 'message' => 'Permintaan ditolak sepenuhnya kerana Kuantiti Diluluskan ialah 0.']);
            exit(); 

        } catch (Exception $e) {
            $conn->rollback();
            http_response_code(500);
            error_log("DB_TRANSACTION_ERROR (Full Reject): " . $e->getMessage());
            echo json_encode(['success' => false, 'message' => 'Database Transaction Failed semasa penolakan penuh: ' . $e->getMessage()]);
            exit();
        }
    }
    
    
    


    
    if (empty($reservation_item_id) || empty($selectedAssets) || $new_quantity <= 0) {
        http_response_code(400); 
        echo json_encode(['message' => 'Maklumat tidak lengkap (ID, Assets, atau Kuantiti).']); 
        exit();
    }
    
    
    if ($new_quantity < $original_qty && strlen($partial_reason) < 5) {
        http_response_code(400); 
        echo json_encode(['message' => 'Sebab penolakan sebahagian diperlukan jika kuantiti dikurangkan.']); 
        exit();
    }

    
    if (count($selectedAssets) !== $new_quantity) {
          http_response_code(400); 
          echo json_encode(['message' => 'Ralat Mismatch: Jumlah aset tidak sepadan dengan kuantiti yang diluluskan.']); 
          exit();
    }

    
    $conn->begin_transaction();
    try {
        
        $stmt = $conn->prepare("UPDATE reservation_items 
                               SET status = 'Approved', 
                                   approved_by = ?, 
                                   quantity = ?, 
                                   rejection_reason = ?, 
                                   approved_on = CURDATE()
                               WHERE id = ?");
        if (!$stmt) throw new Exception("Prepare failed (update item): " . $conn->error);
        
        
        if ($person_id === 0) throw new Exception("Person ID (Technician) is missing from session."); 

        $stmt->bind_param("iisi", $person_id, $new_quantity, $partial_reason, $reservation_item_id); 
        $stmt->execute();
        $stmt->close();

        
        $stmt_delete_ra = $conn->prepare("DELETE FROM reservation_assets WHERE reservation_item_id = ?");
        if (!$stmt_delete_ra) throw new Exception("Prepare failed (delete ra): " . $conn->error);
        $stmt_delete_ra->bind_param("i", $reservation_item_id);
        $stmt_delete_ra->execute();
        $stmt_delete_ra->close();
        
        
        $stmt_asset_insert = $conn->prepare("INSERT INTO reservation_assets (reservation_item_id, asset_id) VALUES (?, ?)");
        if (!$stmt_asset_insert) throw new Exception("Prepare failed (insert asset): " . $conn->error);

        $stmt_asset_update = $conn->prepare("UPDATE assets SET status = 'Reserved' WHERE asset_id = ?");
        if (!$stmt_asset_update) throw new Exception("Prepare failed (update asset): " . $conn->error);

        foreach ($selectedAssets as $asset_id) {
            $asset_id_int = (int)$asset_id;
            
            
            $stmt_asset_insert->bind_param("ii", $reservation_item_id, $asset_id_int);
            $stmt_asset_insert->execute();

            
            $stmt_asset_update->bind_param("i", $asset_id_int);
            $stmt_asset_update->execute();
        }
        $stmt_asset_insert->close();
        $stmt_asset_update->close();
        
        $conn->commit(); 
        
        
        
        
        
        
        $info = null;
        try {
            $stmt_info = $conn->prepare("
                SELECT 
                    r.person_id, p.email AS user_email, p.name AS user_name, i.item_name AS item_name, 
                    ri.reserve_date, ri.return_date,
                    GROUP_CONCAT(a.asset_code) AS asset_code 
                FROM reservation_items ri
                JOIN reservations r ON ri.reserve_id = r.reserve_id
                JOIN person p ON r.person_id = p.person_id
                JOIN item i ON ri.item_id = i.item_id
                LEFT JOIN reservation_assets ra ON ri.id = ra.reservation_item_id
                LEFT JOIN assets a ON ra.asset_id = a.asset_id
                WHERE ri.id = ?
                GROUP BY ri.id
            ");

            if (!$stmt_info) throw new Exception("Prepare failed (get email info): " . $conn->error);

            $stmt_info->bind_param("i", $reservation_item_id);
            $stmt_info->execute();
            $res_info = $stmt_info->get_result();
            
            if ($row = $res_info->fetch_assoc()) {
                $info = $row; 
            }
            $stmt_info->close();
        } catch (Exception $e) {
            error_log("EMAIL_INFO_FETCH_ERROR: " . $e->getMessage());
            $info = null; 
        }

        
        if ($info && isset($info['person_id'])) {
             $message_notify = "Your request for" . htmlspecialchars($info['item_name']) . "has passed and is ready to be taken. ";
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
        
        $partial_reason_for_email = '';
        if ($info && $original_qty > $new_quantity) {
            $partial_reason_for_email = "<strong>Quantity Reduced:</strong> Requested {$original_qty}, Approved {$new_quantity}. Reason: {$partial_reason}";
        }
        
        
        $email_sent = false;
        
        if ($info && defined('SMTP_USER') && defined('SMTP_PASS') && !empty($info['user_email'])) {
             $email_sent = sendNotificationEmail(
                 $info['user_email'], $info['user_name'], $info['item_name'], 
                 $info['asset_code'], $info['reserve_date'], $info['return_date'], 
                 SMTP_USER, SMTP_PASS, $partial_reason_for_email
             );
        }

        
        $message = "Request approved for {$new_quantity} unit(s)!";
        if ($original_qty > $new_quantity) {
            $message .= " (Original: {$original_qty}). Partial reason recorded.";
        }
        $message .= $email_sent ? ' Email notification sent.' : ' **Warning: Email failed to send.**';
        
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
        echo json_encode(['success' => false, 'message' => 'Sebab penolakan diperlukan (min 5 aksara).']); 
        exit();
    }
    
    
    $stmt = $conn->prepare("UPDATE reservation_items SET status = 'Rejected', rejection_reason = ? WHERE id = ?");
    
    if ($stmt === false) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Ralat sistem. Gagal menyediakan query UPDATE status.']);
        exit();
    }
    
    $stmt->bind_param("si", $reason, $reservation_item_id);
    
    if ($stmt->execute()) {
        
        
        
        
        $person_id = null;
        $item_name = 'item tidak diketahui';
        
        $stmt_user = $conn->prepare("SELECT r.person_id, i.item_name 
                                     FROM reservation_items ri
                                     JOIN reservations r ON ri.reserve_id = r.reserve_id
                                     JOIN item i ON ri.item_id = i.item_id
                                     WHERE ri.id = ?");

        if ($stmt_user === false) {
            
            error_log("Notification Prepare Error: " . $conn->error);
        } else {
            $stmt_user->bind_param("i", $reservation_item_id);
            $stmt_user->execute();
            $result_user = $stmt_user->get_result();
            
            if ($result_user->num_rows > 0) {
                $data = $result_user->fetch_assoc();
                $person_id = (int)$data['person_id'];
                $item_name = htmlspecialchars($data['item_name']);
            }
            $stmt_user->close();
        }
        
        
        if ($person_id !== null) {
            $message = "Permintaan anda untuk **" . $item_name . "** telah DITOLAK. Sebab: " . htmlspecialchars($reason);
            
            $stmt_notify = $conn->prepare("INSERT INTO notifications (person_id, message, type, related_id) VALUES (?, ?, 'reject', ?)");
            
            if ($stmt_notify === false) {
                 error_log("Notification INSERT Prepare Error: " . $conn->error);
            } else {
                $stmt_notify->bind_param("isi", $person_id, $message, $reservation_item_id);
                $stmt_notify->execute();
                $stmt_notify->close();
            }
        }
        
        
        echo json_encode(['success' => true, 'message' => 'Permintaan berjaya ditolak dan pengguna telah dimaklumkan.']); 

    } else {
        
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Gagal menolak permintaan. DB Execute Error.']);
    }
    
    $stmt->close();
    break;
	
case 'checkout':
    
    $reservation_item_id = isset($_POST['reservation_item_id']) ? (int)$_POST['reservation_item_id'] : 0;
    $conn->begin_transaction();
    try {
        
        $stmt = $conn->prepare("UPDATE reservation_items SET status = 'Checked Out' WHERE id = ?");
        $stmt->bind_param("i", $reservation_item_id);
        $stmt->execute();
        $stmt->close();

        
        $stmt_assets = $conn->prepare("UPDATE assets SET status = 'Checked Out' WHERE asset_id IN (SELECT asset_id FROM reservation_assets WHERE reservation_item_id = ?)");
        $stmt_assets->bind_param("i", $reservation_item_id);
        $stmt_assets->execute();
        $stmt_assets->close();

        $conn->commit();
        echo json_encode(['message' => 'Item successfully checked out.']);
    } catch (Exception $e) {
        $conn->rollback();
        http_response_code(500);
        error_log("DB_TRANSACTION_ERROR (Checkout): " . $e->getMessage());
        echo json_encode(['message' => 'Checkout failed: ' . $e->getMessage()]);
    }
    break;

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
    
    $reservation_item_id = isset($_POST['reservation_item_id']) ? (int)$_POST['reservation_item_id'] : 0;
    $asset_conditions_json = isset($_POST['asset_conditions']) ? $_POST['asset_conditions'] : '[]';
    $asset_conditions = json_decode($asset_conditions_json, true);

    if (empty($reservation_item_id) || empty($asset_conditions)) {
        http_response_code(400); 
        echo json_encode(['message' => 'Missing required information (ID or Asset Conditions).']); 
        exit();
    }

    $conn->begin_transaction();
    try {
        $damaged_count = 0;
        $checked_in_count = 0;
        
        $stmt_asset_update = $conn->prepare("UPDATE assets SET status = ?, last_return_date = CURDATE() WHERE asset_id = ?");
        if (!$stmt_asset_update) throw new Exception("Prepare failed (update assets): " . $conn->error);

        foreach ($asset_conditions as $asset) {
            $asset_id = (int)$asset['asset_id'];
            $condition = $asset['condition']; 
            $remarks = $asset['remarks'];
            
            $new_asset_status = 'Available';
            
            if ($condition === 'Damaged/Incomplete') {
                $new_asset_status = 'Maintenance';
                $damaged_count++;
            } elseif ($condition === 'Not_Returned_Yet') { 
                continue; 
            }
            
            $stmt_asset_update->bind_param("si", $new_asset_status, $asset_id);
            if (!$stmt_asset_update->execute()) {
                throw new Exception("Asset update failed for asset_id {$asset_id}: " . $stmt_asset_update->error);
            }
            $checked_in_count++;
        }
        
        $stmt_asset_update->close();

        
        $stmt_check_remaining = $conn->prepare("
            SELECT COUNT(a.asset_id) AS remaining_count
            FROM reservation_assets ra
            JOIN assets a ON ra.asset_id = a.asset_id
            WHERE ra.reservation_item_id = ? AND a.status = 'Checked Out' 
        ");
        $stmt_check_remaining->bind_param("i", $reservation_item_id);
        $stmt_check_remaining->execute();
        $remaining_row = $stmt_check_remaining->get_result()->fetch_assoc();
        $remaining_count = (int)$remaining_row['remaining_count'];
        $stmt_check_remaining->close();


        
        $final_item_status = ($remaining_count > 0) ? 'Checked Out' : 'Returned';
        
        $final_condition = ($damaged_count > 0) ? "{$damaged_count} asset(s) Damaged" : "Good";
        $final_remarks = "Checked in {$checked_in_count} asset(s). Final status: {$final_item_status}.";
        
        $stmt_item = $conn->prepare("UPDATE reservation_items 
                                    SET status = ?, 
                                        return_condition = ?, 
                                        return_remarks = ?, 
                                        return_date = CURDATE() 
                                    WHERE id = ?");
        if (!$stmt_item) throw new Exception("Prepare failed (update item): " . $conn->error);
        
        $stmt_item->bind_param("sssi", $final_item_status, $final_condition, $final_remarks, $reservation_item_id);
        if (!$stmt_item->execute()) throw new Exception("Execute failed (update item): " . $stmt_item->error);
        
        $stmt_item->close();

        $conn->commit();
        echo json_encode(['message' => "Check-in successful. {$checked_in_count} asset(s) processed. Reservation status updated to '{$final_item_status}'. {$damaged_count} assets for maintenance."]);

    } catch (Exception $e) {
        $conn->rollback();
        http_response_code(500);
        error_log("Multi Check-in Error for reservation_item_id {$reservation_item_id}: " . $e->getMessage());
        echo json_encode(['message' => 'Check-in failed: ' . $e->getMessage()]);
    }
    break; 
}
?>