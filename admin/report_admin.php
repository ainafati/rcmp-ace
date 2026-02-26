<?php
session_start();
include '../config.php';
include_once '../logger.php';

function build_pagination_query($page_param_name, $page_number) {
    $params = $_GET;
    if (!isset($params['tab'])) {
        if ($page_param_name == 'page_returns') {
            $params['tab'] = 'returns';
        } elseif ($page_param_name == 'page_logs') {
            $params['tab'] = 'activity';
        }
    }
    $params[$page_param_name] = $page_number;
    if ($page_param_name == 'page_returns' && isset($params['page_logs'])) unset($params['page_logs']);
    if ($page_param_name == 'page_logs' && isset($params['page_returns'])) unset($params['page_returns']);
    return http_build_query($params);
}

$allowed_role = 'Admin';
if (!isset($_SESSION['person_id']) || $_SESSION['logged_in_role'] !== $allowed_role) {
    header("Location: login.php");
    exit();
}

$person_id = (int)$_SESSION['person_id'];

// 1. Ambil nama dari Session (sebab session dah ada nama masa login)
$fullName = $_SESSION['name'] ?? 'Admin'; 

// 2. Logik buang Bin / Binti / A/L / A/P
$lowerName = strtolower($fullName);
$shortName = $fullName; // Default

// Senarai pemisah yang biasa digunakan di Malaysia
$separators = [' binti ', ' bin ', ' a/l ', ' a/p '];

foreach ($separators as $sep) {
    $pos = strpos($lowerName, $sep);
    if ($pos !== false) {
        $shortName = substr($fullName, 0, $pos);
        break; // Berhenti bila dah jumpa satu
    }
}

// 3. Jika masih panjang (tiada bin/binti), ambil 2 perkataan pertama sahaja
$parts = explode(' ', trim($shortName));
if (count($parts) > 2) {
    $displayName = $parts[0] . ' ' . $parts[1];
} else {
    $displayName = $shortName;
}

// Pastikan displayName bersih untuk display
$displayName = htmlspecialchars(trim($displayName));


// --- Handle Admin Name Display ---
$full_name = isset($_SESSION['name']) ? $_SESSION['name'] : 'Admin';
$name_parts = explode(' ', trim($full_name));
$admin_display_name = isset($name_parts[1]) ? $name_parts[0] . ' ' . $name_parts[1] : $name_parts[0];

$active_tab = (isset($_GET['tab']) && $_GET['tab'] == 'activity') ? 'activity' : 'returns';
$categories_result = $conn->query("SELECT category_id, category_name FROM categories ORDER BY category_name ASC");
$categories = $categories_result->fetch_all(MYSQLI_ASSOC);

$items_per_page_returns = 10;

// Pastikan dia check 'page_returns' sebab itu nama param dalam link bawah
$page_returns = isset($_GET['page_returns']) ? (int)$_GET['page_returns'] : 1;
if ($page_returns < 1) $page_returns = 1;

// Ambil tarikh dari URL (GET). Jika tak ada, baru guna default bulan semasa.
$report_start_date = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-01');
$report_end_date = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-t');

// Pecahkan tarikh untuk Sync dengan Dropdown Bulan/Tahun
$current_month = date('m', strtotime($report_start_date));
$current_year = date('Y', strtotime($report_start_date));

$report_category_id = isset($_GET['category_id']) ? (int)$_GET['category_id'] : 0;
$status_filter = isset($_GET['status_filter']) ? $_GET['status_filter'] : 'Returned';
$asset_filter_code = isset($_GET['asset_code']) ? $_GET['asset_code'] : '';

$page = $page_returns;
// --- SQL Building ---
$sql_base_report = "FROM reservation_items ri
    JOIN reservations r ON ri.reserve_id = r.reserve_id
    JOIN person u ON r.person_id = u.person_id
    JOIN item i ON ri.item_id = i.item_id
    JOIN categories c ON i.category_id = c.category_id
    LEFT JOIN reservation_assets ra ON ri.id = ra.reservation_item_id
    LEFT JOIN assets a ON ra.asset_id = a.asset_id
    LEFT JOIN person p_handler ON ri.approved_by = p_handler.person_id";

// Clause asas
$where_clauses_report = array("ri.status = ?");
$param_types_report = "s";
$param_values_report = array($status_filter);

