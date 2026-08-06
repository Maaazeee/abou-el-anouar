<?php
require_once __DIR__ . '/../includes/auth.php';
require_role('surveillant');
require_once __DIR__ . '/../includes/pdf.php';

$page_title = 'Carnet de correspondance';
$classes = q('SELECT id, nom FROM classes ORDER BY id')->fetchAll();
$classe_filter = (int)($_GET['classe'] ?? ($classes[0]['id'] ?? 0));
$eleves = q('SELECT id, nom, prenom FROM eleves WHERE classe_id = ? ORDER BY nom, prenom', [$classe_filter])->fetchAll();

// ---- AJOUT D'UN MOT ----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajouter_mot'])) {
    $eleve_id = (int)($_POST['eleve_id'] ?? 0);
    $message = trim($_POST['message'] ?? '');
    if ($eleve_id && $message !== '') {
        q('INSERT INTO mots_carnet (eleve_id, message) VALUES (?, ?)', [$eleve_id, $message]);
        set_flash('success', "Mot ajouté au carnet de l'élève.");
    } else {
        set_flash('error', 'Choisissez un élève et saisissez le message.');
    }
    header('Location: carnet.php?classe=' . $classe_filter);
    exit;
}

// ---- SUPPRESSION ----
if (isset($_GET['delete'])) {
    q('DELETE FROM mots_carnet WHERE id = ?', [(int)$_GET['delete']]);
    set_flash('success', 'Mot supprimé.');
    header('Location: carnet.php?classe=' . $classe_filter);
    exit;
}

// ---- EXPORT ----
$mots_export = [];
if (isset($_GET['export'])) {
    $mots_export = q('SELECT m.*, e.nom, e.prenom, c.nom AS classe
                      FROM mots_carnet m
                      JOIN eleves e ON e.id = m.eleve_id
                      LEFT JOIN classes c ON c.id = e.classe_id
                      ORDER BY m.created_at DESC')->fetchAll();
    $rows = [];
    foreach ($mots_export as $m) {
        $rows[] = [
            'date' => $m['created_at'],
            'classe' => $m['classe'] ?? '',
            'eleve' => $m['prenom'] . ' ' . $m['nom'],
            'message' => $m['message'],
            'lu' => $m['lu'] ? 'Lu' : 'Non lu',
        ];
    }
    if ($_GET['export'] === 'csv') {
        export_csv('carnet.csv', ['date', 'classe', 'eleve', 'message', 'lu'], $rows);
    } else {
        pdf_export('Carnet de correspondance', ['Date', 'Classe', 'Élève', 'Message', 'État'], $rows, 'carnet');
    }
}

// ---- LISTE (filtrée par classe) ----
$mots = q('SELECT m.*, e.nom, e.prenom, c.nom AS classe
           FROM mots_carnet m
           JOIN eleves e ON e.id = m.eleve_id
           LEFT JOIN classes c ON c.id = e.classe_id
           WHERE e.classe_id = ?
           ORDER BY m.created_at DESC, m.id DESC', [$classe_filter])->fetchAll();

$export_base = 'carnet.php?classe=' . $classe_filter;
$import_columns = '';
$no_import = true;

include __DIR__ . '/../includes/header.php';
?>

<div class="section-title"><i class="fas fa-book"></i> Carnet de correspondance</div>
<p class="section-sub">Écrivez un mot destiné aux parents d'un élève. Ils le verront dans leur espace.</p>

<?php include __DIR__ . '/../includes/export_ui.php'; ?>

<div class="filters">
    <form method="GET" style="display:flex; gap:12px; flex-wrap:wrap; align-items:flex-end; width:100%;">
        <div class="form-group" style="flex:1; min-width:150px;">
            <label for="classe">Classe</label>
            <select name="classe" id="classe" onchange="this.form.submit()">
                <?php foreach ($classes as $c): ?>
                    <option value="<?= $c['id'] ?>" <?= $c['id'] == $classe_filter ? 'selected' : '' ?>><?= e($c['nom']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
    </form>
</div>

<div class="card">
    <div class="card-head">
        <h3><i class="fas fa-pen" style="color:var(--gold)"></i> Ajouter un mot</h3>
    </div>
    <div class="card-body">
        <form method="POST" class="form-grid">
            <div class="form-group" style="min-width:200px;">
                <label>Élève *</label>
                <select name="eleve_id" required>
                    <option value="">— Choisir —</option>
                    <?php if (!$eleves): ?>
                        <option value="" disabled>Aucun élève dans cette classe</option>
                    <?php endif; ?>
                    <?php foreach ($eleves as $el): ?>
                        <option value="<?= $el['id'] ?>"><?= e($el['prenom']) ?> <?= e($el['nom']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group" style="grid-column: 1 / -1;">
                <label>Message *</label>
                <textarea name="message" rows="3" required placeholder="Ex : Prière de signer la fiche de renseignements, bon comportement, absence…"></textarea>
            </div>
            <div class="form-actions" style="grid-column: 1 / -1;">
                <button type="submit" name="ajouter_mot" value="1" class="btn btn-gold"><i class="fas fa-paper-plane"></i> Envoyer le mot</button>
            </div>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-head">
        <h3><i class="fas fa-book"></i> Mots écrits — <span class="badge badge-blue"><?= e($classes[array_search($classe_filter, array_column($classes, 'id'))]['nom'] ?? '') ?></span></h3>
        <span class="badge badge-green"><?= count($mots) ?> mot(s)</span>
    </div>
    <div class="table-wrap">
        <table class="data">
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Élève</th>
                    <th>Message</th>
                    <th>État</th>
                    <th style="width:70px">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!$mots): ?>
                    <tr><td colspan="5"><div class="empty-state"><i class="fas fa-book-open"></i><p>Aucun mot pour cette classe.</p></div></td></tr>
                <?php endif; ?>
                <?php foreach ($mots as $m): ?>
                    <tr>
                        <td style="white-space:nowrap;"><?= e(date('d/m/Y', strtotime($m['created_at']))) ?></td>
                        <td><strong><?= e($m['prenom']) ?> <?= e($m['nom']) ?></strong></td>
                        <td><?= e($m['message']) ?></td>
                        <td><?= $m['lu'] ? '<span class="badge badge-green">Lu par les parents</span>' : '<span class="badge badge-gold">Non lu</span>' ?></td>
                        <td>
                            <a href="?delete=<?= $m['id'] ?>&classe=<?= $classe_filter ?>" class="btn btn-sm btn-danger"
                               onclick="return confirm('Supprimer ce mot ?');"><i class="fas fa-trash"></i></a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>