<?php
// File: /technician/report.php
session_start();
include '../config.php';

// --- Pengesahan (pastikan teknikal sudah log masuk) ---
if (!isset($_SESSION['tech_id'])) {
    header("Location: ../login.php");
    exit();
}
$tech_id = (int)$_SESSION['tech_id'];

// Dapatkan info teknikal
$tech = ['name' => 'Technician'];
if ($stmt_tech = $conn->prepare("SELECT name FROM technician WHERE tech_id = ?")) {
    $stmt_tech->bind_param("i", $tech_id);
    $stmt_tech->execute();
    $stmt_tech->bind_result($tname);
    if ($stmt_tech->fetch()) {
        $tech['name'] = $tname;
    }
    $stmt_tech->close();
}

function get_reservation_item_count($conn, $status) {
    // Hanya kira item yang berstatus 'Pending'
    $sql = "SELECT COUNT(id) AS count 
            FROM reservation_items 
            WHERE status = ?";
    
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        error_log("Prepare failed: (" . $conn->errno . ") " . $conn->error);
        return 0;
    }
    
    $stmt->bind_param("s", $status);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    
    return $result ? (int) $result['count'] : 0;
}

// Dapatkan kiraan yang diperlukan untuk dashboard
$pending_count_for_badge = get_reservation_item_count($conn, 'Pending'); 

// Dapatkan senarai kategori untuk dropdown penapis
$categories_result = $conn->query("SELECT category_id, category_name FROM categories ORDER BY category_name ASC");
$categories = $categories_result ? $categories_result->fetch_all(MYSQLI_ASSOC) : [];

// Dapatkan Data Berdasarkan Penapis ---
// Default dates: first and last day of the current month
$start_date = isset($_POST['start_date']) ? $_POST['start_date'] : date('Y-m-01');
$end_date = isset($_POST['end_date']) ? $_POST['end_date'] : date('Y-m-t');
$category_filter_id = isset($_POST['category_id']) ? (int)$_POST['category_id'] : 0; 

// Ambil bulan/tahun daripada $start_date (yang mungkin daripada POST atau lalai)
$current_month = date('m', strtotime($start_date));
$current_year = date('Y', strtotime($start_date));


// --- LOGIK PAGINATION ---
$limit = 10; // 10 rekod per halaman
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$start = ($page - 1) * $limit; // Hitung OFFSET

$sql_base_select = "SELECT
                u.name AS user_name, i.item_name, a.asset_code, c.category_name,
                ri.reserve_date, ri.return_date, ri.return_condition,
                COALESCE(tech.name, adm.name) AS technician_name";
                
$sql_base_from = " FROM reservation_items ri
                JOIN reservations r ON ri.reserve_id = r.reserve_id
                JOIN user u ON r.user_id = u.user_id
                JOIN item i ON ri.item_id = i.item_id
                JOIN categories c ON i.category_id = c.category_id
                LEFT JOIN reservation_assets ra ON ri.id = ra.reservation_item_id
                LEFT JOIN assets a ON ra.asset_id = a.asset_id
                LEFT JOIN technician tech ON ri.approved_by = tech.tech_id
                LEFT JOIN admin adm ON ri.approved_by = adm.admin_id";

$sql_where_clauses = [
    "ri.status = 'Returned'",
    "ri.return_date BETWEEN ? AND ?"
];
$param_types = "ss";
$param_values = [$start_date, $end_date];

// Add category filter if selected
if ($category_filter_id > 0) {
    $sql_where_clauses[] = "i.category_id = ?";
    $param_types .= "i"; // 'i' for integer
    $param_values[] = $category_filter_id;
}

$sql_where = " WHERE " . implode(' AND ', $sql_where_clauses);


// 1. Dapatkan JUMLAH KESELURUHAN rekod (untuk pagination)
$sql_count = "SELECT COUNT(ri.id) AS total" . $sql_base_from . $sql_where;
$stmt_count = $conn->prepare($sql_count);
if ($stmt_count === false) { die("SQL Error: " . htmlspecialchars($conn->error)); }

