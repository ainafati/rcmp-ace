<?php
session_start();
include '../config.php';

// Pastikan teknikal log masuk
if (!isset($_SESSION['tech_id'])) {
    header('Content-Type: application/json');
    http_response_code(403);
    echo json_encode(['message' => 'Access Denied. Please log in again.']);
    exit();
}
$tech_id = (int)$_SESSION['tech_id'];

// Tentukan 'action' berdasarkan POST atau GET (untuk 'get_assets_for_checkin')
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
        $reservation_item_id = (int)$_POST['reservation_item_id'];
        $selectedAssets = $_POST['selectedAssets']; // Ini adalah array

        if (empty($reservation_item_id) || empty($selectedAssets)) {
            http_response_code(400); echo json_encode(['message' => 'Missing required information.']); exit();
        }

        $conn->begin_transaction();
        try {
            // 1. Kemas kini status tempahan
            $stmt = $conn->prepare("UPDATE reservation_items SET status = 'Approved', approved_by = ? WHERE id = ?");
            $stmt->bind_param("ii", $tech_id, $reservation_item_id);
            $stmt->execute();
            $stmt->close();

            // 2. Pautkan aset yang dipilih
            $stmt_link = $conn->prepare("INSERT INTO reservation_assets (reservation_item_id, asset_id) VALUES (?, ?)");
            foreach ($selectedAssets as $asset_id) {
                $stmt_link->bind_param("ii", $reservation_item_id, $asset_id);
                $stmt_link->execute();
            }
            $stmt_link->close();

            // 3. Kemas kini status aset kepada 'Reserved'
            $asset_placeholders = implode(',', array_fill(0, count($selectedAssets), '?'));
            $stmt_update = $conn->prepare("UPDATE assets SET status = 'Reserved' WHERE asset_id IN ($asset_placeholders)");
            $types = str_repeat('i', count($selectedAssets));
            $stmt_update->bind_param($types, ...$selectedAssets);
            $stmt_update->execute();
            $stmt_update->close();

            $conn->commit();
            echo json_encode(['message' => 'Request approved successfully!']);
        } catch (Exception $e) {
            $conn->rollback();
            http_response_code(500);
            echo json_encode(['message' => 'Database error during approval: ' . $e->getMessage()]);
        }
        break;

    case 'reject':
        $reservation_item_id = (int)$_POST['reservation_item_id'];
        $reason = trim($_POST['reason']);
        $stmt = $conn->prepare("UPDATE reservation_items SET status = 'Rejected', rejection_reason = ? WHERE id = ?");
        $stmt->bind_param("si", $reason, $reservation_item_id);
        if ($stmt->execute()) { echo json_encode(['message' => 'Request has been rejected.']); }
        $stmt->close();
        break;

    case 'checkout':
        $reservation_item_id = (int)$_POST['reservation_item_id'];
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
            echo json_encode(['message' => 'Checkout failed: ' . $e->getMessage()]);
        }
        break;

    // --- KES BAHARU UNTUK DAPATKAN ASET SEMASA CHECK-IN ---
    case 'get_assets_for_checkin':
        if (!isset($_GET['reservation_item_id'])) {
            http_response_code(400); echo json_encode(['message' => 'Missing ID.']); exit();
        }
        $reservation_item_id = (int)$_GET['reservation_item_id'];
        
        // Cari semua aset yang dipautkan dengan item tempahan ini DAN masih berstatus 'Checked Out'
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
        
        echo json_encode($result); // Kembalikan senarai aset sebagai JSON
        break;

    // --- KES BAHARU UNTUK PROSES CHECK-IN PELBAGAI ASET ---
    case 'checkin_multi':
        // Data dihantar sebagai JSON string dari AJAX
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
            
            // Sediakan penyata SQL di luar gelung
            $stmt_asset_update = $conn->prepare("UPDATE assets SET status = ?, last_return_date = CURDATE() WHERE asset_id = ?");
            if (!$stmt_asset_update) throw new Exception("Prepare failed (update assets): " . $conn->error);

            foreach ($asset_conditions as $asset) {
                $asset_id = (int)$asset['asset_id'];
                $condition = $asset['condition']; // 'Good' or 'Damaged/Incomplete'
                $remarks = $asset['remarks'];
                
                $new_asset_status = 'Available';
                if ($condition === 'Damaged/Incomplete') {
                    $new_asset_status = 'Maintenance';
                    $damaged_count++;
                }
                
                // 1. Kemas kini status dalam jadual 'assets' utama
                $stmt_asset_update->bind_param("si", $new_asset_status, $asset_id);
                if (!$stmt_asset_update->execute()) {
                    throw new Exception("Asset update failed for asset_id {$asset_id}: " . $stmt_asset_update->error);
                }
            }
            
            $stmt_asset_update->close();

            // 3. Setelah semua aset dikemas kini, tandakan 'reservation_items' utama sebagai 'Returned'
            $final_condition = ($damaged_count > 0) ? "{$damaged_count} asset(s) Damaged" : "Good";
            $final_remarks = "Checked in " . count($asset_conditions) . " asset(s). See asset status for details.";
            
            $stmt_item = $conn->prepare("UPDATE reservation_items SET status = 'Returned', return_condition = ?, return_remarks = ?, return_date = CURDATE() WHERE id = ? AND status = 'Checked Out'");
            if (!$stmt_item) throw new Exception("Prepare failed (update item): " . $conn->error);
            
            $stmt_item->bind_param("ssi", $final_condition, $final_remarks, $reservation_item_id);
            if (!$stmt_item->execute()) throw new Exception("Execute failed (update item): " . $stmt_item->error);
            if ($stmt_item->affected_rows === 0) throw new Exception("Reservation item not found or not 'Checked Out'.");
            
            $stmt_item->close();

            $conn->commit();
            echo json_encode(['message' => "Check-in successful. {$damaged_count} asset(s) marked for 'Maintenance'."]);

        } catch (Exception $e) {
            $conn->rollback();
            http_response_code(500);
            error_log("Multi Check-in Error for reservation_item_id {$reservation_item_id}: " . $e->getMessage());
            echo json_encode(['message' => 'Check-in failed: ' . $e->getMessage()]);
        }
        break; // End case 'checkin_multi'
}
?>