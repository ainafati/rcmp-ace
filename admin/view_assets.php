<?php
session_start();
include 'config.php';

// --- Configuration & Setup ---
// Pastikan senarai ini merangkumi SEMUA status yang mungkin wujud dalam DB.
$all_possible_statuses = ['Available', 'Borrowed', 'Checked Out', 'Maintenance', 'Damaged', 'Retired'];

// --- Authentication ---
if (!isset($_SESSION['admin_id'])) {
    header("Location: login.php");
    exit();
}

// Dapatkan item_id dari URL. Jika tiada, redirect balik.
$item_id_filter = isset($_GET['item_id']) ? (int)$_GET['item_id'] : 0;
if ($item_id_filter === 0) {
    // Kita gunakan 'manageItem_admin.php' untuk Admin, bukan manageItems_tech.php
    header("Location: manageItem_admin.php"); 
    exit();
}

// --- HANDLE POST/GET ACTIONS (Edit & Delete) ---

// 1. HANDLE EDIT ASSET (POST)
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['edit_asset'])) {
    $asset_id = (int)$_POST['asset_id'];
    $brand = trim($_POST['brand']);
    $model = trim($_POST['model']);
    $status = trim($_POST['status']);
    $item_id_return = (int)$_POST['item_id_return']; // Untuk redirect

    // Pastikan status sah sebelum update
    if ($asset_id > 0 && in_array($status, $all_possible_statuses)) {
        $stmt_update = $conn->prepare("UPDATE assets SET brand = ?, model = ?, status = ? WHERE asset_id = ?");
        $stmt_update->bind_param("sssi", $brand, $model, $status, $asset_id);
        
        if ($stmt_update->execute()) {
            $_SESSION['message'] = ['type' => 'success', 'text' => 'Asset Unit successfully updated!'];
        } else {
            $_SESSION['message'] = ['type' => 'error', 'text' => 'Error updating asset: ' . $conn->error];
        }
        $stmt_update->close();
    } else {
        $_SESSION['message'] = ['type' => 'warning', 'text' => 'Invalid data or status provided for update.'];
    }

    header("Location: view_assets.php?item_id=" . $item_id_return);
    exit();
}

// 2. HANDLE DELETE ASSET (GET)
if (isset($_GET['delete_asset_id'])) {
    $delete_asset_id = (int)$_GET['delete_asset_id'];
    $item_id_return = (int)$_GET['item_id_return']; // Untuk redirect

    if ($delete_asset_id > 0) {
        // Amaran: Jika aset ini mempunyai rekod dalam jadual lain (cth: borrowing/reservation),
        // anda perlu mengendalikan Foreign Key Constraints (ON DELETE CASCADE atau set NULL).
        // Untuk memudahkan, kita andaikan ON DELETE CASCADE telah diset dalam DB.
        
        $stmt_delete = $conn->prepare("DELETE FROM assets WHERE asset_id = ?");
        $stmt_delete->bind_param("i", $delete_asset_id);
        
        if ($stmt_delete->execute()) {
            $_SESSION['message'] = ['type' => 'success', 'text' => 'Asset Unit deleted successfully.'];
        } else {
            // Mesej ralat ini mungkin menunjukkan Foreign Key Constraint yang belum dikendalikan.
            $_SESSION['message'] = ['type' => 'error', 'text' => 'Error deleting asset unit. Pastikan tiada rekod pinjaman aktif.'];
        }
        $stmt_delete->close();
    } else {
        $_SESSION['message'] = ['type' => 'error', 'text' => 'Invalid asset ID for deletion.'];
    }

    header("Location: view_assets.php?item_id=" . $item_id_return);
    exit();
}


// --- Data Fetching (Item Type & Assets) ---

$status_filter = isset($_GET['status']) ? trim($_GET['status']) : '';
$where_clauses = ["i.item_id = ?"];
$param_types = "i";
$param_values = [$item_id_filter];

// Ambil Nama Item Type dan Description
$stmt_item = $conn->prepare("SELECT item_name, description FROM item WHERE item_id = ?");
$stmt_item->bind_param("i", $item_id_filter);
$stmt_item->execute();
$stmt_item->bind_result($item_name_title, $description);
if (!$stmt_item->fetch()) {
    header("Location: manageItem_admin.php"); exit();
}
$stmt_item->close();

// Tambah filter Status jika ada
if (!empty($status_filter) && $status_filter != 'All') {
    $where_clauses[] = "a.status = ?";
    $param_types .= "s";
    $param_values[] = $status_filter;
}

// QUERY SQL DIPERBAIKI
// Menggunakan LEFT JOIN untuk mencari peminjam AKTIF bagi aset yang berstatus 'Checked Out'
$sql_assets = "
    SELECT 
        a.asset_id, a.asset_code, a.status, a.brand, a.model, i.item_name,
        CASE 
            WHEN a.status IN ('Checked Out') THEN u.name 
            ELSE NULL 
        END AS borrower_name
    FROM assets a
    JOIN item i ON a.item_id = i.item_id
    -- LEFT JOIN ke reservation_assets (ra) dan reservation_items (ri)
    LEFT JOIN reservation_assets ra ON a.asset_id = ra.asset_id
    LEFT JOIN reservation_items ri ON ra.reservation_item_id = ri.id AND ri.status = 'Checked Out'
    -- LEFT JOIN ke reservations (r) dan users (u)
    LEFT JOIN reservations r ON ri.reserve_id = r.reserve_id
    LEFT JOIN user u ON r.user_id = u.user_id -- Asumsi jadual pengguna anda adalah 'users'
    WHERE " . implode(' AND ', $where_clauses) . "
    ORDER BY a.asset_code ASC
