<?php
/**
 * Form to cancel a reservation (POST only), checking reservation_id and owner's email for security.
 * The linked transaction is removed by ON DELETE CASCADE.
 */
require_once 'config/db.php';
require_once 'includes/functions.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !verify_csrf()) {
    flash('error', 'Invalid request.');
    redirect('reservations.php');
}

$reservationId = (int) post('reservation_id');
$email         = strtolower(post('email'));
$back          = 'reservations.php?email=' . urlencode($email);

// Find the spot first so its is_taken flag can be refreshed afterwards
$stmt = $pdo->prepare(
    'SELECT r.spot_id
     FROM reservations r
     INNER JOIN users u ON u.user_id = r.user_id
     WHERE r.reservation_id = ? AND u.email = ? AND r.reservation_date >= CURDATE()'
);
$stmt->execute([$reservationId, $email]);
$spotId = $stmt->fetchColumn();

if (!$spotId) {
    flash('error', 'That reservation could not be cancelled.');
    redirect($back);
}

$stmt = $pdo->prepare(
    'DELETE r FROM reservations r
     INNER JOIN users u ON u.user_id = r.user_id
     WHERE r.reservation_id = ? AND u.email = ?'
);
$stmt->execute([$reservationId, $email]);
refresh_spot_status($pdo, (int) $spotId);

flash('success', "Reservation #$reservationId was cancelled.");
redirect($back);
