<?php
require_once __DIR__ . '/../includes/auth.php';
require_role('surveillant');
require_once __DIR__ . '/../includes/pdf.php';

$page_title = 'Élèves';
$classes = q('SELECT id, nom, cycle FROM classes ORDER BY id')->fetchAll();

// ---- AJOUT / MODIFICATION ----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['nom'])) {
    $id = (int)($_POST['id'] ?? 0);
    $nom = trim($_POST['nom']);
    $prenom = trim($_POST['prenom']);
    $date_naissance = $_POST['date_naissance'] ?? null;
    $lieu_naissance = trim($_POST['lieu_naissance'] ?? '');
    $nationalite = trim($_POST['nationalite'] ?? 'Algérienne');
    $sexe = $_POST['sexe'] ?? 'M';
    $classe_id = (int)($_POST['classe_id'] ?? 0);
    $adresse = trim($_POST['adresse'] ?? '');
    $tel_parent = trim($_POST['tel_parent'] ?? '');
    $email_parent = trim($_POST['email_parent'] ?? '');
    $date_inscription = $_POST['date_inscription'] ?? null;

    if ($nom === '' || $prenom === '') {
        set_flash('error', 'Le nom et le prénom sont obligatoires.');
    } elseif ($id > 0) {
        q('UPDATE eleves SET nom=?, prenom=?, date_naissance=?, lieu_naissance=?, nationalite=?, sexe=?, classe_id=?, adresse=?, telephone_parent=?, email_parent=?, date_inscription=? WHERE id=?',
          [$nom, $prenom, $date_naissance ?: null, $lieu_naissance ?: null, $nationalite, $sexe,
           $classe_id ?: null, $adresse ?: null, $tel_parent ?: null, $email_parent ?: null, $date_inscription ?: null, $id]);
        set_flash('success', 'Élève modifié avec succès.');
    } else {
        q('INSERT INTO eleves (nom, prenom, date_naissance, lieu_naissance, nationalite, sexe, classe_id, adresse, telephone_parent, email_parent, date_inscription)
           VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
          [$nom, $prenom, $date_naissance ?: null, $lieu_naissance ?: null, $nationalite, $sexe,
           $classe_id ?: null, $adresse ?: null, $tel_parent ?: null, $email_parent ?: null, $date_inscription ?: null]);
        set_flash('success', 'Élève ajouté avec succès.');
    }
    header('Location: eleves.php' . ($classe_id ? "?classe=$classe_id" : ''));
    exit;
}

// ---- SUPPRESSION ----
if (isset($_GET['delete'])) {
    $classe_id = (int)($_GET['classe'] ?? 0);
    q('DELETE FROM eleves WHERE id = ?', [(int)$_GET['delete']]);
    set_flash('success', 'Élève supprimé.');
    header('Location: eleves.php' . ($classe_id ? "?classe=$classe_id" : ''));
    exit;
}

// ---- ÉDITION ----
$edit = null;
if (isset($_GET['edit'])) {
    $edit = q('SELECT * FROM eleves WHERE id = ?', [(int)$_GET['edit']])->fetch();
}

// ---- FILTRE ----
$classe_filter = (int)($_GET['classe'] ?? ($classes[0]['id'] ?? 0));

// ---- EXPORT ----
if (isset($_GET['export'])) {
    $all = q('SELECT e.*, c.nom AS classe FROM eleves e LEFT JOIN classes c ON c.id = e.classe_id ORDER BY c.id, e.nom')->fetchAll();
    $rows = [];
    foreach ($all as $el) {
        $rows[] = [
            'nom' => $el['nom'],
            'prenom' => $el['prenom'],
            'classe' => $el['classe'] ?? '',
            'sexe' => $el['sexe'],
            'date_naissance' => $el['date_naissance'] ?? '',
            'lieu_naissance' => $el['lieu_naissance'] ?? '',
            'nationalite' => $el['nationalite'] ?? '',
            'adresse' => $el['adresse'] ?? '',
            'telephone_parent' => $el['telephone_parent'] ?? '',
            'email_parent' => $el['email_parent'] ?? '',
            'date_inscription' => $el['date_inscription'] ?? '',
        ];
    }
    if ($_GET['export'] === 'csv') {
        export_csv('eleves.csv', ['nom', 'prenom', 'classe', 'sexe', 'date_naissance', 'lieu_naissance', 'nationalite', 'adresse', 'telephone_parent', 'email_parent', 'date_inscription'], $rows);
    } else {
        pdf_export('Liste des élèves', ['Nom', 'Prénom', 'Classe', 'Sexe', 'Naissance', 'Lieu', 'Nationalité', 'Adresse', 'Tél. parent', 'Email parent', 'Inscription'], $rows, 'eleves');
    }
}

