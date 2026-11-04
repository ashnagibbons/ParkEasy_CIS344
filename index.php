<?php
/**
 * Home page: parking spot overview for a chosen date.
 * Uses a LEFT JOIN so spots with zero bookings still appear.
 */
require_once 'config/db.php';
require_once 'includes/functions.php';

$date = $_GET['date'] ?? date('Y-m-d');
if (!valid_date($date)) {
    $date = date('Y-m-d');
}

// One row per spot with that day's bookings (LEFT JOIN + GROUP BY)
$stmt = $pdo->prepare(
    "SELECT p.spot_id,
            p.is_taken,
            COUNT(r.reservation_id) AS bookings,
            GROUP_CONCAT(
                CONCAT(TIME_FORMAT(r.start_time, '%l:%i %p'), ' – ', TIME_FORMAT(r.end_time, '%l:%i %p'))
                ORDER BY r.start_time SEPARATOR '|'
            ) AS slots
     FROM parking_spots p
     LEFT JOIN reservations r
            ON r.spot_id = p.spot_id
           AND r.reservation_date = ?
     GROUP BY p.spot_id, p.is_taken
     ORDER BY p.spot_id"
);
$stmt->execute([$date]);
$spots = $stmt->fetchAll();

$total   = count($spots);
$booked  = count(array_filter($spots, fn($s) => $s['bookings'] > 0));
$free    = $total - $booked;

$pageTitle = 'Parking Spots';
require 'includes/header.php';
?>

<section class="hero">
    <div>
        <h1>Find and reserve a parking spot</h1>
        <p>Pick a day to see which spots are open. Rates are $<?= number_format(HOURLY_RATE, 2) ?>/hour.</p>
    </div>
    <a href="reserve.php?date=<?= e($date) ?>" class="btn btn-primary btn-lg">Reserve a spot</a>
</section>

<section class="card">
    <div class="card-head">
        <h2>Availability for <?= e(fmt_date($date)) ?></h2>
        <form method="get" class="inline-form">
            <label for="date" class="sr-only">Date</label>
            <input type="date" id="date" name="date" value="<?= e($date) ?>" onchange="this.form.submit()">
        </form>
    </div>

    <div class="stats">
        <div class="stat"><span class="stat-num"><?= $total ?></span><span>Total spots</span></div>
        <div class="stat stat-free"><span class="stat-num"><?= $free ?></span><span>Open all day</span></div>
        <div class="stat stat-busy"><span class="stat-num"><?= $booked ?></span><span>Have bookings</span></div>
    </div>

    <div class="legend">
        <span><i class="dot dot-free"></i>Open all day</span>
        <span><i class="dot dot-busy"></i>Partly booked – other times still available</span>
    </div>

    <div class="spot-grid">
        <?php foreach ($spots as $s): $busy = $s['bookings'] > 0; ?>
            <div class="spot <?= $busy ? 'spot-busy' : 'spot-free' ?>">
                <div class="spot-id">#<?= (int) $s['spot_id'] ?></div>
                <?php if ($busy): ?>
                    <ul class="spot-slots">
                        <?php foreach (explode('|', $s['slots']) as $slot): ?>
                            <li><?= e(trim($slot)) ?></li>
                        <?php endforeach; ?>
                    </ul>
                <?php else: ?>
                    <div class="spot-status">Open</div>
                <?php endif; ?>
                <a class="spot-link" href="reserve.php?date=<?= e($date) ?>&spot=<?= (int) $s['spot_id'] ?>">Book</a>
            </div>
        <?php endforeach; ?>
    </div>
</section>

<?php require 'includes/footer.php'; ?>
