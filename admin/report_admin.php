<?php
// PHP LOGIC (PART 1)
session_start();
include '../config.php';
include_once '../logger.php'; 

// Fungsi pembantu untuk membina query string pagination
function build_pagination_query($page_param_name, $page_number) {
    $params = $_GET; // Dapatkan semua parameter URL sedia ada
    
    // Set parameter tab (jika belum ada)
    if (!isset($params['tab'])) {
        if ($page_param_name == 'page_returns') {
            $params['tab'] = 'returns';
        } elseif ($page_param_name == 'page_logs') {
            $params['tab'] = 'activity';
        }
    }
    
    $params[$page_param_name] = $page_number; // Tetapkan nombor halaman baru
    // Pastikan parameter pagination lain dibuang jika menukar tab
    if ($page_param_name == 'page_returns' && isset($params['page_logs'])) unset($params['page_logs']);
    if ($page_param_name == 'page_logs' && isset($params['page_returns'])) unset($params['page_returns']);
    
    return http_build_query($params); // Bina semula query string
}

// --- Pengesahan (pastikan admin sudah log masuk) ---
if (!isset($_SESSION['admin_id'])) {
    header("Location: ../login.php");
    exit();
}
$admin_id = (int)$_SESSION['admin_id'];

// --- Dapatkan Info Admin ---
$admin = array('name' => 'Admin');
if ($stmt_admin = $conn->prepare("SELECT name FROM admin WHERE admin_id = ?")) {
    $stmt_admin->bind_param("i", $admin_id);
    $stmt_admin->execute();
    $stmt_admin->bind_result($aname);
    if ($stmt_admin->fetch()) {
        $admin['name'] = $aname;
    }
    $stmt_admin->close();
}

// --- Tentukan Tab Aktif ---
$active_tab = (isset($_GET['tab']) && $_GET['tab'] == 'activity') ? 'activity' : 'returns';


// =======================================================
// --- SEKSYEN 1: LAPORAN PEMULANGAN (TAB RETURNS) ---
// =======================================================
$records = array();
$categories_result = $conn->query("SELECT category_id, category_name FROM categories ORDER BY category_name ASC");
$categories = $categories_result->fetch_all(MYSQLI_ASSOC);

// Tetapan Pagination
$items_per_page_returns = 10;
$page_returns = isset($_GET['page_returns']) ? (int)$_GET['page_returns'] : 1;
if ($page_returns < 1) $page_returns = 1;

// Borang ini GUNA GET
$report_start_date = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-01');
$report_end_date = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-t');
$report_category_id = isset($_GET['category_id']) ? (int)$_GET['category_id'] : 0;

// Menetapkan nilai drop-down bulan/tahun
$current_month = date('m', strtotime($report_start_date));
$current_year = date('Y', strtotime($report_start_date));

// Query Pembinaan
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

// 1. Query untuk COUNT
$stmt_count_report = $conn->prepare("SELECT COUNT(ri.id) " . $sql_base_report . $sql_where_report);
if ($stmt_count_report) {
    $bind_params_count = array();
    $bind_params_count[] = $param_types_report;
    for ($i = 0; $i < count($param_values_report); $i++) {
        $bind_params_count[] = &$param_values_report[$i];
    }
    call_user_func_array(array($stmt_count_report, 'bind_param'), $bind_params_count);
    
    $stmt_count_report->execute();
    $stmt_count_report->bind_result($total_records_returns);
    $stmt_count_report->fetch();
    $stmt_count_report->close();
    
    $total_pages_returns = ceil($total_records_returns / $items_per_page_returns);
    if ($total_pages_returns == 0) $total_pages_returns = 1;
    if ($page_returns > $total_pages_returns && $total_records_returns > 0) $page_returns = $total_pages_returns;
    $offset_returns = ($page_returns - 1) * $items_per_page_returns; 
} else {
    // Jika tiada rekod atau ralat, tetapkan nilai lalai
    $total_records_returns = 0;
    $total_pages_returns = 1;
    $offset_returns = 0;
}

// 2. Query untuk SELECT
$sql_report = "SELECT 
                 u.name AS user_name, i.item_name, a.asset_code, c.category_name,
                 ri.reserve_date, ri.return_date, ri.return_condition,
                 COALESCE(tech.name, adm.name) AS technician_name
              " . $sql_base_report . $sql_where_report . " 
              ORDER BY ri.return_date DESC
              LIMIT ? OFFSET ?";