$bind_params_count = [];
$bind_params_count[] = $param_types;
for ($i = 0; $i < count($param_values); $i++) {
    $bind_params_count[] = &$param_values[$i];
}
call_user_func_array([$stmt_count, 'bind_param'], $bind_params_count);
$stmt_count->execute();
$count_result = $stmt_count->get_result();
$total_records = $count_result->fetch_assoc()['total'];
$total_pages = ceil($total_records / $limit);
$stmt_count->close();


// 2. Dapatkan DATA UNTUK HALAMAN SEMASA
$sql = $sql_base_select . $sql_base_from . $sql_where . " ORDER BY ri.return_date DESC, a.asset_code ASC LIMIT ?, ?";
$param_types .= "ii";
$param_values[] = $start;
$param_values[] = $limit;

$stmt = $conn->prepare($sql);
if ($stmt === false) { die("SQL Error: " . htmlspecialchars($conn->error)); }

// Re-bind parameters for the final query
$bind_params = [];
$bind_params[] = $param_types;
for ($i = 0; $i < count($param_values); $i++) {
    $bind_params[] = &$param_values[$i];
}
call_user_func_array([$stmt, 'bind_param'], $bind_params);

$stmt->execute();
$result = $stmt->get_result();
$records = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$conn->close(); // Close connection after fetching data

