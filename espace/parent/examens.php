<?php
require_once __DIR__ . '/../includes/parent.php';

$page_title = 'Examens';
$enfants = parent_enfants();
$enfant = parent_enfant_selectionne($enfants);

$examens = [];
if ($enfant && $enfant['classe_id']) {
    $examens = q('SELECT ex.*, m.nom AS matiere
                  FROM emploi_examens ex
                  LEFT JOIN matieres m ON m.id = ex.matiere_id
                  WHERE ex.classe_id = ?
                  ORDER BY ex.date_examen ASC, ex.heure_debut ASC', [$enfant['classe_id']])->fetchAll();
}

include __DIR__ . '/../includes/header_parent.php';
?>

<div class="section-title"><i class="fas fa-file-signature"></i> Examens</div>
<p class="section-sub">Calendrier des examens de la classe de votre enfant.</p>

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
    <div class="card">
        <div class="card-head">
            <h3><i class="fas fa-list"></i> Calendrier — <span class="badge badge-blue"><?= e($enfant['classe'] ?: '—') ?></span></h3>
            <span class="badge badge-green"><?= count($examens) ?> examen(s)</span>
        </div>
        <div class="table-wrap">
            <table class="data">
                <thead><tr><th>Date</th><th>Heure</th><th>Matière</th><th>Salle</th></tr></thead>
                <tbody>
                    <?php if (!$examens): ?>
                        <tr><td colspan="4"><div class="empty-state"><i class="fas fa-calendar-check"></i><p>Aucun examen planifié.</p></div></td></tr>
                    <?php endif; ?>
                    <?php foreach ($examens as $ex): ?>
                        <tr>
                            <td><?= e(date('d/m/Y', strtotime($ex['date_examen']))) ?></td>
                            <td><?= e(substr($ex['heure_debut'], 0, 5)) ?> – <?= e(substr($ex['heure_fin'], 0, 5)) ?></td>
                            <td><strong><?= e($ex['matiere'] ?: '—') ?></strong></td>
                            <td><?= e($ex['salle'] ?: '—') ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
<?php endif; ?>

<?php include __DIR__ . '/../includes/footer_parent.php'; ?>
