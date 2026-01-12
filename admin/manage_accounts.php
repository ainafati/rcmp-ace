<?php
session_start();
include '../config.php';

if (!$conn) {
    die("Database connection failed: " . mysqli_connect_error());
}

$allowed_role = 'Admin';
if (!isset($_SESSION['person_id']) || $_SESSION['logged_in_role'] !== $allowed_role) {
    header("Location: login.php");
    exit();
}

$person_id = (int)$_SESSION['person_id'];
// Ambil nama penuh, pecahkan kepada perkataan
$full_name = isset($_SESSION['name']) ? $_SESSION['name'] : 'Admin';
$name_parts = explode(' ', trim($full_name));

// Ambil 2 perkataan pertama dan gabungkan semula
$admin_display_name = isset($name_parts[1]) ? $name_parts[0] . ' ' . $name_parts[1] : $name_parts[0];
$admin_name = htmlspecialchars($admin_display_name);
$sql = "
    SELECT
        p.person_id AS person_unique_id,
        p.name,
        p.email,
        p.id AS id,              -- Menggunakan lajur 'id' dari jadual person (Staff/Student ID)
        p.status,
        p.suspension_remarks,
        p.phoneNum,
        p.created_at,
        GROUP_CONCAT(r.role_name SEPARATOR ', ') AS roles_list
    FROM 
        person p
    JOIN 
        person_roles pr ON p.person_id = pr.person_id
    JOIN 
        roles r ON pr.role_id = r.role_id
    GROUP BY
        p.person_id, p.name, p.email, p.id, p.status, p.suspension_remarks, p.phoneNum, p.created_at
    ORDER BY
        p.created_at ASC
";

$result = $conn->query($sql);
if (!$result) {
    die("Error executing query: ". $conn->error);
}

$accounts = $result->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1"> 
    <title>Manage User Accounts</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
