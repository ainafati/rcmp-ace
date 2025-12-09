<?php

session_start();
include '../config.php';

if (!isset($_SESSION['person_id'])) {
header("Location: ../login.php");
exit();
}
$person_id = (int)$_SESSION['person_id'];


$stmt_tech = $conn->prepare("SELECT name FROM person WHERE person_id = ?");
$stmt_tech->bind_param("i", $person_id);
$stmt_tech->execute();
$result_tech = $stmt_tech->get_result();
$tech = ($tech_data = $result_tech->fetch_assoc()) ? $tech_data : ['name' => 'Technician'];
$stmt_tech->close();


$report_start_date = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-01');
$report_end_date = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-t');
$report_category_id = isset($_GET['category_id']) ? (int)$_GET['category_id'] : 0;

$sql_base_report = "FROM reservation_items ri
JOIN reservations r ON ri.reserve_id = r.reserve_id
JOIN person u ON r.person_id = u.person_id
JOIN item i ON ri.item_id = i.item_id
JOIN categories c ON i.category_id = c.category_id
LEFT JOIN reservation_assets ra ON ri.id = ra.reservation_item_id
LEFT JOIN assets a ON ra.asset_id = a.asset_id

-- JOIN 1: Approved By
LEFT JOIN person approved ON ri.approved_by = approved.person_id

-- JOIN 2: Checked Out By (Memerlukan lajur checked_out_by di ri)
LEFT JOIN person checkout ON ri.checked_out_by = checkout.person_id

-- JOIN 3: Checked In By (Memerlukan lajur checked_in_by di ri)
LEFT JOIN person checkin ON ri.checked_in_by = checkin.person_id
";

$where_clauses_report = array(
"ri.status = 'Returned'",
"ri.return_date BETWEEN ? AND ?"
);
$param_types_report = "ss";
$param_values_report = array($report_start_date, $report_end_date);

if ($report_category_id > 0) {
$where_clauses_report[] = "i.category_id = ?";
$param_types_report .= "i";
$param_values_report[] = $report_category_id;
}

$sql_where_report = " WHERE " . implode(' AND ', $where_clauses_report);

$sql_report = "SELECT
u.name AS user_name, i.item_name, a.asset_code, c.category_name,
ri.reserve_date, ri.return_date, ri.return_condition,

-- AMBIL 3 NAMA SEKARANG
approved.name AS approved_by_name,
checkout.name AS checked_out_by_name,
checkin.name AS checked_in_by_name
" . $sql_base_report . $sql_where_report . "
ORDER BY a.asset_code ASC";


$stmt_report = $conn->prepare($sql_report);
if ($stmt_report === false) { die("SQL Error: " . htmlspecialchars($conn->error)); }



$bind_params_select = array();
$bind_params_select[] = $param_types_report;
for ($i = 0; $i < count($param_values_report); $i++) {
$bind_params_select[] = &$param_values_report[$i];
}
call_user_func_array(array($stmt_report, 'bind_param'), $bind_params_select);

$stmt_report->execute();
$result = $stmt_report->get_result();




$filename = "tech_returned_items_" . date('Y-m-d') . ".csv";
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');

$output = fopen('php://output', 'w');


fputcsv($output, array(
    'Borrower Name', 
    'Item Name', 
    'Asset Code', 
    'Item Type', 
    'Asset Issuance Date', 
    'Asset Return Date', 
    'Return Condition', 
    'Approved By', 
    'Checked Out By',
    'Checked In By'
));


while ($row = $result->fetch_assoc()) {
fputcsv($output, array(
$row['user_name'],
$row['item_name'],
$row['asset_code'],
$row['category_name'],
$row['reserve_date'],
$row['return_date'],
$row['return_condition'],

$row['approved_by_name'] ?? 'N/A', 
$row['checked_out_by_name'] ?? 'N/A',
$row['checked_in_by_name'] ?? 'N/A'
));
}


$stmt_report->close();
fclose($output);
$conn->close();
exit();
?>