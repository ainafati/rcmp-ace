<?php
session_start();
include 'config.php';


header('Content-Type: application/json');


if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    echo json_encode(['available_count' => 0, 'message' => 'Invalid request method.']);
    exit;
}


$item_name = isset($_POST['item_name']) ? trim($_POST['item_name']) : '';
$reserve_date = isset($_POST['reserve_date']) ? $_POST['reserve_date'] : '';
$return_date = isset($_POST['return_date']) ? $_POST['return_date'] : '';


if (empty($item_name) || empty($reserve_date) || empty($return_date)) {
    echo json_encode(['available_count' => 0, 'message' => 'Item or date is missing.']);
    exit;
}


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


$total_stock_data = $result_total->fetch_assoc();
$total_stock = isset($total_stock_data['total_stock']) ? $total_stock_data['total_stock'] : 0;
$stmt_total->close();


if ($total_stock == 0) {
    echo json_encode(['available_count' => 0, 'message' => 'Item is currently out of stock (Total Stock is 0).']);
    exit;
}


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


$reserved_quantity_data = $result_reserved->fetch_assoc();
$reserved_quantity = isset($reserved_quantity_data['reserved_quantity']) ? $reserved_quantity_data['reserved_quantity'] : 0;
$stmt_reserved->close();



$available_count = $total_stock - $reserved_quantity;


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