<style>
    /* Definisi Pembolehubah CSS */
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

    body {
        font-family: 'Inter', 'Segoe UI', sans-serif;
        background-color: var(--bg-light-gray);
        color: #334155;
        min-height: 100vh;
        overflow-x: hidden;
    }

    /* DESKTOP STYLES */
    .sidebar {
        width: 250px;
        position: fixed;
        top: 0;
        bottom: 0;
        left: 0;
        background: var(--card-bg);
        padding: 20px;
        border-right: 1px solid var(--border-color);
        z-index: 1000;
        display: flex;
        flex-direction: column;
        justify-content: space-between;
        transition: transform 0.3s ease;
    }

    .sidebar-overlay {
        display: none;
        position: fixed;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background: rgba(0, 0, 0, 0.5);
        z-index: 1040;
    }

    .sidebar-overlay.active {
        display: block;
    }

    .sidebar-header {
        display: flex;
        align-items: center;
        gap: 12px;
        margin-bottom: 30px;
    }

    /* THEME: Corporate Blue -> Cyan */
    .logo-icon {
        width: 40px;
        height: 40px;
        background-color: var(--primary-color);
        color: white;
        border-radius: 8px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 20px;
    }

    .logo-text strong {
        display: block;
        font-size: 16px;
        color: var(--text-dark);
    }

    .logo-text span {
        font-size: 12px;
        color: #94a3b8;
    }

    .sidebar a {
        display: flex;
        align-items: center;
        gap: 12px;
        color: var(--text-muted);
        text-decoration: none;
        padding: 12px 15px;
        margin-bottom: 8px;
        border-radius: 8px;
        font-weight: 500;
        font-size: 15px;
        transition: all 0.2s ease-in-out;
    }

    .sidebar a.active, .sidebar a:hover {
        background: var(--primary-color);
        color: #fff;
    }

    .sidebar a.logout-link {
        color: var(--danger-color);
        font-weight: 600;
        margin-top: auto;
    }

    .sidebar a.logout-link:hover {
        color: #fff;
        background: var(--danger-color);
    }

    .main-content {
        margin-left: 250px;
        transition: margin-left 0.3s ease;
    }

    .topbar {
        background: var(--card-bg);
        padding: 15px 30px;
        display: flex;
        justify-content: space-between;
        align-items: center;
        border-bottom: 1px solid var(--border-color);
    }

    .topbar h3 {
        font-weight: 600;
        margin: 0;
        color: var(--text-dark);
        font-size: 22px;
    }

    .topbar .user-profile {
        display: flex;
        align-items: center;
        gap: 12px;
    }

    .topbar .user-name {
        font-weight: 600;
        font-size: 15px;
        color: #334155;
    }

    .container-fluid {
        padding: 30px;
    }

    .card {
        border-radius: 16px;
        padding: 25px;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
        background: var(--card-bg);
        margin-bottom: 25px;
        border: 1px solid #e2e8f0;
    }

    .card h5, .modal-title {
        font-weight: 600;
        color: var(--text-dark);
    }

    .table thead th {
        background: var(--bg-light-gray);
        color: var(--text-muted);
        border: none;
        font-weight: 600;
        text-transform: uppercase;
        font-size: 12px;
    }

    .table tbody td {
        border-bottom: 1px solid #f1f5f9;
        vertical-align: middle;
    }

    .table tbody tr:last-child td {
        border-bottom: none;
    }

    /* KEMASKINI: Gaya untuk ikon dalam badge (RINGKAS) */
    .badge.rounded-pill {
        padding: .4em .8em;
        font-weight: 500;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        height: 30px;
        gap: 0;
    }

    /* KEMASKINI: Status (Active/Suspended) - Saiz tetap */
    .badge.text-bg-success, .badge.text-bg-danger {
        width: 30px; 
    }

    /* KEMASKINI: Role - Saiz auto untuk tooltip */
    .badge.text-bg-info { 
        background-color: #64748b !important; 
        color: white !important; 
        width: auto; 
        padding: .4em .8em;
    } 
    
    /* Warna badge menggunakan pembolehubah */
    .badge.text-bg-success { background-color: var(--primary-color) !important; color: white !important; }
    .badge.text-bg-danger { background-color: var(--danger-color) !important; color: white !important; }

    .btn {
        border-radius: 8px;
        padding: 10px 20px;
        font-weight: 500;
    }

    .btn-primary {
        background-color: var(--primary-color);
        border: none;
    }

    .btn-primary:hover {
        background-color: var(--primary-hover);
    }

    .search-bar {
        display: flex;
        gap: 10px;
        margin-bottom: 20px;
    }

    .search-bar input, .search-bar select {
        border-radius: 8px;
    }

    #editRemarksContainer {
        margin-top: 1rem;
    }

    /* --- MOBILE VIEW (MAX-WIDTH 768px) --- */
    #sidebar-toggle-btn {
        display: none;
        background: none;
        border: none;
        color: #334155;
        font-size: 20px;
        padding: 0;
        margin-right: 15px;
    }

    @media (max-width: 768px) {
        /* GENERAL LAYOUT */
        #sidebar-toggle-btn {
            display: block;
        }

        .sidebar {
            transform: translateX(-100%);
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.15);
            z-index: 1050;
        }

        .sidebar.open {
            transform: translateX(0);
        }

        .main-content {
            margin-left: 0;
            width: 100%;
        }

        .topbar {
            padding: 10px 15px;
            justify-content: flex-start;
        }

        .topbar h3 {
            font-size: 16px;
            flex-grow: 1;
        }

        .topbar .d-flex {
            display: flex;
            align-items: center;
        }

        .topbar .btn {
            font-size: 12px;
            padding: .4rem .6rem;
            white-space: nowrap;
        }

        .topbar .user-name {
            display: none;
        }

        .container-fluid {
            padding: 10px 5px;
        }

        .card {
            padding: 15px;
            margin-bottom: 15px;
        }

        /* FILTER & SEARCH BAR */
        .search-bar {
            flex-direction: column;
            gap: 8px;
        }

        .search-bar input, .search-bar select {
            font-size: 14px;
        }

        /* TABLE STYLES */
        .table-responsive {
            overflow-x: auto;
            display: block;
            width: 100%;
        }

        .table {
            width: 100%;
            min-width: 650px;
        }

        .table thead th {
            font-size: 10px;
            padding: 0.5rem 0.3rem;
            white-space: nowrap;
        }

        .table tbody td {
            padding: 0.4rem 0.3rem;
            font-size: 14px;
        }

        .table tbody td:nth-child(2) {
            white-space: normal;
        }

        .table tbody td:nth-child(5) {
            white-space: nowrap;
            min-width: 100px;
        }

        .table tbody td .btn-sm {
            padding: 0.3rem 0.4rem;
            font-size: 0.7rem;
        }
    }
</style></head>
<body>

