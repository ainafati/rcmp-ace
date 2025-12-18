<?php
session_start();
include '../config.php';


if (!isset($_SESSION['person_id'])) {
    header("Location: ../login.php");
    exit();
}
$person_id = (int)$_SESSION['person_id'];


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
                ri.id AS reservation_item_id, ri.status, ri.quantity, r.reserve_date, r.return_date,
                r.created_at AS apply_date, 
                r.priority, r.reserve_id, /* <<< KOMA DITAMBAH DI SINI */
                r.reason AS reservation_reason,
                u.name AS user_name, u.phoneNum AS user_phone,
                u.person_id AS user_person_id,
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

    
    $sql .= " ORDER BY u.name ASC, r.reserve_id ASC, r.created_at ASC"; 

    
    $stmt = $conn->prepare($sql);

    
    if ($stmt === false) {
        die('SQL Prepare failed: ' . $conn->error . '. Query: ' . $sql);
    }
    
    
    $bind_params = [];
    $bind_params[] = $bind_types;
    foreach ($bind_values as $key => $value) {
        $bind_params[] = &$bind_values[$key]; 
    }
    
    call_user_func_array([$stmt, 'bind_param'], $bind_params); 

    
    $stmt->execute();
    $result = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    return $result;
}

$pending_requests = fetch_reservations_by_status($conn, ['Pending'], $filter_date);
$approved_requests = fetch_reservations_by_status($conn, ['Approved'], $filter_date);
$on_loan_requests = fetch_reservations_by_status($conn, ['Checked Out'], $filter_date);
$completed_requests = fetch_reservations_by_status($conn, ['Returned', 'Rejected', 'Cancelled'], $filter_date);


$assetSql = "
    SELECT asset_id, item_id, asset_code, brand, model 
    FROM assets 
    WHERE status = 'Available'
