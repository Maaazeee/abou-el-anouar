<?php
require_once __DIR__ . '/../includes/auth.php';
require_role('surveillant');
require_once __DIR__ . '/../includes/pdf.php';

$page_title = 'Pré-inscriptions';

// ---- CHANGEMENT DE STATUT ----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['set_statut'])) {
    $id = (int)$_POST['id'];
    $val = $_POST['statut'] === 'validee' ? 'validee' : ($_POST['statut'] === 'refusee' ? 'refusee' : 'nouveau');
    q('UPDATE preinscriptions SET statut = ? WHERE id = ?', [$val, $id]);
    set_flash('success', 'Statut de la pré-inscription mis à jour.');
    header('Location: preinscriptions.php?statut=' . urlencode($_POST['retour_statut'] ?? 'nouveau'));
    exit;
}

// ---- CONVERSION EN ÉLÈVE ----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['convertir'])) {
    $id = (int)$_POST['id'];
    $classe_id = (int)$_POST['classe_id'];
    $p = q('SELECT * FROM preinscriptions WHERE id = ?', [$id])->fetch();
    if ($p && $classe_id) {
        q('INSERT INTO eleves (nom, prenom, date_naissance, lieu_naissance, nationalite, sexe, classe_id, adresse, telephone_parent, email_parent, date_inscription)
           VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
          [$p['nom'], $p['prenom'],
           $p['date_naissance'] ?: null,
           $p['lieu_naissance'] ?: null,
           $p['nationalite'] ?: 'Algérienne',
           $p['civilite'] === 'M' ? 'M' : 'F',
           $classe_id,
           $p['parent_adresse'] ?: null,
           $p['parent_tel'] ?: null,
           $p['parent_email'] ?: null,
           date('Y-m-d')]);
        q('UPDATE preinscriptions SET statut = ? WHERE id = ?', ['validee', $id]);
        set_flash('success', "Pré-inscription convertie : l'élève " . $p['prenom'] . ' ' . $p['nom'] . ' a été ajouté.');
    } else {
        set_flash('error', 'Choisissez une classe pour convertir la demande.');
    }
    header('Location: preinscriptions.php?statut=' . urlencode($_POST['retour_statut'] ?? 'nouveau'));
    exit;
}

// ---- SUPPRESSION ----
if (isset($_GET['delete'])) {
    q('DELETE FROM preinscriptions WHERE id = ?', [(int)$_GET['delete']]);
    set_flash('success', 'Demande supprimée.');
    header('Location: preinscriptions.php');
    exit;
}

// ---- EXPORT ----
if (isset($_GET['export'])) {
    $rows = [];
    foreach (q('SELECT * FROM preinscriptions ORDER BY created_at DESC')->fetchAll() as $p) {
        $rows[] = [
            'date' => $p['created_at'],
            'eleve' => $p['nom'] . ' ' . $p['prenom'],
            'naissance' => $p['date_naissance'] ?? '',
            'cycle' => $p['cycle'] ?? '',
            'classe_actuelle' => $p['classe_actuelle'] ?? '',
            'parent' => ($p['parent_nom'] ?? '') . ' ' . ($p['parent_prenom'] ?? ''),
            'telephone' => $p['parent_tel'] ?? '',
            'email' => $p['parent_email'] ?? '',
            'statut' => $p['statut'],
        ];
    }
    if ($_GET['export'] === 'csv') {
        export_csv('preinscriptions.csv', ['date', 'eleve', 'naissance', 'cycle', 'classe_actuelle', 'parent', 'telephone', 'email', 'statut'], $rows);
    } else {
        pdf_export('Pré-inscriptions', ['Date', 'Élève', 'Naissance', 'Cycle', 'Classe actuelle', 'Parent', 'Téléphone', 'Email', 'Statut'], $rows, 'preinscriptions');
    }
}

