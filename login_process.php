<?php
session_start();
include 'config.php'; 


if ($_SERVER["REQUEST_METHOD"] == "POST") {
    
    
    $email = isset($_POST['email']) ? $_POST['email'] : '';
    $password = isset($_POST['password']) ? $_POST['password'] : '';
    $selected_role = isset($_POST['role']) ? $_POST['role'] : ''; 
    
    
    $_SESSION['login_attempt_role'] = $selected_role;
    $_SESSION['login_attempt_email'] = $email;
    
    
    $email = filter_var($email, FILTER_SANITIZE_EMAIL);
    
    
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
        
        
        if (password_verify($password, $person['password'])) { 
        
            
            
            if ($person['status'] === 'Suspended') {
                $_SESSION['error'] = "Your account is suspended. Please contact the administrator.";
                header("Location: login.php");
                exit();
            }


            $stmt_roles = $conn->prepare("
                SELECT r.role_name 
                FROM person_roles pr 
                JOIN roles r ON pr.role_id = r.role_id 
                WHERE pr.person_id = ? 
            ");

            
            if (!$stmt_roles) {
                
                $_SESSION['error'] = "Database error (Role Query): " . $conn->error;
                header("Location: login.php");
                exit();
            }
            
            $stmt_roles->bind_param("i", $person['person_id']);
            $stmt_roles->execute();
            $roles_result = $stmt_roles->get_result();
            
            
            while ($row = $roles_result->fetch_assoc()) {
                $roles_db[] = $row['role_name'];
            }
            $stmt_roles->close();

            if (empty($roles_db)) {
                $_SESSION['error'] = "Login successful, but no role assigned. Please contact the administrator.";
                header("Location: login.php");
                exit();
            }
            
            
            
            
            
            
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

            
            if (is_null($mapped_role) || !in_array($mapped_role, $roles_db)) {
                $_SESSION['error'] = "The selected role is not valid for this account, or you do not have permission.";
                header("Location: login.php");
                exit();
            }

            
            $_SESSION['person_id'] = $person['person_id'];
            $_SESSION['name'] = $person['name'];
            
            
            $_SESSION['logged_in_role'] = $mapped_role; 
            
            
            $_SESSION['all_roles'] = $roles_db; 
            
            
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
            
            $_SESSION['error'] = "Invalid email or password.";
            header("Location: login.php");
            exit();
        }
    } else {
        
        $stmt->close();
        $_SESSION['error'] = "Invalid email or password.";
        header("Location: login.php");
        exit();
    }
} else {
    
    header("Location: login.php");
    exit();
}
?>