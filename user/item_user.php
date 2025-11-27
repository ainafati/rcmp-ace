<?php
session_start();
include '../config.php';

// Pastikan sambungan DB berjaya
if (!$conn) {
    error_log("Database connection failed: " . mysqli_connect_error());
    die("Server connection error."); 
}

// Semak Log Masuk
if (!isset($_SESSION['person_id'])) {
    header("Location: ../login.php");
    exit();
}

$person_id = (int) $_SESSION['person_id'];

// 1. Fetch User Data
$stmt = $conn->prepare("SELECT name, email, phoneNum FROM person WHERE person_id = ?");
if ($stmt === false) {
    error_log("Error preparing statement: " . $conn->error);
    die("Server error.");
}
$stmt->bind_param("i", $person_id);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();
$stmt->close();

if (!$user) {
    session_destroy();
    header("Location: ../login.php");
    exit();
}

// 2. Fetch Categories (Untuk Menu Sidebar Kategori)
$categories = [];
$res_cat = $conn->query("SELECT category_id, category_name FROM categories ORDER BY category_name");
if ($res_cat) {
    while ($row = $res_cat->fetch_assoc()) {
        $categories[] = $row;
    }
    $res_cat->free();
}

// 3. Fetch All Items for Dropdown & Filtering (ALL_ITEMS_DATA)
$items_for_dropdown = [];
$sql_all_items = "
    SELECT 
        i.item_id, i.item_name, c.category_name, i.category_id, i.image_url 
    FROM item i
    JOIN categories c ON i.category_id = c.category_id
    ORDER BY c.category_name, i.item_name ASC