";
$assetResult = $conn->query($assetSql);
if (!$assetResult) {
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

    
    $grouped_by_user = [];
    foreach ($requests as $row) {
        $user_name = $row['user_name'];
        $grouped_by_user[$user_name][] = $row;
    }

    $main_accordion_id = 'accordion_main_' . uniqid();
    
    
    $current_status = isset($requests[0]) ? strtolower(trim($requests[0]['status'])) : '';
    $is_pending_tab = ($current_status === 'pending');
    $is_approved_tab = ($current_status === 'approved');
    $is_checked_out_tab = ($current_status === 'checked out');

    echo '<div class="accordion" id="' . $main_accordion_id . '">';

    $user_index = 0;

    
    foreach ($grouped_by_user as $user_name => $user_items) {
        
        
        $grouped_by_reserve = [];
        foreach ($user_items as $item) {
            $grouped_by_reserve[$item['reserve_id']][] = $item;
        }

        $total_reserve_count = count($grouped_by_reserve);
        $user_phone = $user_items[0]['user_phone']; 
        
        
        $user_collapse_id = 'collapse_user_' . $user_index;
        $user_header_id = 'header_user_' . $user_index;
        $inner_accordion_id = 'inner_accordion_' . $user_index; 

        echo '<div class="accordion-item shadow-sm mb-3">';

        
        echo '<h2 class="accordion-header" id="' . $user_header_id . '">';
        echo '  <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#' . $user_collapse_id . '" aria-expanded="false" aria-controls="' . $user_collapse_id . '">';
        
        echo '    <div class="d-flex justify-content-between w-100 pe-3">'; 
        echo '      <div>';
        echo '        <strong class="fs-6">' . htmlspecialchars($user_name) . '</strong>';
        echo '        <span class="text-muted ms-2" style="font-size: 0.9em;">(' . htmlspecialchars($user_phone) . ')</span>';
        echo '      </div>';
        echo '      <div class="mt-1">';
        echo '        <span class="badge bg-primary rounded-pill">' . $total_reserve_count . ' Reservation(s)</span>';
        echo '      </div>';
        echo '    </div>';

        echo '  </button>';
        echo '</h2>';
        
        
        echo '<div id="' . $user_collapse_id . '" class="accordion-collapse collapse" aria-labelledby="' . $user_header_id . '" data-bs-parent="#' . $main_accordion_id . '">';
        echo '  <div class="accordion-body p-3">'; 

        
        echo '<div class="accordion" id="' . $inner_accordion_id . '">';
        $reserve_index = 0;

        foreach ($grouped_by_reserve as $reserve_id => $reservation_items) {
            $total_items_in_booking = count($reservation_items);

            $reserve_collapse_id = 'collapse_reserve_' . $user_index . '_' . $reserve_index;
            $reserve_header_id = 'header_reserve_' . $user_index . '_' . $reserve_index;
            
            
            $has_relevant_items = false;
            foreach ($reservation_items as $item) {
                $status = strtolower(trim($item['status']));
                if (($is_pending_tab && $status === 'pending') ||
                    ($is_approved_tab && $status === 'approved') ||
                    ($is_checked_out_tab && $status === 'checked out')) {
                    $has_relevant_items = true;
                    break;
                }
            }

            echo '<div class="accordion-item mb-2 border rounded">';

            
            echo '<h2 class="accordion-header p-0" id="' . $reserve_header_id . '">';
            
            
            echo '  <div class="d-flex align-items-center bg-light rounded-top">';
            
            
            echo '    <button class="accordion-button collapsed p-3 bg-light w-auto flex-grow-1" type="button" data-bs-toggle="collapse" data-bs-target="#' . $reserve_collapse_id . '" aria-expanded="false" aria-controls="' . $reserve_collapse_id . '">';
            echo '      <div class="d-flex justify-content-between w-100 pe-3">'; 
            echo '        <div class="d-flex align-items-center">';
            echo '          <strong><i class="fa-solid fa-bookmark me-2 text-primary"></i> Booking ID: ' . htmlspecialchars($reserve_id) . '</strong>';
            echo '          <span class="ms-3 badge bg-info text-dark rounded-pill">' . $total_items_in_booking . ' Item(s)</span>';
            echo '        </div>';
            echo '      </div>';
            echo '    </button>';

            
            if ($has_relevant_items) {
                
                if ($is_approved_tab) {
                    
                    echo '  <button 
                                class="btn btn-primary btn-sm checkout-all-btn me-3" 
                                data-reserve-id="' . htmlspecialchars($reserve_id) . '"
                                title="Check out all approved items in this booking."
                                style="flex-shrink: 0;">
                                <i class="fa-solid fa-box-open"></i> Check Out All
                            </button>';
                } 
            }
            

            echo '  </div>'; 
            echo '</h2>';

            
            echo '<div id="' . $reserve_collapse_id . '" class="accordion-collapse collapse" aria-labelledby="' . $reserve_header_id . '" data-bs-parent="#' . $inner_accordion_id . '">';
            echo '  <div class="accordion-body p-0">'; 

            
            echo '<div class="table-responsive"><table class="table mb-0 align-middle table-sm">';
            echo '<thead><tr>';
            echo '  <th class="ps-3">Item / Priority</th>';
            echo '  <th class="text-center">Qty</th>';
            echo '  <th>Duration / Applied</th>';
            echo '  <th>Status</th>';
            echo '  <th class="text-center pe-3">Actions</th>';
            echo '</tr></thead><tbody>';

            foreach ($reservation_items as $row) {
                
                $status = strtolower(trim($row['status']));

                
                $priority_class = 'bg-secondary';
                $priority_text = 'Normal Priority';
                if ($row['priority'] == 1) {
                    $priority_class = 'bg-danger';
                    $priority_text = 'High Priority';
                } elseif ($row['priority'] == 2) {
                    $priority_class = 'bg-warning text-dark';
                    $priority_text = 'Moderate Priority';
                } else {
                    $priority_class = 'bg-info text-dark';
                    $priority_text = 'Low Priority';
                }

                
                $badgeClass = 'bg-secondary';
                if ($status === 'pending') $badgeClass = 'bg-warning text-dark';
                if ($status === 'approved') $badgeClass = 'bg-success';
                if ($status === 'checked out') $badgeClass = 'bg-primary';
                if ($status === 'rejected') $badgeClass = 'bg-danger';
                
                
                echo "<tr id='row-{$row['reservation_item_id']}'  
                     data-phone='" . htmlspecialchars($row['user_phone']) . "' 
                     data-itemname='" . htmlspecialchars($row['item_name']) . "' 
                     data-user-name='" . htmlspecialchars($row['user_name']) . "' 
                     data-user-id='{$row['user_person_id']}'  
                     data-item-id='{$row['item_id']}' 
                     data-reason='" . htmlspecialchars($row['reservation_reason']) . "' 
                     data-qty='{$row['quantity']}'
                     >"; 
        
                echo "<td class='ps-3'><strong>" . htmlspecialchars($row['item_name']) . "</strong>";
                echo "<div><span class='badge rounded-pill $priority_class' style='font-size: 0.7em;'>$priority_text</span></div>";
                echo "</td>";
        
                echo "<td class='text-center'><strong>{$row['quantity']}</strong></td>";
                echo "<td>" . date('d M Y', strtotime($row['reserve_date'])) . " to " . date('d M Y', strtotime($row['return_date'])) . "<div class='info-secondary'>Applied: " . date('d M Y', strtotime($row['apply_date'])) . "</div></td>";
                echo "<td><span class='badge rounded-pill $badgeClass'>" . ucfirst(str_replace('_', ' ', $status)) . "</span></td>";
                echo "<td class='text-center pe-3'>";
                
                
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

            echo '</tbody></table></div>'; 
            
            echo '  </div>'; 
            echo '</div>'; 
            echo '</div>'; 

            $reserve_index++;
        } 

        echo '</div>'; 
        
        echo '  </div>'; 
        echo '</div>'; 
        echo '</div>'; 

        $user_index++;
    } 

    echo '</div>'; 
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
    /* --- DEFINISI WARNA TEMA (ROOT VARIABLES) --- */
    :root {
        --primary-color: #06b6d4; /* Cyan 600 (Biru Teal Gelap) */
        --primary-light: #f0f9ff; /* Biru Sangat Muda */
        --primary-hover: #0891b2; /* Cyan 700 */
        --danger-color: #ef4444; /* Merah */
        
        --bg-light-gray: #f8fafc;
        --card-bg: #ffffff;
        --text-dark: #1e293b; 
        --text-muted: #64748b; 
        --border-color: #e5e7eb;
    }

    /* BASE & TYPOGRAPHY */
    body { font-family: 'Inter', 'Segoe UI', sans-serif; background-color: var(--bg-light-gray); color: #334155; }
    .card h5, .modal-title, .topbar h3 { font-weight: 600; color: var(--text-dark); }
    
    /* SIDEBAR */
    .sidebar { 
        width: 250px; position: fixed; top: 0; bottom: 0; left: 0; 
        background: var(--card-bg); padding: 20px; 
        border-right: 1px solid var(--border-color); 
        display: flex; flex-direction: column; justify-content: space-between; z-index: 1000;
    }
    .sidebar-header { display: flex; align-items: center; gap: 12px; margin-bottom: 30px; }
    
    /* LOGO ICON (Menggunakan --primary-color) */
    .logo-icon { 
        width: 40px; height: 40px; 
        background-color: var(--primary-color); /* Cyan */
        color: white; border-radius: 8px; 
        display: flex; align-items: center; justify-content: center; font-size: 20px; 
    }
    
    .logo-text strong { display: block; font-size: 16px; color: var(--text-dark); }
    .logo-text span { font-size: 12px; color: #94a3b8; }
    
    .sidebar a { 
        display: flex; align-items: center; gap: 12px; 
        color: var(--text-muted); text-decoration: none; padding: 12px 15px; 
        margin-bottom: 8px; border-radius: 8px; font-weight: 500; transition: all 0.2s ease-in-out; 
    }
    
    /* ACTIVE & HOVER LINK (Menggunakan --primary-color) */
    .sidebar a.active, .sidebar a:hover { 
        background: var(--primary-color); /* Cyan */
        color: #fff; 
    }
    
    /* LOGOUT LINK */
    .sidebar a.logout-link { 
        color: var(--danger-color); font-weight: 600; margin-top: auto; 
    }
    .sidebar a.logout-link:hover { 
        color: #fff; 
        background: var(--danger-color); /* Merah */
    }
    
    /* MAIN CONTENT & TOPBAR */
    .main-content { margin-left: 250px; }
    .topbar { background: var(--card-bg); padding: 15px 30px; display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid var(--border-color); }
    .topbar h3 { font-weight: 600; color: var(--text-dark); margin: 0; font-size: 22px; }
    .container-fluid { padding: 30px; }
    
    /* CARD & TABLE */
    .card { border-radius: 16px; padding: 25px; box-shadow: 0 4px 12px rgba(0,0,0,0.05); background: var(--card-bg); margin-bottom: 25px; border: 1px solid #e2e8f0; }
    .table thead th { background: var(--bg-light-gray); font-weight: 600; text-transform: uppercase; font-size: 12px; color: var(--text-muted); border: none; }
    .table tbody td { border-bottom-color: #f1f5f9; }
    .table tbody tr:last-child td { border-bottom: none; }
    .info-secondary { font-size: 0.85rem; color: var(--text-muted); }

    /* NAVIGATION TABS */
    .nav-tabs .nav-link { color: #475569; font-weight: 500; border: none; border-bottom: 2px solid transparent;}
    /* ACTIVE TAB (Menggunakan --primary-color) */
    .nav-tabs .nav-link.active { 
        color: var(--primary-color); 
        border-bottom-color: var(--primary-color); /* Cyan */
        background-color: transparent;
    }
    .nav-tabs { border-bottom-color: var(--border-color); }
    .btn { border-radius: 8px; font-weight: 500;}

    /* DATATABLES PAGINATION (Menggunakan --primary-color) */
    .dataTables_wrapper .dataTables_paginate .page-item.active .page-link { 
        background-color: var(--primary-color); /* Cyan */
        border-color: var(--primary-color); /* Cyan */
        color: white; 
    }
    .dataTables_wrapper .dataTables_paginate .page-link { 
        color: var(--primary-color); /* Cyan */
    }
    .dataTables_wrapper .dataTables_length, .dataTables_wrapper .dataTables_filter { margin-bottom: 1rem; }
    .dataTables_wrapper .form-control, .dataTables_wrapper .form-select { border-radius: 8px; font-size: 0.9rem; }
    .dataTables_info { font-size: 0.9rem; color: var(--text-muted); padding-top: 0.5rem !important; }

@media (max-width: 767.98px) {
    /* Sasarkan butang "Check Out All" sahaja */
    .checkout-all-btn {
        /* Tukar saiz fon kepada yang lebih kecil */
        font-size: 0.75rem !important; /* Contoh: 12px */
        
        /* Kurangkan padding butang untuk menjadikannya lebih nipis */
        padding: 0.3rem 0.6rem !important;
        
        /* Pastikan ia tidak cuba mengambil lebar penuh */
        max-width: fit-content;
        
        /* Pastikan teks kekal sebaris */
        white-space: nowrap !important;
        
        /* Jarakkan dari elemen sebelah kiri jika ada */
        margin-left: 5px;
    }

        .sidebar {
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
            <span class="fw-bold d-none d-md-block"><?= htmlspecialchars($tech['name']) ?></span>
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
<p>
    <strong>Quantity Requested:</strong> 
    <span id="requestedQtyText"></span> 
</p>
<p>
    <strong>Reason:</strong> 
    <span id="reservationReasonText" ></span>
</p>
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


const availableAssets = <?php echo $availableAssets_json; ?>;

$(document).ready(function() {
    
    function handleBulkAction(action, reserveId, title, text, confirmText, confirmColor) {
        
        Swal.fire({
            title: title,
            text: text,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: confirmColor,
            cancelButtonColor: '#6c757d',
            confirmButtonText: confirmText
        }).then((result) => {
            if (result.isConfirmed) {
                var $button = $(`[data-reserve-id="${reserveId}"]`); 
                var originalText = $button.html(); 
                
                $button.prop('disabled', true).html('<i class="fa-solid fa-spinner fa-spin me-1"></i> Processing...');

                $.ajax({
                    url: 'checkout_action.php', 
                    type: 'POST',
                    dataType: 'json',
                    data: {
                        action: action,
                        reserve_id: reserveId
                    },
                    success: function(response) {
                        if (response.success) {
                            Swal.fire('Success!', response.message, 'success').then(() => {
                                location.reload(); 
                            });
                        } else {
                            Swal.fire('Error', 'Gagal: ' + (response.message || 'Unknown error occurred.'), 'error');
                            $button.prop('disabled', false).html(originalText); 
                        }
                    },
                    error: function(xhr) {
                        Swal.fire('Network Error', 'A network error occurred or server did not respond correctly.', 'error');
                        $button.prop('disabled', false).html(originalText);
                    }
                });
            }
        });
    }

    
    $(document).on('click', '.checkout-all-btn', function() {
        var reserveId = $(this).data('reserve-id');
        handleBulkAction(
            'checkout_all_items', 
            reserveId,
            'Confirm  Check-Out?',
            "All items approved in Booking ID: " + reserveId + " will be marked as Checked Out (On Loan).",
            'Yes, Check Out All!',
            '#3b82f6' 
        );
    });


function checkOutItem(id) {
    const $btn = $(`[onclick="checkOutItem(${id})"]`); 
    const originalText = $btn.html();

    Swal.fire({
        title: 'Confirm Check-Out?', 
        text: "Are you sure you want to mark this item (and all its assigned assets) as Checked Out?", 
        icon: 'question',
        showCancelButton: true, 
        confirmButtonColor: '#3b82f6', 
        cancelButtonColor: '#6c757d', 
        confirmButtonText: 'Yes, check it out!'
    }).then((result) => {
        if (result.isConfirmed) {
            
            
            Swal.fire({ 
                title: 'Preparing Assets...', 
                text: 'Fetching assets list from server.', 
                allowOutsideClick: false, 
                didOpen: () => { Swal.showLoading(); }
            });
            $btn.prop('disabled', true).html('<i class="fa-solid fa-spinner fa-spin me-1"></i> Checking Out...');
            
            
            $.ajax({
                url: 'checkout_action.php',
                method: 'GET',
                dataType: 'json',
                data: { action: 'get_assets_for_checkout', reservation_item_id: id },
                success: function(assets) {
                    
                    if (!assets || assets.length === 0) {
                        Swal.fire('Error', 'No assets found in \'Reserved\' status for this item. Check status in DB.', 'error');
                        $btn.prop('disabled', false).html(originalText);
                        return;
                    }

                    const asset_ids = assets.map(a => a.asset_id);
                    
                    
                    $.ajax({
                        url: 'checkout_action.php',
                        method: 'POST',
                        dataType: 'json',
                        data: {
                            action: 'checkout_multi',
                            reservation_item_id: id,
                            asset_ids: JSON.stringify(asset_ids) 
                        },
                        success: function(data) {
                            Swal.close();
                            Swal.fire({ 
                                title: 'Checked Out!', 
                                text: data.message, 
                                icon: 'success', 
                                timer: 2000, 
                                showConfirmButton: false 
                            }).then(() => location.reload());
                        },
                        error: function(xhr) {
                            Swal.close();
                            let realErrorMessage = xhr.responseText;
                            if (xhr.responseJSON && xhr.responseJSON.message) {
                                realErrorMessage = xhr.responseJSON.message;
                            }
                            
                            Swal.fire('Error', 'An error occurred during check-out. Details: ' + realErrorMessage, 'error');
                            $btn.prop('disabled', false).html(originalText);
                        }
                    });
                },
                error: function(xhr) {
                    Swal.close();
                    let realErrorMessage = xhr.responseText;
                    if (xhr.responseJSON && xhr.responseJSON.message) {
                        realErrorMessage = xhr.responseJSON.message;
                    }
                    Swal.fire('Error', 'Failed to fetch asset list. Details: ' + realErrorMessage, 'error');
                    $btn.prop('disabled', false).html(originalText);
                }
            });
        }
    });
}

window.checkOutItem = checkOutItem;


function openApproveModal(id) {
    const row = document.getElementById('row-' + id);
    if (!row) return;

    const originalQty = parseInt(row.dataset.qty);
    const itemId = row.dataset.itemId;
    const reservationReason = row.dataset.reason; 
    
    const assets = availableAssets[itemId] || [];
    const availableCount = assets.length;

    const $approveBtn = $('#confirmApproveBtn');
    const $assetContainer = $('#assetListContainer');
    const $approveQtyInput = $('#approve_actual_qty');
    const $partialRejectContainer = $('#partialRejectionReasonContainer');
    const $partialRejectReason = $('#partial_reject_reason');
    
    $('#userName').text(row.dataset.userName);
    $('#itemName').text(row.dataset.itemname);
    $('#userPhone').text(row.dataset.phone);
    
    $('#reservationReasonText').text(reservationReason || 'N/A'); 
    $('#approve_reservation_item_id').val(id);
    $('#requestedQtyText').text(originalQty);
    $('#approve_original_qty').val(originalQty);
    
    let qtyToApprove = (availableCount >= originalQty) ? originalQty : availableCount;
    $approveQtyInput.val(qtyToApprove);
    $approveQtyInput.attr('max', availableCount); 

    function updatePartialReasonDisplay(currentQty) {
        if (currentQty < originalQty) {
            $partialRejectContainer.slideDown();
            $partialRejectReason.attr('required', true); 
        } else {
            $partialRejectContainer.slideUp();
            $partialRejectReason.attr('required', false).val(''); 
        }
    }

    function validateApproveButton() {
        const currentQtyToApprove = parseInt($approveQtyInput.val());
        const checkedCount = $('.asset-checkbox:checked').length;
        
        let isReasonValid = true;
        if (currentQtyToApprove < originalQty) {
             isReasonValid = $partialRejectReason.val().trim().length >= 5; 
        }
        
        if (isNaN(currentQtyToApprove) || currentQtyToApprove < 0 || !isReasonValid) {
              $approveBtn.prop('disabled', true);
        } else if (currentQtyToApprove > 0) {
            $approveBtn.prop('disabled', checkedCount !== currentQtyToApprove);
        } else if (currentQtyToApprove === 0) {
            $approveBtn.prop('disabled', !isReasonValid);
        } else {
            $approveBtn.prop('disabled', true);
        }
    }

function buildAssetCheckboxes() {
    const currentQtyToApprove = parseInt($approveQtyInput.val());

    if (isNaN(currentQtyToApprove) || currentQtyToApprove < 0) {
        $assetContainer.html('<div class="alert alert-warning">Please enter a valid quantity.</div>');
    } else {
        let html = `
            <div class="table-responsive" style="max-height: 400px; overflow-y: auto;">
                <table class="table table-sm table-hover align-middle border-top-0">
                    <thead class="table-light sticky-top">
                        <tr style="font-size: 0.75rem;" class="text-uppercase text-muted">
                            <th class="text-center" style="width: 40px;">Sel</th>
                            <th style="width: 120px;">Asset Code</th>
                            <th>Brand / Model Information</th>
                        </tr>
                    </thead>
                    <tbody>`;

        assets.forEach(a => {
            // Kita pecahkan brand dan model supaya lebih senang baca
            const brandName = a.brand || 'No Brand';
            const modelName = a.model || 'No Model';

            html += `
                <tr style="font-size: 0.85rem;">
                    <td class="text-center">
                        <input class="form-check-input asset-checkbox" type="checkbox" value="${a.asset_id}" id="asset-${a.asset_id}">
                    </td>
                    <td>
                        <label for="asset-${a.asset_id}" class="fw-bold mb-0 text-primary" style="cursor: pointer;">
                            ${a.asset_code}
                        </label>
                    </td>
                    <td>
                        <label for="asset-${a.asset_id}" class="mb-0 d-block" style="cursor: pointer;">
                            <span class="fw-bold text-dark">${brandName}</span> 
                            <span class="text-muted mx-1">|</span> 
                            <span class="text-secondary">${modelName}</span>
                        </label>
                    </td>
                </tr>`;
        });

        html += `</tbody></table></div>`;
        $assetContainer.html(html);
    }
    validateApproveButton();
}
    $approveQtyInput.off('change keyup');
    $assetContainer.off('change.assetcheck');
    $partialRejectReason.off('change keyup'); 

    $approveQtyInput.on('change keyup', function() {
        buildAssetCheckboxes();
        updatePartialReasonDisplay(parseInt($(this).val()));
    }); 

    $partialRejectReason.on('change keyup', validateApproveButton); 
    $assetContainer.on('change.assetcheck', '.asset-checkbox', validateApproveButton); 

    buildAssetCheckboxes();
    updatePartialReasonDisplay(qtyToApprove);

    new bootstrap.Modal('#approveDetailsModal').show();
}    
    window.openApproveModal = openApproveModal;


    
    $('#confirmApproveBtn').on('click', function() {
        const reservation_item_id = $('#approve_reservation_item_id').val();
        const selected = $('.asset-checkbox:checked').map(function() { return $(this).val(); }).get();
        
        const actualApprovedQty = parseInt($('#approve_actual_qty').val());
        const originalQty = parseInt($('#approve_original_qty').val());
        const reasonForPartialReject = $('#partial_reject_reason').val().trim();

        
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
    
    window.openRejectModal = openRejectModal;

    
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
                                    <input class="form-check-input" type="radio" name="${unique_radio_name}" id="condition_damaged_${asset_id}" value="Maintenance">
                                    <label class="form-check-label text-danger" for="condition_damaged_${asset_id}">Damaged</label>
                                </div>
                            </div>
                            <div class="form-check form-check-inline">
                                <input class="form-check-input asset-status" type="radio" name="${unique_radio_name}" id="status_missing_${asset_id}" value="Not_Returned_Yet" required>
                                <label class="form-check-label text-danger" for="status_missing_${asset_id}"><strong>Not Returned Yet (Left Behind)</strong></label>
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
    
    window.checkInItem = checkInItem;

    
    $('#checkInModalBody').on('change', 'input[type="radio"]', function() {
        const $card = $(this).closest('.checkin-asset-card');
        const $remarksContainer = $card.find('.remarks-container');
        const $remarksLabel = $remarksContainer.find('label');
        const $remarksInput = $card.find('input[id^="remarks_"]');
        
        $remarksInput.prop('required', false);
        $remarksInput.val('');
        
        const selectedValue = $(this).val();

        if (selectedValue === 'Maintenance') {
            $remarksLabel.text('Remarks (Required if damaged):'); 
            $remarksInput.prop('required', true);
            $remarksContainer.slideDown();
            
        } else if (selectedValue === 'Not_Returned_Yet') {
            $remarksLabel.text('Remarks (Optional - e.g., Reason left behind):'); 
            $remarksContainer.slideDown(); 
            
        } else { 
            $remarksLabel.text('Remarks (Optional):'); 
            $remarksContainer.slideUp();
        }
    });

    
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
    
});
</script>
</body>
</html>