// URL Parameters for Pagination Links
$pagination_params = http_build_query([
    'start_date' => $start_date, 
    'end_date' => $end_date, 
    'category_id' => $category_filter_id
]);

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
    <style>
        /* 1. FONT & BODY BACKGROUND */
        body { font-family: 'Inter', 'Segoe UI', sans-serif; background-color: #f8fafc; color: #334155; min-height: 100vh; }

        /* 2. SIDEBAR (Desktop Default: Fixed di Kiri) */
        .sidebar { 
            width: 250px; position: fixed; top: 0; bottom: 0; left: 0; background: #ffffff; padding: 20px; border-right: 1px solid #e5e7eb; 
            z-index: 1050; /* FIX Z-INDEX for Mobile */
            display: flex; flex-direction: column; justify-content: space-between; 
            transition: transform 0.3s ease-in-out; 
        }
        .sidebar-header { display: flex; align-items: center; gap: 12px; margin-bottom: 30px; }
        .logo-icon { width: 40px; height: 40px; background-color: #3b82f6; color: white; border-radius: 8px; display: flex; align-items: center; justify-content: center; font-size: 20px; }
        .logo-text strong { display: block; font-size: 16px; color: #1e293b; }
        .logo-text span { font-size: 12px; color: #94a3b8; }
        .sidebar a { display: flex; align-items: center; gap: 12px; color: #64748b; text-decoration: none; padding: 12px 15px; margin-bottom: 8px; border-radius: 8px; font-weight: 500; font-size: 15px; transition: all 0.2s ease-in-out; }
        .sidebar a.active, .sidebar a:hover { background: #3b82f6; color: #fff; }
        .sidebar a.logout-link { color: #ef4444; font-weight: 600; margin-top: auto; }
        .sidebar a.logout-link:hover { color: #fff; background: #ef4444; }
        
		/* 5. SIDEBAR BADGE STYLE (Penambahan) */
.sidebar a .badge {
    margin-left: auto; /* Tolak badge ke kanan */
    font-size: 0.75rem;
    padding: 0.4em 0.6em;
    font-weight: 700;
    border-radius: 10px;
    background-color: #ef4444; /* Merah untuk menarik perhatian */
    color: white;
}

/* Pastikan badge tidak hilang apabila item menu di-hover atau aktif */
.sidebar a.active .badge, .sidebar a:hover .badge {
    background-color: #ffffff;
    color: #ef4444; /* Warna terbalik agar kontras */
}

        /* 3. MAIN LAYOUT & TOPBAR */
        .main-content { margin-left: 250px; transition: margin-left 0.3s ease-in-out; }
        .topbar { 
            background: #ffffff; padding: 15px 30px; display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid #e5e7eb; 
            position: sticky; top: 0; z-index: 990; /* FIX Z-INDEX for Mobile */
        }
        .topbar h3 { font-weight: 600; margin: 0; color: #1e293b; font-size: 22px; }
        .topbar .technician-profile { display: flex; align-items: center; gap: 12px; }
        .topbar .tech-name { font-weight: 600; font-size: 15px; color: #334155; }
		.container-fluid { padding: 30px; }

        /* 4. CARD & TABLE STYLING */
        .card { border-radius: 16px; padding: 25px; box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05); background: #fff; margin-bottom: 25px; border: 1px solid #e2e8f0; }
        .card h5 { font-weight: 600; color: #1e293b; }
        .table thead th { background: #f8fafc; color: #64748b; border: none; font-weight: 600; text-transform: uppercase; font-size: 12px; }
        .table tbody td { border-bottom: 1px solid #f1f5f9; vertical-align: middle; }
        .table tbody tr:last-child td { border-bottom: none; }
        
        /* 5. MOBILE OPTIMIZATIONS */
        @media (max-width: 991.98px) {
             /* Sidebar Off-Canvas Logic */
            .sidebar { transform: translateX(-100%); }
            .offcanvas-open .sidebar { transform: translateX(0); }
            .main-content { margin-left: 0; padding-top: 80px; /* Space for fixed topbar */ }
            .topbar { position: fixed; width: 100%; left: 0; padding: 15px; }
            .topbar h3 { font-size: 1.2rem; }
            .container-fluid { padding: 15px; }
            .d-lg-none { display: inline-block !important; }
            
            /* Table optimization for small screens */
            .table-responsive { border: 1px solid #e2e8f0; border-radius: 12px; }
            .table-responsive > .table { margin-bottom: 0; }
            
             /* Backdrop for Off-Canvas effect */
            .offcanvas-backdrop {
                position: fixed; top: 0; left: 0; z-index: 1040; width: 100vw; height: 100vh;
                background-color: #000; opacity: 0.5; transition: opacity 0.3s ease-in-out;
                display: none; 
            }
            .offcanvas-open .offcanvas-backdrop { display: block; }
        }
        .pagination-container { display: flex; justify-content: flex-end; align-items: center; margin-top: 15px;}
        .page-link { border-radius: 8px !important; margin: 0 2px; }
    </style>
</head>
<body>

<div class="offcanvas-backdrop fade" id="sidebar-backdrop"></div>

<div class="sidebar" id="offcanvasSidebar">
    <div>
        <div class="sidebar-header">
            <div class="logo-icon"><i class="fa-solid fa-wrench"></i></div>
            <div class="logo-text"><strong>UniKL Technician</strong><span>System Support</span></div>
        </div>
        <a href="dashboard_tech.php"><i class="fa-solid fa-table-columns"></i> Dashboard</a>
        <a href="check_out.php">
            <i class="fa-solid fa-dolly"></i> Manage Requests
            <?php if ($pending_count_for_badge > 0): ?>
                <span class="badge rounded-pill bg-danger"><?= $pending_count_for_badge ?></span>
            <?php endif; ?>
        </a>
        <a href="manageItem_tech.php"><i class="fa-solid fa-box-archive"></i> Manage Items</a>
        <a href="report.php" class="active"><i class="fa-solid fa-chart-line"></i> Report</a>
    </div>
    <a href="logout.php" class="logout-link"><i class="fa-solid fa-right-from-bracket"></i> Logout</a>
</div>

<div class="main-content">
    <div class="topbar">
        <button class="btn btn-sm btn-outline-primary d-lg-none me-3" type="button" id="sidebarToggle" aria-controls="offcanvasSidebar">
            <i class="fa-solid fa-bars"></i>
        </button>

        <h3>Returned Items Report</h3>
        <div class="technician-profile">
            <span class="tech-name d-none d-md-inline"><?= htmlspecialchars($tech['name']) ?></span>
            <a href="profile_tech.php" title="Go to My Profile" style="color: inherit; text-decoration: none;">
                <i class="fa-solid fa-user-circle fa-2x text-secondary"></i>
            </a>
        </div>
    </div>
    <div class="container-fluid">
        <div class="card p-4 mb-4">
            <h5 class="mb-3"><i class="fa-solid fa-filter me-2"></i>Filter Report Data</h5>

            <form method="POST" action="report.php" id="reportForm">
                <div class="row g-3 mb-3">
                    <div class="col-md-3 col-6">
                        <label for="month_filter" class="form-label fw-bold">Select Month</label>
                        <select id="month_filter" class="form-select">
                            <?php for ($m = 1; $m <= 12; $m++) {
                                $month_name = date('F', mktime(0, 0, 0, $m, 1));
                                $selected = ($m == $current_month) ? 'selected' : '';
                                echo "<option value='$m' $selected>$month_name</option>";
                            } ?>
                        </select>
                    </div>
                    <div class="col-md-3 col-6">
                        <label for="year_filter" class="form-label fw-bold">Select Year</label>
                        <select id="year_filter" class="form-select">
                            <?php $start_year = date('Y') - 5; $end_year = date('Y');
                            for ($y = $end_year; $y >= $start_year; $y--) {
                                $selected = ($y == $current_year) ? 'selected' : '';
                                echo "<option value='$y' $selected>$y</option>";
                            } ?>
                        </select>
                    </div>

                    <div class="col-md-6 col-12">
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
                </div>
                <hr>

                <div class="row g-3 align-items-end">
                    <div class="col-md-4 col-12">
                        <label for="start_date" class="form-label fw-bold">Start Date</label>
                        <input type="text" id="start_date" name="start_date" class="form-control" value="<?= htmlspecialchars($start_date) ?>">
                    </div>
                    <div class="col-md-4 col-12">
                        <label for="end_date" class="form-label fw-bold">End Date</label>
                        <input type="text" id="end_date" name="end_date" class="form-control" value="<?= htmlspecialchars($end_date) ?>">
                    </div>
                    <div class="col-md-4 col-12">
                        <button type="submit" class="btn btn-primary w-100"><i class="fa-solid fa-arrows-rotate me-2"></i>Apply Filters</button>
                    </div>
                </div>
            </form>
        </div>

        <div class="card p-4">
            <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-3">
                <h5 class="mb-2 mb-md-0">Returned Items (<?= $total_records ?> total records found)</h5>
                <div class="d-flex">
                    <a href="generate_pdf.php?start_date=<?= urlencode($start_date) ?>&end_date=<?= urlencode($end_date) ?>&category_id=<?= $category_filter_id ?>" target="_blank" class="btn btn-danger btn-sm flex-grow-1">
                        <i class="fa-solid fa-file-pdf me-2"></i>PDF
                    </a>
                    <a href="export_excel_tech.php?start_date=<?= urlencode($start_date) ?>&end_date=<?= urlencode($end_date) ?>&category_id=<?= $category_filter_id ?>" target="_blank" class="btn btn-success btn-sm ms-2 flex-grow-1"> 
                        <i class="fa-solid fa-file-excel me-2"></i>Excel
                    </a>
                </div>
            </div>

            <div class="table-responsive">
                <table class="table table-hover align-middle">
                    <thead>
                        <tr>
                            <th>User</th>
                            <th>Item Details & Status</th> <th class="d-none d-md-table-cell">Category</th>
                            <th class="d-none d-lg-table-cell">Borrow Date</th>
                            <th>Return Date</th>
                            <th class="d-none d-lg-table-cell">Handled By</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($records)): ?>
                            <tr><td colspan="6" class="text-center text-muted py-5">No records found for the selected filters.</td></tr>
                        <?php else: foreach ($records as $record): ?>
                            <tr>
                                <td><?= htmlspecialchars($record['user_name']) ?></td>
                                
                                <td>
                                    <strong><?= htmlspecialchars($record['item_name']) ?></strong>
                                    <small class="text-muted d-block">Asset: <?= htmlspecialchars($record['asset_code'] ?: 'N/A') ?></small>
                                    
                                    <?php 
                                    $condition = htmlspecialchars($record['return_condition']);
                                    // PENEKANAN VISUAL UNTUK DAMAGED
                                    if ($condition === 'Damaged'): 
                                    ?>
                                        <span class="badge bg-danger mt-1">
                                            <i class="fa-solid fa-triangle-exclamation me-1"></i> DAMAGED
                                        </span>
                                    <?php 
                                    // Tunjukkan status lain selain 'Good' dengan warna lain
                                    elseif ($condition !== 'Good' && !empty($condition) && $condition !== 'N/A'):
                                    ?>
                                        <span class="badge bg-warning text-dark mt-1">
                                            <?= $condition ?>
                                        </span>
                                    <?php endif; ?>
                                </td>
                                
                                <td class="d-none d-md-table-cell"><?= htmlspecialchars($record['category_name']) ?></td>
                                <td class="d-none d-lg-table-cell"><?= date("d M Y", strtotime($record['reserve_date'])) ?></td>
                                <td><?= date("d M Y", strtotime($record['return_date'])) ?></td>
                                <td class="d-none d-lg-table-cell"><?= htmlspecialchars($record['technician_name'] ?: 'N/A') ?></td>
                            </tr>
                        <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>

            <?php if ($total_pages > 1): ?>
            <div class="pagination-container">
                <nav aria-label="Page navigation">
                    <ul class="pagination pagination-sm mb-0">
                        <li class="page-item <?= ($page <= 1) ? 'disabled' : '' ?>">
                            <a class="page-link" href="?page=<?= $page - 1 ?>&<?= $pagination_params ?>" aria-label="Previous">
                                <span aria-hidden="true">&laquo;</span>
                            </a>
                        </li>

                        <?php 
                        // Show a limited range of pages around the current page
                        $start_page = max(1, $page - 2);
                        $end_page = min($total_pages, $page + 2);

                        if ($start_page > 1) { echo '<li class="page-item disabled"><span class="page-link">...</span></li>'; }

                        for ($i = $start_page; $i <= $end_page; $i++): 
                        ?>
                        <li class="page-item <?= ($i == $page) ? 'active' : '' ?>">
                            <a class="page-link" href="?page=<?= $i ?>&<?= $pagination_params ?>"><?= $i ?></a>
                        </li>
                        <?php 
                        endfor; 
                        
                        if ($end_page < $total_pages) { echo '<li class="page-item disabled"><span class="page-link">...</span></li>'; }
                        ?>

                        <li class="page-item <?= ($page >= $total_pages) ? 'disabled' : '' ?>">
                            <a class="page-link" href="?page=<?= $page + 1 ?>&<?= $pagination_params ?>" aria-label="Next">
                                <span aria-hidden="true">&raquo;</span>
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
    // Inisialisasi date pickers
    flatpickr("#start_date", { dateFormat: "Y-m-d" });
    flatpickr("#end_date", { dateFormat: "Y-m-d" });

    const monthFilter = document.getElementById('month_filter');
    const yearFilter = document.getElementById('year_filter');
    const reportForm = document.getElementById('reportForm');
    const startDateInput = document.getElementById('start_date');
    const endDateInput = document.getElementById('end_date');
    const categoryFilter = document.getElementById('category_filter');

    // --- Logik Sidebar Mobile (Kekalkan) ---
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
    // --- End Logik Sidebar Mobile ---


    // --- Logik Filter Bulan/Tahun & Kategori (DIPERBAIKI) ---
    function updateDateInputs() {
        if (!yearFilter || !monthFilter || !startDateInput || !endDateInput) return; 

        const year = yearFilter.value;
        const month = monthFilter.value;
        
        // Dapatkan hari terakhir bulan
        const lastDay = new Date(year, month, 0).getDate(); 
        
        const startDate = `${year}-${('0' + month).slice(-2)}-01`;
        const endDate = `${year}-${('0' + month).slice(-2)}-${('0' + lastDay).slice(-2)}`;

        // Kemas kini input tarikh
        startDateInput.value = startDate;
        endDateInput.value = endDate;
    }

    function handleFilterChange(event) {
        // 1. Pastikan input tarikh dikemas kini dahulu
        updateDateInputs();

        // 2. Jika perubahan datang dari Month, Year, atau Category, submit form
        if (event.target === monthFilter || event.target === yearFilter || event.target === categoryFilter) {
            // **Hantar borang secara automatik**
            reportForm.submit();
        } 
        
        // JANGAN submit jika datang dari butang 'Apply Filters' atau date pickers lain
    }
    
    // Listeners untuk perubahan
    if (monthFilter) monthFilter.addEventListener('change', handleFilterChange);
    if (yearFilter) yearFilter.addEventListener('change', handleFilterChange);
    if (categoryFilter) categoryFilter.addEventListener('change', handleFilterChange);
    
    // Pastikan input tarikh dikemas kini pada pemuatan halaman
    updateDateInputs(); 
</script>
</body>
</html>