<div class="sidebar-overlay" id="sidebar-overlay"></div>

<div class="sidebar" id="admin-sidebar">
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
        <button id="sidebar-toggle-btn" class="me-3"><i class="fa fa-bars"></i></button>
        <h3>Manage User Account</h3>
        <div class="d-flex align-items-center gap-3">
            <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addUserModal"><i class="fa fa-user-plus me-2"></i> Add Account</button>
            <div class="user-profile">
<span class="user-name"><?= $admin_name ?></span>
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
                                <td>
                                    <?php 
                                        $status_class = strtolower($a['status']) === 'active' ? 'text-bg-success' : 'text-bg-danger';
                                        $status_icon = strtolower($a['status']) === 'active' ? 'fa-check-circle' : 'fa-times-circle';
                                    ?>
                                    <span title="<?= htmlspecialchars($a['status']) ?>">
                                        <span class="badge rounded-pill <?= $status_class ?>">
                                            <i class="fa-solid <?= $status_icon ?>"></i> 
                                        </span>
                                    </span>
                                </td>
                                <td>
                                    <span title="<?= htmlspecialchars($a['roles_list']) ?>">
                                        <span class="badge rounded-pill text-bg-info">
                                            <i class="fa-solid fa-user-tag"></i> 
                                        </span>
                                    </span>
                                </td>
                                 <td>
                                     <button class="btn btn-sm btn-outline-warning" title="Edit User"
                                         onclick="editUser(
                                             '<?= $a['person_unique_id'] ?>',
                                             '<?= htmlspecialchars(addslashes($a['name'])) ?>',
                                             '<?= htmlspecialchars(addslashes($a['email'])) ?>',
                                             '<?= htmlspecialchars(addslashes(isset($a['id']) ? $a['id'] : '')) ?>', '<?= htmlspecialchars(addslashes(isset($a['phoneNum']) ? $a['phoneNum'] : '')) ?>',
                                             '<?= htmlspecialchars(addslashes($a['roles_list'])) ?>',
                                             '<?= htmlspecialchars(addslashes($a['status'])) ?>',
                                             '<?= htmlspecialchars(addslashes(isset($a['suspension_remarks']) ? $a['suspension_remarks'] : '')) ?>'
                                         )">
                                         <i class="fa-solid fa-pen"></i>
                                     </button>
                                     
                                     <form action="delete_user.php" method="POST" style="display:inline;" onsubmit="return confirm('Are you sure you want to delete this account? This action cannot be undone.');">
                                         <input type="hidden" name="id" value="<?= $a['person_unique_id'] ?>">
                                         <input type="hidden" name="role" value="<?= htmlspecialchars($a['roles_list']) ?>">
                                         <button type="submit" class="btn btn-sm btn-outline-danger" title="Delete User">
                                             <i class="fa-solid fa-trash"></i>
                                         </button>
                                     </form>
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
                            pattern="[a-zA-Z0-9._%+-]+@(t\.)?unikl\.edu\.my"
                            title="Please enter a valid UniKL email (e.g., name@unikl.edu.my or name@t.unikl.edu.my)">
                </div>
                
                <div class="mb-3"><label class="form-label">Staff ID</label>
                    <input type="text" name="id" class="form-control" required        
                        pattern="\d{6,12}" 
                        title="Enter staff ID (6 to 12 digits)"
                        placeholder="e.g., 990101">
                </div>
                <div class="mb-3"><label class="form-label">Phone Number</label><input type="text" name="phoneNumber" class="form-control" required></div>
                <div class="mb-3"><label class="form-label">Password</label><input type="password" name="password" class="form-control" required></div>
                <p class="text-muted small">This form will automatically create a <strong>Technician</strong> account.</p>
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
                <input type="hidden" name="person_id" id="editPersonId">
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
                    <label class="form-label">Staff ID / Student ID</label>
                    <input type="text" id="editId" name="id" class="form-control bg-light" readonly> </div>
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
document.addEventListener('DOMContentLoaded', function() {
    const sidebar = document.getElementById('admin-sidebar');
    const toggleBtn = document.getElementById('sidebar-toggle-btn');
    const overlay = document.getElementById('sidebar-overlay');
    
    if (toggleBtn) {
        function toggleSidebar() {
            sidebar.classList.toggle('open');
            overlay.classList.toggle('active');
        }

        toggleBtn.addEventListener('click', toggleSidebar);
        overlay.addEventListener('click', toggleSidebar);
        
        const sidebarLinks = sidebar.querySelectorAll('a');
        sidebarLinks.forEach(link => {
            link.addEventListener('click', function() {
                if (window.innerWidth <= 768) {
                    setTimeout(() => { 
                        sidebar.classList.remove('open');
                        overlay.classList.remove('active');
                    }, 100);
                }
            });
        });
    }
});

