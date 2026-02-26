<?php
session_start();
include '../config.php';

if (!$conn) {
    error_log("Database connection failed: " . mysqli_connect_error());
    die("Server connection error."); 
}

if (!isset($_SESSION['person_id'])) {
    header("Location: ../login.php");
    exit();
}

// Guna satu nama sahaja (person_id)
$person_id = (int) $_SESSION['person_id'];

$stmt = $conn->prepare("SELECT name, email, phoneNum FROM person WHERE person_id = ?");
if ($stmt === false) { die("Database Error (User Info): " . $conn->error); }

// BETULKAN DI SINI: Tukar $user_id kepada $person_id
$stmt->bind_param("i", $person_id); 
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();
$stmt->close();

if (!$user) {
    session_unset();
    session_destroy();
    header("Location: ../login.php");
    exit();
}

// LOGIK NAMA PENDEK (Dah betul)
$fullName = $user['name'] ?? 'Guest User';
$lowerName = strtolower($fullName);

$posBinti = strpos($lowerName, ' binti ');
$posBin = strpos($lowerName, ' bin ');

if ($posBinti !== false) {
    $shortName = substr($fullName, 0, $posBinti);
} elseif ($posBin !== false) {
    $shortName = substr($fullName, 0, $posBin);
} else {
    $shortName = $fullName;
}

$displayName = trim($shortName);

$categories = [];
$res_cat = $conn->query("SELECT category_id, category_name FROM categories ORDER BY category_name");
if ($res_cat) {
    while ($row = $res_cat->fetch_assoc()) {
        $categories[] = $row;
    }
    $res_cat->free();
}
// 1. Tangkap category dari URL (contoh: ?category=Audio+Visual)
$category_filter = isset($_GET['category']) ? $_GET['category'] : '';

$items_for_dropdown = [];

// 2. Bina Query yang boleh tapis
$sql_all_items = "
    SELECT 
        i.item_id, i.item_name, c.category_name, i.category_id, i.image_url 
    FROM item i
    JOIN categories c ON i.category_id = c.category_id
";

// Jika user klik category kat sidebar, kita tambah filter WHERE
if (!empty($category_filter)) {
    $safe_category = $conn->real_escape_string($category_filter);
    $sql_all_items .= " WHERE c.category_name = '$safe_category'";
}

$sql_all_items .= " ORDER BY c.category_name, i.item_name ASC";

$res_items = $conn->query($sql_all_items);
if ($res_items) {
    while ($row = $res_items->fetch_assoc()) {
        $items_for_dropdown[] = $row;
    }
    $res_items->free();
}

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
	    <link rel="stylesheet" href="../css/style.css">

<style>

/* =========================================================================
   2. DESKTOP/LAPTOP VIEW (DEFAULT)
   ========================================================================= */

/* ❌ PAKSA SEMBUNYI KAT LAPTOP - Letak kat luar media query */
.mobile-bottom-nav {
    display: none !important;
    visibility: hidden;
}

.main-content {
    min-height: 100vh;
    background-color: #f1f5f9;
    background-image: url('https://www.transparenttextures.com/patterns/cubes.png');
    background-attachment: fixed;
    padding: 2rem;
    background-repeat: repeat;
}

@media (min-width: 992px) {
    .booking-slide-wrapper {
        display: grid;
        grid-template-columns: 1.4fr 1fr;
        gap: 25px;
    }
}

/* =========================================================================
   3. MOBILE VIEW (MAX-WIDTH: 991px)
   ========================================================================= */
@media (max-width: 991.98px) {
    /* Sorok Sidebar */
    .sidebar, #admin-sidebar, #sidebar-wrapper {
        display: none !important;
    }

    .main-content {
        margin-left: 0 !important;
        padding: 15px 15px 100px 15px !important; 
    }

    /* ✅ MUNCULKAN BOTTOM NAV SEBAGAI APPS BAR (THEMED DARK) */
.mobile-bottom-nav {
    display: flex !important;
    visibility: visible !important;
    position: fixed;
    bottom: 0;
    left: 0;
    right: 0;
    width: 100%;
    /* TUKAR KAT SINI: Guna warna gelap sidebar (#1e293b atau #0f172a) */
    background: #1e293b !important; 
    border-top: 1px solid rgba(255, 255, 255, 0.1); /* Border nipis supaya tak nampak kaku */
    z-index: 10000;
    justify-content: space-around;
    padding: 12px 0; /* Tambah padding sikit bagi nampak luas */
    box-shadow: 0 -5px 25px rgba(0, 0, 0, 0.2);
}

.mobile-bottom-nav a, 
.mobile-bottom-nav a.nav-item {
    /* Warna icon masa tak tekan (kelabu cerah) */
    color: #94a3b8 !important; 
    text-decoration: none !important;
    text-align: center;
    font-size: 11px;
    font-weight: 600;
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 4px;
    flex: 1;
    transition: all 0.3s ease;
}

/* Warna bila AKTIF (Cyan menyala) */
.mobile-bottom-nav a.active,
.mobile-bottom-nav a.nav-item.active {
    color: #06b6d4 !important; 
}

.mobile-bottom-nav i {
    font-size: 20px;
}
    .mobile-bottom-nav i {
        font-size: 20px;
    }

    /* Mobile Slider Logic */
    .booking-slide-wrapper {
        display: flex;
        overflow-x: auto;
        scroll-snap-type: x mandatory;
        scrollbar-width: none;
        margin: 0 -15px;
    }
    .booking-slide-wrapper::-webkit-scrollbar { display: none; }
    
    .slide-column {
        flex: 0 0 100%;
        scroll-snap-align: start;
        padding: 0 15px;
    }
}

/* =========================================================================
   4. UI COMPONENTS (STEPPER, FORM, CARDS)
   ========================================================================= */
.reservation-card {
    background: white; border-radius: 24px;
    border: 1px solid rgba(0,0,0,0.05);
    box-shadow: 0 10px 30px rgba(0,0,0,0.03);
    padding: 35px; max-width: 800px; margin: 0 auto;
}

.stepper-wrapper {
    display: flex; justify-content: space-between;
    margin-bottom: 30px; position: relative;
}

.stepper-wrapper::before {
    content: ""; position: absolute;
    top: 25px; left: 10%; right: 10%;
    height: 2px; background: var(--step-inactive);
    z-index: 0;
}

.step-item {
    position: relative; z-index: 1;
    display: flex; flex-direction: column; align-items: center;
    flex: 1; border: none; background: none; cursor: pointer;
}

.step-counter {
    width: 45px; height: 45px;
    border-radius: 50%; background: white;
    border: 2px solid var(--step-inactive);
    display: flex; align-items: center; justify-content: center;
    margin-bottom: 8px; transition: 0.3s;
    color: var(--step-text); font-weight: bold;
}

