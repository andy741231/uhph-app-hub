<?php
require_once __DIR__ . '/auth.php';
flipbook_require_admin();
$csrfToken = flipbook_csrf_token();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
    <link rel="icon" type="image/png" href="favicon.png">
    <link rel="icon" type="image/x-icon" href="favicon.ico">
    <title><?= isset($pageTitle) ? $pageTitle . ' - ' : '' ?><?= APP_NAME ?></title>
    <base href="<?= BASE_PATH ?>/">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Crimson+Text:ital,wght@0,400;0,600;0,700;1,400&family=League+Gothic&family=Source+Sans+3:ital,wght@0,200..900;1,200..900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="assets/css/app.css">
    <?php if (isset($extraHead)) echo $extraHead; ?>
</head>
<body>
    <nav class="navbar">
        <div class="navbar-inner">
            <a href="index.php" class="navbar-brand">
                <i class="fas fa-book-open"></i>
                <span><?= APP_NAME ?></span>
            </a>
            <div class="navbar-actions">
                <?php if ($admin = flipbook_current_admin()): ?>
                    <span class="text-sm" style="color:var(--gray-600);"><?= htmlspecialchars($admin['name'], ENT_QUOTES, 'UTF-8') ?></span>
                    <?php if (($admin['application_count'] ?? 1) > 1): ?>
                        <a href="<?= htmlspecialchars(FLIPBOOK_HUB_BASE_URL, ENT_QUOTES, 'UTF-8') ?>" class="btn btn-secondary btn-sm">
                            <i class="fas fa-grip"></i> All applications
                        </a>
                    <?php endif; ?>
                <?php endif; ?>
                <a href="upload.php" class="btn btn-primary btn-sm">
                    <i class="fas fa-plus"></i> New Flipbook
                </a>
                <?php if (FLIPBOOK_HUB_SSO_ENABLED): ?>
                    <form method="POST" action="auth/logout.php" style="display:inline;">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
                        <button type="submit" class="btn btn-secondary btn-sm">Sign Out</button>
                    </form>
                <?php endif; ?>
            </div>
        </div>
    </nav>
    <main class="main-content">
