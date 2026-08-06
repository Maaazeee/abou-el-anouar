<?php
require_once __DIR__ . '/../includes/auth.php';
require_role('admin');

$page_title = 'Tableau de bord';

$nb_users = (int)q('SELECT COUNT(*) c FROM users')->fetch()['c'];
$nb_parents = (int)q("SELECT COUNT(*) c FROM users WHERE role = 'parent'")->fetch()['c'];
$nb_surveillants = (int)q("SELECT COUNT(*) c FROM users WHERE role = 'surveillant'")->fetch()['c'];
$nb_admins = (int)q("SELECT COUNT(*) c FROM users WHERE role = 'admin'")->fetch()['c'];
$nb_enfants_lies = (int)q("SELECT COUNT(*) c FROM eleves WHERE email_parent IS NOT NULL AND email_parent <> ''")->fetch()['c'];
$nb_enfants_sans_lien = (int)q("SELECT COUNT(*) c FROM eleves WHERE email_parent IS NULL OR email_parent = ''")->fetch()['c'];

$comptes_recents = q('SELECT username, role, nom, prenom, email, created_at FROM users ORDER BY created_at DESC, id DESC LIMIT 6')->fetchAll();

include __DIR__ . '/../includes/header_admin.php';
?>

<div class="section-title"><i class="fas fa-user-shield"></i> Administration</div>
<p class="section-sub">Gérez les comptes (parents, surveillants, administrateurs) et accédez à la gestion de l'école.</p>

<div class="cards-grid">
    <div class="stat-card">
        <div class="stat-ic red"><i class="fas fa-users"></i></div>
        <div><div class="stat-num"><?= $nb_users ?></div><div class="stat-label">Comptes</div></div>
    </div>
    <div class="stat-card">
        <div class="stat-ic green"><i class="fas fa-user-friends"></i></div>
        <div><div class="stat-num"><?= $nb_parents ?></div><div class="stat-label">Parents</div></div>
    </div>
    <div class="stat-card">
        <div class="stat-ic blue"><i class="fas fa-user-shield"></i></div>
        <div><div class="stat-num"><?= $nb_surveillants ?></div><div class="stat-label">Surveillants</div></div>
    </div>
    <div class="stat-card">
        <div class="stat-ic gold"><i class="fas fa-user-tie"></i></div>
        <div><div class="stat-num"><?= $nb_admins ?></div><div class="stat-label">Administrateurs</div></div>
    </div>
</div>

<div class="cards-grid">
    <div class="card">
        <div class="card-head">
            <h3><i class="fas fa-user-graduate" style="color:var(--gold)"></i> Liens parents / élèves</h3>
            <a href="users.php" class="btn btn-gold btn-sm"><i class="fas fa-users-cog"></i> Gérer les comptes</a>
        </div>
        <div class="card-body">
            <div class="cards-grid">
                <div class="stat-card">
                    <div class="stat-ic green"><i class="fas fa-link"></i></div>
                    <div><div class="stat-num"><?= $nb_enfants_lies ?></div><div class="stat-label">Élèves liés à un parent</div></div>
                </div>
                <div class="stat-card">
                    <div class="stat-ic red"><i class="fas fa-unlink"></i></div>
                    <div><div class="stat-num"><?= $nb_enfants_sans_lien ?></div><div class="stat-label">Élèves sans compte parent</div></div>
                </div>
            </div>
            <p class="section-sub" style="margin-top:10px;"><i class="fas fa-info-circle"></i> Le lien parent → élève repose sur l'adresse email : <code>users.email</code> doit être égale à <code>eleves.email_parent</code>. Créez ou modifiez un compte parent pour lier un élève.</p>
        </div>
    </div>

    <div class="card">
        <div class="card-head">
            <h3><i class="fas fa-clock" style="color:var(--blue)"></i> Comptes récents</h3>
            <a href="users.php" class="btn btn-sm btn-primary">Voir tout</a>
        </div>
        <div class="table-wrap">
            <table class="data">
                <thead><tr><th>Utilisateur</th><th>Rôle</th><th>Nom</th></tr></thead>
                <tbody>
                    <?php if (!$comptes_recents): ?>
                        <tr><td colspan="3"><div class="empty-state"><i class="fas fa-users"></i><p>Aucun compte.</p></div></td></tr>
                    <?php endif; ?>
                    <?php foreach ($comptes_recents as $c): ?>
                        <tr>
                            <td><code><?= e($c['username']) ?></code></td>
                            <td>
                                <span class="badge <?= $c['role'] === 'admin' ? 'badge-red' : ($c['role'] === 'surveillant' ? 'badge-blue' : 'badge-green') ?>">
                                    <?= e($c['role']) ?>
                                </span>
                            </td>
                            <td><?= e(trim(($c['prenom'] ?? '') . ' ' . ($c['nom'] ?? ''))) ?: '—' ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-head">
        <h3><i class="fas fa-arrow-right" style="color:var(--gold)"></i> Accès rapide</h3>
    </div>
    <div class="card-body">
        <div style="display:flex; gap:8px; flex-wrap:wrap;">
            <a href="users.php" class="btn btn-gold"><i class="fas fa-user-plus"></i> Créer un compte</a>
            <a href="<?= url('surveillant/index.php') ?>" class="btn btn-primary"><i class="fas fa-th-large"></i> Tableau de bord école</a>
            <a href="<?= url('surveillant/preinscriptions.php') ?>" class="btn btn-primary"><i class="fas fa-user-plus"></i> Pré-inscriptions</a>
            <a href="<?= url('surveillant/eleves.php') ?>" class="btn btn-primary"><i class="fas fa-user-graduate"></i> Élèves</a>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer_admin.php'; ?>