";
$res_items = $conn->query($sql_all_items);
if ($res_items) {
    while ($row = $res_items->fetch_assoc()) {
        $items_for_dropdown[] = $row;
    }
    $res_items->free();
}
// Tutup sambungan DB
$conn->close();

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Item Availability — UniKL</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" rel="stylesheet" />
<style>
        /* =========================================================================
            1. VARIABLE & BASE STYLES
            ========================================================================= */
        :root {
            --primary-color: #06b6d4; 
            --primary-light: #f0f9ff; 
            --primary-hover: #0891b2; 
            --bg-light-gray: #f8fafc;
            --card-bg: #ffffff;
            --text-dark: #1e293b;
            --text-muted: #64748b;
            --shadow-light: 0 4px 10px rgba(0, 0, 0, 0.04);
        }

        body {
            font-family: 'Inter', 'Segoe UI', sans-serif;
            background-color: var(--bg-light-gray);
            color: var(--text-dark);
            min-height: 100vh;
            overflow-x: hidden;
        }

        /* =========================================================================
            2. LAYOUT: SIDEBAR & TOPBAR
            ========================================================================= */
        
        /* ➡️ SIDEBAR */
        .sidebar {
            width: 280px;
            height: 100vh;
            position: fixed;
            top: 0;
            left: 0;
            background: var(--card-bg);
            padding: 20px;
            box-shadow: 2px 0 10px rgba(0, 0, 0, 0.05);
            z-index: 1050;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            transition: transform 0.3s ease-in-out;
            overflow-y: auto;
        }

        .sidebar-header { display: flex; align-items: center; gap: 10px; margin-bottom: 35px; }
        .logo-icon { width: 45px; height: 45px; background-color: var(--primary-color); color: white; border-radius: 10px; display: flex; align-items: center; justify-content: center; font-size: 22px; }
        .logo-text strong { display: block; font-size: 18px; color: var(--text-dark); font-weight: 700; }
        .logo-text span { font-size: 12px; color: var(--text-muted); font-weight: 500; }

        .sidebar a {
            display: flex; align-items: center; gap: 15px;
            color: var(--text-muted); text-decoration: none;
            padding: 14px 18px; margin-bottom: 6px;
            border-radius: 10px; font-weight: 500; font-size: 15px;
            transition: all 0.2s;
        }
        .sidebar a.active {
            background: var(--primary-light);
            color: var(--primary-color) !important;
            font-weight: 700;
            box-shadow: 0 2px 8px rgba(6, 182, 212, 0.1);
        }
        .sidebar a:hover:not(.active) {
            background: #eef1f4;
            color: var(--text-dark);
        }
        .sidebar a.logout-link { color: #ef4444; font-weight: 600; margin-top: 20px; }
        .sidebar a i { width: 20px; text-align: center; }


        /* ➡️ KATEGORI FILTER STYLES (Sub-Menu) */
        .category-submenu {
            padding-left: 15px;
            max-height: 0;
            overflow: hidden;
            transition: max-height 0.3s ease-in-out;
        }
        .category-submenu.show {
            max-height: 500px;
        }
        .category-submenu .category-filter-link {
            padding: 8px 18px;
            font-size: 14px;
            font-weight: 500;
            margin-bottom: 2px;
        }
        .category-submenu .active-category-filter {
            background: var(--primary-light);
            color: var(--primary-color) !important;
            font-weight: 700;
        }
        .category-submenu .category-filter-link:hover:not(.active-category-filter) {
              background: #eef1f4;
              color: var(--text-dark);
        }
        .sidebar .item-availability-link i.fa-angle-down {
            transition: transform 0.3s;
        }
        /* Ikon panah ke bawah (tertutup) */
        .sidebar .item-availability-link.collapsed i.fa-angle-down {
            transform: rotate(-90deg);
        }


        /* ➡️ CONTENT WRAPPER */
        .main-content {
            margin-left: 280px;
            transition: margin-left 0.3s ease-in-out;
        }
        .container-fluid { padding: 30px; }

        /* ➡️ TOPBAR */
        .topbar {
            background: var(--card-bg);
            padding: 15px 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 1px solid #e2e8f0;
            z-index: 999;
            position: sticky;
            top: 0;
        }
        .topbar h3 { font-weight: 600; margin: 0; color: var(--text-dark); font-size: 22px; }
        .topbar .user-profile { display: flex; align-items: center; gap: 12px; }
        .topbar .user-name { font-weight: 600; font-size: 15px; color: var(--text-dark); }
        .profile-img { width: 40px; height: 40px; object-fit: cover; border-radius: 50%; }


        /* =========================================================================
            3. FORMS & CARDS
            ========================================================================= */
        .card {
            border-radius: 16px;
            padding: 25px;
            box-shadow: var(--shadow-light);
            background: var(--card-bg);
            margin-bottom: 25px;
            border: 1px solid #e2e8f0;
        }
        .card h5 { font-weight: 600; color: var(--text-dark); margin-bottom: 5px; }
        .text-primary { color: var(--primary-color) !important; }
        .btn-primary {
            background-color: var(--primary-color);
            border-color: var(--primary-color);
            font-weight: 600;
        }
        .btn-primary:hover {
            background-color: var(--primary-hover);
            border-color: var(--primary-hover);
        }
        .btn { border-radius: 8px; padding: 10px 20px; font-weight: 500; }
        .form-label { font-weight: 500; color: #334155; }
        .form-control, .form-select {
            border-radius: 8px;
            padding: 10px 12px;
            min-height: 48px; 
            border-color: #e2e8f0; 
        }

        /* Nav Tabs Styling for Steps */
        .nav-tabs { 
            border-bottom: none; 
            margin-bottom: 25px;
            padding: 0;
        }
        .nav-tabs .nav-link {
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            margin-right: 8px;
            color: var(--text-muted);
            background-color: var(--card-bg);
            padding: 10px 15px;
            font-weight: 600;
            transition: all 0.2s;
        }
        .nav-tabs .nav-link.active {
            border-color: var(--primary-color) !important;
            background-color: var(--primary-light);
            color: var(--primary-color);
        }
        .nav-tabs .nav-link.disabled {
            opacity: 0.5;
            cursor: not-allowed;
            background-color: #f1f5f9;
        }

        /* Select2 Styling Fixes */
        .select2-container--bootstrap-5 .select2-selection {
            border-radius: 8px !important;
            padding: 0.375rem 0.75rem !important; 
            min-height: 48px !important; 
            border: 1px solid #e2e8f0 !important; 
        }
        .select2-container--bootstrap-5 .select2-selection--single {
            height: 48px !important;
        }
        .select2-container--bootstrap-5 .select2-selection__rendered {
            line-height: 46px !important; 
        }
        .select2-container--bootstrap-5 .select2-selection__arrow {
            width: 30px !important;
            height: 46px !important; 
        } 
        
        /* Item List Display - Imej */
        .category-image-box { 
            width: 50px; 
            height: 50px; 
            border-radius: 6px; 
            display: flex; 
            align-items: center; 
            justify-content: center; 
            overflow: hidden; 
            border: 1px solid #e2e8f0;
            margin-right: 12px;
            flex-shrink: 0; 
        } 
        .category-thumb-img {
            width: 100%;
            height: 100%;
            object-fit: cover; 
        }

        .list-group-flush .list-group-item { padding-left: 0; padding-right: 0; border-color: #f1f5f9; }


        /* =========================================================================
            4. MOBILE STYLES
            ========================================================================= */
        .menu-toggle-btn { display: none; }
        #overlay { position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0, 0, 0, 0.5); z-index: 1040; display: none; } 

        @media (max-width: 992px) {
            .sidebar {
                transform: translateX(-280px);
            }
            .sidebar.active {
                transform: translateX(0);
            }
            .sidebar.active ~ #overlay {
                display: block;
            }
            .main-content {
                margin-left: 0;
                width: 100%;
            }
            
            .menu-toggle-btn {
                display: inline-block;
                order: -1;
                font-size: 20px;
                background: none;
                border: none;
                color: #1e293b;
                padding: 0;
            }
            
            .topbar {
                padding: 10px 15px;
                display: grid;
                grid-template-columns: auto 1fr auto;
                align-items: center;
                gap: 15px;
            }
            .topbar h3 {
                font-size: 18px;
                text-align: left;
            }
            .topbar .user-profile {
                order: 3;
                justify-self: end;
            }
            .topbar .user-name {
                display: none;
            }
            .container-fluid {
                padding: 15px;
            }
            .card {
                padding: 15px;
            }
            .col-lg-7, .col-lg-5 { 
                flex: 0 0 100% !important;
                max-width: 100% !important;
            }
            .d-grid {
                display: grid !important;
                grid-template-columns: 1fr;
                gap: 10px !important;
            }
            .nav-tabs .nav-link {
                 margin-bottom: 5px;
            }
        }
    </style>
</head>
<body>

<div id="overlay"></div> 

<div class="sidebar">
    <div>
        <div class="sidebar-header">
            <div class="logo-icon"><i class="fa-solid fa-cube"></i></div>
            <div class="logo-text"><strong>UniKL User</strong><span>Equipment System</span></div>
        </div>
        
        <a href="dashboard_user.php"><i class="fa-solid fa-table-columns"></i> Dashboard</a>
        
        <a href="#" class="active item-availability-link collapsed" id="itemAvailabilityToggle">
            <i class="fa-solid fa-box"></i> Item Availability 
            <i class="fa-solid fa-angle-down ms-auto" style="font-size: 12px;"></i>
        </a>
        
        <div class="category-submenu" id="categorySubmenu">
            <a href="#" class="category-filter-link active-category-filter" data-category="">
                <i class="fa-solid fa-layer-group me-2"></i> All Items
            </a>
            <?php foreach ($categories as $category): ?>
                <a href="#" class="category-filter-link" data-category="<?= htmlspecialchars($category['category_name']) ?>">
                    <i class="fa-solid fa-tag me-2"></i> <?= htmlspecialchars($category['category_name']) ?>
                </a>
            <?php endforeach; ?>
        </div>

        <a href="history.php"><i class="fa-solid fa-clock-rotate-left"></i> History</a>
        
    </div>
    <a href="logout.php" class="logout-link"><i class="fa-solid fa-right-from-bracket"></i> Logout</a>
</div>
<div class="main-content">
    <div class="topbar">
        <button class="menu-toggle-btn" id="menuToggle">
            <i class="fa fa-bars"></i>
        </button>
        <h3>Item Availability</h3>
        <div class="user-profile">
            <span class="user-name"><?= htmlspecialchars($user['name']) ?></span>
            <a href="profile.php" title="Go to My Profile" style="color: inherit; text-decoration: none;">
                <i class="fa-solid fa-circle-user fa-2x text-secondary"></i>
            </a>
        </div>
    </div>

    <div class="container-fluid">
        <div class="row">
            
            <div class="col-lg-5 order-lg-2">
                <div class="card">
                    <h5><i class="fa-solid fa-boxes-stacked me-2 text-primary"></i> Available Item List</h5>
                    <p class="text-muted small">View items available for loan. Select a category in the sidebar to filter.</p>
                    
                    <div class="list-group list-group-flush" id="displayItemList">
                        </div>
                </div>
            </div>
            
            <div class="col-lg-7 order-lg-1">
                <form id="reserveForm">
                    <input type="hidden" name="person_id" value="<?= isset($person_id) ? $person_id : '' ?>">
                    <input type="hidden" name="all_items" id="allItems">

                    <ul class="nav nav-tabs" id="myTab" role="tablist">
                        <li class="nav-item" role="presentation">
                            <button class="nav-link active" id="context-tab" data-bs-toggle="tab" data-bs-target="#context-tab-pane" type="button" role="tab" aria-controls="context-tab-pane" aria-selected="true">
                                <i class="fa-solid fa-1 me-1"></i> Context
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link disabled" id="items-tab" data-bs-toggle="tab" data-bs-target="#items-tab-pane" type="button" role="tab" aria-controls="items-tab-pane" aria-selected="false">
                                <i class="fa-solid fa-2 me-1"></i> Add Items
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link disabled" id="review-tab" data-bs-toggle="tab" data-bs-target="#review-tab-pane" type="button" role="tab" aria-controls="review-tab-pane" aria-selected="false">
                                <i class="fa-solid fa-3 me-1"></i> Review & Submit
                            </button>
                        </li>
                    </ul>

                    <div class="tab-content" id="myTabContent">
                        
                        <div class="tab-pane fade show active" id="context-tab-pane" role="tabpanel" aria-labelledby="context-tab" tabindex="0">
                            <div class="card" id="ContextCard">
                                <h5><i class="fa-solid fa-file-pen me-2 text-primary"></i> <strong>Step 1: Define & Lock Context</strong></h5>
                                <p class="text-muted small">Specify the borrowing dates and the main purpose for this <strong>single</strong> submission.</p>
                                <hr>
                                
                                <div class="row mb-3">
                                    <div class="col-md-6">
                                        <label class="form-label" for="reserveDate">1. Borrow Date</label>
                                        <input type="text" id="reserveDate" class="form-control" name="reserve_date" placeholder="Select a date..." required>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label" for="returnDate">2. Return Date</label>
                                        <input type="text" id="returnDate" class="form-control" name="return_date" placeholder="Select a date..." required>
                                    </div>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label" for="program_type">3. Program Type / Priority</label>
                                    <select name="program_type" id="program_type" class="form-select" required>
                                        <option value="" disabled selected>-- Select Type --</option>
                                        <option value="3">Academic Project/Class</option>
                                        <option value="2">Club/Association Program</option>
                                        <option value="1">Official University Ceremony</option>
                                    </select>
                                    <div id="priorityWarning" class="alert alert-warning py-1 mt-2 small" style="display:none;">
									⚠️ <strong>Important:</strong> This request is only valid for this type of program. If you are ordering an item for <strong>Different Priority</strong>, please complete this request first and create a New Request.                                    </div>
                                </div>

                                <div class="mb-1">
                                    <label class="form-label" for="reason">4. Purpose of Loan</label>
                                    <textarea id="reason" name="reason" class="form-control" placeholder="e.g., For Final Year Project presentation or Club event" required></textarea>
                                </div>

                                <div class="d-grid mt-4">
                                    <button type="button" class="btn btn-primary" id="confirmContextBtn">
                                        <i class="fa-solid fa-lock me-2"></i> CONFIRM & LOCK CONTEXT
                                    </button>
                                </div>
                            </div>
                        </div>

<div class="tab-pane fade" id="items-tab-pane" role="tabpanel" aria-labelledby="items-tab" tabindex="0">
    <div class="card" id="ItemSelectionCard">
        <h5><i class="fa-solid fa-list-check me-2 text-primary"></i> <strong>Step 2: Select Items & Check Qty</strong> </h5>
        <p class="text-muted small">Choose the items needed for the dates and purpose confirmed in Step 1.</p>
        <hr>
        
        <div class="row">
            <div class="col-md-8 mb-3">
                <label class="form-label">1. Select Item</label>
                <select id="item_select" class="form-select" style="width: 100%;">
                    <option value="">-- Search and select an item --</option>
                </select>
            </div>

            <div class="col-md-4 mb-3"> 
                <label class="form-label" for="quantity">2. Quantity</label>
                <input type="number" id="quantity" class="form-control" name="quantity" min="1" value="1">
            </div>
        </div>
        <div class="row mb-3">
            <div class="col-md-12" id="availability-status">
                </div>
        </div>

        <div class="d-grid d-md-flex gap-2 mt-4">
            <button type="button" class="btn btn-primary flex-grow-1" id="addMoreBtn" disabled><i class="fa-solid fa-cart-plus me-2"></i> <strong>Add Item to Request List</strong></button>
        </div>

    </div>
</div>
                        <div class="tab-pane fade" id="review-tab-pane" role="tabpanel" aria-labelledby="review-tab" tabindex="0">
                            <div class="card">
                                <h5><i class="fa-solid fa-clipboard-list me-2 text-primary"></i> <strong>Step 3: Review & Submit (1 Submission)</strong> </h5>
                                
                                <div id="contextSummary"></div>
                                
                                <hr>
                                
                                <div id="itemsList">
                                    <div class="text-center text-muted p-4"><i class="fa-solid fa-list-check fa-2x mb-2"></i><p class="mb-0">Your request list is currently empty.</p></div>
                                </div>
                                
                                <hr>
                                
                                <div class="form-check mt-3">
                                    <input class="form-check-input" type="checkbox" value="" id="agreeTerms" disabled>
                                    <label class="form-check-label" for="agreeTerms">
                                        I have read and agree to the 
                                        <a href="#" data-bs-toggle="modal" data-bs-target="#termsModal" class="text-primary fw-bold">Terms and Conditions</a>.
                                    </label>
                                </div>

                                <div class="d-grid mt-4">
                                    <button type="button" class="btn btn-primary" id="finalSubmitBtn" disabled><i class="fa-solid fa-paper-plane me-2"></i> <strong>Submit Final Request</strong></button>
                                </div>

                                <div class="d-grid mt-3">
                                    <button type="button" class="btn btn-outline-secondary" id="backToItemsBtn">
                                        <i class="fa-solid fa-arrow-left me-2"></i> Back to Add Items (Step 2)
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
            
        </div>
    </div>
</div>

<div class="modal fade" id="termsModal" tabindex="-1" aria-labelledby="termsModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
        <div class="modal-header">
            <h5 class="modal-title" id="termsModalLabel">Terms and Conditions of <strong>Equipment Usage</strong></h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
            <p>Please read the following terms carefully before submitting your <strong>reservation</strong> request:</p>
            <ol>
                <li>
                    <strong>Eligibility:</strong> All equipment is available for <strong>reservation</strong> only to registered students and staff of UniKL with a valid ID.
                </li>
                <li>
                    **Reservation Duration:** The **duration** of the reservation is as specified in your request (i.e., from the Collection Date to the Return Date).
                </li>
                <li>
                    **Responsibility:** The party making the reservation is fully responsible for the **reserved equipment** from the moment of collection until they are returned and checked in by a technician.
                </li>
                <li>
                    **Condition of Items:** The reserving party must inspect the item(s) at the time of collection. Any existing damage must be reported immediately, or the reserving party may be held responsible.
                </li>
                <li>
                    **Damage or Loss:** The reserving party will be held financially responsible for the full replacement cost of any lost, stolen, or damaged items (including all parts and accessories).
                </li>
                <li>
                    **Late Returns:** Failure to return items by the specified return date will result in a fine (e.g. RM10 per item per day) and a temporary suspension of **reservation** privileges.
                </li>
                <li>
                    **Purpose of Use:** Items are to be used for academic or official university purposes only, as specified in the reservation form.
                </li>
                <li>
                    **Collection:** Approved items must be collected within 24 hours of the "Approved" status being issued, or the reservation may be cancelled.
                </li>
            </ol>
            <p class="fw-bold">By checking the box, you acknowledge that you have read, understood, and agree to be bound by all the terms and conditions stated above.</p>
        </div> 
        <div class="modal-footer">
            <button type="button" class="btn btn-primary" id="agreeTermsBtn" data-bs-dismiss="modal">I Understand</button>
        </div>
        </div>
    </div>
</div>


<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

<script>

// Dapatkan data item dari PHP dan simpan dalam pembolehubah global JS
const ALL_ITEMS_DATA = <?= json_encode($items_for_dropdown) ?>;
// Dapatkan ID Pengguna untuk penghantaran borang
const phpData = { user_id: <?= isset($person_id) ? $person_id : 'null' ?> };


$(document).ready(function() {

    // =========================================================
    // 1. Sidebar Toggle Logic for Sub-Menu & Mobile
    // =========================================================
    const itemAvailabilityToggle = $('#itemAvailabilityToggle');
    const categorySubmenu = $('#categorySubmenu');
    const sidebar = document.querySelector('.sidebar');
    const overlay = document.getElementById('overlay');
    const menuToggle = document.getElementById('menuToggle');
    const priorityWarning = $('#priorityWarning'); 

    // Toggle Sub-Menu Kategori
    itemAvailabilityToggle.on('click', function(e) {
        e.preventDefault();
        categorySubmenu.toggleClass('show');
        $(this).find('i.fa-angle-down').toggleClass('collapsed');
    });

    // Toggle Sidebar Mobile
    if (menuToggle) {
        menuToggle.addEventListener('click', function() {
            sidebar.classList.toggle('active');
            overlay.style.display = sidebar.classList.contains('active') ? 'block' : 'none';
        });
    }

    if (overlay) { 
        overlay.addEventListener('click', function() {
            sidebar.classList.remove('active');
            overlay.style.display = 'none'; 
        }); 
    }
    
    // =========================================================
    // 2. Select2 Initialization & Helper: Populate Item Dropdown
    // =========================================================
    $('#item_select').select2({
        theme: "bootstrap-5",
        dropdownParent: $('#item_select').parent(),
        placeholder: "Search and select an item"
    });

    function populateItemSelect(items) {
        const $itemSelect = $('#item_select');
        $itemSelect.empty();
        $itemSelect.append(new Option('-- Search and select an item --', '', true, true));

        let currentCategory = null;
        let $optgroup = null;

        items.sort((a, b) => a.category_name.localeCompare(b.category_name));

        items.forEach(item => {
            if (item.category_name !== currentCategory) {
                if ($optgroup) {
                    $itemSelect.append($optgroup);
                }
                currentCategory = item.category_name;
                $optgroup = $('<optgroup label="' + item.category_name + '">'); 
            }
            
            const $option = $('<option>')
                                     .val(item.item_name)
                                     .text(item.item_name)
                                     .attr('data-category-id', item.category_id);
            
            $optgroup.append($option);
        });
        
        if ($optgroup) {
            $itemSelect.append($optgroup);
        }
    }

    // =========================================================
    // 3. Helper: Update Item Display List (Kanan)
    // =========================================================
    function updateItemDisplayList(categoryName) {
        const $listContainer = $('#displayItemList'); 
        $listContainer.empty();
        
        let itemsToDisplay = categoryName 
            ? ALL_ITEMS_DATA.filter(item => item.category_name === categoryName)
            : ALL_ITEMS_DATA;

        if (itemsToDisplay.length === 0) {
             $listContainer.html('<div class="text-center text-muted p-3">No items found in this category.</div>');
             return;
        }

        itemsToDisplay.forEach(item => {
            const imageUrl = item.image_url && item.image_url.trim() !== '' ? item.image_url : '../assets/placeholder.png';
            
            const listItem = `
                <div class="list-group-item d-flex align-items-center p-2">
                    <div class="category-image-box"> 
                        <img src="${imageUrl}" 
                            alt="${item.item_name}" 
                            class="category-thumb-img">
                    </div>
                    <div>
                        <strong>${item.item_name}</strong><br>
                        <small class="text-muted">${item.category_name}</small>
                    </div>
                </div>
            `;
            $listContainer.append(listItem);
        });
    }

    // Panggil pada permulaan untuk memuatkan semua item
    populateItemSelect(ALL_ITEMS_DATA);
    updateItemDisplayList('');

    // =========================================================
    // 4. EVENT HANDLER: Category Sidebar Link Click (Filtering)
    // =========================================================
    $(document).on('click', '.category-filter-link', function(e) {
        e.preventDefault();
        const selectedCategory = $(this).data('category');
        
        // Set active class
        $('.category-filter-link').removeClass('active-category-filter');
        $(this).addClass('active-category-filter');

        // Laksanakan penapisan
        populateItemSelect(selectedCategory ? ALL_ITEMS_DATA.filter(item => item.category_name === selectedCategory) : ALL_ITEMS_DATA);
        updateItemDisplayList(selectedCategory);
        $('#item_select').val(null).trigger('change'); // Reset item selection
        
        // Untuk mobile: tutup sidebar selepas klik
        if (window.innerWidth <= 992) {
            $('.sidebar').removeClass('active');
            $('#overlay').hide();
        }
    });

    // =========================================================
    // 5. Availability Check Logic & Flatpickr
    // =========================================================
    
    let debounceTimer;

    function checkAvailability() {
        clearTimeout(debounceTimer);
        debounceTimer = setTimeout(() => {
            // Ambil data kontekstual (Tarikh/Tujuan)
            const reserve = $('#reserveDate').val();
            const ret = $('#returnDate').val();
            
            // Ambil data item (Item/Kuantiti)
            const itemName = $('#item_select').val();
            const quantity = $('#quantity').val();
            
            const statusDiv = $('#availability-status');
            const addBtn = $('#addMoreBtn');

            // Semak Ketersediaan HANYA jika semua input item utama diisi DAN kontek telah dikunci
            if (itemName && quantity > 0 && $('#confirmContextBtn').hasClass('btn-success')) {
                
                statusDiv.html('<div class="text-muted"><span class="spinner-border spinner-border-sm"></span> Checking availability...</div>');
                addBtn.prop('disabled', true);

                $.ajax({
                    type: 'POST',
                    url: 'check_availability.php',
                    data: { 
                        item_name: itemName,
                        quantity: quantity,
                        start_date: reserve, // Tarikh diambil dari field yang dikunci
                        end_date: ret        // Tarikh diambil dari field yang dikunci
                    },
                    dataType: 'json',
                    success: function(response) {
                        if (response.status === 'success') {
                            statusDiv.html('<div class="alert alert-success py-2">✅ <strong>Available!</strong> You can <strong>add this item</strong> to your request list.</div>');
                            addBtn.prop('disabled', false);

                        } else if (response.status === 'partial') {
                            let suggestionHTML = `
                                <div class="alert alert-warning py-2">
                                    <strong>Suggestion:</strong> ${response.message}
                                    <button type="button" class="btn btn-sm btn-primary ms-2" id="book-available-btn" data-available="${response.available_count}">
                                        Adjust to ${response.available_count} unit(s)?
                                    </button>
                                </div>`;
                            statusDiv.html(suggestionHTML);
                            addBtn.prop('disabled', response.available_count <= 0); 

                        } else { 
                            statusDiv.html(`<div class="alert alert-danger py-2">❌ <strong>Not Available:</strong> ${response.message}</div>`);
                            addBtn.prop('disabled', true);
                        }
                    },
                    error: function() { 
                        statusDiv.html('<div class="alert alert-danger py-2">❌ <strong>Error:</strong> Could not connect to the server.</div>');
                        addBtn.prop('disabled', true);
                    }
                });
            } else if (!$('#confirmContextBtn').hasClass('btn-success')) {
                 // Abaikan checkAvailability jika Context belum dikunci
                 statusDiv.html('<div class="alert alert-info py-2 small">First, click CONFIRM & LOCK Context (Step 1).</div>');
                 addBtn.prop('disabled', true);
            } else {
                statusDiv.html('');
                addBtn.prop('disabled', true); 
            }
        }, 500); 
    }
    
    // Listener untuk butang Adjust Quantity
    $(document).on('click', '#book-available-btn', function() {
        const availableCount = $(this).data('available');
        $('#quantity').val(availableCount); 
        checkAvailability(); 
    });

    // Pemicu Availability Check
    $('#item_select, #quantity').on('input change', checkAvailability); 
    
    // Flatpickr
    const returnDatepicker = flatpickr("#returnDate", {
        dateFormat: "Y-m-d",
        minDate: "today"
    });

    const reserveDatepicker = flatpickr("#reserveDate", {
        dateFormat: "Y-m-d",
        minDate: "today",
        onChange: (selectedDates) => {
            if (selectedDates.length > 0) {
                returnDatepicker.set('minDate', selectedDates[0]);
                // Clear return date if it's before the new reserve date
                if ($('#returnDate').val() && new Date($('#returnDate').val()) < selectedDates[0]) {
                    $('#returnDate').val('');
                }
            }
        }
    });

    // Tunjuk/Sembunyi Peringatan Priority
    $('#program_type').on('change', function() {
        if ($(this).val()) {
            priorityWarning.slideDown();
        } else {
            priorityWarning.slideUp();
        }
    });
    
    // =========================================================
    // 6. Context Locking Logic (NEW) & Tab Navigation Control
    // =========================================================
    
    const contextTabLink = $('#context-tab');
    const itemsTabLink = $('#items-tab');
    const reviewTabLink = $('#review-tab');
    const confirmContextBtn = $('#confirmContextBtn');
    const goToReviewBtn = $('#goToReviewBtn');
    const backToItemsBtn = $('#backToItemsBtn');

    // Handler Navigasi Tab
    $('#myTab button').on('click', function (e) {
        if ($(this).hasClass('disabled')) {
            e.preventDefault();
            e.stopPropagation();
            if ($(this).is(itemsTabLink)) {
                Swal.fire("Incomplete Step", "Please complete and <strong>Confirm Context</strong> in Step 1 first.", "warning");
            } else if ($(this).is(reviewTabLink)) {
                Swal.fire("Incomplete Step", "Please add at least one item in Step 2 before reviewing.", "warning");
            }
        }
    });

    // Butang NEXT: Confirm Context (Step 1 -> Step 2)
    confirmContextBtn.on('click', function() {
        const reserve = $('#reserveDate').val();
        const ret = $('#returnDate').val();
        const reason = $('#reason').val();
        const program_type = $('#program_type').val();

        if (!reserve || !ret || !reason.trim() || !program_type) {
            Swal.fire("Incomplete Context", "Please fill in all dates and purpose details before confirming.", "warning");
            return;
        }
        
        // 1. Kunci semua medan Konteks
        $('#ContextCard').find('input, select, textarea').prop('disabled', true);
        $(this).prop('disabled', true).removeClass('btn-primary').addClass('btn-success')
               .html('<i class="fa-solid fa-check me-2"></i> CONTEXT CONFIRMED');
        priorityWarning.hide(); 
        
        // 2. Buka Kunci Tab Item Selection
        itemsTabLink.removeClass('disabled');
        
        // 3. Pindah ke Tab 2
        bootstrap.Tab.getInstance(itemsTabLink[0]).show();
        
        // 4. Laksanakan checkAvailability jika ada item yang dipilih
        checkAvailability(); 
    });
    
    // Butang NEXT: Go to Review (Step 2 -> Step 3)
    goToReviewBtn.on('click', function() {
        if (reservationItems.length === 0) {
            Swal.fire("Empty List", "Please add at least one item before going to review.", "warning");
            return;
        }
        reviewTabLink.removeClass('disabled');
        renderItemsList(); // Pastikan senarai di tab review terkini
        bootstrap.Tab.getInstance(reviewTabLink[0]).show();
    });

    // Butang BACK: Back to Items (Step 3 -> Step 2)
    backToItemsBtn.on('click', function() {
        bootstrap.Tab.getInstance(itemsTabLink[0]).show();
    });


    // =========================================================
    // 7. Request List & Submission Logic 
    // =========================================================
    let reservationItems = []; 
    
    // Handler Butang Add Item
    $('#addMoreBtn').on('click', () => {
        // Semak status Lock
        if (!confirmContextBtn.hasClass('btn-success')) {
             Swal.fire("Context Not Locked", "Please confirm and lock the Dates/Purpose in Step 1 first.", "error");
             return;
        }

        const itemName = $('#item_select').val();
        const quantity = $('#quantity').val();
        
        if (!itemName || quantity <= 0) {
             Swal.fire("Incomplete Item Details", "Please select an Item and Quantity.", "warning");
             return;
        }
        
        if ($('#availability-status').find('.alert-success').length === 0 && $('#addMoreBtn').prop('disabled')) {
             Swal.fire("Not Confirmed", "Please ensure the item's availability is confirmed before adding it to the list.", "error");
             return;
        }

        if (reservationItems.some(item => item.item_name === itemName)) {
             Swal.fire("Duplicate Item", `Item ${itemName} is already in your request list.`, "info");
             return;
        }
        
        // Ambil data kontekstual dari medan yang sudah 'disabled'
        const newItem = { 
            item_name: itemName, 
            quantity: quantity, 
            reserve_date: $('#reserveDate').val(), 
            return_date: $('#returnDate').val(), 
            reason: $('#reason').val(), 
            program_type: $('#program_type').val()
        };
        
        reservationItems.push(newItem);
        renderItemsList(); 

        // Notifikasi Toast
        const Toast = Swal.mixin({
            toast: true, position: 'top-end', showConfirmButton: false, timer: 3000, timerProgressBar: true
        });
        Toast.fire({ icon: 'success', title: `Item ${itemName} added to your request!` });

        
        // Reset Item Selection untuk item seterusnya
        $('#item_select').val(null).trigger('change');
        $('#quantity').val(1);
        $('#availability-status').html('');
        goToReviewBtn.prop('disabled', false); // Aktifkan butang review
    });

    
    function renderItemsList() {
        const listDiv = $('#itemsList');
        const contextSummaryDiv = $('#contextSummary');
        const submitBtn = $('#finalSubmitBtn');
        const agreeTermsCheckbox = $('#agreeTerms');
        
        if (reservationItems.length > 0) {
            // Dapatkan Konteks (Item pertama dalam senarai)
            const contextItem = reservationItems[0];
            let programTypeText = '';
            if (contextItem.program_type === '3') programTypeText = 'Academic Project/Class';
            if (contextItem.program_type === '2') programTypeText = 'Club/Association Program';
            if (contextItem.program_type === '1') programTypeText = 'Official University Ceremony';
            
            let contextHtml = `
                <div class="alert alert-info py-2 small mb-3">
                    <i class="fa-solid fa-calendar-check me-2"></i> <strong>Submission Context:</strong><br>
                    Date: ${contextItem.reserve_date} to ${contextItem.return_date}<br>
                    Purpose: ${contextItem.reason} (${programTypeText})
                    <hr class="my-1">
                    <button type="button" class="btn btn-sm btn-outline-danger" id="unlockContextBtn"><i class="fa-solid fa-unlock me-1"></i> Change Context</button>
                </div>
            `;
            
            let itemsHtml = reservationItems.map((it, i) => {
                return `
                <div class="d-flex justify-content-between align-items-center bg-white p-3 rounded mb-2 border">
                    <div>
                        <b>${it.item_name}</b> <span class="badge bg-primary">${it.quantity} unit(s)</span>
                    </div>
                    <button type="button" class="btn btn-sm btn-outline-danger ms-2 remove-item-btn" data-index="${i}"><i class="fa fa-trash-alt"></i> Remove</button>
                </div>
                `;
            }).join('');
            
            contextSummaryDiv.html(contextHtml);
            listDiv.html(`<div class="p-2 border rounded bg-light">${itemsHtml}</div>`);
            
            // Logik untuk Tab Review
            // agreeTermsCheckbox.prop('disabled', false); // JANGAN ubah disabled di sini. Kawal melalui modal sahaja.
            submitBtn.prop('disabled', !agreeTermsCheckbox.is(':checked'));
            reviewTabLink.removeClass('disabled');
            goToReviewBtn.prop('disabled', false); 

        } else {
            // Jika senarai kosong
            contextSummaryDiv.html('');
            listDiv.html(`<div class="text-center text-muted p-4"><i class="fa-solid fa-list-check fa-2x mb-2"></i><p>Your request list is currently empty.</p></div>`);
            
            agreeTermsCheckbox.prop('disabled', true).prop('checked', false); 
            submitBtn.prop('disabled', true);
            reviewTabLink.addClass('disabled'); // Kunci Tab Review
            goToReviewBtn.prop('disabled', true);
            
            // Jika tiada item, kita unlock Context secara automatik
            unlockContext(); 
        }

        // Hantar data penuh ke input tersembunyi
        $('#allItems').val(JSON.stringify(reservationItems));
    }
    
    // Handler Butang Unlock Context (di Tab Review)
    $(document).on('click', '#unlockContextBtn', function() {
        Swal.fire({
            title: 'Confirm Change Context?',
            text: "Changing the context (dates/purpose) will EMPTY your current request list and return you to Step 1.",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#dc3545',
            cancelButtonColor: '#6c757d',
            confirmButtonText: 'Yes, change it!'
        }).then((result) => {
            if (result.isConfirmed) {
                reservationItems = [];
                renderItemsList();
                // Alih ke Tab 1 (Context)
                bootstrap.Tab.getInstance(contextTabLink[0]).show();
                // Logik unlockContext akan dipanggil oleh renderItemsList()
                Swal.fire('Context Unlocked', 'You may now change the dates/purpose in Step 1.', 'info');
            }
        });
    });

    function unlockContext() {
         // Unlock Medan Input Step 1
         $('#ContextCard').find('input, select, textarea').prop('disabled', false);
         confirmContextBtn.prop('disabled', false).removeClass('btn-success').addClass('btn-primary')
                .html('<i class="fa-solid fa-lock me-2"></i> <strong>CONFIRM & LOCK CONTEXT</strong>');
         
         // Kunci Tab 2 & 3
         itemsTabLink.addClass('disabled');
         reviewTabLink.addClass('disabled');
         
         // Reset item selection
         $('#availability-status').html('');
         $('#item_select').val(null).trigger('change');
         $('#quantity').val(1);
         $('#addMoreBtn').prop('disabled', true);
         
         // Reset T&C
         $('#agreeTerms').prop('disabled', true).prop('checked', false);
         
         // Tunjuk amaran semula
         if ($('#program_type').val()) {
            priorityWarning.show();
         }
    }

    // Handler Butang Remove Item (di Tab Review)
    $('#itemsList').on('click', '.remove-item-btn', function() {
        const index = $(this).data('index');
        const itemName = reservationItems[index].item_name;
        
        reservationItems.splice(index, 1); 
        renderItemsList(); 
        checkAvailability(); 

        // Alihkan pengguna kembali ke Step 2 jika list kosong
        if (reservationItems.length === 0) {
             bootstrap.Tab.getInstance(itemsTabLink[0]).show();
        }
        
        const Toast = Swal.mixin({
            toast: true, position: 'top-end', showConfirmButton: false, timer: 3000, timerProgressBar: true
        });
        Toast.fire({ icon: 'error', title: `Item ${itemName} removed from list.` });
    });
    
    // Handler Checkbox Terms (Hanya kawal butang Submit)
    $('#agreeTerms').on('change', function() {
        const submitBtn = $('#finalSubmitBtn');
        // Hanya dayakan butang Submit jika kotak semak dicentang DAN ada item dalam senarai.
        if ($(this).is(':checked') && reservationItems.length > 0) {
            submitBtn.prop('disabled', false);
        } else {
            submitBtn.prop('disabled', true);
        }
    });

    // Handler Butang I Understand (Modal Terms) 🔑 LOGIK TERKINI DI SINI
    $('#agreeTermsBtn').on('click', function() {
        // 1. Tetapkan kotak semak kepada 'checked'
        $('#agreeTerms').prop('checked', true);
        
        // 2. Buang status 'disabled' dari kotak semak
        // Ini membolehkan pengguna menyemak atau membatalkan semak secara manual kemudian.
        $('#agreeTerms').prop('disabled', false); 
        
        // 3. Panggil event 'change' untuk mengaktifkan butang Submit jika senarai item sudah penuh.
        $('#agreeTerms').trigger('change');

        // Notifikasi visual (pilihan)
        const Toast = Swal.mixin({
            toast: true, position: 'top-end', showConfirmButton: false, timer: 3000, timerProgressBar: true
        });
        Toast.fire({ icon: 'info', title: 'Terms agreed. Checkbox unlocked.' });
    });

    
    // Handler Final Submit (Kekal sama)
    $('#finalSubmitBtn').on('click', function (e) {
        e.preventDefault();
        const submitBtn = $(this);
        
        if (reservationItems.length === 0) {
            Swal.fire("Empty List", "Please add at least one item before submitting.", "error");
            return;
        }

        if (!$('#agreeTerms').is(':checked')) {
            Swal.fire("Terms and Conditions", "You must agree to the Terms and Conditions to proceed.", "warning");
            return; 
        }
        
        const contextItem = reservationItems[0];
        
        submitBtn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm"></span> Submitting...');
        
        const submissionData = {
            user_id: phpData.user_id,
            all_items: JSON.stringify(reservationItems),
            program_type: contextItem.program_type, 
            reason: contextItem.reason 
        };
        
        $.ajax({
            type: 'POST',
            url: 'submit_reservation.php', 
            data: submissionData, 
            dataType: 'json',
            success: function(response) {
                if(response.status === 'success') {
                    Swal.fire({
                        title: 'Success!',
                        text: 'Your consolidated request has been submitted. Redirecting to history...',
                        icon: 'success',
                        timer: 2000,
                        timerProgressBar: true,
                        showConfirmButton: false
                    }).then(() => {
                        window.location.href = 'history.php'; 
                    });
                } else {
                    
                    Swal.fire("Submission Failed", response.message, "error");
                }
            },
            error: function() {
                
                Swal.fire("Submission Failed", "A server error occurred. Please try again.", "error");
            },
            complete: function() {
                
                const shouldBeEnabled = reservationItems.length > 0 && $('#agreeTerms').is(':checked');
                submitBtn.prop('disabled', !shouldBeEnabled).html('<i class="fa-solid fa-paper-plane me-2"></i> **Submit Final Request**');
            }
        });
    });
    
    // Pastikan senarai dikemas kini pada pemuatan awal
    renderItemsList();
});
</script>
</body>
</html>