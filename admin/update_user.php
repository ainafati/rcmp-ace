<?php
session_start();
include 'config.php';

// Ensure admin is logged in
if (!isset($_SESSION['admin_id'])) {
    // Redirect non-admins
    header("Location: login.php");
    exit();
}

// Check if the form was submitted via POST
if ($_SERVER["REQUEST_METHOD"] == "POST") {

    // 1. Get data from the form
    $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
    $role = isset($_POST['role']) ? $_POST['role'] : '';
    $phoneNum = isset($_POST['phoneNum']) ? trim($_POST['phoneNum']) : '';
    $status = isset($_POST['status']) ? $_POST['status'] : '';
    // Get remarks from POST, default to null if not set
    $remarks = isset($_POST['suspension_remarks']) ? trim($_POST['suspension_remarks']) : null;

    // 2. Validate essential input
    if (empty($id) || empty($role) || empty($status)) {
        $_SESSION['error_message'] = "Missing required information (ID, Role, or Status).";
        header("Location: manage_accounts.php");
        exit();
    }

    // 3. Validate remarks specifically if status is Suspended
    if (strtolower($status) === 'suspended' && empty($remarks)) {
         $_SESSION['error_message'] = "Suspension remarks are required when setting status to 'Suspended'.";
         header("Location: manage_accounts.php");
         exit();
    }

    // 4. IMPORTANT: Clear remarks ONLY if status is NOT 'Suspended'
    if (strtolower($status) !== 'suspended') {
        $remarks = null; // Ensure remarks are NULL if status is Active
    }

    // 5. Determine the table and ID column based on the role
    $table = '';
    $id_column = '';
    if ($role === 'Technician') {
        $table = 'technician';
        $id_column = 'tech_id';
    } elseif ($role === 'User') {
        $table = 'user';
        $id_column = 'user_id';
    } else {
        $_SESSION['error_message'] = "Invalid user role specified.";
        header("Location: manage_accounts.php");
        exit();
    }

    // 6. Prepare and execute the UPDATE query (Ensure column name matches DB)
    // Make sure your column name is exactly 'suspension_remarks' in BOTH tables
    $sql = "UPDATE $table SET phoneNum = ?, status = ?, suspension_remarks = ? WHERE $id_column = ?";
    $stmt = $conn->prepare($sql);

    if ($stmt) {
        // Bind parameters: s = string, i = integer. Remarks is string (s).
        // Correct order: phoneNum (s), status (s), remarks (s), id (i)
        $stmt->bind_param("sssi", $phoneNum, $status, $remarks, $id);

        if ($stmt->execute()) {
            if ($stmt->affected_rows > 0) {
                $_SESSION['success_message'] = "Account updated successfully.";
            } else {
                // Check if there was an error or just no change
                if ($stmt->errno) {
                     $_SESSION['error_message'] = "Error updating account: " . $stmt->error;
                } else {
                    $_SESSION['success_message'] = "No changes were made to the account."; // No data actually changed
                }
            }
        } else {
            $_SESSION['error_message'] = "Error executing update: " . $stmt->error;
        }
        $stmt->close();
    } else {
        $_SESSION['error_message'] = "Error preparing statement: " . $conn->error;
    }

    $conn->close();

} else {
    // If not a POST request, set an error message
    $_SESSION['error_message'] = "Invalid request method.";
}

// Redirect back to the manage accounts page
header("Location: manage_accounts.php");
exit();
?>