<?php
/**
 * Reservation lookup by email.
 * INNER JOIN users + parking_spots, LEFT JOIN transactions so that
 * unpaid reservations still appear (with a "Pay now" link).
 */
require_once 'config/db.php';
require_once 'includes/functions.php';

$email = strtolower(trim($_GET['email'] ?? ''));
$rows  = [];
$user  = null;

if ($email !== '') {
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        flash('error', 'Please enter a valid email address.');
        redirect('reservations.php');
    }

    $stmt = $pdo->prepare('SELECT user_id, name FROM users WHERE email = ?');
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if ($user) {
        $stmt = $pdo->prepare(
            'SELECT r.reservation_id, r.reservation_date, r.start_time, r.end_time,
                    p.spot_id,
                    t.transaction_id, t.amount, t.card_brand, t.card_last4
             FROM reservations r
             INNER JOIN users u          ON u.user_id = r.user_id
             INNER JOIN parking_spots p  ON p.spot_id = r.spot_id
             LEFT JOIN  transactions t   ON t.reservation_id = r.reservation_id
             WHERE u.email = ?
             ORDER BY r.reservation_date DESC, r.start_time DESC'
        );
        $stmt->execute([$email]);
        $rows = $stmt->fetchAll();
    }
}

$pageTitle = 'My Reservations';
require 'includes/header.php';
?>

<section class="card">
    <div class="card-head">
        <h1>My reservations</h1>
        <form method="get" class="inline-form">
            <label for="email" class="sr-only">Email</label>
            <input type="email" id="email" name="email" placeholder="Your email" required value="<?= e($email) ?>">
            <button type="submit" class="btn btn-primary">Look up</button>
        </form>
    </div>

    <?php if ($email === ''): ?>
        <p class="muted">Enter the email you registered with to see your bookings.</p>
    <?php elseif (!$user): ?>
        <div class="alert alert-warning">No account found for <?= e($email) ?>. <a href="register.php">Register here</a>.</div>
    <?php elseif (!$rows): ?>
        <p class="muted">Hi <?= e($user['name']) ?>, you don't have any reservations yet. <a href="reserve.php?email=<?= urlencode($email) ?>">Book a spot</a>.</p>
    <?php else: ?>
        <p class="muted">Showing <?= count($rows) ?> reservation(s) for <strong><?= e($user['name']) ?></strong>.</p>
        <div class="table-wrap">
            <table class="table">
                <thead>
                    <tr>
                        <th>#</th><th>Spot</th><th>Date</th><th>Time</th><th>Payment</th><th>Status</th><th></th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($rows as $r):
                    $past = $r['reservation_date'] < date('Y-m-d');
                    $paid = (bool) $r['transaction_id'];
                ?>
                    <tr>
                        <td data-label="#"><?= (int) $r['reservation_id'] ?></td>
                        <td data-label="Spot">#<?= (int) $r['spot_id'] ?></td>
                        <td data-label="Date"><?= e(fmt_date($r['reservation_date'])) ?></td>
                        <td data-label="Time"><?= e(fmt_time($r['start_time'])) ?> – <?= e(fmt_time($r['end_time'])) ?></td>
                        <td data-label="Payment">
                            <?php if ($paid): ?>
                                $<?= number_format($r['amount'], 2) ?> · <?= e($r['card_brand']) ?> ••<?= e($r['card_last4']) ?>
                            <?php else: ?>
                                <a href="payment.php?id=<?= (int) $r['reservation_id'] ?>">Pay now</a>
                            <?php endif; ?>
                        </td>
                        <td data-label="Status">
                            <?php if ($past): ?>
                                <span class="badge badge-muted">Completed</span>
                            <?php elseif ($paid): ?>
                                <span class="badge badge-success">Confirmed</span>
                            <?php else: ?>
                                <span class="badge badge-warning">Unpaid</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if (!$past): ?>
                                <form method="post" action="cancel.php" class="cancel-form">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="reservation_id" value="<?= (int) $r['reservation_id'] ?>">
                                    <input type="hidden" name="email" value="<?= e($email) ?>">
                                    <button type="submit" class="btn btn-danger btn-sm">Cancel</button>
                                </form>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>

<?php require 'includes/footer.php'; ?>
