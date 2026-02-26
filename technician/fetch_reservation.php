<?php
include '../config.php';
$id = mysqli_real_escape_string($conn, $_GET['id']);

// 1. Cuba cari data reservation secara terus guna reserve_id
$q = "SELECT r.*, p.name FROM reservations r 
      JOIN person p ON r.person_id = p.person_id 
      WHERE r.reserve_id = '$id'";
$resQuery = mysqli_query($conn, $q);
$res = mysqli_fetch_assoc($resQuery);

// 2. Kalau TAK JUMPA (mungkin ID yang dihantar tu adalah ID dari table reservation_items)
if (!$res) {
    $qAlt = "SELECT r.*, p.name 
             FROM reservation_items ri 
             JOIN reservations r ON ri.reserve_id = r.reserve_id 
             JOIN person p ON r.person_id = p.person_id 
             WHERE ri.id = '$id'";
    $resQueryAlt = mysqli_query($conn, $qAlt);
    $res = mysqli_fetch_assoc($resQueryAlt);
    
    // Update $id supaya query items di bawah guna reserve_id yang betul
    if ($res) {
        $id = $res['reserve_id'];
    }
}

// 3. Jika masih tak jumpa selepas dua cubaan, bagi amaran
if (!$res) {
    echo "<div class='p-5 text-center'>
            <h4 class='text-danger'>Aduh! Data tak jumpalah.</h4>
            <p>Check balik ID: <b>$id</b> dalam table reservations kau weii.</p>
          </div>";
    exit;
}

// 4. Tarik list barang berdasarkan reserve_id yang dah sahih
$qItems = "SELECT ri.*, i.item_name 
           FROM reservation_items ri 
           JOIN item i ON ri.item_id = i.item_id 
           WHERE ri.reserve_id = '$id'";
$itemsResult = mysqli_query($conn, $qItems);
?>

<style>
    /* Custom style untuk modal content supaya "Just Nice" */
    .modal-container {
        padding: 30px;
        font-family: 'Plus Jakarta Sans', sans-serif;
    }
    .modal-ref {
        font-family: 'Courier New', Courier, monospace;
        color: #64748b;
        font-size: 0.85rem;
        margin-bottom: 20px;
    }
    .modal-title-custom {
        font-size: 1.25rem;
        font-weight: 700;
        color: #0f172a;
        margin-bottom: 5px;
    }
    .info-text {
        font-size: 0.95rem;
        line-height: 1.5;
        color: #334155;
        margin-bottom: 20px;
    }
    .time-stamp {
        font-size: 0.8rem;
        color: #94a3b8;
        display: flex;
        align-items: center;
        gap: 6px;
    }
    .item-row {
        background: #f8fafc;
        border-radius: 12px;
        padding: 12px 16px;
        margin-bottom: 10px;
        border: 1px solid #e2e8f0;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }
    .item-name {
        font-weight: 600;
        font-size: 0.9rem;
        color: #1e293b;
    }
</style>

<div class="modal-container">
    <div class="d-flex justify-content-between align-items-start">
        <div>
            <div class="modal-title-custom">New Reservation Request</div>
            <div class="modal-ref">Ref: RES-<?= str_pad($id, 3, '0', STR_PAD_LEFT) ?></div>
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="modal" style="font-size: 0.8rem;"></button>
    </div>

    <div class="info-text">
        <strong><?= htmlspecialchars($res['name'] ?? 'User') ?></strong> has requested items for 
        <span class="text-primary fw-600"><?= htmlspecialchars($res['location'] ?? 'Lab') ?></span>. 
        Please review and approve.
    </div>

    <div class="mt-2">
        <?php while($item = mysqli_fetch_assoc($itemsResult)): 
            $st = $item['status'];
            $badgeColor = ($st == 'Approved') ? '#22c55e' : (($st == 'Rejected') ? '#ef4444' : '#f59e0b');
        ?>
            <div class="item-row">
                <div>
                    <div class="item-name"><?= htmlspecialchars($item['item_name']) ?></div>
                    <div style="font-size: 0.75rem; color: <?= $badgeColor ?>; font-weight: 700; text-transform: uppercase;">
                        ● <?= $st ?>
                    </div>
                </div>
                
                <?php if($st == 'Pending'): ?>
                    <div class="btn-group">
                        <a href="check_out.php?approve=<?= $item['id'] ?>" class="btn btn-sm btn-light border" style="font-size: 0.7rem;">
                            <i class="fa-solid fa-check text-success"></i>
                        </a>
                        <a href="check_out.php?reject=<?= $item['id'] ?>" class="btn btn-sm btn-light border" style="font-size: 0.7rem;">
                            <i class="fa-solid fa-xmark text-danger"></i>
                        </a>
                    </div>
                <?php endif; ?>
            </div>
        <?php endwhile; ?>
    </div>

    <div class="time-stamp mt-4">
        <i class="fa-regular fa-clock"></i> 
        <?= date('d M Y, H:i', strtotime($res['created_at'] ?? 'now')) ?>
    </div>
</div>