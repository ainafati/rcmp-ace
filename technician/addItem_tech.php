<?php
// 1. Panggil config
include '../config.php'; 

// 2. Check connection
if (!$conn) {
    die("Connection failed: " . mysqli_connect_error());
}

// 3. Ambil data kategori untuk dropdown
$categories = [];
$cat_query = "SELECT * FROM categories ORDER BY category_name ASC"; 
$cat_result = mysqli_query($conn, $cat_query);

if ($cat_result && mysqli_num_rows($cat_result) > 0) {
    while ($row = mysqli_fetch_assoc($cat_result)) {
        $categories[] = $row;
    }
}

// 4. Logik simpan data
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_item_type_and_units'])) {
    $item_name = ucwords(strtolower(mysqli_real_escape_string($conn, $_POST['item_name'])));
    $cat_id = mysqli_real_escape_string($conn, $_POST['category_id']);
    $brand = mysqli_real_escape_string($conn, $_POST['batch_brand']);
    $model = mysqli_real_escape_string($conn, $_POST['batch_model']);
    $qty = (int)$_POST['quantity'];
    
    $image_path_db = ""; 
    if(isset($_FILES['item_image']) && $_FILES['item_image']['error'] == 0){
        $target_dir = "../assets/item_images/"; 
        if (!file_exists($target_dir)) mkdir($target_dir, 0777, true);
        
        $filename = time() . "_" . basename($_FILES["item_image"]["name"]);
        $target_file = $target_dir . $filename;
        
        if(move_uploaded_file($_FILES["item_image"]["tmp_name"], $target_file)){
            $image_path_db = "assets/item_images/" . $filename;
        }
    }

    $sql_type = "INSERT INTO item (category_id, item_name, image_url) VALUES (?, ?, ?)";
    $stmt = $conn->prepare($sql_type);
    $stmt->bind_param("iss", $cat_id, $item_name, $image_path_db);
    
    if ($stmt->execute()) {
        $last_item_id = $conn->insert_id;

        if (isset($_POST['enable_manual_code']) && isset($_POST['manual_codes'])) {
            foreach ($_POST['manual_codes'] as $code) {
                if (!empty(trim($code))) {
                    $code = mysqli_real_escape_string($conn, $code);
                    $stmt_asset = $conn->prepare("INSERT INTO assets (item_id, brand, model, asset_code, status) VALUES (?, ?, ?, ?, 'Available')");
                    $stmt_asset->bind_param("isss", $last_item_id, $brand, $model, $code);
                    $stmt_asset->execute();
                }
            }
        } else {
            $current_year = date('Y'); 
            for ($i = 1; $i <= $qty; $i++) {
                $serial_number = str_pad($i, 3, '0', STR_PAD_LEFT);
                $auto_code = $current_year . $serial_number;
                $stmt_asset = $conn->prepare("INSERT INTO assets (item_id, brand, model, asset_code, status) VALUES (?, ?, ?, ?, 'Available')");
                $stmt_asset->bind_param("isss", $last_item_id, $brand, $model, $auto_code);
                $stmt_asset->execute();
            }
        }
        header("Location: manageItem_tech.php?success=1");
        exit();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register Item — UniKL</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        :root {
            --primary-color: #06b6d4; 
            --primary-hover: #0891b2; 
            --danger-color: #ef4444; 
            --bg-light-gray: #f8fafc;
            --card-bg: #ffffff;
            --text-dark: #1e293b; 
            --text-muted: #64748b; 
            --border-color: #e5e7eb;
        }

        body { font-family: 'Inter', sans-serif; background-color: var(--bg-light-gray); color: #334155; min-height: 100vh; margin: 0; }
        
        /* SIDEBAR */
        .sidebar { 
            width: 250px; position: fixed; top: 0; bottom: 0; left: 0; 
            background: var(--card-bg); padding: 20px; 
            border-right: 1px solid var(--border-color); z-index: 1050; 
            display: flex; flex-direction: column; transition: transform 0.3s ease-in-out;
        }
        .sidebar-header { display: flex; align-items: center; gap: 12px; margin-bottom: 30px; }
        .logo-icon { width: 40px; height: 40px; background-color: var(--primary-color); color: white; border-radius: 8px; display: flex; align-items: center; justify-content: center; font-size: 20px; }
        .logo-text strong { display: block; font-size: 16px; color: var(--text-dark); }
        .logo-text span { font-size: 12px; color: #94a3b8; }
        
        .sidebar a { display: flex; align-items: center; gap: 12px; color: var(--text-muted); text-decoration: none; padding: 12px 15px; margin-bottom: 8px; border-radius: 8px; font-weight: 500; transition: 0.2s; }
        .sidebar a.active, .sidebar a:hover { background: var(--primary-color); color: #fff; }
        .sidebar a.logout-link { color: var(--danger-color); margin-top: auto; font-weight: 600; }
        .sidebar a.logout-link:hover { background: var(--danger-color); color: #fff; }

        /* MAIN CONTENT */
        .main-content { margin-left: 250px; transition: margin 0.3s; }
        .topbar { background: var(--card-bg); padding: 15px 30px; display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid var(--border-color); margin-bottom: 30px; }

        .form-container { padding: 0 30px 40px 30px; max-width: 1000px; }
        .card { border: 1px solid var(--border-color); border-radius: 12px; box-shadow: 0 1px 3px rgba(0,0,0,0.05); background: white; margin-bottom: 25px; }
        .card-body { padding: 25px; }
        
        .upload-zone { 
            border: 2px dashed var(--border-color); border-radius: 12px; padding: 30px; 
            text-align: center; cursor: pointer; background: #f9fafb; transition: 0.2s;
        }
        .upload-zone:hover { border-color: var(--primary-color); background: #f0fdfa; }
        #imagePreview { max-width: 180px; border-radius: 8px; margin-top: 15px; display: none; }

        .btn-register { background-color: var(--primary-color); color: white; border: none; padding: 12px 25px; border-radius: 8px; font-weight: 600; transition: 0.2s; }
        .btn-register:hover { background-color: var(--primary-hover); transform: translateY(-1px); }

        @media (max-width: 992px) {
            .sidebar { transform: translateX(-100%); }
            .sidebar.active { transform: translateX(0); }
            .main-content { margin-left: 0; }
        }
    </style>
</head>
<body>

<div class="sidebar-overlay" id="sidebarOverlay"></div> 
<div class="sidebar" id="admin-sidebar">
    <div class="sidebar-header">
        <div class="logo-icon"><i class="fa-solid fa-wrench"></i></div>
        <div class="logo-text"><strong>UniKL Technician</strong><span>System Support</span></div>
    </div>
    <a href="dashboard_tech.php"><i class="fa-solid fa-table-columns"></i> Dashboard</a>
    <a href="check_out.php"><i class="fa-solid fa-dolly"></i> Manage Requests</a>
    <a href="manageItem_tech.php" class="active"><i class="fa-solid fa-box-archive"></i> Manage Items</a>
    <a href="report.php"><i class="fa-solid fa-chart-line"></i> Report</a>
    <a href="logout.php" class="logout-link"><i class="fa-solid fa-right-from-bracket"></i> Logout</a>
</div>

<div class="main-content">
    <div class="topbar">
        <div class="d-flex align-items-center">
            <i class="fa-solid fa-bars menu-toggle d-lg-none me-3" id="menuToggle"></i>
            <h3 class="m-0 fw-bold text-dark">Register New Item</h3>
        </div>
        <a href="manageItem_tech.php" class="btn btn-sm btn-outline-secondary rounded-pill px-3">
            <i class="fa-solid fa-arrow-left me-1"></i> Back to List
        </a>
    </div>

    <div class="form-container">
        <form method="post" enctype="multipart/form-data">
            <input type="hidden" name="add_item_type_and_units" value="1">

            <div class="card">
                <div class="card-body">
                    <h5 class="fw-bold mb-4"><i class="fa-solid fa-circle-info text-primary me-2"></i>General Information</h5>
                    <div class="row g-3">
                        <div class="col-md-7">
                            <label class="form-label fw-medium small text-muted">ITEM NAME</label>
                            <input type="text" name="item_name" class="form-control" placeholder="e.g. Projector Epson EB-X06" required>
                        </div>
                        <div class="col-md-5">
                            <label class="form-label fw-medium small text-muted">CATEGORY</label>
                            <select name="category_id" class="form-select" required>
                                <option value="" selected disabled>Select Category</option>
                                <?php foreach($categories as $cat): ?>
                                    <option value="<?= $cat['category_id'] ?>"><?= htmlspecialchars($cat['category_name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-12 mt-4">
                            <label class="form-label fw-medium small text-muted">ITEM IMAGE</label>
                            <div class="upload-zone" onclick="document.getElementById('fileInput').click()">
                                <div id="uploadContent">
                                    <i class="fa-solid fa-cloud-arrow-up fa-2x text-muted mb-2"></i>
                                    <p class="mb-0 small fw-bold text-dark">Click to upload product image</p>
                                    <span class="text-muted smaller">Recommended: 800x800px</span>
                                </div>
                                <img id="imagePreview">
                                <input type="file" name="item_image" id="fileInput" class="d-none" accept="image/*" onchange="previewImage(this)">
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card-body">
                    <h5 class="fw-bold mb-4"><i class="fa-solid fa-boxes-stacked text-primary me-2"></i>Stock & Asset Management</h5>
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label fw-medium small text-muted">INITIAL QUANTITY</label>
                            <input type="number" name="quantity" id="input_quantity" class="form-control" min="1" value="1" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-medium small text-muted">BRAND</label>
                            <input type="text" name="batch_brand" class="form-control" placeholder="e.g. Epson">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-medium small text-muted">MODEL</label>
                            <input type="text" name="batch_model" class="form-control" placeholder="e.g. EB-X06">
                        </div>
                    </div>

                    <div class="mt-4 p-3 border rounded-3 bg-light">
                        <div class="form-check form-switch d-flex align-items-center justify-content-between p-0">
                            <div>
                                <label class="form-check-label fw-bold" for="enable_manual_code">Manual Asset Coding</label>
                                <p class="text-muted small mb-0">Manually input unique codes for each unit</p>
                            </div>
                            <input class="form-check-input ms-0" style="width: 45px; height: 22px;" type="checkbox" id="enable_manual_code" name="enable_manual_code">
                        </div>
                    </div>

                    <div id="manual_assets_container" class="mt-4" style="display:none;">
                        <hr class="text-muted opacity-25">
                        <p class="fw-bold small mb-3">LIST OF ASSET CODES:</p>
                        <div id="dynamic_asset_inputs" class="row g-2"></div>
                    </div>
                </div>
            </div>

            <div class="d-flex justify-content-end gap-2">
                <button type="reset" class="btn btn-light px-4 fw-bold" onclick="location.reload()">Reset</button>
                <button type="submit" class="btn btn-register shadow-sm">
                    <i class="fa-solid fa-save me-2"></i>Register Assets
                </button>
            </div>
        </form>
    </div>
</div>

<script>
    // Sidebar logic
    const menuToggle = document.getElementById('menuToggle');
    const sidebar = document.getElementById('admin-sidebar');
    const overlay = document.getElementById('sidebarOverlay');

    if(menuToggle) {
        menuToggle.addEventListener('click', () => {
            sidebar.classList.add('active');
            overlay.classList.add('active');
        });
    }

    overlay.addEventListener('click', () => {
        sidebar.classList.remove('active');
        overlay.classList.remove('active');
    });

    // Image Preview
    function previewImage(input) {
        const file = input.files[0];
        if (file) {
            const reader = new FileReader();
            reader.onload = function(e) {
                document.getElementById('imagePreview').src = e.target.result;
                document.getElementById('imagePreview').style.display = 'inline-block';
                document.getElementById('uploadContent').style.display = 'none';
            }
            reader.readAsDataURL(file);
        }
    }

    // Manual Input Logic
    const qtyInput = document.getElementById('input_quantity');
    const checkbox = document.getElementById('enable_manual_code');
    const container = document.getElementById('manual_assets_container');
    const dynamicInputs = document.getElementById('dynamic_asset_inputs');

    function updateAssetInputs() {
        dynamicInputs.innerHTML = ''; 
        if (checkbox.checked) {
            container.style.display = 'block';
            let qty = parseInt(qtyInput.value) || 0;
            if(qty > 50) qty = 50; 
            for (let i = 1; i <= qty; i++) {
                dynamicInputs.innerHTML += `
                    <div class="col-md-4 mb-2">
                        <input type="text" name="manual_codes[]" class="form-control form-control-sm" placeholder="Code #${i}" required>
                    </div>`;
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