<?php
session_start();
include '../config.php';


require_once __DIR__ . '/../vendor/autoload.php';


if (!isset($_SESSION['tech_id'])) {
    
    $mpdf = new \Mpdf\Mpdf();
    $mpdf->WriteHTML('<h1>Akses Ditolak</h1><p>Sesi anda telah tamat. Sila log masuk semula.</p>');
    $mpdf->Output('error.pdf', 'I'); 
    exit();
}


$template_path = 'pdf_template.html';
if (!file_exists($template_path)) {
    
    $mpdf = new \Mpdf\Mpdf();
    $mpdf->WriteHTML('<h1>Ralat Konfigurasi</h1><p>Fail templat PDF tidak dijumpai. Sila pastikan fail bernama <strong>pdf_template.html</strong> wujud.</p>');
    $mpdf->Output('error.pdf', 'I');
    exit();
}


$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-01');
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-t');

$sql = "SELECT 
            u.name AS user_name, i.item_name, a.asset_code, 
            ri.reserve_date, ri.return_date, ri.return_condition,
            COALESCE(tech.name, adm.name) AS technician_name
        FROM reservation_items ri
        JOIN reservations r ON ri.reserve_id = r.reserve_id
        JOIN user u ON r.user_id = u.user_id
        JOIN item i ON ri.item_id = i.item_id
        LEFT JOIN reservation_assets ra ON ri.id = ra.reservation_item_id
        LEFT JOIN assets a ON ra.asset_id = a.asset_id
        LEFT JOIN technician tech ON ri.approved_by = tech.tech_id
        LEFT JOIN admin adm ON ri.approved_by = adm.admin_id
        WHERE ri.status = 'Returned' AND ri.return_date BETWEEN ? AND ?
        ORDER BY a.asset_code ASC";


$stmt = $conn->prepare($sql);
if ($stmt === false) { die("SQL Error: " . htmlspecialchars($conn->error)); }

$stmt->bind_param("ss", $start_date, $end_date);
$stmt->execute();
$result = $stmt->get_result();
$records = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();


$html = file_get_contents($template_path);
$tableRows = '';
if (empty($records)) {
    $tableRows = '<tr><td colspan="8" style="text-align:center;">No returned items found for this period.</td></tr>';
} else {
    $count = 1;
    foreach ($records as $record) {
        $duration = (new DateTime($record['return_date']))->diff(new DateTime($record['reserve_date']))->days + 1;
        
        $asset_code = !empty($record['asset_code']) ? $record['asset_code'] : 'N/A';
        $return_condition = !empty($record['return_condition']) ? $record['return_condition'] : 'Not specified';
        $technician_name = !empty($record['technician_name']) ? $record['technician_name'] : 'N/A';

        $tableRows .= '<tr>
            <td>' . $count++ . '</td>
            <td>' . htmlspecialchars($record['user_name']) . '</td>
            <td>' . htmlspecialchars($record['item_name']) . ' (' . htmlspecialchars($asset_code) . ')</td>
            <td>' . date("d M Y", strtotime($record['reserve_date'])) . '</td>
            <td>' . date("d M Y", strtotime($record['return_date'])) . '</td>
            <td>' . $duration . ' day(s)</td>
            <td>' . htmlspecialchars($return_condition) . '</td>
            <td>' . htmlspecialchars($technician_name) . '</td>
        </tr>';
    }
}


$html = str_replace('{{start_date}}', htmlspecialchars($start_date), $html);
$html = str_replace('{{end_date}}', htmlspecialchars($end_date), $html);
$html = str_replace('{{table_rows}}', $tableRows, $html);


try {
    $mpdf = new \Mpdf\Mpdf(['format' => 'A4-L']);
    $mpdf->SetHeader('UniKL Equipment Return Report | Generated: ' . date('Y-m-d H:i'));
    $mpdf->SetFooter('Page {PAGENO}');
    $mpdf->WriteHTML($html);
    $mpdf->Output('Return-Report_' . $start_date . '_to_' . $end_date . '.pdf', 'D');
} catch (\Mpdf\MpdfException $e) {
    echo 'mPDF Error: ' . $e->getMessage();
}
?>