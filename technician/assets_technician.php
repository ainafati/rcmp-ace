<?php
session_start();
include '../config.php'; 

if (!isset($_SESSION['person_id'])) {
    header("Location: ../login.php");
    exit();
}
$person_id = (int)$_SESSION['person_id'];

// Ambil data technician
$stmt_tech = $conn->prepare("SELECT name FROM person WHERE person_id = ?");
$stmt_tech->bind_param("i", $person_id);
$stmt_tech->execute();
$result_tech = $stmt_tech->get_result(); 
$tech = ($tech_data = $result_tech->fetch_assoc()) ? $tech_data : ['name' => 'Technician'];
$stmt_tech->close();

$item_id_filter = isset($_GET['item_id']) ? (int)$_GET['item_id'] : 0;
if (isset($_POST['item_id_return'])) { 
    $item_id_filter = (int)$_POST['item_id_return'];
}

// Logic Edit Asset
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['edit_asset'])) {
    $asset_id = (int)$_POST['asset_id'];
    $brand = trim($_POST['brand']);
    $model = trim($_POST['model']);
    $status = trim($_POST['status']);
    
    $stmt = $conn->prepare("UPDATE assets SET brand = ?, model = ?, status = ? WHERE asset_id = ?");
    $stmt->bind_param("sssi", $brand, $model, $status, $asset_id);
    $stmt->execute();
    $stmt->close();
    
    header("Location: assets_technician.php?item_id=" . $item_id_filter);
    exit();
}

// Logic Delete Asset
if (isset($_GET['delete_asset_id'])) {
    $asset_id_to_delete = (int)$_GET['delete_asset_id'];
    $item_id_return = isset($_GET['item_id_return']) ? (int)$_GET['item_id_return'] : 0;
    
    $stmt = $conn->prepare("DELETE FROM assets WHERE asset_id = ?");
    $stmt->bind_param("i", $asset_id_to_delete);
    $stmt->execute();
    $stmt->close();
    
    header("Location: assets_technician.php?item_id=" . $item_id_return);
    exit();
}

