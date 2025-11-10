<?php
session_start();
include '../config.php'; 

// Periksa sambungan database
if (!$conn) {
    die("Database connection failed: " . mysqli_connect_error());
}

// Periksa status log masuk
if (!isset($_SESSION['user_id'])){
    header("Location: login.php");
    exit();
}

$user_id = (int) $_SESSION['user_id'];

// Ambil maklumat pengguna
$stmt = $conn->prepare("SELECT name, email, phoneNum FROM user WHERE user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();
$stmt->close();

if (!$user) {
    session_destroy();
    header("Location: login.php");
    exit();
}

// Ambil semua kategori untuk filter dan senarai
$categories = [];
$res_cat = $conn->query("SELECT * FROM categories ORDER BY category_name");
if ($res_cat) {
    while ($row = $res_cat->fetch_assoc()) {
        $categories[] = $row;
    }
}

// Ambil semua item untuk dropdown menu
$items_for_dropdown = [];
$sql_all_items = "
    SELECT 
        i.item_name, c.category_name
    FROM item i
    JOIN categories c ON i.category_id = c.category_id
    GROUP BY i.item_name, c.category_name
    ORDER BY c.category_name, i.item_name ASC
";
$res_items = $conn->query($sql_all_items);
if ($res_items) {
    while ($row = $res_items->fetch_assoc()) {
        $items_for_dropdown[] = $row;
    }
}


$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0"> <title>Item Availability — UniKL</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.13/css/select2.min.css" />
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@100..999&display=swap" rel="stylesheet">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css" rel="stylesheet">
    <style>
        /* DEFINING TEAL COLOR AS PRIMARY & MODERN STYLING */
        :root {
            --primary-color: #06b6d4; /* Cyan 600 */
            --primary-hover: #0891b2; /* Cyan 700 */
            --bg-light-gray: #f8fafc; /* Latar belakang utama yang sangat lembut */
            --card-bg: #ffffff;
            --text-dark: #1e293b;
            --text-muted: #64748b;
        }

        /* CSS DEFAULT DESKTOP */
        body { 
            font-family: 'Inter', 'Segoe UI', sans-serif; 
            background-color: var(--bg-light-gray); 
            color: var(--text-dark); 
            min-height: 100vh; 
        }
        .sidebar { 
            width: 250px; 
            position: fixed; 
            top: 0; 
            bottom: 0; 
            left: 0; 
            background: var(--card-bg);
            padding: 20px; 
            border-right: 1px solid #e2e8f0; 
            z-index: 1000; 
            display: flex; 
            flex-direction: column; 
            justify-content: space-between; 
            transition: transform 0.3s ease-in-out; 
        }
        .sidebar-header { display: flex; align-items: center; gap: 12px; margin-bottom: 30px; }
        .logo-icon { 
            width: 40px; 
            height: 40px; 
            background-color: var(--primary-color); /* TEAL */
            color: white; 
            border-radius: 8px; 
            display: flex; 
            align-items: center; 
            justify-content: center; 
            font-size: 20px; 
        }
        .logo-text strong { display: block; font-size: 16px; color: var(--text-dark); }
        .logo-text span { font-size: 12px; color: var(--text-muted); }
        .sidebar a { display: flex; align-items: center; gap: 12px; color: var(--text-muted); text-decoration: none; padding: 12px 15px; margin-bottom: 8px; border-radius: 8px; font-weight: 500; font-size: 15px; transition: all 0.2s; }
        .sidebar a.active, .sidebar a:hover { 
            background: var(--primary-color); /* TEAL */
            color: #fff; 
        }
        .sidebar a.logout-link { color: #ef4444; font-weight: 600; margin-top: auto; }
        .sidebar a.logout-link:hover { color: #fff; background: #ef4444; }
        .main-content { margin-left: 250px; transition: margin-left 0.3s ease-in-out; }
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
        .container-fluid { padding: 30px; }
        .card { 
            border-radius: 16px; 
            padding: 25px; 
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05); 
            background: var(--card-bg); 
            margin-bottom: 25px; 
            border: 1px solid #e2e8f0; 
        }
        .card h5 { font-weight: 600; color: var(--text-dark); margin-bottom: 5px; }
        
        .text-primary { color: var(--primary-color) !important; } /* TEAL */
        .btn-primary { 
            background-color: var(--primary-color); /* TEAL */
            border-color: var(--primary-color); /* TEAL */
        }
        .btn-primary:hover { 
            background-color: var(--primary-hover); /* Darker TEAL */
            border-color: var(--primary-hover); /* Darker TEAL */
        }

        .form-label { font-weight: 500; color: #334155; }
        .form-control, .form-select { border-radius: 8px; }
        .btn { border-radius: 8px; padding: 10px 20px; font-weight: 500; }
        
        .category-thumb { width: 70px; height: 70px; object-fit: cover; border-radius: 8px; margin-right: 12px; }
        .list-group-flush .list-group-item { padding-left: 0; padding-right: 0; }
        .select2-container--default .select2-selection--single { border: 1px solid #dee2e6; border-radius: 8px; height: 44px; }
        .select2-container--default .select2-selection--single .select2-selection__rendered { line-height: 42px; padding-left: 12px; }
        .select2-container--default .select2-selection--single .select2-selection__arrow { height: 42px; }
        .category-pills-container { display: flex; flex-wrap: nowrap; overflow-x: auto; -webkit-overflow-scrolling: touch; padding-bottom: 12px; margin-bottom: 1rem; }
        .category-pills-container .category-pill-filter { white-space: nowrap; padding: 6px 14px; font-size: 14px; }
        .menu-toggle-btn { display: none; }
        #overlay { position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0, 0, 0, 0.5); z-index: 999; display: none; }


        /* MOBILE STYLES START */
        @media (max-width: 992px) {
            /* Sidebar & Main Content Toggling */
            .sidebar { 
                transform: translateX(-250px); 
                left: 0;
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
                /* Susun: Menu | Header | Profile */
                display: grid;
                grid-template-columns: auto 1fr auto;
                align-items: center;
                gap: 15px;
            }
            .topbar h3 {
                font-size: 18px;
                text-align: center;
            }
            .topbar .user-name {
                display: none; /* Sembunyikan nama di mobile */
            }

            /* Container & Card Padding */
            .container-fluid {
                padding: 15px;
            }
            .card {
                padding: 15px;
            }
            
            /* Form & Request List (Susunan Menegak 100%) */
            .col-lg-7, .col-lg-5 {
                flex: 0 0 100%;
                max-width: 100%;
            }
            
            /* Category Pills */
            .category-pills-container {
                padding-bottom: 0; /* Kurangkan padding */
            }

            /* Buttons */
            .d-grid {
                display: grid !important;
                grid-template-columns: 1fr;
                gap: 10px !important;
            }
            /* Bootstrap order classes are used in HTML to change column order on mobile */
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
            
            <div class="col-lg-5 order-1 order-lg-2">
                <div class="card">
                    <h5><i class="fa-solid fa-layer-group me-2 text-primary"></i> Item Categories</h5>
                    <p class="text-muted small">A visual guide of our main categories.</p>
                    <div class="list-group list-group-flush">
                        <?php foreach ($categories as $category): ?>
                            <div class="list-group-item d-flex align-items-center p-2">
                                <img src="../<?= htmlspecialchars($category['image_url'] ?: 'assets/img/default-thumb.jpg') ?>" class="category-thumb" alt="<?= htmlspecialchars($category['category_name']) ?>">
                                <div><strong><?= htmlspecialchars($category['category_name']) ?></strong></div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
            
            <div class="col-lg-7 order-2 order-lg-1">
                <div class="card">
                    <h5><i class="fa-solid fa-file-pen me-2 text-primary"></i> Request Form</h5>
                    <p class="text-muted small">Fill in the details to check item availability in real-time.</p>
                    <hr>
                    <form id="reserveForm" method="POST" action="submit_reservation.php">
                        <input type="hidden" name="user_id" value="<?= $user_id ?>">
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
                            <select id="item_select" class="form-select" style="width: 100%;"><option value="">-- Search and select an item --</option>
                                <?php
                                $current_category = null;
                                foreach ($items_for_dropdown as $item) {
                                    
                                    $item_category_name = trim($item['category_name']); 
                                    if ($item_category_name !== $current_category) {
                                        if ($current_category !== null) echo '</optgroup>';
                                        $current_category = $item_category_name;
                                        echo '<optgroup label="' . htmlspecialchars($current_category) . '">';
                                    }
                                    echo '<option value="' . htmlspecialchars($item['item_name']) . '">' . htmlspecialchars($item['item_name']) . '</option>';
                                }
                                if ($current_category !== null) echo '</optgroup>';
                                ?>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label" for="quantity">3. Quantity</label>
                            <input type="number" id="quantity" class="form-control" min="1" value="1">
                        </div>
                        
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label class="form-label" for="reserveDate">4. Borrow Date</label>
                                <input type="text" id="reserveDate" class="form-control" placeholder="Select a date...">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label" for="returnDate">5. Return Date</label>
                                <input type="text" id="returnDate" class="form-control" placeholder="Select a date...">
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label" for="reason">6. Purpose of Loan</label>
                            <textarea id="reason" class="form-control" placeholder="e.g., For Final Year Project presentation" required></textarea>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label" for="program_type">7. Program Type (Priority)</label>
                            <select name="program_type" id="program_type" class="form-select" required>
                                <option value="3" selected>Academic Project/Class</option>
                                <option value="2">Club/Association Program</option>
                                <option value="1">Official University Ceremony</option>
                            </select>
                        </div>
                        
                        <div id="availability-status" class="mt-3"></div>
                        
                        <div class="form-check mt-4">
                            <input class="form-check-input" type="checkbox" value="" id="agreeTerms">
                            <label class="form-check-label" for="agreeTerms">
                                I have read and agree to the 
                                <a href="#" data-bs-toggle="modal" data-bs-target="#termsModal" class="text-primary">Terms and Conditions</a>.
                            </label>
                        </div>

                        <div class="d-grid d-md-flex gap-2 mt-4">
                             <button type="button" class="btn btn-light border flex-grow-1" id="addMoreBtn"><i class="fa-solid fa-plus me-2"></i> Add to List</button>
                             <button type="submit" class="btn btn-primary flex-grow-1"><i class="fa-solid fa-paper-plane me-2"></i> Submit Request</button>
                        </div>
                    </form>
                </div>
                <div class="card">
                    <h5><i class="fa-solid fa-clipboard-list me-2 text-primary"></i> Your Request List</h5>
                    <div id="itemsList">
                        <div class="text-center text-muted p-4"><i class="fa-solid fa-list-check fa-2x mb-2"></i><p class="mb-0">Your request list is currently empty.</p></div>
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
            <h5 class="modal-title" id="termsModalLabel">Terms and Conditions of Borrowing</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
            <p>Please read the following terms carefully before submitting your request:</p>
            <ol>
                <li><strong>Eligibility:</strong> All items are available for loan only to registered students and staff of UniKL with a valid ID.</li>
                <li><strong>Loan Period:</strong> The loan period is as specified in your request. Any extensions must be requested through the system 24 hours before the due date and are subject to availability.</li>
                <li><strong>Responsibility:</strong> The borrower is fully responsible for the borrowed item(s) from the moment of collection until they are returned and checked in by a technician.</li>
                <li><strong>Condition of Items:</strong> The borrower must inspect the item(s) at the time of collection. Any existing damage must be reported immediately, or the borrower may be held responsible.</li>
                <li><strong>Damage or Loss:</strong> The borrower will be held financially responsible for the full replacement cost of any lost, stolen, or damaged items (including all parts and accessories).</li>
                <li><strong>Late Returns:</strong> Failure to return items by the specified return date will result in a fine (e.i. RM10 per item per day) and a temporary suspension of borrowing privileges.</li>
                <li><strong>Purpose:</strong> Items are to be used for academic or official university purposes only.</li>
                <li><strong>Collection:</strong> Approved items must be collected within 24 hours of the "Approved" status, or the reservation may be cancelled.</li>
            </ol>
            <p class="fw-bold">By checking the box, you acknowledge that you have read, understood, and agree to be bound by all the terms and conditions stated above.</p>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-primary" data-bs-dismiss="modal">I Understand</button>
        </div>
        </div>
    </div>
</div>
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.13/js/select2.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
$(document).ready(function() {
    
    $('#item_select').select2({
        placeholder: "-- Search and select an item --",
        allowClear: true
    });

    let allOptgroups = $('#item_select optgroup').clone();

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
        $select.select2('open');
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

            if (itemName && quantity > 0 && reserve && ret) {
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
                            addBtn.prop('disabled', true); 

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
                addBtn.prop('disabled', false);
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
    $('#addMoreBtn').on('click', () => {
        const itemName = $('#item_select').val();
        const quantity = $('#quantity').val();
        const reserve = $('#reserveDate').val();
        const ret = $('#returnDate').val();
        const reason = $('#reason').val(); 

        if (!itemName || !quantity || !reserve || !ret || !reason.trim()) {
            Swal.fire("Incomplete Form", "Please fill in all request details, including a reason.", "warning");
            return;
        }
        
        
        if ($('#availability-status').find('.alert-success').length === 0) {
            Swal.fire("Not Confirmed", "Please ensure the item's availability is confirmed before adding it to the list.", "error");
            return;
        }
        
        
        const newItem = { 
            item_name: itemName, 
            quantity: quantity, 
            reserve_date: reserve, 
            return_date: ret, 
            reason: reason,
            program_type: $('#program_type').val() 
        };
        
        reservationItems.push(newItem);
        renderItemsList();

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
        if (reservationItems.length > 0) {
            let itemsHtml = reservationItems.map((it, i) => `
                <div class="d-flex justify-content-between align-items-start bg-light p-3 rounded mb-2 border">
                    <div>
                        <b>${it.item_name}</b> (Qty: ${it.quantity})<br>
                        <small class="text-muted">
                            <b>Date:</b> ${it.reserve_date} to ${it.return_date}<br>
                            <b>Purpose:</b> ${it.reason}
                        </small>
                    </div>
                    <button type="button" onclick="removeItem(${i})" class="btn btn-sm btn-outline-danger ms-2"><i class="fa fa-trash-alt"></i></button>
                </div>
            `).join('');
            listDiv.html(`<div class="p-2">${itemsHtml}</div>`);
        } else {
            listDiv.html(`<div class="text-center text-muted p-4"><i class="fa-solid fa-list-check fa-2x mb-2"></i><p>Your request list is empty.</p></div>`);
        }
        
        
        
        $('#allItems').val(JSON.stringify(reservationItems));
        
        
        $('#reserveForm').find('button[type="submit"]').prop('disabled', reservationItems.length === 0);
    }

    window.removeItem = function(index) {
        reservationItems.splice(index, 1);
        renderItemsList();
    }
    
    
    $('#reserveForm').find('button[type="submit"]').prop('disabled', true);


    $('#reserveForm').on('submit', function (e) {
        e.preventDefault();
        
        if (reservationItems.length === 0) {
            Swal.fire("Empty List", "Please add at least one item before submitting.", "error");
            return;
        }

        
        if (!$('#agreeTerms').is(':checked')) {
            Swal.fire("Terms and Conditions", "You must agree to the Terms and Conditions to proceed.", "warning");
            return; 
        }
        
        
        
        if (!$('#reason').val().trim()) {
            Swal.fire("Incomplete Form", "Please ensure the Purpose of Loan is filled in.", "warning");
            return;
        }

        const submitBtn = $(this).find('button[type="submit"]');
        submitBtn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm"></span> Submitting...');
        
        
        $.ajax({
            type: 'POST',
            url: 'submit_reservation.php',
            data: $(this).serialize(),
            dataType: 'json',
            success: function(response) {
                if(response.status === 'success') {
                    Swal.fire({
                        title: 'Success!',
                        text: 'Your request has been submitted. You will be redirected to history page.',
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
                submitBtn.prop('disabled', false).html('<i class="fa-solid fa-paper-plane me-2"></i> Submit Request');
            }
        });
    });
    
    
    renderItemsList();
});
</script>
</body>
</html>