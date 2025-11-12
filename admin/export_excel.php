<?php
session_start();
include '../config.php';

if (!isset($_SESSION['admin_id'])) {
    die("Access denied. Please log in as admin.");
}

$export_type = isset($_GET['export']) ? $_GET['export'] : '';

if ($export_type == 'returns') {
    
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
                    COALESCE(tech.name, adm.name) AS technician_name
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

    // 3. Tetapkan Headers untuk muat turun
    $filename = "returned_items_report_" . date('Y-m-d') . ".csv";
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="' . $filename . '"');

    // 4. Tulis data CSV
    $output = fopen('php://output', 'w');
    
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
            $row['technician_name']
        ));
    }
    
    $stmt_report->close();
    fclose($output);
    exit();

} elseif ($export_type == 'activity') {

    $log_start_date = isset($_GET['log_start_date']) ? $_GET['log_start_date'] : date('Y-m-d');
    $log_end_date = isset($_GET['log_end_date']) ? $_GET['log_end_date'] : date('Y-m-d');
    $log_user_type = isset($_GET['user_type']) ? $_GET['user_type'] : '';
    $log_search = isset($_GET['search']) ? trim($_GET['search']) : '';
    $end_date_sql = $log_end_date . ' 23:59:59';

    $sql_base_log = "FROM activity_logs";
    $where_clauses_log = array("timestamp BETWEEN ? AND ?");
    $param_types_log = "ss";
    $param_values_log = array($log_start_date, $end_date_sql);

    if (!empty($log_user_type)) {
        $where_clauses_log[] = "user_type = ?";
        $param_types_log .= "s";
        $param_values_log[] = $log_user_type;
    }
    if (!empty($log_search)) {
        $where_clauses_log[] = "(action LIKE ? OR details LIKE ?)";
        $param_types_log .= "ss";
        $search_like = "%" . $log_search . "%";
        $param_values_log[] = $search_like;
        $param_values_log[] = $search_like;
    }
    $sql_where_log = " WHERE " . implode(' AND ', $where_clauses_log);

    $sql_log = "SELECT log_id, timestamp, user_type, user_id, action, details, ip_address 
                 " . $sql_base_log . $sql_where_log . " 
                 ORDER BY timestamp ASC"; 

    $stmt_log = $conn->prepare($sql_log);
    if ($stmt_log === false) { die("SQL Error: " . htmlspecialchars($conn->error)); }

    $bind_params_log = array();
    $bind_params_log[] = $param_types_log;
    for ($i = 0; $i < count($param_values_log); $i++) {
        $bind_params_log[] = &$param_values_log[$i];
    }
    call_user_func_array(array($stmt_log, 'bind_param'), $bind_params_log);
    
    $stmt_log->execute();
    $result = $stmt_log->get_result();
    
    $filename = "activity_log_report_" . date('Y-m-d') . ".csv";
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    
    $output = fopen('php://output', 'w');
    
    fputcsv($output, array('Timestamp', 'User Type', 'User ID', 'Action', 'Details', 'IP Address'));

    while ($row = $result->fetch_assoc()) {
        fputcsv($output, array(
            $row['timestamp'],
            $row['user_type'],
            $row['user_id'],
            $row['action'],
            $row['details'],
            $row['ip_address']
        ));
    }
    
    $stmt_log->close();
    fclose($output);
    exit();

} else {
    die("Error: No valid export type specified.");
}
?>