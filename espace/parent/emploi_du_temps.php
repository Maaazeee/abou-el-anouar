<?php
require_once __DIR__ . '/../includes/parent.php';

$page_title = 'Emploi du temps';
$enfants = parent_enfants();
$enfant = parent_enfant_selectionne($enfants);
$jours = ['', 'Dimanche', 'Lundi', 'Mardi', 'Mercredi', 'Jeudi', 'Vendredi'];

$slots = [];
$grid = [];
if ($enfant && $enfant['classe_id']) {
    $slots = q('SELECT DISTINCT heure_debut, heure_fin FROM emploi_du_temps WHERE classe_id = ? ORDER BY heure_debut', [$enfant['classe_id']])->fetchAll();
    $seances = q('SELECT e.*, m.nom AS matiere, p.nom AS pnom, p.prenom AS pprenom
                  FROM emploi_du_temps e
                  LEFT JOIN matieres m ON m.id = e.matiere_id
                  LEFT JOIN professeurs p ON p.id = e.professeur_id
                  WHERE e.classe_id = ?
                  ORDER BY e.jour, e.heure_debut', [$enfant['classe_id']])->fetchAll();
    foreach ($seances as $s) {
        $grid[$s['jour']][substr($s['heure_debut'], 0, 5) . '|' . substr($s['heure_fin'], 0, 5)] = $s;
    }
}

include __DIR__ . '/../includes/header_parent.php';
?>

<div class="section-title"><i class="fas fa-calendar-week"></i> Emploi du temps</div>
<p class="section-sub">Semaine algérienne : dimanche → jeudi (vendredi jour de repos).</p>

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
            <h3><i class="fas fa-th"></i> Grille hebdomadaire — <span class="badge badge-blue"><?= e($enfant['classe'] ?: '—') ?></span></h3>
        </div>
        <div class="card-body" style="padding:14px;">
            <?php if (!$slots): ?>
                <div class="empty-state">
                    <i class="fas fa-calendar-minus"></i>
                    <p>L'emploi du temps de la classe <?= e($enfant['classe'] ?: '') ?> n'a pas encore été publié.</p>
                </div>
            <?php else: ?>
                <div class="grid-edt">
                    <table class="grid">
                        <thead>
                            <tr>
                                <th>Horaires</th>
                                <?php for ($j = 1; $j <= 5; $j++): ?>
                                    <th><?= $jours[$j] ?></th>
                                <?php endfor; ?>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($slots as $slot): ?>
                                <?php $debut = substr($slot['heure_debut'], 0, 5); $fin = substr($slot['heure_fin'], 0, 5); ?>
                                <tr>
                                    <td class="slot-col"><?= $debut ?> – <?= $fin ?></td>
                                    <?php for ($j = 1; $j <= 5; $j++): ?>
                                        <?php $s = $grid[$j][$debut . '|' . $fin] ?? null; ?>
                                        <?php if ($s && $s['matiere']): ?>
                                            <td class="filled-cell" style="background:#f0f6ef;">
                                                <div class="cell-content">
                                                    <strong><?= e($s['matiere']) ?></strong>
                                                    <span class="slot-prof"><?= $s['pprenom'] ? e($s['pprenom']) . ' ' . e($s['pnom']) : '—' ?></span>
                                                </div>
                                            </td>
                                        <?php else: ?>
                                            <td class="free-cell"></td>
                                        <?php endif; ?>
                                    <?php endfor; ?>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <p class="section-sub" style="margin:12px 0 0; font-size:0.85rem;"><i class="fas fa-moon"></i> Vendredi : jour de repos (aucun cours).</p>
            <?php endif; ?>
        </div>
    </div>
<?php endif; ?>

<?php include __DIR__ . '/../includes/footer_parent.php'; ?>
