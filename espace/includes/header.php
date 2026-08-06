<?php
require_once __DIR__ . '/auth.php';
$user = current_user();
$page_title = isset($page_title) ? $page_title : 'Dashboard';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($page_title) ?> | Espace Abou el Anouar</title>
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
                <span><?= current_user()['role'] === 'admin' ? 'Espace Admin' : 'Espace Surveillant' ?></span>
            </div>
        </div>
        <nav class="sidebar-nav">
            <?php if (current_user()['role'] === 'admin'): ?>
                <span class="nav-label">Administration</span>
                <a href="<?= url('admin/index.php') ?>" class="<?= basename($_SERVER['PHP_SELF']) === 'index.php' ? 'active' : '' ?>">
                    <i class="fas fa-user-shield"></i> Tableau de bord admin
                </a>
                <a href="<?= url('admin/users.php') ?>" class="<?= basename($_SERVER['PHP_SELF']) === 'users.php' ? 'active' : '' ?>">
                    <i class="fas fa-users-cog"></i> Comptes
                </a>
                <span class="nav-label">Gestion école</span>
            <?php endif; ?>
            <span class="nav-label">Menu</span>
            <a href="index.php" class="<?= basename($_SERVER['PHP_SELF']) === 'index.php' ? 'active' : '' ?>">
                <i class="fas fa-th-large"></i> Tableau de bord
            </a>
            <a href="emploi_du_temps.php" class="<?= basename($_SERVER['PHP_SELF']) === 'emploi_du_temps.php' ? 'active' : '' ?>">
                <i class="fas fa-calendar-week"></i> Emploi du temps
            </a>
            <a href="professeurs.php" class="<?= basename($_SERVER['PHP_SELF']) === 'professeurs.php' ? 'active' : '' ?>">
                <i class="fas fa-chalkboard-teacher"></i> Professeurs
            </a>
            <a href="matieres.php" class="<?= basename($_SERVER['PHP_SELF']) === 'matieres.php' ? 'active' : '' ?>">
                <i class="fas fa-book-open"></i> Matières
            </a>
            <a href="eleves.php" class="<?= basename($_SERVER['PHP_SELF']) === 'eleves.php' ? 'active' : '' ?>">
                <i class="fas fa-user-graduate"></i> Élèves
            </a>
            <a href="absences.php" class="<?= basename($_SERVER['PHP_SELF']) === 'absences.php' ? 'active' : '' ?>">
                <i class="fas fa-user-times"></i> Absences
            </a>
            <a href="notes.php" class="<?= basename($_SERVER['PHP_SELF']) === 'notes.php' ? 'active' : '' ?>">
                <i class="fas fa-clipboard-check"></i> Notes
            </a>
            <?php $nb_carnet = (int)q("SELECT COUNT(*) FROM mots_carnet WHERE lu = 0")->fetchColumn(); ?>
            <a href="carnet.php" class="<?= basename($_SERVER['PHP_SELF']) === 'carnet.php' ? 'active' : '' ?>">
                <i class="fas fa-book-open"></i> Carnet
                <?php if ($nb_carnet > 0): ?><span class="nav-badge"><?= $nb_carnet ?></span><?php endif; ?>
            </a>
            <a href="bulletins.php" class="<?= basename($_SERVER['PHP_SELF']) === 'bulletins.php' ? 'active' : '' ?>">
                <i class="fas fa-file-invoice"></i> Bulletins
            </a>
            <a href="examens.php" class="<?= basename($_SERVER['PHP_SELF']) === 'examens.php' ? 'active' : '' ?>">
                <i class="fas fa-file-signature"></i> Examens
            </a>
            <?php $nb_preins = (int)(q("SELECT COUNT(*) FROM preinscriptions WHERE statut='nouveau'")->fetchColumn()); ?>
            <a href="preinscriptions.php" class="<?= basename($_SERVER['PHP_SELF']) === 'preinscriptions.php' ? 'active' : '' ?>">
                <i class="fas fa-user-plus"></i> Pré-inscriptions
                <?php if ($nb_preins > 0): ?><span class="nav-badge"><?= $nb_preins ?></span><?php endif; ?>
            </a>
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
                <div class="user-avatar"><i class="fas fa-user-shield"></i></div>
                <div>
                    <strong><?= e(user_fullname() ?: 'Surveillant') ?></strong>
                    <span><?= e($user['role']) ?></span>
                </div>
            </div>
        </header>
        <div class="content">

        <?php foreach (get_flash() as $f): ?>
            <div class="alert alert-<?= e($f['type']) ?>"><?= e($f['message']) ?></div>
        <?php endforeach; ?>