$param_values_select = array_merge($param_values_report); // Salin parameter asal
$param_types_select = $param_types_report . "ii"; 
$param_values_select[] = $items_per_page_returns;
$param_values_select[] = $offset_returns;

$stmt_report = $conn->prepare($sql_report);
if ($stmt_report) {
    $bind_params_select = array();
    $bind_params_select[] = $param_types_select;
    for ($i = 0; $i < count($param_values_select); $i++) {
        $bind_params_select[] = &$param_values_select[$i];
    }
    call_user_func_array(array($stmt_report, 'bind_param'), $bind_params_select);
    $stmt_report->execute();
    $result_report = $stmt_report->get_result();
    $records = $result_report->fetch_all(MYSQLI_ASSOC);
    $stmt_report->close();
}


// =======================================================
// --- SEKSYEN 2: LOG AKTIVITI (TAB ACTIVITY) ---
// =======================================================
$logs = array();

// Tetapan Pagination
$items_per_page_logs = 10;
$page_logs = isset($_GET['page_logs']) ? (int)$_GET['page_logs'] : 1;
if ($page_logs < 1) $page_logs = 1;

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

// 1. Query untuk COUNT
$stmt_count_log = $conn->prepare("SELECT COUNT(log_id) " . $sql_base_log . $sql_where_log);
if ($stmt_count_log) {
    $bind_params_count_log = array();
    $bind_params_count_log[] = $param_types_log;
    for ($i = 0; $i < count($param_values_log); $i++) {
        $bind_params_count_log[] = &$param_values_log[$i];
    }
    call_user_func_array(array($stmt_count_log, 'bind_param'), $bind_params_count_log);

    $stmt_count_log->execute();
    $stmt_count_log->bind_result($total_records_logs);
    $stmt_count_log->fetch();
    $stmt_count_log->close();
    
    $total_pages_logs = ceil($total_records_logs / $items_per_page_logs);
    if ($total_pages_logs == 0) $total_pages_logs = 1;
    if ($page_logs > $total_pages_logs && $total_records_logs > 0) $page_logs = $total_pages_logs;
    $offset_logs = ($page_logs - 1) * $items_per_page_logs; 
} else {
    $total_records_logs = 0;
    $total_pages_logs = 1;
    $offset_logs = 0;
}


// 2. Query untuk SELECT
$sql_log = "SELECT log_id, timestamp, user_type, user_id, action, details, ip_address 
              " . $sql_base_log . $sql_where_log . " 
              ORDER BY timestamp DESC
              LIMIT ? OFFSET ?"; // Order by DESC untuk log terbaru di atas

$param_values_select_log = array_merge($param_values_log); // Salin parameter asal
$param_types_select_log = $param_types_log . "ii"; 
$param_values_select_log[] = $items_per_page_logs;
$param_values_select_log[] = $offset_logs;

