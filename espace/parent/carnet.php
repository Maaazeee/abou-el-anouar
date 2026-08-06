<?php
require_once __DIR__ . '/../includes/parent.php';
require_once __DIR__ . '/../includes/pdf.php';

$page_title = 'Carnet de correspondance';
$enfants = parent_enfants();
$enfant = parent_enfant_selectionne($enfants);

$mots = [];
$nouveaux = [];
if ($enfant) {
    $mots = q('SELECT * FROM mots_carnet WHERE eleve_id = ? ORDER BY created_at DESC, id DESC', [$enfant['id']])->fetchAll();
    // Les mots non lus sont identifiés avant d'être marqués comme lus
    $nouveaux = array_map(fn($m) => (int)$m['id'], array_filter($mots, fn($m) => !$m['lu']));
    if ($nouveaux) {
        q('UPDATE mots_carnet SET lu = 1 WHERE eleve_id = ? AND lu = 0', [$enfant['id']]);
    }
}

if (isset($_GET['export']) && $enfant) {
    $rows = [];
    foreach ($mots as $m) {
        $rows[] = [
            'date' => $m['created_at'],
            'message' => $m['message'],
            'etat' => in_array((int)$m['id'], $nouveaux) ? 'Nouveau' : 'Lu',
        ];
    }
    $fname = 'carnet_' . slugify($enfant['prenom'] . '_' . $enfant['nom']);
    if ($_GET['export'] === 'csv') {
        export_csv($fname . '.csv', ['date', 'message', 'etat'], $rows);
    } else {
        pdf_export('Carnet de correspondance — ' . $enfant['prenom'] . ' ' . $enfant['nom'], ['Date', 'Message', 'État'], $rows, $fname);
    }
}

include __DIR__ . '/../includes/header_parent.php';
?>

<div class="section-title"><i class="fas fa-book"></i> Carnet de correspondance</div>
<p class="section-sub">Messages de l'établissement destinés aux parents.</p>

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
    <?php if ($mots): $export_base = 'carnet.php?enfant=' . $enfant['id']; $no_import = true; include __DIR__ . '/../includes/export_ui.php'; endif; ?>

    <div class="card">
        <div class="card-head">
            <h3><i class="fas fa-book-open"></i> Mots pour <span class="badge badge-blue"><?= e($enfant['prenom']) ?> <?= e($enfant['nom']) ?></span></h3>
            <span class="badge badge-green"><?= count($mots) ?> mot(s)</span>
        </div>
        <div class="card-body">
            <?php if (!$mots): ?>
                <div class="empty-state">
                    <i class="fas fa-book-open"></i>
                    <p>Aucun mot dans le carnet pour le moment.</p>
                </div>
            <?php endif; ?>
            <?php foreach ($mots as $i => $m): ?>
                <div class="carnet-mot <?= in_array((int)$m['id'], $nouveaux) ? 'carnet-mot-new' : '' ?>">
                    <div class="carnet-mot-head">
                        <span class="carnet-mot-date"><i class="fas fa-calendar-day"></i> <?= e(date('d/m/Y', strtotime($m['created_at']))) ?></span>
                        <?php if (in_array((int)$m['id'], $nouveaux)): ?>
                            <span class="badge badge-gold">Nouveau</span>
                        <?php else: ?>
                            <span class="badge badge-green"><i class="fas fa-check"></i> Lu</span>
                        <?php endif; ?>
                    </div>
                    <p class="carnet-mot-msg"><?= e($m['message']) ?></p>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
<?php endif; ?>

<?php include __DIR__ . '/../includes/footer_parent.php'; ?>