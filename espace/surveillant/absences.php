<?php
require_once __DIR__ . '/../includes/auth.php';
require_role('surveillant');
require_once __DIR__ . '/../includes/pdf.php';

$page_title = 'Absences';
$type = $_GET['type'] ?? 'eleves';
$professeurs = q('SELECT id, nom, prenom FROM professeurs ORDER BY nom')->fetchAll();
$eleves = q('SELECT e.id, e.nom, e.prenom, c.nom AS classe FROM eleves e LEFT JOIN classes c ON c.id = e.classe_id ORDER BY e.nom')->fetchAll();

// ---- AJOUT / MODIFICATION ----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['date_absence'])) {
    $id = (int)($_POST['id'] ?? 0);
    $date_absence = $_POST['date_absence'];
    $motif = trim($_POST['motif'] ?? '');
    $justifiee = isset($_POST['justifiee']) ? 1 : 0;

    if ($type === 'profs') {
        $pid = (int)($_POST['professeur_id'] ?? 0);
        if ($pid && $date_absence) {
            if ($id > 0) {
                q('UPDATE absences_profs SET professeur_id=?, date_absence=?, motif=?, justifiee=? WHERE id=?',
                  [$pid, $date_absence, $motif ?: null, $justifiee, $id]);
                set_flash('success', 'Absence professeur modifiée.');
            } else {
                q('INSERT INTO absences_profs (professeur_id, date_absence, motif, justifiee) VALUES (?, ?, ?, ?)',
                  [$pid, $date_absence, $motif ?: null, $justifiee]);
                set_flash('success', 'Absence professeur enregistrée.');
            }
        } else { set_flash('error', 'Veuillez sélectionner le professeur et la date.'); }
    } else {
        $eid = (int)($_POST['eleve_id'] ?? 0);
        if ($eid && $date_absence) {
            if ($id > 0) {
                q('UPDATE absences_eleves SET eleve_id=?, date_absence=?, motif=?, justifiee=? WHERE id=?',
                  [$eid, $date_absence, $motif ?: null, $justifiee, $id]);
                set_flash('success', 'Absence élève modifiée.');
            } else {
                q('INSERT INTO absences_eleves (eleve_id, date_absence, motif, justifiee) VALUES (?, ?, ?, ?)',
                  [$eid, $date_absence, $motif ?: null, $justifiee]);
                set_flash('success', 'Absence élève enregistrée.');
            }
        } else { set_flash('error', 'Veuillez sélectionner l\'élève et la date.'); }
    }
    header('Location: absences.php?type=' . $type);
    exit;
}

// ---- SUPPRESSION ----
if (isset($_GET['delete'])) {
    $table = $type === 'profs' ? 'absences_profs' : 'absences_eleves';
    q("DELETE FROM $table WHERE id = ?", [(int)$_GET['delete']]);
    set_flash('success', 'Absence supprimée.');
    header('Location: absences.php?type=' . $type);
    exit;
}

