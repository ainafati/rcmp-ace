<?php
session_start();
include 'config.php';

// Tetapkan header untuk respons JSON
header('Content-Type: application/json');

// Pastikan request method adalah POST
if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    echo json_encode(['available_count' => 0, 'message' => 'Invalid request method.']);
    exit;
}

// PEMBETULAN UNTUK PHP LAMA: Gantikan ?? dengan isset() dan ternary operator
$item_name = isset($_POST['item_name']) ? trim($_POST['item_name']) : '';
$reserve_date = isset($_POST['reserve_date']) ? $_POST['reserve_date'] : '';
$return_date = isset($_POST['return_date']) ? $_POST['return_date'] : '';
// END PEMBETULAN

if (empty($item_name) || empty($reserve_date) || empty($return_date)) {
    echo json_encode(['available_count' => 0, 'message' => 'Item or date is missing.']);
    exit;
}

// 1. Cari jumlah STOK KESELURUHAN (TOTAL_STOCK)
$sql_total_stock = "
    SELECT 
        SUM(i.stock_quantity) AS total_stock
    FROM item i
    WHERE i.item_name = ?
";
$stmt_total = $conn->prepare($sql_total_stock);
$stmt_total->bind_param("s", $item_name);
$stmt_total->execute();
$result_total = $stmt_total->get_result();

// PEMBETULAN UNTUK PHP LAMA: Gantikan ??
$total_stock_data = $result_total->fetch_assoc();
$total_stock = isset($total_stock_data['total_stock']) ? $total_stock_data['total_stock'] : 0;
$stmt_total->close();
// END PEMBETULAN

if ($total_stock == 0) {
    echo json_encode(['available_count' => 0, 'message' => 'Item is currently out of stock (Total Stock is 0).']);
    exit;
}

// 2. Kira jumlah item yang SUDAH DITEMPAH (RESERVED_QUANTITY)
$sql_reserved = "
    SELECT 
        SUM(ri.quantity) AS reserved_quantity
    FROM reservation_item ri
    JOIN reservation r ON ri.reservation_id = r.reservation_id
    WHERE 
        ri.item_name = ?
        AND r.status IN ('Pending', 'Approved') 
        AND NOT (
            r.return_date <= ? OR r.reserve_date >= ?
        )
";
$stmt_reserved = $conn->prepare($sql_reserved);
$stmt_reserved->bind_param("sss", $item_name, $reserve_date, $return_date);
$stmt_reserved->execute();
$result_reserved = $stmt_reserved->get_result();

// PEMBETULAN UNTUK PHP LAMA: Gantikan ??
$reserved_quantity_data = $result_reserved->fetch_assoc();
$reserved_quantity = isset($reserved_quantity_data['reserved_quantity']) ? $reserved_quantity_data['reserved_quantity'] : 0;
$stmt_reserved->close();
// END PEMBETULAN

// 3. Kira Stok Tersedia
$available_count = $total_stock - $reserved_quantity;

// Sediakan mesej respons
if ($available_count > 0) {
    $response = [
        'available_count' => (int)$available_count,
        'message' => 'Available for booking.'
    ];
} else {
    $response = [
        'available_count' => 0,
        'message' => 'Fully reserved for the selected dates.'
    ];
}

echo json_encode($response);
$conn->close();
?>