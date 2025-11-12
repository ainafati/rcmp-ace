<?php


session_start();

include '../config.php';
include_once '../logger.php';


if (!isset($_SESSION['admin_id'])) {
    header("Location: login.php");
    exit();
}


if (isset($_GET['delete_asset_id']) && isset($_GET['item_id_return'])) {
    $delete_id = (int)$_GET['delete_asset_id'];
    $item_id_return = (int)$_GET['item_id_return'];

    
    $stmt_info = $conn->prepare("SELECT asset_code FROM assets WHERE asset_id = ?");
    $stmt_info->bind_param("i", $delete_id);
    $stmt_info->execute();
    $stmt_info->bind_result($asset_code_to_delete);
    $stmt_info->fetch();
    $stmt_info->close();

    $conn->begin_transaction();
    try {
        
        $conn->query("DELETE FROM reservation_assets WHERE asset_id = $delete_id");

        
        $stmt_delete = $conn->prepare("DELETE FROM assets WHERE asset_id = ?");
        $stmt_delete->bind_param("i", $delete_id);
        $stmt_delete->execute();
        $stmt_delete->close();
        
        $conn->commit();
        
        
        $admin_id_session = (int)$_SESSION['admin_id'];
        $admin_name_session = "Admin";
        $log_details = "Admin '{$admin_name_session}' (ID: {$admin_id_session}) telah memadam unit aset: '{$asset_code_to_delete}' (ID: {$delete_id}).";
        log_activity($conn, 'admin', $admin_id_session, 'DELETE_ASSET_UNIT', $log_details);
        

        $_SESSION['message'] = ['type' => 'success', 'text' => 'Asset unit deleted successfully!'];
    } catch (Exception $e) {
        $conn->rollback();
        $_SESSION['message'] = ['type' => 'error', 'text' => 'Gagal memadam aset. Mungkin ada rekod lama yang terikat.'];
    }

    header("Location: view_assets.php?item_id=" . $item_id_return);
    exit();
}


if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['edit_asset'])) {
    $asset_id = (int)$_POST['asset_id'];
    $brand = trim($_POST['brand']);
    $model = trim($_POST['model']);
    $status = trim($_POST['status']);
    $item_id_return = (int)$_POST['item_id_return'];

    
    $stmt_old = $conn->prepare("SELECT asset_code, brand, model, status FROM assets WHERE asset_id = ?");
    $stmt_old->bind_param("i", $asset_id);
    $stmt_old->execute();
    $result_old = $stmt_old->get_result();
    $old_asset = $result_old->fetch_assoc();
    $stmt_old->close();


    $stmt = $conn->prepare("UPDATE assets SET brand = ?, model = ?, status = ? WHERE asset_id = ?");
    $stmt->bind_param("sssi", $brand, $model, $status, $asset_id);
    
    if ($stmt->execute()) {
        
        
        $admin_id_session = (int)$_SESSION['admin_id'];
        $admin_name_session = "Admin";
        $log_details = "Admin '{$admin_name_session}' (ID: {$admin_id_session}) telah mengemas kini aset: '{$old_asset['asset_code']}' (ID: {$asset_id}). Perubahan: Status dari '{$old_asset['status']}' ke '{$status}', Brand: '{$old_asset['brand']}' ke '{$brand}'.";
        log_activity($conn, 'admin', $admin_id_session, 'EDIT_ASSET_UNIT', $log_details);
        

        $_SESSION['message'] = ['type' => 'success', 'text' => 'Asset unit updated successfully!'];
    } else {
        $_SESSION['message'] = ['type' => 'error', 'text' => 'Gagal mengemas kini aset.'];
    }
    $stmt->close();

    header("Location: view_assets.php?item_id=" . $item_id_return);
    exit();
}


$item_id_filter = isset($_GET['item_id']) ? (int)$_GET['item_id'] : 0;
if ($item_id_filter === 0) {
    header("Location: manageItem_admin.php");
    exit();
}


$status_filter = isset($_GET['status']) ? trim($_GET['status']) : '';
$where_clauses = ["i.item_id = ?"];
$param_types = "i";
$param_values = [$item_id_filter];