// ---- EXPORT ----
if (isset($_GET['export'])) {
    if ($type === 'profs') {
        $data = q("SELECT p.prenom, p.nom, a.date_absence, a.motif, a.justifiee, m.nom AS matiere
                   FROM absences_profs a JOIN professeurs p ON p.id = a.professeur_id
                   LEFT JOIN matieres m ON m.id = p.matiere_id ORDER BY a.date_absence DESC")->fetchAll();
    } else {
        $data = q("SELECT e.prenom, e.nom, c.nom AS classe, a.date_absence, a.motif, a.justifiee
                   FROM absences_eleves a JOIN eleves e ON e.id = a.eleve_id
                   LEFT JOIN classes c ON c.id = e.classe_id ORDER BY a.date_absence DESC")->fetchAll();
    }
    $rows = [];
    foreach ($data as $a) {
        $rows[] = [
            'personne' => $a['prenom'] . ' ' . $a['nom'],
            'classe_matiere' => $a['matiere'] ?? $a['classe'] ?? '',
            'date_absence' => $a['date_absence'],
            'motif' => $a['motif'] ?? '',
            'etat' => $a['justifiee'] ? 'Justifiée' : 'Non justifiée',
        ];
    }
    if ($_GET['export'] === 'csv') {
        export_csv('absences_' . $type . '.csv', ['personne', 'classe_matiere', 'date_absence', 'motif', 'etat'], $rows);
    } else {
        pdf_export('Absences des ' . ($type === 'profs' ? 'professeurs' : 'élèves'), ['Personne', 'Classe/Matière', 'Date', 'Motif', 'État'], $rows, 'absences_' . $type);
    }
}

// ---- IMPORT ----
if (isset($_POST['do_import']) && isset($_FILES['import_csv']) && $_FILES['import_csv']['error'] === 0) {
    $lines = parse_csv($_FILES['import_csv']['tmp_name']);
    $count = 0;
    $errors = 0;
    foreach ($lines as $r) {
        $date_absence = trim($r['date_absence'] ?? '');
        if (!$date_absence) { $errors++; continue; }
        $justifiee = (stripos(($r['etat'] ?? ''), 'justif') !== false) ? 1 : 0;
        $motif = trim($r['motif'] ?? '') ?: null;
        if ($type === 'profs') {
            $pid = lookup_prof_id($r['personne'] ?? '');
            if (!$pid) { $errors++; continue; }
            q('INSERT INTO absences_profs (professeur_id, date_absence, motif, justifiee) VALUES (?, ?, ?, ?)',
              [$pid, $date_absence, $motif, $justifiee]);
        } else {
            $eid = lookup_id('eleves', 'nom', $r['personne'] ?? '');
            if (!$eid) {
                // tenter "Prénom Nom"
                $parts = preg_split('/\s+/', trim($r['personne'] ?? ''));
                $eid = q('SELECT id FROM eleves WHERE prenom=? AND nom=? LIMIT 1', [implode(' ', array_slice($parts, 0, -1)), end($parts)])->fetch()['id'] ?? 0;
            }
            if (!$eid) { $errors++; continue; }
            q('INSERT INTO absences_eleves (eleve_id, date_absence, motif, justifiee) VALUES (?, ?, ?, ?)',
              [$eid, $date_absence, $motif, $justifiee]);
        }
        $count++;
    }
    set_flash('success', "$count absence(s) importée(s)." . ($errors ? " $errors ligne(s) ignorée(s)." : ''));
    header('Location: absences.php?type=' . $type);
    exit;
}

// ---- LISTE ----
if ($type === 'profs') {
    $prof_filter = (int)($_GET['prof'] ?? 0);
    $rows = q("SELECT a.*, p.nom, p.prenom, m.nom AS matiere
               FROM absences_profs a
               JOIN professeurs p ON p.id = a.professeur_id
               LEFT JOIN matieres m ON m.id = p.matiere_id
               WHERE (? = 0 OR a.professeur_id = ?)
               ORDER BY a.date_absence DESC",
              [$prof_filter, $prof_filter])->fetchAll();
} else {
    $eleve_filter = (int)($_GET['eleve'] ?? 0);
    $rows = q("SELECT a.*, e.nom, e.prenom, c.nom AS classe
               FROM absences_eleves a
               JOIN eleves e ON e.id = a.eleve_id
               LEFT JOIN classes c ON c.id = e.classe_id
               WHERE (? = 0 OR a.eleve_id = ?)
               ORDER BY a.date_absence DESC",
              [$eleve_filter, $eleve_filter])->fetchAll();
}

// Statistiques
$nb_total = count($rows);
$nb_j = count(array_filter($rows, fn($r) => $r['justifiee']));
$nb_nj = $nb_total - $nb_j;

$export_base = 'absences.php?type=' . $type;
$import_columns = $type === 'profs'
    ? 'personne;date_absence;motif;etat  (etat = Justifiée/Non justifiée, personne = Prénom Nom)'
    : 'personne;date_absence;motif;etat  (personne = Prénom Nom)';

include __DIR__ . '/../includes/header.php';
?>

<div class="section-title"><i class="fas fa-user-times"></i> Gestion des absences</div>
<p class="section-sub">Absences des professeurs et des élèves, gérées séparément.</p>

<?php include __DIR__ . '/../includes/export_ui.php'; ?>

<div class="chip-row" style="margin-bottom:22px;">
    <a href="?type=profs" class="chip <?= $type === 'profs' ? 'active' : '' ?>"><i class="fas fa-chalkboard-teacher"></i> Professeurs (<?= $nb_total ?>)</a>
    <a href="?type=eleves" class="chip <?= $type === 'eleves' ? 'active' : '' ?>"><i class="fas fa-user-graduate"></i> Élèves</a>
</div>

<div class="cards-grid" style="grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));">
    <div class="stat-card"><div class="stat-ic red"><i class="fas fa-calendar-times"></i></div>
        <div><div class="stat-num"><?= $nb_total ?></div><div class="stat-label">Total absences</div></div></div>
    <div class="stat-card"><div class="stat-ic green"><i class="fas fa-check-circle"></i></div>
        <div><div class="stat-num"><?= $nb_j ?></div><div class="stat-label">Justifiées</div></div></div>
    <div class="stat-card"><div class="stat-ic gold"><i class="fas fa-exclamation-circle"></i></div>
        <div><div class="stat-num"><?= $nb_nj ?></div><div class="stat-label">Non justifiées</div></div></div>
</div>

<div class="card">
    <div class="card-head">
        <h3><i class="fas fa-list"></i> <?= $type === 'profs' ? 'Absences des professeurs' : 'Absences des élèves' ?></h3>
        <button class="btn btn-gold btn-sm" data-modal-open="modalAjout"><i class="fas fa-plus"></i> Ajouter</button>
    </div>
    <div class="table-wrap">
        <table class="data">
            <thead>
                <tr>
                    <th><?= $type === 'profs' ? 'Professeur' : 'Élève' ?></th>
                    <?php if ($type === 'eleves'): ?><th>Classe</th><?php endif; ?>
                    <th>Date</th>
                    <th>Motif</th>
                    <th>État</th>
                    <th style="width:70px">Action</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!$rows): ?>
                    <tr><td colspan="6"><div class="empty-state"><i class="fas fa-calendar-check"></i><p>Aucune absence enregistrée.</p></div></td></tr>
                <?php endif; ?>
                <?php foreach ($rows as $a): ?>
                    <tr>
                        <td><strong><?= e($a['prenom']) ?> <?= e($a['nom']) ?></strong></td>
                        <?php if ($type === 'eleves'): ?><td><span class="badge badge-blue"><?= e($a['classe']) ?></span></td><?php endif; ?>
                        <td><?= e($a['date_absence']) ?></td>
                        <td><?= e($a['motif'] ?: '—') ?></td>
                        <td><?= $a['justifiee'] ? '<span class="badge badge-green">Justifiée</span>' : '<span class="badge badge-red">Non justifiée</span>' ?></td>
                        <td style="white-space:nowrap;">
                            <button class="btn btn-sm btn-primary"
                                    onclick="editAbsence(<?= $a['id'] ?>, <?= (int)($a['professeur_id'] ?? 0) ?>, <?= (int)($a['eleve_id'] ?? 0) ?>, '<?= e($a['date_absence']) ?>', '<?= e(addslashes($a['motif'] ?? '')) ?>', <?= $a['justifiee'] ? 'true' : 'false' ?>)">
                                <i class="fas fa-edit"></i>
                            </button>
                            <a href="?delete=<?= $a['id'] ?>&type=<?= $type ?>" class="btn btn-sm btn-danger"
                               onclick="return confirm('Supprimer cette absence ?');"><i class="fas fa-trash"></i></a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- MODAL AJOUT / MODIFICATION -->
<div class="modal-overlay" id="modalAjout">
    <div class="modal">
        <form method="POST">
            <input type="hidden" name="id" id="a_id" value="0">
            <div class="modal-head">
                <h3 id="a_titre"><i class="fas fa-calendar-plus" style="color:var(--gold)"></i> Ajouter une absence (<?= $type === 'profs' ? 'professeur' : 'élève' ?>)</h3>
                <button type="button" class="modal-close" data-modal-close><i class="fas fa-times"></i></button>
            </div>
            <div class="modal-body">
                <div class="form-grid">
                    <?php if ($type === 'profs'): ?>
                        <div class="form-group">
                            <label>Professeur *</label>
                            <select name="professeur_id" id="a_prof" required>
                                <option value="">— Choisir —</option>
                                <?php foreach ($professeurs as $p): ?>
                                    <option value="<?= $p['id'] ?>" <?= ($_GET['prof'] ?? 0) == $p['id'] ? 'selected' : '' ?>><?= e($p['prenom']) ?> <?= e($p['nom']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    <?php else: ?>
                        <div class="form-group">
                            <label>Élève *</label>
                            <select name="eleve_id" id="a_eleve" required>
                                <option value="">— Choisir —</option>
                                <?php foreach ($eleves as $el): ?>
                                    <option value="<?= $el['id'] ?>" <?= ($_GET['eleve'] ?? 0) == $el['id'] ? 'selected' : '' ?>><?= e($el['prenom']) ?> <?= e($el['nom']) ?> (<?= e($el['classe']) ?>)</option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    <?php endif; ?>
                    <div class="form-group">
                        <label>Date *</label>
                        <input type="date" name="date_absence" id="a_date" required value="<?= date('Y-m-d') ?>">
                    </div>
                    <div class="form-group" style="grid-column:1 / -1;">
                        <label>Motif</label>
                        <input type="text" name="motif" id="a_motif" placeholder="Maladie, raison personnelle...">
                    </div>
                    <div class="form-group" style="grid-column:1 / -1;">
                        <label style="display:flex;align-items:center;gap:8px;cursor:pointer;">
                            <input type="checkbox" name="justifiee" id="a_just" value="1" style="width:auto;"> Absence justifiée
                        </label>
                    </div>
                </div>
            </div>
            <div class="modal-foot">
                <button type="button" class="btn btn-danger" data-modal-close>Annuler</button>
                <button type="submit" class="btn btn-gold"><i class="fas fa-check"></i> Enregistrer</button>
            </div>
        </form>
    </div>
</div>

<script>
function editAbsence(id, prof, eleve, date, motif, justifiee) {
    document.getElementById('a_id').value = id;
    var pf = document.getElementById('a_prof');
    var el = document.getElementById('a_eleve');
    if (pf) pf.value = prof;
    if (el) el.value = eleve;
    document.getElementById('a_date').value = date;
    document.getElementById('a_motif').value = motif;
    document.getElementById('a_just').checked = justifiee;
    document.getElementById('a_titre').innerHTML = '<i class="fas fa-edit" style="color:var(--gold)"></i> Modifier l\'absence';
    document.getElementById('modalAjout').classList.add('open');
}
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
