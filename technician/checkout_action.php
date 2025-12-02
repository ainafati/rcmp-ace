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
    
    $technician_id = isset($_SESSION['person_id']) ? (int)$_SESSION['person_id'] : 0; 
    
    // --- START: Dapatkan Kuantiti Asal dan RESERVE_ID ---
    $original_qty = 0;
    $reserve_id = 0; 
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
        echo json_encode(['message' => 'Ralat sistem semasa mengambil kuantiti asal/Reserve ID.']);
        exit();
    }
    // --- END: Dapatkan Kuantiti Asal dan RESERVE_ID ---
    
    
    // --- LOGIK PENOLAKAN PENUH (Jika Kuantiti Diluluskan = 0) ---
    if ($new_quantity == 0) {
        
        if (strlen($partial_reason) < 5) {
            http_response_code(400);
            echo json_encode(['message' => 'Sebab penolakan penuh diperlukan jika Kuantiti Diluluskan ialah 0.']);
            exit();
        }

        $reason = $partial_reason; 
        
        $user_info = null;
        try {
            $stmt_info = $conn->prepare("SELECT r.person_id, i.item_name, p.email, p.name AS user_name, r.reserve_id  
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
            echo json_encode(['success' => false, 'message' => 'Gagal mengambil maklumat pengguna untuk penolakan penuh.']);
            exit();
        }


        $conn->begin_transaction();
        try {
            
            // KEMASKINI STATUS KEPADA REJECTED
            $stmt_r = $conn->prepare("UPDATE reservation_items SET status = 'Rejected', rejection_reason = ? WHERE id = ?");
            if (!$stmt_r) throw new Exception("Prepare failed (reject update): " . $conn->error);
            $stmt_r->bind_param("si", $reason, $reservation_item_id);
            $stmt_r->execute();
            $stmt_r->close();

            // NOTIFIKASI DALAMAN 
            $message_notify = "Your request for " . $item_name . " has been rejected. Reason: " . htmlspecialchars($reason);
            $stmt_notify = $conn->prepare("INSERT INTO notifications (person_id, message, type, related_id) VALUES (?, ?, 'reject', ?)");
            if (!$stmt_notify) throw new Exception("Prepare failed (notify reject): " . $conn->error);
            $stmt_notify->bind_param("isi", $person_id_applicant, $message_notify, $reservation_item_id);
            $stmt_notify->execute();
            $stmt_notify->close();
            

            $conn->commit();
            $response_message = 'Permintaan ditolak sepenuhnya kerana Kuantiti Diluluskan ialah 0.';
            
            // --- LOGIK E-MEL BERKUMPUL SELEPAS TRANSAKSI BERJAYA ---
            
            $stmt_check_pending = $conn->prepare("SELECT COUNT(id) FROM reservation_items WHERE reserve_id = ? AND status = 'Pending'");
            $stmt_check_pending->bind_param("i", $reserve_id);
            $stmt_check_pending->execute();
            $pending_count = 0;
            $stmt_check_pending->bind_result($pending_count);
            $stmt_check_pending->fetch();
            $stmt_check_pending->close();

            $email_sent = false;

            if ($pending_count === 0) {
                
                $reservation_items_list = fetch_reservation_items_by_id($conn, $reserve_id);
                
                if (!empty($reservation_items_list) && defined('SMTP_USER') && defined('SMTP_PASS')) {
                    
                    $email_sent = sendGroupedRejectionEmail(
                        $user_email, 
                        $user_name, 
                        $reserve_id, 
                        $reservation_items_list, 
                        SMTP_USER, 
                        SMTP_PASS
                    );
                    
                    // TIDAK PERLU UPDATE STATUS JADUAL 'reservations' UTAMA
                    
                    $response_message .= ' **Grouped Email notification sent.**';

                } else {
                    $response_message .= ' **Warning: Grouped Email failed to send (Data/SMTP error).**';
                }
            } else {
                $response_message .= " Individual item rejected. **Waiting for {$pending_count} remaining item(s) in this booking to be processed.**";
            }
            // --- END: LOGIK E-MEL BERKUMPUL ---

            echo json_encode(['success' => true, 'message' => $response_message]);
            exit(); 

        } catch (Exception $e) {
            $conn->rollback();
            http_response_code(500);
            error_log("DB_TRANSACTION_ERROR (Full Reject - Approve Case): " . $e->getMessage());
            echo json_encode(['success' => false, 'message' => 'Database Transaction Failed semasa penolakan penuh: ' . $e->getMessage()]);
            exit();
        }
    }
    
    // --- LOGIK KELULUSAN / PENOLAKAN SEBAHAGIAN ---
    
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
        
        // 1. KEMASKINI ITEM KEPADA APPROVED
        $stmt = $conn->prepare("UPDATE reservation_items 
                                    SET status = 'Approved', 
                                         approved_by = ?, 
                                         quantity = ?, 
                                         rejection_reason = ?, 
                                         approved_on = CURDATE()
                                    WHERE id = ?");
        if (!$stmt) throw new Exception("Prepare failed (update item): " . $conn->error);
        
        if ($technician_id === 0) throw new Exception("Person ID (Technician) is missing from session."); 

        $stmt->bind_param("iisi", $technician_id, $new_quantity, $partial_reason, $reservation_item_id); 
        $stmt->execute();
        $stmt->close();

        // 2. KEMASKINI/SISIPAN reservation_assets dan assets status
        
        $stmt_delete_ra = $conn->prepare("DELETE FROM reservation_assets WHERE reservation_item_id = ?");
        if (!$stmt_delete_ra) throw new Exception("Prepare failed (delete ra): " . $conn->error);
        $stmt_delete_ra->bind_param("i", $reservation_item_id);
        $stmt_delete_ra->execute();
        $stmt_delete_ra->close();
        
        
        $stmt_asset_insert = $conn->prepare("INSERT INTO reservation_assets (reservation_item_id, asset_id) VALUES (?, ?)");
        if (!$stmt_asset_insert) throw new Exception("Prepare failed (insert asset): " . $conn->error);

        // [PENTING] Menggunakan backtick (`) pada `status` untuk mengelakkan ralat 'Unknown column'
        $stmt_asset_update = $conn->prepare("UPDATE assets SET `status` = 'Reserved' WHERE asset_id = ?"); 
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
        
        // --- START: LOGIK E-MEL BERKUMPUL ---
        
        $stmt_check_pending = $conn->prepare("SELECT COUNT(id) FROM reservation_items WHERE reserve_id = ? AND status = 'Pending'");
        $stmt_check_pending->bind_param("i", $reserve_id);
        $stmt_check_pending->execute();
        $pending_count = 0;
        $stmt_check_pending->bind_result($pending_count);
        $stmt_check_pending->fetch();
        $stmt_check_pending->close();


        $email_sent = false;
        $message = "Request approved for {$new_quantity} unit(s)!";
        
        if ($pending_count === 0) {
            
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
                
                // [TIDAK PERLU UPDATE reservations.status DI SINI]

                $message .= ' **Grouped Email notification sent.**';

            } else {
                 $message .= ' **Warning: Grouped Email failed to send (Data/SMTP error).**';
            }
        } else {
            $message .= " Individual item approved. **Waiting for {$pending_count} remaining item(s) in this booking to be processed.**";
        }
        // --- END: LOGIK E-MEL BERKUMPUL ---

        // 5. KOD NOTIFIKASI DALAMAN 
        $info = null;
        try {
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
             $message_notify = "Your request for " . htmlspecialchars($info['item_name']) . " has passed and is ready to be taken. ";
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
        // 1. Dapatkan semua reservation_item_id, item_id, dan kuantiti yang Pending di bawah reserve_id ini.
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
            echo json_encode(['success' => true, 'message' => 'Tiada item Pending untuk kelulusan di bawah ID Tempahan ini.']);
            exit();
        }
        
        // Loop melalui setiap item yang Pending
        foreach ($pending_items as $item) {
            $ri_id = (int)$item['reservation_item_id'];
            $item_id = (int)$item['item_id'];
            $quantity = (int)$item['quantity'];
            
            // 2. Dapatkan Aset yang Tersedia untuk item_id ini (Status 'Available')
            // Hadkan kepada kuantiti yang diperlukan
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

            if (count($asset_ids_to_assign) < $quantity) {
                // Jika aset tidak mencukupi, anggap item ini REJECTED
                $reason = "Rejected Automatically: Only " . count($asset_ids_to_assign) . " asset(s) available for the requested quantity of {$quantity}.";
                 
                $stmt_reject = $conn->prepare("UPDATE reservation_items SET status = 'Rejected', rejection_reason = ? WHERE id = ?");
                $stmt_reject->bind_param("si", $reason, $ri_id);
                $stmt_reject->execute();
                $stmt_reject->close();
                
                // TIDAK PERLU KEMASKINI ASSET STATUS (kerana ia kekal 'Available')
                
                $total_approved_items += 0; // Tambah 0
                
            } else {
                // Jika aset mencukupi, LULUSKAN ITEM
                
                // a. Kemaskini reservation_items status
                $stmt_update_item = $conn->prepare("UPDATE reservation_items 
                                                    SET status = 'Approved', 
                                                        approved_by = ?, 
                                                        approved_on = CURDATE(),
                                                        quantity = ? 
                                                    WHERE id = ?"); // Set quantity to requested quantity
                $stmt_update_item->bind_param("iii", $technician_id, $quantity, $ri_id);
                $stmt_update_item->execute();
                $stmt_update_item->close();

                // b. Sisip dan Kemaskini Asset Status
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
        
        $conn->commit();
        $message = "Berjaya memproses {$total_approved_items} item yang diluluskan untuk ID Tempahan {$reserve_id}.";

        // 3. LOGIK E-MEL BERKUMPUL
        // Periksa semua item telah diproses (Pending = 0)
        $stmt_check_pending = $conn->prepare("SELECT COUNT(id) FROM reservation_items WHERE reserve_id = ? AND status = 'Pending'");
        $stmt_check_pending->bind_param("i", $reserve_id);
        $stmt_check_pending->execute();
        $pending_count = 0;
        $stmt_check_pending->bind_result($pending_count);
        $stmt_check_pending->fetch();
        $stmt_check_pending->close();
        
        if ($pending_count === 0) {
            $message .= " Semua item telah diproses. E-mel notifikasi dihantar.";
            // Panggil fungsi e-mel (dengan asumsi fetch_reservation_items_by_id kini menyertakan assigned_assets)
            $reservation_items_list = fetch_reservation_items_by_id($conn, $reserve_id);
            if (!empty($reservation_items_list)) {
                $user_email = $reservation_items_list[0]['user_email'];
                $user_name = $reservation_items_list[0]['user_name'];
                sendGroupedNotificationEmail($user_email, $user_name, $reserve_id, $reservation_items_list, SMTP_USER, SMTP_PASS);
            }
        } else {
             $message .= " Beberapa item ditolak secara automatik kerana kekurangan aset. Status keseluruhan masih 'Pending' untuk item yang belum diproses.";
        }

        echo json_encode(['success' => true, 'message' => $message]);
        
    } catch (Exception $e) {
        $conn->rollback();
        http_response_code(500);
        error_log("DB_TRANSACTION_ERROR (Approve All): " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Database Transaction Failed semasa kelulusan pukal: ' . $e->getMessage()]);
    }
    break;

case 'checkout_all_items':
    // 1. Tetapkan ID Juruteknik
    $reserve_id = isset($_POST['reserve_id']) ? (int)$_POST['reserve_id'] : 0;
    $technician_id = isset($_SESSION['person_id']) ? (int)$_SESSION['person_id'] : 0;
    
    if ($reserve_id === 0) {
        http_response_code(400); 
        echo json_encode(['success' => false, 'message' => 'Missing Reservation ID for bulk action.']); 
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
        // 1. Dapatkan SEMUA reservation_item_id yang Approved di bawah Tempahan ini
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

        // 2. Lakukan Check Out untuk setiap item
        $asset_log_details = [];
        foreach ($items_to_checkout as $item) {
            $reservation_item_id = (int)$item['id'];
            $item_name = htmlspecialchars($item['item_name']);
            $asset_ids_str = $item['asset_ids'];
            
            if (empty($asset_ids_str)) continue; // Langkau item tanpa aset
            
            $asset_ids = explode(',', $asset_ids_str);
            
            // a) Update reservation_items 
            $stmt_item_update = $conn->prepare("UPDATE reservation_items SET status = 'Checked Out', reserve_date = CURDATE() WHERE id = ?");
            $stmt_item_update->bind_param("i", $reservation_item_id);
            $stmt_item_update->execute();
            $stmt_item_update->close();
            $total_items_updated++;

            // b) Update assets status ke 'Checked Out'
            $asset_placeholders = implode(',', array_fill(0, count($asset_ids), '?'));
            $stmt_asset_update = $conn->prepare("UPDATE assets SET `status` = 'Checked Out' WHERE asset_id IN ($asset_placeholders)");
            
            $types = str_repeat('i', count($asset_ids));
            $stmt_asset_update->bind_param($types, ...$asset_ids);
            $stmt_asset_update->execute();
            $total_assets_updated += $stmt_asset_update->affected_rows;
            $stmt_asset_update->close();
            
            // Simpan log butiran untuk laporan akhir
            $asset_log_details[] = "Item: {$item_name} (IDs: {$asset_ids_str})";

            // LOGGING AKTIVITI PER ITEM (PENTING)
            $log_desc = "Bulk Checked Out: {$item_name} for Reserve ID {$reserve_id}. Asset IDs: {$asset_ids_str}.";
            logActivity($conn, $technician_id, 'BULK_CHECKOUT', $log_desc, $reservation_item_id);
        }

        // 3. Commit dan Pulangkan Kejayaan
        $conn->commit();
        echo json_encode([
            'success' => true,
            'message' => "Bulk Check-Out successful. {$total_items_updated} items updated, {$total_assets_updated} asset(s) status updated to 'Checked Out'."
        ]);

    } catch (Exception $e) {
        $conn->rollback();
        http_response_code(500);
        error_log("DB_TRANSACTION_ERROR (Checkout All): " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Bulk Check-out failed: ' . $e->getMessage()]);
    }
    break;

// --------------------------------------------------------------------------------------------------

case 'get_assets_for_checkout':
    // TIDAK PERLU LOG, hanya operasi GET
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

// --------------------------------------------------------------------------------------------------

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
        
        // Dapatkan Item Name untuk Log
        $stmt_item_info = $conn->prepare("SELECT i.item_name FROM reservation_items ri JOIN item i ON ri.item_id = i.item_id WHERE ri.id = ?");
        $stmt_item_info->bind_param("i", $reservation_item_id);
        $stmt_item_info->execute();
        $item_info = $stmt_item_info->get_result()->fetch_assoc();
        $stmt_item_info->close();
        $item_name = $item_info ? htmlspecialchars($item_info['item_name']) : 'N/A';
        
        // 1. Update reservation_items status
        $stmt_item = $conn->prepare("UPDATE reservation_items SET status = 'Checked Out', reserve_date = CURDATE() WHERE id = ? AND status = 'Approved'");
        if (!$stmt_item) throw new Exception("Prepare failed (update item status): " . $conn->error);
        $stmt_item->bind_param("i", $reservation_item_id);
        $stmt_item->execute();
        $stmt_item->close();

        
        // 2. Update assets status ke 'Checked Out'
        $asset_placeholders = implode(',', array_fill(0, count($asset_ids), '?'));
        $stmt_asset_update = $conn->prepare("UPDATE assets SET `status` = 'Checked Out' WHERE asset_id IN ($asset_placeholders)");
        if (!$stmt_asset_update) throw new Exception("Prepare failed (update assets status): " . $conn->error);

        $types = str_repeat('i', count($asset_ids));
        $stmt_asset_update->bind_param($types, ...$asset_ids);
        $stmt_asset_update->execute();
        $stmt_asset_update->close();

        // LOGGING AKTIVITI
        $asset_ids_str = implode(',', $asset_ids);
        $log_desc = "Checked Out Item: {$item_name}. Assigned Assets: {$asset_ids_str}.";
        logActivity($conn, $technician_id, 'CHECKOUT', $log_desc, $reservation_item_id);

        $conn->commit();
        echo json_encode(['message' => "Item successfully checked out. " . count($asset_ids) . " asset(s) status updated."]);
    } catch (Exception $e) {
        $conn->rollback();
        http_response_code(500);
        error_log("DB_TRANSACTION_ERROR (Checkout Multi): " . $e->getMessage());
        echo json_encode(['message' => 'Check-out failed: ' . $e->getMessage()]);
    }
    break;
    
// --------------------------------------------------------------------------------------------------

case 'get_assets_for_checkin':
    // TIDAK PERLU LOG, hanya operasi GET
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

// --------------------------------------------------------------------------------------------------
	
case 'checkin_multi':
    $reservation_item_id = isset($_POST['reservation_item_id']) ? (int)$_POST['reservation_item_id'] : 0;
    $asset_conditions_json = isset($_POST['asset_conditions']) ? $_POST['asset_conditions'] : '[]';
    $technician_id = isset($_SESSION['person_id']) ? (int)$_SESSION['person_id'] : 0;
    
    $asset_conditions = json_decode($asset_conditions_json, true);

    if (empty($reservation_item_id) || empty($asset_conditions)) {
        http_response_code(400); 
        echo json_encode(['message' => 'Missing required information (ID or Asset Conditions).']); 
        exit();
    }
    if ($technician_id === 0) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Unauthorized: Technician ID not found in session.']);
        exit();
    }

    $conn->begin_transaction();
    try {
        $damaged_count = 0;
        $checked_in_count = 0;
        $checked_in_asset_ids = [];
        $damaged_asset_codes = [];
        $available_asset_codes = [];
        
        // 1. Dapatkan Item Name untuk Log
        $stmt_item_info = $conn->prepare("SELECT i.item_name FROM reservation_items ri JOIN item i ON ri.item_id = i.item_id WHERE ri.id = ?");
        $stmt_item_info->bind_param("i", $reservation_item_id);
        $stmt_item_info->execute();
        $item_info = $stmt_item_info->get_result()->fetch_assoc();
        $stmt_item_info->close();
        $item_name = $item_info ? htmlspecialchars($item_info['item_name']) : 'N/A';
        
        // 2. Kemaskini status aset
        $stmt_asset_update = $conn->prepare("UPDATE assets SET `status` = ?, last_return_date = CURDATE() WHERE asset_id = ?");
        $stmt_get_asset_code = $conn->prepare("SELECT asset_code FROM assets WHERE asset_id = ?"); // Dapatkan kod aset

        foreach ($asset_conditions as $asset) {
            $asset_id = (int)$asset['asset_id'];
            $condition = $asset['condition']; 
            
            $new_asset_status = 'Available';
            
            if ($condition === 'Damaged/Incomplete') {
                $new_asset_status = 'Maintenance';
                $damaged_count++;
                
                // Dapatkan asset code untuk log
                $stmt_get_asset_code->bind_param("i", $asset_id);
                $stmt_get_asset_code->execute();
                $code_row = $stmt_get_asset_code->get_result()->fetch_assoc();
                if ($code_row) $damaged_asset_codes[] = $code_row['asset_code'];
                
            } elseif ($condition === 'Available') {
                
                // Dapatkan asset code untuk log
                $stmt_get_asset_code->bind_param("i", $asset_id);
                $stmt_get_asset_code->execute();
                $code_row = $stmt_get_asset_code->get_result()->fetch_assoc();
                if ($code_row) $available_asset_codes[] = $code_row['asset_code'];
                
            } elseif ($condition === 'Not_Returned_Yet') { 
                continue; 
            }
            
            $stmt_asset_update->bind_param("si", $new_asset_status, $asset_id);
            if (!$stmt_asset_update->execute()) {
                throw new Exception("Asset update failed for asset_id {$asset_id}: " . $stmt_asset_update->error);
            }
            $checked_in_count++;
            $checked_in_asset_ids[] = $asset_id;
        }
        
        $stmt_asset_update->close();
        $stmt_get_asset_code->close();

        
        // 3. Periksa baki aset yang masih 'Checked Out'
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


        
        // 4. Kemaskini status reservation_item
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

        // 5. LOGGING AKTIVITI
        $log_desc = "Checked In Item: {$item_name}. Returned: " . implode(', ', $available_asset_codes) . ". Maintenance: " . implode(', ', $damaged_asset_codes) . ". Item Status: {$final_item_status}.";
        logActivity($conn, $technician_id, 'CHECKIN', $log_desc, $reservation_item_id);


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