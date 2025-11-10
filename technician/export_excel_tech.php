<?php

session_start();
include '../config.php';


if (!isset($_SESSION['tech_id'])) {
    die("Access denied. Please log in as Technician.");
}
$tech_id = (int)$_SESSION['tech_id']; 


$report_start_date = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-01');
$report_end_date = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-t');
$report_category_id = isset($_GET['category_id']) ? (int)$_GET['category_id'] : 0;



$sql_base_report = "FROM reservation_items ri
     JOIN reservations r ON ri.reserve_id = r.reserve_id
     JOIN user u ON r.user_id = u.user_id
     JOIN item i ON ri.item_id = i.item_id
     JOIN categories c ON i.category_id = c.category_id
     LEFT JOIN reservation_assets ra ON ri.id = ra.reservation_item_id
     LEFT JOIN assets a ON ra.asset_id = a.asset_id
     LEFT JOIN technician tech ON ri.approved_by = tech.tech_id
     LEFT JOIN admin adm ON ri.approved_by = adm.admin_id"; 

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
                COALESCE(tech.name, adm.name) AS handled_by_name -- Tukar alias jika perlu
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


$output = fopen('php:





fputcsv($output, array('User', 'Item Name', 'Asset Code', 'Category', 'Borrow Date', 'Return Date', 'Return Condition', 'Handled By'));


while ($row = $result->fetch_assoc()) {
    fputcsv($output, array(
        $row['user_name'],
        $row['item_name'],
        $row['asset_code'],
        $row['category_name'],
        $row['reserve_date'], 
        $row['return_date'],  
        $row['return_condition'],
        $row['handled_by_name'] 
    ));
}

$stmt_report->close();
fclose($output);
$conn->close(); 
exit();
?>