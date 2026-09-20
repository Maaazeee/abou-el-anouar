<?php
require_once __DIR__ . '/../includes/auth.php';
require_role('admin');

$page_title = 'Comptes utilisateurs';
$roles = ['parent', 'surveillant', 'admin'];
$role_badge = [
    'parent' => 'badge-green',
    'surveillant' => 'badge-blue',
    'admin' => 'badge-red',
];
$eleves = q('SELECT id, prenom, nom, (SELECT nom FROM classes WHERE id = eleves.classe_id) AS classe FROM eleves ORDER BY nom, prenom')->fetchAll();

// ---- AJOUT / MODIFICATION ----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['username'])) {
    $id = (int)($_POST['id'] ?? 0);
    $username = trim($_POST['username']);
    $role = in_array($_POST['role'] ?? '', $roles, true) ? $_POST['role'] : 'parent';
    $nom = trim($_POST['nom'] ?? '');
    $prenom = trim($_POST['prenom'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $enfant_id = (int)($_POST['enfant_id'] ?? 0);

    if ($username === '') {
        set_flash('error', "Le nom d'utilisateur est obligatoire.");
        header('Location: users.php' . ($id ? "?edit=$id" : ''));
        exit;
    }
    $dupe = q('SELECT id FROM users WHERE username = ? AND id <> ?', [$username, $id ?: -1])->fetch();
    if ($dupe) {
        set_flash('error', "Ce nom d'utilisateur est déjà utilisé.");
        header('Location: users.php' . ($id ? "?edit=$id" : ''));
        exit;
    }

    if ($id > 0) {
        $fields = 'role=?, nom=?, prenom=?, email=?';
        $params = [$role, $nom ?: null, $prenom ?: null, $email ?: null];
        if ($password !== '') {
            $fields .= ', password=?';
            $params[] = password_hash($password, PASSWORD_DEFAULT);
        }
        $params[] = $id;
        q("UPDATE users SET $fields WHERE id = ?", $params);
        set_flash('success', "Compte « $username » mis à jour.");
    } else {
        if ($password === '') {
            set_flash('error', 'Un mot de passe est requis pour un nouveau compte.');
            header('Location: users.php');
            exit;
        }
        q('INSERT INTO users (username, password, role, nom, prenom, email) VALUES (?,?,?,?,?,?)',
          [$username, password_hash($password, PASSWORD_DEFAULT), $role, $nom ?: null, $prenom ?: null, $email ?: null]);
        set_flash('success', "Compte « $username » créé.");
    }

    // Lien parent → élève (via email) si rôle parent et email renseignés
    if ($role === 'parent' && $email !== '') {
        if ($enfant_id > 0) {
            q('UPDATE eleves SET email_parent = ? WHERE id = ?', [$email, $enfant_id]);
        } else {
            // Si le parent change d'email, on met à jour l'élève déjà lié à l'ancien email
            $ancien = q('SELECT email FROM users WHERE username = ?', [$username])->fetch();
            if ($ancien && $ancien['email'] && $ancien['email'] !== $email) {
                q('UPDATE eleves SET email_parent = ? WHERE email_parent = ?', [$email, $ancien['email']]);
            }
        }
    }
    header('Location: users.php');
    exit;
}

// ---- SUPPRESSION ----
if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    $me = (int)(current_user()['id'] ?? 0);
    $target = q('SELECT * FROM users WHERE id = ?', [$id])->fetch();
    if ($target && $target['username'] !== 'admin' && $id !== $me) {
        q('DELETE FROM users WHERE id = ?', [$id]);
        set_flash('success', "Compte « " . $target['username'] . " » supprimé.");
    } else {
        set_flash('error', 'Impossible de supprimer ce compte (compte admin ou compte courant).');
    }
    header('Location: users.php');
    exit;
}

// ---- ÉDITION ----
$edit = null;
$edit_enfant_id = 0;
if (isset($_GET['edit'])) {
    $edit = q('SELECT * FROM users WHERE id = ?', [(int)$_GET['edit']])->fetch();
    if ($edit && $edit['email']) {
        $li = q('SELECT id FROM eleves WHERE email_parent = ? LIMIT 1', [$edit['email']])->fetch();
        if ($li) $edit_enfant_id = (int)$li['id'];
    }
}