// ---- DONNÉES ----
$statut_filter = $_GET['statut'] ?? 'nouveau';
$classes = q('SELECT id, nom FROM classes ORDER BY id')->fetchAll();
if ($statut_filter === 'tous') {
    $rows = q('SELECT * FROM preinscriptions ORDER BY created_at DESC')->fetchAll();
} else {
    $val = in_array($statut_filter, ['validee', 'refusee'], true) ? $statut_filter : 'nouveau';
    $rows = q('SELECT * FROM preinscriptions WHERE statut = ? ORDER BY created_at DESC', [$val])->fetchAll();
}
$counts = [
    'nouveau' => (int)q("SELECT COUNT(*) FROM preinscriptions WHERE statut='nouveau'")->fetchColumn(),
    'validee' => (int)q("SELECT COUNT(*) FROM preinscriptions WHERE statut='validee'")->fetchColumn(),
    'refusee' => (int)q("SELECT COUNT(*) FROM preinscriptions WHERE statut='refusee'")->fetchColumn(),
];

$export_base = 'preinscriptions.php?statut=' . urlencode($statut_filter);
$import_columns = '';
$no_import = true;

include __DIR__ . '/../includes/header.php';
?>

<div class="section-title"><i class="fas fa-file-signature"></i> Pré-inscriptions</div>
<p class="section-sub">Demandes reçues depuis le formulaire public du site. Validez, refusez ou convertissez une demande en élève inscrit.</p>

<?php include __DIR__ . '/../includes/export_ui.php'; ?>

<div class="filters">
    <form method="GET" style="display:flex; gap:8px; flex-wrap:wrap; align-items:center;">
        <?php foreach (['nouveau' => 'Nouvelles', 'validee' => 'Validées', 'refusee' => 'Refusées', 'tous' => 'Toutes'] as $v => $lbl): ?>
            <a href="?statut=<?= $v ?>" class="chip <?= $statut_filter === $v ? 'active' : '' ?>">
                <i class="fas fa-<?= $v === 'nouveau' ? 'inbox' : ($v === 'validee' ? 'check-circle' : ($v === 'refusee' ? 'ban' : 'list')) ?>"></i>
                <?= $lbl ?> (<?= $counts[$v] ?? array_sum($counts) ?>)
            </a>
        <?php endforeach; ?>
    </form>
</div>

