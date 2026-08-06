<?php
require_once __DIR__ . '/../includes/auth.php';
require_role('surveillant');
require_once __DIR__ . '/../includes/pdf.php';

$page_title = 'Notes';
$classes = q('SELECT id, nom FROM classes ORDER BY id')->fetchAll();
$matieres = q('SELECT id, nom FROM matieres ORDER BY nom')->fetchAll();

// ---- EXPORT ----
if (isset($_GET['export'])) {
    $data = q('SELECT n.*, e.nom, e.prenom, c.nom AS classe, m.nom AS matiere
               FROM notes n
               JOIN eleves e ON e.id = n.eleve_id
               LEFT JOIN classes c ON c.id = e.classe_id
               JOIN matieres m ON m.id = n.matiere_id
               ORDER BY c.id, e.nom')->fetchAll();
    $rows = [];
    foreach ($data as $h) {
        $rows[] = [
            'eleve' => $h['prenom'] . ' ' . $h['nom'],
            'classe' => $h['classe'] ?? '',
            'matiere' => $h['matiere'],
            'type_note' => $h['type_note'],
            'note' => $h['note'],
            'coefficient' => $h['coefficient'],
            'trimestre' => $h['trimestre'],
            'date_note' => $h['date_note'] ?? '',
        ];
    }
    if ($_GET['export'] === 'csv') {
        export_csv('notes.csv', ['eleve', 'classe', 'matiere', 'type_note', 'note', 'coefficient', 'trimestre', 'date_note'], $rows);
    } else {
        pdf_export('Notes des élèves', ['Élève', 'Classe', 'Matière', 'Type', 'Note', 'Coef.', 'Trim.', 'Date'], $rows, 'notes');
    }
}

// ---- IMPORT ----
if (isset($_POST['do_import']) && isset($_FILES['import_csv']) && $_FILES['import_csv']['error'] === 0) {
    $lines = parse_csv($_FILES['import_csv']['tmp_name']);
    $count = 0;
    $errors = 0;
    foreach ($lines as $r) {
        $type_note = (strtolower(trim($r['type_note'] ?? '')) === 'examen') ? 'examen' : 'controle';
        $note = (float)str_replace(',', '.', $r['note'] ?? '');
        if ($note === 0.0 && trim($r['note'] ?? '') === '') { $errors++; continue; }
        $matiere_id = lookup_id('matieres', 'nom', $r['matiere'] ?? '');
        if (!$matiere_id) { $errors++; continue; }
        $parts = preg_split('/\s+/', trim($r['eleve'] ?? ''));
        $eleve_id = 0;
        if (count($parts) >= 2) {
            $row = q('SELECT id FROM eleves WHERE prenom=? AND nom=? LIMIT 1',
                     [implode(' ', array_slice($parts, 0, -1)), end($parts)])->fetch();
            $eleve_id = $row ? $row['id'] : 0;
        }
        if (!$eleve_id) { $errors++; continue; }
        $trimestre = (int)($r['trimestre'] ?? 1) ?: 1;
        q('INSERT INTO notes (eleve_id, matiere_id, type_note, note, coefficient, date_note, trimestre)
           VALUES (?, ?, ?, ?, ?, ?, ?)',
          [$eleve_id, $matiere_id, $type_note, max(0, min(20, $note)),
           (float)str_replace(',', '.', $r['coefficient'] ?? 1) ?: 1,
           trim($r['date_note'] ?? '') ?: date('Y-m-d'),
           $trimestre]);
        $count++;
    }
    set_flash('success', "$count note(s) importée(s)." . ($errors ? " $errors ligne(s) ignorée(s)." : ''));
    header('Location: notes.php');
    exit;
}

// ---- SAISIE DE NOTES ----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_notes'])) {
    $classe_id = (int)$_POST['classe_id'];
    $matiere_id = (int)$_POST['matiere_id'];
    $type_note = $_POST['type_note'] === 'examen' ? 'examen' : 'controle';
    $trimestre = (int)($_POST['trimestre'] ?? 1);
    $date_note = $_POST['date_note'] ?? date('Y-m-d');

    if ($classe_id && $matiere_id) {
        $eleves = q('SELECT id FROM eleves WHERE classe_id = ?', [$classe_id])->fetchAll();
        $saved = 0;
        foreach ($eleves as $el) {
            $val = $_POST['note_' . $el['id']] ?? '';
            if ($val !== '' && is_numeric($val)) {
                $note = max(0, min(20, (float)$val));
                $coef = (float)($_POST['coef_' . $el['id']] ?? 1) ?: 1;
                // Vérifier si une note existe déjà pour cette évaluation
                $existing = q('SELECT id FROM notes WHERE eleve_id=? AND matiere_id=? AND type_note=? AND trimestre=?',
                              [$el['id'], $matiere_id, $type_note, $trimestre])->fetch();
                if ($existing) {
                    q('UPDATE notes SET note=?, coefficient=?, date_note=? WHERE id=?',
                      [$note, $coef, $date_note, $existing['id']]);
                } else {
                    q('INSERT INTO notes (eleve_id, matiere_id, type_note, note, coefficient, date_note, trimestre)
                       VALUES (?, ?, ?, ?, ?, ?, ?)',
                      [$el['id'], $matiere_id, $type_note, $note, $coef, $date_note, $trimestre]);
                }
                $saved++;
            }
        }
        set_flash('success', "$saved note(s) enregistrée(s).");
    } else {
        set_flash('error', 'Veuillez choisir la classe et la matière.');
    }
    header('Location: notes.php?classe=' . ($classe_id ?? 0) . '&matiere=' . ($matiere_id ?? 0) . '&type=' . $type_note);
    exit;
}

