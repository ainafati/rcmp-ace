<?php



session_start();
include '../config.php'; 

if (!$conn) {
    die("Database connection failed: " . mysqli_connect_error());
}


if (!isset($_SESSION['person_id'])) {
    header("Location: ../login.php"); 
    exit();
}


$person_id = (int) $_SESSION['person_id'];


$stmt = $conn->prepare("SELECT name, email, phoneNum FROM person WHERE person_id = ?");
if ($stmt === false) {
    die("Error preparing statement: " . $conn->error);
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

$categories = [];
$res_cat = $conn->query("SELECT category_id, category_name FROM categories ORDER BY category_name");
if ($res_cat) {
    while ($row = $res_cat->fetch_assoc()) {
        $categories[] = $row;
    }
    $res_cat->free(); 
}





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





$all_display_items = []; 
$sql_all_display_items = "
    SELECT 
        i.item_id, 
        i.item_name, 
        c.category_name, 
        i.image_url,
        i.description
    FROM item i
    JOIN categories c ON i.category_id = c.category_id
    ORDER BY c.category_name, i.item_name ASC
";
$res_items_display = $conn->query($sql_all_display_items);

if ($res_items_display) {
    while ($row = $res_items_display->fetch_assoc()) {
        $all_display_items[] = $row;
    }
    $res_items_display->free(); 
}


$user_roles = [];
$stmt_roles = $conn->prepare("
    SELECT r.role_name
    FROM person_roles pr
    JOIN roles r ON pr.role_id = r.role_id
    WHERE pr.person_id = ?
");
$stmt_roles->bind_param("i", $person_id);
$stmt_roles->execute();
$result_roles = $stmt_roles->get_result();
while ($row = $result_roles->fetch_assoc()) {
    $user_roles[] = $row['role_name'];
}
$stmt_roles->close();

$_SESSION['user_roles'] = $user_roles;



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
            --primary-color: #06b6d4; /* Cyan 600 */
            --primary-light: #f0f9ff; /* Cyan 50 */
            --primary-hover: #0891b2; /* Cyan 700 */
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

        /* Buttons */
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
        
        /* Form Controls */
        .form-label { font-weight: 500; color: #334155; }
        .form-control, .form-select {
            border-radius: 8px;
            padding: 10px 12px;
            min-height: 48px; 
            border-color: #e2e8f0; 
        }
        .form-control:disabled, .form-select:disabled, .form-control[readonly] {
            background-color: #e9ecef !important;
            opacity: 1;
        }

        /* Select2 Styling Fixes (PENTING untuk kotak kemas) */
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
        /* Category Pills */
        .category-pills-container { 
            display: flex; flex-wrap: nowrap; overflow-x: auto; 
            -webkit-overflow-scrolling: touch; padding-bottom: 12px; 
            margin-bottom: 1rem;
        }
        .category-pills-container .category-pill-filter { 
            white-space: nowrap; padding: 6px 14px; font-size: 14px; 
            margin-right: 8px; border-radius: 20px; font-weight: 600;
        }
        .btn-outline-secondary { /* Custom style untuk pill tak aktif */
            background-color: #e2e8f0;
            border-color: #e2e8f0;
            color: var(--text-dark);
        }

        /* Item List Display - Imej */
        .category-image-box { 
            width: 50px; /* Saiz Imej Sedikit Besar */
            height: 50px; 
            border-radius: 6px; /* Sudut sedikit melengkung */
            display: flex; 
            align-items: center; 
            justify-content: center; 
            overflow: hidden; 
            border: 1px solid #e2e8f0;
            margin-right: 12px;
            flex-shrink: 0; /* Pastikan ia tidak mengecil */
        } 
        .category-thumb-img {
            width: 100%;
            height: 100%;
            object-fit: cover; /* Pastikan imej penuh kotak tanpa herot */
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
            
            /* Topbar Mobile Layout */
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

            /* Content Padding */
            .container-fluid {
                padding: 15px;
            }
            .card {
                padding: 15px;
            }
            
            /* Force full width on small screens */
            .col-lg-8, .col-lg-4 {
                 flex: 0 0 100% !important;
                 max-width: 100% !important;
            }
            
            /* Grid layout for buttons/divs */
            .d-grid {
                display: grid !important;
                grid-template-columns: 1fr;
                gap: 10px !important;
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
        <a href="item_user.php" class="active"><i class="fa-solid fa-box"></i> Item Availability</a>
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
                    <p class="text-muted small">View all available items and their categories.</p>
                    
                    <div class="list-group list-group-flush" style="max-height: 450px; overflow-y: auto;">
                    
                        <?php if (empty($all_display_items)): ?>
                            <div class="text-center text-muted p-3">No items found in the database.</div>
                        <?php else: ?>
                            <?php foreach ($all_display_items as $item): ?>
                                <div class="list-group-item d-flex align-items-center p-2">
                                    <div class="category-image-box"> 
                                        <img src="<?= htmlspecialchars(isset($item['image_url']) ? $item['image_url'] : '../assets/placeholder.png') ?>" 
                                             alt="<?= htmlspecialchars($item['item_name']) ?>" 
                                             class="category-thumb-img">
                                    </div>
                                    <div>
                                        <strong><?= htmlspecialchars($item['item_name']) ?></strong><br>
                                        <small class="text-muted"><?= htmlspecialchars($item['category_name']) ?></small>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <div class="col-lg-7 order-lg-1">
                <div class="card">
                    <h5><i class="fa-solid fa-file-pen me-2 text-primary"></i> Request Form</h5>
                    <p class="text-muted small">Fill in the details to check item availability in real-time.</p>
                    <hr>
                    <form id="reserveForm">
                        <input type="hidden" name="person_id" value="<?= isset($person_id) ? $person_id : '' ?>">
                        <input type="hidden" name="all_items" id="allItems">

                        <div class="mb-3">
                            <label class="form-label" for="item_select">1. Filter by Category:</label>
                            <div class="category-pills-container">
                                <a href="#" class="btn btn-sm btn-primary category-pill-filter" data-category="">
                                    <i class="fa-solid fa-list-ul me-1"></i> All Items
                                </a>
                                <?php foreach ($categories as $category): ?>
                                    <a href="#" class="btn btn-sm btn-outline-secondary ms-2 category-pill-filter" 
                                        data-category="<?= htmlspecialchars($category['category_name']) ?>">
                                        <?= htmlspecialchars($category['category_name']) ?>
                                    </a>
                                <?php endforeach; ?>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">2. Select Item</label>
                            <select id="item_select" class="form-select" style="width: 100%;">
                                <option value="">-- Search and select an item --</option>
                                <?php
                                $current_category = null;
                                foreach ($items_for_dropdown as $item) {
                                    if ($item['category_name'] !== $current_category) {
                                        if ($current_category !== null) {
                                            echo '</optgroup>';
                                        }
                                        echo '<optgroup label="' . htmlspecialchars($item['category_name']) . '">';
                                        $current_category = $item['category_name'];
                                    }
                                    echo '<option value="' . htmlspecialchars($item['item_name']) . '" data-category-id="' . htmlspecialchars($item['category_id']) . '">';
                                    echo htmlspecialchars($item['item_name']);
                                    echo '</option>';
                                }
                                if ($current_category !== null) {
                                    echo '</optgroup>';
                                }
                                ?>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label" for="quantity">3. Quantity</label>
                            <input type="number" id="quantity" class="form-control" name="quantity" min="1" value="1">
                        </div>
                        
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label class="form-label" for="reserveDate">4. Borrow Date</label>
                                <input type="text" id="reserveDate" class="form-control" name="reserve_date" placeholder="Select a date...">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label" for="returnDate">5. Return Date</label>
                                <input type="text" id="returnDate" class="form-control" name="return_date" placeholder="Select a date...">
                            </div>
                        </div>
                                                
                        <div class="mb-3">
                            
<div class="card bg-light border-info mb-4 p-3">
    <h6 class="mb-2 text-primary"><i class="fa-solid fa-clipboard-question me-2"></i> Booking Context (Step 1)</h6>
    <p class="text-muted small">This purpose applies to <strong>all items</strong> in your request list.</p>

    <div class="mb-3">
        <label class="form-label" for="program_type">1. Program Type / Priority</label>
        <select name="program_type" id="program_type" class="form-select" required>
            <option value="3" selected>Academic Project/Class</option>
            <option value="2">Club/Association Program</option>
            <option value="1">Official University Ceremony</option>
        </select>
    </div>

    <div class="mb-1">
        <label class="form-label" for="reason">2. Purpose of Loan</label>
        <textarea id="reason" name="reason" class="form-control" placeholder="e.g., For Final Year Project presentation" required></textarea>
        <div id="reason-help-text" class="mt-2"></div>
    </div>
</div>
</div>
	<div id="availability-status" class="mt-3"></div>
                        
                        <div class="d-grid d-md-flex gap-2 mt-4">
                            <button type="button" class="btn btn-light border flex-grow-1" id="addMoreBtn" disabled><i class="fa-solid fa-plus me-2"></i> Add to List</button>
                        </div>
                    </form>
                </div>
                
                <div class="card">
                    <h5><i class="fa-solid fa-clipboard-list me-2 text-primary"></i> Your Request List</h5>
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
                            <button type="button" class="btn btn-primary" id="finalSubmitBtn" disabled><i class="fa-solid fa-paper-plane me-2"></i> Submit Request</button>
                    </div>
                    </div>
                </div>
            
        </div>
    </div>
</div>
<div class="modal fade" id="termsModal" tabindex="-1" aria-labelledby="termsModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
        <div class="modal-header">
            <h5 class="modal-title" id="termsModalLabel">Terms and Conditions of **Equipment Usage**</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
<div class="modal-body">
    <p>Please read the following terms carefully before submitting your **reservation** request:</p>
    <ol>
        <li>
            **Eligibility:** All equipment is available for **reservation** only to registered students and staff of UniKL with a valid ID.
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
</div>      <div class="modal-footer">
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
$(document).ready(function() {
    
    const phpData = {
        user_id: '<?= isset($person_id) ? $person_id : '' ?>',
    };

    
    $('#item_select').select2({
        theme: 'bootstrap-5', 
        placeholder: "-- Search and select an item --",
        allowClear: true,
    });

    let allOptgroups = $('#item_select optgroup').clone();
    
    if (allOptgroups.length === 0 && $('#item_select option').length > 1) {
        allOptgroups = $('#item_select optgroup').clone(); 
    }

    
    $(document).on('click', '.category-pill-filter', function(e) {
        e.preventDefault();
        const categoryName = $(this).data('category').toString().trim();
        const $select = $('#item_select');

        
        $('.category-pill-filter').removeClass('btn-primary').addClass('btn-outline-secondary');
        $(this).removeClass('btn-outline-secondary').addClass('btn-primary');

        
        $select.empty().append('<option value="">-- Search and select an item --</option>');

        if (categoryName === "") {
            $select.append(allOptgroups.clone());
        } else {
            allOptgroups.each(function() {
                const optgroupLabel = $(this).attr('label').trim();
                if (optgroupLabel === categoryName) {
                    $select.append($(this).clone());
                }
            });
        }

        
        $select.val(null).trigger('change');
    });
    
    
    const sidebar = document.querySelector('.sidebar');
    const overlay = document.getElementById('overlay');
    const menuToggle = document.getElementById('menuToggle');

    
    if (menuToggle) {
        menuToggle.addEventListener('click', function() {
            sidebar.classList.toggle('active');
            if (sidebar.classList.contains('active')) {
                overlay.style.display = 'block';
            } else {
                overlay.style.display = 'none';
            }
        });
    }

    
    if (overlay) { 
        overlay.addEventListener('click', function() {
            sidebar.classList.remove('active');
            overlay.style.display = 'none'; 
        }); 
    }
    
    
    window.addEventListener('resize', function() {
        if (window.innerWidth > 992 && sidebar.classList.contains('active')) {
            sidebar.classList.remove('active');
            overlay.style.display = 'none';
        }
    });
    
    
    
    let debounceTimer;

    function checkAvailability() {
        clearTimeout(debounceTimer);
        debounceTimer = setTimeout(() => {
            const itemName = $('#item_select').val();
            const quantity = $('#quantity').val();
            const reserve = $('#reserveDate').val();
            const ret = $('#returnDate').val();
            const statusDiv = $('#availability-status');
            const addBtn = $('#addMoreBtn');

            const reason = $('#reason').val(); 
            const program_type = $('#program_type').val(); 

            if (itemName && quantity > 0 && reserve && ret && reason && program_type) {
                statusDiv.html('<div class="text-muted"><span class="spinner-border spinner-border-sm"></span> Checking availability...</div>');
                addBtn.prop('disabled', true);

                $.ajax({
                    type: 'POST',
                    url: 'check_availability.php',
                    data: { 
                        item_name: itemName,
                        quantity: quantity,
                        start_date: reserve,
                        end_date: ret
                    },
                    dataType: 'json',
                    success: function(response) {
                        if (response.status === 'success') {
                            statusDiv.html('<div class="alert alert-success py-2">✅ <strong>Available!</strong> You can add this item.</div>');
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
            } else {
                statusDiv.html('');
                addBtn.prop('disabled', true); 
            }
        }, 500); 
    }

    
    $(document).on('click', '#book-available-btn', function() {
        const availableCount = $(this).data('available');
        $('#quantity').val(availableCount); 
        checkAvailability(); 
    });

    
    $('#item_select').on('change', checkAvailability);
    $('#quantity').on('input', checkAvailability); 
    $('#reason').on('input change', checkAvailability);
    $('#program_type').on('change', checkAvailability); 

    
    
    const returnDatepicker = flatpickr("#returnDate", {
        dateFormat: "Y-m-d",
        minDate: "today",
        onClose: checkAvailability 
    });

    const reserveDatepicker = flatpickr("#reserveDate", {
        dateFormat: "Y-m-d",
        minDate: "today",
        onChange: (selectedDates) => {
            
            if (selectedDates.length > 0) {
                returnDatepicker.set('minDate', selectedDates[0]);
            }
        },
        onClose: checkAvailability 
    });

    
    let reservationItems = []; 
    
    


function updateReasonAndPriorityStatus() {
    const reasonField = $('#reason');
    const programTypeField = $('#program_type');
    const reasonHelpText = $('#reason-help-text'); 

    if (reservationItems.length > 0) {
        
        reasonField.prop('disabled', true).addClass('bg-light');
        programTypeField.prop('disabled', true).addClass('bg-light');
        
        
        reasonHelpText.html('<div class="alert alert-danger py-2 small"><i class="fa-solid fa-lock me-2"></i> Locked: To change, please remove all items from the list first.</div>');
        
    } else {
        
        reasonField.prop('disabled', false).removeClass('bg-light');
        programTypeField.prop('disabled', false).removeClass('bg-light');
        
        reasonHelpText.empty(); 
    }
    checkAvailability(); 
}


    $('#addMoreBtn').on('click', () => {
        const itemName = $('#item_select').val();
        const quantity = $('#quantity').val();
        const reserve = $('#reserveDate').val();
        const ret = $('#returnDate').val();
        
        
        const reason = $('#reason').val(); 
        const program_type = $('#program_type').val(); 

        
        if (!itemName || !quantity || !reserve || !ret || !reason.trim() || !program_type) {
            Swal.fire("Incomplete Form", "Please fill in all request details, including a reason and program type.", "warning");
            return;
        }
        
        
        if ($('#availability-status').find('.alert-success').length === 0 || $('#addMoreBtn').prop('disabled')) {
             Swal.fire("Not Confirmed", "Please ensure the item's availability is confirmed before adding it to the list.", "error");
             return;
        }

        
        if (reservationItems.some(item => item.item_name === itemName)) {
             Swal.fire("Duplicate Item", "This item is already in your list. Please remove it first if you want to change the details.", "info");
             return;
        }
        
        
        const newItem = { 
            item_name: itemName, 
            quantity: quantity, 
            reserve_date: reserve, 
            return_date: ret, 
            
            reason: reason, 
            program_type: program_type
        };
        
        reservationItems.push(newItem);
        renderItemsList(); 
        updateReasonAndPriorityStatus();

        
        const Toast = Swal.mixin({
            toast: true, position: 'top-end', showConfirmButton: false, timer: 3000, timerProgressBar: true
        });
        Toast.fire({ icon: 'success', title: 'Added to list!' });

        
        
        $('#item_select').val(null).trigger('change');
        $('#quantity').val(1);
        reserveDatepicker.clear();
        returnDatepicker.clear();
        
        $('#availability-status').html('');
    });

    
    function renderItemsList() {
        const listDiv = $('#itemsList');
        const submitBtn = $('#finalSubmitBtn');
        const agreeTermsCheckbox = $('#agreeTerms');

        if (reservationItems.length > 0) {
            let itemsHtml = reservationItems.map((it, i) => {
                let programTypeText = 'Unknown';
                if (it.program_type === '3') programTypeText = 'Academic Project/Class';
                if (it.program_type === '2') programTypeText = 'Club/Association Program';
                if (it.program_type === '1') programTypeText = 'Official University Ceremony';
                
                return `
                <div class="d-flex justify-content-between align-items-start bg-light p-3 rounded mb-2 border">
                    <div>
                        <b>${it.item_name}</b> (Qty: ${it.quantity})<br>
                        <small class="text-muted">
                            <b>Date:</b> ${it.reserve_date} to ${it.return_date}<br>
                            <b>Purpose:</b> ${it.reason}<br>
                            <b>Type:</b> ${programTypeText}
                        </small>
                    </div>
                    <button type="button" class="btn btn-sm btn-outline-danger ms-2 remove-item-btn" data-index="${i}"><i class="fa fa-trash-alt"></i></button>
                </div>
                `;
            }).join('');
            listDiv.html(`<div class="p-2">${itemsHtml}</div>`);
            
            submitBtn.prop('disabled', !agreeTermsCheckbox.is(':checked'));
        } else {
            listDiv.html(`<div class="text-center text-muted p-4"><i class="fa-solid fa-list-check fa-2x mb-2"></i><p>Your request list is empty.</p></div>`);
            submitBtn.prop('disabled', true);
        }
        
        updateReasonAndPriorityStatus(); 

        
        $('#allItems').val(JSON.stringify(reservationItems));
    }

    
    $('#itemsList').on('click', '.remove-item-btn', function() {
        const index = $(this).data('index');
        reservationItems.splice(index, 1); 
        renderItemsList(); 
        updateReasonAndPriorityStatus();
    });
    
    
    
    
    $('#agreeTerms').on('change', function() {
        const submitBtn = $('#finalSubmitBtn');
        if ($(this).is(':checked') && reservationItems.length > 0) {
            submitBtn.prop('disabled', false);
        } else {
            submitBtn.prop('disabled', true);
        }
    });

    
    $('#agreeTermsBtn').on('click', function() {
        
        $('#agreeTerms').prop('disabled', false);
        $('#agreeTerms').prop('checked', true).trigger('change');
    });

    
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
        
        const reasonValue = reservationItems.length > 0 ? reservationItems[0].reason : $('#reason').val().trim();
        const programTypeValue = reservationItems.length > 0 ? reservationItems[0].program_type : $('#program_type').val();
        
        if (!reasonValue) {
            Swal.fire("Incomplete Form", "Please ensure the Purpose of Loan is filled in.", "warning");
            return;
        }
        
        submitBtn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm"></span> Submitting...');
        
        const submissionData = {
            user_id: phpData.user_id,
            all_items: JSON.stringify(reservationItems),
            program_type: programTypeValue, 
            reason: reasonValue 
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
                        text: 'Your request has been submitted. You will be redirected to the history page.',
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
                
                Swal.fire("Submission Failed", "A server error occurred or file not found. Please try again.", "error");
            },
            complete: function() {
                
                const shouldBeEnabled = reservationItems.length > 0 && $('#agreeTerms').is(':checked');
                submitBtn.prop('disabled', !shouldBeEnabled).html('<i class="fa-solid fa-paper-plane me-2"></i> Submit Request');
            }
        });
    });
    
    
    renderItemsList();
});
</script>
</body>
</html>