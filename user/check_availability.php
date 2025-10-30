<?php
session_start();
include 'config.php';

header('Content-Type: application/json');

if (!isset($_POST['item_name'], $_POST['quantity'], $_POST['start_date'], $_POST['end_date'])) {
    echo json_encode(['status' => 'error', 'message' => 'Incomplete data.']);
    exit();
}

$item_name = $_POST['item_name'];
$requested_quantity = (int)$_POST['quantity'];
$start_date = $_POST['start_date'];
$end_date = $_POST['end_date'];

if ($requested_quantity <= 0) {
    echo json_encode(['status' => 'error', 'message' => 'Quantity must be at least 1.']);
    exit();
}

// Get total functional stock
$item_id = 0;
$total_functional_stock = 0;
$stmt_item = $conn->prepare("SELECT i.item_id, COUNT(a.asset_id) as total_stock FROM item i LEFT JOIN assets a ON i.item_id = a.item_id WHERE i.item_name = ? AND (a.status IS NULL OR a.status NOT IN ('Broken', 'Missing')) GROUP BY i.item_id");
$stmt_item->bind_param("s", $item_name);
$stmt_item->execute();
$result_item = $stmt_item->get_result();
if ($item_row = $result_item->fetch_assoc()) {
    $item_id = (int)$item_row['item_id'];
    $total_functional_stock = (int)$item_row['total_stock'];
}
$stmt_item->close();

if ($item_id === 0) {
    echo json_encode(['status' => 'error', 'message' => 'Item not found.']);
    exit();
}

// Count booked items during the period
$booked_during_period = 0;
$sql_booked = "SELECT COALESCE(SUM(ri.quantity), 0) as booked_qty FROM reservation_items ri WHERE ri.item_id = ? AND ri.status = 'Approved' AND ri.reserve_date <= ? AND ri.return_date >= ?";
$stmt_booked = $conn->prepare($sql_booked);
$stmt_booked->bind_param("iss", $item_id, $end_date, $start_date);
$stmt_booked->execute();
$result_booked = $stmt_booked->get_result();
if ($booked_row = $result_booked->fetch_assoc()) {
    $booked_during_period = (int)$booked_row['booked_qty'];
}
$stmt_booked->close();

$effective_available_stock = $total_functional_stock - $booked_during_period;

// ✅ NEW LOGIC: Return different statuses based on availability
if ($requested_quantity <= $effective_available_stock) {
    // Fully available
    echo json_encode(['status' => 'success', 'message' => 'Item is available.']);
} elseif ($effective_available_stock > 0) {
    // Partially available
    $message = "Only " . $effective_available_stock . " unit(s) of '" . htmlspecialchars($item_name) . "' are available for these dates.";
    echo json_encode([
        'status' => 'partial', // The new status
        'message' => $message,
        'available_count' => $effective_available_stock // Send back how many are available
    ]);
} else {
    // Completely unavailable
    $message = "No units of '" . htmlspecialchars($item_name) . "' are available for these dates.";
    echo json_encode(['status' => 'error', 'message' => $message]);
}

$conn->close();
?>