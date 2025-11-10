<?php
session_start();
include '../config.php'; // Correct path to config

// --- Authentication ---
if (!isset($_SESSION['tech_id'])) { // Changed from admin_id to tech_id to match your other files
    header("Location: ../login.php");
    exit();
}

$item_id_filter = isset($_GET['item_id']) ? (int)$_GET['item_id'] : 0;
if (isset($_POST['item_id_return'])) { // To handle return from POST form
    $item_id_filter = (int)$_POST['item_id_return'];
}

// <<< --- START: KOD BARU UNTUK EDIT & DELETE --- >>>

// --- LOGIC TO HANDLE EDIT ASSET ---
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['edit_asset'])) {
    $asset_id = (int)$_POST['asset_id'];
    $brand = trim($_POST['brand']);
    $model = trim($_POST['model']);
    $status = trim($_POST['status']);
    
    $stmt = $conn->prepare("UPDATE assets SET brand = ?, model = ?, status = ? WHERE asset_id = ?");
    $stmt->bind_param("sssi", $brand, $model, $status, $asset_id);
    $stmt->execute();
    $stmt->close();
    
    // Redirect back to the same page to show changes
    header("Location: assets_technician.php?item_id=" . $item_id_filter);
    exit();
}

// --- LOGIC TO HANDLE DELETE ASSET ---
if (isset($_GET['delete_asset_id'])) {
    $asset_id_to_delete = (int)$_GET['delete_asset_id'];
    $item_id_return = isset($_GET['item_id_return']) ? (int)$_GET['item_id_return'] : 0;
    
    // You might want to add checks here to ensure the asset isn't borrowed before deleting
    $stmt = $conn->prepare("DELETE FROM assets WHERE asset_id = ?");
    $stmt->bind_param("i", $asset_id_to_delete);
    $stmt->execute();
    $stmt->close();
    
    // Redirect back to the asset list for that item
    header("Location: assets_technician.php?item_id=" . $item_id_return);
    exit();
}

// <<< --- END: KOD BARU UNTUK EDIT & DELETE --- >>>


// --- Fetching and Display Logic (Code below is mostly the same) ---
if ($item_id_filter === 0) {
    header("Location: manageItems_tech.php"); // Corrected filename with 's'
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
    header("Location: manageItems_tech.php"); exit(); // Corrected filename with 's'
}
$stmt_item->close();

$sql_assets = "
    SELECT 
        a.asset_id, a.asset_code, a.status, a.brand, a.model, i.item_name,
        CASE 
            WHEN a.status IN ('Checked Out') THEN u.name 
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

