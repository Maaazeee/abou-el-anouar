<?php
require_once __DIR__ . '/../includes/parent.php';
require_once __DIR__ . '/../includes/pdf.php';

$page_title = 'Absences';
$enfants = parent_enfants();
$enfant = parent_enfant_selectionne($enfants);

$absences = [];
if ($enfant) {
    $absences = q('SELECT a.* FROM absences_eleves a WHERE a.eleve_id = ?
                   ORDER BY a.date_absence DESC, a.id DESC', [$enfant['id']])->fetchAll();
}
$nb_total = count($absences);
$nb_just = count(array_filter($absences, fn($a) => (bool)$a['justifiee']));
$nb_non = $nb_total - $nb_just;

if (isset($_GET['export']) && $enfant) {
    $rows = [];
    foreach ($absences as $a) {
        $rows[] = [
            'date' => $a['date_absence'],
            'motif' => $a['motif'] ?? '',
            'etat' => $a['justifiee'] ? 'Justifiée' : 'Non justifiée',
        ];
    }
    $fname = 'absences_' . slugify($enfant['prenom'] . '_' . $enfant['nom']);
    if ($_GET['export'] === 'csv') {
        export_csv($fname . '.csv', ['date', 'motif', 'etat'], $rows);
    } else {
        pdf_export('Absences de ' . $enfant['prenom'] . ' ' . $enfant['nom'], ['Date', 'Motif', 'État'], $rows, $fname);
    }
}

include __DIR__ . '/../includes/header_parent.php';
?>

<div class="section-title"><i class="fas fa-user-times"></i> Absences de l'élève</div>
<p class="section-sub">Suivi des absences signalées par l'établissement.</p>

<div class="filters">
    <form method="GET" style="display:flex; gap:12px; flex-wrap:wrap; align-items:flex-end; width:100%;">
        <div class="form-group" style="flex:1; min-width:180px;">
            <label>Enfant</label>
            <select name="enfant" onchange="this.form.submit()">
                <?php foreach ($enfants as $e): ?>
                    <option value="<?= $e['id'] ?>" <?= $enfant && $e['id'] == $enfant['id'] ? 'selected' : '' ?>><?= e($e['prenom']) ?> <?= e($e['nom']) ?> (<?= e($e['classe'] ?: '—') ?>)</option>
                <?php endforeach; ?>
            </select>
        </div>
    </form>
</div>

<?php if (!$enfant): ?>
    <div class="card"><div class="card-body"><div class="empty-state"><i class="fas fa-user-friends"></i><p>Aucun enfant lié à ce compte.</p></div></div></div>
<?php else: ?>
    <?php $export_base = 'absences.php?enfant=' . $enfant['id']; $no_import = true; include __DIR__ . '/../includes/export_ui.php'; ?>

    <div class="cards-grid">
        <div class="stat-card">
            <div class="stat-ic red"><i class="fas fa-calendar-times"></i></div>
            <div><div class="stat-num"><?= $nb_total ?></div><div class="stat-label">Total absences</div></div>
        </div>
        <div class="stat-card">
            <div class="stat-ic green"><i class="fas fa-check-circle"></i></div>
            <div><div class="stat-num"><?= $nb_just ?></div><div class="stat-label">Justifiées</div></div>
        </div>
        <div class="stat-card">
            <div class="stat-ic gold"><i class="fas fa-exclamation-circle"></i></div>
            <div><div class="stat-num"><?= $nb_non ?></div><div class="stat-label">Non justifiées</div></div>
        </div>
    </div>

    <div class="card">
        <div class="card-head">
            <h3><i class="fas fa-list"></i> Journal des absences — <span class="badge badge-blue"><?= e($enfant['prenom']) ?> <?= e($enfant['nom']) ?></span></h3>
        </div>
        <div class="table-wrap">
            <table class="data">
                <thead><tr><th>Date</th><th>Motif</th><th>État</th></tr></thead>
                <tbody>
                    <?php if (!$absences): ?>
                        <tr><td colspan="3"><div class="empty-state"><i class="fas fa-user-check"></i><p>Aucune absence enregistrée.</p></div></td></tr>
                    <?php endif; ?>
                    <?php foreach ($absences as $a): ?>
                        <tr>
                            <td><?= e(date('d/m/Y', strtotime($a['date_absence']))) ?></td>
                            <td><?= e($a['motif'] ?: '—') ?></td>
                            <td><?= $a['justifiee'] ? '<span class="badge badge-green">Justifiée</span>' : '<span class="badge badge-red">Non justifiée</span>' ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
<?php endif; ?>

<?php include __DIR__ . '/../includes/footer_parent.php'; ?>
