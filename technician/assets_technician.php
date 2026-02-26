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
    $serial_number = trim($_POST['serial_number']);
    $status = trim($_POST['status']);
    
    $stmt = $conn->prepare("UPDATE assets SET brand = ?, model = ?, serial_number = ?, status = ? WHERE asset_id = ?");
    $stmt->bind_param("ssssi", $brand, $model, $serial_number, $status, $asset_id);
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

// Ambil maklumat item
$stmt_item = $conn->prepare("SELECT item_name FROM item WHERE item_id = ?");
$stmt_item->bind_param("i", $item_id_filter);
$stmt_item->execute();
$stmt_item->bind_result($item_name_title);
if (!$stmt_item->fetch()) {
    header("Location: manageItem_tech.php"); exit(); 
}
$stmt_item->close();

// Query Assets
$sql_assets = "
    SELECT 
        a.asset_id, a.asset_code, a.status, a.brand, a.model, a.serial_number, i.item_name,
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
    WHERE i.item_id = ?
    GROUP BY a.asset_id, a.asset_code, a.status, a.brand, a.model, a.serial_number, i.item_name
    ORDER BY a.asset_code ASC
";

$stmt_assets = $conn->prepare($sql_assets);
$stmt_assets->bind_param("i", $item_id_filter);
$stmt_assets->execute();
$all_assets = $stmt_assets->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt_assets->close();

// Config Status
$status_data = [
    'Available'   => ['color' => '#10b981', 'bg' => '#ecfdf5', 'label' => 'Available'],
    'Checked Out' => ['color' => '#f59e0b', 'bg' => '#fffbeb', 'label' => 'Checked Out'],
    'Maintenance' => ['color' => '#3b82f6', 'bg' => '#eff6ff', 'label' => 'Maintenance'],
    'Damaged'     => ['color' => '#ef4444', 'bg' => '#fef2f2', 'label' => 'Damaged']
];

$count_available = 0;
$count_on_loan = 0;
foreach ($all_assets as $asset) {
    if ($asset['status'] === 'Available') $count_available++;
    if ($asset['status'] === 'Checked Out') $count_on_loan++;
}

// Badge Sidebar Count
$query_pending = "SELECT COUNT(*) as total FROM reservation_items WHERE status = 'Pending'";
$result_pending = $conn->query($query_pending);
$pending_count_for_badge = ($result_pending) ? $result_pending->fetch_assoc()['total'] : 0;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Asset Details | UniKL</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">

