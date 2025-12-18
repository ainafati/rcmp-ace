<?php
// 1. Panggil config dulu! Supaya $conn wujud.
include '../config.php'; 

// 2. Baru check connection
if (!$conn) {
    die("Connection failed: " . mysqli_connect_error());
}

// 3. Ambil data kategori untuk dropdown (Pastikan nama table betul: 'category' atau 'categories')
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
    $desc = mysqli_real_escape_string($conn, $_POST['description']);
    $brand = mysqli_real_escape_string($conn, $_POST['batch_brand']);
    $model = mysqli_real_escape_string($conn, $_POST['batch_model']);
    $qty = (int)$_POST['quantity'];
    
    $target_file = "";
    if(isset($_FILES['item_image']) && $_FILES['item_image']['error'] == 0){
        $target_dir = "../uploads/";
        if (!file_exists($target_dir)) mkdir($target_dir, 0777, true);
        $target_file = $target_dir . time() . "_" . basename($_FILES["item_image"]["name"]);
        move_uploaded_file($_FILES["item_image"]["tmp_name"], $target_file);
    }

    $sql_type = "INSERT INTO item (category_id, item_name, description, image_url) VALUES ('$cat_id', '$item_name', '$desc', '$target_file')";
    
if (mysqli_query($conn, $sql_type)) {
    $last_item_id = mysqli_insert_id($conn);

    if (isset($_POST['enable_manual_code']) && isset($_POST['manual_codes'])) {
        foreach ($_POST['manual_codes'] as $code) {
            $code = mysqli_real_escape_string($conn, $code);
            mysqli_query($conn, "INSERT INTO assets (item_id, brand, model, asset_code, status) 
                               VALUES ('$last_item_id', '$brand', '$model', '$code', 'Available')");
        }
    } else {
        // --- BAHAGIAN GENERATE AUTO CODE BARU ---
        $current_year = date('Y'); 
        for ($i = 1; $i <= $qty; $i++) {
            // Pad nombor siri jadi 3 digit (001, 002, etc.)
            $serial_number = str_pad($i, 3, '0', STR_PAD_LEFT);
            
            // Gabungkan Tahun + Siri (Contoh: 2025001)
            $auto_code = $current_year . $serial_number;
            
            mysqli_query($conn, "INSERT INTO assets (item_id, brand, model, asset_code, status) 
                               VALUES ('$last_item_id', '$brand', '$model', '$auto_code', 'Available')");
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
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <style>
        :root {
            --primary-color: #06b6d4; 
            --bg-light-gray: #f8fafc;
            --text-dark: #1e293b; 
            --text-muted: #64748b; 
            --danger-color: #ef4444;
            --border-color: #e2e8f0;
        }

        body { font-family: 'Inter', sans-serif; background-color: var(--bg-light-gray); color: #334155; min-height: 100vh; }

        /* --- SIDEBAR --- */
        .sidebar { 
            width: 250px; position: fixed; top: 0; bottom: 0; left: 0; 
            background: #fff; padding: 20px; 
            border-right: 1px solid var(--border-color); z-index: 1000; 
            display: flex; flex-direction: column; 
        }

        .sidebar-header { 
            display: flex; align-items: center; gap: 12px; 
            padding-bottom: 20px;
            margin-bottom: 20px;
            border-bottom: 2px solid var(--bg-light-gray); /* GARISAN BAWAH LOGO */
        }
        
        .logo-icon { 
            width: 40px; height: 40px; background-color: var(--primary-color); 
            color: white; border-radius: 8px; display: flex; align-items: center; 
            justify-content: center; font-size: 20px; 
        }
        
        .logo-text strong { display: block; font-size: 16px; color: var(--text-dark); }
        .logo-text span { font-size: 11px; color: #94a3b8; text-transform: uppercase; }
        
        .sidebar nav { flex-grow: 1; }

        .sidebar nav a { 
            display: flex; align-items: center; gap: 12px; 
            color: var(--text-muted); text-decoration: none; padding: 12px 15px; 
            margin-bottom: 5px; border-radius: 8px; font-weight: 500; font-size: 15px; 
            transition: all 0.2s;
            border-bottom: 1px solid #f8fafc; /* GARISAN ANTARA MENU */
        }
        
        .sidebar nav a.active, .sidebar nav a:hover { 
            background: #ecfeff; color: var(--primary-color); 
        }

        /* --- LOGOUT BUTTON MERAH --- */
        .logout-section {
            padding-top: 15px;
            border-top: 1px solid var(--border-color); /* GARISAN SEBELUM LOGOUT */
        }

        .logout-link { 
            display: flex; align-items: center; gap: 12px;
            padding: 12px 15px; border-radius: 8px;
            text-decoration: none; font-weight: 600;
            background: #fef2f2; color: var(--danger-color) !important; 
            transition: 0.3s;
        } 
        .logout-link:hover { background: var(--danger-color); color: white !important; }

        /* --- MAIN CONTENT --- */
        .main-content { margin-left: 250px; padding: 40px; }

        /* --- REQUIRED STAR --- */
        .required-label::after {
            content: " *";
            color: var(--danger-color);
            font-weight: bold;
        }

        .card { 
            border: 1px solid var(--border-color); border-radius: 20px; 
            box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05); background: white;
            overflow: hidden;
        }
        .card-header-custom { padding: 15px 20px; border-bottom: 1px solid #f1f5f9; display: flex; align-items: center; background: #fafafa; }
        
        .upload-zone {
            border: 2px dashed #cbd5e1; border-radius: 15px; padding: 25px;
            text-align: center; cursor: pointer; transition: 0.3s; background: #f8fafc;
        }
        .upload-zone:hover { border-color: var(--primary-color); background: #ecfeff; }

        .btn-save {
            background: var(--primary-color); border: none; padding: 12px 35px;
            border-radius: 10px; font-weight: 600; color: white; transition: 0.3s;
        }
        .btn-save:hover { background: #0891b2; transform: translateY(-2px); }

        .animate-up { animation: fadeInUp 0.5s ease forwards; }
        @keyframes fadeInUp { from { opacity: 0; transform: translateY(20px); } to { opacity: 1; transform: translateY(0); } }
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
        <a href="manage_requests.php"><i class="fa-solid fa-clipboard-list"></i> Manage Requests</a>
        <a href="manageItem_tech.php" class="active"><i class="fa-solid fa-box-archive"></i> Manage Items</a>
        <a href="report.php"><i class="fa-solid fa-chart-line"></i> Report</a>
    </nav>

    <div class="logout-section">
        <a href="logout.php" class="logout-link"><i class="fa-solid fa-right-from-bracket"></i> Logout</a>
    </div>
</div>

<div class="main-content">
    <div class="mb-4">
        <a href="manageItem_tech.php" class="text-decoration-none text-muted small fw-500">
            <i class="fa fa-chevron-left me-2"></i> Back to Inventory
        </a>
    </div>

    <div class="form-container">
        <div class="mb-4 animate-up">
            <h2 class="fw-bold text-dark">Register New Item</h2>
            <p class="text-muted">Tambah peralatan baharu ke dalam sistem inventori UniKL.</p>
        </div>

        <form id="addItemForm" method="post" enctype="multipart/form-data">
            <input type="hidden" name="add_item_type_and_units" value="1">
            
            <div class="card mb-4 animate-up">
                <div class="card-header-custom">
                    <i class="fa fa-tag text-info me-2"></i>
                    <span class="fw-bold">General Information</span>
                </div>
                <div class="card-body p-4">
                    <div class="row g-4">
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
                            <label class="form-label">Description</label>
                            <textarea name="description" class="form-control" rows="2" placeholder="Brief details..."></textarea>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Item Image</label>
                            <div class="upload-zone" onclick="document.getElementById('fileInput').click()">
                                <i class="fa fa-cloud-upload-alt fa-2x mb-2 text-info"></i>
                                <p class="mb-0 text-muted small" id="fileNameDisplay">Click to upload or drag and drop</p>
                                <input type="file" name="item_image" id="fileInput" class="d-none" accept="image/*" onchange="updateFileName(this)">
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card mb-4 animate-up" style="animation-delay: 0.1s;">
                <div class="card-header-custom">
                    <i class="fa fa-layer-group text-success me-2"></i>
                    <span class="fw-bold">Stock & Asset Management</span>
                </div>
                <div class="card-body p-4">
                    <div class="row g-4">
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
                        <label class="form-check-label ms-2 fw-600">Enter Asset Codes manually</label>
                    </div>

                    <div id="manual_assets_container" class="mt-4 p-3 border rounded-4 bg-light" style="display:none;">
                        <div id="dynamic_asset_inputs" class="row g-2"></div>
                    </div>
                </div>
            </div>

            <div class="d-flex align-items-center justify-content-between">
                <button type="reset" class="btn btn-link text-muted text-decoration-none">Discard Changes</button>
                <button type="submit" class="btn btn-save">Register Item</button>
            </div>
        </form>
    </div>
</div>

<script>
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
                    <div class="col-md-4 mb-2">
                        <input type="text" name="manual_codes[]" class="form-control form-control-sm" placeholder="Unit ${i} Code" required>
                    </div>`;
            }
        } else { container.style.display = 'none'; }
    }

    checkbox.addEventListener('change', updateAssetInputs);
    qtyInput.addEventListener('input', updateAssetInputs);

    function updateFileName(input) {
        document.getElementById('fileNameDisplay').innerText = input.files.length > 0 ? input.files[0].name : "Click to upload or drag and drop";
    }
</script>

</body>
</html>