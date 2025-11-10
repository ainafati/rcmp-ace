<?php
session_start();

include '../config.php';


if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php"); 
    exit();
}

$user_id = (int) $_SESSION['user_id'];


$user = null; 
$stmt_user = $conn->prepare("SELECT name, email, phoneNum FROM user WHERE user_id = ?");
if ($stmt_user) {
    $stmt_user->bind_param("i", $user_id);
    $stmt_user->execute();
    $result_user = $stmt_user->get_result();
    $user = $result_user->fetch_assoc();
    $stmt_user->close();
} else {
    
    error_log("Failed to prepare user statement: " . $conn->error);
}



if (!$user) {
    session_destroy();
    header("Location: ../login.php"); 
    exit();
}


$rowsPerPage = 10;
$currentPage = isset($_GET['page']) ? (int)$_GET['page'] : 1;
if ($currentPage < 1) $currentPage = 1;
$offset = ($currentPage - 1) * $rowsPerPage;
$totalRows = 0;
$totalPages = 0;


$sql_count = "SELECT COUNT(ri.id)
              FROM reservations r
              JOIN reservation_items ri ON r.reserve_id = ri.reserve_id
              WHERE r.user_id = ?";
if ($stmt_count = $conn->prepare($sql_count)) {
    $stmt_count->bind_param("i", $user_id);
    $stmt_count->execute();
    $stmt_count->bind_result($totalRows);
    $stmt_count->fetch();
    $stmt_count->close();
    $totalPages = ceil($totalRows / $rowsPerPage);
    
    if ($currentPage > $totalPages && $totalRows > 0) {
        $currentPage = $totalPages;
        $offset = ($currentPage - 1) * $rowsPerPage; 
    }
} else {
    error_log("Failed to prepare count statement: " . $conn->error);
}



$history = [];
$sql = "SELECT i.item_name, ri.reserve_date, ri.return_date, ri.reason, ri.status, ri.quantity
        FROM reservations r
        JOIN reservation_items ri ON r.reserve_id = ri.reserve_id
        JOIN item i ON ri.item_id = i.item_id
        WHERE r.user_id = ?
        ORDER BY ri.reserve_date ASC
        LIMIT ? OFFSET ?";

if ($stmt = $conn->prepare($sql)) {
    
    $stmt->bind_param("iii", $user_id, $rowsPerPage, $offset);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $history[] = $row;
    }
    $stmt->close();
} else {
    error_log("Failed to prepare history statement: " . $conn->error);
}




$approved = $pending = $rejected = $returned = 0; 


$upcoming_bookings_all = [];
$sql_upcoming = "SELECT i.item_name, ri.reserve_date, ri.return_date, ri.status
                 FROM reservations r
                 JOIN reservation_items ri ON r.reserve_id = ri.reserve_id
                 JOIN item i ON ri.item_id = i.item_id
                 WHERE r.user_id = ? AND ri.status IN ('approved', 'pending', 'checked out')
                 ORDER BY ri.reserve_date ASC";

if ($stmt_upcoming = $conn->prepare($sql_upcoming)) {
    $stmt_upcoming->bind_param("i", $user_id);
    $stmt_upcoming->execute();
    $result_upcoming = $stmt_upcoming->get_result();
    while ($row_up = $result_upcoming->fetch_assoc()) {
        $upcoming_bookings_all[] = $row_up;
    }
    $stmt_upcoming->close();
} else {
    error_log("Failed to prepare upcoming bookings statement: " . $conn->error);
}