<div class="card">
    <div class="card-head">
        <h3><i class="fas fa-list"></i> Demandes de pré-inscription</h3>
        <span class="badge badge-blue"><?= count($rows) ?> demande(s)</span>
    </div>
    <div class="table-wrap">
        <table class="data">
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Élève</th>
                    <th>Cycle / Classe actuelle</th>
                    <th>Parent</th>
                    <th>Contact</th>
                    <th>Santé</th>
                    <th>Statut</th>
                    <th style="width:250px">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!$rows): ?>
                    <tr><td colspan="8"><div class="empty-state"><i class="fas fa-inbox"></i><p>Aucune demande dans cette catégorie.</p></div></td></tr>
                <?php endif; ?>
                <?php foreach ($rows as $p): ?>
                    <tr>
                        <td style="white-space:nowrap;"><?= e(date('d/m/Y', strtotime($p['created_at']))) ?></td>
                        <td>
                            <strong><?= e($p['prenom']) ?> <?= e($p['nom']) ?></strong>
                            <?php if ($p['date_naissance']): ?><div style="color:var(--muted);font-size:0.8rem;">Né(e) le <?= e($p['date_naissance']) ?> (<?= e($p['lieu_naissance'] ?: '—') ?>)</div><?php endif; ?>
                        </td>
                        <td>
                            <span class="badge badge-blue"><?= e($p['cycle'] ?: '—') ?></span>
                            <span class="badge badge-gray"><?= e($p['classe_actuelle'] ?: '—') ?></span>
                        </td>
                        <td>
                            <strong><?= e($p['parent_prenom'] ?: '') ?> <?= e($p['parent_nom'] ?: '') ?></strong>
                            <?php if ($p['lien_parente']): ?><div style="color:var(--muted);font-size:0.8rem;"><?= e($p['lien_parente']) ?></div><?php endif; ?>
                        </td>
                        <td>
                            <?php if ($p['parent_tel']): ?><div><i class="fas fa-phone" style="color:var(--muted)"></i> <?= e($p['parent_tel']) ?></div><?php endif; ?>
                            <?php if ($p['parent_email']): ?><div style="font-size:0.8rem;"><?= e($p['parent_email']) ?></div><?php endif; ?>
                        </td>
                        <td><?= $p['sante'] ? '<span class="badge badge-red" title="' . e($p['sante']) . '">Signalé</span>' : '<span class="badge badge-green">OK</span>' ?></td>
                        <td>
                            <?php if ($p['statut'] === 'nouveau'): ?><span class="badge badge-gold">Nouvelle</span>
                            <?php elseif ($p['statut'] === 'validee'): ?><span class="badge badge-green">Validée</span>
                            <?php else: ?><span class="badge badge-red">Refusée</span><?php endif; ?>
                        </td>
                        <td style="white-space:nowrap;">
                            <?php if ($p['statut'] !== 'validee'): ?>
                                <form method="POST" style="display:inline-block;">
                                    <input type="hidden" name="id" value="<?= $p['id'] ?>">
                                    <input type="hidden" name="set_statut" value="1">
                                    <input type="hidden" name="retour_statut" value="<?= e($statut_filter) ?>">
                                    <input type="hidden" name="statut" value="validee">
                                    <button class="btn btn-sm btn-success" title="Valider la demande"><i class="fas fa-check"></i></button>
                                </form>
                            <?php endif; ?>
                            <?php if ($p['statut'] !== 'refusee'): ?>
                                <form method="POST" style="display:inline-block;">
                                    <input type="hidden" name="id" value="<?= $p['id'] ?>">
                                    <input type="hidden" name="set_statut" value="1">
                                    <input type="hidden" name="retour_statut" value="<?= e($statut_filter) ?>">
                                    <input type="hidden" name="statut" value="refusee">
                                    <button class="btn btn-sm btn-danger" title="Refuser la demande"><i class="fas fa-times"></i></button>
                                </form>
                            <?php endif; ?>
                            <?php if ($p['statut'] === 'nouveau'): ?>
                                <button class="btn btn-sm btn-gold" data-modal-open="modalConv<?= $p['id'] ?>"><i class="fas fa-user-plus"></i> Convertir</button>
                            <?php endif; ?>
                            <a href="?delete=<?= $p['id'] ?>&statut=<?= e($statut_filter) ?>" class="btn btn-sm btn-danger"
                               onclick="return confirm('Supprimer cette demande ?');"><i class="fas fa-trash"></i></a>
                        </td>
                    </tr>

                    <!-- MODAL CONVERSION -->
                    <div class="modal-overlay" id="modalConv<?= $p['id'] ?>">
                        <div class="modal">
                            <form method="POST">
                                <input type="hidden" name="id" value="<?= $p['id'] ?>">
                                <input type="hidden" name="convertir" value="1">
                                <input type="hidden" name="retour_statut" value="<?= e($statut_filter) ?>">
                                <div class="modal-head">
                                    <h3><i class="fas fa-user-plus" style="color:var(--gold)"></i> Convertir en élève</h3>
                                    <button type="button" class="modal-close" data-modal-close><i class="fas fa-times"></i></button>
                                </div>
                                <div class="modal-body">
                                    <p style="margin:0 0 14px;"><?= e($p['prenom']) ?> <?= e($p['nom']) ?> sera ajouté à la liste des élèves de la classe choisie.</p>
                                    <div class="form-group">
                                        <label>Classe d'affectation *</label>
                                        <select name="classe_id" required>
                                            <option value="">— Choisir —</option>
                                            <?php foreach ($classes as $c): ?>
                                                <option value="<?= $c['id'] ?>"><?= e($c['nom']) ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                </div>
                                <div class="modal-foot">
                                    <button type="button" class="btn btn-danger" data-modal-close>Annuler</button>
                                    <button type="submit" class="btn btn-gold"><i class="fas fa-check"></i> Convertir</button>
                                </div>
                            </form>
                        </div>
                    </div>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
