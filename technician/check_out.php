<?php
session_start();
include '../config.php';


if (!isset($_SESSION['person_id'])) {
    header("Location: ../login.php");
    exit();
}
$person_id = (int)$_SESSION['person_id'];


// Ambil nama technician (menggunakan jadual person)
$stmt_tech = $conn->prepare("SELECT name FROM person WHERE person_id = ?");
$stmt_tech->bind_param("i", $person_id);
$stmt_tech->execute();
$result_tech = $stmt_tech->get_result(); 
$tech = ($tech_data = $result_tech->fetch_assoc()) ? $tech_data : ['name' => 'Technician'];
$stmt_tech->close();



$filter_date = isset($_GET['filter_date']) && !empty($_GET['filter_date']) ? $_GET['filter_date'] : null;



function fetch_reservations_by_status($conn, $statuses, $filter_date) {
    $status_placeholders = implode(',', array_fill(0, count($statuses), '?'));

    $sql = "SELECT
                ri.id AS reservation_item_id, ri.status, ri.quantity, ri.reserve_date, ri.return_date,
                r.created_at AS apply_date, 
                r.priority, 
                ri.reason AS reservation_reason,
                u.name AS user_name, u.phoneNum AS user_phone,
                u.person_id AS user_person_id, /* <<< TAMBAH: Ambil person_id untuk data attribute JS */
                i.item_name, i.item_id
            FROM reservation_items ri
            JOIN reservations r ON ri.reserve_id = r.reserve_id
            JOIN person u ON r.person_id = u.person_id 
            JOIN item i ON ri.item_id = i.item_id 
            WHERE ri.status IN ($status_placeholders)";

    $bind_types = str_repeat('s', count($statuses));
    $bind_values = $statuses;

    if ($filter_date) {
        $sql .= " AND DATE(r.created_at) = ?";
        $bind_types .= 's';
        $bind_values[] = $filter_date;
    }

    $sql .= " ORDER BY u.name ASC, ri.reserve_date ASC, r.priority ASC, r.created_at ASC";

    // 1. Prepared Statement
    $stmt = $conn->prepare($sql);

    // KOD PENTING: Semak kegagalan prepare
    if ($stmt === false) {
        // Jika gagal, ia akan mati di sini dan memaparkan ralat SQL sebenar
        die('SQL Prepare failed: ' . $conn->error . '. Query: ' . $sql);
    }
    
    // 2. Binding parameters (Kaedah PHP Lama, yang stabil)
    $bind_params = [];
    $bind_params[] = $bind_types;
    foreach ($bind_values as $key => $value) {
        $bind_params[] = &$bind_values[$key]; // MESTI menggunakan &
    }
    // Baris ini akan berjaya jika $stmt bukan false
    call_user_func_array([$stmt, 'bind_param'], $bind_params); 

    // 3. Execution (Baris 66 yang sebelum ini Fatal Error)
    $stmt->execute();
    $result = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    return $result;
}

$pending_requests = fetch_reservations_by_status($conn, ['Pending'], $filter_date);
$approved_requests = fetch_reservations_by_status($conn, ['Approved'], $filter_date);
$on_loan_requests = fetch_reservations_by_status($conn, ['Checked Out'], $filter_date);
$completed_requests = fetch_reservations_by_status($conn, ['Returned', 'Rejected', 'Cancelled'], $filter_date);



// Query yang dibetulkan untuk membuang tempoh 'cooling down' 24 jam.
$assetSql = "
    SELECT asset_id, item_id, asset_code
    FROM assets
    WHERE
        status = 'Available'
    /* MESTI PADAM: Syarat last_return_date dikeluarkan */
";


$assetResult = $conn->query($assetSql);
if (!$assetResult) {
    // Mesej ralat di sini tidak merujuk kepada table 'user' atau 'person', 
    // jadi ia tidak memerlukan pembetulan nama jadual, hanya logging.
    die("Error fetching available assets: " . $conn->error);
}
$availableAssets = [];
while ($row = $assetResult->fetch_assoc()) { $availableAssets[$row['item_id']][] = $row; }
$availableAssets_json = json_encode($availableAssets);


