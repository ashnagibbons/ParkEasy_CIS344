<?php
/**
 * Booking confirmation / receipt.
 * Joins all four tables: reservations, users, parking_spots, transactions.
 */
require_once 'config/db.php';
require_once 'includes/functions.php';

$id = (int) ($_GET['id'] ?? 0);

$stmt = $pdo->prepare(
    'SELECT r.reservation_id, r.reservation_date, r.start_time, r.end_time,
            u.name, u.email,
            p.spot_id,
            t.transaction_id, t.card_brand, t.card_last4, t.amount, t.created_at
     FROM reservations r
     INNER JOIN users u          ON u.user_id = r.user_id
     INNER JOIN parking_spots p  ON p.spot_id = r.spot_id
     INNER JOIN transactions t   ON t.reservation_id = r.reservation_id
     WHERE r.reservation_id = ?'
);
$stmt->execute([$id]);
$row = $stmt->fetch();

if (!$row) {
    flash('error', 'No paid reservation found with that number.');
    redirect('reservations.php');
}

$pageTitle = 'Confirmation';
require 'includes/header.php';
?>

<section class="card receipt">
    <div class="receipt-check">&#10003;</div>
    <h1>You're all set, <?= e($row['name']) ?>!</h1>
    <p class="muted">A record of this booking is below. Show your reservation number on arrival.</p>

    <dl class="receipt-list">
        <dt>Reservation #</dt><dd><?= (int) $row['reservation_id'] ?></dd>
        <dt>Spot</dt><dd>#<?= (int) $row['spot_id'] ?></dd>
        <dt>Date</dt><dd><?= e(fmt_date($row['reservation_date'])) ?></dd>
        <dt>Time</dt><dd><?= e(fmt_time($row['start_time'])) ?> – <?= e(fmt_time($row['end_time'])) ?></dd>
        <dt>Paid with</dt><dd><?= e($row['card_brand']) ?> ending in <?= e($row['card_last4']) ?></dd>
        <dt>Transaction #</dt><dd><?= (int) $row['transaction_id'] ?></dd>
        <dt>Paid on</dt><dd><?= e(date('M j, Y g:i A', strtotime($row['created_at']))) ?></dd>
        <dt class="total">Amount</dt><dd class="total">$<?= number_format($row['amount'], 2) ?></dd>
    </dl>

    <div class="btn-row">
        <a href="reservations.php?email=<?= urlencode($row['email']) ?>" class="btn btn-secondary">View my reservations</a>
        <a href="reserve.php?email=<?= urlencode($row['email']) ?>" class="btn btn-primary">Book another</a>
    </div>
</section>

<?php require 'includes/footer.php'; ?>