function editUser(person_unique_id, name, email, id, phone, roles_list, status, remarks) {
    document.getElementById('editPersonId').value = person_unique_id;
    document.getElementById('editName').value = name;
    document.getElementById('editEmail').value = email;
    document.getElementById('editId').value = id; 
    document.getElementById('editPhone').value = phone;
    document.getElementById('editRole').value = roles_list.trim(); 
    document.getElementById('editStatus').value = status.trim();
    document.getElementById('editRemarks').value = remarks; 

    const remarksContainer = document.getElementById('editRemarksContainer');
    const remarksTextarea = document.getElementById('editRemarks');
    if (status.trim().toLowerCase() === 'suspended') {
        remarksContainer.style.display = 'block';
        remarksTextarea.required = true; 
    } else {
        remarksContainer.style.display = 'none';
        remarksTextarea.required = false; 
    }

    new bootstrap.Modal(document.getElementById('editUserModal')).show();
}

function filterTable() {
    const search = document.getElementById('searchInput').value.toLowerCase();
    const role = document.getElementById('roleFilter').value.toLowerCase();
    const status = document.getElementById('statusFilter').value.toLowerCase();
    const rows = document.querySelectorAll('#userTable tbody tr');
    rows.forEach(row => {
        const name = row.cells[0].textContent.toLowerCase();
        const emailPhone = row.cells[1].textContent.toLowerCase();
        
        // Ambil status dan role dari atribut title (tooltip) pada wrapper <span>
        const statusSpanWrapper = row.cells[2].querySelector('span[title]');
        const userStatus = statusSpanWrapper ? statusSpanWrapper.getAttribute('title').toLowerCase().trim() : '';
        const roleSpanWrapper = row.cells[3].querySelector('span[title]');
        const userRole = roleSpanWrapper ? roleSpanWrapper.getAttribute('title').toLowerCase().trim() : '';


        const matchSearch = name.includes(search) || emailPhone.includes(search);
        const matchRole = role === '' || userRole.includes(role); 
        const matchStatus = status === '' || userStatus.includes(status);

        row.style.display = (matchSearch && matchRole && matchStatus) ? '' : 'none';
    });
}

document.getElementById('addAccountForm').addEventListener('submit', function(e) {
    const emailInput = this.querySelector('input[name="email"]');
    const email = emailInput.value.trim();
    
    const uniklEmailPattern = /^[a-zA-Z0-9._%+-]+@(t\.)?unikl\.edu\.my$/;

    if (!uniklEmailPattern.test(email)) { 
        e.preventDefault(); 
        Swal.fire({
            icon: 'error',
            title: 'Invalid Email',
            text: 'Please enter a valid UniKL email (e.g., name@unikl.edu.my or name@t.unikl.edu.my)', 
            didClose: () => {
                emailInput.focus(); 
            }
        });
    }
});

document.getElementById('editStatus').addEventListener('change', function() {
    const selectedStatus = this.value.toLowerCase();
    const remarksContainer = document.getElementById('editRemarksContainer');
    const remarksTextarea = document.getElementById('editRemarks');

    if (selectedStatus === 'suspended') {
        remarksContainer.style.display = 'block';
        remarksTextarea.required = true; 
    } else {
        remarksContainer.style.display = 'none';
        remarksTextarea.required = false; 
        remarksTextarea.value = ''; 
    }
});

document.getElementById('editAccountForm').addEventListener('submit', function(e) {
    const status = document.getElementById('editStatus').value.toLowerCase();
    const remarksTextarea = document.getElementById('editRemarks');
    const remarks = remarksTextarea.value.trim();

    if (status === 'suspended' && remarks === '') {
        e.preventDefault(); 
        Swal.fire({
            icon: 'warning',
            title: 'Remarks Required',
            text: 'Please provide a reason for suspending this account.'
        }).then(() => {
             remarksTextarea.focus(); 
        });
    }
});
</script>

</body>
</html>