// ---- IMPORT ----
if (isset($_POST['do_import']) && isset($_FILES['import_csv']) && $_FILES['import_csv']['error'] === 0) {
    $lines = parse_csv($_FILES['import_csv']['tmp_name']);
    $count = 0;
    $errors = 0;
    foreach ($lines as $r) {
        $nom = trim($r['nom'] ?? '');
        $prenom = trim($r['prenom'] ?? '');
        if ($nom === '' || $prenom === '') { $errors++; continue; }
        $classe_id = lookup_id('classes', 'nom', $r['classe'] ?? '');
        q('INSERT INTO eleves (nom, prenom, date_naissance, lieu_naissance, nationalite, sexe, classe_id, adresse, telephone_parent, email_parent, date_inscription)
           VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
          [$nom, $prenom,
           trim($r['date_naissance'] ?? '') ?: null,
           trim($r['lieu_naissance'] ?? '') ?: null,
           trim($r['nationalite'] ?? '') ?: 'Algérienne',
           strtoupper(trim($r['sexe'] ?? 'M')) === 'F' ? 'F' : 'M',
           $classe_id,
           trim($r['adresse'] ?? '') ?: null,
           trim($r['telephone_parent'] ?? '') ?: null,
           trim($r['email_parent'] ?? '') ?: null,
           trim($r['date_inscription'] ?? '') ?: null]);
        $count++;
    }
    set_flash('success', "$count élève(s) importé(s)." . ($errors ? " $errors ligne(s) ignorée(s)." : ''));
    header('Location: eleves.php');
    exit;
}

