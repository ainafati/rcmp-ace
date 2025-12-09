<?php
session_start();
include '../config.php';

require_once __DIR__ . '/../vendor/autoload.php';

use Mpdf\Mpdf;
use Mpdf\MpdfException;


$relative_path_to_logo = __DIR__ . '/../img/unikl_logo-removebg-preview.png';
$logo_file_path = realpath($relative_path_to_logo);
if ($logo_file_path === false) { 
    $logo_file_path = $relative_path_to_logo; 
}

$template_path = 'pdf_template.html';
if (!file_exists($template_path)) {
    $mpdf = new Mpdf();
    $mpdf->WriteHTML('<h1>Configuration Error</h1><p>PDF template file not found. Please ensure a file named <strong>pdf_template.html</strong> exists.</p>');
    $mpdf->Output('error_config.pdf', 'I');
    exit();
}


$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-01');
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-t');
$asset_id_filter = isset($_GET['asset_id']) && !empty($_GET['asset_id']) ? $_GET['asset_id'] : null;



$sql = "SELECT 
            u.name AS user_name, 
            i.item_name, 
            a.asset_code, 
            ri.reserve_date, 
            ri.return_date, 
            ri.return_condition,
            
            -- 1. Approved By
            approved.name AS approved_by_name,
            
            -- 2. Checked Out By
            checkout.name AS checked_out_by_name,
            
            -- 3. Checked In By
            checkin.name AS checked_in_by_name
            
        FROM reservation_items ri
        JOIN reservations r ON ri.reserve_id = r.reserve_id
        JOIN person u ON r.person_id = u.person_id 
        JOIN item i ON ri.item_id = i.item_id
        LEFT JOIN reservation_assets ra ON ri.id = ra.reservation_item_id
        LEFT JOIN assets a ON ra.asset_id = a.asset_id
        
        -- JOIN untuk Approved By (guna ri.approved_by)
        LEFT JOIN person approved ON ri.approved_by = approved.person_id
        
        -- JOIN untuk Checked Out By (guna lajur BARU ri.checked_out_by)
        LEFT JOIN person checkout ON ri.checked_out_by = checkout.person_id
        
        -- JOIN untuk Checked In By (guna lajur BARU ri.checked_in_by)
        LEFT JOIN person checkin ON ri.checked_in_by = checkin.person_id
        
        WHERE ri.status = 'Returned' AND ri.return_date BETWEEN ? AND ?";
        

if ($asset_id_filter !== null) {
    $sql .= " AND a.asset_id = ?";
}

$sql .= " ORDER BY a.asset_code ASC";


$stmt = $conn->prepare($sql);
if ($stmt === false) { die("SQL Error: " . htmlspecialchars($conn->error)); }


if ($asset_id_filter !== null) {
    
    $stmt->bind_param("ssi", $start_date, $end_date, $asset_id_filter); 
} else {
    
    $stmt->bind_param("ss", $start_date, $end_date);
}

$stmt->execute();
$result = $stmt->get_result();
$records = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();



$html = file_get_contents($template_path);
$tableRows = '';

if (empty($records)) {
    
    $tableRows = '<tr><td colspan="10" style="text-align:center; padding: 20px; color: #CC0000;">No returned items found for this period.</td></tr>';
} else {
    $count = 1;
    foreach ($records as $record) {
        
        $reserve_date_obj = new DateTime($record['reserve_date']); 
        $return_date_obj = new DateTime($record['return_date']);
        $duration = $return_date_obj->diff($reserve_date_obj)->days + 1;
        
        $asset_code = !empty($record['asset_code']) ? htmlspecialchars($record['asset_code']) : 'N/A';
        $return_condition = !empty($record['return_condition']) ? htmlspecialchars($record['return_condition']) : 'Not specified'; 
        
        
        $approved_by = !empty($record['approved_by_name']) ? htmlspecialchars($record['approved_by_name']) : 'N/A';
        $checked_out_by = !empty($record['checked_out_by_name']) ? htmlspecialchars($record['checked_out_by_name']) : 'N/A';
        $checked_in_by = !empty($record['checked_in_by_name']) ? htmlspecialchars($record['checked_in_by_name']) : 'N/A';
        
        $item_details = '<div class="item-details">' . 
                            '<strong>' . htmlspecialchars($record['item_name']) . ':</strong>' . 
                            '<br>' . 
                            'Asset Code: ' . $asset_code . 
                            '</div>';

        $tableRows .= '<tr>
            <td>' . $count++ . '</td>
            <td>' . htmlspecialchars($record['user_name']) . '</td>
            <td>' . $item_details . '</td>
            <td>' . $reserve_date_obj->format("d M Y") . '</td>
            <td>' . $return_date_obj->format("d M Y") . '</td>
            <td>' . $duration . ' days</td>
            <td>' . $return_condition . '</td>
            <td>' . $approved_by . '</td>
            <td>' . $checked_out_by . '</td>
            <td>' . $checked_in_by . '</td>
        </tr>';
    }
}



$html = str_replace('{{logo_path}}', $logo_file_path, $html); 
$html = str_replace('{{start_date}}', date("d M Y", strtotime($start_date)), $html);
$html = str_replace('{{end_date}}', date("d M Y", strtotime($end_date)), $html);
$html = str_replace('{{table_rows}}', $tableRows, $html);

try {
    
    $mpdf = new Mpdf(['format' => 'A4-L']); 
    $mpdf->SetTitle('UiTM Equipment Return Report'); 
    
    $mpdf->WriteHTML($html);
    
    $mpdf->Output('Return-Report_' . $start_date . '_to_' . $end_date . '.pdf', 'D'); 
} catch (MpdfException $e) {
    
    echo 'mPDF Error: ' . $e->getMessage();
}

exit();
?>