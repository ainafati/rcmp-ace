<?php
session_start();
include '../config.php';

require_once __DIR__ . '/../vendor/autoload.php';

use Mpdf\Mpdf;
use Mpdf\MpdfException;

// ------------------------------------------------------------------------
// 1. PATH RESOLUTION & ERROR CHECK
// ------------------------------------------------------------------------

$relative_path_to_logo = __DIR__ . '/../img/logo_latest-Photoroom.png';
$logo_file_path = realpath($relative_path_to_logo);
if ($logo_file_path === false) { 
    // Fallback jika realpath gagal
    $logo_file_path = $relative_path_to_logo; 
}

$template_path = 'pdf_template.html';
if (!file_exists($template_path)) {
    try {
        $mpdf = new Mpdf();
        $mpdf->WriteHTML('<h1>Configuration Error</h1><p>PDF template file not found. Please ensure a file named <strong>pdf_template.html</strong> exists.</p>');
        $mpdf->Output('error_config.pdf', 'I');
    } catch (MpdfException $e) {
        echo 'mPDF Error during initial setup: ' . $e->getMessage();
    }
    exit();
}

// 1. Tangkap semua filter dari URL
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-01');
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-t');
$status_filter = isset($_GET['status_filter']) ? $_GET['status_filter'] : 'Returned';
$category_id = isset($_GET['category_id']) ? (int)$_GET['category_id'] : 0;
$asset_code_filter = isset($_GET['asset_code']) ? trim($_GET['asset_code']) : '';

// 2. Tentukan kolum tarikh secara dinamik
// Kalau status 'Returned', guna return_date. Kalau lain, guna reserve_date
$date_column = ($status_filter === 'Returned') ? "r.return_date" : "r.reserve_date";

// 3. Bina SQL yang fleksibel
$sql = "SELECT 
            u.name AS user_name, 
            i.item_name, 
            a.asset_code, 
            ri.checked_out_on,
            r.reserve_date,
            r.return_date,
            ri.return_condition,
            approved.name AS approved_by_name,
            checkout.name AS checked_out_by_name,
            checkin.name AS checked_in_by_name
        FROM reservation_items ri
        JOIN reservations r ON ri.reserve_id = r.reserve_id
        JOIN person u ON r.person_id = u.person_id 
        JOIN item i ON ri.item_id = i.item_id
        LEFT JOIN reservation_assets ra ON ri.id = ra.reservation_item_id
        LEFT JOIN assets a ON ra.asset_id = a.asset_id
        LEFT JOIN person approved ON ri.approved_by = approved.person_id
        LEFT JOIN person checkout ON ri.checked_out_by = checkout.person_id
        LEFT JOIN person checkin ON ri.checked_in_by = checkin.person_id
        WHERE ri.status = ? AND $date_column BETWEEN ? AND ?";

// Tambah filter tambahan jika ada
if ($category_id > 0) { $sql .= " AND i.category_id = ?"; }
if (!empty($asset_code_filter)) { $sql .= " AND a.asset_code = ?"; }

$sql .= " ORDER BY $date_column DESC";

$stmt = $conn->prepare($sql);

// 4. Bind parameters ikut jumlah filter
$params = [$status_filter, $start_date, $end_date];
$types = "sss";

if ($category_id > 0) { $types .= "i"; $params[] = $category_id; }
if (!empty($asset_code_filter)) { $types .= "s"; $params[] = $asset_code_filter; }

$stmt->bind_param($types, ...$params);
$stmt->execute();
$records = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();// ------------------------------------------------------------------------
// 3. HTML GENERATION
// ------------------------------------------------------------------------

$html = file_get_contents($template_path);
$tableRows = '';

if (empty($records)) {
    
    $tableRows = '<tr><td colspan="9" style="text-align:center; padding: 20px; color: #CC0000;">No returned items found for this period.</td></tr>'; // Ubah colspan ke 9 (10 lajur - 1)
} else {
    $count = 1;
    foreach ($records as $record) {
        
        // Tetapkan Tarikh Check Out/Reserve Date untuk paparan
        $checkout_date_str = $record['checked_out_on'] ?? $record['return_date']; // Fallback jika checked_out_on NULL
        $checkout_date_obj = new DateTime($checkout_date_str); 
        $return_date_obj = new DateTime($record['return_date']);
        
        // *** DURATION LOGIC DIHAPUSKAN ***
        
        $asset_code = !empty($record['asset_code']) ? htmlspecialchars($record['asset_code']) : 'N/A';
        $return_condition = !empty($record['return_condition']) ? htmlspecialchars($record['return_condition']) : 'Not specified'; 
        
        
        $approved_by = !empty($record['approved_by_name']) ? htmlspecialchars($record['approved_by_name']) : 'N/A';
        $checked_out_by = !empty($record['checked_out_by_name']) ? htmlspecialchars($record['checked_out_by_name']) : 'N/A';
        $checked_in_by = !empty($record['checked_in_by_name']) ? htmlspecialchars($record['checked_in_by_name']) : 'N/A';
        
        // Item Details 
        $item_details = '<div class="item-details">' . 
                            '<strong>' . htmlspecialchars($record['item_name']) . ':</strong>' . 
                            '<br>' . 
                            'Asset Code: ' . $asset_code . 
                            '</div>';

        $tableRows .= '<tr>
            <td>' . $count++ . '</td>
            <td>' . htmlspecialchars($record['user_name']) . '</td>
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


// Replace placeholders in the HTML template
$html = str_replace('{{logo_path}}', $logo_file_path, $html); 
$html = str_replace('{{start_date}}', date("d M Y", strtotime($start_date)), $html);
$html = str_replace('{{end_date}}', date("d M Y", strtotime($end_date)), $html);
$html = str_replace('{{table_rows}}', $tableRows, $html);

// ------------------------------------------------------------------------
// 4. Mpdf GENERATION & OUTPUT
// ------------------------------------------------------------------------

try {
    
    $mpdf = new Mpdf(['format' => 'A4-L']); // A4-L: Landscape
    $mpdf->SetTitle('Equipment Return Report'); 
    
    $mpdf->WriteHTML($html);
    
    // Output PDF ke pelayar sebagai muat turun ('D')
    $mpdf->Output('Return-Report_' . $start_date . '_to_' . $end_date . '.pdf', 'D'); 
} catch (MpdfException $e) {
    
    echo 'mPDF Error: ' . $e->getMessage();
}

exit();
// TIADA TAG PENUTUP ?>