$stmt_log = $conn->prepare($sql_log);
if ($stmt_log) {
    $bind_params_select_log = array();
    $bind_params_select_log[] = $param_types_select_log;
    for ($i = 0; $i < count($param_values_select_log); $i++) {
        $bind_params_select_log[] = &$param_values_select_log[$i];
    }
    call_user_func_array(array($stmt_log, 'bind_param'), $bind_params_select_log);
    $stmt_log->execute();
    $result_log = $stmt_log->get_result();
    $logs = $result_log->fetch_all(MYSQLI_ASSOC);
    $stmt_log->close();
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1"> 
    <title>System Reports — UniKL Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', 'Segoe UI', sans-serif; background-color: #f8fafc; color: #334155; min-height: 100vh; overflow-x: hidden;}
        
        /* DESKTOP STYLES */
        .sidebar { width: 250px; position: fixed; top: 0; bottom: 0; left: 0; background: #ffffff; padding: 20px; border-right: 1px solid #e5e7eb; z-index: 1000; display: flex; flex-direction: column; justify-content: space-between; transition: transform 0.3s ease; }
        .sidebar-overlay { display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0, 0, 0, 0.5); z-index: 1040; }
        .sidebar-overlay.active { display: block; }
        .sidebar-header { display: flex; align-items: center; gap: 12px; margin-bottom: 30px; }
        .logo-icon { width: 40px; height: 40px; background-color: #3b82f6; color: white; border-radius: 8px; display: flex; align-items: center; justify-content: center; font-size: 20px; }
        .logo-text strong { display: block; font-size: 16px; color: #1e293b; }
        .logo-text span { font-size: 12px; color: #94a3b8; }
        .sidebar a { display: flex; align-items: center; gap: 12px; color: #64748b; text-decoration: none; padding: 12px 15px; margin-bottom: 8px; border-radius: 8px; font-weight: 500; font-size: 15px; transition: all 0.2s ease-in-out; }
        .sidebar a.active, .sidebar a:hover { background: #3b82f6; color: #fff; }
        .sidebar a.logout-link { color: #ef4444; font-weight: 600; margin-top: auto; }
        .sidebar a.logout-link:hover { color: #fff; background: #ef4444; }
        .main-content { margin-left: 250px; transition: margin-left 0.3s ease; }
        .topbar { background: #ffffff; padding: 15px 30px; display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid #e5e7eb; }
        .topbar h3 { font-weight: 600; margin: 0; color: #1e293b; font-size: 22px; }
        .topbar .admin-profile { display: flex; align-items: center; gap: 12px; }
        .topbar .admin-name { font-weight: 600; font-size: 15px; color: #334155; }
        .container-fluid { padding: 30px; }
        .card { border-radius: 16px; padding: 25px; box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05); background: #fff; margin-bottom: 25px; border: 1px solid #e2e8f0; }
        .card h5 { font-weight: 600; color: #1e293b; }
        .table thead th { background: #f8fafc; color: #64748b; border: none; font-weight: 600; text-transform: uppercase; font-size: 12px; }
        .table tbody td { border-bottom: 1px solid #f1f5f9; }
        .table tbody tr:last-child td { border-bottom: none; }
        .badge { font-size: 0.8rem; padding: 0.4em 0.6em; }
        /* Gaya untuk Tab */
        .nav-tabs { border-bottom: 2px solid #e2e8f0; margin-bottom: 0; }
        .nav-tabs .nav-link { 
            border: none; 
            border-bottom: 2px solid transparent;
            color: #64748b;
            font-weight: 600;
            padding: 12px 20px;
            margin-bottom: -2px; 
        }
        .nav-tabs .nav-link.active {
            color: #3b82f6;
            background-color: transparent;
            border-color: #3b82f6;
        }
        .tab-content .tab-pane .card {
            border-top-left-radius: 0;
            border-top: none;
        }
        /* Gaya untuk Pagination */
        .pagination .page-item .page-link {
            border-radius: 8px;
            margin: 0 3px;
            border: 1px solid #e2e8f0;
            color: #3b82f6;
        }
        .pagination .page-item.active .page-link {
            background-color: #3b82f6;
            border-color: #3b82f6;
            color: #fff;
        }
        .pagination .page-item.disabled .page-link {
            color: #94a3b8;
        }

        /* --- MOBILE VIEW (MAX-WIDTH 768px) --- */
        #sidebar-toggle-btn {
            display: none; /* Default: hide on desktop */
            background: none;
            border: none;
            color: #334155;
            font-size: 20px;
            padding: 0;
            margin-right: 15px;
        }

        @media (max-width: 768px) {
            /* GENERAL LAYOUT */
            #sidebar-toggle-btn { display: block; }
            .sidebar { transform: translateX(-100%); box-shadow: 0 0 10px rgba(0, 0, 0, 0.15); z-index: 1050; }
            .sidebar.open { transform: translateX(0); }
            .main-content { margin-left: 0; width: 100%; }
            .topbar { padding: 10px 15px; justify-content: flex-start; } 
            .topbar h3 { font-size: 16px; flex-grow: 1; }
            .topbar .admin-name { display: none; }
            .topbar .admin-profile { margin-left: auto; }
            .container-fluid { padding: 10px 5px; }
            .card { padding: 15px; margin-bottom: 15px; }
            
            /* TABS */
            .nav-tabs .nav-link { padding: 10px 12px; font-size: 14px; }

            /* FILTER FORMS */
            #reportForm .row, #logForm .row { 
                --bs-gutter-x: 0.5rem; /* Kurangkan padding sisi */
            }
            #reportForm .col-md-3, #reportForm .col-md-4, #reportForm .col-md-6, 
            #logForm .col-md-3 {
                width: 100%;
                margin-bottom: 8px;
            }
            .form-label { font-size: 14px; }
            
            /* TABLES */
            .table-responsive { overflow-x: auto; display: block; width: 100%; }
            .table { width: 100%; min-width: 650px; } /* Force minimum width to enable scrolling */
            
            .table thead th {
                font-size: 10px;
                padding: 0.5rem 0.3rem;
                white-space: nowrap;
            }
            .table tbody td {
                padding: 0.4rem 0.3rem;
                font-size: 14px;
            }
            /* Export buttons layout */
            .d-flex.justify-content-between.align-items-center.mb-3 {
                flex-direction: column;
                align-items: flex-start !important;
                gap: 10px;
            }
            .d-flex.justify-content-between.align-items-center.mb-3 > div, 
            .d-flex.justify-content-between.align-items-center.mb-3 > a,
            .d-flex.justify-content-between.align-items-center.mb-3 > div a {
                width: 100%;
                text-align: center;
            }
            .d-flex.justify-content-between.align-items-center.mb-3 > div a:first-child {
                margin-bottom: 5px;
            }
        }
    </style>
</head>
<body>

<div class="sidebar-overlay" id="sidebar-overlay"></div>

<div class="sidebar" id="admin-sidebar">
    <div>
        <div class="sidebar-header">
            <div class="logo-icon"><i class="fa-solid fa-user-shield"></i></div>
            <div class="logo-text"><strong>UniKL Admin</strong><span>System Control</span></div>
        </div>
        <a href="manageItem_admin.php"><i class="fa-solid fa-box-archive"></i> Manage Items</a>
        <a href="manage_accounts.php"><i class="fa-solid fa-users-cog"></i> Manage Accounts</a>
        <a href="report_admin.php" class="active"><i class="fa-solid fa-chart-pie"></i> System Report</a>
    </div>
    <a href="logout.php" class="logout-link"><i class="fa-solid fa-right-from-bracket"></i> Logout</a>
</div>

<div class="main-content">
    <div class="topbar">
        <button id="sidebar-toggle-btn" class="me-3"><i class="fa fa-bars"></i></button>
        <h3>System Reports</h3>
        <div class="admin-profile">
            <span class="admin-name"><?= htmlspecialchars($admin['name']) ?></span>
            <a href="profile_admin.php" title="Go to My Profile" style="color: inherit; text-decoration: none;">
                <i class="fa-solid fa-user-circle fa-2x text-secondary"></i>
            </a>
        </div>
    </div>
    <div class="container-fluid">

        <ul class="nav nav-tabs" id="reportTab" role="tablist">
            <li class="nav-item" role="presentation">
                <a class="nav-link <?php if($active_tab == 'returns') echo 'active'; ?>" 
                   href="?tab=returns" id="returns-tab" role="tab">
                    <i class="fa-solid fa-right-left me-2"></i> Returned Items Report
                </a>
            </li>
            <li class="nav-item" role="presentation">
                <a class="nav-link <?php if($active_tab == 'activity') echo 'active'; ?>" 
                   href="?tab=activity" id="activity-tab" role="tab">
                    <i class="fa-solid fa-clipboard-list me-2"></i> Activity Log
                </a>
            </li>
        </ul>

        <div class="tab-content" id="reportTabContent">
            
            <div class="tab-pane fade <?php if($active_tab == 'returns') echo 'show active'; ?>" 
                 id="returns-pane" role="tabpanel">
                
                <div class="card p-4">
                    <h5 class="mb-3"><i class="fa-solid fa-filter me-2"></i>Filter Returned Items</h5>
                    <form method="GET" action="report_admin.php" id="reportForm">
                        <input type="hidden" name="tab" value="returns">
                        
                        <div class="row g-3 mb-3">
                            <div class="col-md-3">
                                <label for="month_filter" class="form-label fw-bold">Select Month</label>
                                <select id="month_filter" class="form-select">
                                    <?php for ($m = 1; $m <= 12; $m++) {
                                        $month_name = date('F', mktime(0, 0, 0, $m, 1));
                                        $selected = ($m == $current_month) ? 'selected' : '';
                                        echo "<option value='$m' $selected>$month_name</option>";
                                    } ?>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label for="year_filter" class="form-label fw-bold">Select Year</label>
                                <select id="year_filter" class="form-select">
                                    <?php $start_year = date('Y') - 5; $end_year = date('Y');
                                    for ($y = $end_year; $y >= $start_year; $y--) {
                                        $selected = ($y == $current_year) ? 'selected' : '';
                                        echo "<option value='$y' $selected>$y</option>";
                                    } ?>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label for="category_filter" class="form-label fw-bold">Filter by Category</label>
                                <select id="category_filter" name="category_id" class="form-select">
                                    <option value="0">All Categories</option>
                                    <?php foreach($categories as $cat): ?>
                                        <option value="<?= $cat['category_id'] ?>" <?= ($cat['category_id'] == $report_category_id) ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($cat['category_name']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <hr>
                        <div class="row g-3 align-items-end">
                            <div class="col-md-4">
                                <label for="start_date" class="form-label fw-bold">Start Date</label>
                                <input type="text" id="start_date" name="start_date" class="form-control" value="<?= htmlspecialchars($report_start_date) ?>">
                            </div>
                            <div class="col-md-4">
                                <label for="end_date" class="form-label fw-bold">End Date</label>
                                <input type="text" id="end_date" name="end_date" class="form-control" value="<?= htmlspecialchars($report_end_date) ?>">
                            </div>
                            <div class="col-md-4">
                                <button type="submit" class="btn btn-primary w-100"><i class="fa-solid fa-search me-2"></i>Apply Filters</button>
                            </div>
                        </div>
                    </form>
                    
                    <hr class="my-4">
                    
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h5 class="mb-0">Returned Items (<?= $total_records_returns ?> records found)</h5>
                        <div>
                            <a href="generate_pdf_admin.php?start_date=<?= urlencode($report_start_date) ?>&end_date=<?= urlencode($report_end_date) ?>&category_id=<?= $report_category_id ?>" target="_blank" class="btn btn-danger"><i class="fa-solid fa-file-pdf me-2"></i>Export as PDF</a>
                            <a href="export_excel.php?export=returns&start_date=<?= urlencode($report_start_date) ?>&end_date=<?= urlencode($report_end_date) ?>&category_id=<?= $report_category_id ?>" target="_blank" class="btn btn-success">
                            <i class="fa-solid fa-file-excel me-2"></i>Export to Excel (CSV)
                        </a>                    
                        </div>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle">
                            <thead>
                                <tr>
                                    <th>User</th>
                                    <th>Item Details</th>
                                    <th>Category</th>
                                    <th>Borrow Date</th>
                                    <th>Return Date</th>
                                    <th>Return Condition</th>
                                    <th>Handled By</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($records)): ?>
                                    <tr><td colspan="7" class="text-center text-muted py-5">No records found for the selected filters.</td></tr>
                                <?php else: foreach ($records as $record): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($record['user_name']) ?></td>
                                        <td>
                                            <strong><?= htmlspecialchars($record['item_name']) ?></strong>
                                            <small class="text-muted d-block">Asset: <?= htmlspecialchars($record['asset_code'] ?: 'N/A') ?></small>
                                        </td>
                                        <td><?= htmlspecialchars($record['category_name']) ?></td>
                                        <td><?= date("d M Y", strtotime($record['reserve_date'])) ?></td>
                                        <td><?= date("d M Y", strtotime($record['return_date'])) ?></td>
                                        <td class="text-muted"><?= htmlspecialchars($record['return_condition'] ?: 'Not specified') ?></td>
                                        <td><?= htmlspecialchars($record['technician_name'] ?: 'N/A') ?></td>
                                    </tr>
                                <?php endforeach; endif; ?>
                            </tbody>
                        </table>
                    </div>
                    
                    <nav aria-label="Returned Items Pagination" class="mt-4">
                        <p class="text-center text-muted small mb-2">
                            Showing page <?= $page_returns ?> of <?= $total_pages_returns ?> (Total <?= $total_records_returns ?> records)
                        </p>
                        <ul class="pagination justify-content-center">
                            <?php for ($i = 1; $i <= $total_pages_returns; $i++): ?>
                                <li class="page-item <?php if ($i == $page_returns) echo 'active'; ?>">
                                    <a class="page-link" href="?<?= build_pagination_query('page_returns', $i) ?>">
                                        <?= $i ?>
                                    </a>
                                </li>
                            <?php endfor; ?>
                        </ul>
                    </nav>
                    
                </div>
            </div>

            <div class="tab-pane fade <?php if($active_tab == 'activity') echo 'show active'; ?>" 
                 id="activity-pane" role="tabpanel">
                
                <div class="card p-4">
                    <h5 class="mb-3"><i class="fa-solid fa-filter me-2"></i>Filter Activity Logs</h5>
                    <form method="GET" action="report_admin.php" id="logForm">
                        <input type="hidden" name="tab" value="activity">
                        
                        <div class="row g-3">
                            <div class="col-md-3">
                                <label for="log_start_date" class="form-label fw-bold">Start Date</label>
                                <input type="text" id="log_start_date" name="log_start_date" class="form-control" value="<?= htmlspecialchars($log_start_date) ?>">
                            </div>
                            <div class="col-md-3">
                                <label for="log_end_date" class="form-label fw-bold">End Date</label>
                                <input type="text" id="log_end_date" name="log_end_date" class="form-control" value="<?= htmlspecialchars($log_end_date) ?>">
                            </div>
                            <div class="col-md-3">
                                <label for="user_type" class="form-label fw-bold">User Type</label>
                                <select id="user_type" name="user_type" class="form-select">
                                    <option value="">All Types</option>
                                    <option value="admin" <?= ($log_user_type == 'admin') ? 'selected' : '' ?>>Admin</option>
                                    <option value="user" <?= ($log_user_type == 'user') ? 'selected' : '' ?>>User</option>
                                    <option value="tech" <?= ($log_user_type == 'tech') ? 'selected' : '' ?>>Technician</option>
                                    <option value="system" <?= ($log_user_type == 'system') ? 'selected' : '' ?>>System</option>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label for="search" class="form-label fw-bold">Search Details</label>
                                <input type="text" id="search" name="search" class="form-control" value="<?= htmlspecialchars($log_search) ?>" placeholder="e.g., 'LOGIN' or 'Projector'">
                            </div>
                        </div>
                        <hr>
                        <div class="text-end">
                            <a href="?tab=activity" class="btn btn-secondary"><i class="fa-solid fa-eraser me-2"></i>Reset</a>
                            <button type="submit" class="btn btn-primary"><i class="fa-solid fa-search me-2"></i>Apply Log Filters</button>
                        </div>
                    </form>
                    
                    <hr class="my-4">

                    <div class="d-flex justify-content-between align-items-center mb-3">
                    <h5 class="mb-0">Log Records (<?= $total_records_logs ?> records found)</h5>
        
                    <a href="export_excel.php?export=activity&log_start_date=<?= urlencode($log_start_date) ?>&log_end_date=<?= urlencode($log_end_date) ?>&user_type=<?= urlencode($log_user_type) ?>&search=<?= urlencode($log_search) ?>" target="_blank" class="btn btn-success">
                        <i class="fa-solid fa-file-excel me-2"></i>Export to Excel (CSV)
                    </a>
                    </div>
                       <div class="table-responsive">
                        <table class="table table-hover align-middle">
                            <thead>
                                <tr>
                                    <th>Timestamp</th>
                                    <th>User</th>
                                    <th>Action</th>
                                    <th>Details</th>
                                    <th>IP Address</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($logs)): ?>
                                    <tr><td colspan="5" class="text-center text-muted py-5">No activity logs found for the selected filters.</td></tr>
                                <?php else: foreach ($logs as $log): ?>
                                    <tr>
                                        <td style="white-space: nowrap;"><?= date("d M Y, h:i:s A", strtotime($log['timestamp'])) ?></td>
                                        <td>
                                            <?php 
                                            $user_type = htmlspecialchars($log['user_type']);
                                            $badge_class = 'bg-secondary';
                                            if ($user_type == 'admin') $badge_class = 'bg-danger text-white';
                                            if ($user_type == 'user') $badge_class = 'bg-primary text-white';
                                            if ($user_type == 'tech') $badge_class = 'bg-info text-dark';
                                            ?>
                                            <span class="badge <?= $badge_class ?>"><?= ucfirst($user_type) ?></span>
                                            <small class="d-block text-muted">ID: <?= htmlspecialchars($log['user_id'] ?: 'N/A') ?></small>
                                        </td>
                                        <td>
                                            <strong class="text-primary"><?= htmlspecialchars($log['action']) ?></strong>
                                        </td>
                                        <td><?= htmlspecialchars($log['details']) ?></td>
                                        <td class="text-muted"><small><?= htmlspecialchars($log['ip_address']) ?></small></td>
                                    </tr>
                                <?php endforeach; endif; ?>
                            </tbody>
                        </table>
                    </div>
                    
                    <nav aria-label="Activity Log Pagination" class="mt-4">
                        <p class="text-center text-muted small mb-2">
                            Showing page <?= $page_logs ?> of <?= $total_pages_logs ?> (Total <?= $total_records_logs ?> records)
                        </p>
                        <ul class="pagination justify-content-center">
                            <?php for ($i = 1; $i <= $total_pages_logs; $i++): ?>
                                <li class="page-item <?php if ($i == $page_logs) echo 'active'; ?>">
                                    <a class="page-link" href="?<?= build_pagination_query('page_logs', $i) ?>">
                                        <?= $i ?>
                                    </a>
                                </li>
                            <?php endfor; ?>
                        </ul>
                    </nav>
                    
                </div>
            </div>

        </div> 
    </div> 
</div> 

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
<script>
    // --- JS UNTUK TOGGLE SIDEBAR (MOBILE ONLY) ---
    document.addEventListener('DOMContentLoaded', function() {
        const sidebar = document.getElementById('admin-sidebar');
        const toggleBtn = document.getElementById('sidebar-toggle-btn');
        const overlay = document.getElementById('sidebar-overlay');
        
        if (toggleBtn) {
            function toggleSidebar() {
                sidebar.classList.toggle('open');
                overlay.classList.toggle('active');
            }

            toggleBtn.addEventListener('click', toggleSidebar);
            overlay.addEventListener('click', toggleSidebar);
            
            // Tutup sidebar jika pautan diklik (untuk navigasi)
            const sidebarLinks = sidebar.querySelectorAll('a');
            sidebarLinks.forEach(link => {
                link.addEventListener('click', function() {
                    if (window.innerWidth <= 768) {
                        // Tambah sedikit kelewatan untuk membolehkan navigasi berlaku
                        setTimeout(() => { 
                            sidebar.classList.remove('open');
                            overlay.classList.remove('active');
                        }, 100);
                    }
                });
            });
        }
        
        // --- BLOK KOD YANG MENYEBABKAN MASALAH TELAH DIBUANG ---
        // Kod yang cuba .click() tab (returnsTab / activityTab) telah dipadam
        // Kerana PHP anda sudah mengendalikan tab 'active' dengan betul.
        // ---
    });

    // --- JS UNTUK TAB 1 (RETURNED ITEMS) ---
    flatpickr("#start_date", { dateFormat: "Y-m-d" });
    flatpickr("#end_date", { dateFormat: "Y-m-d" });

    var monthFilter = document.getElementById('month_filter');
    var yearFilter = document.getElementById('year_filter');
    var categoryFilter = document.getElementById('category_filter');
    
    function updateAndSubmit() {
        var year = yearFilter.value;
        var month = monthFilter.value;
        
        // Cipta objek URLSearchParams untuk kekalkan filter lain
        var params = new URLSearchParams(window.location.search);
        
        // Tetapkan tarikh
        var startDate = new Date(year, month - 1, 1);
        var endDate = new Date(year, month, 0);
        
        var formatDate = function(date) {
            var y = date.getFullYear();
            // Pembetulan: Pastikan concatenation menggunakan '+'
            var m = ('0' + (date.getMonth() + 1)).slice(-2); 
            var d = ('0' + date.getDate()).slice(-2);
            return y + '-' + m + '-' + d;
        };

        // Set nilai pada URL params
        params.set('start_date', formatDate(startDate));
        params.set('end_date', formatDate(endDate));
        params.set('category_id', categoryFilter.value); // Ambil nilai kategori juga
        params.set('tab', 'returns'); // Pastikan tab betul
        params.delete('page_returns'); // Reset ke halaman 1 bila filter
        
        // Submit borang dengan redirect GET
        window.location.search = params.toString();
    }

    // Elak 'null' error
    if (monthFilter) monthFilter.addEventListener('change', updateAndSubmit);
    if (yearFilter) yearFilter.addEventListener('change', updateAndSubmit);
    if (categoryFilter) categoryFilter.addEventListener('change', updateAndSubmit);

    // --- JS UNTUK TAB 2 (ACTIVITY LOG) ---
    flatpickr("#log_start_date", { dateFormat: "Y-m-d" });
    flatpickr("#log_end_date", { dateFormat: "Y-m-d" });
    
</script>
</body>
</html>