<?php
/** Page header + navigation. Set $pageTitle before including. */
$current = basename($_SERVER['PHP_SELF']);
$nav = [
    'index.php'        => 'Spots',
    'register.php'     => 'Register',
    'reserve.php'      => 'Reserve',
    'reservations.php' => 'My Reservations',
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($pageTitle ?? 'ParkEasy') ?> · ParkEasy</title>
    <!-- ?v= changes whenever the file is edited, so the browser never uses a stale cached copy -->
    <link rel="stylesheet" href="assets/css/style.css?v=<?= filemtime(__DIR__ . '/../assets/css/style.css') ?>">
</head>
<body>
<header class="site-header">
    <div class="container header-inner">
        <a href="index.php" class="logo"><span class="logo-mark">P</span>ParkEasy</a>
        <button class="nav-toggle" aria-label="Open menu" aria-expanded="false">&#9776;</button>
        <nav class="main-nav">
            <?php foreach ($nav as $file => $label): ?>
                <a href="<?= $file ?>" class="<?= $current === $file ? 'active' : '' ?>"><?= $label ?></a>
            <?php endforeach; ?>
        </nav>
    </div>
</header>

<main class="container">
    <?php foreach (get_flashes() as $f): ?>
        <div class="alert alert-<?= e($f['type']) ?>"><?= e($f['message']) ?></div>
    <?php endforeach; ?>