if (!empty($status_filter) && $status_filter != 'All') {
    $where_clauses[] = "a.status = ?";
    $param_types .= "s";
    $param_values[] = $status_filter;
}

$stmt_item = $conn->prepare("SELECT item_name, description FROM item WHERE item_id = ?");
$stmt_item->bind_param("i", $item_id_filter);
$stmt_item->execute();
$stmt_item->bind_result($item_name_title, $description);
if (!$stmt_item->fetch()) {
    header("Location: manageItem_admin.php"); exit();
}
$stmt_item->close();


$sql_assets = "
    SELECT
        a.asset_id, a.asset_code, a.status, a.brand, a.model, i.item_name,
        CASE
            WHEN a.status IN ('Borrowed', 'Checked Out') THEN u.name
            ELSE NULL
        END AS borrower_name
    FROM assets a
    JOIN item i ON a.item_id = i.item_id
    LEFT JOIN reservation_assets ra ON a.asset_id = ra.asset_id
    LEFT JOIN reservation_items ri ON ra.reservation_item_id = ri.id
             AND ri.status = 'Checked Out'
    LEFT JOIN reservations r ON ri.reserve_id = r.reserve_id
    LEFT JOIN user u ON r.user_id = u.user_id
    WHERE " . implode(' AND ', $where_clauses) . "
    ORDER BY a.asset_code ASC
";

$stmt_assets = $conn->prepare($sql_assets);
$stmt_assets->bind_param($param_types, ...$param_values);
$stmt_assets->execute();
$all_assets = $stmt_assets->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt_assets->close();

