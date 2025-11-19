<?php
session_start();
include '../config.php';

// Path yang betul ke Composer Autoload (Naik satu tingkat dari 'technician')
require_once __DIR__ . '/../vendor/autoload.php';

use Mpdf\Mpdf;
use Mpdf\MpdfException;

// --- 1. Keamanan Sesi ---
// Menggunakan 'person_id' jika 'tech_id' tidak konsisten dengan DB schema
if (!isset($_SESSION['tech_id']) && !isset($_SESSION['person_id'])) {
    $mpdf = new Mpdf();
    $mpdf->WriteHTML('<h1>Akses Ditolak</h1><p>Sesi anda telah tamat. Sila log masuk semula.</p>');
    $mpdf->Output('error_access.pdf', 'I');
    exit();
}

// --- 2. Konfigurasi dan Pemeriksaan Templat ---

$template_path = 'pdf_template.html';
if (!file_exists($template_path)) {
    $mpdf = new Mpdf();
    $mpdf->WriteHTML('<h1>Ralat Konfigurasi</h1><p>Fail templat PDF tidak dijumpai. Sila pastikan fail bernama <strong>pdf_template.html</strong> wujud.</p>');
    $mpdf->Output('error_config.pdf', 'I');
    exit();
}

$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-01');
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-t');

// --- 3. Query SQL DIBETULKAN (Mengikut Skema DB Anda) ---
$sql = "SELECT 
            u.name AS user_name, i.item_name, a.asset_code, 
            ri.reserve_date, ri.return_date, ri.return_condition,
            handler.name AS technician_name 
        FROM reservation_items ri
        JOIN reservations r ON ri.reserve_id = r.reserve_id
        
        -- FIX: Join ke tabel person menggunakan r.person_id
        JOIN person u ON r.person_id = u.person_id 
        
        JOIN item i ON ri.item_id = i.item_id
        LEFT JOIN reservation_assets ra ON ri.id = ra.reservation_item_id
        LEFT JOIN assets a ON ra.asset_id = a.asset_id
        
        -- FIX: Join ke tabel person untuk mendapatkan nama yang meluluskan
        LEFT JOIN person handler ON ri.approved_by = handler.person_id
        
        WHERE ri.status = 'Returned' AND ri.return_date BETWEEN ? AND ?
        ORDER BY a.asset_code ASC";


$stmt = $conn->prepare($sql);
if ($stmt === false) { die("SQL Error: " . htmlspecialchars($conn->error)); }

$stmt->bind_param("ss", $start_date, $end_date);
$stmt->execute();
$result = $stmt->get_result();
$records = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();


// --- 4. Proses Data dan Ganti Templat ---

$html = file_get_contents($template_path);
$tableRows = '';

if (empty($records)) {
    // Colspan 8 untuk memadankan 8 TH dalam templat
    $tableRows = '<tr><td colspan="8" style="text-align:center; padding: 20px; color: #CC0000;">Tiada item yang dikembalikan ditemui untuk tempoh ini.</td></tr>';
} else {
    $count = 1;
    foreach ($records as $record) {
        $reserve_date_obj = new DateTime($record['reserve_date']);
        $return_date_obj = new DateTime($record['return_date']);
        $duration = $return_date_obj->diff($reserve_date_obj)->days + 1;
        
        $asset_code = !empty($record['asset_code']) ? htmlspecialchars($record['asset_code']) : 'N/A';
        $return_condition = !empty($record['return_condition']) ? htmlspecialchars($record['return_condition']) : 'Tidak dinyatakan';
        $technician_name = !empty($record['technician_name']) ? htmlspecialchars($record['technician_name']) : 'N/A';

        // Guna struktur HTML templat anda
        $item_details = '<div class="item-details">' . 
                        '<strong>' . htmlspecialchars($record['item_name']) . '</strong>' .
                        'Asset Code: ' . $asset_code . 
                        '</div>';

        $tableRows .= '<tr>
            <td>' . $count++ . '</td>
            <td>' . htmlspecialchars($record['user_name']) . '</td>
            <td>' . $item_details . '</td>
            <td>' . $reserve_date_obj->format("d M Y") . '</td>
            <td>' . $return_date_obj->format("d M Y") . '</td>
            <td>' . $duration . ' hari</td>
            <td>' . $return_condition . '</td>
            <td>' . $technician_name . '</td>
        </tr>';
    }
}


// Ganti placeholder tarikh dengan format yang kemas
$html = str_replace('{{start_date}}', date("d M Y", strtotime($start_date)), $html);
$html = str_replace('{{end_date}}', date("d M Y", strtotime($end_date)), $html);
$html = str_replace('{{table_rows}}', $tableRows, $html);


// --- 5. Output PDF Menggunakan mPDF ---

try {
    // Tetapkan mPDF untuk format A4 Landscape
    $mpdf = new Mpdf(['format' => 'A4-L']); 
    $mpdf->SetTitle('Laporan Pengembalian Peralatan UniKL');
    
    // BARIS INI DIBUANG: $mpdf->SetHeader('UniKL Equipment Return Report | Dijana: ' . date('Y-m-d H:i'));
    // BARIS INI DIBUANG: $mpdf->SetFooter('Halaman {PAGENO}');
    
    $mpdf->WriteHTML($html);
    // 'D' memaksa browser untuk memuat turun fail
    $mpdf->Output('Return-Report_' . $start_date . '_to_' . $end_date . '.pdf', 'D');
} catch (MpdfException $e) {
    // Cetak ralat mPDF jika ada
    echo 'mPDF Error: ' . $e->getMessage();
}

exit();
?>