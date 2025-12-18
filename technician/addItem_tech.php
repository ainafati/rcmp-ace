<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add New Item — UniKL</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary-color: #06b6d4; 
            --primary-hover: #0891b2; 
            --danger-color: #ef4444; 
            --bg-light-gray: #f8fafc;
            --card-bg: #ffffff;
            --text-dark: #1e293b; 
            --text-muted: #64748b; 
            --border-color: #e2e8f0;
        }

        body { 
            font-family: 'Inter', sans-serif; 
            background-color: var(--bg-light-gray); 
            color: #334155; 
            min-height: 100vh; 
        }

        /* SIDEBAR (Design Kekal) */
        .sidebar { 
            width: 250px; position: fixed; top: 0; bottom: 0; left: 0; 
            background: var(--card-bg); padding: 20px; 
            border-right: 1px solid var(--border-color); z-index: 1000; 
            display: flex; flex-direction: column;
        }
        .sidebar-header { display: flex; align-items: center; gap: 12px; margin-bottom: 30px; }
        .logo-icon { 
            width: 40px; height: 40px; background-color: var(--primary-color); 
            color: white; border-radius: 10px; display: flex; 
            align-items: center; justify-content: center; font-size: 20px; 
        }
        .sidebar a { 
            display: flex; align-items: center; gap: 12px; 
            color: var(--text-muted); text-decoration: none; padding: 12px 15px; 
            margin-bottom: 8px; border-radius: 10px; font-weight: 500; transition: all 0.2s; 
        }
        .sidebar a.active, .sidebar a:hover { background: var(--primary-color); color: #fff; }
        .sidebar a.logout-link { color: var(--danger-color); margin-top: auto; font-weight: 600; }
        .sidebar a.logout-link:hover { background: var(--danger-color); color: white; }

        /* MAIN CONTENT area */
        .main-content { margin-left: 250px; padding-bottom: 50px; }
        
        .top-nav-bar {
            padding: 20px 40px;
            background: transparent;
            display: flex;
            align-items: center;
        }

        /* FORM STYLING - LEBIH CANTIK */
        .form-container { max-width: 900px; margin: 0 auto; padding: 0 20px; }
        
        .card { 
            border: none; 
            border-radius: 20px; 
            box-shadow: 0 10px 25px rgba(0,0,0,0.02); 
            transition: transform 0.3s ease;
            background: #ffffff;
            overflow: hidden;
        }
        
        .card-header-custom {
            padding: 20px 25px;
            border-bottom: 1px solid #f1f5f9;
            background: #fff;
        }

        .section-icon {
            width: 35px; height: 35px;
            border-radius: 8px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            margin-right: 12px;
        }

        .form-label { font-weight: 600; font-size: 0.875rem; color: var(--text-dark); margin-bottom: 8px; }
        
        .form-control, .form-select { 
            padding: 12px 15px;
            border-radius: 10px;
            border: 1px solid #e2e8f0;
            background-color: #fcfdfe;
            transition: all 0.2s;
        }

        .form-control:focus, .form-select:focus {
            box-shadow: 0 0 0 4px rgba(6, 182, 212, 0.1);
            border-color: var(--primary-color);
            background-color: #fff;
        }

        /* CUSTOM CHECKBOX */
        .form-check-input:checked {
            background-color: var(--primary-color);
            border-color: var(--primary-color);
        }

        .asset-code-entry {
            background: #f8fafc;
            border: 1px dashed #cbd5e1;
            border-radius: 12px;
            padding: 20px;
            margin-top: 15px;
        }

        .btn-save {
            background: var(--primary-color);
            border: none;
            padding: 14px 40px;
            border-radius: 12px;
            font-weight: 600;
            color: white;
            box-shadow: 0 4px 15px rgba(6, 182, 212, 0.3);
            transition: all 0.3s;
        }

        .btn-save:hover {
            background: var(--primary-hover);
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(6, 182, 212, 0.4);
            color: white;
        }

        .back-link {
            color: var(--text-muted);
            font-weight: 500;
            transition: color 0.2s;
        }
        .back-link:hover { color: var(--primary-color); }

        /* Animation */
        @keyframes fadeInUp {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .animate-up { animation: fadeInUp 0.5s ease forwards; }

    </style>
</head>
<body>

<div class="sidebar">
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
    <div class="top-nav-bar">
        <a href="manageItem_tech.php" class="back-link text-decoration-none">
            <i class="fa fa-chevron-left me-2"></i> Back to Inventory
        </a>
    </div>

    <div class="form-container">
        <div class="mb-4 animate-up">
            <h2 class="fw-bold text-dark">Register New Item</h2>
            <p class="text-muted">Fill in the details below to add a new equipment type to the system.</p>
        </div>

        <form method="post" action="manageItem_tech.php" enctype="multipart/form-data">
            <input type="hidden" name="add_item_type_and_units" value="1">
            
            <div class="card mb-4 animate-up" style="animation-delay: 0.1s;">
                <div class="card-header-custom">
                    <span class="section-icon bg-info-subtle text-info"><i class="fa fa-tag"></i></span>
                    <span class="fw-bold">General Information</span>
                </div>
                <div class="card-body p-4">
                    <div class="row g-4">
                        <div class="col-md-7">
                            <label class="form-label">Item Type Name</label>
                            <input type="text" name="item_name" class="form-control" placeholder="e.g. Dell Latitude 5420" required>
                        </div>
                        <div class="col-md-5">
                            <label class="form-label">Category</label>
                            <select name="category_id" class="form-select" required>
                                <option value="" selected disabled>Choose category...</option>
                                <?php foreach($categories as $cat): ?>
                                    <option value="<?= $cat['category_id'] ?>"><?= htmlspecialchars($cat['category_name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Description</label>
                            <textarea name="description" class="form-control" rows="3" placeholder="Brief details about the item..."></textarea>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Item Image (Optional)</label>
                            <div class="input-group">
                                <span class="input-group-text bg-white"><i class="fa fa-image text-muted"></i></span>
                                <input type="file" name="item_image" class="form-control" accept="image/*">
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card mb-4 animate-up" style="animation-delay: 0.2s;">
                <div class="card-header-custom">
                    <span class="section-icon bg-success-subtle text-success"><i class="fa fa-layer-group"></i></span>
                    <span class="fw-bold">Stock & Asset Management</span>
                </div>
                <div class="card-body p-4">
                    <div class="row g-4 align-items-center">
                        <div class="col-md-4">
                            <label class="form-label">Initial Quantity</label>
                            <div class="input-group">
                                <button class="btn btn-outline-secondary" type="button" onclick="changeQty(-1)">-</button>
                                <input type="number" name="quantity" id="input_quantity" class="form-control text-center" min="1" value="1">
                                <button class="btn btn-outline-secondary" type="button" onclick="changeQty(1)">+</button>
                            </div>
                        </div>
                        <div class="col-md-8">
                            <div class="form-check form-switch mt-4">
                                <input class="form-check-input" type="checkbox" id="enable_manual_code" name="enable_manual_code" style="width: 40px; height: 20px;">
                                <label class="form-check-label ms-2 fw-600" for="enable_manual_code">
                                    I want to enter Asset Codes manually
                                </label>
                            </div>
                        </div>
                    </div>

                    <div id="manual_assets_container" class="asset-code-entry" style="display:none;">
                        <p class="small text-muted mb-3"><i class="fa fa-barcode me-1"></i> Please enter a unique code for each unit:</p>
                        <div id="dynamic_asset_inputs" class="row g-2">
                            </div>
                    </div>

                    <hr class="my-4" style="opacity: 0.1;">

                    <div class="row g-4">
                        <div class="col-md-6">
                            <label class="form-label">Brand</label>
                            <input type="text" name="batch_brand" class="form-control" placeholder="e.g. Logitech">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Model/Series</label>
                            <input type="text" name="batch_model" class="form-control" placeholder="e.g. M185">
                        </div>
                    </div>
                </div>
            </div>

            <div class="d-flex align-items-center justify-content-between animate-up" style="animation-delay: 0.3s;">
                <button type="button" class="btn btn-link text-muted text-decoration-none" onclick="history.back()">Discard Changes</button>
                <button type="submit" class="btn btn-save">
                    <i class="fa fa-check-circle me-2"></i> Register Item
                </button>
            </div>
        </form>
    </div>
</div>

<script>
    const qtyInput = document.getElementById('input_quantity');
    const checkbox = document.getElementById('enable_manual_code');
    const container = document.getElementById('manual_assets_container');
    const dynamicInputs = document.getElementById('dynamic_asset_inputs');

    function changeQty(val) {
        let current = parseInt(qtyInput.value) || 1;
        if (current + val >= 1) {
            qtyInput.value = current + val;
            updateAssetInputs();
        }
    }

    function updateAssetInputs() {
        dynamicInputs.innerHTML = ''; 
        if (checkbox.checked) {
            container.style.display = 'block';
            let qty = parseInt(qtyInput.value) || 0;
            // Limit to avoid browser lag if user enters 1000+
            let safeQty = Math.min(qty, 50); 
            
            for (let i = 1; i <= safeQty; i++) {
                dynamicInputs.innerHTML += `
                    <div class="col-md-4">
                        <div class="form-floating mb-2">
                            <input type="text" name="manual_codes[]" class="form-control form-control-sm" id="code_${i}" placeholder="Asset ${i}" required>
                            <label for="code_${i}">Unit ${i} Code</label>
                        </div>
                    </div>`;
            }
        } else {
            container.style.display = 'none';
        }
    }

    qtyInput.addEventListener('input', updateAssetInputs);
    checkbox.addEventListener('change', updateAssetInputs);
</script>
</body>
</html>