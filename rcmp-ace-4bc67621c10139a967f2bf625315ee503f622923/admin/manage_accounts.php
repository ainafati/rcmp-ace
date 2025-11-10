<?php
session_start();
include 'config.php';

// Check database connection
if (!$conn) {
    die("Database connection failed: " . mysqli_connect_error());
}

// Ensure admin is logged in
if (!isset($_SESSION['admin_id'])) {
    header("Location: login.php");
    exit();
}
$admin_id = (int)$_SESSION['admin_id'];
$admin = ['name' => 'Admin'];
$stmt_admin = $conn->prepare("SELECT name FROM admin WHERE admin_id = ?");
$stmt_admin->bind_param("i", $admin_id);
$stmt_admin->execute();
$result_admin = $stmt_admin->get_result();
if ($admin_data = $result_admin->fetch_assoc()) {
    $admin = $admin_data;
}
$stmt_admin->close();

// Fetch accounts including suspension remarks
$sql = "
    (SELECT tech_id AS id, name, email, ic_num, status, suspension_remarks, phoneNum, 'Technician' AS role, created_at FROM technician)
    UNION ALL
    (SELECT user_id AS id, name, email, ic_num, status, suspension_remarks, phoneNum, 'User' AS role, created_at FROM user)
    ORDER BY created_at ASC
";

$result = $conn->query($sql);
if (!$result) {
    die("Error executing query: ". $conn->error);
}

