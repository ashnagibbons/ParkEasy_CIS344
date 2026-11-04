<?php
/**
 * Payment form, all data is inserted into the transactions table, which only stores last 4 digits of CC
 */
require_once 'config/db.php';
require_once 'includes/functions.php';

$id = (int) ($_GET['id'] ?? 0);

// Reservation + user + any existing payment (INNER JOIN + LEFT JOIN)
$stmt = $pdo->prepare(
    'SELECT r.reservation_id, r.spot_id, r.reservation_date, r.start_time, r.end_time,
            u.name, u.email,
            t.transaction_id
     FROM reservations r
     INNER JOIN users u       ON u.user_id = r.user_id
     LEFT JOIN transactions t ON t.reservation_id = r.reservation_id
     WHERE r.reservation_id = ?'
);
$stmt->execute([$id]);
$res = $stmt->fetch();

if (!$res) {
    flash('error', 'Reservation not found.');
    redirect('reservations.php');
}
if ($res['transaction_id']) {
    flash('info', 'This reservation has already been paid.');
    redirect("confirmation.php?id=$id");
}

$amount = reservation_cost($res['start_time'], $res['end_time']);
$errors = [];
$holder = $res['name'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf()) {
        $errors[] = 'Your session expired. Please try again.';
    }

    $holder = post('card_holder');
    $number = preg_replace('/\D/', '', post('card_number')); // digits only
    $expiry = post('expiry');
    $cvv    = post('cvv');

    // ---- Validation ----
    if ($holder === '' || mb_strlen($holder) > 100) {
        $errors[] = 'Enter the name on the card.';
    }
    if (strlen($number) < 13 || strlen($number) > 19 || !luhn_valid($number)) {
        $errors[] = 'Enter a valid card number.';
    }
    if (!preg_match('/^(0[1-9]|1[0-2])\/(\d{2})$/', $expiry, $m)) {
        $errors[] = 'Enter the expiry date as MM/YY.';
    } else {
        // Card is valid through the last day of the expiry month
        $expEnd = DateTime::createFromFormat('Y-m-d H:i:s', "20{$m[2]}-{$m[1]}-01 00:00:00")->modify('first day of next month');
        if ($expEnd <= new DateTime()) {
            $errors[] = 'That card has expired.';
        }
    }
    if (!preg_match('/^\d{3,4}$/', $cvv)) {
        $errors[] = 'Enter the 3 or 4 digit security code.';
    }

    if (!$errors) {
        $stmt = $pdo->prepare(
            'INSERT INTO transactions (reservation_id, card_holder, card_brand, card_last4, amount)
             VALUES (?, ?, ?, ?, ?)'
        );
        $stmt->execute([$id, $holder, card_brand($number), substr($number, -4), $amount]);

        flash('success', 'Payment received. Your spot is booked!');
        redirect("confirmation.php?id=$id");
    }
}

$pageTitle = 'Payment';
require 'includes/header.php';
?>

<div class="two-col">
    <section class="card form-card">
        <h1>Payment</h1>
        <p class="muted">This is a class project – use a test number such as <code>4242 4242 4242 4242</code>.</p>

        <?php if ($errors): ?>
            <div class="alert alert-error"><ul><?php foreach ($errors as $err): ?><li><?= e($err) ?></li><?php endforeach; ?></ul></div>
        <?php endif; ?>

        <form method="post" action="payment.php?id=<?= $id ?>" novalidate data-validate autocomplete="off">
            <?= csrf_field() ?>
            <div class="field">
                <label for="card_holder">Name on card</label>
                <input type="text" id="card_holder" name="card_holder" maxlength="100" required value="<?= e($holder) ?>">
            </div>
            <div class="field">
                <label for="card_number">Card number</label>
                <input type="text" id="card_number" name="card_number" inputmode="numeric" maxlength="23" required placeholder="1234 5678 9012 3456">
            </div>
            <div class="field-row">
                <div class="field">
                    <label for="expiry">Expiry (MM/YY)</label>
                    <input type="text" id="expiry" name="expiry" inputmode="numeric" maxlength="5" required placeholder="08/28">
                </div>
                <div class="field">
                    <label for="cvv">CVV</label>
                    <input type="password" id="cvv" name="cvv" inputmode="numeric" maxlength="4" required placeholder="123">
                </div>
            </div>
            <button type="submit" class="btn btn-primary btn-block">Pay $<?= number_format($amount, 2) ?></button>
        </form>
    </section>

    <aside class="card summary">
        <h2>Booking summary</h2>
        <dl>
            <dt>Reservation</dt><dd>#<?= (int) $res['reservation_id'] ?></dd>
            <dt>Name</dt><dd><?= e($res['name']) ?></dd>
            <dt>Spot</dt><dd>#<?= (int) $res['spot_id'] ?></dd>
            <dt>Date</dt><dd><?= e(fmt_date($res['reservation_date'])) ?></dd>
            <dt>Time</dt><dd><?= e(fmt_time($res['start_time'])) ?> – <?= e(fmt_time($res['end_time'])) ?></dd>
            <dt class="total">Total</dt><dd class="total">$<?= number_format($amount, 2) ?></dd>
        </dl>
    </aside>
</div>

<?php require 'includes/footer.php'; ?>
