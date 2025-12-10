<?php
session_start();
include '../config.php';


require_once __DIR__ . '/../vendor/autoload.php';

use Mpdf\Mpdf;
use Mpdf\MpdfException;

// ------------------------------------------------------------------------
// 1. PATH RESOLUTION & SETUP
// ------------------------------------------------------------------------

$relative_path_to_logo = __DIR__ . '/../img/unikl_logo-removebg-preview.png';

$logo_file_path = realpath($relative_path_to_logo);

if ($logo_file_path === false) {
    // Fallback jika realpath gagal
    $logo_file_path = $relative_path_to_logo; 
}


$template_path = 'pdf_template_admin.php'; 
if (!file_exists($template_path)) {
    try {
        $mpdf = new Mpdf();
        $mpdf->WriteHTML('<h1>Configuration Error</h1><p>PDF template file not found. Please ensure a file named <strong>' . htmlspecialchars($template_path) . '</strong> exists.</p>');
        $mpdf->Output('error_config.pdf', 'I');
    } catch (MpdfException $e) {
        echo 'mPDF Error during initial setup: ' . $e->getMessage();
    }
    exit();
}


$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-01');
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-t');



// ------------------------------------------------------------------------
// 2. SQL QUERY & DATA FETCHING (DIPERBAIKI)
// ------------------------------------------------------------------------

$sql = "SELECT 
            u.name AS user_name, 
            i.item_name, 
            a.asset_code, 
            r.reserve_date, 
            r.return_date, 
            ri.return_condition,
            
            handler.name AS approved_by_name,
            checkout.name AS checked_out_by_name, -- *** BARIS DITAMBAH ***
            checkin.name AS checked_in_by_name
            
        FROM reservation_items ri
        JOIN reservations r ON ri.reserve_id = r.reserve_id
        
        JOIN person u ON r.person_id = u.person_id 
        JOIN item i ON ri.item_id = i.item_id
        LEFT JOIN reservation_assets ra ON ri.id = ra.reservation_item_id
        LEFT JOIN assets a ON ra.asset_id = a.asset_id
        
        LEFT JOIN person handler ON ri.approved_by = handler.person_id
        LEFT JOIN person checkout ON ri.checked_out_by = checkout.person_id -- *** JOIN DITAMBAH ***
        LEFT JOIN person checkin ON ri.checked_in_by = checkin.person_id
        
        WHERE ri.status = 'Returned' AND r.return_date BETWEEN ? AND ?
        ORDER BY r.return_date DESC, a.asset_code ASC";


$stmt = $conn->prepare($sql);
if ($stmt === false) { die("SQL Prepare Error: " . htmlspecialchars($conn->error)); }

$stmt->bind_param("ss", $start_date, $end_date);
$stmt->execute();
$result = $stmt->get_result();
$records = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();



// ... (Langkah 2: Query SQL masih mengambil 9 lajur: u.name, i.item_name, a.asset_code, ri.checked_out_on, r.return_date, ri.return_condition, approved.name, checkout.name, checkin.name)

// ------------------------------------------------------------------------
// 3. HTML GENERATION (Laporan Juruteknik)
// ------------------------------------------------------------------------
$html = file_get_contents($template_path);
$tableRows = '';

if (empty($records)) {
    // Colspan = 9 (No, Borrower, Asset, Reserve, Return, Condition, Approved, Checked Out, Checked In)
    $tableRows = '<tr><td colspan="9" style="text-align:center; padding: 20px; color: #CC0000;">No returned items found for this period.</td></tr>';
} else {
    $count = 1;
    foreach ($records as $record) {
        
        $checkout_date_str = $record['checked_out_on'] ?? $record['return_date']; 
        $checkout_date_obj = new DateTime($checkout_date_str); 
        $return_date_obj = new DateTime($record['return_date']);
        
        // *** HAPUSKAN LOGIK DURATION DI SINI ***
        // $duration = $return_date_obj->diff($checkout_date_obj)->days + 1; 
        
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
            <td class="center-text">' . $count++ . '</td>
            <td class="name-cell">' . htmlspecialchars($record['user_name']) . '</td>
            <td>' . $item_details . '</td>
            <td class="center-text">' . $checkout_date_obj->format("d M Y") . '</td>
            <td class="center-text">' . $return_date_obj->format("d M Y") . '</td>
            
            <td>' . $return_condition . '</td> 

            <td class="name-cell">' . $approved_by . '</td>
            <td class="name-cell">' . $checked_out_by . '</td>
            <td class="name-cell">' . $checked_in_by . '</td>
        </tr>';
    }
}
// ...
// Replace placeholders in the HTML template
$html = str_replace('{{logo_path}}', $logo_file_path, $html); 
$html = str_replace('{{start_date}}', date("d M Y", strtotime($start_date)), $html);
$html = str_replace('{{end_date}}', date("d M Y", strtotime($end_date)), $html);
$html = str_replace('{{table_rows}}', $tableRows, $html);


// ------------------------------------------------------------------------
// 4. Mpdf GENERATION & OUTPUT
// ------------------------------------------------------------------------

try {
    
    $mpdf = new Mpdf(['format' => 'A4-L']); 
    $mpdf->SetTitle('Equipment Return Report (Admin)'); 
    
    $mpdf->WriteHTML($html);
    
    $mpdf->Output('Return-Report_Admin_' . $start_date . '_to_' . $end_date . '.pdf', 'D'); 
} catch (MpdfException $e) {
    
    echo 'mPDF Error: ' . $e->getMessage();
}

exit();
// TIADA TAG PENUTUP ?>