$eleves = q('SELECT e.*, c.nom AS classe,
             (SELECT COUNT(*) FROM absences_eleves a WHERE a.eleve_id = e.id) AS nb_abs
             FROM eleves e LEFT JOIN classes c ON c.id = e.classe_id
             WHERE e.classe_id = ?
             ORDER BY e.nom, e.prenom', [$classe_filter])->fetchAll();
$classe_nom = $classes[array_search($classe_filter, array_column($classes, 'id'))]['nom'] ?? '';

$export_base = 'eleves.php';
$import_columns = 'nom;prenom;classe;sexe;date_naissance;lieu_naissance;nationalite;adresse;telephone_parent;email_parent;date_inscription';

include __DIR__ . '/../includes/header.php';
?>

<div class="section-title"><i class="fas fa-user-graduate"></i> Gestion des élèves</div>
<p class="section-sub">Liste des élèves par classe, informations et contact des parents.</p>

<?php include __DIR__ . '/../includes/export_ui.php'; ?>

<div class="filters">
    <form method="GET" style="display:flex; gap:12px; width:100%; align-items:flex-end;">
        <div class="form-group" style="flex:1;">
            <label for="classe">Classe</label>
            <select name="classe" id="classe" onchange="this.form.submit()">
                <?php foreach ($classes as $c): ?>
                    <option value="<?= $c['id'] ?>" <?= $c['id'] == $classe_filter ? 'selected' : '' ?>>
                        <?= e($c['nom']) ?> (<?= ucfirst(e($c['cycle'])) ?>)
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <button class="btn btn-primary" type="submit"><i class="fas fa-filter"></i> Filtrer</button>
    </form>
    <button class="btn btn-gold" data-modal-open="modalAjout"><i class="fas fa-plus"></i> Ajouter un élève</button>
</div>

<div class="card">
    <div class="card-head">
        <h3><i class="fas fa-users"></i> Élèves de la classe <span class="badge badge-blue"><?= e($classe_nom) ?></span></h3>
        <span class="badge badge-green"><?= count($eleves) ?> élève(s)</span>
    </div>
    <div class="table-wrap">
        <table class="data">
            <thead>
                <tr>
                    <th>Élève</th>
                    <th>Sexe</th>
                    <th>Naissance</th>
                    <th>Contact parent</th>
                    <th>Date inscription</th>
                    <th>Absences</th>
                    <th style="width:110px">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($eleves as $el): ?>
                    <tr>
                        <td><strong><?= e($el['prenom']) ?> <?= e($el['nom']) ?></strong></td>
                        <td><?= $el['sexe'] === 'F' ? '<i class="fas fa-venus" style="color:#c9a84c"></i>' : '<i class="fas fa-mars" style="color:#1B3A5C"></i>' ?></td>
                        <td>
                            <?= $el['date_naissance'] ? e($el['date_naissance']) : '—' ?>
                            <?php if ($el['lieu_naissance']): ?><div style="color:var(--muted);font-size:0.8rem;"><?= e($el['lieu_naissance']) ?></div><?php endif; ?>
                        </td>
                        <td>
                            <?php if ($el['telephone_parent']): ?><div><i class="fas fa-phone" style="color:var(--muted)"></i> <?= e($el['telephone_parent']) ?></div><?php endif; ?>
                            <?php if ($el['email_parent']): ?><div style="font-size:0.8rem;"><?= e($el['email_parent']) ?></div><?php endif; ?>
                            <?php if (!$el['telephone_parent'] && !$el['email_parent']): ?>—<?php endif; ?>
                        </td>
                        <td><?= e($el['date_inscription'] ?: '—') ?></td>
                        <td>
                            <a href="absences.php?type=eleves&eleve=<?= $el['id'] ?>" style="color:inherit">
                                <span class="badge <?= $el['nb_abs'] > 0 ? 'badge-red' : 'badge-green' ?>"><?= $el['nb_abs'] ?> jour(s)</span>
                            </a>
                        </td>
                        <td>
                            <a href="?edit=<?= $el['id'] ?>&classe=<?= $classe_filter ?>" class="btn btn-sm btn-primary" data-modal-open="modalAjout"><i class="fas fa-edit"></i></a>
                            <a href="?delete=<?= $el['id'] ?>&classe=<?= $classe_filter ?>" class="btn btn-sm btn-danger"
                               onclick="return confirm('Supprimer cet élève ?');"><i class="fas fa-trash"></i></a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- MODAL AJOUT / ÉDITION ÉLÈVE -->
<div class="modal-overlay" id="modalAjout<?= $edit ? ' open' : '' ?>">
    <div class="modal">
        <form method="POST">
            <input type="hidden" name="id" value="<?= $edit['id'] ?? 0 ?>">
            <div class="modal-head">
                <h3><i class="fas fa-user-<?= $edit ? 'edit' : 'plus' ?>" style="color:var(--gold)"></i> <?= $edit ? 'Modifier l\'élève' : 'Ajouter un élève' ?></h3>
                <button type="button" class="modal-close" data-modal-close><i class="fas fa-times"></i></button>
            </div>
            <div class="modal-body">
                <div class="form-grid">
                    <div class="form-group">
                        <label>Nom *</label>
                        <input type="text" name="nom" required placeholder="AMRANI" value="<?= e($edit['nom'] ?? '') ?>">
                    </div>
                    <div class="form-group">
                        <label>Prénom *</label>
                        <input type="text" name="prenom" required placeholder="Mohamed" value="<?= e($edit['prenom'] ?? '') ?>">
                    </div>
                    <div class="form-group">
                        <label>Classe *</label>
                        <select name="classe_id" required>
                            <?php foreach ($classes as $c): ?>
                                <option value="<?= $c['id'] ?>" <?= ($edit['classe_id'] ?? $classe_filter) == $c['id'] ? 'selected' : '' ?>><?= e($c['nom']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Sexe</label>
                        <select name="sexe">
                            <option value="M" <?= ($edit['sexe'] ?? 'M') === 'M' ? 'selected' : '' ?>>Masculin</option>
                            <option value="F" <?= ($edit['sexe'] ?? '') === 'F' ? 'selected' : '' ?>>Féminin</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Date de naissance</label>
                        <input type="date" name="date_naissance" value="<?= e($edit['date_naissance'] ?? '') ?>">
                    </div>
                    <div class="form-group">
                        <label>Lieu de naissance</label>
                        <input type="text" name="lieu_naissance" placeholder="Alger" value="<?= e($edit['lieu_naissance'] ?? '') ?>">
                    </div>
                    <div class="form-group">
                        <label>Nationalité</label>
                        <input type="text" name="nationalite" value="<?= e($edit['nationalite'] ?? 'Algérienne') ?>">
                    </div>
                    <div class="form-group">
                        <label>Date d'inscription</label>
                        <input type="date" name="date_inscription" value="<?= e($edit['date_inscription'] ?? '') ?>">
                    </div>
                    <div class="form-group">
                        <label>Téléphone parent</label>
                        <input type="text" name="tel_parent" placeholder="0551234567" value="<?= e($edit['telephone_parent'] ?? '') ?>">
                    </div>
                    <div class="form-group">
                        <label>Email parent</label>
                        <input type="email" name="email_parent" placeholder="parent@gmail.com" value="<?= e($edit['email_parent'] ?? '') ?>">
                    </div>
                    <div class="form-group" style="grid-column:1 / -1;">
                        <label>Adresse</label>
                        <textarea name="adresse" placeholder="Aïn Benian, Alger"><?= e($edit['adresse'] ?? '') ?></textarea>
                    </div>
                </div>
            </div>
            <div class="modal-foot">
                <button type="button" class="btn btn-danger" data-modal-close>Annuler</button>
                <button type="submit" class="btn btn-gold"><i class="fas fa-check"></i> <?= $edit ? 'Mettre à jour' : 'Enregistrer' ?></button>
            </div>
        </form>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