// ---- LISTE ----
$users = q("SELECT u.*,
            (SELECT STRING_AGG(e.prenom || ' ' || e.nom, ', ')
             FROM eleves e WHERE e.email_parent = u.email) AS enfants
            FROM users u
            ORDER BY u.role, u.username")->fetchAll();

include __DIR__ . '/../includes/header_admin.php';
?>

<div class="section-title"><i class="fas fa-users-cog"></i> Comptes utilisateurs</div>
<p class="section-sub">Créez et gérez les comptes des parents et des surveillants. Le lien parent → élève se fait par l'email (emails.email_parent).</p>

<div class="card">
    <div class="card-head">
        <h3><?= $edit ? 'Modifier le compte' : 'Créer un compte' ?></h3>
        <?php if ($edit): ?><a href="users.php" class="btn btn-danger">Annuler</a><?php endif; ?>
    </div>
    <div class="card-body">
        <form method="POST" class="form-grid" novalidate>
            <input type="hidden" name="id" value="<?= $edit['id'] ?? 0 ?>">
            <div class="form-group">
                <label>Nom d'utilisateur *</label>
                <input type="text" name="username" required value="<?= e($edit['username'] ?? '') ?>" placeholder="ex : parent_haddadi">
            </div>
            <div class="form-group">
                <label>Rôle *</label>
                <select name="role" id="u_role">
                    <?php foreach (['parent' => 'Parent', 'surveillant' => 'Surveillant', 'admin' => 'Administrateur'] as $val => $lbl): ?>
                        <option value="<?= $val ?>" <?= ($edit['role'] ?? 'parent') === $val ? 'selected' : '' ?>><?= $lbl ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label>Prénom</label>
                <input type="text" name="prenom" value="<?= e($edit['prenom'] ?? '') ?>" placeholder="Karim">
            </div>
            <div class="form-group">
                <label>Nom</label>
                <input type="text" name="nom" value="<?= e($edit['nom'] ?? '') ?>" placeholder="HADDADI">
            </div>
            <div class="form-group" style="grid-column: 1 / -1;">
                <label>Adresse email (lien parent) <span class="badge badge-gold">important</span></label>
                <input type="email" name="email" value="<?= e($edit['email'] ?? '') ?>" placeholder="parent@gmail.com">
                <p style="font-size:0.78rem; color:var(--muted); margin-top:4px;">Pour un parent : doit correspondre à <code>emails.email_parent</code> de l'élève (ou choisissez l'enfant ci-dessous).</p>
            </div>
            <div class="form-group" id="u_role_parent_group" <?= ($edit['role'] ?? 'parent') === 'parent' ? '' : 'style="display:none;"' ?>>
                <label>Enfant à lier</label>
                <select name="enfant_id">
                    <option value="">— Aucun / déjà lié —</option>
                    <?php foreach ($eleves as $el): ?>
                        <option value="<?= $el['id'] ?>" <?= $el['id'] == $edit_enfant_id ? 'selected' : '' ?>><?= e($el['prenom']) ?> <?= e($el['nom']) ?> (<?= e($el['classe'] ?: '—') ?>)</option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label>Mot de passe <?= $edit ? '(vide = inchangé)' : '*' ?></label>
                <input type="password" name="password" value="" placeholder="<?= $edit ? '••••••••' : 'Mot de passe' ?>" <?= $edit ? '' : 'required' ?>>
            </div>
            <div class="form-actions" style="grid-column: 1 / -1;">
                <button type="submit" class="btn btn-gold"><i class="fas fa-save"></i> <?= $edit ? 'Mettre à jour' : 'Créer le compte' ?></button>
            </div>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-head">
        <h3><i class="fas fa-users"></i> Liste des comptes</h3>
        <span class="badge badge-blue"><?= count($users) ?> compte(s)</span>
    </div>
    <div class="table-wrap">
        <table class="data">
            <thead>
                <tr>
                    <th>Utilisateur</th>
                    <th>Rôle</th>
                    <th>Nom complet</th>
                    <th>Email</th>
                    <th>Enfant(s) lié(s)</th>
                    <th style="width:110px">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!$users): ?>
                    <tr><td colspan="6"><div class="empty-state"><i class="fas fa-users"></i><p>Aucun compte.</p></div></td></tr>
                <?php endif; ?>
                <?php foreach ($users as $u): ?>
                    <?php $is_self = (int)($u['id']) === (int)(current_user()['id'] ?? 0); ?>
                    <tr>
                        <td>
                            <strong><?= e($u['username']) ?></strong>
                            <?php if ($u['username'] === 'admin'): ?><span class="badge badge-red">principal</span><?php endif; ?>
                        </td>
                        <td><span class="badge <?= $role_badge[$u['role']] ?? 'badge-gray' ?>"><?= e($u['role']) ?></span></td>
                        <td><?= e(trim(($u['prenom'] ?? '') . ' ' . ($u['nom'] ?? ''))) ?: '—' ?></td>
                        <td><?= e($u['email'] ?? '') ?: '—' ?></td>
                        <td><?= e($u['enfants'] ?? '') ?: ($u['role'] === 'parent' ? '<span class="badge badge-gray">non lié</span>' : '—') ?></td>
                        <td>
                            <a href="?edit=<?= $u['id'] ?>" class="btn btn-sm btn-primary" title="Modifier"><i class="fas fa-edit"></i></a>
                            <?php if ($u['username'] !== 'admin' && !$is_self): ?>
                                <a href="?delete=<?= $u['id'] ?>" class="btn btn-sm btn-danger" title="Supprimer"
                                   onclick="return confirm('Supprimer le compte « <?= e($u['username']) ?> » ?');"><i class="fas fa-trash"></i></a>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
    (function () {
        var g = document.getElementById('u_role_parent_group');
        var sel = document.getElementById('u_role');
        if (g && sel) {
            sel.addEventListener('change', function () {
                g.style.display = this.value === 'parent' ? '' : 'none';
            });
        }
    })();
</script>

<?php include __DIR__ . '/../includes/footer_admin.php'; ?>