<style>
    :root {
        --primary-color: #06b6d4;
        --dark-sidebar: #1a2235;
        --bg-body: #f8fafc;
        --danger-red: #ff4d4d;
    }

    body { 
        font-family: 'Inter', sans-serif; 
        background-color: var(--bg-body); 
        color: #1e293b; 
        overflow-x: hidden;
    }

    /* --- SIDEBAR --- */
    .sidebar { 
        width: 260px; position: fixed; top: 15px; bottom: 15px; left: 15px; 
        background: var(--dark-sidebar) !important; padding: 25px 0; border-radius: 20px; 
        display: flex; flex-direction: column; z-index: 1050; transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        box-shadow: 0 10px 30px rgba(0,0,0,0.1);
    }
    .sidebar-header { padding: 0 25px; display: flex; align-items: center; gap: 12px; margin-bottom: 40px; }
    .logo-icon { width: 42px; height: 42px; background: var(--primary-color); color: white; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 1.2rem; }
    .logo-text strong { display: block; font-size: 1.1rem; color: #ffffff !important; }
    .logo-text span { font-size: 0.75rem; color: #94a3b8 !important; }
    .sidebar nav { padding: 0 15px; flex-grow: 1; }
    .sidebar a { display: flex; align-items: center; gap: 12px; color: #94a3b8; text-decoration: none; padding: 12px 18px; border-radius: 12px; font-weight: 500; margin-bottom: 8px; transition: 0.3s; }
    .sidebar a:hover { background: rgba(255,255,255,0.05); color: #ffffff; }
    .sidebar a.active { background: var(--primary-color) !important; color: #ffffff !important; box-shadow: 0 4px 15px rgba(6, 182, 212, 0.3); }
    .sidebar a .badge { margin-left: auto; background-color: var(--danger-red) !important; color: white !important; font-size: 0.75rem; padding: 2px 8px; border-radius: 10px; font-weight: 700; }

    /* --- SEARCH BAR --- */
    .search-container {
        position: relative;
        max-width: 400px;
    }
    .search-container i {
        position: absolute;
        left: 15px;
        top: 50%;
        transform: translateY(-50%);
        color: #94a3b8;
    }
    .search-input {
        padding: 10px 15px 10px 40px;
        border-radius: 12px;
        border: 1px solid #e2e8f0;
        width: 100%;
        transition: 0.3s;
        font-size: 0.9rem;
    }
    .search-input:focus {
        outline: none;
        border-color: var(--primary-color);
        box-shadow: 0 0 0 4px rgba(6, 182, 212, 0.1);
    }

    /* --- STATUS DOT --- */
    .status-indicator { 
        width: 14px; height: 14px; 
        min-width: 14px; /* Kunci bulat kat mobile */
        border-radius: 50%; display: inline-block; position: relative; 
    }
    .status-indicator::after {
        content: ''; position: absolute; top: 0; left: 0; width: 100%; height: 100%;
        border-radius: 50%; background: inherit; opacity: 0.5; animation: pulse-ring 1.5s infinite;
    }
    @keyframes pulse-ring {
        0% { transform: scale(1); opacity: 0.5; }
        100% { transform: scale(2.5); opacity: 0; }
    }

    /* --- CONTENT AREA --- */
    .main-content { margin-left: 290px; padding: 30px 40px; min-height: 100vh; }
    .card-main { background: #ffffff; border-radius: 24px; padding: 30px; box-shadow: 0 10px 30px rgba(0,0,0,0.02); }
    .asset-code-pill { background: #f1f5f9; padding: 6px 12px; border-radius: 8px; font-weight: 700; font-family: 'JetBrains Mono', monospace; font-size: 0.85rem; }

    .stat-card {
        background: #f8fafc; padding: 25px; border-radius: 24px;
        display: flex; align-items: center; gap: 20px;
        box-shadow: 8px 8px 16px #d1d9e6, -8px -8px 16px #ffffff;
    }

    @media (max-width: 991px) {
        .sidebar { display: none; }
        .main-content { margin-left: 0; padding: 20px; }
        .search-container { max-width: 100%; margin-bottom: 20px; }
        .table thead { display: none; }
        .table tbody tr { display: block; background: #fff; border: 1px solid #e2e8f0; border-radius: 20px; margin-bottom: 20px; padding: 20px; box-shadow: 6px 6px 12px #d1d9e6; }
        .table td { display: flex; justify-content: space-between; align-items: flex-start; border: none !important; padding: 12px 0 !important; }
        .table td::before { content: attr(data-label); font-weight: 700; color: #94a3b8; font-size: 0.7rem; text-transform: uppercase; min-width: 110px; }
        .table td > div { text-align: right; flex-grow: 1; }
    }
</style>
</head>
<body>

<div class="sidebar">
    <div class="sidebar-header">
        <div class="logo-icon"><i class="fa-solid fa-wrench"></i></div>
        <div class="logo-text"><strong>UniKL Technician</strong><span>System Support</span></div>
    </div>
    <nav>
        <a href="dashboard_tech.php"><i class="fa-solid fa-table-columns"></i> Dashboard</a>
        <a href="check_out.php">
            <i class="fa-solid fa-dolly"></i> Manage Requests
            <?php if ($pending_count_for_badge > 0): ?>
                <span class="badge"><?= $pending_count_for_badge ?></span>
            <?php endif; ?>
        </a>
        <a href="manageItem_tech.php" class="active"><i class="fa-solid fa-box-archive"></i> Manage Items</a>
        <a href="report.php"><i class="fa-solid fa-chart-line"></i> Report</a>
    </nav>
    <a href="logout.php" class="logout-link"><i class="fa-solid fa-right-from-bracket"></i> Logout</a>
</div>

<div class="main-content">
    <div class="d-flex justify-content-between align-items-end mb-4">
        <div>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-1 small">
                    <li class="breadcrumb-item"><a href="manageItem_tech.php" class="text-decoration-none text-muted">Items</a></li>
                    <li class="breadcrumb-item active">Details</li>
                </ol>
            </nav>
            <h1 class="fw-bold h2 mb-0"><?= htmlspecialchars($item_name_title) ?></h1>
        </div>
        <a href="manageItem_tech.php" class="btn btn-white border shadow-sm px-4 rounded-4 bg-white">Back</a>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-6 col-md-4"><div class="stat-card">
            <div class="bg-info bg-opacity-10 p-3 rounded-3 text-info"><i class="fa-solid fa-boxes-stacked"></i></div>
            <div><div class="small text-muted">Total</div><div class="fw-bold h4 mb-0"><?= count($all_assets) ?></div></div>
        </div></div>
        <div class="col-6 col-md-4"><div class="stat-card" style="border-bottom: 4px solid #10b981;">
            <div class="bg-success bg-opacity-10 p-3 rounded-3 text-success"><i class="fa-solid fa-check-double"></i></div>
            <div><div class="small text-muted">Ready</div><div class="fw-bold h4 mb-0 text-success"><?= $count_available ?></div></div>
        </div></div>
        <div class="col-12 col-md-4"><div class="stat-card" style="border-bottom: 4px solid #f59e0b;">
            <div class="bg-warning bg-opacity-10 p-3 rounded-3 text-warning"><i class="fa-solid fa-handshake"></i></div>
            <div><div class="small text-muted">On Loan</div><div class="fw-bold h4 mb-0 text-warning"><?= $count_on_loan ?></div></div>
        </div></div>
    </div>

    <div class="card-main">
        <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap">
            <h5 class="fw-bold mb-0">List of Assets</h5>
            <div class="search-container mt-2 mt-md-0">
                <i class="fa-solid fa-magnifying-glass"></i>
                <input type="text" id="assetSearch" class="search-input" placeholder="Search Asset Code...">
            </div>
        </div>

        <div class="table-responsive">
            <table class="table table-borderless align-middle" id="assetTable">
                <thead>
                    <tr class="text-muted small">
                        <th class="ps-3">ASSET CODE</th>
                        <th>INFO UNIT</th>
                        <th class="text-center">STATUS</th>
                        <th>BORROWER</th>
                        <th class="text-end">ACTIONS</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($all_assets as $asset): 
                        $info = $status_data[$asset['status']] ?? ['color' => '#64748b', 'label' => $asset['status']];
                    ?>
                    <tr class="asset-row">
                        <td data-label="Asset Code" class="ps-md-3">
                            <span class="asset-code-pill"><?= htmlspecialchars($asset['asset_code']) ?></span>
                        </td>
                        <td data-label="Info Unit">
                            <div>
                                <div class="fw-bold text-dark"><?= htmlspecialchars($asset['brand'] ?: '-') ?></div>
                                <div class="text-muted small"><?= htmlspecialchars($asset['model'] ?: '-') ?></div>
                                <div class="text-primary fw-bold" style="font-size: 0.75rem;">S/N: <?= htmlspecialchars($asset['serial_number'] ?: '-') ?></div>
                            </div>
                        </td>
                        <td data-label="Status" class="text-center">
                            <div>
                                <div class="status-indicator" 
                                     style="background: <?= $info['color'] ?>;" 
                                     data-bs-toggle="tooltip" 
                                     title="<?= $info['label'] ?>">
                                </div>
                            </div>
                        </td>
                        <td data-label="Borrower"><span class="small"><?= htmlspecialchars($asset['borrower_name'] ?: '--') ?></span></td>
                        <td data-label="Actions" class="text-end">
                            <button class="btn btn-sm btn-light text-warning" onclick='openEditModal(<?= json_encode($asset) ?>)'><i class="fa fa-edit"></i></button>
                            <button class="btn btn-sm btn-light text-danger" onclick="confirmDel(<?= $asset['asset_id'] ?>, '<?= $asset['asset_code'] ?>')"><i class="fa fa-trash"></i></button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <div id="noData" class="text-center py-4 text-muted" style="display: none;">
                <i class="fa-solid fa-ghost fa-2x mb-2"></i>
                <p>No asset code matches your search.</p>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="editModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content rounded-4 border-0 p-3 shadow">
            <div class="modal-header border-0"><h5 class="fw-bold">Update Asset</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <form method="post">
                <div class="modal-body">
                    <input type="hidden" name="edit_asset" value="1"><input type="hidden" id="edit_id" name="asset_id"><input type="hidden" name="item_id_return" value="<?= $item_id_filter ?>">
                    <div class="mb-3"><label class="small fw-bold mb-1">Brand</label><input type="text" id="edit_brand" name="brand" class="form-control rounded-3"></div>
                    <div class="mb-3"><label class="small fw-bold mb-1">Model</label><input type="text" id="edit_model" name="model" class="form-control rounded-3"></div>
                    <div class="mb-3"><label class="small fw-bold mb-1">Serial Number</label><input type="text" id="edit_serial" name="serial_number" class="form-control rounded-3"></div>
                    <div class="mb-3"><label class="small fw-bold mb-1">Status</label>
                        <select name="status" id="edit_status" class="form-select rounded-3">
                            <?php foreach($status_data as $key => $val): ?><option value="<?= $key ?>"><?= $key ?></option><?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="modal-footer border-0"><button type="submit" class="btn btn-primary w-100 py-2 rounded-3 fw-bold">Save Changes</button></div>
            </form>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
    // Logic Search Asset Code (Fast JS)
    document.getElementById('assetSearch').addEventListener('keyup', function() {
        let filter = this.value.toUpperCase();
        let rows = document.querySelectorAll('.asset-row');
        let noData = document.getElementById('noData');
        let visibleCount = 0;

        rows.forEach(row => {
            let assetCode = row.querySelector('.asset-code-pill').innerText.toUpperCase();
            if (assetCode.indexOf(filter) > -1) {
                row.style.display = "";
                visibleCount++;
            } else {
                row.style.display = "none";
            }
        });

        noData.style.display = (visibleCount === 0) ? "block" : "none";
    });

    // Tooltip & Modal Functions
    document.addEventListener('DOMContentLoaded', function () {
        var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'))
        tooltipTriggerList.map(function (tooltipTriggerEl) {
            return new bootstrap.Tooltip(tooltipTriggerEl)
        })
    });

    function openEditModal(asset) {
        document.getElementById('edit_id').value = asset.asset_id;
        document.getElementById('edit_brand').value = asset.brand;
        document.getElementById('edit_model').value = asset.model;
        document.getElementById('edit_serial').value = asset.serial_number;
        document.getElementById('edit_status').value = asset.status;
        new bootstrap.Modal(document.getElementById('editModal')).show();
    }

    function confirmDel(id, code) {
        if(confirm("Delete " + code + "?")) { window.location.href = 'assets_technician.php?delete_asset_id=' + id + '&item_id_return=<?= $item_id_filter ?>'; }
    }
</script>
</body>
</html>