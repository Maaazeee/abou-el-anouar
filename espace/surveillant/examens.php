<?php
require_once __DIR__ . '/../includes/auth.php';
require_role('surveillant');
require_once __DIR__ . '/../includes/pdf.php';

$page_title = 'Emploi du temps des examens';
$classes = q('SELECT id, nom FROM classes ORDER BY id')->fetchAll();
$matieres = q('SELECT id, nom FROM matieres ORDER BY nom')->fetchAll();

// ---- EXPORT ----
if (isset($_GET['export'])) {
    $data = q('SELECT e.*, c.nom AS classe, m.nom AS matiere
               FROM emploi_examens e
               LEFT JOIN classes c ON c.id = e.classe_id
               LEFT JOIN matieres m ON m.id = e.matiere_id
               ORDER BY e.date_examen, e.heure_debut')->fetchAll();
    $rows = [];
    foreach ($data as $ex) {
        $rows[] = [
            'classe' => $ex['classe'] ?? '',
            'matiere' => $ex['matiere'] ?? '',
            'date_examen' => $ex['date_examen'],
            'heure_debut' => substr($ex['heure_debut'], 0, 5),
            'heure_fin' => substr($ex['heure_fin'], 0, 5),
            'salle' => $ex['salle'] ?? '',
        ];
    }
    if ($_GET['export'] === 'csv') {
        export_csv('emploi_examens.csv', ['classe', 'matiere', 'date_examen', 'heure_debut', 'heure_fin', 'salle'], $rows);
    } else {
        pdf_export('Emploi du temps des examens', ['Classe', 'Matière', 'Date', 'Début', 'Fin', 'Salle'], $rows, 'emploi_examens');
    }
}

// ---- IMPORT ----
if (isset($_POST['do_import']) && isset($_FILES['import_csv']) && $_FILES['import_csv']['error'] === 0) {
    $lines = parse_csv($_FILES['import_csv']['tmp_name']);
    $count = 0;
    $errors = 0;
    foreach ($lines as $r) {
        $classe_id = lookup_id('classes', 'nom', $r['classe'] ?? '');
        $date_examen = trim($r['date_examen'] ?? '');
        $heure_debut = trim($r['heure_debut'] ?? '');
        $heure_fin = trim($r['heure_fin'] ?? '');
        if (!$classe_id || !$date_examen || !$heure_debut || !$heure_fin) { $errors++; continue; }
        $matiere_id = lookup_id('matieres', 'nom', $r['matiere'] ?? '');
        q('INSERT INTO emploi_examens (classe_id, matiere_id, date_examen, heure_debut, heure_fin, salle)
           VALUES (?, ?, ?, ?, ?, ?)',
          [$classe_id, $matiere_id, $date_examen, $heure_debut, $heure_fin, trim($r['salle'] ?? '') ?: null]);
        $count++;
    }
    set_flash('success', "$count examen(s) importé(s)." . ($errors ? " $errors ligne(s) ignorée(s)." : ''));
    header('Location: examens.php');
    exit;
}

// ---- AJOUT ----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['date_examen'])) {
    $classe_id = (int)($_POST['classe_id'] ?? 0);
    $matiere_id = (int)($_POST['matiere_id'] ?? 0);
    $date_examen = $_POST['date_examen'];
    $heure_debut = $_POST['heure_debut'] ?? '';
    $heure_fin = $_POST['heure_fin'] ?? '';
    $salle = trim($_POST['salle'] ?? '');

    if ($classe_id && $date_examen && $heure_debut && $heure_fin) {
        q('INSERT INTO emploi_examens (classe_id, matiere_id, date_examen, heure_debut, heure_fin, salle)
           VALUES (?, ?, ?, ?, ?, ?)',
          [$classe_id, $matiere_id ?: null, $date_examen, $heure_debut, $heure_fin, $salle ?: null]);
        set_flash('success', 'Examen planifié.');
    } else {
        set_flash('error', 'Veuillez remplir les champs obligatoires.');
    }
    header('Location: examens.php');
    exit;
}

// ---- MODIFICATION ----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update'])) {
    q('UPDATE emploi_examens SET classe_id=?, matiere_id=?, date_examen=?, heure_debut=?, heure_fin=?, salle=? WHERE id=?',
      [(int)$_POST['classe_id'], (int)($_POST['matiere_id'] ?? 0) ?: null,
       $_POST['date_examen'], $_POST['heure_debut'], $_POST['heure_fin'],
       trim($_POST['salle'] ?? '') ?: null, (int)$_POST['update']]);
    set_flash('success', 'Examen modifié.');
    header('Location: examens.php');
    exit;
}

// ---- SUPPRESSION ----
if (isset($_GET['delete'])) {
    q('DELETE FROM emploi_examens WHERE id = ?', [(int)$_GET['delete']]);
    set_flash('success', 'Examen supprimé.');
    header('Location: examens.php');
    exit;
}

