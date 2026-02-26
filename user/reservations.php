<?php
include '../config.php';

// $id yang kau dapat ni sebenarnya 'id' dari table reservation_items (cth: 129)
$id = isset($_GET['id']) ? mysqli_real_escape_string($conn, $_GET['id']) : 0;

// QUERY BARU: Kita cari 'id' dalam reservation_items, tapi kita JOIN ke reservations 
// supaya kita dapat 'reason' dan 'location' yang kau nak tu.
$q = "SELECT r.*, p.name 
      FROM reservation_items ri
      JOIN reservations r ON ri.reserve_id = r.reserve_id
      LEFT JOIN person p ON r.person_id = p.person_id
      WHERE ri.id = '$id'"; // Kita filter guna ID 129 tadi

$resQuery = mysqli_query($conn, $q);
$res = mysqli_fetch_assoc($resQuery);

// Kalau still tak jumpa, kita buat satu lagi backup check (kut-kut kau hantar reserve_id terus)
if (!$res) {
    $q_backup = "SELECT r.*, p.name FROM reservations r 
                 LEFT JOIN person p ON r.person_id = p.person_id 
                 WHERE r.reserve_id = '$id'";
    $resQuery = mysqli_query($conn, $q_backup);
    $res = mysqli_fetch_assoc($resQuery);
}

if (!$res) {
    echo "<div class='p-4 text-center text-danger'>Data langsung tak jumpa dalam Reservations mahupun Items.</div>";
    exit;
}

// Lepas dah dapat info reservation, kita tarik SEMUA items yang ada bawah reserve_id yang sama
$reserve_id_real = $res['reserve_id'];
$qItems = "SELECT ri.*, i.item_name 
           FROM reservation_items ri 
           JOIN item i ON ri.item_id = i.item_id 
           WHERE ri.reserve_id = '$reserve_id_real'";
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
    <div class="mb-2">
        <strong><?= htmlspecialchars($res['name'] ?? 'Unknown User') ?></strong> has requested items for 
        <b class="text-primary"><?= htmlspecialchars($res['location']) ?></b>.
    </div>
    
    <div style="background: #f1f5f9; padding: 12px; border-radius: 8px; border-left: 4px solid #64748b; font-size: 0.85rem;">
        <span class="text-muted fw-bold" style="font-size: 0.7rem; text-transform: uppercase; display: block; margin-bottom: 4px;">Purpose / Reason:</span>
        <span class="text-secondary">
            <?php 
                // Kita check guna !empty supaya kalau ada 'F' dia tunjuk 'F'
                if (!empty($res['reason'])) {
                    echo '"' . htmlspecialchars($res['reason']) . '"';
                } else {
                    echo '<i class="text-muted">No reason provided in database.</i>';
                }
            ?>
        </span>
    </div>
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