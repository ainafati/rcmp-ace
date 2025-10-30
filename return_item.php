<?php
include 'config.php';

if(isset($_POST['loan_id']) && isset($_POST['inspection'])){
    $loan_id = intval($_POST['loan_id']);
    $inspection = $_POST['inspection'];

    $stmt = $conn->prepare("UPDATE loans 
                            SET status='returned', inspection=?, return_date=CURDATE() 
                            WHERE loan_id=?");
    $stmt->bind_param("si", $inspection, $loan_id);

    if($stmt->execute()){
        echo "success";
    } else {
        echo "error";
    }
    $stmt->close();
}
?>