function create_request_table($requests) {
    if (empty($requests)) {
        echo '<div class="text-center text-muted py-5"><i class="fa-solid fa-inbox fa-2x mb-2"></i><br>No reservations found matching the criteria.</div>';
        return;
    }

    // 1. Kumpulkan (group) permintaan mengikut nama pengguna
    $grouped_requests = [];
    foreach ($requests as $row) {
        $grouped_requests[$row['user_name']][] = $row;
    }

    // 2. Buat ID unik untuk Accordion ini (supaya 4 tab tak bercampur)
    $accordion_id = 'accordion_' . uniqid();

    echo '<div class="accordion" id="' . $accordion_id . '">';

    $item_index = 0; // Untuk ID unik bagi setiap item

    // 3. Loop melalui setiap group (setiap pengguna)
    foreach ($grouped_requests as $user_name => $user_items) {
        
        $user_phone = $user_items[0]['user_phone']; 
        $item_count = count($user_items); // Kira bilangan item
        
        // 4. Buat ID unik untuk header dan body accordion ini
        $collapse_id = 'collapse_' . $accordion_id . '_' . $item_index;
        $header_id = 'header_' . $accordion_id . '_' . $item_index;

        echo '<div class="accordion-item">';

        // 5. ACCORDION HEADER (Bahagian yang boleh diklik)
        echo '<h2 class="accordion-header" id="' . $header_id . '">';
        echo '  <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#' . $collapse_id . '" aria-expanded="false" aria-controls="' . $collapse_id . '">';
        
        // Gunakan flexbox untuk susun nama & bilangan item
        echo '    <div classd-flex justify-content-between w-100 pe-3">';
        echo '      <div>';
        echo '        <strong class="fs-6">' . htmlspecialchars($user_name) . '</strong>';
        echo '        <span class="text-muted ms-2" style="font-size: 0.9em;">(' . htmlspecialchars($user_phone) . ')</span>';
        echo '      </div>';
        echo '      <div class="mt-1">';
        echo '        <span class="badge bg-primary rounded-pill">' . $item_count . ' Item(s) Requested</span>';
        echo '      </div>';
        echo '    </div>';

        echo '  </button>';
        echo '</h2>';
        
        // 6. ACCORDION BODY (Bahagian yang 'collapsible' - mengandungi jadual)
        // Tambah 'show' jika nak item pertama terbuka (buang 'collapsed' dari button di atas)
        echo '<div id="' . $collapse_id . '" class="accordion-collapse collapse" aria-labelledby="' . $header_id . '" data-bs-parent="#' . $accordion_id . '">';
        echo '  <div class="accordion-body p-0">'; // p-0 = padding 0, supaya table rapat ke tepi

        // 7. Jadual item (Sama seperti kod sebelum ini)
        echo '<div class="table-responsive"><table class="table mb-0 align-middle">';
        echo '<thead><tr>';
        echo '  <th>Item / Priority</th>';
        echo '  <th class="text-center">Qty</th>';
        echo '  <th>Duration / Applied</th>';
        echo '  <th>Status</th>';
        echo '  <th class="text-center">Actions</th>';
        echo '</tr></thead><tbody>';

        // 8. Loop melalui setiap item milik pengguna ini
        foreach ($user_items as $row) {
            // (Logik untuk badge status & prioriti)
            $status = strtolower(trim($row['status']));
            $badgeClass = 'text-bg-light';
            if ($status == 'approved') $badgeClass = 'text-bg-primary';
            if ($status == 'pending') $badgeClass = 'text-bg-warning';
            if ($status == 'rejected') $badgeClass = 'text-bg-dark';
            if ($status == 'checked out') $badgeClass = 'text-bg-danger';
            if ($status == 'returned') $badgeClass = 'text-bg-success';
            if ($status == 'cancelled') $badgeClass = 'text-bg-secondary';

            $priority = isset($row['priority']) ? $row['priority'] : 3;
            $priority_text = 'Low Priority'; $priority_class = 'text-bg-info';
            if ($priority == 1) { $priority_text = 'High Priority'; $priority_class = 'text-bg-danger'; }
            if ($priority == 2) { $priority_text = 'Moderate Priority'; $priority_class = 'text-bg-warning'; }
            
// DALAM FUNGSI create_request_table, sekitar Baris 200
// Pastikan anda telah menambah 'user_person_id' dalam query fetch_reservations_by_status
// dan menggunakan 'reservation_reason' dari query
echo "<tr id='row-{$row['reservation_item_id']}' 
         data-phone='" . htmlspecialchars($row['user_phone']) . "' 
         data-itemname='" . htmlspecialchars($row['item_name']) . "' 
         data-user-name='" . htmlspecialchars($row['user_name']) . "' 
         data-user-id='{$row['user_person_id']}'            /* <<< BARU: Pastikan ada */
         data-item-id='{$row['item_id']}' 
         data-reason='" . htmlspecialchars($row['reservation_reason']) . "' /* <<< PENTING: Tambah baris ini */
         data-qty='{$row['quantity']}'>";       

		 // Papar data item
            echo "<td><strong>" . htmlspecialchars($row['item_name']) . "</strong>";
            echo "<div><span class='badge rounded-pill $priority_class' style='font-size: 0.7em;'>$priority_text</span></div>";
            echo "</td>";

            echo "<td class='text-center'><strong>{$row['quantity']}</strong></td>";
            echo "<td>" . date('d M Y', strtotime($row['reserve_date'])) . " to " . date('d M Y', strtotime($row['return_date'])) . "<div class='info-secondary'>Applied: " . date('d M Y', strtotime($row['apply_date'])) . "</div></td>";
            echo "<td><span class='badge rounded-pill $badgeClass'>" . ucfirst(str_replace('_', ' ', $status)) . "</span></td>";
            echo "<td class='text-center'>";

            // (Logik untuk butang - sama macam kod asal)
            if ($status === 'pending') {
                echo "<button class='btn btn-success btn-sm' title='Approve' aria-label='Approve Request' onclick='openApproveModal({$row['reservation_item_id']})'><i class='fa-solid fa-check'></i></button> ";
                echo "<button class='btn btn-danger btn-sm' title='Reject' aria-label='Reject Request' onclick='openRejectModal({$row['reservation_item_id']})'><i class='fa-solid fa-xmark'></i></button>";
            } elseif ($status === 'approved') {
                echo "<button class='btn btn-primary btn-sm' title='Check Out' aria-label='Check Out Item' onclick='checkOutItem({$row['reservation_item_id']})'><i class='fa-solid fa-box-open'></i></button>";
            } elseif ($status === 'checked out') {
                echo "<button class='btn btn-warning btn-sm' title='Check In' aria-label='Check In Item' onclick='checkInItem({$row['reservation_item_id']})'><i class='fa-solid fa-inbox'></i></button>";
            } else {
                echo "<span class='text-muted'>—</span>";
            }
            echo "</td></tr>";
        }

        echo '</tbody></table></div>'; // Tamat table
        echo '</div></div>'; // Tamat accordion-body & accordion-collapse
        echo '</div>'; // Tamat accordion-item

        $item_index++; // Naikkan index untuk ID unik
    }

    echo '</div>'; // Tamat accordion
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

        /* --- PEMBETULAN (Performance & Accessibility) --- */
        @media (max-width: 991.98px) {
            .sidebar {
                /* Guna transform (laju) dan bukannya 'left' (perlahan) */
                transform: translateX(-100%); 
                transition: transform 0.3s ease-in-out;
                z-index: 1050; 
                box-shadow: 4px 0 12px rgba(0,0,0,0.1);
            }
            .sidebar.toggled {
                transform: translateX(0);
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
<div class="sidebar" id="admin-sidebar">
    <div>
        <div class="sidebar-header">
            <div class="logo-icon"><i class="fa-solid fa-wrench"></i></div>
            <div class="logo-text"><strong>UniKL Technician</strong><span>System Support</span></div>
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
            <button class="btn d-lg-none me-3" id="sidebarToggle" aria-label="Toggle navigation menu">
                <i class="fa-solid fa-bars"></i>
            </button>
            <h3>Manage Requests</h3>
        </div>
        <div class="d-flex align-items-center gap-3">
            <span class="fw-bold"><?= htmlspecialchars($tech['name']) ?></span>
            <a href="profile_tech.php" title="My Profile" aria-label="View My Profile">
                <i class="fa-solid fa-user-circle fa-2x text-secondary"></i>
            </a>
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

<div class="modal fade" id="approveDetailsModal" tabindex="-1" role="dialog" aria-labelledby="approveModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="approveModalLabel">Approve Reservation</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p><strong>User:</strong> <span id="userName"></span> (<span id="userPhone"></span>)</p>
                <p><strong>Item:</strong> <span id="itemName"></span></p>
                <p><strong>Quantity Requested:</strong> <span id="requestedQtyText" class="badge text-bg-warning"></span></p>
				<p><strong>Reason:</strong> <span id="reservationReasonText" class="badge text-bg-warning"></span></p>				
				<hr>

                <div class="mb-3">
                    <label for="approve_actual_qty" class="form-label fw-bold">Quantity to Approve:</label>
                    <input type="number" class="form-control" id="approve_actual_qty" min="1">
                </div>

                <div class="mb-3" id="partialRejectionReasonContainer" style="display:none;">
                    <label for="partial_reject_reason" class="form-label fw-bold text-danger">Reason for Quantity Reduction:</label>
                    <textarea class="form-control" id="partial_reject_reason" placeholder="e.g., Only 2 units are available now."></textarea>
                    <small class="form-text text-muted">Please explain why the approved quantity is less than requested.</small>
                </div>
                <div id="assetListContainer"></div>

                <input type="hidden" id="approve_reservation_item_id">
                <input type="hidden" id="approve_original_qty"> 

            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-success" id="confirmApproveBtn" disabled>Approve Request</button>
            </div>
        </div>
    </div>
</div>
<div class="modal fade" id="rejectModal" tabindex="-1" role="dialog" aria-labelledby="rejectModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content"><div class="modal-header"><h5 class="modal-title" id="rejectModalLabel">Reject Reservation</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
            <div class="modal-body">
                <p>Please provide a reason for rejection:</p>
                <textarea id="reject_reason" class="form-control" placeholder="e.g., Item unavailable, insufficient details..."></textarea>
                <input type="hidden" id="reject_reservation_item_id">
            </div>
            <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button><button type="button" class="btn btn-danger" id="confirmRejectBtn">Confirm Rejection</button></div>
        </div>
    </div>
</div>

<div class="modal fade" id="checkInModal" tabindex="-1" role="dialog" aria-labelledby="checkInModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="checkInModalLabel">Check In Item(s)</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
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
// PENTING: Pastikan variable ini wujud dan diisi dari PHP di Bahagian 1
const availableAssets = <?php echo $availableAssets_json; ?>;


// =========================================================================================
// 1. FUNGSI APPROVAL DAN VALIDASI ASET
// =========================================================================================

function openApproveModal(id) {
    const row = document.getElementById('row-' + id);
    if (!row) return;

    // 1. Dapatkan data asal dari data-attributes
    const originalQty = parseInt(row.dataset.qty);
    const itemId = row.dataset.itemId;
    const reservationReason = row.dataset.reason; // MESTI ADA
    
    const assets = availableAssets[itemId] || [];
    const availableCount = assets.length;

    // 2. Dapatkan elemen modal
    const $approveBtn = $('#confirmApproveBtn');
    const $assetContainer = $('#assetListContainer');
    const $approveQtyInput = $('#approve_actual_qty');
    const $partialRejectContainer = $('#partialRejectionReasonContainer');
    const $partialRejectReason = $('#partial_reject_reason');
    
    // 3. Isi maklumat asas dan Reason
    $('#userName').text(row.dataset.userName);
    $('#itemName').text(row.dataset.itemname);
    $('#userPhone').text(row.dataset.phone);
    // PENTING: Inject data Reason ke dalam modal
    $('#reservationReasonText').text(reservationReason || 'N/A'); 
    $('#approve_reservation_item_id').val(id);
    $('#requestedQtyText').text(originalQty);
    $('#approve_original_qty').val(originalQty);
    
    // Set kuantiti lalai
    let qtyToApprove = (availableCount >= originalQty) ? originalQty : availableCount;
    $approveQtyInput.val(qtyToApprove);
    $approveQtyInput.attr('max', availableCount); 

    // --- LOGIK: Tunjuk atau sembunyikan kotak sebab (untuk Partial Rejection / Full Reject) ---
    function updatePartialReasonDisplay(currentQty) {
        // Paparkan kotak sebab jika Kuantiti Diluluskan < Kuantiti Diminta (termasuk 0)
        if (currentQty < originalQty) {
            $partialRejectContainer.slideDown();
            $partialRejectReason.attr('required', true); 
        } else {
            $partialRejectContainer.slideUp();
            $partialRejectReason.attr('required', false).val(''); 
        }
    }

    // 5. FUNGSI BARU (1): Validasi butang 'Approve'
    function validateApproveButton() {
        const currentQtyToApprove = parseInt($approveQtyInput.val());
        const checkedCount = $('.asset-checkbox:checked').length;
        
        let isReasonValid = true;
        // SEMAK SEBAB JIKA PARTIAL/FULL REJECT (Qty Diluluskan < Qty Diminta)
        if (currentQtyToApprove < originalQty) {
              isReasonValid = $partialRejectReason.val().trim().length >= 5; // Min 5 aksara
        }
        
        if (isNaN(currentQtyToApprove) || currentQtyToApprove < 0 || !isReasonValid) {
              $approveBtn.prop('disabled', true);
              
        // LOGIK UTAMA: JIKA Qty > 0, aset mesti dipilih
        } else if (currentQtyToApprove > 0) {
            $approveBtn.prop('disabled', checkedCount !== currentQtyToApprove);
            
        // LOGIK KRITIKAL: JIKA Qty == 0, dan SEBAB VALID, BENARKAN
        } else if (currentQtyToApprove === 0) {
            $approveBtn.prop('disabled', !isReasonValid);
            
        } else {
            $approveBtn.prop('disabled', true);
        }
    }

    // 6. FUNGSI BARU (2): Hanya untuk bina senarai checkbox (atau tunjuk ralat)
    function buildAssetCheckboxes() {
        const currentQtyToApprove = parseInt($approveQtyInput.val());

        if (isNaN(currentQtyToApprove) || currentQtyToApprove < 0) {
            $assetContainer.html('<div class="alert alert-warning">Please enter a valid quantity (0 or more) to approve.</div>');
            
        } else if (currentQtyToApprove === 0) {
             // JIKA KUANTITI 0: Abaikan senarai aset
             $assetContainer.html('<div class="alert alert-info mb-0">Quantity to Approve is 0. This request will be processed as a **Full Rejection**. Please provide a reason above.</div>');
            
        } else if (availableCount < currentQtyToApprove) {
            $assetContainer.html(`<div class='alert alert-danger'>❌ Only ${availableCount} unit(s) available. You cannot approve ${currentQtyToApprove}.</div>`);
            
        } else if (availableCount === 0 && currentQtyToApprove > 0) {
             $assetContainer.html("<div class='alert alert-danger mb-0'>❌ No available assets found for this item. Please **Reject** the request (or enter 0 above with a reason).</div>");
            
        } else {
            // Kuantiti > 0 dan aset mencukupi
            let html = `<h6>Select exactly ${currentQtyToApprove} asset(s) to assign:</h6>`;
            html += assets.map(a =>
                `<div class='form-check'><input class='form-check-input asset-checkbox' value='${a.asset_id}' type='checkbox' id='asset-${a.asset_id}'><label class='form-check-label' for='asset-${a.asset_id}'>${a.asset_code}</label></div>`
            ).join('');
            $assetContainer.html(html);
        }
        
        validateApproveButton();
    }

    // 7. Event Listeners: Padam yang lama & pasang yang baru
    $approveQtyInput.off('change keyup');
    $assetContainer.off('change.assetcheck');
    $partialRejectReason.off('change keyup'); 

    $approveQtyInput.on('change keyup', function() {
        buildAssetCheckboxes();
        updatePartialReasonDisplay(parseInt($(this).val()));
    }); 

    $partialRejectReason.on('change keyup', validateApproveButton); 
    
    $assetContainer.on('change.assetcheck', '.asset-checkbox', validateApproveButton); 

    // 8. Panggil sekali untuk 'initialize' modal
    buildAssetCheckboxes();
    updatePartialReasonDisplay(qtyToApprove);

    // 9. Buka modal
    new bootstrap.Modal('#approveDetailsModal').show();
}

// Tindakan AJAX Approve
$('#confirmApproveBtn').on('click', function() {
    const reservation_item_id = $('#approve_reservation_item_id').val();
    const selected = $('.asset-checkbox:checked').map(function() { return $(this).val(); }).get();
    
    const actualApprovedQty = parseInt($('#approve_actual_qty').val());
    const originalQty = parseInt($('#approve_original_qty').val());
    const reasonForPartialReject = $('#partial_reject_reason').val().trim();

    // Validasi Front-End tambahan untuk memastikan kuantiti sepadan (Hanya jika Qty > 0)
    if (actualApprovedQty > 0 && selected.length !== actualApprovedQty) {
        Swal.fire('Selection Error', `You must select exactly ${actualApprovedQty} asset(s).`, 'warning');
        return;
    }
    
    const btn = $(this).prop('disabled', true).html('<i class="fa-solid fa-spinner fa-spin me-1"></i> Approving...');
    
    $.ajax({
        url: 'checkout_action.php', 
        method: 'POST',
        data: { 
            action: 'approve', 
            reservation_item_id: reservation_item_id, 
            selectedAssets: selected,
            approved_quantity: actualApprovedQty,
            partial_reason: (actualApprovedQty < originalQty) ? reasonForPartialReject : '' 
        },
        dataType: 'json',
        success: (data) => {
            Swal.fire({ title: 'Success!', text: data.message || 'Approved!', icon: 'success', timer: 1500, showConfirmButton: false })
            .then(() => location.reload());
        },
        error: (xhr) => {
            let realErrorMessage = xhr.responseText;
            if (xhr.responseJSON && xhr.responseJSON.message) {
                realErrorMessage = xhr.responseJSON.message;
            }
            Swal.fire('AJAX Request Failed!', 'Details: ' + realErrorMessage, 'error');
            btn.prop('disabled', false).text('Approve Request');
        }
    });
});


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
        let realErrorMessage = xhr.responseText;
        if (xhr.responseJSON && xhr.responseJSON.message) {
            realErrorMessage = xhr.responseJSON.message;
        }
        Swal.fire('Error', 'An error occurred during rejection. Details: ' + realErrorMessage, 'error');
        btn.prop('disabled', false).text('Confirm Rejection');
    });
});


