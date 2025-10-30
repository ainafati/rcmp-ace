<?php
session_start();
include 'config.php';

// (Pastikan ada pengesahan sesi admin di sini)
if (!isset($_SESSION['admin_id'])) {
    header("Location: login.php");
    exit();
}

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['item_id'])) {
    $item_id = (int)$_POST['item_id'];
    $quantity = (int)$_POST['quantity'];

    if ($item_id > 0 && $quantity > 0) {
        
        // --- MULA LOGIK AUTO GENERATE ---

        // 1. Dapatkan awalan (prefix) dari kategori item yang dipilih
        $stmt_cat = $conn->prepare(
            "SELECT c.category_name FROM categories c JOIN item i ON c.category_id = i.category_id WHERE i.item_id = ?"
        );
        $stmt_cat->bind_param("i", $item_id);
        $stmt_cat->execute();
        $stmt_cat->bind_result($category_name);
        $stmt_cat->fetch();
        $stmt_cat->close();

        $prefix = strtoupper(substr($category_name, 0, 3));
        $like_prefix = $prefix . '-%';

        // 2. Cari nombor kod aset terakhir untuk kategori ini
        $stmt_last = $conn->prepare(
            "SELECT asset_code FROM assets WHERE asset_code LIKE ? ORDER BY asset_code DESC LIMIT 1"
        );
        $stmt_last->bind_param("s", $like_prefix);
        $stmt_last->execute();
        $stmt_last->bind_result($last_code);
        $stmt_last->fetch();
        $stmt_last->close();
        
        // Tentukan nombor permulaan
        $last_num = $last_code ? (int)substr($last_code, -3) : 0;
        
        // 3. Loop sebanyak kuantiti yang diminta
        for ($i = 0; $i < $quantity; $i++) {
            $next_num = $last_num + 1 + $i;
            $new_asset_code = $prefix . '-' . str_pad($next_num, 3, '0', STR_PAD_LEFT);

            // 4. Masukkan aset baru ke dalam pangkalan data
            $stmt_insert = $conn->prepare("INSERT INTO assets (item_id, asset_code, status) VALUES (?, ?, 'Available')");
            $stmt_insert->bind_param("is", $item_id, $new_asset_code);
            $stmt_insert->execute();
            $stmt_insert->close();
        }

        echo "<script>alert('✅ Successfully added " . $quantity . " new unit(s)!'); window.location.href='manageItem_admin.php';</script>";

    } else {
        echo "<script>alert('❌ Invalid data provided.'); window.location.href='manageItem_admin.php';</script>";
    }
}
?>