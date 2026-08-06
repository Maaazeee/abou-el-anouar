<?php
require_once __DIR__ . '/auth.php';
require_role('parent');
$user = current_user();
$page_title = isset($page_title) ? $page_title : 'Espace Parent';
$nav_items = [
    ['index.php', 'Tableau de bord', 'th-large'],
    ['notes.php', 'Notes', 'clipboard-check'],
    ['bulletins.php', 'Bulletins', 'file-invoice'],
    ['carnet.php', 'Carnet', 'book-open'],
    ['absences.php', 'Absences', 'user-times'],
    ['emploi_du_temps.php', 'Emploi du temps', 'calendar-week'],
    ['examens.php', 'Examens', 'file-signature'],
];
$u = current_user();
$nb_carnet = (int)q("SELECT COUNT(*) FROM mots_carnet m JOIN eleves e ON e.id = m.eleve_id WHERE e.email_parent = ? AND m.lu = 0", [$u['email'] ?? ''])->fetchColumn();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($page_title) ?> | Espace Parent</title>
    <link rel="stylesheet" href="../css/dashboard.css">
    <link rel="icon" type="image/jpg" href="../../images/logo_ecole.jpg">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
</head>
<body>
<div class="dash">
    <!-- ===== SIDEBAR ===== -->
    <aside class="sidebar" id="sidebar">
        <div class="sidebar-logo">
            <img src="../../images/logo_ecole.jpg" alt="Logo">
            <div>
                <strong>Abou el Anouar</strong>
                <span>Espace Parent</span>
            </div>
        </div>
        <nav class="sidebar-nav">
            <span class="nav-label">Menu</span>
            <?php foreach ($nav_items as [$file, $label, $icon]): ?>
                <a href="<?= $file ?>" class="<?= basename($_SERVER['PHP_SELF']) === $file ? 'active' : '' ?>">
                    <i class="fas fa-<?= $icon ?>"></i> <?= $label ?>
                    <?php if ($file === 'carnet.php' && $nb_carnet > 0): ?><span class="nav-badge"><?= $nb_carnet ?></span><?php endif; ?>
                </a>
            <?php endforeach; ?>
        </nav>
        <div class="sidebar-footer">
            <a href="../logout.php"><i class="fas fa-sign-out-alt"></i> Déconnexion</a>
        </div>
    </aside>

    <!-- ===== MAIN ===== -->
    <main class="main">
        <header class="topbar-dash">
            <button class="menu-toggle" id="menuToggle"><i class="fas fa-bars"></i></button>
            <h2><?= e($page_title) ?></h2>
            <div class="topbar-user">
                <div class="user-avatar" style="background:var(--green)"><i class="fas fa-user-friends"></i></div>
                <div>
                    <strong><?= e(user_fullname() ?: 'Parent') ?></strong>
                    <span><?= e($user['role']) ?></span>
                </div>
            </div>
        </header>
        <div class="content">

        <?php foreach (get_flash() as $f): ?>
            <div class="alert alert-<?= e($f['type']) ?>"><?= e($f['message']) ?></div>
        <?php endforeach; ?>