// =========================================================================================
// 3. FUNGSI CHECKOUT (Approve -> Checked Out)
// =========================================================================================

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
                let realErrorMessage = xhr.responseText;
                if (xhr.responseJSON && xhr.responseJSON.message) {
                    realErrorMessage = xhr.responseJSON.message;
                }
                Swal.fire('Error', 'An error occurred during check-out. Details: ' + realErrorMessage, 'error');
            });
        }
    });
}


// =========================================================================================
// 4. FUNGSI CHECK-IN (Checked Out -> Returned)
// =========================================================================================

function checkInItem(id) {
    const row = document.getElementById('row-' + id);
    if (!row) return;

    const $modal = $('#checkInModal');
    const $modalBody = $('#checkInModalBody');
    const $confirmBtn = $('#confirmCheckInBtn');

    $modal.find('.modal-title').text('Check In: ' + row.dataset.itemname);
    $modalBody.html('<div class="text-center p-4"><i class="fa-solid fa-spinner fa-spin fa-2x"></i><br>Loading assets...</div>');
    $confirmBtn.prop('disabled', true);

    const checkInModal = new bootstrap.Modal($modal[0]);
    checkInModal.show();

    // 1. Dapatkan senarai aset yang sedang 'on loan'
    $.ajax({
        url: 'checkout_action.php',
        method: 'GET',
        data: {
            action: 'get_assets_for_checkin',
            reservation_item_id: id
        },
        dataType: 'json',
        success: function(assets) {
            if (!assets || assets.length === 0) {
                $modalBody.html('<div class="alert alert-warning mb-0">No assets found in \'Checked Out\' status for this item. It might have already been returned.</div>');
                return;
            }

            // 2. Bina form check-in secara dinamik
            let html = `<p>Please set the condition for each returned asset (${assets.length} unit(s)).</p>`;
            html += `<form id="checkInForm">`;
            html += `<input type="hidden" id="checkin_reservation_item_id" value="${id}">`; 

            assets.forEach(asset => {
                const asset_id = asset.asset_id;
                const unique_radio_name = `condition_${asset_id}`;
                
                html += `
                    <div class="card mb-3 p-3 checkin-asset-card" data-asset-id="${asset_id}">
                        <h6 class="mb-2 fw-bold text-primary">${asset.asset_code}</h6>
                        <div class="mb-2">
                            <div class="form-check form-check-inline">
                                <input class="form-check-input" type="radio" name="${unique_radio_name}" id="condition_good_${asset_id}" value="Good" required checked>
                                <label class="form-check-label" for="condition_good_${asset_id}">Good</label>
                            </div>
                            <div class="form-check form-check-inline">
                                <input class="form-check-input" type="radio" name="${unique_radio_name}" id="condition_damaged_${asset_id}" value="Damaged/Incomplete">
                                <label class="form-check-label text-danger" for="condition_damaged_${asset_id}">Damaged/Incomplete</label>
                            </div>
                        </div>
						
						<div class="form-check form-check-inline">
                            <input class="form-check-input asset-status" type="radio" name="${unique_radio_name}" id="status_missing_${asset_id}" value="Not_Returned_Yet" required>
                            <label class="form-check-label text-danger" for="status_missing_${asset_id}"><strong>Not Returned Yet (Left Behind)<strong></label>
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
            $confirmBtn.prop('disabled', false); 
            
            $modalBody.find('input[type="radio"]:checked').trigger('change');
        },
        error: function(xhr) {
            let realErrorMessage = xhr.responseText;
            if (xhr.responseJSON && xhr.responseJSON.message) {
                realErrorMessage = xhr.responseJSON.message;
            }
            $modalBody.html('<div class="alert alert-danger mb-0">Error loading asset list: ' + realErrorMessage + '</div>');
        }
    });
}


// =========================================================================================
// 5. DOCUMENT READY (Event Listeners Global)
// =========================================================================================

$(document).ready(function() {
    // --- SIDEBAR TOGGLE LOGIC --- (Anda boleh semak bahagian ini dalam kod asal anda)
    const $sidebar = $('.sidebar');
    const $overlay = $('#sidebarOverlay');
    const $focusableElements = $sidebar.find('a, button');

    function setSidebarState(open) {
        if (open) {
            $sidebar.addClass('toggled'); 
            $overlay.addClass('active');
            $focusableElements.attr('tabindex', '0'); 
        } else {
            $sidebar.removeClass('toggled');
            $overlay.removeClass('active');
            $focusableElements.attr('tabindex', '-1'); 
        }
    }

    $('#sidebarToggle').on('click', function(e) {
        e.preventDefault();
        setSidebarState(!$sidebar.hasClass('toggled'));
    });

    $('#sidebarOverlay').on('click', function() {
        setSidebarState(false);
    });

    $(window).on('load resize', function() {
        if ($(window).width() >= 992) {
            $sidebar.removeClass('toggled'); 
            $overlay.removeClass('active'); 
            $focusableElements.attr('tabindex', '0'); 
        } else {
            $sidebar.removeClass('toggled');
            $overlay.removeClass('active');
            $focusableElements.attr('tabindex', '-1'); 
        }
    });
    
    // --- MODAL & CHECK-IN LOGIC ---
    
    // Logik untuk memaparkan kotak Remarks berdasarkan kondisi Check-In
    $('#checkInModalBody').on('change', 'input[type="radio"]', function() {
        const $card = $(this).closest('.checkin-asset-card');
        const $remarksContainer = $card.find('.remarks-container');
        const $remarksLabel = $remarksContainer.find('label');
        const $remarksInput = $card.find('input[id^="remarks_"]');
        
        $remarksInput.prop('required', false);
        $remarksInput.val('');
        
        const selectedValue = $(this).val();

        if (selectedValue === 'Damaged/Incomplete') {
            $remarksLabel.text('Remarks (Required if damaged):'); 
            $remarksInput.prop('required', true);
            $remarksContainer.slideDown();
            
        } else if (selectedValue === 'Not_Returned_Yet') {
            $remarksLabel.text('Remarks (Optional - e.g., Reason left behind):'); 
            $remarksContainer.slideDown(); 
            
        } else { // 'Good'
            $remarksLabel.text('Remarks (Optional):'); 
            $remarksContainer.slideUp();
        }
    });
    
    // Tindakan AJAX Check-In Multi
    $('#confirmCheckInBtn').on('click', function() {
        const reservation_item_id = $('#checkin_reservation_item_id').val();
        let asset_conditions = [];
        let isValid = true;
        let firstErrorField = null;

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

        const $btn = $(this);

        Swal.fire({
            title: 'Confirm Check-In?',
            text: `You are about to check in ${asset_conditions.length} asset(s). This action cannot be undone.`,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#3b82f6',
            cancelButtonColor: '#6c757d',
            confirmButtonText: 'Yes, confirm check-in!'
        }).then((result) => {
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
                    let realErrorMessage = xhr.responseText;
                    if (xhr.responseJSON && xhr.responseJSON.message) {
                        realErrorMessage = xhr.responseJSON.message;
                    }
                    Swal.fire('Error', 'An error occurred during check-in. Details: ' + realErrorMessage, 'error');
                    $btn.prop('disabled', false).text('Confirm Check-In');
                });
            }
        });
    });
    
});
</script>

</body>
</html>