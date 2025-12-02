<?php
session_start();

include '../config.php'; 


if (!isset($_SESSION['person_id'])) {
    
    die("Access denied.");
}


$export_type = isset($_GET['export']) ? $_GET['export'] : 'returns';


$sql_select = "";
$sql_from_where = "";
$param_types = "";
$param_values = array();
$filename_prefix = "";
$header_row = array();


if ($export_type === 'returns') {
    
    $filename_prefix = "returned_items";
    $report_start_date = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-01');
    $report_end_date = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-t');
    $report_category_id = isset($_GET['category_id']) ? (int)$_GET['category_id'] : 0;

    $header_row = array('Borrower Name', 'Item Name', 'Asset Code', 'Item Type', 'Asset Issuance Date', 'Asset Return Date', 'Return Condition', 'Responsible Officer');

    $sql_select = "u.name AS user_name, i.item_name, a.asset_code, c.category_name, ri.reserve_date, ri.return_date, ri.return_condition, handler.name AS handled_by_name";
    
    $sql_from_where = "FROM reservation_items ri
        JOIN reservations r ON ri.reserve_id = r.reserve_id
        JOIN person u ON r.person_id = u.person_id
        JOIN item i ON ri.item_id = i.item_id
        JOIN categories c ON i.category_id = c.category_id
        LEFT JOIN reservation_assets ra ON ri.id = ra.reservation_item_id
        LEFT JOIN assets a ON ra.asset_id = a.asset_id
        LEFT JOIN person handler ON ri.approved_by = handler.person_id";
        
    $where_clauses = array(
        "ri.status = 'Returned'",
        "ri.return_date BETWEEN ? AND ?"
    );
    $param_types = "ss";
    $param_values = array($report_start_date, $report_end_date);

    if ($report_category_id > 0) {
        $where_clauses[] = "i.category_id = ?";
        $param_types .= "i";
        $param_values[] = $report_category_id;
    }
    
    $sql_from_where .= " WHERE " . implode(' AND ', $where_clauses);
    $sql_order = " ORDER BY ri.return_date DESC";

} elseif ($export_type === 'activity') {
    
    $filename_prefix = "activity_log";
    $log_start_date = isset($_GET['log_start_date']) ? $_GET['log_start_date'] : date('Y-m-d', strtotime('-7 days'));
    $log_end_date = isset($_GET['log_end_date']) ? $_GET['log_end_date'] : date('Y-m-d');
    $log_user_type = isset($_GET['user_type']) ? $_GET['user_type'] : '';
    $log_search = isset($_GET['search']) ? trim($_GET['search']) : '';
    $end_date_sql = $log_end_date . ' 23:59:59';
    
    $header_row = array('Timestamp', 'User Type', 'User ID', 'Action', 'Details', 'IP Address');

    $sql_select = "timestamp, user_type, user_id, action, details, ip_address";
    $sql_from_where = "FROM activity_logs";
    
    $where_clauses = array("timestamp BETWEEN ? AND ?");
    $param_types = "ss";
    $param_values = array($log_start_date, $end_date_sql);

    if (!empty($log_user_type)) {
        $where_clauses[] = "user_type = ?";
        $param_types .= "s";
        $param_values[] = $log_user_type;
    }
    if (!empty($log_search)) {
        $where_clauses[] = "(action LIKE ? OR details LIKE ?)";
        $param_types .= "ss";
        $search_like = "%" . $log_search . "%";
        $param_values[] = $search_like;
        $param_values[] = $search_like;
    }
    
    $sql_from_where .= " WHERE " . implode(' AND ', $where_clauses);
    $sql_order = " ORDER BY timestamp DESC";

} else {
    die("Invalid export type.");
}




$sql_full = "SELECT " . $sql_select . " " . $sql_from_where . $sql_order;

$stmt = $conn->prepare($sql_full);
if ($stmt === false) { die("SQL Error: " . htmlspecialchars($conn->error)); }


if (!empty($param_values)) {
    $bind_params = array();
    $bind_params[] = $param_types;
    for ($i = 0; $i < count($param_values); $i++) {
        $bind_params[] = &$param_values[$i];
    }
    call_user_func_array(array($stmt, 'bind_param'), $bind_params);
}

$stmt->execute();
$result = $stmt->get_result();



$filename = $filename_prefix . "_" . date('Y-m-d') . ".csv";
header('Content-Type: text/csv; charset=utf-8');    
header('Content-Disposition: attachment; filename="' . $filename . '"');


$output = fopen('php://output', 'w');    

fputcsv($output, $header_row);

while ($row = $result->fetch_assoc()) {
    if ($export_type === 'returns') {
        
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
    } elseif ($export_type === 'activity') {
        
        fputcsv($output, array(
            date("Y-m-d H:i:s", strtotime($row['timestamp'])), 
            $row['user_type'],
            $row['user_id'],
            $row['action'],
            $row['details'],
            $row['ip_address']
        ));
    }
}


$stmt->close();
fclose($output);
$conn->close();    
exit();
?>