// ---- LISTE ----
$examens = q('SELECT e.*, c.nom AS classe, m.nom AS matiere
              FROM emploi_examens e
              LEFT JOIN classes c ON c.id = e.classe_id
              LEFT JOIN matieres m ON m.id = e.matiere_id
              ORDER BY e.date_examen, e.heure_debut')->fetchAll();

// Regroupement par classe
$par_classe = [];
foreach ($examens as $ex) {
    $par_classe[$ex['classe']][] = $ex;
}

include __DIR__ . '/../includes/header.php';
?>

<div class="section-title"><i class="fas fa-file-signature"></i> Emploi du temps des examens</div>
<p class="section-sub">Planifiez les examens et contrôles par classe, date, heure et salle.</p>

<?php
$export_base = 'examens.php';
$import_columns = 'classe;matiere;date_examen;heure_debut;heure_fin;salle';
include __DIR__ . '/../includes/export_ui.php';
?>

<div class="card">
    <div class="card-head">
        <h3><i class="fas fa-plus-circle" style="color:var(--gold)"></i> Planifier un examen</h3>
    </div>
    <div class="card-body">
        <form method="POST" class="form-grid">
            <div class="form-group">
                <label>Classe *</label>
                <select name="classe_id" required>
                    <?php foreach ($classes as $c): ?>
                        <option value="<?= $c['id'] ?>"><?= e($c['nom']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label>Matière</label>
                <select name="matiere_id">
                    <option value="">— Aucune —</option>
                    <?php foreach ($matieres as $m): ?>
                        <option value="<?= $m['id'] ?>"><?= e($m['nom']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label>Date *</label>
                <input type="date" name="date_examen" required>
            </div>
            <div class="form-group">
                <label>Heure début *</label>
                <input type="time" name="heure_debut" required>
            </div>
            <div class="form-group">
                <label>Heure fin *</label>
                <input type="time" name="heure_fin" required>
            </div>
            <div class="form-group">
                <label>Salle</label>
                <input type="text" name="salle" placeholder="Salle 1">
            </div>
            <div class="form-actions" style="grid-column:1 / -1;">
                <button type="submit" class="btn btn-gold"><i class="fas fa-calendar-plus"></i> Planifier</button>
            </div>
        </form>
    </div>
</div>

<?php foreach ($par_classe as $classe => $list): ?>
<div class="card">
    <div class="card-head">
        <h3><i class="fas fa-school" style="color:var(--blue)"></i> Classe <?= e($classe) ?></h3>
        <span class="badge badge-blue"><?= count($list) ?> examen(s)</span>
    </div>
    <div class="table-wrap">
        <table class="data">
            <thead>
                <tr>
                    <th>Date</th><th>Heure</th><th>Matière</th><th>Salle</th><th style="width:110px">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($list as $ex): ?>
                    <tr>
                        <td><strong><?= e($ex['date_examen']) ?></strong></td>
                        <td><?= e(substr($ex['heure_debut'], 0, 5)) ?> – <?= e(substr($ex['heure_fin'], 0, 5)) ?></td>
                        <td><span class="badge badge-gold"><?= e($ex['matiere'] ?: '—') ?></span></td>
                        <td><?= e($ex['salle'] ?: '—') ?></td>
                        <td>
                            <button class="btn btn-sm btn-primary" data-modal-open="modalEdit<?= $ex['id'] ?>"><i class="fas fa-edit"></i></button>
                            <a href="?delete=<?= $ex['id'] ?>" class="btn btn-sm btn-danger"
                               onclick="return confirm('Supprimer cet examen ?');"><i class="fas fa-trash"></i></a>
                        </td>
                    </tr>

<!-- MODAL ÉDITION -->
<div class="modal-overlay" id="modalEdit<?= $ex['id'] ?>">
    <div class="modal">
        <form method="POST">
            <input type="hidden" name="update" value="<?= $ex['id'] ?>">
            <div class="modal-head">
                <h3><i class="fas fa-edit" style="color:var(--gold)"></i> Modifier l'examen</h3>
                <button type="button" class="modal-close" data-modal-close><i class="fas fa-times"></i></button>
            </div>
            <div class="modal-body">
                <div class="form-grid">
                    <div class="form-group">
                        <label>Classe *</label>
                        <select name="classe_id" required>
                            <?php foreach ($classes as $c): ?>
                                <option value="<?= $c['id'] ?>" <?= $ex['classe_id'] == $c['id'] ? 'selected' : '' ?>><?= e($c['nom']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Matière</label>
                        <select name="matiere_id">
                            <option value="">— Aucune —</option>
                            <?php foreach ($matieres as $m): ?>
                                <option value="<?= $m['id'] ?>" <?= $ex['matiere_id'] == $m['id'] ? 'selected' : '' ?>><?= e($m['nom']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Date *</label>
                        <input type="date" name="date_examen" value="<?= e($ex['date_examen']) ?>" required>
                    </div>
                    <div class="form-group">
                        <label>Heure début *</label>
                        <input type="time" name="heure_debut" value="<?= e($ex['heure_debut']) ?>" required>
                    </div>
                    <div class="form-group">
                        <label>Heure fin *</label>
                        <input type="time" name="heure_fin" value="<?= e($ex['heure_fin']) ?>" required>
                    </div>
                    <div class="form-group">
                        <label>Salle</label>
                        <input type="text" name="salle" value="<?= e($ex['salle']) ?>">
                    </div>
                </div>
            </div>
            <div class="modal-foot">
                <button type="button" class="btn btn-danger" data-modal-close>Annuler</button>
                <button type="submit" class="btn btn-gold"><i class="fas fa-check"></i> Mettre à jour</button>
            </div>
        </form>
    </div>
</div>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endforeach; ?>

<?php if (!$examens): ?>
    <div class="card">
        <div class="card-body">
            <div class="empty-state"><i class="fas fa-calendar-times"></i><p>Aucun examen planifié pour le moment.</p></div>
        </div>
    </div>
<?php endif; ?>

<?php include __DIR__ . '/../includes/footer.php'; ?>
