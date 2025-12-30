<?php
session_start();
include '../config.php';

if (!isset($_SESSION['person_id'])) {
    header("Location: ../login.php");
    exit();
}

$person_id = (int) $_SESSION['person_id'];

// 1. Tarik data user
$stmt = $conn->prepare("SELECT name, email, phoneNum FROM person WHERE person_id = ?");
if ($stmt) {
    $stmt->bind_param("i", $person_id);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();
    $stmt->close();
}

if (!$user) {
    session_destroy();
    header("Location: ../login.php");
    exit();
}

// 2. LOGIK NAMA PENDEK
$fullName = $user['name'] ?? 'Guest User';
$lowerName = strtolower($fullName);
$posBinti = strpos($lowerName, ' binti ');
$posBin = strpos($lowerName, ' bin ');

if ($posBinti !== false) {
    $shortName = substr($fullName, 0, $posBinti);
} elseif ($posBin !== false) {
    $shortName = substr($fullName, 0, $posBin);
} else {
    $shortName = $fullName;
}
$displayName = trim($shortName);

$user['person_id'] = $person_id;

// 3. PAGINATION SETUP
$rowsPerPage = 10;
$currentPage = isset($_GET['page']) ? (int)$_GET['page'] : 1;
if ($currentPage < 1) $currentPage = 1;
$offset = ($currentPage - 1) * $rowsPerPage;
$totalRows = 0;
$totalPages = 0;

$sql_count = "SELECT COUNT(ri.id) FROM reservations r JOIN reservation_items ri ON r.reserve_id = ri.reserve_id WHERE r.person_id = ?";
if ($stmt_count = $conn->prepare($sql_count)) {
    $stmt_count->bind_param("i", $person_id);
    $stmt_count->execute();
    $stmt_count->bind_result($totalRows);
    $stmt_count->fetch();
    $stmt_count->close();
    $totalPages = ceil($totalRows / $rowsPerPage);
    if ($currentPage > $totalPages && $totalRows > 0) {
        $currentPage = $totalPages;
        $offset = ($currentPage - 1) * $rowsPerPage;
    }
}

// 4. FETCH HISTORY
$history = [];
$sql = "SELECT ri.id AS reservation_item_id, i.item_name, r.reserve_date, r.return_date, r.reason, ri.status, ri.quantity
 FROM reservations r
 JOIN reservation_items ri ON r.reserve_id = ri.reserve_id
 JOIN item i ON ri.item_id = i.item_id
 WHERE r.person_id = ?
 ORDER BY ri.id ASC
 LIMIT ? OFFSET ?";

if ($stmt = $conn->prepare($sql)) {
    $stmt->bind_param("iii", $person_id, $rowsPerPage, $offset);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $history[] = $row;
    }
    $stmt->close();
}

