<?php
require_once __DIR__ . '/../includes/auth.php';
require_role('surveillant');
require_once __DIR__ . '/../includes/pdf.php';

$page_title = 'Professeurs';
$matieres = q('SELECT id, nom FROM matieres ORDER BY nom')->fetchAll();

// ---- RECALCUL AUTO DES HEURES (depuis l'emploi du temps) ----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['recalc_heures'])) {
    q("UPDATE professeurs p
       SET p.total_heures = COALESCE((
           SELECT SUM(TIMESTAMPDIFF(MINUTE, e.heure_debut, e.heure_fin)) / 60.0
           FROM emploi_du_temps e WHERE e.professeur_id = p.id), 0)");
    set_flash('success', "Heures recalculées automatiquement depuis l'emploi du temps.");
    header('Location: professeurs.php');
    exit;
}

// ---- AJOUT / MODIFICATION ----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['nom'])) {
    $id = (int)($_POST['id'] ?? 0);
    $nom = trim($_POST['nom']);
    $prenom = trim($_POST['prenom']);
    $email = trim($_POST['email'] ?? '');
    $adresse = trim($_POST['adresse'] ?? '');
    $telephone = trim($_POST['telephone'] ?? '');
    $matiere_id = (int)($_POST['matiere_id'] ?? 0);
    $prix_heure = (float)($_POST['prix_heure'] ?? 0);
    $total_heures = (float)($_POST['total_heures'] ?? 0);

    if ($nom === '' || $prenom === '') {
        set_flash('error', 'Le nom et le prénom sont obligatoires.');
    } elseif ($id > 0) {
        q('UPDATE professeurs SET nom=?, prenom=?, email=?, adresse=?, telephone=?, matiere_id=?, prix_heure=?, total_heures=? WHERE id=?',
          [$nom, $prenom, $email ?: null, $adresse ?: null, $telephone ?: null,
           $matiere_id ?: null, $prix_heure, $total_heures, $id]);
        set_flash('success', 'Professeur modifié avec succès.');
    } else {
        q('INSERT INTO professeurs (nom, prenom, email, adresse, telephone, matiere_id, prix_heure, total_heures)
           VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
          [$nom, $prenom, $email ?: null, $adresse ?: null, $telephone ?: null,
           $matiere_id ?: null, $prix_heure, $total_heures]);
        set_flash('success', 'Professeur ajouté avec succès.');
    }
    header('Location: professeurs.php');
    exit;
}

// ---- SUPPRESSION ----
if (isset($_GET['delete'])) {
    q('DELETE FROM professeurs WHERE id = ?', [(int)$_GET['delete']]);
    set_flash('success', 'Professeur supprimé.');
    header('Location: professeurs.php');
    exit;
}

// ---- ÉDITION (charger données) ----
$edit = null;
if (isset($_GET['edit'])) {
    $edit = q('SELECT * FROM professeurs WHERE id = ?', [(int)$_GET['edit']])->fetch();
}