$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Borrowing History — UniKL</title>
    <link href="https:
    <link rel="stylesheet" href="https:
    <link rel="preconnect" href="https:
    <link rel="preconnect" href="https:
    <link href="https:

    <script src='https:

    <style>
        /* DEFINING TEAL COLOR AS PRIMARY & MODERN STYLING */
        :root {
            --primary-color: #06b6d4; /* Cyan 600 */
            --primary-hover: #0891b2; /* Cyan 700 */
            --bg-light-gray: #f8fafc; 
            --card-bg: #ffffff;
            --text-dark: #1e293b;
            --text-muted: #64748b;
        }

        body { 
            font-family: 'Inter', 'Segoe UI', sans-serif; 
            background-color: var(--bg-light-gray); 
            color: var(--text-dark); 
            min-height: 100vh; 
        }
        
        /* --- Sidebar Styles (Desktop) --- */
        .sidebar { width: 250px; position: fixed; top: 0; bottom: 0; left: 0; background: var(--card-bg); padding: 20px; border-right: 1px solid #e2e8f0; z-index: 1000; display: flex; flex-direction: column; justify-content: space-between; transition: transform 0.3s ease-in-out; }
        .main-content { margin-left: 250px; transition: margin-left 0.3s ease-in-out; }
        .sidebar-header { display: flex; align-items: center; gap: 12px; margin-bottom: 30px; }
        .logo-icon { width: 40px; height: 40px; background-color: var(--primary-color); color: white; border-radius: 8px; display: flex; align-items: center; justify-content: center; font-size: 20px; }
        .logo-text strong { display: block; font-size: 16px; color: var(--text-dark); }
        .logo-text span { font-size: 12px; color: var(--text-muted); }
        .sidebar a { display: flex; align-items: center; gap: 12px; color: var(--text-muted); text-decoration: none; padding: 12px 15px; margin-bottom: 8px; border-radius: 8px; font-weight: 500; font-size: 15px; transition: all 0.2s; }
        .sidebar a.active, .sidebar a:hover { background: var(--primary-color); color: #fff; }
        .sidebar a.logout-link { color: #ef4444; font-weight: 600; margin-top: auto; }
        .sidebar a.logout-link:hover { color: #fff; background: #ef4444; }

        /* --- Topbar & Content Styles --- */
        .topbar { background: var(--card-bg); padding: 15px 30px; display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid #e2e8f0; z-index: 999; position: sticky; top: 0; }
        .topbar h3 { font-weight: 600; margin: 0; color: var(--text-dark); font-size: 22px; }
        .topbar .user-profile { display: flex; align-items: center; gap: 12px; }
        .topbar .user-name { font-weight: 600; font-size: 15px; color: var(--text-dark); }
        .container-fluid { padding: 30px; }
        .card { border-radius: 16px; padding: 25px; box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05); background: var(--card-bg); margin-bottom: 25px; border: 1px solid #e2e8f0; }
        .card h5 { font-weight: 600; color: var(--text-dark); }
        
        /* Primary/Teal Color Application */
        .text-primary { color: var(--primary-color) !important; }
        .text-primary i { color: var(--primary-color); }
        .btn-primary { background-color: var(--primary-color); border-color: var(--primary-color); }
        .btn-primary:hover { background-color: var(--primary-hover); border-color: var(--primary-hover); }
        .btn-outline-primary { color: var(--primary-color); border-color: var(--primary-color); }
        .btn-outline-primary:hover { color: #fff; background-color: var(--primary-color); }
        
        /* Status Colors (Keep Success/Warning/Danger standard) */
        .text-success i { color: #22c55e; } 
        .text-warning i { color: #f59e0b; } 
        .text-danger i { color: #ef4444; } 
        .text-secondary i { color: #64748b; }

        /* Table and Pagination */
        .table thead th { background: var(--bg-light-gray); color: var(--text-muted); border: none; font-weight: 600; text-transform: uppercase; font-size: 12px; white-space: nowrap;}
        .table tbody td { border-bottom: 1px solid #f1f5f9; vertical-align: middle; font-size: 0.9rem;}
        .table tbody tr:last-child td { border-bottom: none; }
        
        /* --- NEW: Quick Filters (Chips) --- */
        .quick-filter-chips .btn { 
            font-size: 14px; 
            padding: 5px 12px; 
            font-weight: 500; 
            border-radius: 20px;
        }
        .quick-filter-chips .btn.active { 
             background-color: var(--primary-color); 
             color: #fff; 
             border-color: var(--primary-color);
        }
        
        /* --- NEW: Enhanced Badge Styling --- */
        .badge.rounded-pill { 
            padding: .4em .8em; 
            font-weight: 600; 
            display: inline-flex; 
            align-items: center; 
            gap: 6px;
        }
        .badge.rounded-pill i { 
            font-size: 0.8em; 
            line-height: 1; 
        }

        /* FullCalendar - Change standard blue to Teal */
        #calendarView .fc-event { 
            font-weight: 500; cursor: pointer; border: none;
            background-color: var(--primary-color); /* Teal for events */
            border-color: var(--primary-color);
        }
        #calendarView .fc-daygrid-day.fc-day-today { 
            background-color: #eff6ff; 
        }
        
        .pagination .page-item .page-link { border-radius: 8px; margin: 0 3px; border: 1px solid #e2e8f0; color: var(--primary-color); }
        .pagination .page-item.active .page-link { background-color: var(--primary-color); border-color: var(--primary-color); color: #fff; }
        .pagination .page-item.disabled .page-link { color: #94a3b8; background-color: #e9ecef; border-color: #dee2e6; }
        
        /* Upcoming Bookings */
        .upcoming-list { display: flex; flex-direction: column; gap: 15px; }
        .upcoming-item { display: flex; align-items: center; gap: 15px; }
        .upcoming-item .icon-box { width: 40px; height: 40px; border-radius: 8px; display: flex; align-items: center; justify-content: center; color: white; font-size: 16px; flex-shrink: 0; }
        
        /* --- Mobile Optimizations --- */
        @media (max-width: 991.98px) {
            .sidebar { transform: translateX(-100%); z-index: 1050; position: fixed; }
            .offcanvas-open .sidebar { transform: translateX(0); }
            .main-content { margin-left: 0; }
            .d-flex.align-items-center.gap-2.mb-3 { flex-direction: column; align-items: stretch !important; gap: 10px; }
            .d-flex.align-items-center.gap-2.mb-3 > * { width: 100% !important; }
            .quick-filter-chips { justify-content: space-between !important; overflow-x: auto; padding-bottom: 10px;}
            .quick-filter-chips .btn { white-space: nowrap; }
            .container-fluid { padding: 15px; }
        }
    </style>
</head>
<body>

<div class="offcanvas-backdrop fade" id="sidebar-backdrop" style="display: none; z-index: 1040;"></div>

<div class="sidebar" id="offcanvasSidebar">
    <div>
        <div class="sidebar-header">
            <div class="logo-icon"><i class="fa-solid fa-cube"></i></div>
            <div class="logo-text"><strong>UniKL User</strong><span>Equipment System</span></div>
        </div>
        <a href="dashboard_user.php"><i class="fa-solid fa-table-columns"></i> Dashboard</a>
        <a href="item_user.php"><i class="fa-solid fa-box"></i> Item Availability</a>
        <a href="history.php" class="active"><i class="fa-solid fa-clock-rotate-left"></i> History</a>
    </div>
    <a href="logout.php" class="logout-link"><i class="fa-solid fa-right-from-bracket"></i> Logout</a>
</div>

<div class="main-content">
    <div class="topbar">
        <button class="btn btn-sm btn-outline-primary d-lg-none" type="button" id="sidebarToggle">
            <i class="fa-solid fa-bars"></i>
        </button>

        <h3>Borrowing History</h3>
        <div class="user-profile">
            <span class="user-name"><?= htmlspecialchars(isset($user['name']) ? $user['name'] : 'User') ?></span>
            <a href="profile.php" title="Go to My Profile" style="color: inherit; text-decoration: none;">
                <i class="fa-solid fa-circle-user fa-2x text-secondary"></i>
            </a>
        </div>
    </div>
    <div class="container-fluid">
        <div class="row">
            <div class="col-lg-8 col-12"> 
                <div class="card">
                    <div class="d-flex flex-wrap justify-content-between align-items-center mb-3">
                        <h5 class="mb-0"><i class="fa-solid fa-list-ul me-2 text-primary"></i> My Loan History</h5>
                        <div class="btn-group" role="group">
                            <button type="button" id="tableViewBtn" class="btn btn-primary btn-sm active"><i class="fa-solid fa-table-list me-2"></i>Table</button>
                            <button type="button" id="calendarViewBtn" class="btn btn-outline-primary btn-sm"><i class="fa-solid fa-calendar-days me-2"></i>Calendar</button>
                        </div>
                    </div>

                    <div id="tableView">
                        <div class="d-flex flex-wrap gap-2 mb-3 quick-filter-chips">
                            <button type="button" class="btn btn-outline-primary btn-sm quick-filter-btn" data-filter-status="">All History</button>
                            <button type="button" class="btn btn-outline-warning btn-sm quick-filter-btn" data-filter-status="pending"><i class="fa-solid fa-hourglass-half me-1"></i> Pending</button>
                            <button type="button" class="btn btn-outline-success btn-sm quick-filter-btn" data-filter-status="approved,checked out"><i class="fa-solid fa-box-open me-1"></i> Active Loans</button>
                            <button type="button" class="btn btn-outline-secondary btn-sm quick-filter-btn" data-filter-status="returned"><i class="fa-solid fa-check-double me-1"></i> Returned</button>
                        </div>
                        
                        <div class="d-flex align-items-center gap-2 mb-3">
                           <select id="statusFilter" class="form-select form-select-sm" style="width: auto; min-width: 150px;">
                                <option value="">All Statuses (Dropdown)</option>
                                <option value="approved">Approved</option>
                                <option value="checked out">Checked Out</option>
                                <option value="pending">Pending</option>
                                <option value="rejected">Rejected</option>
                                <option value="returned">Returned</option>
                           </select>
                           <input id="searchInput" type="text" class="form-control form-control-sm" placeholder="Search item or reason...">
                        </div>
                        
                        <div class="table-responsive">
                            <table id="historyTable" class="table align-middle">
                                <thead>
                                    <tr>
                                        <th>Item Name</th>
                                        <th>Borrow Date</th>
                                        <th>Return Date</th>
                                        <th>Status</th>
                                        <th>Quantity</th>
                                        <th>Reason</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (!empty($history)): ?>
                                        <?php foreach ($history as $loan):
                                            $status = strtolower($loan['status']);
                                            $badgeClass = 'text-bg-light'; 
                                            $statusIcon = 'fa-solid fa-circle-info';

                                            if ($status == 'approved') {
                                                $badgeClass = 'text-bg-success';
                                                $statusIcon = 'fa-solid fa-circle-check';
                                            } elseif ($status == 'checked out') {
                                                $badgeClass = 'text-bg-primary'; 
                                                $statusIcon = 'fa-solid fa-hand-holding-box';
                                            } elseif ($status == 'pending') {
                                                $badgeClass = 'text-bg-warning';
                                                $statusIcon = 'fa-solid fa-hourglass-half';
                                            } elseif ($status == 'rejected') {
                                                $badgeClass = 'text-bg-danger';
                                                $statusIcon = 'fa-solid fa-circle-xmark';
                                            } elseif ($status == 'returned') {
                                                $badgeClass = 'text-bg-secondary';
                                                $statusIcon = 'fa-solid fa-handshake';
                                            }
                                        ?>
                                        <tr>
                                            <td><strong><?= htmlspecialchars($loan['item_name']) ?></strong></td>
                                            <td><?= date("d M Y", strtotime($loan['reserve_date'])) ?></td>
                                            <td><?= date("d M Y", strtotime($loan['return_date'])) ?></td>
                                            <td>
                                                <span class="badge rounded-pill <?= $badgeClass ?> loan-status-badge" data-status="<?= $status ?>">
                                                    <i class="<?= $statusIcon ?>"></i> <?= ucfirst(htmlspecialchars($loan['status'])) ?>
                                                </span>
                                            </td>
                                            <td><?= htmlspecialchars($loan['quantity']) ?></td>
                                            <td><?= htmlspecialchars($loan['reason']) ?></td>
                                        </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr><td colspan="6" class="text-center text-muted py-5"><i class="fa-solid fa-box-open fa-2x mb-2"></i><br>No history records found.</td></tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                        <nav aria-label="History Pagination" class="pagination-controls">
                           <div>
                                <?php if ($totalRows > 0): ?>
                                Showing <?= min(($currentPage - 1) * $rowsPerPage + 1, $totalRows) ?>
                                to <?= min($currentPage * $rowsPerPage, $totalRows) ?>
                                of <?= $totalRows ?> entries
                                <?php else: ?>
                                No entries found
                                <?php endif; ?>
                           </div>
                           <ul class="pagination pagination-sm">
                                <?php if ($currentPage > 1): ?>
                                    <li class="page-item">
                                        <a class="page-link" href="?page=<?= $currentPage - 1 ?>" aria-label="Previous">
                                            <span aria-hidden="true">&laquo;</span>
                                        </a>
                                    </li>
                                <?php else: ?>
                                    <li class="page-item disabled">
                                        <span class="page-link">&laquo;</span>
                                    </li>
                                <?php endif; ?>

                                <?php
                                 $maxPagesToShow = 5; 
                                 $startPage = max(1, $currentPage - floor($maxPagesToShow / 2));
                                 $endPage = min($totalPages, $startPage + $maxPagesToShow - 1);
                                 if ($endPage == $totalPages) {
                                     $startPage = max(1, $endPage - $maxPagesToShow + 1);
                                 }

                                 if ($startPage > 1) {
                                     echo '<li class="page-item"><a class="page-link" href="?page=1">1</a></li>';
                                     if ($startPage > 2) {
                                         echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                                     }
                                 }

                                 for ($i = $startPage; $i <= $endPage; $i++): ?>
                                    <li class="page-item <?= ($i == $currentPage) ? 'active' : '' ?>">
                                        <a class="page-link" href="?page=<?= $i ?>"><?= $i ?></a>
                                    </li>
                                <?php endfor;

                                 if ($endPage < $totalPages) {
                                     if ($endPage < $totalPages - 1) {
                                         echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                                     }
                                     echo '<li class="page-item"><a class="page-link" href="?page=' . $totalPages . '">' . $totalPages . '</a></li>';
                                 }
                                ?>

                                <?php if ($currentPage < $totalPages): ?>
                                    <li class="page-item">
                                        <a class="page-link" href="?page=<?= $currentPage + 1 ?>" aria-label="Next">
                                            <span aria-hidden="true">&raquo;</span>
                                        </a>
                                    </li>
                                <?php else: ?>
                                    <li class="page-item disabled">
                                        <span class="page-link">&raquo;</span>
                                    </li>
                                <?php endif; ?>
                           </ul>
                        </nav>
                    </div>

                    <div id="calendarView" style="display: none;"></div>
                </div>
            </div>

            <div class="col-lg-4 col-12">
                <div class="card">
                    <h5><i class="fa-solid fa-bell me-2 text-primary"></i> Upcoming Bookings</h5>
                    <hr>
                    <div class="upcoming-list">
                        <?php if (!empty($upcoming_bookings_all)): ?>
                            <?php foreach (array_slice($upcoming_bookings_all, 0, 5) as $booking):
                                $status_upcoming = strtolower($booking['status']);
                                $icon_bg = ($status_upcoming === 'approved' || $status_upcoming === 'checked out') ? 'bg-success' : 'bg-warning';
                            ?>
                            <div class="upcoming-item">
                                <div class="icon-box <?= $icon_bg ?>"><i class="fa-solid fa-box"></i></div>
                                <div>
                                    <strong><?= htmlspecialchars($booking['item_name']) ?></strong>
                                    <p class="text-muted small mb-0">
                                      <?= date("d M", strtotime($booking['reserve_date'])) ?> &rarr; <?= date("d M Y", strtotime($booking['return_date'])) ?>
                                      (<?= ucfirst(htmlspecialchars($status_upcoming)) ?>)
                                    </p>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <p class="text-muted text-center pt-3">No upcoming bookings.</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<script src="https:
<script>
document.addEventListener('DOMContentLoaded', function() {
    
    
    const sidebar = document.getElementById('offcanvasSidebar');
    const toggleBtn = document.getElementById('sidebarToggle');
    const backdrop = document.getElementById('sidebar-backdrop');
    const body = document.body;

    if (toggleBtn) {
        toggleBtn.addEventListener('click', () => {
            if (sidebar.style.transform === 'translateX(0px)') {
                
                sidebar.style.transform = 'translateX(-100%)';
                backdrop.style.display = 'none';
                body.classList.remove('offcanvas-open');
            } else {
                
                sidebar.style.transform = 'translateX(0px)';
                backdrop.style.display = 'block';
                body.classList.add('offcanvas-open');
            }
        });

        
        backdrop.addEventListener('click', () => {
             sidebar.style.transform = 'translateX(-100%)';
             backdrop.style.display = 'none';
             body.classList.remove('offcanvas-open');
        });
    }

    
    const tableViewBtn = document.getElementById('tableViewBtn');
    const calendarViewBtn = document.getElementById('calendarViewBtn');
    const tableView = document.getElementById('tableView');
    const calendarView = document.getElementById('calendarView');
    let calendar = null;

    function initializeCalendar() {
        
        if (calendar) {
            calendar.render(); 
            return;
        } 
        
        calendar = new FullCalendar.Calendar(calendarView, {
            initialView: 'dayGridMonth',
            headerToolbar: { left: 'prev,next today', center: 'title', right: 'dayGridMonth,timeGridWeek,listWeek' },
            events: 'get_bookings.php', 
            height: 'auto' 
        });
        calendar.render();
    }

    if (tableViewBtn && calendarViewBtn && tableView && calendarView) {
        tableViewBtn.addEventListener('click', () => {
            tableView.style.display = 'block';
            calendarView.style.display = 'none';
            tableViewBtn.classList.add('active', 'btn-primary');
            tableViewBtn.classList.remove('btn-outline-primary');
            calendarViewBtn.classList.remove('active', 'btn-primary');
            calendarViewBtn.classList.add('btn-outline-primary');
        });

        calendarViewBtn.addEventListener('click', () => {
            tableView.style.display = 'none';
            calendarView.style.display = 'block';
            calendarViewBtn.classList.add('active', 'btn-primary');
            calendarViewBtn.classList.remove('btn-outline-primary');
            tableViewBtn.classList.remove('active', 'btn-primary');
            tableViewBtn.classList.add('btn-outline-primary');
            initializeCalendar(); 
        });
    }

    
    const historyTable = document.getElementById('historyTable');
    if (historyTable) {
        const tableBody = historyTable.querySelector('tbody');
        const currentRows = Array.from(tableBody.querySelectorAll('tr')); 
        const searchInput = document.getElementById('searchInput');
        const statusFilter = document.getElementById('statusFilter');
        const noRecordRowHTML = tableBody.querySelector('td[colspan="6"]') ? tableBody.innerHTML : null;

        function applyClientSideFilters() {
            const query = searchInput.value.trim().toLowerCase();
            const status = statusFilter.value;
            let visibleCount = 0;
            let hasDataRows = false; 

            currentRows.forEach(row => {
                if (row.querySelector('td[colspan="6"]')) {
                    
                    hasDataRows = false;
                    return; 
                }
                hasDataRows = true;

                
                const item = row.cells[0].innerText.toLowerCase();
                const reason = row.cells[5].innerText.toLowerCase();
                const rowStatusBadge = row.cells[3].querySelector('.badge');
                const rowStatus = rowStatusBadge ? rowStatusBadge.textContent.trim().toLowerCase() : '';

                const matchesSearch = item.includes(query) || reason.includes(query);
                const matchesStatus = (status === '') || (rowStatus === status);

                if (matchesSearch && matchesStatus) {
                    row.style.display = ''; 
                    visibleCount++;
                } else {
                    row.style.display = 'none'; 
                }
            });

            
            const existingNoMatchRow = tableBody.querySelector('.no-filter-match');
            if (existingNoMatchRow) existingNoMatchRow.remove();

            if (hasDataRows && visibleCount === 0) {
                 const tr = document.createElement('tr');
                 tr.className = 'no-filter-match';
                 tr.innerHTML = `<td colspan="6" class="text-center text-muted py-5"><i class="fa-solid fa-search fa-2x mb-2"></i><br>No matching records found for the current filters on this page.</td>`;
                 tableBody.appendChild(tr);
            } else if (hasDataRows && visibleCount > 0 && tableBody.querySelector('.no-filter-match')) {
                 
                 tableBody.querySelector('.no-filter-match').remove();
            }
        }

        if(searchInput) searchInput.addEventListener('keyup', applyClientSideFilters);
        if(statusFilter) statusFilter.addEventListener('change', applyClientSideFilters);

    }
});
</script>

</body>
</html>