<?php
require_once __DIR__ . '/../includes/parent.php';
require_once __DIR__ . '/../includes/moyennes.php';

$page_title = 'Tableau de bord';
$enfants = parent_enfants();

// Stats par enfant
$stats = [];
foreach ($enfants as $e) {
    $moy_gen = [];
    for ($t = 1; $t <= 3; $t++) {
        [, $gen] = calcule_moyennes_eleve($e['id'], $t);
        $moy_gen[$t] = $gen;
    }
    $stats[$e['id']] = [
        'moyennes' => $moy_gen,
        'absences' => (int)q('SELECT COUNT(*) c FROM absences_eleves WHERE eleve_id = ?', [$e['id']])->fetch()['c'],
        'abs_just' => (int)q('SELECT COUNT(*) c FROM absences_eleves WHERE eleve_id = ? AND justifiee = 1', [$e['id']])->fetch()['c'],
        'notes' => (int)q('SELECT COUNT(*) c FROM notes WHERE eleve_id = ?', [$e['id']])->fetch()['c'],
    ];
}

// Absences récentes (tous les enfants)
$ids = array_column($enfants, 'id');
$abs_recentes = [];
if ($ids) {
    $ph = implode(',', array_fill(0, count($ids), '?'));
    $abs_recentes = q("SELECT e.id AS eleve_id, e.prenom, e.nom, a.date_absence, a.motif, a.justifiee
                       FROM absences_eleves a JOIN eleves e ON e.id = a.eleve_id
                       WHERE a.eleve_id IN ($ph)
                       ORDER BY a.date_absence DESC LIMIT 6", $ids)->fetchAll();
}

// Examens à venir (classes des enfants)
$examens = [];
if ($ids) {
    $cids = array_filter(array_column($enfants, 'classe_id'));
    if ($cids) {
        $ph = implode(',', array_fill(0, count($cids), '?'));
        $examens = q("SELECT ex.date_examen, ex.heure_debut, c.nom AS classe, m.nom AS matiere, ex.salle
                      FROM emploi_examens ex
                      LEFT JOIN classes c ON c.id = ex.classe_id
                      LEFT JOIN matieres m ON m.id = ex.matiere_id
                      WHERE ex.classe_id IN ($ph) AND ex.date_examen >= CURRENT_DATE
                      ORDER BY ex.date_examen ASC, ex.heure_debut ASC LIMIT 6", array_values($cids))->fetchAll();
    }
}

// Mots du carnet récents (tous les enfants)
$mots_carnet = [];
$nb_carnet_nonlus = 0;
if ($ids) {
    $ph = implode(',', array_fill(0, count($ids), '?'));
    $mots_carnet = q("SELECT m.id, m.message, m.created_at, m.lu, e.prenom, e.nom
                      FROM mots_carnet m JOIN eleves e ON e.id = m.eleve_id
                      WHERE m.eleve_id IN ($ph)
                      ORDER BY m.created_at DESC, m.id DESC LIMIT 6", $ids)->fetchAll();
    $nb_carnet_nonlus = (int)q("SELECT COUNT(*) c FROM mots_carnet m WHERE m.eleve_id IN ($ph) AND m.lu = 0", $ids)->fetch()['c'];
}

include __DIR__ . '/../includes/header_parent.php';
?>

<div class="section-title"><i class="fas fa-home"></i> Bonjour, <?= e(user_fullname() ?: '') ?></div>
<p class="section-sub">Suivi scolaire de votre (vos) enfant(s).</p>

<?php if (!$enfants): ?>
    <div class="card">
        <div class="card-body">
            <div class="empty-state">
                <i class="fas fa-user-friends"></i>
                <p>Aucun enfant lié à ce compte.</p>
                <p style="font-size:0.85rem; margin-top:8px;">Contactez l'administration de l'école pour relier votre enfant à votre adresse email.</p>
            </div>
        </div>
    </div>
<?php else: ?>

<?php foreach ($enfants as $e): ?>
    <?php $s = $stats[$e['id']]; $meilleure = null; ?>
    <div class="card">
        <div class="card-head">
            <h3><i class="fas fa-user-graduate" style="color:var(--gold)"></i> <?= e($e['prenom']) ?> <?= e($e['nom']) ?></h3>
            <span class="badge badge-blue"><?= e($e['classe'] ?: '—') ?></span>
        </div>
        <div class="card-body">
            <div class="cards-grid">
                <?php for ($t = 1; $t <= 3; $t++): ?>
                    <?php $g = $s['moyennes'][$t]; if ($g !== null && ($meilleure === null || $g > $meilleure)) $meilleure = $g; ?>
                    <div class="stat-card">
                        <div class="stat-ic <?= $g !== null && $g >= 10 ? 'green' : 'gold' ?>"><i class="fas fa-chart-line"></i></div>
                        <div>
                            <div class="stat-num"><?= $g !== null ? number_format($g, 2, ',', ' ') : '—' ?></div>
                            <div class="stat-label">Moyenne T<?= $t ?></div>
                        </div>
                    </div>
                <?php endfor; ?>
                <div class="stat-card">
                    <div class="stat-ic red"><i class="fas fa-user-times"></i></div>
                    <div>
                        <div class="stat-num"><?= $s['absences'] ?></div>
                        <div class="stat-label">Absences (<?= $s['abs_just'] ?> justif.)</div>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-ic teal"><i class="fas fa-clipboard-check"></i></div>
                    <div>
                        <div class="stat-num"><?= $s['notes'] ?></div>
                        <div class="stat-label">Notes saisies</div>
                    </div>
                </div>
            </div>

            <?php if ($meilleure !== null): ?>
                <p class="section-sub" style="margin-top:12px;">
                    Meilleure moyenne (trimestre) : <strong style="color:var(--green)"><?= number_format($meilleure, 2, ',', ' ') ?>/20</strong>
                </p>
            <?php endif; ?>

            <div style="display:flex; gap:8px; flex-wrap:wrap; margin-top:14px;">
                <a href="notes.php?enfant=<?= $e['id'] ?>" class="btn btn-primary btn-sm"><i class="fas fa-clipboard-check"></i> Notes</a>
                <a href="bulletins.php?enfant=<?= $e['id'] ?>" class="btn btn-gold btn-sm"><i class="fas fa-file-invoice"></i> Bulletin</a>
                <a href="absences.php?enfant=<?= $e['id'] ?>" class="btn btn-danger btn-sm"><i class="fas fa-user-times"></i> Absences</a>
                <a href="emploi_du_temps.php?enfant=<?= $e['id'] ?>" class="btn btn-primary btn-sm"><i class="fas fa-calendar-week"></i> Emploi du temps</a>
            </div>
        </div>
    </div>
<?php endforeach; ?>

<div class="cards-grid">
    <div class="card">
        <div class="card-head">
            <h3><i class="fas fa-book-open" style="color:var(--gold)"></i> Mots du carnet</h3>
            <div style="display:flex; gap:8px; align-items:center;">
                <?php if ($nb_carnet_nonlus > 0): ?><span class="badge badge-gold"><?= $nb_carnet_nonlus ?> nouveau(x)</span><?php endif; ?>
                <a href="carnet.php" class="btn btn-gold btn-sm"><i class="fas fa-arrow-right"></i> Voir</a>
            </div>
        </div>
        <div class="table-wrap">
            <table class="data">
                <thead><tr><th>Élève</th><th>Message</th><th>Date</th><th>État</th></tr></thead>
                <tbody>
                    <?php if (!$mots_carnet): ?>
                        <tr><td colspan="4"><div class="empty-state"><i class="fas fa-book-open"></i><p>Aucun mot dans le carnet.</p></div></td></tr>
                    <?php endif; ?>
                    <?php foreach ($mots_carnet as $mc): ?>
                        <tr>
                            <td><?= e($mc['prenom']) ?> <?= e($mc['nom']) ?></td>
                            <td><?= e(mb_strimwidth($mc['message'], 0, 80, '…')) ?></td>
                            <td><?= e(date('d/m/Y', strtotime($mc['created_at']))) ?></td>
                            <td><?= $mc['lu'] ? '<span class="badge badge-green">Lu</span>' : '<span class="badge badge-gold">Non lu</span>' ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div class="card">
        <div class="card-head">
            <h3><i class="fas fa-file-signature" style="color:var(--gold)"></i> Prochains examens</h3>
            <a href="examens.php" class="btn btn-gold btn-sm"><i class="fas fa-arrow-right"></i> Voir</a>
        </div>
        <div class="table-wrap">
            <table class="data">
                <thead>
                    <tr><th>Date</th><th>Heure</th><th>Classe</th><th>Matière</th><th>Salle</th></tr>
                </thead>
                <tbody>
                    <?php if (!$examens): ?>
                        <tr><td colspan="5"><div class="empty-state"><i class="fas fa-calendar-check"></i><p>Aucun examen planifié.</p></div></td></tr>
                    <?php endif; ?>
                    <?php foreach ($examens as $ex): ?>
                        <tr>
                            <td><?= e($ex['date_examen']) ?></td>
                            <td><?= e(substr($ex['heure_debut'], 0, 5)) ?></td>
                            <td><span class="badge badge-blue"><?= e($ex['classe']) ?></span></td>
                            <td><?= e($ex['matiere']) ?></td>
                            <td><?= e($ex['salle']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div class="card">
        <div class="card-head">
            <h3><i class="fas fa-user-times" style="color:var(--red)"></i> Absences récentes</h3>
            <a href="absences.php" class="btn btn-sm btn-danger">Voir</a>
        </div>
        <div class="table-wrap">
            <table class="data">
                <thead><tr><th>Élève</th><th>Date</th><th>État</th></tr></thead>
                <tbody>
                    <?php if (!$abs_recentes): ?>
                        <tr><td colspan="3"><div class="empty-state"><i class="fas fa-user-check"></i><p>Aucune absence récente.</p></div></td></tr>
                    <?php endif; ?>
                    <?php foreach ($abs_recentes as $a): ?>
                        <tr>
                            <td><?= e($a['prenom']) ?> <?= e($a['nom']) ?></td>
                            <td><?= e($a['date_absence']) ?></td>
                            <td><?= $a['justifiee'] ? '<span class="badge badge-green">Justifiée</span>' : '<span class="badge badge-red">Non justifiée</span>' ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php endif; ?>

<?php include __DIR__ . '/../includes/footer_parent.php'; ?>