$available_statuses = ['Available', 'Borrowed', 'Maintenance', 'Damaged', 'Retired'];

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Manage Assets — <?= htmlspecialchars($item_name_title) ?></title>
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">

    <style>
        /* CSS LENGKAP & SERAGAM UNTUK TEMA BARU ANDA */
        body { font-family: 'Inter', 'Segoe UI', sans-serif; background-color: #f8fafc; color: #334155; min-height: 100vh; overflow-x: hidden; }
        
        /* Sidebar dan Desktop View (Kekal Sama) */
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
        .container-fluid { padding: 30px; }
        .card { border-radius: 16px; padding: 25px; box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05); background: #fff; margin-bottom: 25px; border: 1px solid #e2e8f0; }
        .card h5, .modal-title { font-weight: 600; color: #1e293b; }
        .table thead th { background: #f8fafc; color: #64748b; border: none; font-weight: 600; text-transform: uppercase; font-size: 12px; }
        .table tbody td { border-bottom: 1px solid #f1f5f9; }
        .table tbody tr:last-child td { border-bottom: none; }
        .badge.rounded-pill { padding: .4em .8em; font-weight: 500; }
        .btn { border-radius: 8px; font-weight: 500; }
        .btn-primary { background-color: #3b82f6; border: none; }
        .btn-primary:hover { background-color: #2563eb; }

        /* Gaya Butang Tindakan Desktop */
        .table td:last-child .btn {
            margin-right: 5px;
        }

        /* --- MOBILE VIEW (MAX-WIDTH 768px) --- */
        #sidebar-toggle-btn {
            display: none;
            background: none;
            border: none;
            color: #334155;
            font-size: 20px;
            padding: 0;
            margin-right: 15px;
        }

        @media (max-width: 768px) {
            #sidebar-toggle-btn { display: block; }
            .sidebar { transform: translateX(-100%); box-shadow: 0 0 10px rgba(0, 0, 0, 0.15); z-index: 1050; }
            .sidebar.open { transform: translateX(0); }
            .main-content { margin-left: 0; width: 100%; }
            .topbar { padding: 10px 15px; justify-content: space-between; }
            .topbar h3 { font-size: 16px; }
            .topbar .btn { font-size: 12px; padding: .4rem .6rem; }
            .container-fluid { padding: 10px 5px; }
            .card { padding: 15px; margin-bottom: 15px; }

            /* JADUAL SUPER PADAT */
            .table {
                /* Ini membolehkan jadual bergulir secara mendatar jika terlalu lebar */
                overflow-x: auto;
                display: block;
                width: 100%;
            }
            .table thead th {
                font-size: 10px;
                padding: 0.5rem 0.3rem;
                white-space: nowrap; /* Jangan benarkan header berpecah */
            }
            .table tbody td {
                padding: 0.4rem 0.3rem;
                white-space: nowrap; /* Jangan benarkan cell berpecah */
            }

            /* --- Brand dan Model DIBENARKAN MUNCUL (SILA BERGULIR JIKA TERLALU LEBAR) --- */
            
            /* BUTANG ACTIONS MENEGAK */
            .table tbody td:last-child {
                white-space: normal;
                text-align: center;
                width: 50px;
            }
            .table tbody td:last-child .btn {
                padding: 0.3rem 0.4rem;
                font-size: 0.7rem;
                margin: 1px auto;
                display: block;
                width: 80%;
            }
            
            /* Laras Filter Form */
            .form-select, .form-control { font-size: 14px; }
            .col-md-4 { width: 70%; }
            .col-md-auto { width: 30%; }
            .form-label.small { display: none; }
            .row.g-3 .btn { width: 100%; padding: .4rem .5rem; font-size: 12px; }
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
        <a href="manageItem_admin.php" class="active"><i class="fa-solid fa-box-archive"></i> Manage Items</a>
        <a href="manage_accounts.php"><i class="fa-solid fa-users-cog"></i> Manage Accounts</a>
    </div>
    <a href="logout.php" class="logout-link"><i class="fa-solid fa-right-from-bracket"></i> Logout</a>
</div>

<div class="main-content">
    <div class="topbar">
        <button id="sidebar-toggle-btn" class="me-3"><i class="fa fa-bars"></i></button>
        <h3>Asset Unit Details</h3>
        <a href="manageItem_admin.php" class="btn btn-outline-secondary btn-sm">
            <i class="fa fa-arrow-left me-2"></i> Back
        </a>
    </div>

    <div class="container-fluid">
        <div class="card">
            <h5><i class="fa-solid fa-list me-2 text-primary"></i> <?= htmlspecialchars($item_name_title) ?></h5>
            <p class="text-muted"><?= htmlspecialchars($description) ?></p>
            <hr>
            <form method="GET" class="row g-3 align-items-center mb-3">
                <input type="hidden" name="item_id" value="<?= $item_id_filter ?>">
                <div class="col-md-4">
                    <label for="status_filter" class="form-label small">Filter by Status</label>
                    <select name="status" id="status_filter" class="form-select">
                        <option value="All">Show All</option>
                        <?php foreach($available_statuses as $s): ?>
                            <option value="<?= $s ?>" <?= $status_filter == $s ? 'selected' : '' ?>><?= htmlspecialchars($s) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-auto align-self-end">
                    <button type="submit" class="btn btn-primary"><i class="fa fa-filter me-2"></i>Apply Filter</button>
                </div>
            </form>

            <div class="table-responsive">
                <table class="table table-hover align-middle">
                    <thead>
                        <tr>
                            <th>Asset Code</th><th>Brand</th><th>Model</th><th>Status</th><th>Borrower</th><th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($all_assets)): ?>
                            <tr><td colspan="6" class="text-center text-muted py-5"><i class="fa-solid fa-box-open fa-2x mb-2"></i><br>No asset units found for this item type.</td></tr>
                        <?php else: foreach($all_assets as $asset): 
                            $status = strtolower($asset['status']);
                            $badge_class = 'text-bg-light';
                            if ($status == 'available') $badge_class = 'text-bg-success';
                            if (in_array($status, ['borrowed', 'checked out'])) $badge_class = 'text-bg-warning';
                            if (in_array($status, ['damaged', 'maintenance'])) $badge_class = 'text-bg-danger';
                            if ($status == 'retired') $badge_class = 'text-bg-dark';
                        ?>
                        <tr>
                            <td><span class="badge rounded-pill text-bg-secondary"><?= htmlspecialchars($asset['asset_code']) ?></span></td>
                            <td><?= htmlspecialchars($asset['brand'] ?: '-') ?></td>
                            <td><?= htmlspecialchars($asset['model'] ?: '-') ?></td>
                            <td><span class="badge rounded-pill <?= $badge_class ?>"><?= htmlspecialchars($asset['status']) ?></span></td>
                            <td>
                                <?php if (!empty($asset['borrower_name'])): ?>
                                    <i class="fa-solid fa-user-check text-warning me-1"></i> <?= htmlspecialchars($asset['borrower_name']) ?>
                                <?php else: ?>
                                    <span class="text-muted">-</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <button class="btn btn-sm btn-outline-warning" title="Edit Unit" onclick='openEditAssetModal(<?= json_encode($asset) ?>)'><i class="fa fa-edit"></i></button>
                                <button class="btn btn-sm btn-outline-danger" title="Delete Unit" onclick="deleteAsset(<?= $asset['asset_id'] ?>, '<?= htmlspecialchars(addslashes($asset['asset_code'])) ?>', <?= $item_id_filter ?>)"><i class="fa fa-trash"></i></button>
                            </td>
                        </tr>
                        <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="editAssetModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-warning">
                <h5 class="modal-title text-dark"><i class="fa fa-edit me-2"></i> Edit Asset: <span id="asset_code_display"></span></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="editAssetForm" method="post" action="view_assets.php">
                    <input type="hidden" name="edit_asset" value="1">
                    <input type="hidden" id="edit_asset_id" name="asset_id">
                    <input type="hidden" name="item_id_return" value="<?= $item_id_filter ?>">
                    <div class="mb-3"><label class="form-label">Brand</label><input type="text" id="edit_brand" name="brand" class="form-control"></div>
                    <div class="mb-3"><label class="form-label">Model</label><input type="text" id="edit_model" name="model" class="form-control"></div>
                    <div class="mb-3"><label class="form-label">Status</label><select id="edit_status" name="status" class="form-select" required><?php foreach($available_statuses as $s): ?><option value="<?= $s ?>"><?= htmlspecialchars($s) ?></option><?php endforeach; ?></select></div>
                    <button type="submit" class="btn btn-warning w-100 text-dark">Update Asset Unit</button>
                </form>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>

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
        
        const sidebarLinks = sidebar.querySelectorAll('a');
        sidebarLinks.forEach(link => {
            link.addEventListener('click', function() {
                if (window.innerWidth <= 768) {
                    setTimeout(() => {
                        sidebar.classList.remove('open');
                        overlay.classList.remove('active');
                    }, 100);
                }
            });
        });
    }

    
    <?php
    if (isset($_SESSION['message'])) {
        $message = $_SESSION['message'];
        $message_text_js = str_replace("'", "\'", $message['text']);
        echo "Swal.fire({
            icon: '{$message['type']}',
            title: '{$message_text_js}',
            toast: true,
            position: 'top-end',
            showConfirmButton: false,
            timer: 3500,
            timerProgressBar: true
        });";
        unset($_SESSION['message']);
    }
    ?>
});



function openEditAssetModal(asset) {
    document.getElementById('edit_asset_id').value = asset.asset_id;
    document.getElementById('asset_code_display').textContent = asset.asset_code;
    document.getElementById('edit_brand').value = asset.brand;
    document.getElementById('edit_model').value = asset.model;
    document.getElementById('edit_status').value = asset.status;
    new bootstrap.Modal(document.getElementById('editAssetModal')).show();
}

function deleteAsset(id, code, item_id) {
    Swal.fire({
        title: `Delete Unit '${code}'?`,
        text: "This will permanently delete this asset unit. This action cannot be undone!",
        icon: 'error',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        cancelButtonColor: '#6c757d',
        confirmButtonText: 'Yes, delete it!'
    }).then((result) => {
        if (result.isConfirmed) {
            window.location.href = 'view_assets.php?delete_asset_id=' + id + '&item_id_return=' + item_id;
        }
    });
}
</script>
</body>
</html>