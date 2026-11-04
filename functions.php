<?php
/**
 * Shared helpers: output escaping, CSRF protection, flash messages,
 * validation and pricing.
 */

date_default_timezone_set('America/New_York');//can pass in your local time zone

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/** Hourly parking rate in dollars. */
const HOURLY_RATE = 5.00;

/** Escape output for HTML (prevents XSS). */
function e($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

/* ---------------- CSRF protection ---------------- */

function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/** Hidden input to drop inside every POST form. */
function csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="' . e(csrf_token()) . '">';
}

function verify_csrf(): bool
{
    return isset($_POST['csrf_token'])
        && hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf_token']);
}

/* ---------------- Flash messages ---------------- */

function flash(string $type, string $message): void
{
    $_SESSION['flash'][] = ['type' => $type, 'message' => $message];
}

function get_flashes(): array
{
    $messages = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $messages;
}

function redirect(string $url): void
{
    header("Location: $url");
    exit;
}

/* ---------------- Input helpers ---------------- */

/** Trimmed POST value (or '' if missing). */
function post(string $key): string
{
    return trim($_POST[$key] ?? '');
}

function valid_date(string $date): bool
{
    $d = DateTime::createFromFormat('Y-m-d', $date);
    return $d && $d->format('Y-m-d') === $date;
}

function valid_time(string $time): bool
{
    return (bool) preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $time);
}

/** Luhn checksum - catches mistyped card numbers. */
function luhn_valid(string $number): bool
{
    $sum = 0;
    $alt = false;
    for ($i = strlen($number) - 1; $i >= 0; $i--) {
        $n = (int) $number[$i];
        if ($alt) {
            $n *= 2;
            if ($n > 9) {
                $n -= 9;
            }
        }
        $sum += $n;
        $alt = !$alt;
    }
    return $sum % 10 === 0;
}

function card_brand(string $number): string
{
    if (preg_match('/^4/', $number))            return 'Visa';
    if (preg_match('/^(5[1-5]|2[2-7])/', $number)) return 'Mastercard';
    if (preg_match('/^3[47]/', $number))        return 'Amex';
    if (preg_match('/^6(011|5)/', $number))     return 'Discover';
    return 'Card';
}

/** Price of a reservation based on its length. */
function reservation_cost(string $start, string $end): float
{
    $hours = (strtotime($end) - strtotime($start)) / 3600;
    return round($hours * HOURLY_RATE, 2);
}

/** 12-hour display time, e.g. 14:30:00 -> 2:30 PM */
function fmt_time(string $time): string
{
    return date('g:i A', strtotime($time));
}

function fmt_date(string $date): string
{
    return date('D, M j, Y', strtotime($date));
}

/**
 * Keep parking_spots.is_taken in sync: a spot is "taken" while it has
 * any reservation dated today or later.
 */
function refresh_spot_status(PDO $pdo, int $spotId): void
{
    $stmt = $pdo->prepare(
        'UPDATE parking_spots p
         SET p.is_taken = EXISTS (
             SELECT 1 FROM reservations r
             WHERE r.spot_id = p.spot_id AND r.reservation_date >= CURDATE()
         )
         WHERE p.spot_id = ?'
    );
    $stmt->execute([$spotId]);
}
