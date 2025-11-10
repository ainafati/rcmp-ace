<?php
include 'config.php';

if (isset($_POST['category_id'])) {
    $category_id = $_POST['category_id'];
    $query = $conn->prepare("SELECT * FROM item WHERE category_id = ?");
    $query->bind_param("i", $category_id);
    $query->execute();
    $result = $query->get_result();

    echo '<option value="">-- Select Item --</option>';
    while ($row = $result->fetch_assoc()) {
        echo "<option value='{$row['item_id']}'>{$row['item_name']}</option>";
    }
}
?>