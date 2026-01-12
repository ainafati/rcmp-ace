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
    $item_name = mysqli_real_escape_string($conn, $_POST['item_name']);
    $cat_id = mysqli_real_escape_string($conn, $_POST['category_id']);
    $brand = mysqli_real_escape_string($conn, $_POST['batch_brand']);
    $model = mysqli_real_escape_string($conn, $_POST['batch_model']);
    $qty = (int)$_POST['quantity'];
    
    $image_path_db = ""; // Path yang disimpan dalam DB
    if(isset($_FILES['item_image']) && $_FILES['item_image']['error'] == 0){
        $target_dir = "../assets/item_images/"; // Folder fizikal
        if (!file_exists($target_dir)) mkdir($target_dir, 0777, true);
        
        $filename = time() . "_" . basename($_FILES["item_image"]["name"]);
        $target_file = $target_dir . $filename;
        
        if(move_uploaded_file($_FILES["item_image"]["tmp_name"], $target_file)){
            // Simpan path relatif dalam DB supaya konsisten
            $image_path_db = "assets/item_images/" . $filename;
        }
    }

    // Guna Prepared Statement untuk elakkan SQL Injection
    $sql_type = "INSERT INTO item (category_id, item_name, image_url) VALUES (?, ?, ?)";
    $stmt = $conn->prepare($sql_type);
    $stmt->bind_param("iss", $cat_id, $item_name, $image_path_db);
    
    if ($stmt->execute()) {
        $last_item_id = $conn->insert_id;

        if (isset($_POST['enable_manual_code']) && isset($_POST['manual_codes'])) {
            foreach ($_POST['manual_codes'] as $code) {
                $code = mysqli_real_escape_string($conn, $code);
                $stmt_asset = $conn->prepare("INSERT INTO assets (item_id, brand, model, asset_code, status) VALUES (?, ?, ?, ?, 'Available')");
                $stmt_asset->bind_param("isss", $last_item_id, $brand, $model, $code);
                $stmt_asset->execute();
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
        header("Location: manageItem_admin.php?success=1");
        exit();
    } else {
        echo "Error: " . $conn->error;
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
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <style>
        :root {
            --primary-color: #06b6d4; 
            --bg-light-gray: #f8fafc;
            --text-dark: #1e293b; 
            --text-muted: #64748b; 
            --danger-color: #ef4444;
            --border-color: #e2e8f0;
            --sidebar-width: 260px;
        }

        body { font-family: 'Inter', sans-serif; background-color: var(--bg-light-gray); color: #334155; min-height: 100vh; margin: 0; }

        /* SIDEBAR */
        .sidebar { 
            width: var(--sidebar-width); position: fixed; top: 0; bottom: 0; left: 0; 
            background: white; padding: 25px; 
            border-right: 1px solid var(--border-color); z-index: 1050; 
            display: flex; flex-direction: column; transition: transform 0.3s ease;
        }
        .sidebar-header { display: flex; align-items: center; gap: 12px; margin-bottom: 35px; }
        .logo-icon { 
            width: 42px; height: 42px; background-color: var(--primary-color); 
            color: white; border-radius: 10px; display: flex; align-items: center; 
            justify-content: center; font-size: 20px; 
        }
        .logo-text strong { display: block; font-size: 16px; color: var(--text-dark); line-height: 1.2; }
        .logo-text span { font-size: 12px; color: #94a3b8; }
        
        .sidebar a { 
            display: flex; align-items: center; gap: 12px; color: var(--text-muted); 
            text-decoration: none; padding: 12px 15px; margin-bottom: 8px; 
            border-radius: 10px; font-weight: 500; transition: 0.2s; 
        }
        .sidebar a.active, .sidebar a:hover { background: var(--primary-color); color: #fff; box-shadow: 0 4px 12px rgba(6, 182, 212, 0.2); }

        /* MOBILE NAV */
        .mobile-nav {
            display: none; background: white; padding: 12px 20px; border-bottom: 1px solid var(--border-color);
            position: sticky; top: 0; z-index: 1040; align-items: center; justify-content: space-between;
        }

        /* MAIN CONTENT */
        .main-content { margin-left: var(--sidebar-width); padding: 40px; transition: 0.3s; }

        /* OVERLAY */
        .sidebar-overlay {
            display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0;
            background: rgba(0,0,0,0.4); z-index: 1045; backdrop-filter: blur(2px);
        }

        /* CARDS */
        .card { border: 1px solid var(--border-color); border-radius: 16px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05); }
        .card-header-custom { padding: 18px 24px; border-bottom: 1px solid #f1f5f9; background: #fafafa; border-radius: 16px 16px 0 0; }
        
        /* UPLOAD ZONE */
        .upload-zone {
            border: 2px dashed #cbd5e1; border-radius: 15px; padding: 30px;
            text-align: center; cursor: pointer; transition: 0.3s; background: #f8fafc; position: relative;
        }
        .upload-zone:hover { border-color: var(--primary-color); background: #ecfeff; }
        #imagePreview { max-width: 150px; border-radius: 10px; margin-top: 15px; display: none; }

        .required-label::after { content: " *"; color: var(--danger-color); }
        .btn-save { background: var(--primary-color); border: none; padding: 12px 30px; border-radius: 10px; color: white; font-weight: 600; }

        /* RESPONSIVE */
        @media (max-width: 992px) {
            .sidebar { transform: translateX(-100%); }
            .sidebar.active { transform: translateX(0); }
            .mobile-nav { display: flex; }
            .main-content { margin-left: 0; padding: 20px; }
            .sidebar-overlay.active { display: block; }
            .btn-save { width: 100%; }
            .d-flex.justify-content-between { flex-direction: column-reverse; gap: 15px; }
        }
    </style>
</head>
<body>


<div class="main-content">
    <div class="mb-4">
        <a href="manageItem_admin.php" class="text-decoration-none text-muted small"><i class="fa fa-chevron-left me-2"></i> Back to Inventory</a>
    </div>

    <div class="mb-4">
        <h2 class="fw-bold text-dark">Register New Item</h2>
        <p class="text-muted">Add new equipment to UniKL's inventory system.</p>
    </div>

    <form method="post" enctype="multipart/form-data">
        <input type="hidden" name="add_item_type_and_units" value="1">
        
        <div class="card mb-4">
            <div class="card-header-custom d-flex align-items-center">
                <i class="fa fa-tag text-info me-2"></i><span class="fw-bold">General Information</span>
            </div>
            <div class="card-body p-4">
                <div class="row g-3">
                    <div class="col-md-7">
                        <label class="form-label required-label">Item Type Name</label>
                        <input type="text" name="item_name" class="form-control" placeholder="e.g. Dell Latitude 5420" required>
                    </div>
                    <div class="col-md-5">
                        <label class="form-label required-label">Category</label>
                        <select name="category_id" class="form-select" required>
                            <option value="" selected disabled>Choose category...</option>
                            <?php foreach($categories as $cat): ?>
                                <option value="<?= $cat['category_id'] ?>"><?= htmlspecialchars($cat['category_name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-12">
                        <label class="form-label">Item Image</label>
                        <div class="upload-zone" onclick="document.getElementById('fileInput').click()">
                            <div id="uploadContent">
                                <i class="fa fa-cloud-upload-alt fa-2x mb-2 text-info"></i>
                                <p class="mb-0 text-muted small" id="fileNameDisplay">Click to upload or drag and drop</p>
                            </div>
                            <img id="imagePreview" src="">
                            <input type="file" name="item_image" id="fileInput" class="d-none" accept="image/*" onchange="previewImage(this)">
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="card mb-4">
            <div class="card-header-custom d-flex align-items-center">
                <i class="fa fa-layer-group text-success me-2"></i><span class="fw-bold">Stock & Asset Management</span>
            </div>
            <div class="card-body p-4">
                <div class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label required-label">Initial Quantity</label>
                        <input type="number" name="quantity" id="input_quantity" class="form-control" min="1" value="1" required>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Brand</label>
                        <input type="text" name="batch_brand" class="form-control" placeholder="e.g. Dell">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Model/Series</label>
                        <input type="text" name="batch_model" class="form-control" placeholder="e.g. Latitude">
                    </div>
                </div>

                <div class="form-check form-switch mt-4">
                    <input class="form-check-input" type="checkbox" id="enable_manual_code" name="enable_manual_code">
                    <label class="form-check-label ms-2 fw-bold">Enter Asset Codes manually</label>
                </div>

                <div id="manual_assets_container" class="mt-4 p-3 border rounded-4 bg-light" style="display:none;">
                    <div id="dynamic_asset_inputs" class="row g-2"></div>
                </div>
            </div>
        </div>

        <div class="d-flex justify-content-between align-items-center mb-5">
            <button type="reset" class="btn btn-link text-muted text-decoration-none" onclick="location.reload()">Discard Changes</button>
            <button type="submit" class="btn btn-save">Register Item</button>
        </div>
    </form>
</div>

<script>
    // Sidebar Toggle
    const sidebar = document.getElementById('sidebar');
    const overlay = document.getElementById('overlay');
    const btnToggle = document.getElementById('btnToggle');

    function toggleMenu() {
        sidebar.classList.toggle('active');
        overlay.classList.toggle('active');
    }

    btnToggle.addEventListener('click', toggleMenu);
    overlay.addEventListener('click', toggleMenu);

    // Preview Image Logic
    function previewImage(input) {
        const file = input.files[0];
        const preview = document.getElementById('imagePreview');
        const content = document.getElementById('uploadContent');
        const fileName = document.getElementById('fileNameDisplay');

        if (file) {
            const reader = new FileReader();
            reader.onload = function(e) {
                preview.src = e.target.result;
                preview.style.display = 'inline-block';
                content.style.display = 'none';
                fileName.innerText = file.name;
            }
            reader.readAsDataURL(file);
        }
    }

    // Manual Asset Codes Logic
    const qtyInput = document.getElementById('input_quantity');
    const checkbox = document.getElementById('enable_manual_code');
    const container = document.getElementById('manual_assets_container');
    const dynamicInputs = document.getElementById('dynamic_asset_inputs');

    function updateAssetInputs() {
        dynamicInputs.innerHTML = '';
        if (checkbox.checked) {
            container.style.display = 'block';
            let qty = Math.min(parseInt(qtyInput.value) || 0, 100);
            for (let i = 1; i <= qty; i++) {
                dynamicInputs.innerHTML += `
                    <div class="col-md-4 mb-2">
                        <input type="text" name="manual_codes[]" class="form-control form-control-sm" placeholder="Unit ${i} Code" required>
                    </div>`;
            }
        } else { container.style.display = 'none'; }
    }

    checkbox.addEventListener('change', updateAssetInputs);
    qtyInput.addEventListener('input', updateAssetInputs);
</script>

</body>
</html>