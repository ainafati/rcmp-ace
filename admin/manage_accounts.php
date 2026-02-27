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

// 1. Ambil nama dari Session (sebab session dah ada nama masa login)
$fullName = $_SESSION['name'] ?? 'Admin'; 

// 2. Logik buang Bin / Binti / A/L / A/P
$lowerName = strtolower($fullName);
$shortName = $fullName; // Default

// Senarai pemisah yang biasa digunakan di Malaysia
$separators = [' binti ', ' bin ', ' a/l ', ' a/p '];

foreach ($separators as $sep) {
    $pos = strpos($lowerName, $sep);
    if ($pos !== false) {
        $shortName = substr($fullName, 0, $pos);
        break; // Berhenti bila dah jumpa satu
    }
}

// 3. Jika masih panjang (tiada bin/binti), ambil 2 perkataan pertama sahaja
$parts = explode(' ', trim($shortName));
if (count($parts) > 2) {
    $displayName = $parts[0] . ' ' . $parts[1];
} else {
    $displayName = $shortName;
}

// Pastikan displayName bersih untuk display
$displayName = htmlspecialchars(trim($displayName));
// --- SETUP PAGINATION ---
$limit = 10; // Jumlah akaun per page
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
if ($page < 1) $page = 1;
$offset = ($page - 1) * $limit;

// Ambil jumlah keseluruhan data untuk pengiraan page
$total_results_sql = "SELECT COUNT(*) FROM person";
$total_result = $conn->query($total_results_sql);
$total_rows = $total_result->fetch_row()[0];
$total_pages = ceil($total_rows / $limit);

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
ORDER BY p.created_at ASC
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
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Manage User Accounts</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
	    <link rel="stylesheet" href="../css/style.css">

<style>

/* Pastikan cell jadual tidak memotong dropdown */
#userTable td {
    overflow: visible !important;
    position: static !important; /* Membolehkan dropdown 'keluar' dari cell */
}

/* Tambah ruang bawah pada dropdown supaya tidak rapat sangat dengan tepi */
.dropdown-menu {
    margin-top: 10px !important;
    transform: translateX(-10%) !important; /* Adjust posisi ke kiri sikit jika perlu */
}

/* Fix Dropdown Tenggelam */
.table-responsive {
    overflow: visible !important; 
    padding-bottom: 50px; /* Tambah ruang supaya dropdown tak kena potong kat bawah */
}

#userTable td {
    overflow: visible !important;
    position: relative; 
}

/* Pastikan Dropdown sentiasa di depan */
.dropdown-menu {
    z-index: 1060 !important; 
    position: absolute !important;
}

/* Supaya row tak lari bila dropdown buka */
.dropdown {
    position: static !important; 
}

/* Gaya untuk badge yang boleh diklik */
.role-label {
    transition: all 0.2s ease;
    font-size: 0.75rem;
    display: inline-flex;
    align-items: center;
    user-select: none;
}

/* Bila checkbox ditanda, tukar warna label secara automatik */
input[value="Technician"]:checked + .tech-label {
    background-color: #f59e0b !important; /* Oren */
    color: white !important;
    border-color: transparent !important;
}

input[value="Admin"]:checked + .admin-label {
    background-color: #ef4444 !important; /* Merah */
    color: white !important;
    border-color: transparent !important;
}

/* Hover effect */
.role-label:hover {
    transform: translateY(-2px);
    filter: brightness(0.9);
}
    .topbar .user-profile {
        display: flex;
        align-items: center;
        gap: 12px;
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

    .btn {
        border-radius: 8px;
        padding: 10px 20px;
        font-weight: 500;
    }


    .search-bar {
        display: flex;
        gap: 10px;
        margin-bottom: 20px;
    }


   
      
#addUserModal .modal-body {
    padding: 1.5rem 2rem !important;
}

/* Kurangkan jarak antara label dan input */
#addUserModal .mb-3, #addUserModal .mb-4 {
    margin-bottom: 1rem !important;
}

/* Pastikan input tak nampak terlalu tinggi */
#addUserModal .form-control, #addUserModal .form-select {
    padding: 10px 12px;
}

