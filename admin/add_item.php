<?php
session_start();
include '../config.php'; 

if (!$conn) {
    die("Connection failed: " . mysqli_connect_error());
}

// Kira Badge Pending
$pending_count_for_badge = 0;
$query_pending = "SELECT COUNT(*) as total FROM reservation_items WHERE status = 'Pending'";
$result_pending = mysqli_query($conn, $query_pending);
if ($result_pending) {
    $row_p = mysqli_fetch_assoc($result_pending);
    $pending_count_for_badge = (int)$row_p['total'];
}

// Ambil Kategori
$categories = [];
$cat_query = "SELECT * FROM categories ORDER BY category_name ASC"; 
$cat_result = mysqli_query($conn, $cat_query);
if ($cat_result) {
    while ($row = mysqli_fetch_assoc($cat_result)) {
        $categories[] = $row;
    }
}

// Logik Simpan
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_item_type_and_units'])) {
    $item_name = ucwords(strtolower(mysqli_real_escape_string($conn, $_POST['item_name'])));
    $cat_id = mysqli_real_escape_string($conn, $_POST['category_id']);
    $brand = mysqli_real_escape_string($conn, $_POST['batch_brand']);
    $model = mysqli_real_escape_string($conn, $_POST['batch_model']);
    $qty = (int)$_POST['quantity'];
    $manual_serials = $_POST['manual_serials'] ?? [];

    $image_path_db = ""; 
    if(isset($_FILES['item_image']) && $_FILES['item_image']['error'] == 0){
        $target_dir = "../assets/item_images/"; 
        if (!file_exists($target_dir)) mkdir($target_dir, 0777, true);
        $filename = time() . "_" . basename($_FILES["item_image"]["name"]);
        if(move_uploaded_file($_FILES["item_image"]["tmp_name"], $target_dir . $filename)){
            $image_path_db = "assets/item_images/" . $filename;
        }
    }

    $sql_type = "INSERT INTO item (category_id, item_name, image_url) VALUES (?, ?, ?)";
    $stmt = $conn->prepare($sql_type);
    $stmt->bind_param("iss", $cat_id, $item_name, $image_path_db);
    
    if ($stmt->execute()) {
        $last_item_id = $conn->insert_id;
        if (isset($_POST['enable_manual_code']) && isset($_POST['manual_codes'])) {
            foreach ($_POST['manual_codes'] as $index => $code) {
                if (!empty(trim($code))) {
                    $code = mysqli_real_escape_string($conn, $code);
                    $serial = isset($manual_serials[$index]) ? mysqli_real_escape_string($conn, $manual_serials[$index]) : '';
                    $stmt_asset = $conn->prepare("INSERT INTO assets (item_id, brand, model, asset_code, serial_number, status) VALUES (?, ?, ?, ?, ?, 'Available')");
                    $stmt_asset->bind_param("issss", $last_item_id, $brand, $model, $code, $serial);
                    $stmt_asset->execute();
                }
            }
        } else {
            $current_year = date('Y'); 
            for ($i = 1; $i <= $qty; $i++) {
                $auto_code = $current_year . str_pad($i, 3, '0', STR_PAD_LEFT);
                $stmt_asset = $conn->prepare("INSERT INTO assets (item_id, brand, model, asset_code, serial_number, status) VALUES (?, ?, ?, ?, ?, 'Available')");
                $stmt_asset->bind_param("issss", $last_item_id, $brand, $model, $auto_code, $auto_code);
                $stmt_asset->execute();
            }
        }
        header("Location: manageItem_admin.php?success=1");
        exit();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register Item | UniKL Technician</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../css/style.css">
    
    <style>
        :root {
            --primary-cyan: #06b6d4;
            --sidebar-width: 260px;
        }

        /* Responsive Logic */
        @media (max-width: 991px) {
            .sidebar { display: none !important; }
            .sidebar-overlay { display: none !important; }
            .main-content { 
                margin-left: 0 !important; 
                padding: 15px !important;
                padding-bottom: 90px !important; 
            }
            .mobile-bottom-nav { display: flex !important; }
            .topbar #sidebarToggle { display: none !important; } /* Sembunyi sebab dah ada Bottom Nav */
        }

        @media (min-width: 992px) {
            .mobile-bottom-nav { display: none !important; }
            .main-content { margin-left: var(--sidebar-width); }
        }

        /* Mobile Bottom Nav Styles */
        .mobile-bottom-nav {
            position: fixed;
            bottom: 0; left: 0; width: 100%;
            background: #fff;
            border-top: 1px solid #e2e8f0;
            z-index: 9999;
            justify-content: space-around;
            padding: 10px 0;
            box-shadow: 0 -5px 15px rgba(0,0,0,0.05);
            display: none;
        }
        .mobile-bottom-nav a {
            color: #94a3b8;
            text-decoration: none;
            font-size: 10px;
            font-weight: 700;
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 4px;
            flex: 1;
        }
        .mobile-bottom-nav a.active { color: var(--primary-cyan); }
        .mobile-bottom-nav i { font-size: 20px; }

        /* Form Styling */
        .upload-zone {
            border: 2px dashed #cbd5e1;
            border-radius: 15px;
            padding: 30px;
            text-align: center;
            cursor: pointer;
            transition: 0.3s;
            background: #f8fafc;
        }
        .upload-zone:hover { border-color: var(--primary-cyan); background: #f0f9ff; }
        #imagePreview { 
            max-width: 100%; 
            max-height: 200px; 
            border-radius: 10px; 
            display: none; 
            margin-top: 10px;
        }
        .card { border: none; border-radius: 15px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05); margin-bottom: 1.5rem; }
        .btn-register { background: var(--primary-cyan); color: white; font-weight: 600; border-radius: 10px; padding: 12px 25px; border: none; }
        .btn-register:hover { background: #0891b2; color: white; }
    </style>
</head>
<body>
<div class="sidebar-overlay" id="sidebarOverlay"></div> 
<div class="sidebar" id="admin-sidebar">
    <div> <div class="sidebar-header">
    <div class="logo-icon"><i class="fa-solid fa-wrench"></i></div>
    <div class="logo-text">
        <strong>UniKL Admin</strong>
        <span class="d-block">System Control</span> </div>
</div>
        
        <div class="sidebar-nav"> 
<a href="manageItem_admin.php"class="active" ><i class="fa-solid fa-box-archive"></i> Manage Items</a>
        <a href="manage_accounts.php" ><i class="fa-solid fa-users-cog"></i> Manage Accounts</a>
        <a href="report_admin.php" ><i class="fa-solid fa-chart-pie"></i> System Report</a>        </div>
    </div>
    
    <div class="sidebar-footer">
        <a href="logout.php" class="logout-link"><i class="fa-solid fa-sign-out-alt"></i> Logout</a> 
    </div>
</div>

<div class="main-content">
    <div class="topbar d-flex justify-content-between align-items-center p-3 mb-4">
        <h3 class="m-0 fw-bold">Register New Item</h3>
        <a href="manageItem_admin.php" class="btn btn-sm btn-outline-secondary rounded-pill px-3">
            <i class="fa-solid fa-arrow-left me-1"></i> Back
        </a>
    </div>

    <div class="container-fluid">
        <form method="post" enctype="multipart/form-data">
            <input type="hidden" name="add_item_type_and_units" value="1">

            <div class="card">
                <div class="card-body p-4">
                    <h5 class="fw-bold mb-4 text-dark"><i class="fa-solid fa-info-circle text-info me-2"></i>General Information</h5>
                    <div class="row g-3">
                        <div class="col-md-7">
                            <label class="form-label fw-semibold small text-muted">ITEM NAME</label>
                            <input type="text" name="item_name" class="form-control" placeholder="e.g. Projector Epson EB-X06" required>
                        </div>
                        <div class="col-md-5">
                            <label class="form-label fw-semibold small text-muted">CATEGORY</label>
                            <select name="category_id" class="form-select" required>
                                <option value="" selected disabled>Select Category</option>
                                <?php foreach($categories as $cat): ?>
                                    <option value="<?= $cat['category_id'] ?>"><?= htmlspecialchars($cat['category_name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-12 mt-4">
                            <label class="form-label fw-semibold small text-muted">ITEM IMAGE</label>
                            <div class="upload-zone" onclick="document.getElementById('fileInput').click()">
                                <div id="uploadContent">
                                    <i class="fa-solid fa-image fa-3x text-muted mb-2"></i>
                                    <p class="mb-0 fw-bold">Click to upload product image</p>
                                </div>
                                <img id="imagePreview">
                                <input type="file" name="item_image" id="fileInput" class="d-none" accept="image/*" onchange="previewImage(this)">
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card-body p-4">
                    <h5 class="fw-bold mb-4 text-dark"><i class="fa-solid fa-boxes-stacked text-info me-2"></i>Asset Management</h5>
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label fw-semibold small text-muted">QUANTITY</label>
                            <input type="number" name="quantity" id="input_quantity" class="form-control" min="1" value="1" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold small text-muted">BRAND</label>
                            <input type="text" name="batch_brand" class="form-control" placeholder="Epson">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold small text-muted">MODEL</label>
                            <input type="text" name="batch_model" class="form-control" placeholder="EB-X06">
                        </div>
                    </div>

                    <div class="mt-4 p-3 border rounded-3 bg-light d-flex justify-content-between align-items-center">
                        <div>
                            <label class="fw-bold mb-0">Manual Asset Coding</label>
                            <p class="text-muted small mb-0">Input custom Asset Code & Serial Number for each unit.</p>
                        </div>
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" id="enable_manual_code" name="enable_manual_code" style="width: 40px; height: 20px;">
                        </div>
                    </div>

                    <div id="manual_assets_container" class="mt-4" style="display:none;">
                        <div class="row mb-2 text-muted small fw-bold">
                            <div class="col-6">ASSET CODE</div>
                            <div class="col-6">SERIAL NUMBER</div>
                        </div>
                        <div id="dynamic_asset_inputs" class="row g-2"></div>
                    </div>
                </div>
            </div>

            <div class="d-flex justify-content-end gap-2 mb-5">
                <button type="reset" class="btn btn-light px-4" onclick="location.reload()">Reset</button>
                <button type="submit" class="btn btn-register">
                    <i class="fa-solid fa-save me-2"></i>Register Assets
                </button>
            </div>
        </form>
    </div>
</div>

<script>
    function previewImage(input) {
        if (input.files && input.files[0]) {
            const reader = new FileReader();
            reader.onload = function(e) {
                document.getElementById('imagePreview').src = e.target.result;
                document.getElementById('imagePreview').style.display = 'inline-block';
                document.getElementById('uploadContent').style.display = 'none';
            }
            reader.readAsDataURL(input.files[0]);
        }
    }

    const qtyInput = document.getElementById('input_quantity');
    const checkbox = document.getElementById('enable_manual_code');
    const container = document.getElementById('manual_assets_container');
    const dynamicInputs = document.getElementById('dynamic_asset_inputs');

    function updateAssetInputs() {
        dynamicInputs.innerHTML = ''; 
        if (checkbox.checked) {
            container.style.display = 'block';
            let qty = Math.min(parseInt(qtyInput.value) || 0, 50);
            for (let i = 1; i <= qty; i++) {
                dynamicInputs.innerHTML += `
                    <div class="col-md-6"><input type="text" name="manual_codes[]" class="form-control mb-2" placeholder="Code #${i}" required></div>
                    <div class="col-md-6"><input type="text" name="manual_serials[]" class="form-control mb-2" placeholder="Serial #${i}"></div>`;
            }
        } else {
            container.style.display = 'none';
        }
    }

    checkbox.addEventListener('change', updateAssetInputs);
    qtyInput.addEventListener('input', updateAssetInputs);
</script>

</body>
</html>