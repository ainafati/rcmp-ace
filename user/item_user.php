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
    
    /* Item List Display - Imej (DIPERBAIKI) */
    .category-image-box { 
        width: 80px; /* DIBESARKAN DARI 50px */
        height: 80px; /* DIBESARKAN DARI 50px */
        border-radius: 8px; 
        display: flex; 
        align-items: center; 
        justify-content: center; 
        overflow: hidden; 
        border: 1px solid #e2e8f0;
        margin-right: 15px; /* DITAMBAH MARGIN */
        flex-shrink: 0; 
    } 
    .category-thumb-img {
        width: 100%;
        height: 100%;
        object-fit: cover; 
    }

    /* Padding untuk setiap item dalam senarai (DIPERBAIKI) */
    /* Ini memastikan elemen 'a' mempunyai padding yang cukup */
    .list-group-item {
        padding-top: 15px !important; 
        padding-bottom: 15px !important;
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
// Data store for all items fetched from PHP
const ALL_ITEMS_DATA = <?= json_encode($items_for_dropdown) ?>;

// Variables to hold the current reservation context
let RESERVATION_CONTEXT = {};
// Array to hold items the user is requesting
let REQUEST_ITEMS = [];

$(document).ready(function() {
    
    // --- 1. Initial Setup and Handlers ---

    // Initialize Flatpickr for Date Selection
    flatpickr("#reserveDate", {
        dateFormat: "Y-m-d",
        minDate: "today",
        altInput: true,
        altFormat: "F j, Y",
        onChange: function(selectedDates, dateStr, instance) {
            // Update the minimum date for the return date picker
            returnDatePicker.set('minDate', dateStr || new Date());
            
            // Re-check item availability when dates change
            if ($('#items-tab').hasClass('active')) {
                checkItemAvailability();
            }
        }
    });

    const returnDatePicker = flatpickr("#returnDate", {
        dateFormat: "Y-m-d",
        minDate: "today", // Initial minDate
        altInput: true,
        altFormat: "F j, Y",
        onChange: function(selectedDates, dateStr, instance) {
            // Re-check item availability when dates change
            if ($('#items-tab').hasClass('active')) {
                checkItemAvailability();
            }
        }
    });

    // Initialize Select2 for Item Selection (Step 2)
    $('#item_select').select2({
        theme: 'bootstrap-5',
        placeholder: 'Search and select an item...',
        dropdownParent: $('#ItemSelectionCard'), // Fix for modal/card overflow
        data: ALL_ITEMS_DATA.map(item => ({
            id: item.item_id,
            text: `${item.item_name} (${item.category_name})`,
            itemData: item // Store the full item object for easy lookup
        }))
    });
    
    // Filter the dropdown items based on the selected category in the sidebar
    function filterSelect2ByCategoryId(categoryId) {
        const filteredData = ALL_ITEMS_DATA
            .filter(item => categoryId === '' || item.category_id === categoryId)
            .map(item => ({
                id: item.item_id,
                text: `${item.item_name} (${item.category_name})`,
                itemData: item
            }));

        // Reset and populate Select2 with filtered data
        $('#item_select').empty().append(new Option('-- Search and select an item --', '')).select2({
            theme: 'bootstrap-5',
            placeholder: 'Search and select an item...',
            dropdownParent: $('#ItemSelectionCard'),
            data: filteredData
        });

        // Clear availability status when the filter changes
        $('#availability-status').html('');
        $('#addMoreBtn').prop('disabled', true);
        $('#quantity').val(1);
    }

    // Set up sidebar category filtering (on the right-hand list and the Select2 dropdown)
    $('.category-filter-link').on('click', function(e) {
        e.preventDefault();
        
        // Update sidebar active class
        $('.category-filter-link').removeClass('active-category-filter');
        $(this).addClass('active-category-filter');
        
        // Get the category name for filtering the list
        const categoryName = $(this).data('category');
        
        // Get the category ID (or empty string for 'All Items')
        const categoryId = ALL_ITEMS_DATA.find(item => item.category_name === categoryName)?.category_id || '';

        // 1. Filter the right-hand side item list (displayItemList)
        filterItemList(categoryName);
        
        // 2. Filter the Step 2 item Select2 dropdown
        filterSelect2ByCategoryId(categoryId);
    });
    
    // Initial load: show all items on the right-hand list and in Select2
    filterItemList('');
    
    // Toggle Category Submenu
    $('#itemAvailabilityToggle').on('click', function(e) {
        e.preventDefault();
        $('#categorySubmenu').toggleClass('show');
        $(this).toggleClass('collapsed');
    });

    // Mobile menu toggle
    $('#menuToggle').on('click', function() {
        $('.sidebar').toggleClass('active');
        $('#overlay').toggle();
    });

    $('#overlay').on('click', function() {
        $('.sidebar').removeClass('active');
        $('#overlay').hide();
    });
    
    // Handle 'I Understand' button click in Terms Modal
    $('#agreeTermsBtn').on('click', function() {
        $('#agreeTerms').prop('checked', true).trigger('change');
    });


    // --- 2. Step 1: Context Handling ---

    // Show Priority Warning based on selection
    $('#program_type').on('change', function() {
        if ($(this).val()) {
            $('#priorityWarning').slideDown(200);
        } else {
            $('#priorityWarning').slideUp(200);
        }
    });

    // Confirm Context (Move to Step 2)
    $('#confirmContextBtn').on('click', function() {
        if (checkContextInputs()) {
            // Collect and store context data
            RESERVATION_CONTEXT = {
                reserve_date: $('#reserveDate').val(),
                return_date: $('#returnDate').val(),
                program_type: $('#program_type').val(),
                program_type_text: $('#program_type option:selected').text().trim(),
                reason: $('#reason').val(),
                person_id: $('input[name="person_id"]').val()
            };

            // Lock context fields (visually disabled/readonly)
            $('#ContextCard').find('input, select, textarea, button').not('#confirmContextBtn').prop('disabled', true).css('opacity', 0.6);
            $('#confirmContextBtn').html('<i class="fa-solid fa-check-double me-2"></i> CONTEXT IS LOCKED').removeClass('btn-primary').addClass('btn-success').prop('disabled', true);
            
            // Enable Step 2 tab and switch to it
            $('#items-tab').removeClass('disabled');
            new bootstrap.Tab(document.getElementById('items-tab')).show();
            
            // Immediately check availability for the initially selected item/quantity (if any)
            checkItemAvailability();
        } else {
            Swal.fire('Incomplete Context', 'Please fill in all the required fields (Dates, Program Type, and Purpose) in Step 1.', 'warning');
        }
    });
    
    // Validation check for Step 1 fields
    function checkContextInputs() {
        return $('#reserveDate').val() && 
               $('#returnDate').val() && 
               $('#program_type').val() && 
               $('#reason').val();
    }


    // --- 3. Step 2: Item Selection and Availability ---

    // Availability check triggered by item change or quantity change
    $('#item_select, #quantity').on('change input', function() {
        // Only run availability check if context is locked
        if ($('#confirmContextBtn').is(':disabled')) {
            checkItemAvailability();
        }
    });

    // Function to format and display item availability status
    function displayAvailability(availableQty, requestedQty, itemName) {
        const statusDiv = $('#availability-status');
        statusDiv.empty();
        
        if (availableQty === null) {
            // No item selected
            statusDiv.append('<div class="alert alert-secondary py-2 small"><i class="fa-solid fa-circle-info me-2"></i> Select an item and quantity above.</div>');
            $('#addMoreBtn').prop('disabled', true);
            return;
        }

        if (availableQty >= requestedQty) {
            // Available
            statusDiv.append(`
                <div class="alert alert-success py-2 small">
                    <i class="fa-solid fa-check-circle me-2"></i> 
                    ${itemName}: <strong>${requestedQty} unit(s) is AVAILABLE</strong> from ${availableQty} total unit(s).
                </div>
            `);
            $('#addMoreBtn').prop('disabled', false);
        } else if (availableQty > 0) {
            // Partially available
            statusDiv.append(`
                <div class="alert alert-warning py-2 small">
                    <i class="fa-solid fa-triangle-exclamation me-2"></i> 
                    ${itemName}: Only <strong>${availableQty} unit(s) are AVAILABLE</strong>. Requested: ${requestedQty}.
                </div>
            `);
            // Disable button if not enough, but still allow if the user accepts the partial quantity by reducing the input
            if (requestedQty > availableQty) {
                $('#addMoreBtn').prop('disabled', true);
                statusDiv.append('<p class="small text-danger mb-0 mt-1">Please reduce the quantity to match the available stock.</p>');
            } else {
                $('#addMoreBtn').prop('disabled', false);
            }
        } else {
            // Not available
            statusDiv.append(`
                <div class="alert alert-danger py-2 small">
                    <i class="fa-solid fa-ban me-2"></i> 
                    ${itemName}: <strong>0 unit(s) AVAILABLE</strong> for the selected dates.
                </div>
            `);
            $('#addMoreBtn').prop('disabled', true);
        }
    }

    // AJAX call to check item availability
    function checkItemAvailability() {
        const item_id = $('#item_select').val();
        const quantity = parseInt($('#quantity').val());

        if (!item_id || isNaN(quantity) || quantity <= 0) {
            displayAvailability(null); // Show "Select an item" message
            return;
        }

        // Find the full item data
        const selectedItem = ALL_ITEMS_DATA.find(item => item.item_id == item_id);
        const itemName = selectedItem ? selectedItem.item_name : 'Selected Item';
        
        // Temporarily disable the button while checking
        $('#addMoreBtn').prop('disabled', true).html('<i class="fa-solid fa-spinner fa-spin me-2"></i> Checking Availability...');

        $.ajax({
            url: 'check_availability.php', // You must create this file
            type: 'POST',
            dataType: 'json',
            data: {
                item_id: item_id,
                reserve_date: RESERVATION_CONTEXT.reserve_date,
                return_date: RESERVATION_CONTEXT.return_date,
            },
            success: function(response) {
                if (response.success) {
                    const availableQty = parseInt(response.available_quantity);
                    displayAvailability(availableQty, quantity, itemName);
                } else {
                    $('#availability-status').html('<div class="alert alert-danger py-2 small"><i class="fa-solid fa-exclamation-triangle me-2"></i> Error checking availability.</div>');
                    $('#addMoreBtn').prop('disabled', true);
                }
                $('#addMoreBtn').html('<i class="fa-solid fa-cart-plus me-2"></i> <strong>Add Item to Request List</strong>');
            },
            error: function() {
                $('#availability-status').html('<div class="alert alert-danger py-2 small"><i class="fa-solid fa-exclamation-triangle me-2"></i> Server error. Please try again.</div>');
                $('#addMoreBtn').prop('disabled', true);
                $('#addMoreBtn').html('<i class="fa-solid fa-cart-plus me-2"></i> <strong>Add Item to Request List</strong>');
            }
        });
    }

    // Add Item to Request List
    $('#addMoreBtn').on('click', function() {
        const item_id = $('#item_select').val();
        const quantity = parseInt($('#quantity').val());
        const selectedItem = ALL_ITEMS_DATA.find(item => item.item_id == item_id);

        if (!item_id || isNaN(quantity) || quantity <= 0 || !selectedItem) {
            Swal.fire('Error', 'Please select a valid item and quantity.', 'error');
            return;
        }

        // Check if item is already in the list
        const existingIndex = REQUEST_ITEMS.findIndex(item => item.item_id == item_id);
        
        if (existingIndex !== -1) {
            // Item exists, update quantity
            REQUEST_ITEMS[existingIndex].quantity = quantity;
            Swal.fire('Updated!', `${selectedItem.item_name} quantity updated to ${quantity}.`, 'success');
        } else {
            // Add new item
            REQUEST_ITEMS.push({
                item_id: item_id,
                item_name: selectedItem.item_name,
                category_name: selectedItem.category_name,
                image_url: selectedItem.image_url,
                quantity: quantity
            });
            Swal.fire('Added!', `${selectedItem.item_name} (x${quantity}) added to your request list.`, 'success');
        }

        // Reset form for next item
        $('#item_select').val(null).trigger('change');
        $('#quantity').val(1);
        $('#availability-status').empty();
        $('#addMoreBtn').prop('disabled', true);

        // Enable Review tab and update summary
        updateReviewTab();
    });


    // --- 4. Step 3: Review and Submission ---
    
    // Back to Step 2 button handler
    $('#backToItemsBtn').on('click', function() {
        new bootstrap.Tab(document.getElementById('items-tab')).show();
    });

    // Update the Step 3 tab with context and item list
    function updateReviewTab() {
        // A. Update Context Summary
        const summary = `
            <div class="alert alert-info py-2 small">
                <p class="mb-1"><strong><i class="fa-solid fa-calendar-alt me-1"></i> Loan Period:</strong> ${RESERVATION_CONTEXT.reserve_date} until ${RESERVATION_CONTEXT.return_date}</p>
                <p class="mb-1"><strong><i class="fa-solid fa-users me-1"></i> Program Type:</strong> ${RESERVATION_CONTEXT.program_type_text}</p>
                <p class="mb-0"><strong><i class="fa-solid fa-pen-nib me-1"></i> Purpose:</strong> ${RESERVATION_CONTEXT.reason}</p>
            </div>
        `;
        $('#contextSummary').html(summary);

        // B. Update Items List
        let itemsHtml = '';
        if (REQUEST_ITEMS.length === 0) {
            itemsHtml = '<div class="text-center text-muted p-4"><i class="fa-solid fa-list-check fa-2x mb-2"></i><p class="mb-0">Your request list is currently empty.</p></div>';
            $('#agreeTerms').prop('disabled', true).prop('checked', false).trigger('change');
        } else {
            itemsHtml = '<ul class="list-group list-group-flush">';
            REQUEST_ITEMS.forEach((item, index) => {
                itemsHtml += `
                    <li class="list-group-item d-flex align-items-center justify-content-between">
                        <div class="d-flex align-items-center flex-grow-1">
                            <div class="category-image-box">
							<img src="../assets/default-image.jpg" alt="Default Image">
                            </div>
                            <div>
                                <strong class="d-block">${item.item_name}</strong>
                                <span class="text-muted small">${item.category_name}</span>
                            </div>
                        </div>
                        <div class="d-flex align-items-center">
                            <span class="badge text-bg-primary fs-6 me-3">${item.quantity} unit(s)</span>
                            <button type="button" class="btn btn-outline-danger btn-sm remove-item-btn" data-index="${index}">
                                <i class="fa-solid fa-trash-alt"></i>
                            </button>
                        </div>
                    </li>
                `;
            });
            itemsHtml += '</ul>';
            $('#agreeTerms').prop('disabled', false); // Enable checkbox if list is not empty
        }
        $('#itemsList').html(itemsHtml);
        
        // Update total items hidden input for form submission
        $('input#allItems').val(JSON.stringify(REQUEST_ITEMS));

        // Re-attach removal handlers
        $('.remove-item-btn').on('click', removeItemFromList);
    }
    
    // Remove item from request list
    function removeItemFromList() {
        const indexToRemove = $(this).data('index');
        
        Swal.fire({
            title: 'Confirm Removal',
            text: "Are you sure you want to remove this item from the list?",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#06b6d4',
            cancelButtonColor: '#ef4444',
            confirmButtonText: 'Yes, Remove It!'
        }).then((result) => {
            if (result.isConfirmed) {
                REQUEST_ITEMS.splice(indexToRemove, 1);
                updateReviewTab(); // Re-render the list
                Swal.fire('Removed!', 'The item has been removed.', 'success');
            }
        });
    }

    // Enable/Disable final submit button based on terms checkbox
    $('#agreeTerms').on('change', function() {
        $('#finalSubmitBtn').prop('disabled', !$(this).is(':checked'));
    });

    // Final Submission Handler
    $('#finalSubmitBtn').on('click', function() {
        if (REQUEST_ITEMS.length === 0) {
            Swal.fire('Error', 'Your request list is empty. Please add items in Step 2.', 'error');
            return;
        }
        if (!$('#agreeTerms').is(':checked')) {
            Swal.fire('Error', 'Please read and agree to the Terms and Conditions.', 'error');
            return;
        }

        // Prepare data for final AJAX submission
        const submissionData = {
            ...RESERVATION_CONTEXT,
            items: REQUEST_ITEMS
        };

        // Disable button to prevent double submission
        const $btn = $(this);
        $btn.prop('disabled', true).html('<i class="fa-solid fa-spinner fa-spin me-2"></i> Submitting...');

        $.ajax({
            url: 'submit_reservation.php', // You must create this file
            type: 'POST',
            dataType: 'json',
            contentType: 'application/json', // Tell server we're sending JSON
            data: JSON.stringify(submissionData),
            success: function(response) {
                if (response.success) {
                    Swal.fire({
                        title: 'Success!',
                        text: response.message,
                        icon: 'success'
                    }).then(() => {
                        window.location.href = 'history.php'; // Redirect to history page
                    });
                } else {
                    Swal.fire('Submission Failed', response.message, 'error');
                }
            },
            error: function(xhr, status, error) {
                console.error("Submission Error:", error);
                Swal.fire('Server Error', 'Could not process the submission. Please check the network connection or try again later.', 'error');
            },
            complete: function() {
                // Re-enable button
                $btn.prop('disabled', false).html('<i class="fa-solid fa-paper-plane me-2"></i> <strong>Submit Final Request</strong>');
            }
        });
    });

    // --- 5. Utility Functions ---
    
    // Function to render the right-hand side list of ALL items based on category
    function filterItemList(categoryName) {
        const displayList = $('#displayItemList');
        displayList.empty();
        
        const filteredItems = ALL_ITEMS_DATA.filter(item => 
            categoryName === '' || item.category_name === categoryName
        );

        if (filteredItems.length === 0) {
            displayList.html('<p class="text-center text-muted p-3">No items found in this category.</p>');
            return;
        }

filteredItems.forEach(item => {
    // Tentukan laluan imej yang betul.
    // Menambah '../' untuk naik satu direktori, mengandaikan skrip ini di dalam subfolder (cth: /user/)
    // dan folder 'uploads/' berada di akar projek.
    const imagePath = item.image_url ? `../${item.image_url}` : '../path/to/default-image.jpg';
    
    const itemHtml = `
        <a href="#" class="list-group-item list-group-item-action d-flex align-items-center py-3" onclick="selectItemFromList(${item.item_id}); return false;">
            <div class="category-image-box">
                <img src="${imagePath}" alt="${item.item_name}" class="category-thumb-img">
            </div>
            <div>
                <strong class="d-block">${item.item_name}</strong>
                <span class="text-muted small">${item.category_name}</span>
            </div>
            <i class="fa-solid fa-chevron-right ms-auto text-muted small"></i>
        </a>
    `;
    displayList.append(itemHtml);
});
    }

    // Expose selectItemFromList globally so the onclick handler works
    window.selectItemFromList = function(itemId) {
        $('#item_select').val(itemId).trigger('change');
        // Smooth scroll to the item selection card
        $('html, body').animate({
            scrollTop: $('#ItemSelectionCard').offset().top - 80
        }, 500);
    }
    
    // Initial display update for the item list
    filterItemList(''); 
});
</script>
</body>
</html>