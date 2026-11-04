<?php
/**
 * Reservation form.
 *  - "Check availability" lists open spots for the chosen window (LEFT JOIN).
 *  - "Continue to payment" validates, re-checks for overlaps inside a DB
 *    transaction (prevents double booking), then INSERTs the reservation.
 */
require_once 'config/db.php';
require_once 'includes/functions.php';

$errors = [];
$isPost = $_SERVER['REQUEST_METHOD'] === 'POST';
$src    = $isPost ? $_POST : $_GET;

$email  = strtolower(trim($src['email'] ?? ''));
$date   = trim($src['date']  ?? date('Y-m-d'));
$start  = trim($src['start'] ?? '');
$end    = trim($src['end']   ?? '');
$spotId = (int) ($src['spot'] ?? 0);
$action = $_POST['action'] ?? '';

// Dropdown choices: every 15 minutes from 12:00 AM to 11:45 PM ("00:00" … "23:45")
$timeSlots = [];
for ($m = 0; $m < 24 * 60; $m += 15) {
    $timeSlots[] = sprintf('%02d:%02d', intdiv($m, 60), $m % 60);
}

/** Validate the date/time window; returns a list of errors. */
function window_errors(string $date, string $start, string $end): array
{
    $errs = [];
    if (!valid_date($date)) {
        $errs[] = 'Please choose a valid date.';
    } elseif ($date < date('Y-m-d')) {
        $errs[] = 'The reservation date cannot be in the past.';
    }
    if (!valid_time($start) || !valid_time($end)) {
        $errs[] = 'Please choose a start and end time.';
    } elseif ((int) substr($start, 3) % 15 || (int) substr($end, 3) % 15) {
        $errs[] = 'Times must be in 15-minute steps.';
    } elseif ($end <= $start) {
        $errs[] = 'End time must be after start time.';
    } elseif ($date === date('Y-m-d') && $start < date('H:i')) {
        $errs[] = 'Start time has already passed today.';
    }
    return $errs;
}

$windowReady = $start !== '' && $end !== '';
$windowErrs  = $windowReady ? window_errors($date, $start, $end) : [];

// ---- Open spots for the chosen window (LEFT JOIN anti-join) ----
$openSpots = [];
if ($windowReady && !$windowErrs) {
    $stmt = $pdo->prepare(
        'SELECT p.spot_id
         FROM parking_spots p
         LEFT JOIN reservations r
                ON r.spot_id = p.spot_id
               AND r.reservation_date = ?
               AND r.start_time < ?      -- existing starts before new ends
               AND r.end_time   > ?      -- existing ends after new starts
         WHERE r.reservation_id IS NULL
         ORDER BY p.spot_id'
    );
    $stmt->execute([$date, $end, $start]);
    $openSpots = array_column($stmt->fetchAll(), 'spot_id');
}

// ---- Handle booking ----
if ($isPost && $action === 'book') {
    if (!verify_csrf()) {
        $errors[] = 'Your session expired. Please try again.';
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Please enter the email you registered with.';
    }
    $errors = array_merge($errors, window_errors($date, $start, $end));
    if ($spotId <= 0) {
        $errors[] = 'Please choose a parking spot.';
    }

    // Look up the user
    $user = null;
    if (!$errors) {
        $stmt = $pdo->prepare('SELECT user_id, name FROM users WHERE email = ?');
        $stmt->execute([$email]);
        $user = $stmt->fetch();
        if (!$user) {
            $errors[] = 'No account found for that email. Please register first.';
        }
    }

    if (!$errors) {
        try {
            $pdo->beginTransaction();

            // Lock the spot row so two people can't book it at the same moment
            $stmt = $pdo->prepare('SELECT spot_id FROM parking_spots WHERE spot_id = ? FOR UPDATE');
            $stmt->execute([$spotId]);
            if (!$stmt->fetch()) {
                throw new RuntimeException('That parking spot does not exist.');
            }

            // Overlap check
            $stmt = $pdo->prepare(
                'SELECT COUNT(*) FROM reservations
                 WHERE spot_id = ? AND reservation_date = ?
                   AND start_time < ? AND end_time > ?'
            );
            $stmt->execute([$spotId, $date, $end, $start]);
            if ($stmt->fetchColumn() > 0) {
                throw new RuntimeException("Spot #$spotId was just booked for that time. Please pick another.");
            }

            $stmt = $pdo->prepare(
                'INSERT INTO reservations (spot_id, user_id, reservation_date, start_time, end_time)
                 VALUES (?, ?, ?, ?, ?)'
            );
            $stmt->execute([$spotId, $user['user_id'], $date, $start, $end]);
            $reservationId = (int) $pdo->lastInsertId();

            refresh_spot_status($pdo, $spotId);
            $pdo->commit();

            redirect("payment.php?id=$reservationId");
        } catch (RuntimeException $ex) {
            $pdo->rollBack();
            $errors[] = $ex->getMessage();
        }
    }
} elseif ($isPost && $action === 'check' && $windowErrs) {
    $errors = $windowErrs;
}

