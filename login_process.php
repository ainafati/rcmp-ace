<?php
session_start();
include 'config.php'; // Fail ini mesti menyediakan sambungan $conn

// Pastikan ia adalah permintaan POST
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    
    // BARIS 7, 8, & 9 YANG DIBETULKAN: Gantikan '??' dengan isset() dan operator ternary
    $email = isset($_POST['email']) ? $_POST['email'] : '';
    $password = isset($_POST['password']) ? $_POST['password'] : '';
    $selected_role = isset($_POST['role']) ? $_POST['role'] : ''; // <-- PERANAN YANG DIPILIH DARI BORANG
    
    // Simpan data untuk mengisi semula borang jika gagal
    $_SESSION['login_attempt_role'] = $selected_role;
    $_SESSION['login_attempt_email'] = $email;
    
    // Bersihkan input
    $email = filter_var($email, FILTER_SANITIZE_EMAIL);
    
    // 1. Cari pengguna dalam Jadual Person
    $stmt = $conn->prepare("SELECT person_id, name, password, status FROM person WHERE email = ?");
    
    if (!$stmt) {
        $_SESSION['error'] = "Database error: " . $conn->error;
        header("Location: login.php");
        exit();
    }
    
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($person = $result->fetch_assoc()) {
        $stmt->close();
        
        // 2. Sahkan Kata Laluan (GUNA password_verify)
        if (password_verify($password, $person['password'])) { 
        // Guna: if ($password === $person['password']) { jika masih guna plain text
            
            // 2.1 Semak Status Akaun
            if ($person['status'] === 'Suspended') {
                $_SESSION['error'] = "Your account is suspended. Please contact the administrator.";
                header("Location: login.php");
                exit();
            }

// 3. Ambil Semua Peranan Pengguna (DIBETULKAN SINTAKS SQL)
            $stmt_roles = $conn->prepare("
                SELECT r.role_name 
                FROM person_roles pr 
                JOIN roles r ON pr.role_id = r.role_id 
                WHERE pr.person_id = ? 
            ");

            // Semak jika prepare gagal sebelum memanggil bind_param
            if (!$stmt_roles) {
                // Ralat ini harus ditangkap jika terdapat ralat sintaks DB sebenar
                $_SESSION['error'] = "Database error (Role Query): " . $conn->error;
                header("Location: login.php");
                exit();
            }
            
            $stmt_roles->bind_param("i", $person['person_id']);
            $stmt_roles->execute();
            $roles_result = $stmt_roles->get_result();
            
            // ... (Sambungan kod anda) ...            $roles_db = [];
            while ($row = $roles_result->fetch_assoc()) {
                $roles_db[] = $row['role_name'];
            }
            $stmt_roles->close();

            if (empty($roles_db)) {
                $_SESSION['error'] = "Login successful, but no role assigned. Please contact the administrator.";
                header("Location: login.php");
                exit();
            }
            
            // 4. SAHKAN PERANAN YANG DIPILIH (Selected Role)
            // Terjemahkan peranan borang kepada format DB
            
            // PENGGANTIAN SINTAKS MATCH DENGAN LOGIK IF/ELSE ATAU SWITCH LAMA
            // Sintaks 'match' memerlukan PHP 8.0, jadi kita guna 'switch'
            $mapped_role = null;
            switch ($selected_role) {
                case 'admin':
                    $mapped_role = 'Admin';
                    break;
                case 'tech':
                    $mapped_role = 'Technician';
                    break;
                case 'user':
                    $mapped_role = 'User';
                    break;
                default:
                    $mapped_role = null;
            }

            // Semak jika peranan yang DIPILIH wujud dalam peranan pengguna (DB)
            if (is_null($mapped_role) || !in_array($mapped_role, $roles_db)) {
                $_SESSION['error'] = "The selected role is not valid for this account, or you do not have permission.";
                header("Location: login.php");
                exit();
            }

            // 5. Tetapkan Sesi (Session)
            $_SESSION['person_id'] = $person['person_id'];
            $_SESSION['name'] = $person['name'];
            
            // Peranan yang mana pengguna sedang log masuk SEKARANG
            $_SESSION['logged_in_role'] = $mapped_role; 
            
            // SEMUA peranan yang dimiliki oleh pengguna (untuk rujukan lain)
            $_SESSION['all_roles'] = $roles_db; 
            
            // 6. Logik Pengarahan (Redirect) - Berdasarkan peranan YANG DIPILIH
            switch ($mapped_role) {
                case 'Admin':
                    header("Location: admin/manageItem_admin.php");
                    break;
                case 'Technician':
                    header("Location: technician/dashboard_tech.php");
                    break;
                case 'User':
                    header("Location: user/dashboard_user.php");
                    break;
                default:
                    $_SESSION['error'] = "Role selected is valid but dashboard path is missing.";
                    header("Location: login.php"); 
            }
            exit();

        } else {
            // Ralat Kata Laluan
            $_SESSION['error'] = "Invalid email or password.";
            header("Location: login.php");
            exit();
        }
    } else {
        // Ralat Emel tidak ditemui
        $stmt->close();
        $_SESSION['error'] = "Invalid email or password.";
        header("Location: login.php");
        exit();
    }
} else {
    // Jika akses bukan melalui borang POST
    header("Location: login.php");
    exit();
}
?>