<?php
session_start();
include 'config.php';
header('Content-Type: application/json');

function send_error($message) {
    echo json_encode(['status' => 'error', 'message' => $message]);
    exit();
}

if (!isset($_SESSION['user_id'])) { send_error('Sesi tamat.'); }
$user_id = (int)$_SESSION['user_id'];

if (!isset($_POST['all_items']) || empty($_POST['all_items'])) { send_error('Tiada item dihantar.'); }
$items_to_reserve = json_decode($_POST['all_items'], true);

// ✅ Ambil priority dari POST (kalau takde, default 3)
$priority = isset($_POST['program_type']) ? (int)$_POST['program_type'] : 3;

$conn->begin_transaction();

try {
    // 1. Cipta satu rekod tempahan utama
    $stmt_res = $conn->prepare("INSERT INTO reservations (user_id, created_at, priority) VALUES (?, NOW(), ?)");
    $stmt_res->bind_param("ii", $user_id, $priority);
    $stmt_res->execute();
    $reserve_id = $conn->insert_id;
    $stmt_res->close();

    // 2. Loop dan simpan setiap item yang diminta
    foreach ($items_to_reserve as $item_data) {
        $item_name = $item_data['item_name'];
        
        $stmt_find_id = $conn->prepare("SELECT item_id FROM item WHERE item_name = ? LIMIT 1");
        $stmt_find_id->bind_param("s", $item_name);
        $stmt_find_id->execute();
        $result_id = $stmt_find_id->get_result()->fetch_assoc();
        $stmt_find_id->close();
        
        if (!$result_id) { throw new Exception("Item '" . htmlspecialchars($item_name) . "' tidak wujud."); }
        $item_id = $result_id['item_id'];

        $stmt_item = $conn->prepare(
            "INSERT INTO reservation_items (reserve_id, item_id, quantity, reserve_date, return_date, reason, status) VALUES (?, ?, ?, ?, ?, ?, 'Pending')"
        );
        $stmt_item->bind_param("iiisss", $reserve_id, $item_id, $item_data['quantity'], $item_data['reserve_date'], $item_data['return_date'], $item_data['reason']);
        $stmt_item->execute();
        $stmt_item->close();
    }

    $conn->commit();
    echo json_encode(['status' => 'success', 'message' => 'Tempahan berjaya dihantar!']);

} catch (Exception $e) {
    $conn->rollback();
    send_error($e->getMessage());
}

$conn->close();
?>