// ---- EXPORT ----
if (isset($_GET['export'])) {
    $profs = q('SELECT p.*, m.nom AS matiere,
                (SELECT COUNT(*) FROM absences_profs a WHERE a.professeur_id = p.id) AS nb_abs
                FROM professeurs p LEFT JOIN matieres m ON m.id = p.matiere_id
                ORDER BY p.nom, p.prenom')->fetchAll();
    $rows = [];
    foreach ($profs as $p) {
        $rows[] = [
            'nom' => $p['nom'],
            'prenom' => $p['prenom'],
            'email' => $p['email'] ?? '',
            'adresse' => $p['adresse'] ?? '',
            'telephone' => $p['telephone'] ?? '',
            'matiere' => $p['matiere'] ?? '',
            'prix_heure' => number_format((float)$p['prix_heure'], 2, ',', ' '),
            'total_heures' => $p['total_heures'],
            'total_paiement' => number_format((float)$p['prix_heure'] * (float)$p['total_heures'], 2, ',', ' '),
            'absences' => $p['nb_abs'],
        ];
    }
    if ($_GET['export'] === 'csv') {
        export_csv('professeurs.csv', ['nom', 'prenom', 'email', 'adresse', 'telephone', 'matiere', 'prix_heure', 'total_heures', 'total_paiement', 'absences'], $rows);
    } else {
        pdf_export('Liste des professeurs', ['Nom', 'Prénom', 'Email', 'Adresse', 'Téléphone', 'Matière', 'Prix/h', 'Heures', 'Total', 'Abs.'], $rows, 'professeurs');
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
        $matiere_id = lookup_id('matieres', 'nom', $r['matiere'] ?? '');
        q('INSERT INTO professeurs (nom, prenom, email, adresse, telephone, matiere_id, prix_heure, total_heures)
           VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
          [$nom, $prenom,
           trim($r['email'] ?? '') ?: null,
           trim($r['adresse'] ?? '') ?: null,
           trim($r['telephone'] ?? '') ?: null,
           $matiere_id,
           (float)str_replace(',', '.', $r['prix_heure'] ?? 0),
           (float)str_replace(',', '.', $r['total_heures'] ?? 0)]);
        $count++;
    }
    set_flash('success', "$count professeur(s) importé(s)." . ($errors ? " $errors ligne(s) ignorée(s)." : ''));
    header('Location: professeurs.php');
    exit;
}

// ---- LISTE ----
$profs = q('SELECT p.*, m.nom AS matiere,
            (SELECT COUNT(*) FROM absences_profs a WHERE a.professeur_id = p.id) AS nb_abs,
            (SELECT COALESCE(SUM(TIMESTAMPDIFF(MINUTE, e.heure_debut, e.heure_fin)) / 60.0, 0)
             FROM emploi_du_temps e WHERE e.professeur_id = p.id) AS heures_edt
            FROM professeurs p LEFT JOIN matieres m ON m.id = p.matiere_id
            ORDER BY p.nom, p.prenom')->fetchAll();

$export_base = 'professeurs.php';
$import_columns = 'nom;prenom;email;adresse;telephone;matiere;prix_heure;total_heures';

include __DIR__ . '/../includes/header.php';
?>

<div class="section-title"><i class="fas fa-chalkboard-teacher"></i> Gestion des professeurs</div>
<p class="section-sub">Fiche, rémunération et suivi des absences.</p>

<?php include __DIR__ . '/../includes/export_ui.php'; ?>

<div class="card">
    <div class="card-head">
        <h3><?= $edit ? 'Modifier le professeur' : 'Ajouter un professeur' ?></h3>
    </div>
    <div class="card-body">
        <form method="POST" class="form-grid">
            <input type="hidden" name="id" value="<?= $edit['id'] ?? 0 ?>">
            <div class="form-group">
                <label>Nom *</label>
                <input type="text" name="nom" required value="<?= e($edit['nom'] ?? '') ?>" placeholder="BENALI">
            </div>
            <div class="form-group">
                <label>Prénom *</label>
                <input type="text" name="prenom" required value="<?= e($edit['prenom'] ?? '') ?>" placeholder="Karim">
            </div>
            <div class="form-group">
                <label>Email</label>
                <input type="email" name="email" value="<?= e($edit['email'] ?? '') ?>" placeholder="prof@abouelanouar.dz">
            </div>
            <div class="form-group">
                <label>Téléphone</label>
                <input type="text" name="telephone" value="<?= e($edit['telephone'] ?? '') ?>" placeholder="0551234567">
            </div>
            <div class="form-group">
                <label>Matière</label>
                <select name="matiere_id">
                    <option value="">— Aucune —</option>
                    <?php foreach ($matieres as $m): ?>
                        <option value="<?= $m['id'] ?>" <?= ($edit['matiere_id'] ?? 0) == $m['id'] ? 'selected' : '' ?>><?= e($m['nom']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label>Prix heure (DA)</label>
                <input type="number" step="0.01" min="0" name="prix_heure" value="<?= e($edit['prix_heure'] ?? '') ?>" placeholder="1200">
            </div>
            <div class="form-group">
                <label>Total heures</label>
                <input type="number" step="0.5" min="0" name="total_heures" value="<?= e($edit['total_heures'] ?? '') ?>" placeholder="20">
            </div>
            <div class="form-group" style="grid-column: 1 / -1;">
                <label>Adresse</label>
                <textarea name="adresse" placeholder="Aïn Benian, Alger"><?= e($edit['adresse'] ?? '') ?></textarea>
            </div>
            <div class="form-actions" style="grid-column: 1 / -1;">
                <button type="submit" class="btn btn-gold"><i class="fas fa-save"></i> <?= $edit ? 'Mettre à jour' : 'Enregistrer' ?></button>
                <?php if ($edit): ?>
                    <a href="professeurs.php" class="btn btn-danger"><i class="fas fa-times"></i> Annuler</a>
                <?php endif; ?>
            </div>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-head">
        <h3><i class="fas fa-list"></i> Liste des professeurs</h3>
        <div style="display:flex; gap:8px; align-items:center;">
            <span class="badge badge-blue"><?= count($profs) ?> professeur(s)</span>
            <form method="POST" style="display:inline-block;">
                <button type="submit" name="recalc_heures" value="1" class="btn btn-sm btn-gold" title="Recalculer les heures depuis l'emploi du temps"><i class="fas fa-sync-alt"></i> Recalculer les heures (EDT)</button>
            </form>
        </div>
    </div>
    <div class="table-wrap">
        <table class="data">
            <thead>
                <tr>
                    <th>Professeur</th>
                    <th>Contact</th>
                    <th>Matière</th>
                    <th>Prix / h</th>
                    <th>Heures</th>
                    <th>Total paiement</th>
                    <th>Absences</th>
                    <th style="width:110px">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($profs as $p): ?>
                    <?php $total = $p['prix_heure'] * $p['total_heures']; ?>
                    <tr>
                        <td><strong><?= e($p['prenom']) ?> <?= e($p['nom']) ?></strong></td>
                        <td>
                            <?php if ($p['email']): ?><div><i class="fas fa-envelope" style="color:var(--muted)"></i> <?= e($p['email']) ?></div><?php endif; ?>
                            <?php if ($p['telephone']): ?><div><i class="fas fa-phone" style="color:var(--muted)"></i> <?= e($p['telephone']) ?></div><?php endif; ?>
                            <?php if (!$p['email'] && !$p['telephone']): ?>—<?php endif; ?>
                        </td>
                        <td><span class="badge badge-blue"><?= e($p['matiere'] ?: '—') ?></span></td>
                        <td><?= number_format((float)$p['prix_heure'], 2) ?> DA</td>
                        <td>
                            <?= e($p['total_heures']) ?> h
                            <?php if ((float)$p['heures_edt'] != (float)$p['total_heures']): ?>
                                <span class="badge badge-gray" title="Selon l'emploi du temps">EDT : <?= rtrim(rtrim(number_format((float)$p['heures_edt'], 2, '.', ' '), '0'), '.') ?> h</span>
                            <?php endif; ?>
                        </td>
                        <td><strong><?= number_format($total, 2) ?> DA</strong></td>
                        <td>
                            <a href="absences.php?type=profs&prof=<?= $p['id'] ?>" style="color:inherit">
                                <span class="badge <?= $p['nb_abs'] > 0 ? 'badge-red' : 'badge-green' ?>"><?= $p['nb_abs'] ?> jour(s)</span>
                            </a>
                        </td>
                        <td>
                            <a href="?edit=<?= $p['id'] ?>" class="btn btn-sm btn-primary"><i class="fas fa-edit"></i></a>
                            <a href="?delete=<?= $p['id'] ?>" class="btn btn-sm btn-danger"
                               onclick="return confirm('Supprimer ce professeur ?');"><i class="fas fa-trash"></i></a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
