<?php
// File: /admin/generate_pdf_admin.php
session_start();
include '../config.php';

require_once __DIR__ . '/../vendor/autoload.php';

// 1. Pemeriksaan Sesi untuk Admin
if (!isset($_SESSION['admin_id'])) {
    $mpdf = new \Mpdf\Mpdf();
    $mpdf->WriteHTML('<h1>Access Denied</h1><p>Your session has expired. Please log in again.</p>');
    $mpdf->Output('error.pdf', 'I');
    exit();
}

// 2. Pemeriksaan Fail Templat
$template_path = 'pdf_template_admin.php';
if (!file_exists($template_path)) {
    $mpdf = new \Mpdf\Mpdf();
    $mpdf->WriteHTML('<h1>Configuration Error</h1><p>The PDF template file was not found.</p>');
    $mpdf->Output('error.pdf', 'I');
    exit();
}

// 3. Dapatkan Data dengan Logik Penapis yang Sama
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-01');
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-t');
$category_filter_id = isset($_GET['category_id']) ? (int)$_GET['category_id'] : 0;

// Bina query secara dinamik
$sql_base = "SELECT 
                u.name AS user_name, i.item_name, a.asset_code, c.category_name,
                ri.reserve_date, ri.return_date, ri.return_condition,
                COALESCE(tech.name, adm.name) AS technician_name
             FROM reservation_items ri
             JOIN reservations r ON ri.reserve_id = r.reserve_id
             JOIN user u ON r.user_id = u.user_id
             JOIN item i ON ri.item_id = i.item_id
             JOIN categories c ON i.category_id = c.category_id
             LEFT JOIN reservation_assets ra ON ri.id = ra.reservation_item_id
             LEFT JOIN assets a ON ra.asset_id = a.asset_id
             LEFT JOIN technician tech ON ri.approved_by = tech.tech_id
             LEFT JOIN admin adm ON ri.approved_by = adm.admin_id";

$where_clauses = array(
    "ri.status = 'Returned'",
    "ri.return_date BETWEEN ? AND ?"
);
$param_types = "ss";
$param_values = array($start_date, $end_date);

if ($category_filter_id > 0) {
    $where_clauses[] = "i.category_id = ?";
    $param_types .= "i";
    $param_values[] = $category_filter_id;
}

$sql = $sql_base . " WHERE " . implode(' AND ', $where_clauses) . " ORDER BY ri.return_date ASC";

$stmt = $conn->prepare($sql);
if ($stmt === false) { die("SQL Error: " . htmlspecialchars($conn->error)); }

// Gunakan call_user_func_array untuk bind_param
$bind_params = array();
$bind_params[] = $param_types;
for ($i = 0; $i < count($param_values); $i++) {
    $bind_params[] = &$param_values[$i];
}
call_user_func_array(array($stmt, 'bind_param'), $bind_params);

$stmt->execute();
$result = $stmt->get_result();
$records = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Dapatkan nama kategori untuk tajuk laporan
$category_name = 'All Categories';
if ($category_filter_id > 0) {
    $cat_stmt = $conn->prepare("SELECT category_name FROM categories WHERE category_id = ?");
    $cat_stmt->bind_param("i", $category_filter_id);
    $cat_stmt->execute();
    $cat_res = $cat_stmt->get_result()->fetch_assoc();
    $category_name = isset($cat_res['category_name']) ? $cat_res['category_name'] : 'Unknown';
    $cat_stmt->close();
}

// 4. Bina Kandungan HTML
$html = file_get_contents($template_path);
$tableRows = '';
if (empty($records)) {
    $tableRows = '<tr><td colspan="8" style="text-align:center;">No returned items found for this period.</td></tr>';
} else {
    $count = 1;
    foreach ($records as $record) {
        $asset_code = !empty($record['asset_code']) ? $record['asset_code'] : 'N/A';
        $return_condition = !empty($record['return_condition']) ? $record['return_condition'] : 'Not specified';
        $technician_name = !empty($record['technician_name']) ? $record['technician_name'] : 'N/A';
        
        $tableRows .= '<tr>
            <td>' . $count++ . '</td>
            <td>' . htmlspecialchars($record['user_name']) . '</td>
            <td>' . htmlspecialchars($record['item_name']) . ' (' . htmlspecialchars($asset_code) . ')</td>
            <td>' . htmlspecialchars($record['category_name']) . '</td>
            <td>' . date("d M Y", strtotime($record['reserve_date'])) . '</td>
            <td>' . date("d M Y", strtotime($record['return_date'])) . '</td>
            <td>' . htmlspecialchars($return_condition) . '</td>
            <td>' . htmlspecialchars($technician_name) . '</td>
        </tr>';
    }
}

// Gantikan pemegang tempat
$html = str_replace('{{start_date}}', htmlspecialchars(date("d M Y", strtotime($start_date))), $html);
$html = str_replace('{{end_date}}', htmlspecialchars(date("d M Y", strtotime($end_date))), $html);
$html = str_replace('{{category_name}}', htmlspecialchars($category_name), $html);
$html = str_replace('{{table_rows}}', $tableRows, $html);

// 5. Jana PDF
try {
    $mpdf = new \Mpdf\Mpdf(array('format' => 'A4-L'));
    $mpdf->SetHeader('UniKL Equipment Return Report | Generated: ' . date('d M Y, H:i'));
    $mpdf->SetFooter('Page {PAGENO}');
    $mpdf->WriteHTML($html);
    $filename = 'Return-Report_' . $start_date . '_to_' . $end_date . '.pdf';
    $mpdf->Output($filename, 'I');
} catch (\Mpdf\MpdfException $e) {
    echo 'mPDF Error: ' . $e->getMessage();
}
?>