<?php
session_start();
include '../config.php';

// Ensure technician is logged in
if (!isset($_SESSION['tech_id'])) {
    header("Location: ../login.php");
    exit();
}
$tech_id = (int) $_SESSION['tech_id'];

// Get technician info
$stmt_tech = $conn->prepare("SELECT name FROM technician WHERE tech_id = ?");
$stmt_tech->bind_param("i", $tech_id);
$stmt_tech->execute();
$stmt_tech->bind_result($tname);
$tech = $stmt_tech->fetch() ? ['name' => $tname] : ['name' => 'Technician'];
$stmt_tech->close();

// Get single date filter data from URL (GET)
$filter_date = isset($_GET['filter_date']) && !empty($_GET['filter_date']) ? $_GET['filter_date'] : null;


// Fetch Data By Status
// ✨ FUNGSI INI DIKEMAS KINI UNTUK PRIORITY ✨
function fetch_reservations_by_status($conn, $statuses, $filter_date) {
    $status_placeholders = implode(',', array_fill(0, count($statuses), '?'));

    // Base SQL
    $sql = "SELECT
                ri.id AS reservation_item_id, ri.status, ri.quantity, ri.reserve_date, ri.return_date,
                r.created_at AS apply_date, 
                r.priority, -- <-- 1. TAMBAH SELECT priority
                u.name AS user_name, u.phoneNum AS user_phone,
                i.item_name, i.item_id
            FROM reservation_items ri
            JOIN reservations r ON ri.reserve_id = r.reserve_id
            JOIN user u ON r.user_id = u.user_id
            JOIN item i ON ri.item_id = i.item_id
            WHERE ri.status IN ($status_placeholders)";

    $bind_types = str_repeat('s', count($statuses));
    $bind_values = $statuses;

    // Add single date filter if it exists - FILTERING ON r.created_at
    if ($filter_date) {
        // Filter based on the exact application date
        $sql .= " AND DATE(r.created_at) = ?";
        $bind_types .= 's';
        $bind_values[] = $filter_date;
    }

    // *** 2. LOGIK SUSUNAN (ORDER BY) BAHARU ***
    // Susun ikut Tarikh Acara -> Keutamaan (1=Tinggi) -> Tarikh Mohon (FCFS)
    $sql .= " ORDER BY ri.reserve_date ASC, r.priority ASC, r.created_at ASC";

    $stmt = $conn->prepare($sql);

    // Use call_user_func_array for older PHP compatibility
    $bind_params = [];
    $bind_params[] = $bind_types;
    foreach ($bind_values as $key => $value) {
        $bind_params[] = &$bind_values[$key];
    }
    call_user_func_array([$stmt, 'bind_param'], $bind_params);

    $stmt->execute();
    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

// Pass the single date to the function
$pending_requests = fetch_reservations_by_status($conn, ['Pending'], $filter_date);
$approved_requests = fetch_reservations_by_status($conn, ['Approved'], $filter_date);
$on_loan_requests = fetch_reservations_by_status($conn, ['Checked Out'], $filter_date);
$completed_requests = fetch_reservations_by_status($conn, ['Returned', 'Rejected', 'Cancelled'], $filter_date);


// Get other data for modals
// ADD 1-DAY BUFFER LOGIC TO AVAILABLE ASSETS QUERY
// Assumes 'assets' table has 'last_return_date' column updated during check-in
$assetSql = "
    SELECT asset_id, item_id, asset_code
    FROM assets
    WHERE
        status = 'Available' AND
        (
            last_return_date IS NULL OR
            -- Check if last_return_date is EARLIER than yesterday
            DATE(last_return_date) < DATE_SUB(CURDATE(), INTERVAL 1 DAY)
        )
";

$assetResult = $conn->query($assetSql);
if (!$assetResult) { // Add error checking
    die("Error fetching available assets: " . $conn->error);
}
$availableAssets = [];
while ($row = $assetResult->fetch_assoc()) { $availableAssets[$row['item_id']][] = $row; }
$availableAssets_json = json_encode($availableAssets);

// Function to generate the table HTML
// ✨ FUNGSI INI DIKEMAS KINI UNTUK PAPAR BADGE PRIORITY ✨
function create_request_table($requests) {
    if (empty($requests)) {
        echo '<div class="text-center text-muted py-5"><i class="fa-solid fa-inbox fa-2x mb-2"></i><br>No reservations found matching the criteria.</div>';
        return;
    }
    echo '<div class="table-responsive"><table class="table table-hover align-middle request-table">'; // Add 'request-table' class
    echo '<thead><tr><th>User / Item</th><th class="text-center">Qty</th><th>Duration</th><th>Status</th><th class="text-center">Actions</th></tr></thead><tbody>';
    foreach ($requests as $row) {
        $status = strtolower(trim($row['status']));
        $badgeClass = 'text-bg-light';
        if ($status == 'approved') $badgeClass = 'text-bg-primary';
        if ($status == 'pending') $badgeClass = 'text-bg-warning';
        if ($status == 'rejected') $badgeClass = 'text-bg-dark';
        if ($status == 'checked out') $badgeClass = 'text-bg-danger';
        if ($status == 'returned') $badgeClass = 'text-bg-success';
        if ($status == 'cancelled') $badgeClass = 'text-bg-secondary';

        $priority = isset($row['priority']) ? $row['priority'] : 3; 
        $priority_text = 'Low Priority';        $priority_class = 'text-bg-info'; // Guna 'info' untuk rendah
        if ($priority == 1) { $priority_text = 'High Priority'; $priority_class = 'text-bg-danger'; }
        if ($priority == 2) { $priority_text = 'Moderate Priority'; $priority_class = 'text-bg-warning'; }
        // --- Tamat Logik Badge ---

        echo "<tr id='row-{$row['reservation_item_id']}' data-phone='" . htmlspecialchars($row['user_phone']) . "' data-itemname='" . htmlspecialchars($row['item_name']) . "' data-user-name='" . htmlspecialchars($row['user_name']) . "' data-item-id='{$row['item_id']}' data-qty='{$row['quantity']}'>";
        
        // --- Paparan Badge Ditambah Di Sini ---
        echo "<td><strong>" . htmlspecialchars($row['user_name']) . "</strong>";
        echo "<div class='info-secondary'>" . htmlspecialchars($row['item_name']) . "</div>";
        echo "<div><span class='badge rounded-pill $priority_class' style='font-size: 0.7em;'>Priority: $priority_text</span></div>"; // <-- BADGE BARU
        echo "</td>";
        // --- Tamat Paparan Badge ---

        echo "<td class='text-center'><strong>{$row['quantity']}</strong></td>";
        echo "<td>" . date('d M Y', strtotime($row['reserve_date'])) . " to " . date('d M Y', strtotime($row['return_date'])) . "<div class='info-secondary'>Applied: " . date('d M Y', strtotime($row['apply_date'])) . "</div></td>";
        echo "<td><span class='badge rounded-pill $badgeClass'>" . ucfirst(str_replace('_', ' ', $status)) . "</span></td>";
        echo "<td class='text-center'>";
        if ($status === 'pending') {
            echo "<button class='btn btn-success btn-sm' title='Approve' onclick='openApproveModal({$row['reservation_item_id']})'><i class='fa-solid fa-check'></i></button> ";
            echo "<button class='btn btn-danger btn-sm' title='Reject' onclick='openRejectModal({$row['reservation_item_id']})'><i class='fa-solid fa-xmark'></i></button>";
        } elseif ($status === 'approved') {
            echo "<button class='btn btn-primary btn-sm' title='Check Out' onclick='checkOutItem({$row['reservation_item_id']})'><i class='fa-solid fa-box-open'></i></button>";
        } elseif ($status === 'checked out') {
            echo "<button class='btn btn-warning btn-sm' title='Check In' onclick='checkInItem({$row['reservation_item_id']})'><i class='fa-solid fa-inbox'></i></button>";
        } else {
            echo "<span class='text-muted'>—</span>";
        }
        echo "</td></tr>";
    }
    echo '</tbody></table></div>';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Technician - Manage Requests</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.7/css/dataTables.bootstrap5.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', 'Segoe UI', sans-serif; background-color: #f8fafc; color: #334155; }
        .sidebar { width: 250px; position: fixed; top: 0; bottom: 0; left: 0; background: #ffffff; padding: 20px; border-right: 1px solid #e5e7eb; display: flex; flex-direction: column; justify-content: space-between; z-index: 1000;}
        .sidebar-header { display: flex; align-items: center; gap: 12px; margin-bottom: 30px; }
        .logo-icon { width: 40px; height: 40px; background-color: #3b82f6; color: white; border-radius: 8px; display: flex; align-items: center; justify-content: center; font-size: 20px; }
        .logo-text strong { display: block; font-size: 16px; color: #1e2f3b; }
        .logo-text span { font-size: 12px; color: #94a3b8; }
        .sidebar a { display: flex; align-items: center; gap: 12px; color: #64748b; text-decoration: none; padding: 12px 15px; margin-bottom: 8px; border-radius: 8px; font-weight: 500; transition: all 0.2s ease-in-out; }
        .sidebar a.active, .sidebar a:hover { background: #3b82f6; color: #fff; }
        .sidebar a.logout-link { color: #ef4444; font-weight: 600; margin-top: auto; }
        .sidebar a.logout-link:hover { color: #fff; background: #ef4444; }
        .main-content { margin-left: 250px; }
        .topbar { background: #ffffff; padding: 15px 30px; display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid #e5e7eb; }
        .topbar h3 { font-weight: 600; color: #1e293b; margin: 0; font-size: 22px; }
        .container-fluid { padding: 30px; }
        .card { border-radius: 16px; padding: 25px; box-shadow: 0 4px 12px rgba(0,0,0,0.05); background: #fff; margin-bottom: 25px; border: 1px solid #e2e8f0; }
        .card h5, .modal-title { font-weight: 600; color: #1e293b; }
        .table thead th { background: #f8fafc; font-weight: 600; text-transform: uppercase; font-size: 12px; color: #64748b; border: none; }
        .table tbody td { border-bottom-color: #f1f5f9; }
        .table tbody tr:last-child td { border-bottom: none; }
        .info-secondary { font-size: 0.85rem; color: #64748b; }
        .nav-tabs .nav-link { color: #475569; font-weight: 500; border: none; border-bottom: 2px solid transparent;}
        .nav-tabs .nav-link.active { color: #3b82f6; border-bottom-color: #3b82f6; background-color: transparent;}
        .nav-tabs { border-bottom-color: #e5e7eb; }
        .btn { border-radius: 8px; font-weight: 500;}
        .dataTables_wrapper .dataTables_paginate .page-item.active .page-link { background-color: #3b82f6; border-color: #3b82f6; color: white; }
        .dataTables_wrapper .dataTables_paginate .page-link { color: #3b82f6; }
        .dataTables_wrapper .dataTables_length, .dataTables_wrapper .dataTables_filter { margin-bottom: 1rem; }
        .dataTables_wrapper .form-control, .dataTables_wrapper .form-select { border-radius: 8px; font-size: 0.9rem; }
        .dataTables_info { font-size: 0.9rem; color: #64748b; padding-top: 0.5rem !important; }

        /* ADDED FOR MOBILE RESPONSIVENESS */
        @media (max-width: 991.98px) {
            .sidebar {
                left: -250px; 
                transition: left 0.3s ease-in-out;
                z-index: 1050; 
                box-shadow: 4px 0 12px rgba(0,0,0,0.1);
            }
            .sidebar.toggled {
                left: 0;
            }
            .main-content {
                margin-left: 0;
            }
            .topbar {
                position: sticky;
                top: 0;
                z-index: 1000;
            }
            .sidebar-overlay {
                position: fixed;
                top: 0;
                left: 0;
                right: 0;
                bottom: 0;
                background-color: rgba(0, 0, 0, 0.5);
                z-index: 1040;
                display: none;
            }
            .sidebar-overlay.active {
                display: block;
            }
        }
        .btn.d-lg-none {
            border: none;
        }
    </style>
</head>
<body>

<div class="sidebar-overlay" id="sidebarOverlay"></div> 
<div class="sidebar">
    <div>
        <div class="sidebar-header">
            <div class="logo-icon"><i class="fa-solid fa-wrench"></i></div>
            <div class="logo-text"><strong>UniKL Technician</strong><span>Dashboard</span></div>
        </div>
        <a href="dashboard_tech.php"><i class="fa-solid fa-table-columns"></i> Dashboard</a>
        <a href="check_out.php" class="active"><i class="fa-solid fa-dolly"></i> Manage Requests</a>
        <a href="manageItem_tech.php"><i class="fa-solid fa-box-archive"></i> Manage Items</a>
        <a href="report.php"><i class="fa-solid fa-chart-line"></i> Report</a>
    </div>
    <a href="logout.php" class="logout-link"><i class="fa-solid fa-right-from-bracket"></i> Logout</a>
</div>

<div class="main-content">
    <div class="topbar">
        <div class="d-flex align-items-center">
            <button class="btn d-lg-none me-3" id="sidebarToggle"><i class="fa-solid fa-bars"></i></button>
            <h3>Manage Requests</h3>
        </div>
        <div class="d-flex align-items-center gap-3">
            <span class="fw-bold"><?= htmlspecialchars($tech['name']) ?></span>
            <a href="profile_tech.php" title="My Profile"><i class="fa-solid fa-user-circle fa-2x text-secondary"></i></a>
        </div>
    </div>

    <div class="container-fluid">
        <div class="row g-4">
            <div class="col-lg-12">

                <div class="card">
                    <h5 class="mb-3"><i class="fa-solid fa-filter me-2 text-primary"></i> Filter by Apply Date</h5>
                    <form method="GET" action="check_out.php" class="row g-3 align-items-end">
                        <div class="col-md-5">
                            <label for="filter_date" class="form-label fw-bold">Select Apply Date</label>
                            <input type="date" class="form-control" id="filter_date" name="filter_date" value="<?= htmlspecialchars(isset($filter_date) ? $filter_date : '') ?>">
                        </div>
                        <div class="col-md-auto">
                            <button type="submit" class="btn btn-primary">Filter</button>
                        </div>
                         <div class="col-md-auto">
                            <a href="check_out.php" class="btn btn-outline-secondary">Reset</a>
                        </div>
                    </form>
                </div>

                <div class="card">
                    <h5><i class="fa-solid fa-list-check me-2 text-primary"></i> Reservation Actions</h5>
                    <ul class="nav nav-tabs nav-fill mt-3" id="myTab" role="tablist">
                        <li class="nav-item" role="presentation">
                            <button class="nav-link active" id="pending-tab" data-bs-toggle="tab" data-bs-target="#pending-tab-pane" type="button" role="tab">New Requests <span class="badge rounded-pill text-bg-warning ms-1"><?= count($pending_requests) ?></span></button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="approved-tab" data-bs-toggle="tab" data-bs-target="#approved-tab-pane" type="button" role="tab">To Be Collected <span class="badge rounded-pill text-bg-primary ms-1"><?= count($approved_requests) ?></span></button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="onloan-tab" data-bs-toggle="tab" data-bs-target="#onloan-tab-pane" type="button" role="tab">On Loan <span class="badge rounded-pill text-bg-danger ms-1"><?= count($on_loan_requests) ?></span></button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="completed-tab" data-bs-toggle="tab" data-bs-target="#completed-tab-pane" type="button" role="tab">Completed Archive</button>
                        </li>
                    </ul>
                    <div class="tab-content pt-3" id="myTabContent">
                        <div class="tab-pane fade show active" id="pending-tab-pane" role="tabpanel"><?php create_request_table($pending_requests); ?></div>
                        <div class="tab-pane fade" id="approved-tab-pane" role="tabpanel"><?php create_request_table($approved_requests); ?></div>
                        <div class="tab-pane fade" id="onloan-tab-pane" role="tabpanel"><?php create_request_table($on_loan_requests); ?></div>
                        <div class="tab-pane fade" id="completed-tab-pane" role="tabpanel"><?php create_request_table($completed_requests); ?></div>
                    </div>
                </div>
            </div>

        </div>
    </div>
</div>

<div class="modal fade" id="approveDetailsModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content"><div class="modal-header"><h5 class="modal-title">Approve Reservation</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body">
                <p><strong>User:</strong> <span id="userName"></span> (<span id="userPhone"></span>)</p>
                <p><strong>Item:</strong> <span id="itemName"></span></p>
                <hr>
                <div id="assetListContainer"></div>
                <input type="hidden" id="approve_reservation_item_id">
                <input type="hidden" id="approve_required_qty">
            </div>
            <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button><button type="button" class="btn btn-success" id="confirmApproveBtn" disabled>Approve Request</button></div>
        </div>
    </div>
</div>

<div class="modal fade" id="rejectModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content"><div class="modal-header"><h5 class="modal-title">Reject Reservation</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body">
                <p>Please provide a reason for rejection:</p>
                <textarea id="reject_reason" class="form-control" placeholder="e.g., Item unavailable, insufficient details..."></textarea>
                <input type="hidden" id="reject_reservation_item_id">
            </div>
            <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button><button type="button" class="btn btn-danger" id="confirmRejectBtn">Confirm Rejection</button></div>
        </div>
    </div>
</div>

<div class="modal fade" id="checkInModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Check In Item(s)</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="checkInModalBody">
                <div class="text-center p-4">
                    <i class="fa-solid fa-spinner fa-spin fa-2x"></i>
                    <br>Loading assets...
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" id="confirmCheckInBtn" disabled>Confirm Check-In</button>
            </div>
        </div>
    </div>
</div>
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="https://cdn.datatables.net/1.13.7/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.7/js/dataTables.bootstrap5.min.js"></script>

<script>
const availableAssets = <?php echo $availableAssets_json; ?>;

// --- Approve Logic ---
function openApproveModal(id) {
    const row = document.getElementById('row-' + id);
    if (!row) return;
    $('#userName').text(row.dataset.userName);
    $('#itemName').text(row.dataset.itemname);
    $('#userPhone').text(row.dataset.phone);
    $('#approve_reservation_item_id').val(id);
    $('#approve_required_qty').val(row.dataset.qty);
    const assets = availableAssets[row.dataset.itemId] || [];
    const requiredQty = parseInt(row.dataset.qty);
    let html;
    if (assets.length < requiredQty) {
        // Technician sees this message and should proceed to REJECT the request.
        html = `<div class='alert alert-danger mb-0'>❌ Only ${assets.length} unit(s) available. **Cannot fulfill this request.** Please **Reject** and state the reason.</div>`;
    } else if (assets.length > 0) {
        html = `<h6>Select ${requiredQty} asset(s) to assign:</h6>`;
        html += assets.map(a =>
            `<div class='form-check'><input class='form-check-input asset-checkbox' value='${a.asset_id}' type='checkbox' id='asset-${a.asset_id}'><label class='form-check-label' for='asset-${a.asset_id}'>${a.asset_code}</label></div>`
        ).join('');
    } else {
         // This condition also implies insufficient assets
        html = "<div class='alert alert-danger mb-0'>❌ No available assets found for this item (considering buffer). Please **Reject** the request.</div>";
    }
    $('#assetListContainer').html(html);
    const $approveBtn = $('#confirmApproveBtn');
    // Disable Approve button if insufficient assets are found
    $approveBtn.prop('disabled', assets.length < requiredQty);
    $('#assetListContainer').off('change.assetcheck').on('change.assetcheck', '.asset-checkbox', function() {
        const checkedCount = $('.asset-checkbox:checked').length;
        // Button enabled only if the exact required quantity is checked
        $approveBtn.prop('disabled', checkedCount !== requiredQty);
    });
    new bootstrap.Modal('#approveDetailsModal').show();
}

$('#confirmApproveBtn').on('click', function() {
    const reservation_item_id = $('#approve_reservation_item_id').val();
    const selected = $('.asset-checkbox:checked').map(function() { return $(this).val(); }).get();
    const requiredQty = parseInt($('#approve_required_qty').val());
    if (selected.length !== requiredQty) {
        Swal.fire('Selection Error', `You must select exactly ${requiredQty} asset(s).`, 'warning');
        return;
    }
    const btn = $(this).prop('disabled', true).html('<i class="fa-solid fa-spinner fa-spin me-1"></i> Approving...');
    $.ajax({
        url: 'checkout_action.php', method: 'POST',
        data: { action: 'approve', reservation_item_id, selectedAssets: selected },
        dataType: 'json',
        success: (data) => {
            Swal.fire({ title: 'Success!', text: data.message || 'Approved!', icon: 'success', timer: 1500, showConfirmButton: false })
            .then(() => location.reload());
        },
        error: (xhr) => {
            Swal.fire('Error', (xhr.responseJSON ? xhr.responseJSON.message : 'An unknown error occurred during approval.'), 'error');
            btn.prop('disabled', false).text('Approve Request');
        }
    });
});

// --- Reject Logic ---
function openRejectModal(id) {
    $('#reject_reservation_item_id').val(id);
    $('#reject_reason').val('');
    new bootstrap.Modal('#rejectModal').show();
}

$('#confirmRejectBtn').on('click', function() {
    const reservation_item_id = $('#reject_reservation_item_id').val();
    const reason = $('#reject_reason').val().trim();
    if (!reason) {
        Swal.fire('Input Required', 'Please provide a reason for rejection.', 'warning');
        $('#reject_reason').focus();
        return;
    }
    const btn = $(this).prop('disabled', true).html('<i class="fa-solid fa-spinner fa-spin me-1"></i> Rejecting...');
    $.post('checkout_action.php', { action: 'reject', reservation_item_id, reason }, (data) => {
        Swal.fire({ title: 'Rejected!', text: data.message || 'Request rejected.', icon: 'success', timer: 1500, showConfirmButton: false })
        .then(() => location.reload());
    }, 'json').fail((xhr) => {
        Swal.fire('Error', (xhr.responseJSON ? xhr.responseJSON.message : 'An error occurred during rejection.'), 'error');
        btn.prop('disabled', false).text('Confirm Rejection');
    });
});

// --- Check-Out Logic ---
function checkOutItem(id) {
    Swal.fire({
        title: 'Confirm Check-Out?', text: "Mark item as picked up by the user.", icon: 'question',
        showCancelButton: true, confirmButtonColor: '#3b82f6', cancelButtonColor: '#6c757d', confirmButtonText: 'Yes, check it out!'
    }).then((result) => {
        if (result.isConfirmed) {
             Swal.fire({ title: 'Processing...', text: 'Updating status.', allowOutsideClick: false, didOpen: () => { Swal.showLoading(); }});
            $.post('checkout_action.php', { action: 'checkout', reservation_item_id: id }, (data) => {
                Swal.close();
                Swal.fire({ title: 'Checked Out!', text: data.message, icon: 'success', timer: 1500, showConfirmButton: false })
                .then(() => location.reload());
            }, 'json').fail((xhr) => {
                Swal.close();
                Swal.fire('Error', (xhr.responseJSON ? xhr.responseJSON.message : 'An error occurred during check-out.'), 'error');
            });
        }
    });
}

// === FUNGSI checkInItem (Logik 'remarks' optional/required) ===
function checkInItem(id) {
    const row = document.getElementById('row-' + id);
    if (!row) return;

    // Dapatkan modal dan elemennya
    const $modal = $('#checkInModal');
    const $modalBody = $('#checkInModalBody');
    const $confirmBtn = $('#confirmCheckInBtn');

    // Reset modal ke keadaan loading
    $modal.find('.modal-title').text('Check In: ' + row.dataset.itemname);
    $modalBody.html('<div class="text-center p-4"><i class="fa-solid fa-spinner fa-spin fa-2x"></i><br>Loading assets...</div>');
    $confirmBtn.prop('disabled', true);

    // Tunjukkan modal
    const checkInModal = new bootstrap.Modal($modal[0]);
    checkInModal.show();

    // Ambil senarai aset yang berkaitan dengan permintaan ini
    $.ajax({
        url: 'checkout_action.php',
        method: 'GET',
        data: {
            action: 'get_assets_for_checkin', // Guna action GET baharu
            reservation_item_id: id
        },
        dataType: 'json',
        success: function(assets) {
            if (!assets || assets.length === 0) {
                $modalBody.html('<div class="alert alert-warning mb-0">No assets found in \'Checked Out\' status for this item. It might have already been returned.</div>');
                return;
            }

            // Bina borang HTML secara dinamik
            let html = `<p>Please set the condition for each returned asset (${assets.length} unit(s)).</p>`;
            html += `<form id="checkInForm">`;
            // Simpan ID tempahan dalam borang
            html += `<input type="hidden" id="checkin_reservation_item_id" value="${id}">`; 

            assets.forEach(asset => {
                const asset_id = asset.asset_id;
                // 'remarks' disembunyikan secara default
                html += `
                    <div class="card mb-3 p-3 checkin-asset-card" data-asset-id="${asset_id}">
                        <h6 class="mb-2 fw-bold text-primary">${asset.asset_code}</h6>
                        <div class="mb-2">
                            <div class="form-check form-check-inline">
                                <input class="form-check-input" type="radio" name="condition_${asset_id}" id="condition_good_${asset_id}" value="Good" required checked>
                                <label class="form-check-label" for="condition_good_${asset_id}">Good</label>
                            </div>
                            <div class="form-check form-check-inline">
                                <input class="form-check-input" type="radio" name="condition_${asset_id}" id="condition_damaged_${asset_id}" value="Damaged/Incomplete">
                                <label class="form-check-label text-danger" for="condition_damaged_${asset_id}">Damaged/Incomplete</label>
                            </div>
                        </div>
                        <div class="remarks-container" style="display: none;">
                            <label for="remarks_${asset_id}" class="form-label small mb-1">Remarks (Required if damaged):</label>
                            <input type="text" class="form-control form-control-sm" id="remarks_${asset_id}" placeholder="e.g., Screen cracked, cable missing...">
                        </div>
                    </div>
                `;
            });
            
            html += '</form>';
            $modalBody.html(html);
            $confirmBtn.prop('disabled', false); // Dayakan butang selepas borang dimuatkan
            
            // Trigger perubahan untuk menetapkan label remarks yang betul pada permulaan
            $modalBody.find('input[type="radio"]:checked').trigger('change');
        },
        error: function(xhr) {
            $modalBody.html('<div class="alert alert-danger mb-0">Error loading asset list: ' + (xhr.responseJSON ? xhr.responseJSON.message : 'Unknown error') + '</div>');
        }
    });
}


$(document).ready(function() {
    
    // === LOGIK MOBILE SIDEBAR TOGGLE (BARU) ===
    $('#sidebarToggle').on('click', function(e) {
        e.preventDefault();
        $('.sidebar').toggleClass('toggled');
        $('#sidebarOverlay').toggleClass('active');
    });

    // Tutup sidebar apabila overlay diklik
    $('#sidebarOverlay').on('click', function() {
        $('.sidebar').removeClass('toggled');
        $(this).removeClass('active');
    });

    // Pastikan sidebar terbuka by default pada desktop
    $(window).on('load resize', function() {
        if ($(window).width() >= 992) {
            $('.sidebar').removeClass('toggled');
            $('#sidebarOverlay').removeClass('active');
        }
    });
    // === TAMAT LOGIK MOBILE SIDEBAR TOGGLE ===


    // Initialize DataTables
    $('.request-table').DataTable({
        "pageLength": 10,
        "order": [], // Biar PHP yang tentukan susunan (priority)
        "language": {
            "search": "Search in table:",
            "lengthMenu": "Show _MENU_ entries",
            "info": "Showing _START_ to _END_ of _TOTAL_ entries",
            "infoEmpty": "Showing 0 to 0 of 0 entries",
            "infoFiltered": "(filtered from _MAX_ total entries)",
            "zeroRecords": "No matching records found",
            "paginate": { "first": "First", "last": "Last", "next": "Next", "previous": "Previous" }
        }
    });

    // Adjust DataTables columns on tab change
    $('button[data-bs-toggle="tab"]').on('shown.bs.tab', function (e) {
        var targetPane = $(e.target).attr('data-bs-target');
        if ($.fn.DataTable.isDataTable($(targetPane).find('.request-table'))) {
             $(targetPane).find('.request-table').DataTable().columns.adjust();
        }
    });

    // === LOGIK MODAL CHECK-IN BAHARU ===

    // ✨ Tunjukkan/sembunyikan 'remarks' berdasarkan pilihan
    $('#checkInModalBody').on('change', 'input[type="radio"]', function() {
        const $card = $(this).closest('.checkin-asset-card');
        const $remarksContainer = $card.find('.remarks-container');
        const $remarksLabel = $remarksContainer.find('label');

        if ($(this).val() === 'Damaged/Incomplete') {
            $remarksLabel.text('Remarks (Required if damaged):'); 
            $remarksContainer.slideDown();
        } else {
            // Jika 'Good', tunjukkan juga tapi tukar label
            $remarksLabel.text('Remarks (Optional):'); 
            $remarksContainer.slideDown(); 
        }
    });


    // Pengendali klik '#confirmCheckInBtn'
    $('#confirmCheckInBtn').on('click', function() {
        const reservation_item_id = $('#checkin_reservation_item_id').val();
        let asset_conditions = [];
        let isValid = true;
        let firstErrorField = null;

        // 1. Validasi
        $('.checkin-asset-card').each(function() {
            const $card = $(this);
            const asset_id = $card.data('asset-id');
            const condition = $(`input[name="condition_${asset_id}"]:checked`).val();
            const remarks = $(`#remarks_${asset_id}`).val().trim();

            if (!condition) {
                isValid = false;
                Swal.fire('Input Required', `Please select a condition for asset ${asset_id}.`, 'warning');
                firstErrorField = $card;
                return false; 
            }

            // ✨ LOGIK VALIDASI: Semak 'remarks' jika 'Damaged/Incomplete'
            if (condition === 'Damaged/Incomplete' && !remarks) {
                isValid = false;
                Swal.fire('Input Required', `Remarks are required for damaged asset (ID: ${asset_id}).`, 'warning');
                firstErrorField = $(`#remarks_${asset_id}`);
                return false; 
            }
            
            asset_conditions.push({
                asset_id: asset_id,
                condition: condition,
                remarks: remarks 
            });
        });

        if (!isValid) {
            if (firstErrorField) firstErrorField.focus();
            return; 
        }
        
        if (asset_conditions.length === 0) {
            Swal.fire('Error', 'No assets were found to check in.', 'error');
            return;
        }

        // Simpan rujukan 'button'
        const $btn = $(this);

        // 2. Popup Pengesahan
        Swal.fire({
            title: 'Confirm Check-In?',
            text: `You are about to check in ${asset_conditions.length} asset(s). This action cannot be undone.`,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#3b82f6',
            cancelButtonColor: '#6c757d',
            confirmButtonText: 'Yes, confirm check-in!'
        }).then((result) => {
            
            // 3. Hantar (Submit) jika disahkan
            if (result.isConfirmed) {
                
                $btn.prop('disabled', true).html('<i class="fa-solid fa-spinner fa-spin me-1"></i> Confirming...');
                
                $.post('checkout_action.php', {
                    action: 'checkin_multi', 
                    reservation_item_id: reservation_item_id,
                    asset_conditions: JSON.stringify(asset_conditions) 
                }, function(data) {
                    Swal.fire({ title: 'Checked In!', text: data.message, icon: 'success', timer: 2000, showConfirmButton: false })
                    .then(() => location.reload());
                }, 'json').fail(function(xhr) {
                    Swal.fire('Error', (xhr.responseJSON ? xhr.responseJSON.message : 'An error occurred during check-in.'), 'error');
                    $btn.prop('disabled', false).text('Confirm Check-In');
                });
            }
        });
    });
    // === TAMAT LOGIK MODAL CHECK-IN BAHARU ===
});
</script>

</body>
</html>