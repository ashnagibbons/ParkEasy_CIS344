<?php
/**
 * User registration form -> INSERT INTO users.
 */
require_once 'config/db.php';
require_once 'includes/functions.php';

$errors = [];
$name   = '';
$email  = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf()) {
        $errors[] = 'Your session expired. Please try again.';
    }

    $name  = post('name');
    $email = strtolower(post('email'));

    // ---- Validation ----
    if ($name === '' || mb_strlen($name) > 100) {
        $errors[] = 'Please enter your name (max 100 characters).';
    } elseif (!preg_match("/^[\p{L} .'-]+$/u", $name)) {
        $errors[] = 'Name may only contain letters, spaces, apostrophes, periods and hyphens.';
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 150) {
        $errors[] = 'Please enter a valid email address.';
    }

    // ---- Duplicate email check ----
    if (!$errors) {
        $stmt = $pdo->prepare('SELECT user_id FROM users WHERE email = ?');
        $stmt->execute([$email]);
        if ($stmt->fetch()) {
            $errors[] = 'That email is already registered. You can go straight to Reserve.';
        }
    }

    // ---- Insert (prepared statement prevents SQL injection) ----
    if (!$errors) {
        $stmt = $pdo->prepare('INSERT INTO users (name, email) VALUES (?, ?)');
        $stmt->execute([$name, $email]);
        flash('success', "Welcome, $name! You can now reserve a spot.");
        redirect('reserve.php?email=' . urlencode($email));
    }
}

$pageTitle = 'Register';
require 'includes/header.php';
?>

<section class="card form-card">
    <h1>Create an account</h1>
    <p class="muted">Register once, then use your email to book spots.</p>

    <?php if ($errors): ?>
        <div class="alert alert-error"><ul><?php foreach ($errors as $err): ?><li><?= e($err) ?></li><?php endforeach; ?></ul></div>
    <?php endif; ?>

    <form method="post" action="register.php" novalidate data-validate>
        <?= csrf_field() ?>
        <div class="field">
            <label for="name">Full name</label>
            <input type="text" id="name" name="name" maxlength="100" required value="<?= e($name) ?>" placeholder="Jane Doe">
        </div>
        <div class="field">
            <label for="email">Email</label>
            <input type="email" id="email" name="email" maxlength="150" required value="<?= e($email) ?>" placeholder="jane@example.com">
        </div>
        <button type="submit" class="btn btn-primary btn-block">Register</button>
    </form>
</section>

<?php require 'includes/footer.php'; ?>