if ($item_id_filter === 0) {
    header("Location: manageItem_tech.php"); 
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

// Ambil maklumat item (Tajuk & Deskripsi)
$stmt_item = $conn->prepare("SELECT item_name, description FROM item WHERE item_id = ?");
$stmt_item->bind_param("i", $item_id_filter);
$stmt_item->execute();
$stmt_item->bind_result($item_name_title, $description);
if (!$stmt_item->fetch()) {
    header("Location: manageItem_tech.php"); exit(); 
}
$stmt_item->close();

// Query Assets
$sql_assets = "
    SELECT 
        a.asset_id, a.asset_code, a.status, a.brand, a.model, i.item_name,
        MAX(CASE 
            WHEN a.status IN ('Checked Out') THEN u.name 
            ELSE NULL 
        END) AS borrower_name 
    FROM assets a
    JOIN item i ON a.item_id = i.item_id
    LEFT JOIN reservation_assets ra ON a.asset_id = ra.asset_id
    LEFT JOIN reservation_items ri ON ra.reservation_item_id = ri.id AND ri.status = 'Checked Out'
    LEFT JOIN reservations r ON ri.reserve_id = r.reserve_id
    LEFT JOIN person u ON r.person_id = u.person_id
    WHERE " . implode(' AND ', $where_clauses) . "
    GROUP BY a.asset_id, a.asset_code, a.status, a.brand, a.model, i.item_name
    ORDER BY a.asset_code ASC
";

$stmt_assets = $conn->prepare($sql_assets);
$stmt_assets->bind_param($param_types, ...$param_values);
$stmt_assets->execute();
$all_assets = $stmt_assets->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt_assets->close();

// Konfigurasi Warna Status
$status_data = [
    'Available'   => ['color' => '#10b981', 'bg' => '#ecfdf5', 'label' => 'Available'],
    'Checked Out' => ['color' => '#f59e0b', 'bg' => '#fffbeb', 'label' => 'Checked Out'],
    'Maintenance' => ['color' => '#3b82f6', 'bg' => '#eff6ff', 'label' => 'Maintenance'],
    'Damaged'     => ['color' => '#ef4444', 'bg' => '#fef2f2', 'label' => 'Damaged']
];

// Pengiraan Statistik Automatik
$count_available = 0;
$count_on_loan = 0;
foreach ($all_assets as $asset) {
    if ($asset['status'] === 'Available') $count_available++;
    if ($asset['status'] === 'Checked Out') $count_on_loan++;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Asset Unit Management | UniKL</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">

<style>
    :root {
        --primary-color: #06b6d4;
        --primary-dark: #0891b2;
        --bg-body: #f8fafc;
        --sidebar-width: 280px;
        --card-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.04);
    }

    body { font-family: 'Inter', sans-serif; background-color: var(--bg-body); color: #1e293b; overflow-x: hidden; }

    /* SIDEBAR */
    .sidebar { 
        width: var(--sidebar-width); position: fixed; top: 0; bottom: 0; left: 0; 
        background: #fff; padding: 30px 20px; border-right: 1px solid #e2e8f0; 
        display: flex; flex-direction: column; z-index: 1000;
    }
    .sidebar-header { display: flex; align-items: center; gap: 15px; margin-bottom: 40px; padding-left: 10px; }
    .logo-icon { 
        width: 45px; height: 45px; background: linear-gradient(135deg, var(--primary-color), var(--primary-dark)); 
        color: #fff; border-radius: 12px; display: flex; align-items: center; justify-content: center; 
        font-size: 1.3rem; box-shadow: 0 4px 12px rgba(6, 182, 212, 0.3);
    }
    .logo-text { line-height: 1.2; }
    .logo-text strong { display: block; font-size: 1.1rem; color: #0f172a; }
    .logo-text span { font-size: 0.75rem; color: #64748b; letter-spacing: 1px; }

    .sidebar nav { flex-grow: 1; }
    .sidebar a { 
        display: flex; align-items: center; gap: 12px; color: #64748b; 
        text-decoration: none; padding: 14px 18px; border-radius: 12px; 
        font-weight: 500; margin-bottom: 5px; transition: 0.3s;
    }
    .sidebar a:hover { background: #f1f5f9; color: var(--primary-color); transform: translateX(5px); }
    .sidebar a.active { background: #ecfeff; color: var(--primary-color); font-weight: 600; }
    
    .sidebar a.logout-link { 
        margin-top: auto; color: #ef4444; background: #fef2f2; border: 1px solid #fecaca;
    }
    .sidebar a.logout-link:hover { background: #ef4444; color: #fff; }

    /* MAIN CONTENT */
    .main-content { margin-left: var(--sidebar-width); padding: 50px; }

    /* STATS CARD */
    .stat-card {
        background: #fff; padding: 25px; border-radius: 20px; box-shadow: var(--card-shadow);
        display: flex; align-items: center; gap: 15px; border: 1px solid transparent; transition: 0.3s;
    }
    .stat-card:hover { transform: translateY(-5px); }
    .stat-icon { width: 50px; height: 50px; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 1.4rem; }

    /* TABLE */
    .card { border-radius: 24px; border: none; box-shadow: var(--card-shadow); background: #fff; padding: 30px; }
    .table thead th { background: #f8fafc; color: #64748b; text-transform: uppercase; font-size: 0.75rem; letter-spacing: 1px; padding: 15px; border: none; }
    .asset-code-pill { background: #f1f5f9; color: #475569; font-weight: 700; padding: 6px 12px; border-radius: 8px; font-family: monospace; font-size: 0.9rem; }

    /* STATUS DOT & TOOLTIP */
    .status-indicator {
        width: 28px; height: 28px; border-radius: 50%; display: flex;
        align-items: center; justify-content: center; margin: 0 auto;
        cursor: help; transition: 0.3s; border: 2px solid transparent;
    }
    .status-indicator:hover { transform: scale(1.2); }
    .inner-dot { width: 10px; height: 10px; border-radius: 50%; }

    .btn-action { width: 38px; height: 38px; border-radius: 10px; border: none; background: #f1f5f9; transition: 0.2s; }
    .btn-action:hover { transform: scale(1.1); }
</style>
</head>
<body>

<div class="sidebar" id="offcanvasSidebar">
    <div>
        <div class="sidebar-header">
            <div class="logo-icon"><i class="fa-solid fa-wrench"></i></div>
            <div class="logo-text"><strong>UniKL Technician</strong><span>System Support</span></div>
        </div>
        <a href="dashboard_tech.php"><i class="fa-solid fa-table-columns"></i> Dashboard</a>
        <a href="check_out.php"><i class="fa-solid fa-dolly"></i> Manage Requests</a>
        <a href="manageItem_tech.php" class="active"><i class="fa-solid fa-box-archive"></i> Manage Items</a>
        <a href="report.php"><i class="fa-solid fa-chart-line"></i> Report</a>
    </div>
    <a href="logout.php" class="logout-link"><i class="fa-solid fa-right-from-bracket"></i> Logout</a>
</div>


<div class="main-content">
    <div class="d-flex justify-content-between align-items-end mb-5">
        <div>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-2">
                    <li class="breadcrumb-item"><a href="manageItem_tech.php" class="text-decoration-none text-muted">Items</a></li>
                    <li class="breadcrumb-item active">Asset Details</li>
                </ol>
            </nav>
            <h1 class="fw-bold h2 mb-0"><?= htmlspecialchars($item_name_title) ?></h1>
            <p class="text-muted mt-1"><i class="fa-solid fa-info-circle me-1"></i> <?= htmlspecialchars($description) ?></p>
        </div>
        <a href="manageItem_tech.php" class="btn btn-white border shadow-sm px-4 py-2 rounded-4 bg-white">
            <i class="fa fa-chevron-left me-2"></i> Back
        </a>
    </div>

    <div class="row g-4 mb-5">
        <div class="col-md-4">
            <div class="stat-card">
                <div class="stat-icon bg-info bg-opacity-10 text-info"><i class="fa-solid fa-boxes-stacked"></i></div>
                <div><div class="small text-muted">Total Units</div><div class="fw-bold h4 mb-0"><?= count($all_assets) ?></div></div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="stat-card" style="border-bottom: 4px solid #10b981;">
                <div class="stat-icon bg-success bg-opacity-10 text-success"><i class="fa-solid fa-check-double"></i></div>
                <div><div class="small text-muted">Available</div><div class="fw-bold h4 mb-0 text-success"><?= $count_available ?></div></div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="stat-card" style="border-bottom: 4px solid #f59e0b;">
                <div class="stat-icon bg-warning bg-opacity-10 text-warning"><i class="fa-solid fa-handshake"></i></div>
                <div><div class="small text-muted">On Loan</div><div class="fw-bold h4 mb-0 text-warning"><?= $count_on_loan ?></div></div>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h4 class="fw-bold m-0">Asset Units List</h4>
            <form method="GET">
                <input type="hidden" name="item_id" value="<?= $item_id_filter ?>">
                <select name="status" class="form-select border-0 bg-light rounded-3 shadow-sm" onchange="this.form.submit()">
                    <option value="All">🔍 Filter: All Status</option>
                    <?php foreach($status_data as $key => $val): ?>
                        <option value="<?= $key ?>" <?= $status_filter == $key ? 'selected' : '' ?>><?= $key ?></option>
                    <?php endforeach; ?>
                </select>
            </form>
        </div>

        <div class="table-responsive">
            <table class="table table-borderless align-middle">
                <thead>
                    <tr>
                        <th class="ps-3">Asset Code</th>
                        <th>Info Unit</th>
                        <th class="text-center">Status</th>
                        <th>Borrower</th>
                        <th class="text-end pe-3">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($all_assets)): ?>
                        <tr><td colspan="5" class="text-center py-5 text-muted">Tiada aset dijumpai.</td></tr>
                    <?php else: foreach($all_assets as $asset): 
                        $info = $status_data[$asset['status']] ?? ['color' => '#64748b', 'bg' => '#f1f5f9', 'label' => $asset['status']];
                    ?>
                    <tr style="border-bottom: 1px solid #f1f5f9;">
                        <td class="ps-3"><span class="asset-code-pill"><?= htmlspecialchars($asset['asset_code']) ?></span></td>
                        <td>
                            <div class="fw-bold text-dark"><?= htmlspecialchars($asset['brand'] ?: '-') ?></div>
                            <div class="text-muted small"><?= htmlspecialchars($asset['model'] ?: '-') ?></div>
                        </td>
                        <td class="text-center">
                            <div class="status-indicator" 
                                 style="background: <?= $info['bg'] ?>; border-color: <?= $info['color'] ?>40;"
                                 data-bs-toggle="tooltip" title="<?= $info['label'] ?>">
                                <div class="inner-dot" style="background: <?= $info['color'] ?>;"></div>
                            </div>
                        </td>
                        <td>
                            <?php if ($asset['borrower_name']): ?>
                                <div class="d-flex align-items-center">
                                    <img src="https://ui-avatars.com/api/?name=<?= urlencode($asset['borrower_name']) ?>&background=random&size=32" class="rounded-circle me-2">
                                    <span class="small fw-500"><?= htmlspecialchars($asset['borrower_name']) ?></span>
                                </div>
                            <?php else: ?>
                                <span class="text-muted small">--</span>
                            <?php endif; ?>
                        </td>
                        <td class="text-end pe-3">
                            <button class="btn-action text-warning me-1" onclick='openEditModal(<?= json_encode($asset) ?>)'><i class="fa fa-edit"></i></button>
                            <button class="btn-action text-danger" onclick="confirmDel(<?= $asset['asset_id'] ?>, '<?= $asset['asset_code'] ?>')"><i class="fa fa-trash"></i></button>
                        </td>
                    </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="modal fade" id="editModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content p-3">
            <div class="modal-header border-0"><h5 class="fw-bold">Update Asset Info</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <form method="post">
                <div class="modal-body">
                    <input type="hidden" name="edit_asset" value="1">
                    <input type="hidden" id="edit_id" name="asset_id">
                    <input type="hidden" name="item_id_return" value="<?= $item_id_filter ?>">
                    <div class="mb-3"><label class="small fw-bold mb-1">Asset Code</label><input type="text" id="edit_code" class="form-control bg-light" readonly></div>
                    <div class="row g-2 mb-3">
                        <div class="col-6"><label class="small fw-bold mb-1">Brand</label><input type="text" id="edit_brand" name="brand" class="form-control"></div>
                        <div class="col-6"><label class="small fw-bold mb-1">Model</label><input type="text" id="edit_model" name="model" class="form-control"></div>
                    </div>
                    <div><label class="small fw-bold mb-1">Status</label>
                        <select name="status" id="edit_status" class="form-select">
                            <?php foreach($status_data as $key => $val): ?><option value="<?= $key ?>"><?= $key ?></option><?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="modal-footer border-0 mt-3"><button type="submit" class="btn btn-primary w-100 py-3 rounded-3 shadow">Save Changes</button></div>
            </form>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
    // Aktifkan Tooltip Bootstrap
    document.addEventListener('DOMContentLoaded', function () {
        var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'))
        tooltipTriggerList.map(function (t) { return new bootstrap.Tooltip(t) })
    });

    function openEditModal(asset) {
        document.getElementById('edit_id').value = asset.asset_id;
        document.getElementById('edit_code').value = asset.asset_code;
        document.getElementById('edit_brand').value = asset.brand;
        document.getElementById('edit_model').value = asset.model;
        document.getElementById('edit_status').value = asset.status;
        new bootstrap.Modal(document.getElementById('editModal')).show();
    }

    function confirmDel(id, code) {
        if(confirm("Padam aset " + code + "?")) {
            window.location.href = 'assets_technician.php?delete_asset_id=' + id + '&item_id_return=<?= $item_id_filter ?>';
        }
    }
</script>
</body>
</html>