.step-item.active .step-counter {
    background: var(--primary-color); color: white; border-color: var(--primary-color);
}

/* Category Submenu */
#categorySubmenu {
    display: none;
    padding-left: 20px;
    overflow: hidden; 
}

.arrow-icon {
    display: inline-block;
    transition: transform 0.3s ease;
}

.arrow-rotate { transform: rotate(180deg); }

/* T&C Link Animation */
.tc-link {
    text-decoration: underline;
    cursor: pointer;
    padding: 2px 5px;
    background-color: #fff3cd;
    border-radius: 4px;
    animation: pulse-blue 2s infinite;
}

@keyframes pulse-blue {
    0% { transform: scale(1); }
    50% { transform: scale(1.05); color: #0056b3; }
    100% { transform: scale(1); }
}

/* Form Styling */
.form-control, .form-select {
    border-radius: 12px;
    padding: 12px;
    border: 1px solid #e2e8f0;
    background-color: #fcfcfd;
}

.category-image-box { 
    width: 60px; height: 60px; border-radius: 8px; 
    overflow: hidden; border: 1px solid #e2e8f0;
    margin-right: 15px; flex-shrink: 0; 
}
.category-thumb-img { width: 100%; height: 100%; object-fit: cover; }

/* --- TAMBAHAN UNTUK BUTTON & TEXT --- */

/* 1. Warna Tulisan 'Available Equipment' */
/* (Tukar warna ni ikut tema Cyan/Blue kau) */
h5.fw-bold.mb-0, 
.inventory-title { 
    color: #1e293b !important; /* Warna gelap slate */
    font-weight: 700 !important;
    letter-spacing: -0.5px;
}

/* 2. Button Confirm (Step Akhir) */
#confirmReservationBtn, 
.btn-primary.w-100 {
    background: linear-gradient(135deg, #06b6d4 0%, #0d6efd 100%) !important; /* Gradient Cyan ke Blue */
    border: none !important;
    padding: 14px !important;
    font-weight: 700 !important;
    border-radius: 12px !important;
    transition: all 0.3s ease !important;
    box-shadow: 0 4px 15px rgba(6, 182, 212, 0.2) !important;
}

#confirmReservationBtn:hover {
    transform: translateY(-2px) !important;
    box-shadow: 0 6px 20px rgba(6, 182, 212, 0.3) !important;
    filter: brightness(1.1);
}

/* 3. Button 'Add' atau 'Select' kat Inventory */
.btn-outline-primary {
    border-color: #06b6d4 !important;
    color: #06b6d4 !important;
    border-radius: 8px !important;
}

.btn-outline-primary:hover {
    background-color: #06b6d4 !important;
    color: #fff !important;
}

/* --- 1. TULISAN 'AVAILABLE EQUIPMENT' --- */
.inventory-title-wrapper h5 {
    color: #06b6d4 !important; /* Warna Cyan */
    font-weight: 700 !important;
    font-size: 1.1rem;
    display: flex;
    align-items: center;
    gap: 8px;
}

/* --- 2. KOTAK GAMBAR (FIX BIAR PENUH) --- */
.category-image-box {
    width: 65px !important; 
    height: 65px !important;
    border-radius: 12px !important; /* Bagi nampak moden sikit */
    overflow: hidden;
    border: 1px solid #f1f5f9;
    background: #f8fafc;
    flex-shrink: 0;
}

.category-thumb-img {
    width: 100% !important;
    height: 100% !important;
    object-fit: cover !important; /* PENTING: Ini yang buat gambar penuh kotak */
    display: block;
}

/* --- 3. BUTTON CONFIRM & LOCK CONTEXT --- */
#confirmReservationBtn, 
button[type="submit"].btn-primary,
.btn-confirm-lock {
    background: linear-gradient(135deg, #06b6d4 0%, #0d6efd 100%) !important;
    border: none !important;
    color: white !important;
    font-weight: 700 !important;
    text-transform: uppercase;
    letter-spacing: 1px;
    padding: 15px !important;
    border-radius: 12px !important;
    transition: all 0.3s ease;
    box-shadow: 0 4px 15px rgba(6, 182, 212, 0.3) !important;
}

#confirmReservationBtn:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(6, 182, 212, 0.4) !important;
    filter: brightness(1.1);
}

/* --- 4. ALTERNATIF: Kalau gambar pecah (Macam Tripod tu) --- */
.category-thumb-img[src=""], 
.category-thumb-img:not([src]) {
    display: none;
}
</style></head>
<body>

<div id="overlay"></div>

<div class="sidebar" id="admin-sidebar">
    <div>
        <div class="sidebar-header">
    <div class="logo-icon">
        <i class="fa-solid fa-wrench"></i>
    </div>
    <div class="logo-text">
        <strong class="brand-name">UniKL User</strong><br>
        <span>Equipment System</span>
    </div>
</div>
<div class="sidebar-nav">
    <a href="dashboard_user.php" class="<?= (basename($_SERVER['PHP_SELF']) == 'dashboard_user.php') ? 'active' : '' ?>">
        <i class="fa-solid fa-house"></i> Dashboard
    </a>

  <a href="item_user.php" class="active item-availability-link collapsed" id="itemAvailabilityToggle">
            <i class="fa-solid fa-calendar-plus"></i>Book Equipment
            <i class="fa-solid fa-angle-down ms-auto" style="font-size: 12px;"></i>
        </a>
		
		<div class="category-submenu ps-4" id="categorySubmenu" style="display: none;">
    <a href="#" class="category-filter-link py-2 d-block text-decoration-none" 
       data-category="" 
       style="color: #cbd5e1; font-size: 0.9rem;">
        <i class="fa-solid fa-layer-group me-2"></i> All Items
    </a>

    <?php foreach ($categories as $category): ?>
        <a href="#" class="category-filter-link py-2 d-block text-decoration-none" 
           data-category="<?= htmlspecialchars($category['category_name']) ?>" 
           style="color: #cbd5e1; font-size: 0.9rem;">
            <i class="fa-solid fa-tag me-2"></i> <?= htmlspecialchars($category['category_name']) ?>
        </a>
    <?php endforeach; ?>
</div>
    <a href="history.php" class="<?= (basename($_SERVER['PHP_SELF']) == 'history.php') ? 'active' : '' ?>">
        <i class="fa-solid fa-clock-rotate-left"></i> My Loan
    </a>
</div>
    </div>
    
<div class="sidebar-footer">
    <a href="logout.php" class="logout-link"><i class="fa-solid fa-sign-out-alt"></i> Logout</a> 
</div> 	
</div>

<div class="main-content">
<div class="topbar">
    <div class="topbar-left">
        <nav aria-label="breadcrumb">
            <h4 class="fw-bold">Reservation</h4>
        </nav>
    </div>

    <div class="topbar-right">
       
        <a href="profile.php" class="user-pill text-decoration-none d-flex align-items-center">
    <div class="text-end me-2 d-none d-md-block">
        <div class="user-name fw-bold" style="color: #1e293b; line-height: 1.2;">
            <?= htmlspecialchars($displayName) ?>
        </div>
        <div style="font-size: 0.7rem; color: #64748b;">
            Student / Staff
        </div>
    </div>

    <div class="profile-avatar">
        <img src="https://ui-avatars.com/api/?name=<?= urlencode($displayName ?? 'U') ?>&background=06b6d4&color=fff" 
             class="rounded-circle" 
             width="35">
    </div>
</a>
    </div>
</div>

<div class="container-fluid py-4">
    <div class="booking-slide-wrapper">
        
        <div class="slide-column main-form-column">
            <form id="reserveForm">
                <input type="hidden" name="person_id" value="<?= isset($person_id) ? $person_id : '' ?>">
                <input type="hidden" name="all_items" id="allItems">

                <div class="stepper-wrapper mb-4">
                    <button type="button" class="step-item active" id="step1-nav">
                        <div class="step-counter"><i class="fa-solid fa-file-lines"></i></div>
                        <div class="step-label">Context</div>
                    </button>
                    <button type="button" class="step-item" id="step2-nav" disabled>
                        <div class="step-counter"><i class="fa-solid fa-list-check"></i></div>
                        <div class="step-label">Add Items</div>
                    </button>
                    <button type="button" class="step-item" id="step3-nav" disabled>
                        <div class="step-counter"><i class="fa-solid fa-clipboard-check"></i></div>
                        <div class="step-label">Review</div>
                    </button>
                </div>

                <div class="reservation-card border-0 shadow-sm p-4 bg-white" style="border-radius: 20px; min-height: 450px;">
                    
                    <div class="tab-pane-custom active-step" id="step1-content">
                        <div class="d-flex justify-content-between align-items-center mb-1">
                            <h5 class="fw-bold mb-0"><i class="fa-solid fa-circle-1 text-primary me-2"></i>Define Context</h5>
                            <span class="badge bg-primary-light text-primary d-lg-none animate-hint">
                                Items <i class="fa-solid fa-arrow-right ms-1"></i>
                            </span>
                        </div>
                        <p class="text-muted small mb-4">Specify the borrowing dates and location.</p>
                        
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label small fw-bold">Borrow Date</label>
                                <input type="date" class="form-control" name="reserve_date" id="reserveDate" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small fw-bold">Return Date</label>
                                <input type="date" class="form-control" name="return_date" id="returnDate" required>
                            </div>
                            <div class="col-12">
                                <label class="form-label small fw-bold">Program Type</label>
                                <select class="form-select" id="program_type" name="program_type" required>
                                    <option value="">-- Select Type --</option>
                                    <option value="3">Academic Project/Class</option>
                                    <option value="4">Official Event</option>
                                    <option value="5">Club/Society Activity</option>
                                </select>
                            </div>
                            <div class="col-12">
                                <label class="form-label small fw-bold">Usage Location</label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light border-end-0"><i class="fa-solid fa-location-dot text-primary"></i></span>
                                    <input type="text" class="form-control border-start-0" id="location" name="location" placeholder="e.g., Block A, Level 3" required>
                                </div>
                            </div>
                            <div class="col-12">
                                <label class="form-label small fw-bold">Purpose/Reason</label>
                                <textarea class="form-control" id="reason" name="reason" rows="2" placeholder="Briefly explain..."></textarea>
                            </div>
                        </div>
                        
                        <button type="button" class="btn btn-primary w-100 mt-4 py-3 fw-bold shadow-sm" id="confirmContextBtn">
                            <i class="fa-solid fa-lock me-2"></i> CONFIRM & LOCK CONTEXT
                        </button>
                    </div>

                    <div class="tab-pane-custom d-none" id="step2-content">
                        <h5 class="fw-bold mb-1"><i class="fa-solid fa-circle-2 text-primary me-2"></i>Select Items</h5>
                        <p class="text-muted small mb-4">Choose items from the inventory.</p>
                        
                        <div class="bg-light p-3 rounded-3 mb-4 border border-dashed">
                            <div class="row g-3">
                                <div class="col-md-8">
                                    <label class="form-label small fw-bold">Search Item</label>
                                    <select id="item_select" class="form-select select2-ins-card" style="width: 100%;">
                                        <option value="">-- Search and select --</option>
                                    </select>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label small fw-bold">Qty</label>
                                    <input type="number" id="quantity" class="form-control" min="1" value="1">
                                </div>
                            </div>
                            <div id="availability-status" class="mt-2"></div>
                            <button type="button" class="btn btn-primary btn-sm w-100 mt-3" id="addMoreBtn" disabled>
                                <i class="fa-solid fa-plus me-1"></i> Add to Request List
                            </button>
                        </div>

                        <h6 class="fw-bold mb-3 small text-uppercase">Current Request List</h6>
                        <div id="itemsList" class="mb-4"></div>

                        <div class="d-flex justify-content-between pt-3 border-top">
                            <button type="button" id="prevToContextBtn" class="btn btn-link text-decoration-none text-muted small">
                                <i class="fa-solid fa-arrow-left me-1"></i> Edit Context
                            </button>
                            <button type="button" class="btn btn-primary px-4 shadow-sm" id="nextToReviewBtn" disabled>
                                Next Step <i class="fa-solid fa-chevron-right ms-1"></i>
                            </button>
                        </div>
                    </div>

                    <div class="tab-pane-custom d-none" id="step3-content">
                        <div class="text-center mb-4">
                            <div class="bg-success text-white rounded-circle d-inline-flex align-items-center justify-content-center mb-2" style="width: 50px; height: 50px;">
                                <i class="fa-solid fa-check-double fa-lg"></i>
                            </div>
                            <h5 class="fw-bold">Final Review</h5>
                        </div>
                        <div class="bg-light p-3 rounded-3 mb-4 small border-start border-primary border-4">
                            <div id="contextSummary"></div>
                        </div>
                        <div id="itemsReviewList" class="mb-4 border rounded-3 p-2 bg-white" style="max-height: 200px; overflow-y: auto;"></div>
                        <div class="form-check p-3 bg-light rounded-3 mb-4" id="tncTrigger">
                            <input class="form-check-input ms-0 me-2" type="checkbox" id="agreeTerms">
                            <label class="form-check-label small" for="agreeTerms">I agree to the Terms & Conditions</label>
                        </div>
                        <div class="row g-2">
                            <div class="col-8"><button type="button" class="btn btn-primary btn-lg w-100 fw-bold" id="finalSubmitBtn" disabled>SUBMIT</button></div>
                            <div class="col-4"><button type="button" class="btn btn-outline-secondary btn-lg w-100" id="backToItemsBtn">BACK</button></div>
                        </div>
                    </div>
                </div> 
            </form>
        </div>

        <div class="slide-column side-inventory-column">
            <div class="card border-0 shadow-sm h-100" style="border-radius: 20px; overflow: hidden;">
                <div class="card-header bg-white py-3 px-4 border-bottom d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="fw-bold mb-0 text-primary"><i class="fa-solid fa-boxes-stacked me-2"></i>Available Equipment</h6>
                        <small class="text-muted">Live preview</small>
                    </div>
                    <button type="button" class="btn btn-sm btn-light d-lg-none" onclick="document.querySelector('.booking-slide-wrapper').scrollLeft = 0;">
                        <i class="fa-solid fa-arrow-left"></i>
                    </button>
                </div>
                
                <div class="list-group list-group-flush" id="displayItemList" style="max-height: 70vh; overflow-y: auto;">
                    <?php if (!empty($items_for_dropdown)): ?>
                        <?php foreach ($items_for_dropdown as $item): ?>
                            <div class="list-group-item d-flex align-items-center py-3 px-4 item-hover">
                                <div class="category-image-box me-3">
                                    <img src="<?= htmlspecialchars($item['image_url'] ?: '../assets/img/no-image-placeholder.png') ?>" class="rounded-3 shadow-sm" style="width: 50px; height: 50px; object-fit: cover;">
                                </div>
                                <div class="flex-grow-1">
                                    <h6 class="mb-0 small fw-bold text-dark"><?= htmlspecialchars($item['item_name']) ?></h6>
                                    <small class="text-muted" style="font-size: 11px;"><?= htmlspecialchars($item['category_name']) ?></small>
                                </div>
                                <div class="badge bg-light text-success border" style="font-size: 10px;">Available</div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>

    </div>
</div>
<div class="modal fade" id="termsModal" tabindex="-1" aria-labelledby="termsModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable modal-lg">
        <div class="modal-content shadow-lg border-0">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title d-flex align-items-center" id="termsModalLabel">
                    <i class="fa-solid fa-file-contract me-2"></i> Terms & Conditions
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            
            <div class="modal-body p-4" id="modalTermsContent">
                <div class="alert alert-info border-0 shadow-sm small mb-4">
                    <i class="fa-solid fa-circle-info me-2"></i> 
                    Please read the following terms carefully before submitting your <strong>reservation</strong> request.
                </div>

                <div class="tnc-list">
                    <div class="d-flex mb-3">
                        <div class="me-3 text-primary"><i class="fa-solid fa-1"></i></div>
                        <div><strong>Eligibility:</strong> All equipment is available for reservation only to registered students and staff of UniKL with a valid ID.</div>
                    </div>
                    <div class="d-flex mb-3">
                        <div class="me-3 text-primary"><i class="fa-solid fa-2"></i></div>
                        <div><strong>Reservation Duration:</strong> The duration of the reservation is as specified in your request.</div>
                    </div>
                    <div class="d-flex mb-3">
                        <div class="me-3 text-primary"><i class="fa-solid fa-3"></i></div>
                        <div><strong>Responsibility:</strong> The party  making the reservation is fully responsible for the reserved equipment from the moment of collection until they are returned and checked in by a technician.</div>
                    </div>
                    <div class="d-flex mb-3">
                        <div class="me-3 text-primary"><i class="fa-solid fa-4"></i></div>
                        <div><strong>Condition of Items:</strong> The reserving party must inspect the item(s) at the time of collection. Any existing damage must be reported immediately, or the reserving party may be held responsible.</div>
                    </div>
                    <div class="d-flex mb-3 text-danger">
                        <div class="me-3"><i class="fa-solid fa-5 text-danger"></i></div>
                        <div><strong>Damage or Loss:</strong> The  reserving party will be held financially responsible for the full replacement cost of any lost, stolen, or damaged items (including all parts and accessories).</div>
                    </div>
                    <div class="d-flex mb-3">
                        <div class="me-3 text-primary"><i class="fa-solid fa-6"></i></div>
                        <div><strong>Late Returns:</strong> Failure to return items by the specified return date will result in a fine and a temporary suspension of reservation privileges.</div>
                    </div>
                    <div class="d-flex mb-3">
                        <div class="me-3 text-primary"><i class="fa-solid fa-7"></i></div>
                        <div><strong>Purpose of Use:</strong> Items are to be used for academic or official university purposes only, as specified in the reservation form.</div>
                    </div>
					<div class="d-flex mb-3">
                        <div class="me-3 text-primary"><i class="fa-solid fa-7"></i></div>
                        <div><strong>Collection:</strong> Approved items must be collected within 24 hours of the "Approved" status being issued, or the reservation may be cancelled.</div>
                    </div>
                </div>

                <div class="bg-light p-3 rounded-3 mt-4 text-center border">
                    <p class="mb-0 small fw-bold">By checking the box below, you acknowledge that you have read and agree to be bound by all the terms stated above.</p>
                </div>
            </div> 

            <div class="modal-footer flex-column bg-light border-top-0 p-4">
                <div class="form-check mb-3 w-100 d-flex justify-content-center">
                    <input class="form-check-input me-2 shadow-none" type="checkbox" id="modalAgreeCheck" style="transform: scale(1.2);">
                    <label class="form-check-label fw-bold" for="modalAgreeCheck">
                        I have read and agree to the Terms & Conditions
                    </label>
                </div>
                <button type="button" id="confirmTermsBtn" class="btn btn-primary btn-lg w-100 shadow-sm fw-bold" disabled>
                    <i class="fa-solid fa-check-circle me-2"></i>Confirm & Agree
                </button>
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


const ALL_ITEMS_DATA = <?= json_encode($items_for_dropdown) ?>;


let RESERVATION_CONTEXT = {};

let REQUEST_ITEMS = [];


let reserveDatePicker;
let returnDatePicker;

$(document).ready(function() {
    // Kita target span yang ada ID toggle (Arrow sahaja)
    $('#itemAvailabilityToggle').on('click', function(e) {
        // Elakkan event ni 'merebak' ke elemen lain
        e.preventDefault();
        e.stopPropagation();

        const submenu = $('#categorySubmenu');
        const arrowIcon = $(this).find('.arrow-icon');

        // Slide toggle submenu
        submenu.stop(true, true).slideToggle(300);

        // Pusingkan arrow
        if (arrowIcon.hasClass('rotated')) {
            arrowIcon.removeClass('rotated').css('transform', 'rotate(0deg)');
        } else {
            arrowIcon.addClass('rotated').css('transform', 'rotate(180deg)');
        }
    });

    // Kekalkan submenu terbuka kalau user sedang tengok kategori spesifik
    if (window.location.search.includes('category=')) {
        $('#categorySubmenu').show();
        $('.arrow-icon').addClass('rotated').css('transform', 'rotate(180deg)');
    }
    $('#myTab button[data-bs-toggle="tab"]').on('click', function (e) {
        if ($(this).hasClass('disabled')) {
            e.preventDefault();
            e.stopPropagation(); 
            
            const targetId = $(this).attr('id');
            if (targetId === 'items-tab') {
                Swal.fire('Locked', 'Please confirm the Context (Step 1) first before adding items.', 'warning');
            } else if (targetId === 'review-tab') {
                Swal.fire('Locked', 'Please add items to your request list (Step 2) first.', 'warning');
            }
        }
    });
    // Trigger check stok bila item dipilih
$('#item_select').on('change', function() {
    checkItemAvailability();
});

// Trigger check stok bila kuantiti ditaip/tukar
$('#quantity').on('input change', function() {
    checkItemAvailability();
});
    
// 1. Init Borrow Date Picker (Reserve Date)
reserveDatePicker = flatpickr("#reserveDate", {
    dateFormat: "Y-m-d",
    minDate: "today", // [Auto-Validation] Halang pilih sebelum hari ini
    altInput: true,
    altFormat: "F j, Y",
    onChange: function(selectedDates, dateStr) {
        // [Logic Guard] Kemaskini minDate untuk Return Date secara dinamik
        if (returnDatePicker) {
            returnDatePicker.set('minDate', dateStr || "today");
            
            // Jika Return Date sedia ada lebih awal dari Borrow Date yang baru dipilih, 
            // kita kosongkan Return Date supaya user pilih semula dengan betul.
            if (returnDatePicker.selectedDates[0] < selectedDates[0]) {
                returnDatePicker.clear();
            }
        }
        
        // Update context global
        RESERVATION_CONTEXT.reserve_date = dateStr;
        
        // [Real-time Check] Trigger check ketersediaan stok
        handleAvailabilityRecheck();
    }
});

// 2. Init Return Date Picker
returnDatePicker = flatpickr("#returnDate", {
    dateFormat: "Y-m-d",
    minDate: "today", // [Auto-Validation] Default tetap hari ini
    altInput: true,
    altFormat: "F j, Y",
    onChange: function(selectedDates, dateStr) {
        // Update context global
        RESERVATION_CONTEXT.return_date = dateStr;

        // [Real-time Check] Trigger check ketersediaan stok
        handleAvailabilityRecheck();
    }
});
    $('#item_select').select2({
        theme: 'bootstrap-5',
        placeholder: 'Search and select an item...',
        dropdownParent: $('#ItemSelectionCard'),
        data: ALL_ITEMS_DATA.map(item => ({
            id: item.item_id,
            text: `${item.item_name} (${item.category_name})`,
            itemData: item
        }))
    });
    
	function unlockContext() {
    
    
    $('#ContextCard').find('input, select, textarea').prop('disabled', false).css('opacity', 1);

    

    
    if (typeof reserveDatePicker !== 'undefined' && reserveDatePicker) {
        
        $(reserveDatePicker.element).prop('readonly', false).prop('disabled', false);
        $(reserveDatePicker.altInput).prop('readonly', false).prop('disabled', false);

        
        if (RESERVATION_CONTEXT.reserve_date) {
            reserveDatePicker.setDate(RESERVATION_CONTEXT.reserve_date, true, 'Y-m-d'); 
        }
        
        
        reserveDatePicker.set('clickOpens', true);
    }

    if (typeof returnDatePicker !== 'undefined' && returnDatePicker) {
        
        $(returnDatePicker.element).prop('readonly', false).prop('disabled', false);
        $(returnDatePicker.altInput).prop('readonly', false).prop('disabled', false);

        
        if (RESERVATION_CONTEXT.return_date) {
            returnDatePicker.setDate(RESERVATION_CONTEXT.return_date, true, 'Y-m-d');
        }
        
        returnDatePicker.set('clickOpens', true);
    }

    
    $('#confirmContextBtn').html('<i class="fa-solid fa-check-double me-2"></i> Confirm Context').removeClass('btn-success').addClass('btn-primary').prop('disabled', false);

    
    $('#nextToItemsBtn').addClass('d-none').prop('disabled', true);
    
    
    Swal.fire('Context Unlocked', 'You can now edit the dates, program type, and purpose.', 'info');
}
	
    
    function filterSelect2ByCategoryId(categoryId) {
    // Tapis data dari master list
    const filteredData = ALL_ITEMS_DATA
        .filter(item => categoryId === '' || item.category_id == categoryId)
        .map(item => ({
            id: item.item_id,
            text: `${item.item_name} (${item.category_name})`,
            itemData: item
        }));

    // Reset Select2
    const $select = $('#item_select');
    $select.empty(); // Buang option lama
    $select.append(new Option('-- Search and select an item --', ''));

    // Re-initialize Select2 dengan data baru
    $select.select2({
        theme: 'bootstrap-5',
        placeholder: 'Search and select an item...',
        width: '100%',
        data: filteredData
    });
}
$('.category-filter-link').on('click', function(e) {
    e.preventDefault();
    
    // 1. Visual update untuk sidebar
    $('.category-filter-link').removeClass('active-category-filter').css('color', '#cbd5e1');
    $(this).addClass('active-category-filter').css('color', '#06b6d4');
    
    const categoryName = $(this).data('category'); 
    console.log("Kategori dipilih:", categoryName);

    // 2. Filter list besar di sebelah kanan (Available Equipment)
    filterItemList(categoryName); 

    // 3. LOGIC FIX: Cari ID secara fleksibel (ignore case)
    let categoryId = '';
    if (categoryName && categoryName !== '') {
        const foundItem = ALL_ITEMS_DATA.find(item => 
            item.category_name.toLowerCase() === categoryName.toLowerCase()
        );
        categoryId = foundItem ? foundItem.category_id : '';
    }

    // 4. Update Dropdown Select2
    filterSelect2ByCategoryId(categoryId);
}); 
    filterItemList('');
    
    
    $('#menuToggle').on('click', function() {
        $('.sidebar').toggleClass('active');
        $('#overlay').toggle();
    });

    $('#overlay').on('click', function() {
        $('.sidebar').removeClass('active');
        $('#overlay').hide();
    });
   

// 1. Trigger Modal bila klik mana-mana bahagian dalam kotak T&C
$(document).on('click', '#tncTrigger', function(e) {
    e.preventDefault();
    // Guna API Bootstrap untuk buka modal
    var tncModal = new bootstrap.Modal(document.getElementById('termsModal'));
    tncModal.show();
});

// 2. Dalam Modal: Enable butang 'Confirm' hanya bila checkbox DALAM modal ditick
$(document).on('change', '#modalAgreeCheck', function() {
    $('#confirmTermsBtn').prop('disabled', !$(this).is(':checked'));
});

// 3. Bila klik butang 'Confirm' dalam modal
$(document).on('click', '#confirmTermsBtn', function() {
    // Tick-kan checkbox kat luar secara programatik
    $('#agreeTerms').prop('checked', true);
    
    // Aktifkan butang Submit utama (Step 3)
    $('#finalSubmitBtn').prop('disabled', false);
    
    // Tutup modal
    $('#termsModal').modal('hide');

    // Feedback visual
    Swal.fire({
        toast: true,
        position: 'top-end',
        icon: 'success',
        title: 'Terms Accepted',
        showConfirmButton: false,
        timer: 2000
    });
});

// 4. Reset modal kalau user tutup tanpa tekan Confirm
$('#termsModal').on('hidden.bs.modal', function () {
    if (!$('#agreeTerms').is(':checked')) {
        $('#modalAgreeCheck').prop('checked', false);
        $('#confirmTermsBtn').prop('disabled', true);
    }
});
	
function checkContextInputs() {
    return $('#reserveDate').val() && 
           $('#returnDate').val() && 
           $('#program_type').val() && 
           $('#reason').val() &&
           $('#location').val(); // TAMBAH INI
}

    
// GANTIKAN bahagian navigasi sedia ada dengan ini
function showStep(stepNumber) {
    $('.tab-pane-custom').addClass('d-none'); // Sembunyi semua content
    $('#step' + stepNumber + '-content').removeClass('d-none'); // Tunjuk content langkah terpilih
    
    // Update stepper UI
    $('.step-item').removeClass('active');
    for(let i=1; i<=stepNumber; i++) {
        $('#step' + i + '-nav').addClass('active').prop('disabled', false);
    }
}

// Langkah 1 -> 2
$('#confirmContextBtn').on('click', function() {
    if (checkContextInputs()) {
        RESERVATION_CONTEXT = {
            reserve_date: $('#reserveDate').val(),
            return_date: $('#returnDate').val(),
            program_type: $('#program_type').val(),
            program_type_text: $('#program_type option:selected').text().trim(),
            reason: $('#reason').val(),
            location: $('#location').val(),
            person_id: $('input[name="person_id"]').val()
        };

      $('#step1-content').find('input, select, textarea').prop('disabled', true).css('opacity', 0.6);
        $(this).html('<i class="fa-solid fa-lock me-2"></i> CONTEXT LOCKED').addClass('btn-success').prop('disabled', true);
        
        showStep(2); 

        // TOAST UNTUK STEP 1
        Swal.fire({
            toast: true,
            position: 'top-end',
            icon: 'success',
            title: 'Context Locked & Saved',
            showConfirmButton: false,
            timer: 2000
        });
        
        checkItemAvailability();
    } else {
        // Warning jika tak lengkap pun boleh buat toast
        Swal.fire({
            toast: true,
            position: 'top-end',
            icon: 'warning',
            title: 'Please fill all fields',
            showConfirmButton: false,
            timer: 3000
        });
    }
});


// Langkah 2 -> 1 (Edit Context)
$('#prevToContextBtn').on('click', function() {
    // Unlock input Step 1
    $('#step1-content').find('input, select, textarea').prop('disabled', false).css('opacity', 1);
    $('#confirmContextBtn').html('<i class="fa-solid fa-lock me-2"></i> CONFIRM & LOCK CONTEXT').removeClass('btn-success').prop('disabled', false);
    showStep(1);
});

// Langkah 2 -> 3
$('#nextToReviewBtn').on('click', function() {
    if (REQUEST_ITEMS.length > 0) {
        updateReviewTab(); 
        showStep(3);
    } else {
        Swal.fire('No Items', 'Add at least one item first.', 'warning');
    }
});

// Langkah 3 -> 2
$('#backToItemsBtn').on('click', function() {
    showStep(2);
});

$('#item_select').select2({
    theme: 'bootstrap-5',
    placeholder: 'Search and select an item...',
    width: '100%', // Paksa lebar 100%
    dropdownParent: $('#step2-content') // Setkan terus ke ID step tersebut
});    
    function displayAvailability(availableQty, requestedQty, itemName) {
        const statusDiv = $('#availability-status');
        statusDiv.empty();
        
        if (availableQty === null) {
            statusDiv.append('<div class="alert alert-secondary py-2 small"><i class="fa-solid fa-circle-info me-2"></i> Select an item and quantity above.</div>');
            $('#addMoreBtn').prop('disabled', true);
            return;
        }

        if (availableQty >= requestedQty) {
            statusDiv.append(`
                <div class="alert alert-success py-2 small">
                    <i class="fa-solid fa-check-circle me-2"></i> 
                    ${itemName}: <strong>${requestedQty} unit(s) is AVAILABLE</strong> from ${availableQty} total unit(s).
                </div>
            `);
            $('#addMoreBtn').prop('disabled', false).html('<i class="fa-solid fa-cart-plus me-2"></i> <strong>Add Item to Request List</strong>');
        } else if (availableQty > 0) {
            statusDiv.append(`
                <div class="alert alert-warning py-2 small">
                    <i class="fa-solid fa-triangle-exclamation me-2"></i> 
                    ${itemName}: Only <strong>${availableQty} unit(s) are AVAILABLE</strong>. Requested: ${requestedQty}.
                </div>
            `);
            if (requestedQty > availableQty) {
                $('#addMoreBtn').prop('disabled', true);
                statusDiv.append('<p class="small text-danger mb-0 mt-1">Please reduce the quantity to match the available stock.</p>');
            } else {
                 $('#addMoreBtn').prop('disabled', false).html('<i class="fa-solid fa-cart-plus me-2"></i> <strong>Add Item to Request List</strong>');
            }
        } else {
            statusDiv.append(`
                <div class="alert alert-danger py-2 small">
                    <i class="fa-solid fa-ban me-2"></i> 
                    ${itemName}: <strong>0 unit(s) AVAILABLE</strong> for the selected dates.
                </div>
            `);
            $('#addMoreBtn').prop('disabled', true);
        }
    }

    
    function checkItemAvailability() {
        const item_id = $('#item_select').val();
        const quantity = parseInt($('#quantity').val());

        if (!item_id || isNaN(quantity) || quantity <= 0) {
            displayAvailability(null);
            return;
        }

        const selectedItem = ALL_ITEMS_DATA.find(item => item.item_id == item_id);
        const itemName = selectedItem ? selectedItem.item_name : 'Selected Item';
        
        $('#addMoreBtn').prop('disabled', true).html('<i class="fa-solid fa-spinner fa-spin me-2"></i> Checking Availability...');

        $.ajax({
            url: 'check_availability.php',
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
                    $('#addMoreBtn').prop('disabled', true).html('<i class="fa-solid fa-cart-plus me-2"></i> <strong>Add Item to Request List</strong>');
                }
            },
            error: function() {
                $('#availability-status').html('<div class="alert alert-danger py-2 small"><i class="fa-solid fa-exclamation-triangle me-2"></i> Server error. Please try again.</div>');
                $('#addMoreBtn').prop('disabled', true).html('<i class="fa-solid fa-cart-plus me-2"></i> <strong>Add Item to Request List</strong>');
            }
        });
    }
$('#addMoreBtn').on('click', function() {
    const item_id = $('#item_select').val();
    const quantity = parseInt($('#quantity').val());
    const selectedItem = ALL_ITEMS_DATA.find(item => item.item_id == item_id);

    if (!item_id || isNaN(quantity) || quantity <= 0 || !selectedItem) {
        Swal.fire({
            toast: true,
            position: 'top-end',
            icon: 'error',
            title: 'Please select valid item/quantity',
            showConfirmButton: false,
            timer: 3000
        });
        return;
    }
    
    const existingIndex = REQUEST_ITEMS.findIndex(item => item.item_id == item_id);
    
    if (existingIndex !== -1) {
        REQUEST_ITEMS[existingIndex].quantity = quantity;
        // TOAST UPDATE
        Swal.fire({
            toast: true,
            position: 'top-end',
            icon: 'success',
            title: 'Quantity Updated',
            showConfirmButton: false,
            timer: 2000
        });
    } else {
        REQUEST_ITEMS.push({
            item_id: item_id,
            item_name: selectedItem.item_name,
            category_name: selectedItem.category_name,
            image_url: selectedItem.image_url,
            quantity: quantity
        });
        // TOAST ADDED
        Swal.fire({
            toast: true,
            position: 'top-end',
            icon: 'success',
            title: 'Item Added to List',
            showConfirmButton: false,
            timer: 2000
        });
    }

    $('#item_select').val(null).trigger('change');
    $('#quantity').val(1);
    $('#availability-status').empty();
    $('#addMoreBtn').prop('disabled', true);

    updateReviewTab();
});
    
    $('#backToItemsBtn').on('click', function() {
        new bootstrap.Tab(document.getElementById('items-tab')).show();
    });


function updateReviewTab() {
    
    const summary = `
        <div class="alert alert-info py-2 small">
            <p class="mb-1"><strong><i class="fa-solid fa-calendar-alt me-1"></i> Loan Duration:</strong> ${RESERVATION_CONTEXT.reserve_date} until ${RESERVATION_CONTEXT.return_date}</p>
            <p class="mb-1"><strong><i class="fa-solid fa-location-dot me-1"></i> Location:</strong> ${RESERVATION_CONTEXT.location}</p> <p class="mb-1"><strong><i class="fa-solid fa-users me-1"></i> Program Type:</strong> ${RESERVATION_CONTEXT.program_type_text}</p>
            <p class="mb-0"><strong><i class="fa-solid fa-pen-nib me-1"></i> Purpose:</strong> ${RESERVATION_CONTEXT.reason}</p>
        </div>
    `;
    $('#contextSummary').html(summary);

    let itemsListHtml_Step2 = '';
    
    
    let itemsListHtml_Step3_Review = ''; 
    
    if (REQUEST_ITEMS.length === 0) {
        itemsListHtml_Step2 = '<div class="text-center text-muted p-4"><i class="fa-solid fa-list-check fa-2x mb-2"></i><p class="mb-0">Your request list is currently empty.</p></div>';
        itemsListHtml_Step3_Review = '<div class="text-center text-muted p-4 border rounded"><i class="fa-solid fa-box-open fa-2x mb-2"></i><p class="mb-0">No items added. Please go back to Step 2.</p></div>';
        
        
    } else {
        itemsListHtml_Step2 = '<ul class="list-group list-group-flush">';
        itemsListHtml_Step3_Review = '<ul class="list-group list-group-flush">';

        REQUEST_ITEMS.forEach((item, index) => {
            const imagePath = item.image_url ? `../${item.image_url}` : '../assets/default-image.jpg';
            
            
            itemsListHtml_Step2 += `
                <li class="list-group-item d-flex align-items-center justify-content-between">
                    <div class="d-flex align-items-center flex-grow-1">
                        <div class="category-image-box">
                            <img src="${imagePath}" alt="${item.item_name}" class="category-thumb-img">
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
            
            
            itemsListHtml_Step3_Review += `
                <li class="list-group-item d-flex align-items-center justify-content-between border-bottom">
                    <div class="d-flex align-items-center flex-grow-1">
                        <div class="category-image-box me-3">
                            <img src="${imagePath}" alt="${item.item_name}" class="category-thumb-img" style="width: 40px; height: 40px;">
                        </div>
                        <div>
                            <strong class="d-block">${item.item_name}</strong>
                            <span class="text-muted small">${item.category_name}</span>
                        </div>
                    </div>
                    <span class="badge text-bg-success fs-6">${item.quantity} unit(s)</span>
                </li>
            `;
            
        });
        itemsListHtml_Step2 += '</ul>';
        itemsListHtml_Step3_Review += '</ul>';

    }
    
    
    $('#itemsList').html(itemsListHtml_Step2);
    $('#itemsReviewList').html(itemsListHtml_Step3_Review);


    
    const reviewTabElement = $('#review-tab');
    const nextReviewBtn = $('#nextToReviewBtn');
    
    if (REQUEST_ITEMS.length > 0) {
        reviewTabElement.removeClass('disabled');
        nextReviewBtn.prop('disabled', false).removeClass('btn-outline-secondary').addClass('btn-primary');
        reviewTabElement.html(`<i class="fa-solid fa-3 me-1"></i> Review & Submit <span class="badge text-bg-success ms-2">${REQUEST_ITEMS.length} Items</span>`);
    } else {
        reviewTabElement.addClass('disabled');
        nextReviewBtn.prop('disabled', true).removeClass('btn-primary').addClass('btn-outline-secondary');
        reviewTabElement.html('<i class="fa-solid fa-3 me-1"></i> Review & Submit');
    }

    $('input#allItems').val(JSON.stringify(REQUEST_ITEMS));

    $('.remove-item-btn').on('click', removeItemFromList);
}

function removeItemFromList() {
    const indexToRemove = $(this).data('index');
    
    Swal.fire({
        title: 'Confirm Removal?',
        text: "Remove this item from list?",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#06b6d4',
        cancelButtonColor: '#ef4444',
        confirmButtonText: 'Yes, Remove It!'
    }).then((result) => {
        if (result.isConfirmed) {
            REQUEST_ITEMS.splice(indexToRemove, 1);
            updateReviewTab();
            
            // VERSI TOAST TEPI
            Swal.fire({
                toast: true,
                position: 'top-end',
                icon: 'success',
                title: 'Item Removed',
                showConfirmButton: false,
                timer: 2000
            });
        }
    });
}
    
    $('#agreeTerms').on('change', function() {
        $('#finalSubmitBtn').prop('disabled', !$(this).is(':checked'));
    });

    
    $('#finalSubmitBtn').on('click', function() {
		    // Jika list kosong
    if (REQUEST_ITEMS.length === 0) {
        Swal.fire('Error', 'Your request list is empty.', 'error');
        return;
    }
    
    // Jika belum setuju T&C (Safety Check)
    if (!$('#agreeTerms').is(':checked')) {
        Swal.fire({
            title: 'Terms & Conditions',
            text: 'You must read and agree to the terms before submitting.',
            icon: 'info',
            confirmButtonText: 'Read T&C'
        }).then((result) => {
            if (result.isConfirmed) {
                $('#termsModal').modal('show');
            }
        });
        return;
    }

        if (REQUEST_ITEMS.length === 0) {
            Swal.fire('Error', 'Your request list is empty. Please add items in Step 2.', 'error');
            return;
        }
        if (!$('#agreeTerms').is(':checked')) {
            Swal.fire('Error', 'Please read and agree to the Terms and Conditions.', 'error');
            return;
        }

        const submissionData = {
            ...RESERVATION_CONTEXT,
            items: REQUEST_ITEMS
        };

        const $btn = $(this);
        $btn.prop('disabled', true).html('<i class="fa-solid fa-spinner fa-spin me-2"></i> Submitting...');

        $.ajax({
            url: 'submit_reservation.php', 
            type: 'POST',
            dataType: 'json',
            contentType: 'application/json', 
            data: JSON.stringify(submissionData),
            
            success: function(response) {
                if (response && response.status === 'success') {
                    Swal.fire({
                        title: 'Success!',
                        text: response.message,
                        icon: 'success'
                    }).then(() => {
                        window.location.href = 'history.php';
                    });
                } else if (response && response.status === 'error') {
                    Swal.fire('Submission Failed (Logic Error)', response.message, 'error');
                } else {
                    Swal.fire('Submission Failed', 'Unexpected successful response format.', 'error');
                }
            },
            
            error: function(xhr, status, error) {
                let errorMessage = 'Could not process the submission.';
                let responseText = xhr.responseText.trim();

                try {
                    const jsonResponse = JSON.parse(responseText);
                    errorMessage = jsonResponse.message;
                    
                    if (errorMessage.includes("Tempahan berjaya dihantar") || jsonResponse.status === 'success') {
                         Swal.fire({
                             title: 'Success (Resolved)',
                             text: "Booking successfully sent. Ralat output kotor telah diselesaikan.",
                             icon: 'success'
                         }).then(() => {
                             window.location.href = 'history.php';
                         });
                         return; 
                    }
                } catch (e) {
                    if (responseText.includes("Booking successfully sent")) {
                         Swal.fire({
                             title: 'Success (Resolved)',
                             text: "Successfully sent!",
                             icon: 'success'
                         }).then(() => {
                             window.location.href = 'history.php';
                         });
                         return; 
                    }
                    
                    if (xhr.status === 0 || xhr.status === 404) {
                        errorMessage = 'Network connection failed or Server file not found (404).';
                    } else if (xhr.status >= 500) {
                        errorMessage = `Server Error (${xhr.status}). Please check PHP logs for ${xhr.status}.`;
                    } else {
                        errorMessage = `JSON Parsing Error. Server response starts with: "${responseText.substring(0, 50)}..."`;
                    }
                }

                Swal.fire('Submission Failed', errorMessage, 'error');
            },
            
            complete: function() {
                $btn.prop('disabled', false).html('<i class="fa-solid fa-paper-plane me-2"></i> <strong>Submit Final Request</strong>');
            }
        });
    });

    
    
function filterItemList(categoryName) {
    // 1. Cari target container (Make sure ID ni ada dalam HTML kau)
    const displayList = $('#displayItemList'); 
    if (displayList.length === 0) return; // Exit kalau ID tak jumpa

    displayList.empty();
    
    // 2. Tapis data. Kalau categoryName kosong atau "All Items", tunjuk semua.
    const filteredItems = ALL_ITEMS_DATA.filter(item => {
        return (categoryName === '' || 
                categoryName === 'All Items' || 
                item.category_name === categoryName);
    });

    // 3. Kalau kosong, tunjuk mesej
    if (filteredItems.length === 0) {
        displayList.html('<p class="text-center text-muted p-3">No items found in this category.</p>');
        return;
    }

    // 4. Loop dan masukkan HTML
    filteredItems.forEach(item => {
        const imagePath = item.image_url ? `../${item.image_url}` : '../assets/default-image.jpg';
        
        const itemHtml = `
            <a href="#" class="list-group-item list-group-item-action d-flex align-items-center py-3" 
               onclick="selectItemFromList(${item.item_id}); return false;">
                <div class="category-image-box me-3">
                    <img src="${imagePath}" alt="${item.item_name}" class="category-thumb-img" 
                         style="width:50px; height:50px; object-fit:cover; border-radius:8px;">
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
    
    window.selectItemFromList = function(itemId) {
        $('#item_select').val(itemId).trigger('change');
        $('html, body').animate({
            scrollTop: $('#ItemSelectionCard').offset().top - 80
        }, 500);
    }
    
    filterItemList(''); 
});
</script>
<nav class="mobile-bottom-nav">
    <a href="dashboard_user.php" class="nav-item">
        <i class="fa-solid fa-house"></i>
        <span>Dashboard</span>
    </a>
    <a href="item_user.php" class="nav-item active">
        <i class="fa-solid fa-calendar-plus"></i>
        <span>Book Equipment</span>
    </a>
    <a href="history.php" class="nav-item">
        <i class="fa-solid fa-clock-rotate-left"></i>
        <span>My Loan</span>
    </a>
    <a href="profile.php" class="nav-item">
        <i class="fa-solid fa-user"></i>
        <span>Profile</span>
    </a>
</nav>
</body>
</html>