// ---- SUPPRESSION NOTE ----
if (isset($_GET['delete'])) {
    q('DELETE FROM notes WHERE id = ?', [(int)$_GET['delete']]);
    set_flash('success', 'Note supprimée.');
    header('Location: notes.php');
    exit;
}

// ---- SÉLECTION COURANTE ----
$classe_filter = (int)($_GET['classe'] ?? ($classes[0]['id'] ?? 0));
$matiere_filter = (int)($_GET['matiere'] ?? 0);
$type_filter = $_GET['type'] ?? 'controle';
$trimestre_filter = (int)($_GET['trimestre'] ?? 1);

// Élèves de la classe + notes existantes
$eleves_classe = q('SELECT id, nom, prenom FROM eleves WHERE classe_id = ? ORDER BY nom', [$classe_filter])->fetchAll();
$notes_map = [];
if ($matiere_filter) {
    foreach (q('SELECT eleve_id, note, coefficient FROM notes WHERE matiere_id=? AND type_note=? AND trimestre=?',
               [$matiere_filter, $type_filter, $trimestre_filter])->fetchAll() as $n) {
        $notes_map[$n['eleve_id']] = $n;
    }
}

// Historique global
$historique = q('SELECT n.*, e.nom, e.prenom, c.nom AS classe, m.nom AS matiere
                 FROM notes n
                 JOIN eleves e ON e.id = n.eleve_id
                 LEFT JOIN classes c ON c.id = e.classe_id
                 JOIN matieres m ON m.id = n.matiere_id
                 ORDER BY n.date_note DESC, e.nom LIMIT 30')->fetchAll();

include __DIR__ . '/../includes/header.php';
?>

<div class="section-title"><i class="fas fa-clipboard-check"></i> Gestion des notes</div>
<p class="section-sub">Saisie des contrôles et examens par classe et par matière.</p>

<?php
$export_base = 'notes.php';
$import_columns = 'eleve;matiere;type_note;note;coefficient;trimestre;date_note';
include __DIR__ . '/../includes/export_ui.php';
?>

<div class="filters">
    <form method="GET" style="display:flex; gap:12px; flex-wrap:wrap; align-items:flex-end; width:100%;">
        <div class="form-group" style="flex:1; min-width:150px;">
            <label>Classe</label>
            <select name="classe" onchange="this.form.submit()">
                <?php foreach ($classes as $c): ?>
                    <option value="<?= $c['id'] ?>" <?= $c['id'] == $classe_filter ? 'selected' : '' ?>><?= e($c['nom']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group" style="flex:1; min-width:170px;">
            <label>Matière</label>
            <select name="matiere" onchange="this.form.submit()">
                <option value="0">— Choisir —</option>
                <?php foreach ($matieres as $m): ?>
                    <option value="<?= $m['id'] ?>" <?= $m['id'] == $matiere_filter ? 'selected' : '' ?>><?= e($m['nom']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group" style="min-width:140px;">
            <label>Type</label>
            <select name="type">
                <option value="controle" <?= $type_filter === 'controle' ? 'selected' : '' ?>>Contrôle</option>
                <option value="examen" <?= $type_filter === 'examen' ? 'selected' : '' ?>>Examen</option>
            </select>
        </div>
        <div class="form-group" style="min-width:110px;">
            <label>Trimestre</label>
            <select name="trimestre">
                <option value="1" <?= $trimestre_filter === 1 ? 'selected' : '' ?>>T1</option>
                <option value="2" <?= $trimestre_filter === 2 ? 'selected' : '' ?>>T2</option>
                <option value="3" <?= $trimestre_filter === 3 ? 'selected' : '' ?>>T3</option>
            </select>
        </div>
        <button class="btn btn-primary" type="submit"><i class="fas fa-filter"></i> Afficher</button>
    </form>
</div>

<?php if ($matiere_filter): ?>
<div class="card">
    <div class="card-head">
        <h3><i class="fas fa-edit"></i> Saisie des notes —
            <span class="badge badge-blue"><?= e($classes[array_search($classe_filter, array_column($classes, 'id'))]['nom'] ?? '') ?></span>
            <span class="badge badge-gold"><?= $type_filter === 'examen' ? 'Examen' : 'Contrôle' ?></span>
            <span class="badge badge-gray">Trimestre <?= $trimestre_filter ?></span>
        </h3>
    </div>
    <div class="card-body">
        <form method="POST">
            <input type="hidden" name="classe_id" value="<?= $classe_filter ?>">
            <input type="hidden" name="matiere_id" value="<?= $matiere_filter ?>">
            <input type="hidden" name="type_note" value="<?= $type_filter ?>">
            <input type="hidden" name="trimestre" value="<?= $trimestre_filter ?>">
            <div class="form-grid" style="grid-template-columns: 1fr 1fr; margin-bottom:18px;">
                <div class="form-group">
                    <label>Date de l'évaluation</label>
                    <input type="date" name="date_note" value="<?= date('Y-m-d') ?>">
                </div>
            </div>
            <div class="table-wrap">
                <table class="data">
                    <thead>
                        <tr>
                            <th>Élève</th>
                            <th style="width:140px">Note / 20</th>
                            <th style="width:120px">Coefficient</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!$eleves_classe): ?>
                            <tr><td colspan="3"><div class="empty-state"><i class="fas fa-user-graduate"></i><p>Aucun élève dans cette classe.</p></div></td></tr>
                        <?php endif; ?>
                        <?php foreach ($eleves_classe as $el): ?>
                            <?php $n = $notes_map[$el['id']] ?? null; ?>
                            <tr>
                                <td><strong><?= e($el['prenom']) ?> <?= e($el['nom']) ?></strong></td>
                                <td>
                                    <input type="number" step="0.25" min="0" max="20" name="note_<?= $el['id'] ?>"
                                           value="<?= $n ? e($n['note']) : '' ?>" placeholder="—" style="width:100%; padding:8px 10px; border:1.5px solid var(--border); border-radius:8px; font-family:inherit;">
                                </td>
                                <td>
                                    <input type="number" step="0.5" min="0" name="coef_<?= $el['id'] ?>"
                                           value="<?= $n ? e($n['coefficient']) : '1' ?>" style="width:100%; padding:8px 10px; border:1.5px solid var(--border); border-radius:8px; font-family:inherit;">
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <div class="form-actions">
                <button type="submit" name="save_notes" class="btn btn-gold"><i class="fas fa-save"></i> Enregistrer les notes</button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

<div class="card">
    <div class="card-head">
        <h3><i class="fas fa-history"></i> Dernières notes enregistrées</h3>
    </div>
    <div class="table-wrap">
        <table class="data">
            <thead>
                <tr>
                    <th>Élève</th><th>Classe</th><th>Matière</th><th>Type</th><th>Note</th><th>Coef.</th><th>Trimestre</th><th>Date</th><th style="width:70px">Action</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!$historique): ?>
                    <tr><td colspan="9"><div class="empty-state"><i class="fas fa-clipboard"></i><p>Aucune note enregistrée.</p></div></td></tr>
                <?php endif; ?>
                <?php foreach ($historique as $h): ?>
                    <tr>
                        <td><strong><?= e($h['prenom']) ?> <?= e($h['nom']) ?></strong></td>
                        <td><span class="badge badge-blue"><?= e($h['classe']) ?></span></td>
                        <td><?= e($h['matiere']) ?></td>
                        <td><span class="badge <?= $h['type_note'] === 'examen' ? 'badge-gold' : 'badge-gray' ?>"><?= ucfirst(e($h['type_note'])) ?></span></td>
                        <td><strong style="color:<?= (float)$h['note'] >= 10 ? 'var(--green)' : 'var(--red)' ?>"><?= e($h['note']) ?> / 20</strong></td>
                        <td><?= e($h['coefficient']) ?></td>
                        <td>T<?= $h['trimestre'] ?></td>
                        <td><?= e($h['date_note'] ?: '—') ?></td>
                        <td>
                            <a href="?delete=<?= $h['id'] ?>" class="btn btn-sm btn-danger"
                               onclick="return confirm('Supprimer cette note ?');"><i class="fas fa-trash"></i></a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