$available_statuses = ['Available', 'Borrowed', 'Maintenance', 'Damaged'];

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Assets — <?= htmlspecialchars($item_name_title) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">

    <style>
        /* CSS LENGKAP & SERAGAM UNTUK TEMA BARU ANDA */
        body { font-family: 'Inter', 'Segoe UI', sans-serif; background-color: #f8fafc; color: #334155; min-height: 100vh; }
        .sidebar { width: 250px; position: fixed; top: 0; bottom: 0; left: 0; background: #ffffff; padding: 20px; border-right: 1px solid #e5e7eb; z-index: 1000; display: flex; flex-direction: column; justify-content: space-between; }
        .sidebar-header { display: flex; align-items: center; gap: 12px; margin-bottom: 30px; }
        .logo-icon { width: 40px; height: 40px; background-color: #3b82f6; color: white; border-radius: 8px; display: flex; align-items: center; justify-content: center; font-size: 20px; }
        .logo-text strong { display: block; font-size: 16px; color: #1e293b; }
        .logo-text span { font-size: 12px; color: #94a3b8; }
        .sidebar a { display: flex; align-items: center; gap: 12px; color: #64748b; text-decoration: none; padding: 12px 15px; margin-bottom: 8px; border-radius: 8px; font-weight: 500; font-size: 15px; transition: all 0.2s ease-in-out; }
        .sidebar a.active, .sidebar a:hover { background: #3b82f6; color: #fff; }
        .sidebar a.logout-link { color: #ef4444; font-weight: 600; margin-top: auto; }
        .sidebar a.logout-link:hover { color: #fff; background: #ef4444; }
        .main-content { margin-left: 250px; }
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

        /* KOD TAMBAHAN UNTUK MOBILE VIEW */
        @media (max-width: 992px) {
            /* Sembunyikan sidebar pada skrin kecil */
            .sidebar {
                display: none;
            }

            /* Main content guna 100% lebar skrin, tiada margin kiri */
            .main-content {
                margin-left: 0;
            }

            /* Laraskan padding topbar untuk skrin kecil */
            .topbar {
                padding: 15px 20px;
            }

            /* Laraskan padding container */
            .container-fluid {
                padding: 20px;
            }
            
            /* Saiz fon topbar pada skrin kecil */
            .topbar h3 {
                font-size: 20px;
            }

            /* Jadual: Jadikan scrollable secara mendatar (Horizontal Scroll) */
            .table-responsive {
                overflow-x: auto;
            }
            
            /* Pastikan elemen form filter berderet ke bawah (stack) dengan baik */
            .g-3 {
                --bs-gutter-x: 0; /* Buang gutter untuk susunan yang kemas */
            }
            .col-md-4, .col-md-auto {
                width: 100% !important; /* Setiap elemen ambil 100% lebar */
                margin-bottom: 10px;
            }
        }
        /* END KOD TAMBAHAN UNTUK MOBILE VIEW */
    </style>
</head>
<body>

<div class="sidebar">
    <div>
        <div class="sidebar-header">
            <div class="logo-icon"><i class="fa-solid fa-wrench"></i></div>
            <div class="logo-text"><strong>UniKL Technician</strong><span>Dashboard</span></div>
        </div>
        <a href="dashboard_tech.php"><i class="fa-solid fa-table-columns"></i> Dashboard</a>
        <a href="check_out.php"><i class="fa-solid fa-dolly"></i> Manage Requests</a>
        <a href="manageItems_tech.php" class="active"><i class="fa-solid fa-box-archive"></i> Manage Items</a>
        <a href="report.php"><i class="fa-solid fa-chart-line"></i> Report</a>
    </div>
    <a href="logout.php" class="logout-link"><i class="fa-solid fa-right-from-bracket"></i> Logout</a>
</div>

<div class="main-content">
    <div class="topbar">
        <h3>Asset Unit Details</h3>
        <a href="manageItem_tech.php" class="btn btn-outline-secondary btn-sm">
            <i class="fa fa-arrow-left me-2"></i> Back to Item Summary
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
</thead>                        <tbody>
                            <?php if (empty($all_assets)): ?>
                                <tr><td colspan="6" class="text-center text-muted py-5"><i class="fa-solid fa-box-open fa-2x mb-2"></i><br>No asset units found for this item type.</td></tr>
                            <?php else: foreach($all_assets as $asset): 
                                $status = strtolower($asset['status']);
                                $badge_class = 'text-bg-light';
                                if ($status == 'available') $badge_class = 'text-bg-success';
                                if (in_array($status, ['borrowed', 'checked out'])) $badge_class = 'text-bg-warning';
                                if (in_array($status, ['damaged', 'maintenance'])) $badge_class = 'text-bg-danger';
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
</tr>                            <?php endforeach; endif; ?>
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
                <form id="editAssetForm" method="post" action="assets_technician.php">
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
<script>
function openEditAssetModal(asset) {
    document.getElementById('edit_asset_id').value = asset.asset_id;
    document.getElementById('asset_code_display').textContent = asset.asset_code;
    document.getElementById('edit_brand').value = asset.brand;
    document.getElementById('edit_model').value = asset.model;
    document.getElementById('edit_status').value = asset.status;
    new bootstrap.Modal(document.getElementById('editAssetModal')).show();
}

function deleteAsset(id, code, item_id) {
    if (confirm("Are you sure you want to delete asset unit '" + code + "'? This cannot be undone.")) {
        window.location.href = 'assets_technician.php?delete_asset_id=' + id + '&item_id_return=' + item_id;
    }
}
</script>
</body>
</html>