// Filter Tarikh (Penting: Guna r.return_date untuk Returned, r.reserve_date untuk yang lain)
if ($status_filter === 'Returned') {
    $where_clauses_report[] = "r.return_date BETWEEN ? AND ?";
} else {
    $where_clauses_report[] = "r.reserve_date BETWEEN ? AND ?";
}
$param_types_report .= "ss";
$param_values_report[] = $report_start_date;
$param_values_report[] = $report_end_date;

// Filter Category
if ($report_category_id > 0) {
    $where_clauses_report[] = "i.category_id = ?";
    $param_types_report .= "i";
    $param_values_report[] = $report_category_id;
}

// Filter Asset Code (BARU)
if (!empty($asset_filter_code)) {
    $where_clauses_report[] = "a.asset_code LIKE ?";
    $param_types_report .= "s";
    $param_values_report[] = "%" . $asset_filter_code . "%";
}

$sql_where_report = " WHERE " . implode(' AND ', $where_clauses_report);
// --- Get Total Count First ---
$stmt_count_report = $conn->prepare("SELECT COUNT(ri.id) " . $sql_base_report . $sql_where_report);
if ($stmt_count_report) {
    $bind_params_count = array();
    $bind_params_count[] = $param_types_report;
    foreach ($param_values_report as $key => $value) {
        $bind_params_count[] = &$param_values_report[$key];
    }
    call_user_func_array(array($stmt_count_report, 'bind_param'), $bind_params_count);
    $stmt_count_report->execute();
    $stmt_count_report->bind_result($total_records_returns);
    $stmt_count_report->fetch();
    $stmt_count_report->close();
    
    $total_pages_returns = ceil($total_records_returns / $items_per_page_returns);
    if ($total_pages_returns == 0) $total_pages_returns = 1;
    if ($page_returns > $total_pages_returns) $page_returns = $total_pages_returns;
    $offset_returns = ($page_returns - 1) * $items_per_page_returns;
} else {
    $total_records_returns = 0;
    $total_pages_returns = 1;
    $offset_returns = 0;
}

// --- Define variables for HTML (to fix Undefined Variable errors) ---
$total_pages = $total_pages_returns;
$total_records = $total_records_returns;
$page = $page_returns;
$pagination_params = build_pagination_query('page_returns', $page);

$sql_report = "SELECT u.name AS user_name, i.item_name, a.asset_code, c.category_name,
    r.reserve_date, r.return_date, ri.return_condition, p_handler.name AS approved_by_name,
    (SELECT name FROM person WHERE person_id = ri.checked_out_by) as checked_out_by_name,
    (SELECT name FROM person WHERE person_id = ri.checked_in_by) as checked_in_by_name
    " . $sql_base_report . $sql_where_report . "
    ORDER BY r.return_date DESC LIMIT ? OFFSET ?";
	
	$stmt_report = $conn->prepare($sql_report);