";

$stmt_assets = $conn->prepare($sql_assets);
$stmt_assets->bind_param($param_types, ...$param_values);
$stmt_assets->execute();
$all_assets = $stmt_assets->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt_assets->close();

// Kita gunakan $all_possible_statuses di sini untuk Filter/Modal
$available_statuses = $all_possible_statuses; 
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Manage Assets — <?= htmlspecialchars($item_name_title) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <style>
        /* CSS LENGKAP & SERAGAM KEKAL SAMA */
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
        .borrower-icon { color: #1e293b; margin-right: 5px; }
    </style>
</head>
<body>

<div class="sidebar">
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
        <h3>Asset Unit Details</h3>
        <a href="manageItem_admin.php" class="btn btn-outline-secondary btn-sm">
            <i class="fa fa-arrow-left me-2"></i> Back to Item Summary
        </a>
    </div>

    <div class="container-fluid">
        <div class="card">
            <h5><i class="fa-solid fa-list me-2 text-primary"></i> <?= htmlspecialchars($item_name_title) ?></h5>
            <p class="text-muted"><?= htmlspecialchars($description) ?></p>
            <hr>
            
            <form method="GET" class="row g-3 align-items-center mb-4">
                <input type="hidden" name="item_id" value="<?= $item_id_filter ?>">
                <div class="col-md-4">
                    <label for="status_filter" class="form-label small text-uppercase fw-bold">Filter by Status</label>
                    <select name="status" id="status_filter" class="form-select">
                        <option value="All">Show All (<?= count($all_assets) ?> units)</option>
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
                            <tr><td colspan="6" class="text-center text-muted py-5"><i class="fa-solid fa-box-open fa-2x mb-2"></i><br>No asset units found for this item type or filter.</td></tr>
                        <?php else: foreach($all_assets as $asset):
                            $status = strtolower(str_replace(' ', '_', $asset['status']));
                            $badge_class = 'text-bg-light';
                            if ($status == 'available') $badge_class = 'text-bg-success';
                            if (in_array($status, ['borrowed', 'checked_out'])) $badge_class = 'text-bg-warning';
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
                                    <i class="fa fa-user borrower-icon"></i> <?= htmlspecialchars($asset['borrower_name']) ?>
                                <?php else: ?>
                                    -
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
                    
                    <div class="mb-3"><label class="form-label fw-bold">Brand</label><input type="text" id="edit_brand" name="brand" class="form-control"></div>
                    <div class="mb-3"><label class="form-label fw-bold">Model</label><input type="text" id="edit_model" name="model" class="form-control"></div>
                    <div class="mb-3">
                        <label class="form-label fw-bold">Status</label>
                        <select id="edit_status" name="status" class="form-select" required>
                            <?php foreach($all_possible_statuses as $s): // Guna all_possible_statuses di modal ?>
                                <option value="<?= $s ?>"><?= htmlspecialchars($s) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <button type="submit" class="btn btn-warning w-100 text-dark fw-bold mt-3">Update Asset Unit</button>
                </form>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
    // --- SWEETALERT2 MESSAGE DISPLAY (DAPATKAN DARI SESSION) ---
    <?php
    if (isset($_SESSION['message'])) {
        $message = $_SESSION['message'];
        unset($_SESSION['message']);
        echo "Swal.fire({
            icon: '{$message['type']}',
            title: '{$message['text']}',
            toast: true,
            position: 'top-end',
            showConfirmButton: false,
            timer: 3500,
            timerProgressBar: true
        });";
    }
    ?>

    // --- FUNGSI JAVASCRIPT ---

    function openEditAssetModal(asset) {
        document.getElementById('edit_asset_id').value = asset.asset_id;
        document.getElementById('asset_code_display').textContent = asset.asset_code;
        document.getElementById('edit_brand').value = asset.brand;
        document.getElementById('edit_model').value = asset.model;
        // Pastikan status di modal juga diset betul
        const statusSelect = document.getElementById('edit_status');
        for (let i = 0; i < statusSelect.options.length; i++) {
            if (statusSelect.options[i].value === asset.status) {
                statusSelect.selectedIndex = i;
                break;
            }
        }
        new bootstrap.Modal(document.getElementById('editAssetModal')).show();
    }

    function deleteAsset(id, code, item_id) {
        Swal.fire({
            title: `Delete Unit ${code}?`,
            text: "This unit will be permanently removed from inventory. This action cannot be undone!",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            confirmButtonText: 'Yes, Delete Unit!'
        }).then((result) => {
            if (result.isConfirmed) {
                window.location.href = 'view_assets.php?delete_asset_id=' + id + '&item_id_return=' + item_id;
            }
        });
    }
</script>
</body>
</html>