if (isset($conn)) {
    $conn->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Borrowing History — UniKL</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary-color: #06b6d4;
            --primary-light: #f0f9ff;
            --primary-hover: #0891b2;
            --bg-light-gray: #f4f7f9;
            --card-bg: #ffffff;
            --text-dark: #1e293b;
            --text-muted: #64748b;
            --shadow-light: 0 4px 12px rgba(0, 0, 0, 0.05);

            --status-approved-bg: #d1fae5;
            --status-approved-text: #059669;
            --status-checkedout-bg: #dbeafe;
            --status-checkedout-text: #1d4ed8;
            --status-pending-bg: #fef3c7;
            --status-pending-text: #b45309;
            --status-rejected-bg: #fee2e2;
            --status-rejected-text: #991b1b;
            --status-returned-bg: #e5e7eb;
            --status-returned-text: #374151;
        }

        body { font-family: 'Inter', sans-serif; background-color: var(--bg-light-gray); color: var(--text-dark); min-height: 100vh; }

        .sidebar {
            width: 280px; position: fixed; top: 0; bottom: 0; left: 0;
            background: var(--card-bg); padding: 20px;
            box-shadow: 2px 0 10px rgba(0, 0, 0, 0.05); z-index: 1050;
            display: flex; flex-direction: column; justify-content: space-between;
            transition: transform 0.3s ease-in-out;
            transform: translateX(0px);
        }

        .main-content { margin-left: 280px; transition: margin-left 0.3s ease-in-out; }

        @media (max-width: 992px) {
            .sidebar { transform: translateX(-280px); }
            .main-content { margin-left: 0; }
            .topbar .user-name { display: none; }
        }

        .sidebar.active { transform: translateX(0px) !important; }

        .sidebar-header { display: flex; align-items: center; gap: 10px; margin-bottom: 35px; }
        .logo-icon { width: 45px; height: 45px; background-color: var(--primary-color); color: white; border-radius: 10px; display: flex; align-items: center; justify-content: center; font-size: 22px; }
        .logo-text strong { display: block; font-size: 18px; color: var(--text-dark); font-weight: 700; }
        .logo-text span { font-size: 12px; color: var(--text-muted); font-weight: 500; }

        .sidebar a {
            display: flex; align-items: center; gap: 15px; color: var(--text-muted);
            text-decoration: none; padding: 14px 18px; margin-bottom: 6px;
            border-radius: 10px; font-weight: 500; transition: all 0.2s;
        }

        .sidebar a.active { background: var(--primary-light); color: var(--primary-color); font-weight: 700; }
        .sidebar a.logout-link { color: #ef4444; margin-top: 20px; }

        .topbar {
            background: var(--card-bg); padding: 18px 30px; display: flex;
            justify-content: space-between; align-items: center; border-bottom: 1px solid #eef1f4;
            position: sticky; top: 0; z-index: 1040;
        }

        .container-fluid { padding: 30px; }
        .card { border-radius: 12px; box-shadow: var(--shadow-light); background: var(--card-bg); border: none; padding: 25px; }

        /* --- STYLES FOR STATUS ICONS (TOOLTIP) --- */
        .loan-status-badge {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 35px;
            height: 35px;
            padding: 0 !important;
            border-radius: 50% !important;
            cursor: help; /* Menunjukkan ada info tambahan */
            transition: transform 0.2s ease;
        }
        .loan-status-badge:hover { transform: scale(1.15); }
        .loan-status-badge i { font-size: 14px; }

        .loan-status-badge[data-status="approved"] { background-color: var(--status-approved-bg); color: var(--status-approved-text); }
        .loan-status-badge[data-status="checked out"] { background-color: var(--status-checkedout-bg); color: var(--status-checkedout-text); }
        .loan-status-badge[data-status="pending"] { background-color: var(--status-pending-bg); color: var(--status-pending-text); }
        .loan-status-badge[data-status="rejected"] { background-color: var(--status-rejected-bg); color: var(--status-rejected-text); }
        .loan-status-badge[data-status="returned"] { background-color: var(--status-returned-bg); color: var(--status-returned-text); }

        .offcanvas-backdrop { position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1045; display: none; }
        .offcanvas-backdrop.active { display: block; }
    </style>
</head>
<body>

<div class="offcanvas-backdrop" id="sidebar-backdrop"></div>

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
        <button class="btn btn-sm btn-outline-primary d-lg-none" type="button" id="sidebarToggle"><i class="fa-solid fa-bars"></i></button>
        <h3>Borrowing History</h3>
        <div class="user-profile">
            <span class="user-name me-2" style="text-transform: capitalize; font-weight: 600;"><?= htmlspecialchars($displayName) ?></span>
            <a href="profile.php"><i class="fa-solid fa-circle-user fa-2x text-secondary"></i></a>
        </div>
    </div>

    <div class="container-fluid">
        <div class="card">
            <h5 class="mb-4"><i class="fa-solid fa-list-ul me-2 text-primary"></i> My Loan History</h5>
            
            <div class="d-flex gap-3 mb-4 flex-wrap">
                <select id="statusFilter" class="form-select form-select-sm" style="max-width: 200px;">
                    <option value="">All Statuses</option>
                    <option value="approved">Approved</option>
                    <option value="checked out">Checked Out</option>
                    <option value="pending">Pending</option>
                    <option value="rejected">Rejected</option>
                    <option value="returned">Returned</option>
                </select>
                <input id="searchInput" type="text" class="form-control form-control-sm" placeholder="Search item or reason..." style="max-width: 300px;">
            </div>

            <div class="table-responsive">
                <table id="historyTable" class="table table-hover align-middle">
                    <thead class="table-light">
                        <tr>
                            <th>Item Name</th>
                            <th>Borrow Date</th>
                            <th>Return Date</th>
                            <th class="text-center">Status</th>
                            <th>Qty</th>
                            <th>Reason</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if (!empty($history)): ?>
                        <?php foreach ($history as $loan):
                            $status = strtolower($loan['status']);
                            $statusIcon = 'fa-solid fa-circle-info';
                            if ($status == 'approved') $statusIcon = 'fa-solid fa-circle-check';
                            elseif ($status == 'checked out') $statusIcon = 'fa-solid fa-hand-holding-box';
                            elseif ($status == 'pending') $statusIcon = 'fa-solid fa-hourglass-half';
                            elseif ($status == 'rejected') $statusIcon = 'fa-solid fa-circle-xmark';
                            elseif ($status == 'returned') $statusIcon = 'fa-solid fa-handshake';
                        ?>
                        <tr>
                            <td><strong><?= htmlspecialchars($loan['item_name']) ?></strong></td>
                            <td><?= date("d M Y", strtotime($loan['reserve_date'])) ?></td>
                            <td><?= date("d M Y", strtotime($loan['return_date'])) ?></td>
                            <td class="text-center">
                                <span class="badge loan-status-badge" 
                                      data-status="<?= $status ?>" 
                                      data-bs-toggle="tooltip" 
                                      data-bs-placement="top" 
                                      title="<?= ucfirst(htmlspecialchars($loan['status'])) ?>">
                                    <i class="<?= $statusIcon ?>"></i>
                                </span>
                            </td>
                            <td><?= htmlspecialchars($loan['quantity']) ?></td>
                            <td><small class="text-muted"><?= htmlspecialchars($loan['reason']) ?></small></td>
                        </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="6" class="text-center py-5 text-muted">No records found.</td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <nav class="d-flex justify-content-between align-items-center mt-4">
                <small class="text-muted">Page <?= $currentPage ?> of <?= $totalPages ?></small>
                <ul class="pagination pagination-sm mb-0">
                    <li class="page-item <?= ($currentPage <= 1) ? 'disabled' : '' ?>">
                        <a class="page-link" href="?page=<?= $currentPage - 1 ?>">Previous</a>
                    </li>
                    <?php for($i=1; $i<=$totalPages; $i++): ?>
                        <li class="page-item <?= ($i == $currentPage) ? 'active' : '' ?>">
                            <a class="page-link" href="?page=<?= $i ?>"><?= $i ?></a>
                        </li>
                    <?php endfor; ?>
                    <li class="page-item <?= ($currentPage >= $totalPages) ? 'disabled' : '' ?>">
                        <a class="page-link" href="?page=<?= $currentPage + 1 ?>">Next</a>
                    </li>
                </ul>
            </nav>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    // 1. AKTIFKAN TOOLTIP BOOTSTRAP (PENTING!)
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'))
    var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl)
    });

    // 2. SIDEBAR LOGIC
    const sidebar = document.getElementById('offcanvasSidebar');
    const toggleBtn = document.getElementById('sidebarToggle');
    const backdrop = document.getElementById('sidebar-backdrop');

    function toggleSidebar() {
        sidebar.classList.toggle('active');
        backdrop.classList.toggle('active');
    }

    if(toggleBtn) toggleBtn.addEventListener('click', toggleSidebar);
    if(backdrop) backdrop.addEventListener('click', toggleSidebar);

    // 3. SEARCH & FILTER LOGIC
    const searchInput = document.getElementById('searchInput');
    const statusFilter = document.getElementById('statusFilter');
    const tableRows = document.querySelectorAll('#historyTable tbody tr');

    function filterTable() {
        const searchText = searchInput.value.toLowerCase();
        const filterStatus = statusFilter.value.toLowerCase();

        tableRows.forEach(row => {
            const itemName = row.cells[0].innerText.toLowerCase();
            const reason = row.cells[5].innerText.toLowerCase();
            const statusBadge = row.querySelector('.loan-status-badge');
            const rowStatus = statusBadge ? statusBadge.getAttribute('data-status') : '';

            const matchesSearch = itemName.includes(searchText) || reason.includes(searchText);
            const matchesStatus = filterStatus === "" || rowStatus === filterStatus;

            row.style.display = (matchesSearch && matchesStatus) ? "" : "none";
        });
    }

    if(searchInput) searchInput.addEventListener('keyup', filterTable);
    if(statusFilter) statusFilter.addEventListener('change', filterTable);
});
</script>
</body>
</html>