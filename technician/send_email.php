<?php


use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;



require '../PHPMailer-master/src/Exception.php';
require '../PHPMailer-master/src/PHPMailer.php';
require '../PHPMailer-master/src/SMTP.php';

function fetch_reservation_items_by_id($conn, $reserve_id) {
    // Perhatikan penggunaan LEFT JOIN dengan reservation_assets dan GROUP_CONCAT
    $stmt = $conn->prepare("
        SELECT
            ri.id,
            ri.quantity,
            ri.reserve_date,
            ri.return_date,
            ri.status,
            i.item_name,
            p.email AS user_email,
            p.name AS user_name,
            
            -- *** KUNCI PENYELESAIAN MASALAH ANDA: Menggunakan GROUP_CONCAT ***
            GROUP_CONCAT(a.asset_code SEPARATOR ', ') AS assigned_assets
            
        FROM
            reservation_items ri
        JOIN
            reservations r ON ri.reserve_id = r.reserve_id
        JOIN
            person p ON r.person_id = p.person_id
        JOIN
            item i ON ri.item_id = i.item_id
        LEFT JOIN 
            reservation_assets ra ON ri.id = ra.reservation_item_id -- JOIN ke jadual aset
        LEFT JOIN
            assets a ON ra.asset_id = a.asset_id                  -- JOIN ke jadual aset untuk mendapatkan kod
        WHERE
            ri.reserve_id = ?
        GROUP BY
            ri.id, ri.quantity, ri.reserve_date, ri.return_date, ri.status, i.item_name, p.email, p.name
        ORDER BY
            ri.id
    ");

    if (!$stmt) {
        error_log("DB Prepare Error in fetch_reservation_items_by_id: " . $conn->error);
        return [];
    }
    
    $stmt->bind_param("i", $reserve_id);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    
    return $result;
}

/**
 * FUNGSI FINAL DIBETULKAN: Log aktiviti ke dalam jadual activity_logs.
 * MENGGUNAKAN nilai $person_id yang dibekalkan sebagai data untuk lajur DB 'user_id'.
 */
function logActivity($conn, $person_id, $action_type, $description, $related_id = NULL) {
    
    // 1. Dapatkan role & ID pengguna.
    // user_type diambil dari sesi. Jika hilang, set 'system' (kerana DB anda NOT NULL).
    $user_type = $_SESSION['user_type'] ?? 'system'; 
    $user_id_for_db = (int)$person_id; // Nilai person_id dihantar ke lajur user_id
    
    // Pilihan: Semak ENUM jika anda mahu lebih ketat (jika nilai sesi salah ejaan)
    $valid_types = ['admin', 'user', 'tech', 'system'];
    if (!in_array($user_type, $valid_types)) {
        $user_type = 'system'; 
    }

    $action = strtoupper($action_type); // Contoh: CHECKOUT, APPROVE
    $details = (string)$description; 
    $ip_address = $_SERVER['REMOTE_ADDR'] ?? 'N/A';
    
    // 2. QUERY: Memasukkan data ke dalam lajur user_id, user_type, action, details, ip_address
    $stmt = $conn->prepare("
        INSERT INTO activity_logs (user_id, user_type, action, details, ip_address) 
        VALUES (?, ?, ?, ?, ?)
    ");

    if (!$stmt) {
        // Log ralat persediaan SQL
        error_log("Logging Prepare Error: " . $conn->error);
        return false;
    }
    
    // 3. BIND PARAMETER: 
    // i (user_id/person_id), s (user_type), s (action), s (details), s (ip_address)
    $stmt->bind_param("issss", $user_id_for_db, $user_type, $action, $details, $ip_address);
    
    if (!$stmt->execute()) {
        // Log ralat pelaksanaan (mungkin user_type bukan nilai ENUM)
        error_log("Logging Execute Error (user_id: {$user_id_for_db}, type: {$user_type}): " . $stmt->error);
        $stmt->close();
        return false;
    }
    
    $stmt->close();
    return true;
}


function sendGroupedNotificationEmail($to_email, $user_name, $reserve_id, $items_array, $smtp_user, $smtp_pass) {
    
    $mail = new PHPMailer(true);

    try {
        $mail->SMTPDebug = SMTP_DEBUG_LEVEL;
        $mail->Debugoutput = 'error_log';
        
        $mail->isSMTP();
        $mail->Host      = SMTP_HOST;
        $mail->SMTPAuth  = SMTP_AUTH;
        $mail->Username  = $smtp_user;
        $mail->Password  = $smtp_pass;
        $mail->SMTPSecure = SMTP_SECURE;
        $mail->Port      = SMTP_PORT;
        
        $mail->setFrom(SMTP_USER, SMTP_FROM_NAME);
        $mail->addAddress($to_email, $user_name);

		$mail->addBCC('it.rcmp@unikl.edu.my','IT Monitoring');

        $mail->isHTML(true);
        $mail->Subject = 'Confirmation: Reservation ID ' . $reserve_id . ' Has Been Processed';
        
        $item_list_html = '';
        $partial_notice = '';
        
        // 1. Bina senarai item
        foreach ($items_array as $item) {
            $item_name = htmlspecialchars($item['item_name']);
            $quantity = $item['quantity'];
            // Ambil Assigned Assets, jika tiada, guna string kosong
            $assigned_assets = isset($item['assigned_assets']) ? htmlspecialchars($item['assigned_assets']) : 'N/A';
            $reserve_date = date('d M Y', strtotime($item['reserve_date']));
            // $return_date = date('d M Y', strtotime($item['return_date'])); // Tidak digunakan dalam jadual
            $status = $item['status'];
            
            $status_color = (strtolower($status) === 'approved') ? '#27ae60' : '#e74c3c';
            $status_text = (strtolower($status) === 'approved') ? 'Approved' : 'Rejected';

            if (strtolower($status) === 'rejected') {
                // Jika Rejected, Assigned Assets adalah NA
                $assigned_assets = 'N/A';
                 // Kumpul notis penolakan sebahagian, jika ada
                 $partial_notice = "<p style='color: #e74c3c; font-weight: bold;'>
                                    Perhatian: Sesetengah item mungkin telah ditolak atau diubah suai. Sila semak status setiap item di bawah.</p>";
            } else if (strtolower($status) === 'approved' && empty($assigned_assets)) {
                // Jika Approved tapi tiada aset (sepatutnya tidak berlaku), beri amaran
                $assigned_assets = 'Error: No Asset Codes!';
            }

            $item_list_html .= "
                <tr>
                    <td style='border: 1px solid #ddd; padding: 8px;'>{$item_name}</td>
                    <td style='border: 1px solid #ddd; padding: 8px; text-align: center;'>{$quantity}</td>
                    <td style='border: 1px solid #ddd; padding: 8px; text-align: left; font-size: 11px;'>{$assigned_assets}</td>
                    <td style='border: 1px solid #ddd; padding: 8px; text-align: center;'>{$reserve_date}</td>
                    <td style='border: 1px solid #ddd; padding: 8px; text-align: center; color: {$status_color};'><strong>{$status_text}</strong></td>
                </tr>
            ";
        }
        
        $mail->Body = "
            <html>
            <body style='font-family: Arial, sans-serif; line-height: 1.6; color: #333;'>
                <div style='max-width: 800px; margin: 0 auto; border: 1px solid #ddd; padding: 20px;'>
                    <h2 style='color: #27ae60;'>Reservation Processed (ID: {$reserve_id})</h2>
                    <p>Hello <strong>{$user_name}</strong>,</p>
                    {$partial_notice}
                    <p>Your reservation request containing multiple items has been fully processed by the technician. Please see the status of all requested items below.</p>
                    
                    <table border='0' cellpadding='5' cellspacing='0' style='width: 100%; margin: 15px 0; border-collapse: collapse; border: 1px solid #ddd;'>
                        <thead>
                            <tr style='background-color: #f2f2f2;'>
                                <th style='border: 1px solid #ddd; padding: 10px; text-align: left;'>Item</th>
                                <th style='border: 1px solid #ddd; padding: 10px; text-align: center;'>Approved Qty</th>
                                <th style='border: 1px solid #ddd; padding: 10px; text-align: left;'>Assigned Assets</th>
                                <th style='border: 1px solid #ddd; padding: 10px; text-align: center;'>Collect Date</th>
                                <th style='border: 1px solid #ddd; padding: 10px; text-align: center;'>Final Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            {$item_list_html}
                        </tbody>
                    </table>
                    
                    <p style='margin-top: 20px;'>
                        <strong>Action Required:</strong> Please proceed to the inventory counter to collect the **Approved** item(s).
                    </p>
                    <p>Kindly contact the IT staff if you have any questions.</p>

                    <hr style='border: 0; border-top: 1px solid #eee; margin: 20px 0;'>
                    <p style='font-size: 12px; color: #999;'>This is an automated email. Please do not reply to this message.</p>
                </div>
            </body>
            </html>
        ";
        
        $mail->AltBody = "Your reservation (ID: {$reserve_id}) has been processed. Please check the system for the status of all items in your booking. Remember to bring the assigned asset codes when collecting the items.";

        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log("PHPMailer Grouped Approval Error: {$mail->ErrorInfo}");
        return false;
    }
}

function sendNotificationEmail($to_email, $user_name, $item_name, $asset_code, $reserve_date, $return_date, $smtp_user, $smtp_pass, $partial_reason = null) {
    
    $mail = new PHPMailer(true);

    try {
        $mail->SMTPDebug = SMTP_DEBUG_LEVEL; 
        $mail->Debugoutput = 'error_log'; 
        
        $mail->isSMTP();
        $mail->Host      = SMTP_HOST;
        $mail->SMTPAuth    = SMTP_AUTH;
        $mail->Username    = $smtp_user; 
        $mail->Password    = $smtp_pass;
        $mail->SMTPSecure = SMTP_SECURE; 
        $mail->Port      = SMTP_PORT; 
        
        $mail->setFrom(SMTP_USER, SMTP_FROM_NAME); 
        $mail->addAddress($to_email, $user_name);


        $mail->isHTML(true);
        $mail->Subject = 'Confirmation of Assigned Assets ' . $item_name;
        
        $partial_notice = '';
        if ($partial_reason) {
            $partial_notice = "<p style='color: #e74c3c; font-weight: bold; border: 1px dashed #e74c3c; padding: 10px;'>
                                 <strong>Attention:</strong> The approved quantity is reduced. {$partial_reason}
                                 </p>";
        }

        $mail->Body = "
            <html>
            <body style='font-family: Arial, sans-serif; line-height: 1.6; color: #333;'>
                <div style='max-width: 600px; margin: 0 auto; border: 1px solid #ddd; padding: 20px;'>
                    <h2 style='color: #27ae60;'>Reservation Approved and Asset Assigned</h2>
                    <p>Hello <strong>{$user_name}</strong>,</p>
                    {$partial_notice} <p>Your reservation for the following asset(s) has been <strong>approved</strong> by the technician and is ready for collection.</p>
                    
                    <table border='0' cellpadding='5' cellspacing='0' style='width: 100%; margin: 15px 0; border-collapse: collapse;'>
                        <tr>
                            <td style='width: 35%; padding: 8px 0; border-bottom: 1px solid #eee;'><strong>Requested Item:</strong></td>
                            <td style='width: 65%; padding: 8px 0; border-bottom: 1px solid #eee;'>{$item_name}</td>
                        </tr>
                        <tr>
                            <td style='width: 35%; padding: 8px 0; border-bottom: 1px solid #eee;'><strong>Reserved Date:</strong></td>
                            <td style='width: 65%; padding: 8px 0; border-bottom: 1px solid #eee;'>{$reserve_date}</td>
                        </tr>
                        <tr>
                            <td style='width: 35%; padding: 8px 0; border-bottom: 1px solid #eee;'><strong>Return Date:</strong></td>
                            <td style='width: 65%; padding: 8px 0; border-bottom: 1px solid #eee;'><strong>{$return_date}</strong></td>
                        </tr>
                        <tr>
                            <td style='width: 35%; padding: 8px 0;'><strong>Assigned Asset Code(s):</strong></td>
                            <td style='width: 65%; padding: 8px 0;'><strong>{$asset_code}</strong></td>
                        </tr>
                    </table>
                    
                    <p style='color: #e67e22; font-weight: bold;'>Action Required:</p>
                    <p>Please proceed to the inventory counter to collect the asset(s). Ensure you collect the specific asset(s) with the codes listed above.</p>
                    
                    <hr style='border: 0; border-top: 1px solid #eee; margin: 20px 0;'>
                    <p style='font-size: 12px; color: #999;'>This is an automated email. Please do not reply to this message.</p>
                    <p style='font-size: 12px; color: #999;'>UniKL Inventory System</p>
                </div>
            </body>
            </html>
        ";
        
        $mail->AltBody = "Your reservation has been approved. Your assigned Asset Code(s) are: " . $asset_code . ". Please refer to the system for details." . ($partial_reason ? " Note: Quantity was partially rejected. Reason: {$partial_reason}" : "");

        $mail->send();
        return true; 
    } catch (Exception $e) {
        error_log("PHPMailer Error: {$mail->ErrorInfo}"); 
        return false; 
    }
}


function sendNewReservationNotification($technician_email, $reserve_id, $user_name, $item_name, $reserve_date, $link_to_approval) {
    
    $mail = new PHPMailer(true);

    try {
        
        $mail->isSMTP();
        $mail->Host      = SMTP_HOST;
        $mail->SMTPAuth    = SMTP_AUTH;
        $mail->Username    = SMTP_USER;
        $mail->Password    = SMTP_PASS;
        $mail->SMTPSecure = SMTP_SECURE;
        $mail->Port      = SMTP_PORT;
        $mail->SMTPDebug  = SMTP_DEBUG_LEVEL;

        $mail->setFrom(SMTP_USER, SMTP_FROM_NAME);
        
        $mail->addAddress($technician_email, 'Inventory Technician'); 

		$mail->addBCC('it.rcmp@unikl.edu.my','IT Monitoring');

        $mail->isHTML(true);
        $mail->Subject = "NEW RESERVATION: Item - {$item_name} by {$user_name}";
        
        $mail->Body = "
            <html>
            <body style='font-family: Arial, sans-serif; line-height: 1.6; color: #333;'>
                <div style='max-width: 600px; margin: 0 auto; border: 1px solid #ffcc00; padding: 20px; background-color: #fffacd;'>
                    <h3 style='color: #ff9900;'>New Reservation Request Requires Action</h3>
                    <p>A user has submitted a new asset reservation request that requires your approval.</p>
                    
                    <table border='0' cellpadding='5' cellspacing='0' style='width: 100%; margin: 15px 0; border-collapse: collapse; border: 1px solid #ccc;'>
                        <tr><td style='width: 35%; padding: 8px;'><strong>Reservation ID:</strong></td><td style='width: 65%; padding: 8px;'><strong>{$reserve_id}</strong></td></tr>
                        <tr><td style='padding: 8px;'><strong>Requested By:</strong></td><td style='padding: 8px;'>{$user_name}</td></tr>
                        <tr><td style='padding: 8px;'><strong>Item Requested:</strong></td><td style='padding: 8px;'>{$item_name}</td></tr>
                        <tr><td style='padding: 8px;'><strong>Reservation Date:</strong></td><td style='padding: 8px;'>{$reserve_date}</td></tr>
                    </table>

                    <p style='margin-top: 20px;'>Please click the button below to review and approve/reject this request:</p>
                    
                    <a href='{$link_to_approval}' style='display: inline-block; padding: 10px 20px; color: white; background-color: #007bff; text-decoration: none; border-radius: 5px; font-weight: bold;'>
                        Review Request Now
                    </a>
                    <hr style='border: 0; border-top: 1px solid #ddd; margin: 20px 0;'>
                    <p style='font-size: 12px; color: #999;'>Please confirm the status of this request as soon as possible.</p>
                </div>
            </body>
            </html>
        ";

        $mail->AltBody = "A new reservation request has been submitted by {$user_name} for {$item_name}. Please log into the system for approval: {$link_to_approval}";     $mail->send();
        return true;
    } catch (Exception $e) {
        error_log("PHPMailer Error (New Reservation): {$mail->ErrorInfo}");
        return false;
    }
}

function sendRejectionEmail($to_email, $user_name, $item_name, $rejection_reason, $smtp_user, $smtp_pass) {
    
    
    $mail = new PHPMailer(true); 

    try {
        $mail->SMTPDebug = SMTP_DEBUG_LEVEL;
        $mail->Debugoutput = 'error_log';
        
        $mail->isSMTP();
        $mail->Host      = SMTP_HOST;
        $mail->SMTPAuth    = SMTP_AUTH;
        $mail->Username    = $smtp_user; 
        $mail->Password    = $smtp_pass;
        $mail->SMTPSecure = SMTP_SECURE;
        $mail->Port      = SMTP_PORT; 
        
        $mail->setFrom(SMTP_USER, SMTP_FROM_NAME);
        $mail->addAddress($to_email, $user_name);

        

        $mail->isHTML(true);
        $mail->Subject = 'IMPORTANT: Your Reservation Request for ' . $item_name . ' has been Rejected';
        
        $mail->Body = "
            <html>
            <body style='font-family: Arial, sans-serif; line-height: 1.6; color: #333;'>
                <div style='max-width: 600px; margin: 0 auto; border: 1px solid #ddd; padding: 20px;'>
                    <h2 style='color: #e74c3c;'>Reservation Request Rejected</h2>
                    <p>Hello <strong>{$user_name}</strong>,</p>
                    <p>We regret to inform you that your reservation request for the following item has been <strong>REJECTED</strong>:</p>
                    
                    <ul style='list-style-type: none; padding: 0;'>
                        <li style='margin-bottom: 10px; padding: 5px; background-color: #fceae9; border-left: 3px solid #e74c3c;'><strong>Item Requested:</strong> {$item_name}</li>
                    </ul>

                    <p style='font-weight: bold; color: #e74c3c;'>Reason for Rejection:</p>
                    <p style='border: 1px solid #e74c3c; padding: 10px; background-color: #fff; border-radius: 5px;'>
                        " . nl2br(htmlspecialchars($rejection_reason)) . "
                    </p>
                    
                    <p>If you require further clarification, please contact the it department UniKL RCMP.</p>
                    
                    <hr style='border: 0; border-top: 1px solid #eee; margin: 20px 0;'>
                    <p style='font-size: 12px; color: #999;'>This is an automated email from the UniKL RCMP IT Department.</p>
                </div>
            </body>
            </html>
        ";
        
        $mail->AltBody = "Your reservation for " . $item_name . " has been REJECTED. Reason: " . $rejection_reason;

        $mail->send();
        return true; 
    } catch (Exception $e) {
        error_log("PHPMailer Rejection Error: {$mail->ErrorInfo}"); 
        return false; 
    }
	
	// --- Tambah fungsi ini dalam fail e-mel anda (`send_email.php`) ---

/**
 * Menghantar satu e-mel tolakan kepada pengguna dengan senarai semua item dalam satu reserve_id.
 * Dicetuskan apabila SEMUA item dalam tempahan telah diproses (Rejected atau Approved).
 */
function sendGroupedRejectionEmail($to_email, $user_name, $reserve_id, $items_array, $smtp_user, $smtp_pass) {
    
    $mail = new PHPMailer(true);

    try {
        $mail->SMTPDebug = SMTP_DEBUG_LEVEL; 
        $mail->Debugoutput = 'error_log'; 
        
        $mail->isSMTP();
        $mail->Host      = SMTP_HOST;
        $mail->SMTPAuth    = SMTP_AUTH;
        $mail->Username    = $smtp_user; 
        $mail->Password    = $smtp_pass;
        $mail->SMTPSecure = SMTP_SECURE; 
        $mail->Port      = SMTP_PORT; 
        
        $mail->setFrom(SMTP_USER, SMTP_FROM_NAME);
        $mail->addAddress($to_email, $user_name);

        $mail->isHTML(true);
        $mail->Subject = 'IMPORTANT: Update on Your Reservation ID ' . $reserve_id . ' (Processed)';
        
        $item_list_html = '';
        $rejected_count = 0;
        
        // 1. Bina senarai item
        foreach ($items_array as $item) {
            $item_name = htmlspecialchars($item['item_name']);
            $status = $item['status'];
            
            $status_color = (strtolower($status) === 'rejected') ? '#e74c3c' : '#27ae60';
            $status_text = (strtolower($status) === 'rejected') ? 'Rejected' : 'Approved';

            if (strtolower($status) === 'rejected') {
                 $rejected_count++;
            }

            $item_list_html .= "
                <tr>
                    <td style='border: 1px solid #ddd; padding: 8px;'>{$item_name}</td>
                    <td style='border: 1px solid #ddd; padding: 8px; text-align: center; color: {$status_color};'><strong>{$status_text}</strong></td>
                </tr>
            ";
        }
        
        $header_text = ($rejected_count === count($items_array)) 
                       ? "Your entire reservation has been **REJECTED**." 
                       : "Your reservation has been processed. Some item(s) were rejected.";
                       
        $mail->Body = "
            <html>
            <body style='font-family: Arial, sans-serif; line-height: 1.6; color: #333;'>
                <div style='max-width: 700px; margin: 0 auto; border: 1px solid #ddd; padding: 20px;'>
                    <h2 style='color: #e74c3c;'>Reservation Update (ID: {$reserve_id})</h2>
                    <p>Hello <strong>{$user_name}</strong>,</p>
                    <p>{$header_text}</p>
                    
                    <table border='0' cellpadding='5' cellspacing='0' style='width: 100%; margin: 15px 0; border-collapse: collapse; border: 1px solid #ddd;'>
                        <thead>
                            <tr style='background-color: #f2f2f2;'>
                                <th style='border: 1px solid #ddd; padding: 10px; text-align: left;'>Item</th>
                                <th style='border: 1px solid #ddd; padding: 10px; text-align: center;'>Final Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            {$item_list_html}
                        </tbody>
                    </table>
                    
                    <p style='margin-top: 20px;'>
                        Please check the system for the rejection reasons for the items marked 'Rejected'.
                    </p>
                    <p>If you have any further questions, please contact the UniKL RCMP IT Department .</p>

                    <hr style='border: 0; border-top: 1px solid #eee; margin: 20px 0;'>
                    <p style='font-size: 12px; color: #999;'>This is an automated email from the UniKL Inventory System.</p>
                </div>
            </body>
            </html>
        ";
        
        $mail->AltBody = "Your reservation (ID: {$reserve_id}) has been processed. Please check the system for the status of all items in your booking.";

        $mail->send();
        return true; 
    } catch (Exception $e) {
        error_log("PHPMailer Grouped Rejection Error: {$mail->ErrorInfo}"); 
        return false; 
    }
}
}
?>