// Fetch all results into the accounts array
$accounts = $result->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Manage User Accounts</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', 'Segoe UI', sans-serif; background-color: #f8fafc; color: #334155; min-height: 100vh; }
        .sidebar { width: 250px; position: fixed; top: 0; bottom: 0; left: 0; background: #ffffff; padding: 20px; border-right: 1px solid #e5e7eb; z-index: 1000; display: flex; flex-direction: column; justify-content: space-between; }
        .sidebar-header { display: flex; align-items: center; gap: 12px; margin-bottom: 30px; }
        .logo-icon { width: 40px; height: 40px; background-color: #3b82f6; color: white; border-radius: 8px; display: flex; align-items: center; justify-content: center; font-size: 20px; }
        .logo-text strong { display: block; font-size: 16px; color: #1e293b; }
        .logo-text span { font-size: 12px; color: #94a3b8; }
        .sidebar a { display: flex; align-items: center; gap: 12px; color: #64748b; text-decoration: none; padding: 12px 15px; margin-bottom: 8px; border-radius: 8px; font-weight: 500; font-size: 15px; transition: all 0.2s ease-in-out; }
        .sidebar a.active, .sidebar a:hover { background: #3b82f6; color: #fff; }
        .sidebar a.logout-link { color: #ef4444; font-weight: 600; margin-top: auto; }
        .sidebar a.logout-link:hover { color: #fff; background: #ef4444; }
        .main-content { margin-left: 250px; }
        .topbar { background: #ffffff; padding: 15px 30px; display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid #e5e7eb; }
        .topbar h3 { font-weight: 600; margin: 0; color: #1e293b; font-size: 22px; }
        .topbar .user-profile { display: flex; align-items: center; gap: 12px; }
        .topbar .user-name { font-weight: 600; font-size: 15px; color: #334155; }
        .container-fluid { padding: 30px; }
        .card { border-radius: 16px; padding: 25px; box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05); background: #fff; margin-bottom: 25px; border: 1px solid #e2e8f0; }
        .card h5, .modal-title { font-weight: 600; color: #1e293b; }
        .table thead th { background: #f8fafc; color: #64748b; border: none; font-weight: 600; text-transform: uppercase; font-size: 12px; }
        .table tbody td { border-bottom: 1px solid #f1f5f9; vertical-align: middle;}
        .table tbody tr:last-child td { border-bottom: none; }
        .badge.rounded-pill { padding: .4em .8em; font-weight: 500; }
        .btn { border-radius: 8px; padding: 10px 20px; font-weight: 500; }
        .btn-primary { background-color: #3b82f6; border: none; }
        .btn-primary:hover { background-color: #2563eb; }
        .search-bar { display:flex; gap:10px; margin-bottom:20px; }
        .search-bar input, .search-bar select { border-radius: 8px; }
        #editRemarksContainer { margin-top: 1rem; }
        
        /* MOBILE STYLES START */
        .sidebar { transition: transform 0.3s ease-in-out; z-index: 1001; }
        .main-content { margin-left: 250px; transition: margin-left 0.3s ease-in-out; }
        .menu-toggle-btn { display: none; } /* Sembunyikan pada desktop */
        
        #overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5); 
            z-index: 999; 
            display: none; 
        }

        @media (max-width: 992px) {
            /* Mobile Sidebar & Main Content Control (Kekal) */
            .sidebar { 
                transform: translateX(-250px); 
                left: 0;
                width: 250px;
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
            .topbar {
                padding: 10px 15px;
                position: sticky;
                top: 0;
            }
            .topbar h3 {
                font-size: 18px;
            }
            .topbar .user-name {
                display: none; 
            }
            .menu-toggle-btn { 
                display: inline-block; 
                order: -1; 
            }
            .container-fluid {
                padding: 15px;
            }
            .card {
                padding: 15px;
            }
            .search-bar {
                flex-direction: column;
            }
            
            /* Table Responsiveness: Wajib untuk tatal mendatar */
            .table-responsive {
                overflow-x: auto;
            }
            
            /* === STYLING JADUAL JADI SANGAT PADAT (AGRESIF) === */
            .table {
                min-width: 700px; /* Lebar minimum untuk mengelakkan teks 'berlonggok' */
                font-size: 12px; /* Saiz fon kecil */
            }
            
            /* PADDING SANGAT KETAT */
            .table tbody td {
                padding: 4px 6px; /* Kurangkan padding atas/bawah & kiri/kanan secara drastik */
            }
            .table thead th {
                padding: 3px 6px; /* Header pun rapat */
                font-size: 11px;
            }
            
            /* Saiz teks */
            .table tbody td strong {
                font-size: 13px; /* Kekalkan nama agar senang dibaca sedikit */
                display: block;
            }
            .table tbody td small {
                font-size: 9px; /* Kecilkan fon nombor telefon */
                opacity: 0.8; 
            }
            .table tbody td:nth-child(2) { /* Rapat lagi untuk Email & Phone */
                white-space: nowrap; /* Elak email terputus baris */
            }
            
            /* Jadikan butang Action sangat rapat dan kecil */
            .table tbody td .btn {
                padding: 3px 5px; /* Butang sangat kecil */
                font-size: 9px;
                margin-right: 1px;
                line-height: 1; /* Rapatkan baris */
            }
            .table tbody td .btn i {
                font-size: 10px; /* Saiz ikon pun kecil */
            }

            /* Pastikan badge kecil */
            .badge.rounded-pill {
                font-size: 8px; /* Badge paling kecil */
                padding: .1em .3em;
            }
        }
    </style>
</head>
<body>

<div id="overlay"></div> 

<div class="sidebar">
    <div>
        <div class="sidebar-header">
            <div class="logo-icon"><i class="fa-solid fa-user-shield"></i></div>
            <div class="logo-text"><strong>UniKL Admin</strong><span>System Control</span></div>
        </div>
        <a href="manageItem_admin.php" ><i class="fa-solid fa-box-archive"></i> Manage Items</a>
        <a href="manage_accounts.php" class="active"><i class="fa-solid fa-users-cog"></i> Manage Accounts</a>
        <a href="report_admin.php" ><i class="fa-solid fa-chart-pie"></i> System Report</a>
    </div>
    <a href="logout.php" class="logout-link"><i class="fa-solid fa-right-from-bracket"></i> Logout</a>
</div>

<div class="main-content">
    <div class="topbar">
        <button class="btn btn-sm btn-outline-secondary menu-toggle-btn" id="menuToggle">
            <i class="fa fa-bars"></i>
        </button>
        <h3>Manage User Account</h3>
        <div class="d-flex align-items-center gap-3">
            <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addUserModal"><i class="fa fa-user-plus me-2"></i> Add New Account</button>
            <div class="user-profile">
                <span class="user-name"><?= htmlspecialchars($admin['name']) ?></span>
                <a href="profile_admin.php" title="Go to My Profile" style="color: inherit; text-decoration: none;">
                <i class="fa-solid fa-user-circle fa-2x text-secondary"></i>
            </a>
            </div>
        </div>
    </div>
    <div class="container-fluid">
        <?php if (isset($_SESSION['success_message'])): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <?= htmlspecialchars($_SESSION['success_message']); unset($_SESSION['success_message']); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
        <?php if (isset($_SESSION['error_message'])): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <?= htmlspecialchars($_SESSION['error_message']); unset($_SESSION['error_message']); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <div class="card">
            <div class="search-bar">
                <input type="text" id="searchInput" class="form-control" placeholder="Search by name or email..." onkeyup="filterTable()">
                <select id="roleFilter" class="form-select" onchange="filterTable()">
                    <option value="">All Roles</option><option value="User">User</option><option value="Technician">Technician</option>
                </select>
                <select id="statusFilter" class="form-select" onchange="filterTable()">
                    <option value="">All Status</option><option value="Active">Active</option><option value="Suspended">Suspended</option>
                </select>
            </div>
            <div class="table-responsive">
                <table class="table table-hover align-middle" id="userTable">
                    <thead>
                        <tr>
                            <th>Name</th><th>Email & Phone</th><th>Status</th><th>Role</th><th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($accounts) > 0): foreach ($accounts as $a): ?>
                            <tr>
                                <td><strong><?= htmlspecialchars($a['name']) ?></strong></td>
                                <td>
                                    <?= htmlspecialchars($a['email']) ?><br>
                                    <small class="text-muted"><?= htmlspecialchars(isset($a['phoneNum']) ? $a['phoneNum'] : 'N/A') ?></small>
                                </td>
                                <td><span class="badge rounded-pill <?= strtolower($a['status']) === 'active' ? 'text-bg-success' : 'text-bg-danger' ?>"><?= htmlspecialchars($a['status']) ?></span></td>
                                <td><span class="badge rounded-pill text-bg-info"><?= htmlspecialchars($a['role']) ?></span></td>
                                <td>
                                    <button class="btn btn-sm btn-outline-warning" title="Edit User"
                                        onclick="editUser(
                                            '<?= $a['id'] ?>',
                                            '<?= htmlspecialchars(addslashes($a['name'])) ?>',
                                            '<?= htmlspecialchars(addslashes($a['email'])) ?>',
                                            '<?= htmlspecialchars(addslashes(isset($a['ic_num']) ? $a['ic_num'] : '')) ?>',
                                            '<?= htmlspecialchars(addslashes(isset($a['phoneNum']) ? $a['phoneNum'] : '')) ?>',
                                            '<?= htmlspecialchars(addslashes($a['role'])) ?>',
                                            '<?= htmlspecialchars(addslashes($a['status'])) ?>',
                                            '<?= htmlspecialchars(addslashes(isset($a['suspension_remarks']) ? $a['suspension_remarks'] : '')) ?>' 
                                        )">
                                        <i class="fa-solid fa-pen"></i>
                                    </button>
                                    <a href="delete_user.php?id=<?= $a['id'] ?>&role=<?= urlencode($a['role']) ?>" onclick="return confirm('Are you sure you want to delete this account? This action cannot be undone.')" class="btn btn-sm btn-outline-danger" title="Delete User">
                                        <i class="fa-solid fa-trash"></i>
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; else: ?>
                            <tr><td colspan="5" class="text-center text-muted py-5"><i class="fa-solid fa-users-slash fa-2x mb-2"></i><br>No accounts found.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="addUserModal" tabindex="-1">
    <div class="modal-dialog">
        <form action="save_user.php" method="POST" class="modal-content" id="addAccountForm">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title">Add New Account</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3"><label class="form-label">Name</label><input type="text" name="username" class="form-control" required></div>
                <div class="mb-3"><label class="form-label">Email (UniKL)</label>
                    <input type="email" name="email" class="form-control" required
                            pattern="[a-zA-Z0-9._%+-]+@unikl\.edu\.my"
                            title="Please enter a valid UniKL email address (e.g., name@unikl.edu.my)">
                </div>
                <div class="mb-3"><label class="form-label">IC Number (12-digit)</label>
                    <input type="text" name="ic_num" class="form-control" required
                            pattern="\d{12}"
                            title="Enter 12 digits IC number without dash (-)"
                            placeholder="e.g., 990101105000">
                </div>
                <div class="mb-3"><label class="form-label">Phone Number</label><input type="text" name="phoneNumber" class="form-control" required></div>
                <div class="mb-3"><label class="form-label">Password</label><input type="password" name="password" class="form-control" required></div>
                <p class="text-muted small">This form will automatically create a **Technician** account.</p>
                <input type="hidden" name="role" value="Technician">
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-primary">Save Account</button>
            </div>
        </form>
    </div>
</div>

<div class="modal fade" id="editUserModal" tabindex="-1">
    <div class="modal-dialog">
        <form action="update_user.php" method="POST" class="modal-content" id="editAccountForm">
            <div class="modal-header bg-warning">
                <h5 class="modal-title text-dark">Edit Account</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" name="id" id="editId">
                <input type="hidden" name="role" id="editRole">
                <div class="mb-3">
                    <label class="form-label">Name</label>
                    <input type="text" id="editName" name="username" class="form-control bg-light" readonly>
                </div>
                <div class="mb-3">
                    <label class="form-label">Email</label>
                    <input type="email" id="editEmail" name="email" class="form-control bg-light" readonly>
                </div>
                <div class="mb-3">
                    <label class="form-label">IC Number</label>
                    <input type="text" id="editIcNum" name="ic_num" class="form-control bg-light" readonly>
                </div>
                <div class="mb-3">
                    <label class="form-label">Phone Number</label>
                    <input type="text" name="phoneNum" id="editPhone" class="form-control">
                </div>
                <div class="mb-3">
                    <label class="form-label">Status</label>
                    <select name="status" id="editStatus" class="form-select" required>
                        <option value="Active">Active</option>
                        <option value="Suspended">Suspended</option>
                    </select>
                </div>
                <div id="editRemarksContainer" style="display: none;">
                    <label for="editRemarks" class="form-label">Suspension Remarks <span class="text-danger">*</span></label>
                    <textarea name="suspension_remarks" id="editRemarks" class="form-control" rows="3" placeholder="Reason for suspension..."></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-warning text-dark">Update Account</button>
            </div>
        </form>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<script>
// --- MOBILE SIDEBAR TOGGLE & OVERLAY CONTROL ---
const sidebar = document.querySelector('.sidebar');
const overlay = document.getElementById('overlay');
const menuToggle = document.getElementById('menuToggle');

// Fungsi untuk menukar keadaan sidebar
function toggleSidebar() {
    sidebar.classList.toggle('active');
    
    // Kawal paparan overlay
    if (sidebar.classList.contains('active')) {
        overlay.style.display = 'block';
    } else {
        overlay.style.display = 'none';
    }
}

// Apabila butang menu diklik
menuToggle.addEventListener('click', toggleSidebar);

// Tutup sidebar apabila mengklik overlay (hanya berfungsi di mobile)
if (overlay) { 
    overlay.addEventListener('click', toggleSidebar); 
}

// Tutup sidebar apabila mengubah saiz kepada desktop
window.addEventListener('resize', function() {
    if (window.innerWidth > 992 && sidebar.classList.contains('active')) {
        sidebar.classList.remove('active');
        overlay.style.display = 'none';
    }
});
// ------------------------------------------------

function editUser(id, name, email, ic_num, phone, role, status, remarks) {
    document.getElementById('editId').value = id;
    document.getElementById('editName').value = name;
    document.getElementById('editEmail').value = email;
    document.getElementById('editIcNum').value = ic_num;
    document.getElementById('editPhone').value = phone;
    document.getElementById('editStatus').value = status.trim();
    document.getElementById('editRole').value = role.trim();
    document.getElementById('editRemarks').value = remarks; // Set remarks

    // Show/Hide Remarks container based on initial status
    const remarksContainer = document.getElementById('editRemarksContainer');
    const remarksTextarea = document.getElementById('editRemarks');
    if (status.trim().toLowerCase() === 'suspended') {
        remarksContainer.style.display = 'block';
        remarksTextarea.required = true; // Make required if suspended
    } else {
        remarksContainer.style.display = 'none';
        remarksTextarea.required = false; // Make not required if active
    }

    new bootstrap.Modal(document.getElementById('editUserModal')).show();
}

// Function to filter the table based on search and dropdowns
function filterTable() {
    const search = document.getElementById('searchInput').value.toLowerCase();
    const role = document.getElementById('roleFilter').value.toLowerCase();
    const status = document.getElementById('statusFilter').value.toLowerCase();
    const rows = document.querySelectorAll('#userTable tbody tr');
    rows.forEach(row => {
        const name = row.cells[0].textContent.toLowerCase();
        const emailPhone = row.cells[1].textContent.toLowerCase();
        // Ensure status text is correctly extracted (might be inside a span)
        const statusSpan = row.cells[2].querySelector('.badge');
        const userStatus = statusSpan ? statusSpan.textContent.toLowerCase().trim() : '';
        // Ensure role text is correctly extracted
        const roleSpan = row.cells[3].querySelector('.badge');
        const userRole = roleSpan ? roleSpan.textContent.toLowerCase().trim() : '';

        const matchSearch = name.includes(search) || emailPhone.includes(search);
        const matchRole = role === '' || userRole.includes(role); 
        const matchStatus = status === '' || userStatus.includes(status);

        row.style.display = (matchSearch && matchRole && matchStatus) ? '' : 'none';
    });
}

// Client-side validation for Add Account form (UniKL email)
document.getElementById('addAccountForm').addEventListener('submit', function(e) {
    const emailInput = this.querySelector('input[name="email"]');
    const email = emailInput.value.trim();

    if (!email.endsWith('@unikl.edu.my')) {
        e.preventDefault(); // Stop form submission
        Swal.fire({
            icon: 'error',
            title: 'Invalid Email',
            text: 'Please enter a valid UniKL email address (e.g., name@unikl.edu.my)',
            didClose: () => {
                emailInput.focus(); // Re-focus the email input after closing
            }
        });
    }
    // IC validation is handled by the 'pattern' attribute
});

// Show/Hide Suspension Remarks in Edit Modal based on Status dropdown
document.getElementById('editStatus').addEventListener('change', function() {
    const selectedStatus = this.value.toLowerCase();
    const remarksContainer = document.getElementById('editRemarksContainer');
    const remarksTextarea = document.getElementById('editRemarks');

    if (selectedStatus === 'suspended') {
        remarksContainer.style.display = 'block';
        remarksTextarea.required = true; // Make required
    } else {
        remarksContainer.style.display = 'none';
        remarksTextarea.required = false; // Make not required
        remarksTextarea.value = ''; // Clear remarks when switching to Active
    }
});

// Client-side validation for Edit Account form (Remarks required if suspended)
document.getElementById('editAccountForm').addEventListener('submit', function(e) {
    const status = document.getElementById('editStatus').value.toLowerCase();
    const remarksTextarea = document.getElementById('editRemarks');
    const remarks = remarksTextarea.value.trim();

    if (status === 'suspended' && remarks === '') {
        e.preventDefault(); // Stop submission
        Swal.fire({
            icon: 'warning',
            title: 'Remarks Required',
            text: 'Please provide a reason for suspending this account.'
        }).then(() => {
             remarksTextarea.focus(); // Focus the textarea
        });
    }
});
</script>

</body>
</html>