if ($stmt_report) {
    $param_types_select = $param_types_report . "ii";
    $param_values_select = array_merge($param_values_report, [$items_per_page_returns, $offset_returns]);
    $bind_params_select = array($param_types_select);
    foreach ($param_values_select as $key => $value) {
        $bind_params_select[] = &$param_values_select[$key];
    }
    call_user_func_array(array($stmt_report, 'bind_param'), $bind_params_select);
    $stmt_report->execute();
    $records = $stmt_report->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt_report->close();
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Returned Items Report — UniKL Technician</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
	    <link rel="stylesheet" href="../css/style.css">
<style>

.main-content {
    /* Gunakan min-height supaya background sentiasa sekurang-kurangnya setinggi skrin */
    min-height: 100vh; 
    
    /* Warna latar belakang utama */
    background-color: #f1f5f9; 
    
    /* Pattern texture */
    background-image: url('https://www.transparenttextures.com/patterns/cubes.png');
    
    /* PENTING: Supaya pattern tidak bergerak bila kita scroll (nampak lebih kemas) */
    background-attachment: fixed;
    
    /* Tambah padding supaya content tidak rapat sangat dengan tepi skrin */
    padding: 2rem;
    
    /* Memastikan background meliputi seluruh ruang */
    background-repeat: repeat;
}

:root {
    --bg-main: #f4f7fe; /* Warna background dashboard soft blue */
    --card-shadow: 0 10px 30px rgba(132, 147, 168, 0.15);
    --primary-text: #1b2559;
}

body {
    background-color: var(--bg-main);
    font-family: 'DM Sans', 'Inter', sans-serif; /* Guna font lebih rounded */
    color: var(--primary-text);
}

/* --- Container Utama (Glass Card) --- */
.card {
    background: rgba(255, 255, 255, 0.8);
    backdrop-filter: blur(10px); /* Efek glassmorphism */
    border: none;
    border-radius: 25px; /* Lebih bulat macam dalam gambar b17eff.png */
    box-shadow: var(--card-shadow);
    padding: 30px !important;
    margin-bottom: 25px;
}

/* --- Input & Filter Section --- */
.form-select, .form-control {
    background: #ffffff !important;
    border: 1px solid #e0e5f2 !important;
    border-radius: 16px; /* Pill shape */
    padding: 12px 16px;
    font-weight: 500;
    color: var(--primary-text);
}

/* --- Table Styling (Floating Rows) --- */
.table {
    border-collapse: separate;
    border-spacing: 0 12px; /* Jarakkan row supaya nampak terapung */
}

.table thead th {
    border: none;
    color: #a3aed0; /* Warna kelabu muted untuk header */
    font-size: 0.8rem;
    text-transform: uppercase;
    letter-spacing: 1px;
    padding: 15px;
}

.table tbody tr {
    background-color: #ffffff;
    border-radius: 20px;
    transition: transform 0.2s ease;
    box-shadow: 0 4px 10px rgba(0,0,0,0.02);
}

.table tbody tr:hover {
    transform: scale(1.01); /* Efek pop-up bila hover */
    background-color: #ffffff !important;
}

.table td {
    padding: 20px 15px !important;
    border: none !important;
}

/* Round kan bucu row */
.table td:first-child { border-radius: 20px 0 0 20px; }
.table td:last-child { border-radius: 0 20px 20px 0; }

/* --- Badge Status (Modern Pill) --- */
.badge {
    padding: 8px 16px;
    border-radius: 12px;
    font-weight: 700;
    font-size: 0.75rem;
}

.badge.bg-success { background-color: #05cd991a !important; color: #05cd99 !important; }
.badge.bg-danger { background-color: #ee5d501a !important; color: #ee5d50 !important; }

/* --- Button Export (Macam image_e626b9.png) --- */
.btn-export {
    border-radius: 14px;
    padding: 8px 20px;
    font-weight: 600;
    border: 1px solid #e0e5f2;
    background: #ffffff;
    transition: 0.3s;
}

.btn-export-pdf { color: #ee5d50; border-color: #fdd8d5; }
.btn-export-excel { color: #05cd99; border-color: #bdf3e4; }

/* --- MOBILE BOTTOM NAV (THEMED DARK) --- */
@media (max-width: 991px) {
    body {
        padding-bottom: 80px; /* Ruang supaya content tak kena sorok dek bar */
    }

    .mobile-bottom-nav {
        display: flex !important;
        position: fixed;
        bottom: 0;
        left: 0;
        width: 100%;
        /* TUKAR: Warna gelap macam sidebar laptop */
        background: #1e293b !important; 
        border-top: 1px solid rgba(255, 255, 255, 0.1); 
        z-index: 10000;
        justify-content: space-around;
        padding: 12px 0;
        box-shadow: 0 -8px 25px rgba(0,0,0,0.2);
    }

    .mobile-bottom-nav a {
        /* Warna icon & teks masa tak aktif */
        color: #94a3b8 !important; 
        text-decoration: none !important;
        text-align: center;
        font-size: 11px;
        font-weight: 600;
        display: flex;
        flex-direction: column;
        align-items: center;
        gap: 4px;
        flex: 1;
        transition: 0.3s;
    }

    /* Warna Cyan bila menu aktif/tekan */
    .mobile-bottom-nav a.active {
        color: #06b6d4 !important;
    }

    .mobile-bottom-nav a i {
        font-size: 20px;
    }

    /* Tambah sikit effect bila user touch */
    .mobile-bottom-nav a:active {
        transform: scale(0.9);
        opacity: 0.8;
    }
}
    /* Sembunyikan kalau kat PC */
    @media (min-width: 992px) {
        .mobile-bottom-nav {
            display: none !important;
        }
    }
	
	.toast-container {
    z-index: 1060; /* Pastikan dia duduk atas sekali dari modal/sidebar */
}

.toast {
    border-radius: 12px;
    overflow: hidden;
    animation: slideInRight 0.5s ease-out;
}

@keyframes slideInRight {
    from { transform: translateX(100%); opacity: 0; }
    to { transform: translateX(0); opacity: 1; }
}

.toast-header {
    border-bottom: none;
}
/* --- Pagination Upgrade (Modern & Align Right) --- */
.pagination-wrapper {
    display: flex;
    justify-content: flex-end; /* Letak belah kanan */
    margin-top: 25px;
}

.pagination {
    gap: 8px;
}

.page-item .page-link {
    border: none;
    border-radius: 12px !important; /* Kotak jadi rounded */
    color: #64748b;
    font-weight: 600;
    padding: 10px 16px;
    transition: all 0.3s ease;
    background: #ffffff;
    box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05);
}

.page-item.active .page-link {
    background: linear-gradient(135deg, #06b6d4 0%, #0891b2 100%) !important;
    color: white !important;
    box-shadow: 0 10px 15px -3px rgba(6, 182, 212, 0.25);
}

.page-item.disabled .page-link {
    background: transparent;
    opacity: 0.5;
}

.page-link:hover:not(.active) {
    background: #f1f5f9;
    transform: translateY(-2px);
}

/* --- Filter & Card Woah Factor --- */
.card {
    border-radius: 24px;
    border: 1px solid rgba(255, 255, 255, 0.3);
    background: rgba(255, 255, 255, 0.9);
}

.btn-primary {
    background: linear-gradient(135deg, #1e293b 0%, #334155 100%);
    border: none;
    border-radius: 14px;
    padding: 12px 24px;
    font-weight: 600;
    transition: all 0.3s ease;
}

.btn-primary:hover {
    transform: translateY(-2px);
    box-shadow: 0 10px 20px rgba(30, 41, 59, 0.2);
}

/* Custom Table Row - Floating Effect */
.table tbody tr {
    transition: all 0.3s ease;
}

.table tbody tr:hover {
    background: #ffffff !important;
    box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.05);
    transform: scale(1.005);
}
</style>

</head>
<body>
<div class="sidebar-overlay" id="sidebarOverlay"></div> 
<div class="sidebar" id="admin-sidebar">
    <div> <div class="sidebar-header">
    <div class="logo-icon"><i class="fa-solid fa-wrench"></i></div>
    <div class="logo-text">
        <strong>UniKL Admin</strong>
        <span class="d-block">System Control</span> </div>
</div>
        
        <div class="sidebar-nav"> 
<a href="manageItem_admin.php"><i class="fa-solid fa-box-archive"></i> Manage Items</a>
        <a href="manage_accounts.php" ><i class="fa-solid fa-users-cog"></i> Manage Accounts</a>
        <a href="report_admin.php" class="active" ><i class="fa-solid fa-chart-pie"></i> System Report</a>        </div>
    </div>
    
    <div class="sidebar-footer">
        <a href="logout.php" class="logout-link"><i class="fa-solid fa-sign-out-alt"></i> Logout</a> 
    </div>
</div>


<div class="main-content">
    <div class="topbar">
        <div class="topbar-left">
            <button id="sidebarToggle" class="btn d-none">
                <i class="fas fa-bars"></i>
            </button>
            <h3 class="mb-0">Returned Item Report</h3>
        </div>

        <div class="topbar-right">
            <a href="profile_admin.php" class="user-pill text-decoration-none shadow-sm">
                    <div class="text-end me-2 d-none d-md-block">
                        <div class="user-name" style="text-transform: capitalize; font-weight: 600; color: #1e293b; line-height: 1;">
                            <?= htmlspecialchars($displayName) ?>
                        </div>
                        <small class="text-muted" style="font-size: 0.75rem;">Administrator</small>
                    </div>
                    <div class="profile-avatar">
                        <img src="https://ui-avatars.com/api/?name=<?= urlencode($displayName) ?>&background=06b6d4&color=fff" class="rounded-circle" width="35">
                    </div>
                </a>
        </div>
    </div>

	
    <div class="container-fluid">
        <div class="card p-4 mb-4">
            <h5 class="mb-3"><i class="fa-solid fa-filter me-2"></i>Filter Report Data</h5>

            <form method="GET" action="report_admin.php" id="reportForm">
                <input type="hidden" id="start_date_hidden" name="start_date" value="<?= htmlspecialchars($report_start_date) ?>">
<input type="hidden" id="end_date_hidden" name="end_date" value="<?= htmlspecialchars($report_end_date) ?>">
                
                <div class="row g-3 mb-3">
                    <div class="col-md-3 col-6">
                        <label for="month_select" class="form-label fw-bold">Select Month</label>
                        <select id="month_select" class="form-select">
                            <?php for ($m = 1; $m <= 12; $m++) {
                                $month_name = date('F', mktime(0, 0, 0, $m, 1));
                                $selected = ($m == $current_month) ? 'selected' : '';
                                
                                echo "<option value='" . str_pad($m, 2, '0', STR_PAD_LEFT) . "' $selected>$month_name</option>";
                            } ?>
                        </select>
                    </div>
                    <div class="col-md-3 col-6">
                        <label for="year_select" class="form-label fw-bold">Select Year</label>
                        <select id="year_select" class="form-select">
                            <?php $start_year = date('Y') - 5; $end_year = date('Y');
                            for ($y = $end_year; $y >= $start_year; $y--) {
                                $selected = ($y == $current_year) ? 'selected' : '';
                                echo "<option value='$y' $selected>$y</option>";
                            } ?>
                        </select>
                    </div>

                    <div class="col-md-3 col-6">
                        <label for="category_filter" class="form-label fw-bold">Filter by Category</label>
                        <select id="category_filter" name="category_id" class="form-select">
                            <option value="0">All Categories</option>
                            <?php if (!empty($categories)): foreach($categories as $cat): ?>
                                <option value="<?= $cat['category_id'] ?>" <?= ($cat['category_id'] == $category_filter_id) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($cat['category_name']) ?>
                                </option>
                            <?php endforeach; endif; ?>
                        </select>
                    </div>
                    
                    <div class="col-md-3 col-6">
                        <label for="asset_code_filter" class="form-label fw-bold">Filter by Asset Code</label>
                        <input type="text" id="asset_code_filter" name="asset_code" class="form-control" 
                            value="<?= htmlspecialchars($asset_filter_code) ?>" placeholder="e.g., LCT-001">
                    </div>

<div class="col-md-3 col-6">
    <label for="status_filter" class="form-label fw-bold">Report Type / Status</label>
    <select id="status_filter" name="status_filter" class="form-select">
        <option value="Returned" <?= ($status_filter == 'Returned') ? 'selected' : '' ?>>1. Returned Items</option>
        <option value="Cancelled" <?= ($status_filter == 'Cancelled') ? 'selected' : '' ?>>2. User Cancelled</option>
        <option value="Voided" <?= ($status_filter == 'Voided') ? 'selected' : '' ?>>3. Reservation Incomplete</option>
        <option value="Expired" <?= ($status_filter == 'Expired') ? 'selected' : '' ?>>4. Unclaimed (Expired)</option>
    </select>
</div>                </div>
                <hr>

               <div class="row g-3 align-items-end">
    <div class="col-md-4 col-12">
        <label for="start_date_display" class="form-label fw-bold">Start Date</label>
        <input type="text" id="start_date_display" class="form-control" value="<?= htmlspecialchars($report_start_date) ?>">
    </div>
    <div class="col-md-4 col-12">
        <label for="end_date_display" class="form-label fw-bold">End Date</label>
        <input type="text" id="end_date_display" class="form-control" value="<?= htmlspecialchars($report_end_date) ?>">
    </div>
    <div class="col-md-4 col-12">
        <button type="submit" class="btn btn-primary w-100"><i class="fa-solid fa-arrows-rotate me-2"></i>Apply Filters</button>
    </div>
</div>
            </form>
        </div>

        <div class="card p-4">
            <div class="d-flex justify-content-between align-items-center mb-4">
   <h5 class="fw-bold">Returned Items 
    <span class="text-muted">(<?= $total_records_returns ?> found)</span>
</h5>
    <div>
    <a href="generate_pdf_admin.php?<?= $pagination_params ?>" target="_blank" class="btn btn-export btn-export-pdf me-2">
        <i class="fa-solid fa-file-pdf me-2"></i>PDF
    </a>
    <a href="export_excel.php?<?= $pagination_params ?>" target="_blank" class="btn btn-export btn-export-excel">
        <i class="fa-solid fa-file-excel me-2"></i>Excel
    </a>
</div>
</div>

            <div class="table-responsive">
                <table class="table table-hover align-middle">
                   
                   <thead>
    <tr>
        <th>User</th>
        <th>Item Details & Status</th> 
        <th class="d-none d-md-table-cell">Category</th>
        <th class="d-none d-lg-table-cell">Reserve Date</th>
        <th><?= ($status_filter === 'Returned') ? 'Return Date' : 'Status Date' ?></th>
        <th class="d-none d-lg-table-cell">Approved By</th>
        <?php if ($status_filter === 'Returned'): ?>
            <th class="d-none d-lg-table-cell">Check Out By</th>
            <th class="d-none d-lg-table-cell">Check In By</th>
        <?php endif; ?>
    </tr>
</thead>
<tbody>
    <?php if (empty($records)): ?>
        <tr><td colspan="8" class="text-center text-muted py-5">No records found for <?= htmlspecialchars($status_filter) ?>.</td></tr>
    <?php else: foreach ($records as $record): ?>
        <tr>
            <td><?= htmlspecialchars($record['user_name']) ?></td>
            <td>
                <strong><?= htmlspecialchars($record['item_name']) ?></strong>
                <small class="text-muted d-block">Asset: <?= htmlspecialchars($record['asset_code'] ?: 'N/A') ?></small>
                
                <?php if ($status_filter === 'Returned'): ?>
                    <span class="badge bg-success">Returned</span>
                <?php elseif ($status_filter === 'Cancelled'): ?>
                    <span class="badge bg-secondary">Cancelled</span>
                <?php elseif ($status_filter === 'Voided'): ?>
                    <span class="badge bg-danger">Rejected</span>
                <?php elseif ($status_filter === 'Expired'): ?>
                    <span class="badge bg-warning text-dark">Expired</span>
                <?php endif; ?>
            </td>
            
            <td class="d-none d-md-table-cell"><?= htmlspecialchars($record['category_name']) ?></td>
            <td class="d-none d-lg-table-cell"><?= date("d M Y", strtotime($record['reserve_date'])) ?></td>
            <td>
                <?php 
                    $displayDate = ($status_filter === 'Returned') ? $record['return_date'] : $record['reserve_date'];
                    echo date("d M Y", strtotime($displayDate)); 
                ?>
            </td>
            <td class="d-none d-lg-table-cell"><?= htmlspecialchars($record['approved_by_name'] ?: 'N/A') ?></td>
            
            <?php if ($status_filter === 'Returned'): ?>
                <td class="d-none d-lg-table-cell"><?= htmlspecialchars($record['checked_out_by_name'] ?: 'N/A') ?></td>
                <td class="d-none d-lg-table-cell"><?= htmlspecialchars($record['checked_in_by_name'] ?: 'N/A') ?></td>
            <?php endif; ?>
        </tr>
    <?php endforeach; endif; ?>
</tbody>
                </table>
            </div>

<?php if ($total_pages > 1): ?>
<div class="pagination-wrapper">
    <nav aria-label="Page navigation">
        <ul class="pagination mb-0">
            <li class="page-item <?= ($page <= 1) ? 'disabled' : '' ?>">
                <a class="page-link" href="?page_returns=<?= max(1, $page - 1) ?>&<?= http_build_query(array_merge($_GET, ['page_returns' => max(1, $page - 1)])) ?>" aria-label="Previous">
                    <i class="fa-solid fa-chevron-left"></i>
                </a>
            </li>

            <?php 
            $start_page = max(1, $page - 2);
            $end_page = min($total_pages, $page + 2);

            if ($start_page > 1): ?>
                <li class="page-item"><a class="page-link" href="?page_returns=1&<?= http_build_query(array_merge($_GET, ['page_returns' => 1])) ?>">1</a></li>
                <?php if ($start_page > 2): ?><li class="page-item disabled"><span class="page-link">...</span></li><?php endif; ?>
            <?php endif; ?>

            <?php for ($i = $start_page; $i <= $end_page; $i++): ?>
                <li class="page-item <?= ($i == $page) ? 'active' : '' ?>">
                    <a class="page-link" href="?page_returns=<?= $i ?>&<?= http_build_query(array_merge($_GET, ['page_returns' => $i])) ?>"><?= $i ?></a>
                </li>
            <?php endfor; ?>

            <?php if ($end_page < $total_pages): ?>
                <?php if ($end_page < $total_pages - 1): ?><li class="page-item disabled"><span class="page-link">...</span></li><?php endif; ?>
                <li class="page-item"><a class="page-link" href="?page_returns=<?= $total_pages ?>&<?= http_build_query(array_merge($_GET, ['page_returns' => $total_pages])) ?>"><?= $total_pages ?></a></li>
            <?php endif; ?>

            <li class="page-item <?= ($page >= $total_pages) ? 'disabled' : '' ?>">
                <a class="page-link" href="?page_returns=<?= min($total_pages, $page + 1) ?>&<?= http_build_query(array_merge($_GET, ['page_returns' => min($total_pages, $page + 1)])) ?>" aria-label="Next">
                    <i class="fa-solid fa-chevron-right"></i>
                </a>
            </li>
        </ul>
    </nav>
</div>
<?php endif; ?>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
<script>
    
    const startDateDisplay = document.getElementById('start_date_display');
    const endDateDisplay = document.getElementById('end_date_display');
    const startDateHidden = document.getElementById('start_date_hidden');
    const endDateHidden = document.getElementById('end_date_hidden');

    
    flatpickr(startDateDisplay, { 
        dateFormat: "Y-m-d",
        onChange: function(selectedDates, dateStr) {
            startDateHidden.value = dateStr; 
        }
    });
    
    flatpickr(endDateDisplay, { 
        dateFormat: "Y-m-d",
        onChange: function(selectedDates, dateStr) {
            endDateHidden.value = dateStr; 
        }
    });

    const monthSelect = document.getElementById('month_select');
    const yearSelect = document.getElementById('year_select');
    const reportForm = document.getElementById('reportForm');
    const categoryFilter = document.getElementById('category_filter');

    
    const sidebar = document.getElementById('offcanvasSidebar');
    const toggleBtn = document.getElementById('sidebarToggle');
    const backdrop = document.getElementById('sidebar-backdrop');
    const body = document.body;

    function toggleSidebar() {
        body.classList.toggle('offcanvas-open');
        if (body.classList.contains('offcanvas-open')) {
            backdrop.style.display = 'block';
        } else {
            setTimeout(() => {
                backdrop.style.display = 'none';
            }, 300);
        }
    }

    if (toggleBtn) {
        toggleBtn.addEventListener('click', toggleSidebar);
    }
    
    if (backdrop) {
        backdrop.addEventListener('click', toggleSidebar);
    }
    
    
    function updateDateInputs() {
    const year = yearSelect.value;
    const month = monthSelect.value;
    
    if(!year || !month) return;

    // Cari hari terakhir bagi bulan tersebut
    const lastDay = new Date(year, month, 0).getDate();
    
    const startDate = `${year}-${month}-01`;
    const endDate = `${year}-${month}-${lastDay.toString().padStart(2, '0')}`;

    // Update Flatpickr Display
    startDateDisplay._flatpickr.setDate(startDate);
    endDateDisplay._flatpickr.setDate(endDate);

    // Update Hidden Inputs (Ini yang PHP baca)
    startDateHidden.value = startDate;
    endDateHidden.value = endDate;
}

function handleFilterChange(event) {
    // Kalau user tukar Bulan atau Tahun, kita update tarikh dulu
    if (event.target === monthSelect || event.target === yearSelect) {
        updateDateInputs();
    }
    
    // Submit form secara automatik untuk semua filter kecuali text input asset code
    if (event.target !== document.getElementById('asset_code_filter')) {
        reportForm.submit();
    }
}

    if (monthSelect) monthSelect.addEventListener('change', handleFilterChange);
    if (yearSelect) yearSelect.addEventListener('change', handleFilterChange);
    if (categoryFilter) categoryFilter.addEventListener('change', handleFilterChange);
    
</script>


<nav class="mobile-bottom-nav">
   <nav class="mobile-bottom-nav">
<a href="manageItem_admin.php"><i class="fa-solid fa-box-archive"></i> Manage Items</a>
        <a href="manage_accounts.php" ><i class="fa-solid fa-users-cog"></i> Manage Accounts</a>
        <a href="report_admin.php" class="active" ><i class="fa-solid fa-chart-pie"></i> System Report</a>        </div>
    <a href="profile_admin.php" ><i class="fa-solid fa-user"></i><span>Profile</span></a>
</nav></body>
</html>


