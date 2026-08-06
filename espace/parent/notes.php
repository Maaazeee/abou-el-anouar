<?php
require_once __DIR__ . '/../includes/parent.php';
require_once __DIR__ . '/../includes/pdf.php';
require_once __DIR__ . '/../includes/moyennes.php';

$page_title = 'Notes';
$enfants = parent_enfants();
$enfant = parent_enfant_selectionne($enfants);
$trimestre = (int)($_GET['trimestre'] ?? 1) ?: 1;

$matieres = q('SELECT id, nom FROM matieres ORDER BY id')->fetchAll();

$notes = [];
$moy = [];
$gen = null;
if ($enfant) {
    $notes = q('SELECT n.*, m.nom AS matiere
                FROM notes n JOIN matieres m ON m.id = n.matiere_id
                WHERE n.eleve_id = ? AND n.trimestre = ?
                ORDER BY m.id, n.date_note, n.id', [$enfant['id'], $trimestre])->fetchAll();
    [$moy, $gen] = calcule_moyennes_eleve($enfant['id'], $trimestre);
}

if (isset($_GET['export']) && $enfant) {
    $rows = [];
    foreach ($notes as $n) {
        $rows[] = [
            'matiere' => $n['matiere'],
            'type' => $n['type_note'],
            'note' => number_format((float)$n['note'], 2, ',', ' '),
            'coefficient' => $n['coefficient'],
            'date' => $n['date_note'] ?? '',
        ];
    }
    $fname = 'notes_' . slugify($enfant['prenom'] . '_' . $enfant['nom']) . '_T' . $trimestre;
    if ($_GET['export'] === 'csv') {
        export_csv($fname . '.csv', ['matiere', 'type', 'note', 'coefficient', 'date'], $rows);
    } else {
        pdf_export('Notes de ' . $enfant['prenom'] . ' ' . $enfant['nom'] . " — Trimestre $trimestre",
                   ['Matière', 'Type', 'Note /20', 'Coef.', 'Date'], $rows, $fname);
    }
}

include __DIR__ . '/../includes/header_parent.php';
?>

<div class="section-title"><i class="fas fa-clipboard-check"></i> Notes de l'élève</div>
<p class="section-sub">Évaluations (contrôles et examens) saisies par l'établissement.</p>

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
        <div class="form-group" style="min-width:120px;">
            <label>Trimestre</label>
            <select name="trimestre" onchange="this.form.submit()">
                <option value="1" <?= $trimestre === 1 ? 'selected' : '' ?>>1ᵉʳ</option>
                <option value="2" <?= $trimestre === 2 ? 'selected' : '' ?>>2ᵉ</option>
                <option value="3" <?= $trimestre === 3 ? 'selected' : '' ?>>3ᵉ</option>
            </select>
        </div>
    </form>
</div>

<?php if (!$enfant): ?>
    <div class="card"><div class="card-body"><div class="empty-state"><i class="fas fa-user-friends"></i><p>Aucun enfant lié à ce compte.</p></div></div></div>
<?php else: ?>

<?php if ($notes): $export_base = 'notes.php?enfant=' . $enfant['id'] . '&trimestre=' . $trimestre; $no_import = true; include __DIR__ . '/../includes/export_ui.php'; endif; ?>

<div class="cards-grid">
    <div class="card">
        <div class="card-head">
            <h3><i class="fas fa-list"></i> Évaluations — <span class="badge badge-blue"><?= e($enfant['prenom']) ?> <?= e($enfant['nom']) ?></span> <span class="badge badge-gold">T<?= $trimestre ?></span></h3>
            <span class="badge badge-green"><?= count($notes) ?> note(s)</span>
        </div>
        <div class="table-wrap">
            <table class="data">
                <thead>
                    <tr><th>Matière</th><th>Type</th><th>Note /20</th><th>Coefficient</th><th>Date</th></tr>
                </thead>
                <tbody>
                    <?php if (!$notes): ?>
                        <tr><td colspan="5"><div class="empty-state"><i class="fas fa-clipboard"></i><p>Aucune note pour ce trimestre.</p></div></td></tr>
                    <?php endif; ?>
                    <?php foreach ($notes as $n): ?>
                        <tr>
                            <td><strong><?= e($n['matiere']) ?></strong></td>
                            <td><span class="badge <?= $n['type_note'] === 'examen' ? 'badge-gold' : 'badge-blue' ?>"><?= $n['type_note'] === 'examen' ? 'Examen' : 'Contrôle' ?></span></td>
                            <td><strong style="color:<?= (float)$n['note'] >= 10 ? 'var(--green)' : 'var(--red)' ?>"><?= number_format((float)$n['note'], 2, ',', ' ') ?></strong></td>
                            <td><?= e($n['coefficient']) ?></td>
                            <td><?= e($n['date_note'] ? date('d/m/Y', strtotime($n['date_note'])) : '—') ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div class="card">
        <div class="card-head">
            <h3><i class="fas fa-chart-line" style="color:var(--gold)"></i> Moyennes par matière</h3>
        </div>
        <div class="table-wrap">
            <table class="data">
                <thead><tr><th>Matière</th><th>Moyenne /20</th><th>Mention</th></tr></thead>
                <tbody>
                    <?php if (!$moy): ?>
                        <tr><td colspan="3"><div class="empty-state"><i class="fas fa-chart-line"></i><p>Aucune moyenne.</p></div></td></tr>
                    <?php endif; ?>
                    <?php foreach ($matieres as $m): ?>
                        <?php if (isset($moy[$m['id']]) && $moy[$m['id']] !== null): ?>
                            <tr>
                                <td><?= e($m['nom']) ?></td>
                                <td><strong style="color:<?= $moy[$m['id']] >= 10 ? 'var(--green)' : 'var(--red)' ?>"><?= number_format($moy[$m['id']], 2, ',', ' ') ?></strong></td>
                                <td><span class="badge <?= $moy[$m['id']] >= 10 ? 'badge-green' : 'badge-red' ?>"><?= e(bulletin_mention($moy[$m['id']])) ?></span></td>
                            </tr>
                        <?php endif; ?>
                    <?php endforeach; ?>
                    <tr>
                        <td><strong>Moyenne générale</strong></td>
                        <td><strong style="color:<?= $gen !== null && $gen >= 10 ? 'var(--green)' : 'var(--red)' ?>"><?= $gen !== null ? number_format($gen, 2, ',', ' ') : '—' ?></strong></td>
                        <td><?= $gen !== null ? '<span class="badge ' . ($gen >= 10 ? 'badge-green' : 'badge-red') . '">' . e(bulletin_mention($gen)) . '</span>' : '<span class="badge badge-gray">—</span>' ?></td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php endif; ?>

<?php include __DIR__ . '/../includes/footer_parent.php'; ?>