$pageTitle = 'Reserve a Spot';
require 'includes/header.php';
?>

<section class="card form-card form-card-wide">
    <h1>Reserve a parking spot</h1>
    <p class="muted">Choose a date and time, check what's open, then pick your spot. Rate: $<?= number_format(HOURLY_RATE, 2) ?>/hour.</p>

    <?php if ($errors): ?>
        <div class="alert alert-error"><ul><?php foreach ($errors as $err): ?><li><?= e($err) ?></li><?php endforeach; ?></ul></div>
    <?php endif; ?>

    <form method="post" action="reserve.php" novalidate data-validate id="reserve-form">
        <?= csrf_field() ?>

        <div class="field">
            <label for="email">Your registered email</label>
            <input type="email" id="email" name="email" required value="<?= e($email) ?>" placeholder="jane@example.com">
            <small>Not registered? <a href="register.php">Create an account</a>.</small>
        </div>

        <div class="field-row">
            <div class="field">
                <label for="date">Date</label>
                <input type="date" id="date" name="date" required min="<?= date('Y-m-d') ?>" value="<?= e($date) ?>">
            </div>
            <div class="field">
                <label for="start">Start time</label>
                <select id="start" name="start" required>
                    <option value="">Select time</option>
                    <?php foreach ($timeSlots as $slot): ?>
                        <option value="<?= $slot ?>" <?= $start === $slot ? 'selected' : '' ?>><?= fmt_time($slot) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="field">
                <label for="end">End time</label>
                <select id="end" name="end" required>
                    <option value="">Select time</option>
                    <?php foreach ($timeSlots as $slot): ?>
                        <option value="<?= $slot ?>" <?= $end === $slot ? 'selected' : '' ?>><?= fmt_time($slot) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <p class="price-preview" id="price-preview" data-rate="<?= HOURLY_RATE ?>"></p>

        <button type="submit" name="action" value="check" class="btn btn-secondary" formnovalidate>Check availability</button>

        <?php if ($windowReady && !$windowErrs): ?>
            <div class="field spot-picker">
                <label>Open spots for <?= e(fmt_date($date)) ?>, <?= e(fmt_time($start)) ?> – <?= e(fmt_time($end)) ?></label>
                <?php if ($openSpots): ?>
                    <div class="spot-options">
                        <?php foreach ($openSpots as $id): ?>
                            <label class="spot-option">
                                <input type="radio" name="spot" value="<?= (int) $id ?>" <?= $spotId === (int) $id ? 'checked' : '' ?> required>
                                <span>#<?= (int) $id ?></span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="alert alert-warning">Every spot is booked for that window. Try another time.</div>
                <?php endif; ?>
            </div>

            <?php if ($openSpots): ?>
                <button type="submit" name="action" value="book" class="btn btn-primary btn-block">Continue to payment</button>
            <?php endif; ?>
        <?php endif; ?>
    </form>
</section>

<?php require 'includes/footer.php'; ?>