/* Button eye adjustment */
#togglePassword {
    border: 2px solid #f1f5f9;
    border-left: none;
    border-radius: 0 12px 12px 0;
}		.text-success { color: #10b981 !important; } /* Hijau yang lebih moden */
.password-hints i { transition: all 0.2s ease; }
#togglePassword:focus { box-shadow: none; }

.role-pills .btn-check:checked + .btn, 
.status-pills .btn-check:checked + .btn {
    background-color: white !important;
    color: #0d6efd !important;
    border-color: transparent !important;
    font-weight: bold;
    border-radius: 8px !important;
    box-shadow: 0 2px 5px rgba(0,0,0,0.1) !important;
}

.role-pills .btn, .status-pills .btn {
    border: none;
    color: #6c757d;
    background-color: #f1f3f5; /* Warna background container filter */
    margin: 0 2px;
    border-radius: 8px !important;
    transition: all 0.2s;
}

.btn-group {
    background-color: #f1f3f5;
    padding: 4px;
    border-radius: 12px;
}		
/* Container Luar */
.role-exclusive-pill {
    display: inline-flex;
    align-items: center;
    padding: 6px 14px;
    background: #ffffff;
    border: 1px solid #eef2f6;
    border-radius: 30px;
    transition: all 0.3s ease;
    box-shadow: 0 2px 4px rgba(0,0,0,0.02);
}

.role-exclusive-pill:hover {
    background: #f8fafc;
    border-color: #cbd5e1;
    transform: translateY(-1px);
}

/* Dot Styling */
.dot-group { display: flex; gap: 4px; }
.dot-static { width: 8px; height: 8px; border-radius: 50%; }
.dot-user { background: #22c55e; box-shadow: 0 0 8px rgba(34, 197, 94, 0.4); }
.dot-tech { background: #f59e0b; box-shadow: 0 0 8px rgba(245, 158, 11, 0.4); }
.dot-admin { background: #ef4444; box-shadow: 0 0 8px rgba(239, 68, 68, 0.4); }
.dot-dim { background: #e2e8f0; }

/* Role Item inside Dropdown */
.role-item {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 10px 12px;
    border-radius: 8px;
    transition: background 0.2s;
    cursor: pointer;
    margin: 0;
}

.role-item:hover { background: #f8fafc; }

/* Custom Checkbox */
.custom-check {
    width: 16px !important;
    height: 16px !important;
    margin: 0 !important;
    cursor: pointer;
}/* Pastikan warna ini ada di luar @media query supaya terpakai pada semua saiz skrin */
.block-user { background-color: #3b82f6 !important; }   /* Biru */
.block-tech { background-color: #f59e0b !important; }   /* Oren */
.block-admin { background-color: #ef4444 !important; }  /* Merah */

/* Pastikan blok mempunyai saiz walaupun kosong */
.role-block {
    width: 14px;
    height: 14px;
    border-radius: 3px;
    background-color: #e2e8f0; /* Warna kelabu jika role tidak aktif */
    display: inline-block;
}
.pagination .page-link {
    color: var(--primary-color);
    border: 1px solid var(--border-color);
}

.pagination .page-item.active .page-link {
    background-color: var(--primary-color);
    border-color: var(--primary-color);
}

.pagination .page-link:hover {
    background-color: #f1f5f9;
}

/* 1. Paksa SweetAlert duduk paling depan */
.swal2-container {
    z-index: 100001 !important; 
}

/* 2. Pastikan teks input warna gelap supaya nampak apa kita taip */
.swal2-input {
    color: #1e293b !important;
}
	/* Container styling */
.main-content {
    background-color: #f8fafc !important; /* Warna background grey lembut */
}

/* Card table styling */
.inventory-card {
    background: white;
    border-radius: 16px;
    border: 1px solid #e2e8f0;
    box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05);
}

/* Icon box styling (Macam dalam gambar) */
.item-icon-box {
    width: 45px;
    height: 45px;
    background-color: #eef2ff; /* Biru cair lembut */
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #6366f1;
    font-size: 1.2rem;
}

/* Category Pill Styling */
.category-pill {
    background: white;
    border: 1px solid #e2e8f0;
    color: #475569;
    padding: 4px 16px;
    border-radius: 20px;
    font-size: 0.8rem;
    font-weight: 500;
}

/* Stock Pill Styling */
.stock-badge {
    padding: 6px 12px;
    border-radius: 8px;
    font-weight: 600;
    font-size: 0.85rem;
    min-width: 40px;
    display: inline-block;
}
.bg-stock-total { background-color: #f1f5f9; color: #475569; }
.bg-stock-avail { background-color: #f0fdf4; color: #16a34a; }

/* Action buttons styling */
.btn-action {
    color: #94a3b8;
    transition: all 0.2s;
    font-size: 1.1rem;
}
.btn-action:hover { color: #1e293b; transform: translateY(-1px); }
.btn-delete:hover { color: #ef4444; }


/* Gaya asas untuk status dot */
.status-dot {
    width: 12px;
    height: 12px;
    border-radius: 50%;
    display: inline-block;
    cursor: help;
    position: relative;
    transition: transform 0.2s;
}

.status-dot:hover {
    transform: scale(1.3); /* Besar sikit bila cursor lalu */
}

/* Warna ikut status */
.dot-active {
    background-color: #10b981; /* Hijau */
    box-shadow: 0 0 8px rgba(16, 185, 129, 0.5);
}

.dot-suspended {
    background-color: #ef4444; /* Merah */
    box-shadow: 0 0 8px rgba(239, 68, 68, 0.5);
}
.avatar-circle {
    width: 45px;          /* Saiz bulatan */
    height: 45px;         /* Mesti sama dengan width untuk jadi bulat */
    background-color: #f1f3f5; /* Warna background bulatan */
    color: #495057;       /* Warna huruf */
    display: flex;
    align-items: center;  /* Center huruf secara vertical */
    justify-content: center; /* Center huruf secara horizontal */
    border-radius: 50%;   /* Ini yang buat dia jadi BULAT */
    font-weight: bold;
    font-size: 1.2rem;
    border: 1px solid #dee2e6; /* Border nipis bagi nampak shape */
}
/* Action Buttons Styling */
.action-btn-group {
    display: flex;
    gap: 8px;
}

.btn-edit, .btn-delete {
    width: 35px;
    height: 35px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 10px;
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    border: 1px solid transparent;
}

/* Edit Button (Warning/Gold) */
.btn-edit {
    background-color: #fefce8;
    color: #ca8a04;
}
.btn-edit:hover {
    background-color: #fef08a;
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(202, 138, 4, 0.15);
}

/* Delete Button (Danger/Red) */
.btn-delete {
    background-color: #fef2f2;
    color: #dc2626;
}
.btn-delete:hover {
    background-color: #fee2e2;
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(220, 38, 38, 0.15);
}

/* Dropdown Card Styling */
.dropdown-menu.role-card {
    border: none;
    border-radius: 18px;
    padding: 1.25rem;
    box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.1), 0 8px 10px -6px rgba(0, 0, 0, 0.1);
    min-width: 250px;
}

/* Butang Add Account supaya sama dengan sidebar */
.btn-primary, .btn-add-account {
    background-color: #3b82f6 !important; /* Biru sidebar */
    border: none !important;
    padding: 10px 24px !important;
    font-weight: 600 !important;
    box-shadow: 0 4px 14px rgba(59, 130, 246, 0.4); /* Shadow biru lembut */
    transition: all 0.3s ease;
}

.btn-primary:hover, .btn-add-account:hover {
    background-color: #2563eb !important; /* Biru gelap sikit bila hover */
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(59, 130, 246, 0.5);
}
/* Kotak Filter supaya lebih 'Pop Up' */
.d-flex.align-items-center.justify-content-between.mb-4.p-3.bg-white {
    border-radius: 16px !important;
    border: 1px solid rgba(226, 232, 240, 0.8) !important;
    box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.05), 
                0 8px 10px -6px rgba(0, 0, 0, 0.05) !important;
    background-color: #ffffff !important;
}

/* Cantikkan sikit input search kat dalam tu */
#searchInput {
    background-color: #f8fafc !important;
    border: 1px solid #f1f5f9 !important;
    transition: all 0.2s ease;
}

#searchInput:focus {
    background-color: #ffffff !important;
    border-color: #3b82f6 !important;
    box-shadow: 0 0 0 4px rgba(59, 130, 246, 0.1) !important;
}
/* Butang Add Account - Tema Cyan */
.btn-add-account {
    background-color: #06b6d4 !important; /* Warna Cyan/Turquoise */
    color: white !important;
    border: none !important;
    padding: 10px 24px !important;
    border-radius: 12px !important; /* Bagi round sikit macam dalam gambar */
    font-weight: 600 !important;
    display: flex;
    align-items: center;
    gap: 8px;
    box-shadow: 0 4px 14px rgba(6, 182, 212, 0.3) !important;
    transition: all 0.3s ease !important;
}

.btn-add-account:hover {
    background-color: #0891b2 !important; /* Gelap sikit bila hover */
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(6, 182, 212, 0.4) !important;
}

.btn-add-account i {
    font-size: 1rem;
}

/* 1. Paksa SweetAlert duduk paling depan */
.swal2-container {
    z-index: 100001 !important; 
}

/* 2. Pastikan teks input warna gelap supaya nampak apa kita taip */
.swal2-input {
    color: #1e293b !important;
}
	/* Container styling */
.main-content {
    background-color: #f8fafc !important; /* Warna background grey lembut */
}

/* Card table styling */
.inventory-card {
    background: white;
    border-radius: 16px;
    border: 1px solid #e2e8f0;
    box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05);
}

/* Header table styling */
.table thead th {
    background: transparent;
    color: #64748b;
    font-size: 0.85rem;
    font-weight: 600;
    text-transform: none;
    letter-spacing: normal;
    padding: 20px 24px;
    border-bottom: 1px solid #f1f5f9;
}

/* Row styling */
.table tbody tr td {
    padding: 20px 24px;
    border-bottom: 1px solid #f1f5f9;
    color: #1e293b;
}

/* Icon box styling (Macam dalam gambar) */
.item-icon-box {
    width: 45px;
    height: 45px;
    background-color: #eef2ff; /* Biru cair lembut */
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #6366f1;
    font-size: 1.2rem;
}

/* Category Pill Styling */
.category-pill {
    background: white;
    border: 1px solid #e2e8f0;
    color: #475569;
    padding: 4px 16px;
    border-radius: 20px;
    font-size: 0.8rem;
    font-weight: 500;
}

/* Stock Pill Styling */
.stock-badge {
    padding: 6px 12px;
    border-radius: 8px;
    font-weight: 600;
    font-size: 0.85rem;
    min-width: 40px;
    display: inline-block;
}
.bg-stock-total { background-color: #f1f5f9; color: #475569; }
.bg-stock-avail { background-color: #f0fdf4; color: #16a34a; }

/* Action buttons styling */
.btn-action {
    color: #94a3b8;
    transition: all 0.2s;
    font-size: 1.1rem;
}
.btn-action:hover { color: #1e293b; transform: translateY(-1px); }
.btn-delete:hover { color: #ef4444; }

.main-content {
    /* Gunakan min-height supaya background sentiasa sekurang-kurangnya setinggi skrin */
    min-height: 100vh; 
    
    /* Warna latar belakang utama */
    background-color: #f1f5f9; 
    
    /* Pattern texture */
    background-image: url('https://www.transparenttextures.com/patterns/cubes.png');
    
    /* PENTING: Supaya pattern tidak bergerak bila kita scroll (nampak lebih kemas) */
    background-attachment: fixed;
    
    /* Tambah padding supaya content tidak rapat sangat dengan tepi skrin */
    padding: 2rem;
    
    /* Memastikan background meliputi seluruh ruang */
    background-repeat: repeat;
}

/* --- MOBILE BOTTOM NAV (THEMED DARK) --- */
@media (max-width: 991px) {
    body {
        padding-bottom: 80px; /* Ruang supaya content tak kena sorok dek bar */
    }

    .mobile-bottom-nav {
        display: flex !important;
        position: fixed;
        bottom: 0;
        left: 0;
        width: 100%;
        /* TUKAR: Warna gelap macam sidebar laptop */
        background: #1e293b !important; 
        border-top: 1px solid rgba(255, 255, 255, 0.1); 
        z-index: 10000;
        justify-content: space-around;
        padding: 12px 0;
        box-shadow: 0 -8px 25px rgba(0,0,0,0.2);
    }

    .mobile-bottom-nav a {
        /* Warna icon & teks masa tak aktif */
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
        transition: 0.3s;
    }

    /* Warna Cyan bila menu aktif/tekan */
    .mobile-bottom-nav a.active {
        color: #06b6d4 !important;
    }

    .mobile-bottom-nav a i {
        font-size: 20px;
    }

    /* Tambah sikit effect bila user touch */
    .mobile-bottom-nav a:active {
        transform: scale(0.9);
        opacity: 0.8;
    }
}
    /* Sembunyikan kalau kat PC */
    @media (min-width: 992px) {
        .mobile-bottom-nav {
            display: none !important;
        }
    }
	
	.toast-container {
    z-index: 1060; /* Pastikan dia duduk atas sekali dari modal/sidebar */
}


.toast {
    border-radius: 12px;
    overflow: hidden;
    animation: slideInRight 0.5s ease-out;
}

@keyframes slideInRight {
    from { transform: translateX(100%); opacity: 0; }
    to { transform: translateX(0); opacity: 1; }
}

.toast-header {
    border-bottom: none;
}
@media (max-width: 768px) {
    /* Sembunyikan header jadual yang asal */
    #userTable thead {
        display: none;
    }

    /* Ubah setiap baris (tr) menjadi seperti "Card" */
    #userTable tbody tr {
        display: block;
        margin-bottom: 1.5rem;
        border: 1px solid #e2e8f0;
        border-radius: 12px;
        background: #fff;
        padding: 10px;
        box-shadow: 0 2px 5px rgba(0,0,0,0.05);
    }

    /* Ubah setiap cell (td) menjadi block */
    #userTable tbody td {
        display: flex;
        justify-content: space-between;
        align-items: center;
        text-align: right;
        padding: 8px 10px;
        border-bottom: 1px solid #f1f5f9;
        width: 100%;
    }

    /* Hilangkan border pada cell terakhir */
    #userTable tbody td:last-child {
        border-bottom: none;
        justify-content: center; /* Butang action letak tengah */
        padding-top: 15px;
    }

    /* Tambah Label sebelum data menggunakan pseudo-element */
    #userTable tbody td::before {
        content: attr(data-label); /* Ambil label dari attribute data-label */
        font-weight: 700;
        text-transform: uppercase;
        font-size: 0.75rem;
        color: #64748b;
        float: left;
        text-align: left;
    }

    /* Pastikan avatar dan nama nampak kemas dalam mobile */
    .avatar-circle {
        width: 35px;
        height: 35px;
        font-size: 1rem;
    }

    /* Kotak filter atas (Search & Filter) */
    .d-flex.align-items-center.justify-content-between.mb-4.p-3.bg-white {
        flex-direction: column; /* Susun ke bawah */
        gap: 15px !important;
    }

    .position-relative.flex-grow-1 {
        max-width: 100% !important;
        width: 100%;
    }
    
    .d-flex.align-items-center.gap-4 {
        flex-direction: column;
        width: 100%;
        gap: 10px !important;
    }
}
@media (max-width: 768px) {
    /* Container utama filter */
    .filter-container {
        flex-direction: column !important;
        align-items: stretch !important;
        padding: 15px !important;
        gap: 15px !important;
    }

    /* Bahagian Search Input */
    .search-wrapper {
        max-width: 100% !important;
        width: 100%;
    }

    /* Membungkus kumpulan filter (Role & Status) */
    .filter-group-wrapper {
        flex-direction: column;
        gap: 12px !important;
        width: 100%;
    }

    /* Label "ROLE:" dan "STATUS:" */
    .filter-label {
        font-size: 0.7rem;
        margin-bottom: 4px;
        display: block;
    }

    /* Kotak butang filter (All, User, dll) */
    .btn-group-filter {
        display: flex;
        width: 100%;
        justify-content: space-between;
    }

    .filter-pill {
        flex: 1; /* Butang akan bahagi ruang sama rata */
        padding: 6px 2px !important;
        font-size: 0.7rem !important;
    }
}
@media (max-width: 576px) {
    .topbar {
        padding: 10px 0;
    }
    
    .topbar h3 {
        font-size: 1.2rem;
    }

    /* Sembunyikan "Administrator" text */
    .user-pill .text-end {
        display: none !important;
    }

    /* Kecilkan butang Add Account */
    .btn-add-account {
        padding: 8px 12px !important;
        font-size: 0.8rem !important;
    }
    
    .btn-add-account span {
        display: none; /* Sembunyikan teks "Add Account", biar icon sahaja */
    }
}
</style>
	
</head>
<body>
<div class="sidebar-overlay" id="sidebarOverlay"></div> 
<div class="sidebar" id="admin-sidebar">
    <div> <div class="sidebar-header">
    <div class="logo-icon"><i class="fa-solid fa-wrench"></i></div>
    <div class="logo-text">
        <strong>UniKL Admin</strong>
        <span class="d-block">System Control</span> </div>
</div>
        
        <div class="sidebar-nav"> 
<a href="manageItem_admin.php"><i class="fa-solid fa-box-archive"></i> Manage Items</a>
        <a href="manage_accounts.php" class="active"  ><i class="fa-solid fa-users-cog"></i> Manage Accounts</a>
        <a href="report_admin.php" ><i class="fa-solid fa-chart-pie"></i> System Report</a>        </div>
    </div>
    
    <div class="sidebar-footer">
        <a href="logout.php" class="logout-link"><i class="fa-solid fa-sign-out-alt"></i> Logout</a> 
    </div>
</div>
<div class="main-content">
 
	
<div class="topbar d-flex justify-content-between align-items-center">
    <div>
        <h3 class="mb-0 fw-bold">Manage User Account</h3>
        <p class="text-muted small mb-0" id="totalAccountCount"><?= count($accounts) ?> accounts found</p>
    </div>
    
    <div class="d-flex align-items-center gap-3">
        <button class="btn btn-add-account btn-sm" data-bs-toggle="modal" data-bs-target="#addUserModal">
            <i class="fa-solid fa-circle-plus"></i> Add Account
        </button>            

         <a href="profile_admin.php" class="user-pill text-decoration-none shadow-sm">
                    <div class="text-end me-2 d-none d-md-block">
                        <div class="user-name" style="text-transform: capitalize; font-weight: 600; color: #1e293b; line-height: 1;">
                            <?= htmlspecialchars($displayName) ?>
                        </div>
                        <small class="text-muted" style="font-size: 0.75rem;">Administrator</small>
                    </div>
                    <div class="profile-avatar">
                        <img src="https://ui-avatars.com/api/?name=<?= urlencode($displayName) ?>&background=06b6d4&color=fff" class="rounded-circle" width="35">
                    </div>
                </a>
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

<div class="filter-container d-flex align-items-center justify-content-between mb-4 p-3 bg-white shadow-sm" style="border-radius: 15px; gap: 20px;">
    <div class="search-wrapper position-relative flex-grow-1" style="max-width: 350px;">
        <i class="fa-solid fa-magnifying-glass position-absolute top-50 start-0 translate-middle-y ms-3 text-muted"></i>
        <input type="text" id="searchInput" class="form-control ps-5 py-2 border-0 bg-light" 
               style="border-radius: 10px;" placeholder="Search by name, email..." onkeyup="filterTable()">
    </div>

    <div class="filter-group-wrapper d-flex align-items-center gap-4">
        <div class="w-100">
            <span class="filter-label small fw-bold text-muted">ROLE:</span>
            <div class="p-1 bg-light d-flex gap-1 btn-group-filter" style="border-radius: 12px;">
                <input type="radio" class="btn-check" name="roleFilter" id="roleAll" value="" checked onchange="filterTable()">
                <label class="btn btn-sm filter-pill" for="roleAll">All</label>

                <input type="radio" class="btn-check" name="roleFilter" id="roleUser" value="User" onchange="filterTable()">
                <label class="btn btn-sm filter-pill" for="roleUser">User</label>

                <input type="radio" class="btn-check" name="roleFilter" id="roleTech" value="Technician" onchange="filterTable()">
                <label class="btn btn-sm filter-pill" for="roleTech">Tech</label>

                <input type="radio" class="btn-check" name="roleFilter" id="roleAdmin" value="Admin" onchange="filterTable()">
                <label class="btn btn-sm filter-pill" for="roleAdmin">Admin</label>
            </div>
        </div>

        <div class="w-100">
            <span class="filter-label small fw-bold text-muted">STATUS:</span>
            <div class="p-1 bg-light d-flex gap-1 btn-group-filter" style="border-radius: 12px;">
                <input type="radio" class="btn-check" name="statusFilter" id="statusAll" value="" checked onchange="filterTable()">
                <label class="btn btn-sm filter-pill" for="statusAll">All</label>

                <input type="radio" class="btn-check" name="statusFilter" id="statusActive" value="Active" onchange="filterTable()">
                <label class="btn btn-sm filter-pill" for="statusActive">Active</label>

                <input type="radio" class="btn-check" name="statusFilter" id="statusSuspended" value="Suspended" onchange="filterTable()">
                <label class="btn btn-sm filter-pill" for="statusSuspended">Susp</label>
            </div>
        </div>
    </div>
</div>
            <div class="table-responsive">
                <table class="table table-hover align-middle" id="userTable">
                    <thead>
                        <tr>
                            <th>Name</th><th>Email & Phone</th><th>Status</th><th>Role</th><th>Actions</th>
                        </tr>
                    </thead>
					
<tbody>
    <?php if (count($accounts) > 0): ?>
        <?php foreach ($accounts as $a): ?>
            <?php 
                $roles_list = strtolower($a['roles_list']);
                $isTech = str_contains($roles_list, 'technician');
                $isAdmin = str_contains($roles_list, 'admin');
                $status = strtolower($a['status']);
            ?>
            <tr data-role="<?= $roles_list ?>" data-status="<?= $status ?>">
                <td>
    <div class="d-flex align-items-center">
        <div class="avatar-circle me-3">
            <?= strtoupper(substr($a['name'], 0, 1)) ?>
        </div>
        
        <div class="d-flex flex-column">
            <span class="fw-bold text-dark" style="line-height: 1.2;">
                <?= htmlspecialchars($a['name']) ?>
            </span>
            <small class="text-muted">
                ID: <?= htmlspecialchars($a['id'] ?? 'N/A') ?>
            </small>
        </div>
    </div>
</td>

                <td>
                    <div class="small">
                        <div class="mb-1"><i class="fa-regular fa-envelope text-muted me-2"></i><?= htmlspecialchars($a['email']) ?></div>
                        <div><i class="fa-solid fa-phone text-muted me-2"></i><?= htmlspecialchars($a['phoneNum'] ?? 'N/A') ?></div>
                    </div>
                </td>

                <td class="text-center">
                    <?php 
                        $dotClass = ($status === 'active') ? 'dot-active' : 'dot-suspended';
                    ?>
                    <span class="status-dot <?= $dotClass ?>" title="<?= ucfirst($status) ?>"></span>
                </td>

                <td class="text-center">
                    <div class="dropdown">
        <div class="role-exclusive-pill" data-bs-toggle="dropdown" aria-expanded="false">
            <div class="dot-group">
                <span class="dot-static dot-user"></span>
                <span class="dot-static <?= $isTech ? 'dot-tech' : 'dot-dim' ?>"></span>
                <span class="dot-static <?= $isAdmin ? 'dot-admin' : 'dot-dim' ?>"></span>
            </div>
            <i class="fa-solid fa-chevron-down ms-2 opacity-50" style="font-size: 0.7rem;"></i>
        </div>

        <div class="dropdown-menu dropdown-menu-end shadow-lg" style="border-radius: 12px; border: 1px solid rgba(0,0,0,0.05); min-width: 200px; padding: 15px;">
            <div class="text-uppercase mb-3 opacity-50 fw-bold" style="font-size: 0.65rem; letter-spacing: 1.2px;">Access Control</div>
            
          <form class="role-update-form">
    <input type="hidden" name="user_id" value="<?= $a['person_unique_id'] ?>">
    
    <div class="role-item mb-2">
        <div class="d-flex align-items-center">
            <i class="fa-solid fa-circle-user me-3 text-success opacity-75"></i>
            <span class="small fw-semibold text-secondary">Standard User</span>
        </div>
        <i class="fa-solid fa-lock text-muted" style="font-size: 0.7rem;"></i>
    </div>

    <label class="role-item mb-2" for="t<?= $a['person_unique_id'] ?>">
        <div class="d-flex align-items-center">
            <i class="fa-solid fa-screwdriver-wrench me-3 text-warning"></i>
            <span class="small fw-semibold">Technician Mode</span>
        </div>
        <input type="checkbox" name="roles[]" value="Technician" 
               id="t<?= $a['person_unique_id'] ?>" 
               class="form-check-input custom-check tech-checkbox" 
               <?= $isTech ? 'checked' : '' ?>>
    </label>

    <label class="role-item mb-3" for="a<?= $a['person_unique_id'] ?>">
        <div class="d-flex align-items-center">
            <i class="fa-solid fa-shield-halved me-3 text-danger"></i>
            <span class="small fw-semibold">Full Administrator</span>
        </div>
        <input type="checkbox" name="roles[]" value="Admin" 
               id="a<?= $a['person_unique_id'] ?>" 
               class="form-check-input custom-check admin-checkbox" 
               <?= $isAdmin ? 'checked' : '' ?>>
    </label>

   <button type="button" 
        class="btn btn-dark w-100 btn-sm py-2 fw-bold" 
        style="border-radius: 8px;"
        onclick="saveRoleChanges(this, '<?= $a['person_unique_id'] ?>')">
    UPDATE PERMISSIONS
</button>
</form>
        </div>
    </div>
                </td>

                <td class="text-center">
                    <div class="action-btn-group justify-content-center">
                       <button class="btn-edit" title="Edit Profile"
    onclick="editUser(
        '<?= $a['person_unique_id'] ?>', 
        '<?= htmlspecialchars(addslashes($a['name'])) ?>', 
        '<?= htmlspecialchars(addslashes($a['email'])) ?>', 
        '<?= htmlspecialchars(addslashes($a['id'] ?? '')) ?>', 
        '<?= htmlspecialchars(addslashes($a['phoneNum'] ?? '')) ?>', 
        '<?= htmlspecialchars(addslashes($a['roles_list'])) ?>', 
        '<?= htmlspecialchars(addslashes($a['status'])) ?>'
    )">
    <i class="fa-solid fa-pen-to-square"></i>
</button>
                        <form action="delete_user.php" method="POST" class="d-inline" onsubmit="return confirm('Padam?')">
                             <input type="hidden" name="id" value="<?= $a['person_unique_id'] ?>">
                             <button type="submit" class="btn-delete"><i class="fa-solid fa-trash-can"></i></button>
                        </form>
                    </div>
                </td>
            </tr>
        <?php endforeach; ?>
    <?php endif; ?>
</tbody>    
               </table>
				
				
				<div class="d-flex justify-content-between align-items-center mt-3">
    <div class="text-muted small">
        Showing <?= $offset + 1 ?> to <?= min($offset + $limit, $total_rows) ?> of <?= $total_rows ?> accounts
    </div>
    <nav aria-label="Page navigation">
        <ul class="pagination pagination-sm mb-0" id="pagination-links">
            <li class="page-item <?= ($page <= 1) ? 'disabled' : '' ?>">
                <a class="page-link" href="?page=<?= $page - 1 ?>" aria-label="Previous">
                    <span aria-hidden="true">&laquo;</span>
                </a>
            </li>

            <?php for($i = 1; $i <= $total_pages; $i++): ?>
                <li class="page-item <?= ($page == $i) ? 'active' : '' ?>">
                    <a class="page-link" href="?page=<?= $i ?>"><?= $i ?></a>
                </li>
            <?php endfor; ?>

            <li class="page-item <?= ($page >= $total_pages) ? 'disabled' : '' ?>">
                <a class="page-link" href="?page=<?= $page + 1 ?>" aria-label="Next">
                    <span aria-hidden="true">&raquo;</span>
                </a>
            </li>
        </ul>
    </nav>
</div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="addUserModal" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-centered"> 
        <form action="save_user.php" method="POST" class="modal-content" id="addAccountForm">
            <div class="modal-header text-white">
                <h5 class="modal-title"><i class="fa-solid fa-user-plus me-2"></i>Add New Account</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            
            <div class="modal-body p-4">
                <div class="row g-3 mb-3">
                    <div class="col-md-4">
                        <label class="form-label">Full Name</label>
                        <input type="text" name="username" class="form-control" placeholder="Nama penuh" required>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">UniKL Email</label>
                        <input type="email" name="email" class="form-control" placeholder="name@unikl.edu.my" required>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Staff / Student ID</label>
                        <input type="text" name="id" class="form-control" placeholder="6-12 digit" required>
                    </div>
                </div>

                <div class="row g-3 mb-4">
                    <div class="col-md-4">
                        <label class="form-label">Phone Number</label>
                        <input type="text" name="phoneNumber" class="form-control" placeholder="01X-XXXXXXX" required>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Account Role</label>
                        <select name="role" class="form-select" required>
                            <option value="Technician" selected>Technician</option>
                            <option value="Admin">Admin</option>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Secure Password</label>
                        <div class="input-group">
                            <input type="password" name="password" id="passwordInput" class="form-control" placeholder="••••••••" required>
                            <button class="btn btn-outline-secondary" type="button" id="togglePassword">
                                <i class="fa-solid fa-eye"></i>
                            </button>
                        </div>
                    </div>
                </div>

                <div class="row g-3">
                    <div class="col-md-8">
                        <div class="p-3" style="background: #f8fafc; border-radius: 12px; border: 1px solid #e2e8f0;">
                            <p class="mb-2 small fw-bold text-secondary text-uppercase" style="font-size: 0.65rem; letter-spacing: 0.5px;">Password Requirements:</p>
                            <div class="row g-0">
                                <div class="col-6 small text-muted" id="reqLength" style="font-size: 0.7rem;"><i class="fa-solid fa-circle-xmark me-1"></i> Min. 8 Aksara</div>
                                <div class="col-6 small text-muted" id="reqUpper" style="font-size: 0.7rem;"><i class="fa-solid fa-circle-xmark me-1"></i> Huruf Besar</div>
                                <div class="col-6 small text-muted" id="reqNumber" style="font-size: 0.7rem;"><i class="fa-solid fa-circle-xmark me-1"></i> Nombor (0-9)</div>
                                <div class="col-6 small text-muted" id="reqSpecial" style="font-size: 0.7rem;"><i class="fa-solid fa-circle-xmark me-1"></i> Simbol (@$!%)</div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4 d-flex align-items-center">
                        <div class="alert alert-info m-0 py-2 px-3 border-0" style="border-radius: 10px; font-size: 0.75rem; background-color: #f0f7ff;">
                            <i class="fa-solid fa-circle-info me-1"></i> 
                            Admin & Technician automatically include <strong>User</strong> access.
                        </div>
                    </div>
                </div>
            </div>

            <div class="modal-footer bg-light border-0" style="border-radius: 0 0 20px 20px;">
                <button type="button" class="btn btn-link text-muted text-decoration-none" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-primary btn-save px-5">Save Account</button>
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
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>

// Tambah ini di baris paling bawah script hang
document.addEventListener('DOMContentLoaded', function() {
    filterTable(1);
});


document.querySelectorAll('.role-update-form').forEach(form => {
    const adminCheck = form.querySelector('.admin-checkbox');
    const techCheck = form.querySelector('.tech-checkbox');

    if (adminCheck && techCheck) {
        // Function untuk handle logic hierarchy
        const handleHierarchy = () => {
            if (adminCheck.checked) {
                techCheck.checked = true;
                techCheck.disabled = true; // Kunci sebab Admin memang dah ada kuasa Tech
                // Tambah hidden input supaya value 'Technician' tetap hantar ke PHP
                if(!form.querySelector('#hidden-tech')) {
                    const hidden = document.createElement('input');
                    hidden.type = 'hidden';
                    hidden.name = 'roles[]';
                    hidden.value = 'Technician';
                    hidden.id = 'hidden-tech';
                    form.appendChild(hidden);
                }
            } else {
                techCheck.disabled = false;
                const hidden = form.querySelector('#hidden-tech');
                if(hidden) hidden.remove();
            }
        };

        adminCheck.addEventListener('change', handleHierarchy);
        
        // Run sekali masa page load
        handleHierarchy();
    }
});

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

let currentPage = 1;
const rowsPerPage = 10;
function filterTable(page = 1) {
    // PENTING: Setiap kali input/filter berubah, kita reset ke page 1 
    // KECUALI kalau function ni dipanggil dari butang pagination (ada parameter page)
    currentPage = page;

    const searchVal = document.getElementById('searchInput').value.toLowerCase().trim();
    const roleRadio = document.querySelector('input[name="roleFilter"]:checked');
    const statusRadio = document.querySelector('input[name="statusFilter"]:checked');
    
    const roleVal = roleRadio ? roleRadio.value.toLowerCase() : "";
    const statusVal = statusRadio ? statusRadio.value.toLowerCase() : "";

    const allRows = Array.from(document.querySelectorAll('#userTable tbody tr'));
    
    // 1. Proses Tapisan
    const filteredRows = allRows.filter(row => {
        // Abaikan row "No accounts found" kalau ada
        if (row.cells.length < 2) return false;

        const rowRole = (row.getAttribute('data-role') || "").toLowerCase();
        const rowStatus = (row.getAttribute('data-status') || "").toLowerCase();
        const rowText = row.innerText.toLowerCase();

        const matchesSearch = rowText.includes(searchVal);
        const matchesRole = (roleVal === "") || rowRole.includes(roleVal);
        const matchesStatus = (statusVal === "") || (rowStatus === statusVal);

        return matchesSearch && matchesRole && matchesStatus;
    });

    const totalRows = filteredRows.length;

    // 2. Update Kaunter Atas (12 accounts found)
    const countDisplay = document.getElementById('totalAccountCount');
    if (countDisplay) {
        countDisplay.innerText = `${totalRows} accounts found`;
    }

    // 3. Logic Pagination
    const totalPages = Math.ceil(totalRows / rowsPerPage);
    
    // Kalau total rows sikit, pastikan tak tersekat kat page besar
    if (currentPage > totalPages && totalPages > 0) currentPage = totalPages;
    if (totalRows === 0) currentPage = 1;

    const start = (currentPage - 1) * rowsPerPage;
    const end = start + rowsPerPage;

    // 4. Sorok/Tunjuk Row (Guna display: none secara agresif)
    allRows.forEach(row => {
        row.style.display = 'none';
        row.style.visibility = 'hidden'; // Tambah ni untuk extra safety
    });
    
    const rowsToShow = filteredRows.slice(start, end);
    rowsToShow.forEach(row => {
        row.style.display = '';
        row.style.visibility = 'visible';
    });

    // 5. Update UI Pagination & Info
    renderPaginationButtons(totalPages, currentPage);

    const infoText = document.querySelector('.text-muted.small');
    if (infoText) {
        const showingFrom = totalRows === 0 ? 0 : start + 1;
        const showingTo = Math.min(end, totalRows);
        infoText.innerHTML = `Showing ${showingFrom} to ${showingTo} of ${totalRows} accounts`;
    }
}
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

const passwordInput = document.getElementById('passwordInput');
const reqLength = document.getElementById('reqLength');
const reqUpper = document.getElementById('reqUpper');
const reqNumber = document.getElementById('reqNumber');
const reqSpecial = document.getElementById('reqSpecial');

passwordInput.addEventListener('input', function() {
    const val = passwordInput.value;

    // Check Length
    updateStatus(reqLength, val.length >= 8);
    // Check Uppercase
    updateStatus(reqUpper, /[A-Z]/.test(val));
    // Check Number
    updateStatus(reqNumber, /[0-9]/.test(val));
    // Check Special Char
    updateStatus(reqSpecial, /[\W_]/.test(val));
});

function updateStatus(element, isValid) {
    if (isValid) {
        element.classList.remove('text-muted', 'text-danger');
        element.classList.add('text-success', 'fw-bold');
        element.querySelector('i').classList.replace('fa-circle-xmark', 'fa-check-circle');
    } else {
        element.classList.remove('text-success', 'fw-bold');
        element.classList.add('text-muted');
        element.querySelector('i').classList.replace('fa-check-circle', 'fa-circle-xmark');
    }
}

// Toggle Show/Hide Password
document.getElementById('togglePassword').addEventListener('click', function() {
    const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
    passwordInput.setAttribute('type', type);
    this.querySelector('i').classList.toggle('fa-eye');
    this.querySelector('i').classList.toggle('fa-eye-slash');
});

function toggleRole(userId, roleType) {
    // Gunakan SweetAlert biar nampak pro sikit
    Swal.fire({
        title: 'Confirm Change?',
        text: `Do you want to update this user to ${roleType}?`,
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#3085d6',
        cancelButtonColor: '#d33',
        confirmButtonText: 'Yes, update it!'
    }).then((result) => {
        if (result.isConfirmed) {
            const params = new URLSearchParams();
            // NAMA DI SINI MESTI SAMA DENGAN PHP ($_POST['person_id'] & $_POST['new_role'])
            params.append('person_id', userId); 
            params.append('new_role', roleType);

            fetch('update_role_silent.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: params.toString()
            })
            .then(res => res.json())
            .then(data => {
                if(data.success) {
                    Swal.fire('Updated!', 'Role has been changed.', 'success')
                    .then(() => location.reload());
                } else {
                    Swal.fire('Error', data.message, 'error');
                }
            })
            .catch(err => {
                console.error('Error:', err);
                Swal.fire('Error', 'Something went wrong!', 'error');
            });
        }
    });
}
function updateRole(pid, roleName) {
    // 1. Bungkus data dalam format URL encoded
    const params = new URLSearchParams();
    params.append('person_id', pid);
    params.append('new_role', roleName);

    // 2. Hantar guna fetch
    fetch('update_role_silent.php', {
        method: 'POST',
        headers: {
            // Ini PENTING supaya PHP tahu ni data $_POST
            'Content-Type': 'application/x-www-form-urlencoded'
        },
        body: params.toString()
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            console.log("Berjaya!");
            location.reload(); // Refresh untuk nampak perubahan warna petak
        } else {
            alert("Gagal: " + data.message);
        }
    })
    .catch(error => {
        console.error('Error:', error);
    });
}
function saveRoleChanges(btn, userId) {
    // 1. Cari form yang berkaitan dengan button yang ditekan
    const form = btn.closest('.role-update-form');
    const params = new URLSearchParams();
    
    params.append('user_id', userId);

    // 2. Kumpul semua role yang di-tick
    // Kita letak 'User' secara manual sebab dalam HTML ia bukan input yang boleh di-tick (statik)
    let rolesArray = ['User']; 
    
    form.querySelectorAll('input[name="roles[]"]:checked').forEach(cb => {
        rolesArray.push(cb.value);
    });

    // 3. Masukkan dalam params (PHP hang expect array roles)
    // Kita hantar satu per satu guna nama roles[] supaya PHP automatik nampak sebagai array
    rolesArray.forEach(role => {
        params.append('roles[]', role);
    });

    // 4. Hantar ke Backend
    fetch('update_role_silent.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: params.toString()
    })
    .then(res => {
        if (!res.ok) throw new Error('Network response was not ok');
        return res.json();
    })
    .then(data => {
        if(data.success) {
            Swal.fire({
                icon: 'success',
                title: 'Success!',
                text: data.message,
                timer: 1500,
                showConfirmButton: false
            }).then(() => {
                location.reload(); // Refresh untuk update dot warna kat table
            });
        } else {
            Swal.fire('Error', data.message, 'error');
        }
    })
    .catch(err => {
        console.error('Error:', err);
        Swal.fire('Error', 'Server connection failed', 'error');
    });
}
function updatePaginationUI(totalPages, currentPage) {
    const paginationContainer = document.querySelector('.pagination'); // Pastikan class ni sama dengan HTML hang
    if (!paginationContainer) return;

    let html = '';
    
    // Butang Previous
    html += `<li class="page-item ${currentPage === 1 ? 'disabled' : ''}">
                <a class="page-link" href="#" onclick="filterTable(${currentPage - 1})">&laquo;</a>
             </li>`;

    // Butang Nombor Page
    for (let i = 1; i <= totalPages; i++) {
        html += `<li class="page-item ${i === currentPage ? 'active' : ''}">
                    <a class="page-link" href="#" onclick="filterTable(${i})">${i}</a>
                 </li>`;
    }

    // Butang Next
    html += `<li class="page-item ${currentPage === totalPages || totalPages === 0 ? 'disabled' : ''}">
                <a class="page-link" href="#" onclick="filterTable(${currentPage + 1})">&raquo;</a>
             </li>`;

    paginationContainer.innerHTML = html;
}
</script>

<nav class="mobile-bottom-nav">
   
    <a href="manageItem_admin.php" ><i class="fa-solid fa-box-archive"></i> Manage Items</a>
        <a href="manage_accounts.php" class="active" ><i class="fa-solid fa-users-cog"></i> Manage Accounts</a>
        <a href="report_admin.php" ><i class="fa-solid fa-chart-pie"></i> System Report</a>        </div>

    <a href="profile_admin.php" class="nav-item">
        <i class="fa-solid fa-user"></i>
        <span>Profile</span>
    </a>
</nav></body>

</html>

