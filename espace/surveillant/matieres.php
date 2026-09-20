<?php
require_once __DIR__ . '/../includes/auth.php';
require_role('surveillant');
require_once __DIR__ . '/../includes/pdf.php';

$page_title = 'Matières';

// ---- AJOUT / MODIFICATION MATIÈRE ----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['matiere_nom'])) {
    $id = (int)($_POST['id'] ?? 0);
    $nom = trim($_POST['matiere_nom']);
    if ($nom === '') {
        set_flash('error', 'Le nom de la matière est obligatoire.');
    } elseif ($id > 0) {
        q('UPDATE matieres SET nom = ? WHERE id = ?', [$nom, $id]);
        set_flash('success', 'Matière modifiée.');
    } else {
        q('INSERT INTO matieres (nom) VALUES (?)', [$nom]);
        set_flash('success', 'Matière ajoutée.');
    }
    header('Location: matieres.php');
    exit;
}

// ---- SUPPRESSION MATIÈRE ----
if (isset($_GET['delete'])) {
    q('DELETE FROM matieres WHERE id = ?', [(int)$_GET['delete']]);
    set_flash('success', 'Matière supprimée.');
    header('Location: matieres.php');
    exit;
}

// ---- AJOUT FILIÈRE ----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['filiere_nom'])) {
    $nom = trim($_POST['filiere_nom']);
    if ($nom !== '') {
        q('INSERT INTO filieres (nom) VALUES (?)', [$nom]);
        set_flash('success', 'Filière ajoutée.');
    } else {
        set_flash('error', 'Nom de filière obligatoire.');
    }
    header('Location: matieres.php');
    exit;
}

// ---- SUPPRESSION FILIÈRE ----
if (isset($_GET['del_filiere'])) {
    q('DELETE FROM filieres WHERE id = ?', [(int)$_GET['del_filiere']]);
    set_flash('success', 'Filière supprimée (les coefficients liés aussi).');
    header('Location: matieres.php');
    exit;
}

// ---- SAUVEGARDE DES COEFFICIENTS ----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['coef'])) {
    $coef = $_POST['coef'];
    foreach ($coef as $matiere_id => $vals) {
        foreach ($vals as $filiere_id => $value) {
            $value = (float)str_replace(',', '.', $value);
            if ($value > 0) {
                q('INSERT INTO matiere_filiere (matiere_id, filiere_id, coefficient) VALUES (?, ?, ?)
                   ON CONFLICT (matiere_id, filiere_id) DO UPDATE SET coefficient = EXCLUDED.coefficient',
                  [(int)$matiere_id, (int)$filiere_id, $value]);
            } else {
                q('DELETE FROM matiere_filiere WHERE matiere_id = ? AND filiere_id = ?',
                  [(int)$matiere_id, (int)$filiere_id]);
            }
        }
    }
    set_flash('success', 'Coefficients mis à jour.');
    header('Location: matieres.php');
    exit;
}

// ---- ÉDITION MATIÈRE ----
$edit = null;
if (isset($_GET['edit'])) {
    $edit = q('SELECT * FROM matieres WHERE id = ?', [(int)$_GET['edit']])->fetch();
}

// ---- EXPORT ----
if (isset($_GET['export'])) {
    $matieres = q('SELECT m.*, STRING_AGG(p.prenom || \' \' || p.nom, \'; \') AS profs
                   FROM matieres m LEFT JOIN professeurs p ON p.matiere_id = m.id
                   GROUP BY m.id, m.nom ORDER BY m.nom')->fetchAll();
    $filieres = q('SELECT * FROM filieres ORDER BY nom')->fetchAll();
    $coefs = q('SELECT * FROM matiere_filiere')->fetchAll();
    $coef_map = [];
    foreach ($coefs as $c) { $coef_map[$c['matiere_id'] . '_' . $c['filiere_id']] = $c['coefficient']; }

    $headers = ['matiere', 'professeurs', 'coef_generale'];
    foreach ($filieres as $f) { $headers[] = 'coef_' . slugify($f['nom']); }
    $rows = [];
    foreach ($matieres as $m) {
        $row = ['matiere' => $m['nom'], 'professeurs' => $m['profs'] ?? '', 'coef_generale' => $coef_map[$m['id'] . '_0'] ?? ''];
        foreach ($filieres as $f) {
            $row['coef_' . slugify($f['nom'])] = $coef_map[$m['id'] . '_' . $f['id']] ?? '';
        }
        $rows[] = $row;
    }
    if ($_GET['export'] === 'csv') {
        export_csv('matieres.csv', $headers, $rows);
    } else {
        $pdf_headers = ['Matière', 'Professeurs', 'Générale (avant lycée)'];
        foreach ($filieres as $f) { $pdf_headers[] = $f['nom']; }
        pdf_export('Matières & coefficients', $pdf_headers, $rows, 'matieres');
    }
}

// ---- IMPORT ----
if (isset($_POST['do_import']) && isset($_FILES['import_csv']) && $_FILES['import_csv']['error'] === 0) {
    $lines = parse_csv($_FILES['import_csv']['tmp_name']);
    $count = 0;
    $errors = 0;
    foreach ($lines as $r) {
        $nom = trim($r['matiere'] ?? '');
        if ($nom === '') { $errors++; continue; }
        q('INSERT INTO matieres (nom) VALUES (?)', [$nom]);
        $count++;
    }
    set_flash('success', "$count matière(s) importée(s)." . ($errors ? " $errors ligne(s) ignorée(s)." : ''));
    header('Location: matieres.php');
    exit;
}

// ---- DONNÉES ----
$matieres = q('SELECT m.*,
               (SELECT COUNT(*) FROM professeurs p WHERE p.matiere_id = m.id) AS nb_profs
               FROM matieres m ORDER BY m.nom')->fetchAll();
$profs_par_matiere = [];
foreach (q('SELECT id, prenom, nom, matiere_id FROM professeurs ORDER BY nom')->fetchAll() as $p) {
    $profs_par_matiere[$p['matiere_id']][] = $p;
}
$filieres = q('SELECT * FROM filieres ORDER BY nom')->fetchAll();
$coefs = q('SELECT * FROM matiere_filiere')->fetchAll();
$coef_map = [];
foreach ($coefs as $c) { $coef_map[$c['matiere_id'] . '_' . $c['filiere_id']] = $c['coefficient']; }

$export_base = 'matieres.php';
$import_columns = 'matiere  (coefficients « Générale » et par filière à régler ensuite dans la grille)';

include __DIR__ . '/../includes/header.php';
?>

<div class="section-title"><i class="fas fa-book-open"></i> Gestion des matières</div>
<p class="section-sub">Toutes les matières, les professeurs qui les enseignent et les coefficients. Avant le lycée (primaire & moyenne), il n'y a qu'une seule filière « Générale » ; les filières n'existent qu'au lycée.</p>

<?php include __DIR__ . '/../includes/export_ui.php'; ?>

<div class="cards-grid" style="grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));">
    <div class="stat-card"><div class="stat-ic"><i class="fas fa-book"></i></div>
        <div><div class="stat-num"><?= count($matieres) ?></div><div class="stat-label">Matières</div></div></div>
    <div class="stat-card"><div class="stat-ic gold"><i class="fas fa-layer-group"></i></div>
        <div><div class="stat-num"><?= count($filieres) ?></div><div class="stat-label">Filières</div></div></div>
</div>

<div class="card">
    <div class="card-head">
        <h3><?= $edit ? 'Modifier la matière' : 'Ajouter une matière' ?></h3>
    </div>
    <div class="card-body">
        <form method="POST" style="display:flex; gap:12px; align-items:flex-end; flex-wrap:wrap;">
            <input type="hidden" name="id" value="<?= $edit['id'] ?? 0 ?>">
            <div class="form-group" style="flex:1; min-width:220px;">
                <label>Nom de la matière *</label>
                <input type="text" name="matiere_nom" required value="<?= e($edit['nom'] ?? '') ?>" placeholder="ex : Sciences Physiques">
            </div>
            <button type="submit" class="btn btn-gold"><i class="fas fa-save"></i> <?= $edit ? 'Mettre à jour' : 'Ajouter' ?></button>
            <?php if ($edit): ?><a href="matieres.php" class="btn btn-danger"><i class="fas fa-times"></i> Annuler</a><?php endif; ?>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-head">
        <h3><i class="fas fa-list"></i> Matières, professeurs et coefficients</h3>
    </div>
    <div class="table-wrap">
        <table class="data">
            <thead>
                <tr>
                    <th>Matière</th>
                    <th>Professeurs</th>
                    <th>Coefficients par filière</th>
                    <th style="width:110px">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!$matieres): ?>
                    <tr><td colspan="4"><div class="empty-state"><i class="fas fa-book"></i><p>Aucune matière. Ajoutez-en une ci-dessus.</p></div></td></tr>
                <?php endif; ?>
                <?php foreach ($matieres as $m): ?>
                    <tr>
                        <td><strong><?= e($m['nom']) ?></strong></td>
                        <td>
                            <?php $profs = $profs_par_matiere[$m['id']] ?? []; ?>
                            <?php foreach ($profs as $p): ?>
                                <span class="badge badge-blue"><?= e($p['prenom']) ?> <?= e($p['nom']) ?></span>
                            <?php endforeach; ?>
                            <?php if (!$profs): ?><span class="badge badge-gray">Aucun professeur</span><?php endif; ?>
                        </td>
                        <td>
                            <?php $c0 = $coef_map[$m['id'] . '_0'] ?? null; ?>
                            <?php if ($c0): ?>
                                <span class="badge badge-gold" title="Générale (avant le lycée)">Générale : <?= $c0 ?></span>
                            <?php endif; ?>
                            <?php $has_coef = (bool)$c0; ?>
                            <?php foreach ($filieres as $f): ?>
                                <?php $c = $coef_map[$m['id'] . '_' . $f['id']] ?? null; ?>
                                <?php if ($c): $has_coef = true; ?>
                                    <span class="badge badge-gold" title="<?= e($f['nom']) ?>"><?= e($f['nom']) ?> : <?= $c ?></span>
                                <?php endif; ?>
                            <?php endforeach; ?>
                            <?php if (!$has_coef): ?><span class="badge badge-gray">—</span><?php endif; ?>
                        </td>
                        <td>
                            <a href="?edit=<?= $m['id'] ?>" class="btn btn-sm btn-primary"><i class="fas fa-edit"></i></a>
                            <a href="?delete=<?= $m['id'] ?>" class="btn btn-sm btn-danger"
                               onclick="return confirm('Supprimer cette matière ?');"><i class="fas fa-trash"></i></a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="card">
    <div class="card-head">
        <h3><i class="fas fa-layer-group"></i> Filières & coefficients</h3>
        <form method="POST" style="display:flex; gap:8px; align-items:flex-end;">
            <div class="form-group">
                <label>Nouvelle filière (lycée)</label>
                <input type="text" name="filiere_nom" placeholder="ex : Mathématiques">
            </div>
            <button class="btn btn-gold btn-sm" type="submit"><i class="fas fa-plus"></i> Ajouter</button>
        </form>
    </div>
    <div class="card-body" style="padding-top:0;">
        <p class="section-sub" style="margin:0;">Avant le lycée (primaire & moyenne), il n'y a <strong>qu'une seule filière</strong> : les coefficients <strong>« Générale »</strong> s'appliquent à tous les élèves. Les filières (2AS/3AS) ne concernent que le lycée.</p>
    </div>
    <?php if (!$filieres): ?>
        <div class="card-body"><div class="empty-state"><i class="fas fa-layer-group"></i><p>Ajoutez une filière pour pouvoir définir les coefficients du lycée.</p></div></div>
    <?php else: ?>
        <form method="POST">
            <div class="table-wrap">
                <table class="data">
                    <thead>
                        <tr>
                            <th>Matière</th>
                            <th style="min-width:120px;">Générale<br><small>(avant le lycée)</small></th>
                            <?php foreach ($filieres as $f): ?>
                                <th style="min-width:120px;">
                                    <?= e($f['nom']) ?>
                                    <a href="?del_filiere=<?= $f['id'] ?>" class="filiere-del" title="Supprimer cette filière"
                                       onclick="return confirm('Supprimer la filière « <?= e($f['nom']) ?> » ?');"><i class="fas fa-times-circle"></i></a>
                                </th>
                            <?php endforeach; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($matieres as $m): ?>
                            <tr>
                                <td><strong><?= e($m['nom']) ?></strong></td>
                                <td>
                                    <input type="number" step="0.5" min="0" max="20"
                                           name="coef[<?= $m['id'] ?>][0]"
                                           value="<?= e($coef_map[$m['id'] . '_0'] ?? '') ?>"
                                           placeholder="0" style="width:70px; padding:7px 9px; border:1.5px solid var(--border); border-radius:8px; font-family:inherit;">
                                </td>
                                <?php foreach ($filieres as $f): ?>
                                    <td>
                                        <input type="number" step="0.5" min="0" max="20"
                                               name="coef[<?= $m['id'] ?>][<?= $f['id'] ?>]"
                                               value="<?= e($coef_map[$m['id'] . '_' . $f['id']] ?? '') ?>"
                                               placeholder="0" style="width:70px; padding:7px 9px; border:1.5px solid var(--border); border-radius:8px; font-family:inherit;">
                                    </td>
                                <?php endforeach; ?>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <div class="card-body" style="padding-top:8px;">
                <button type="submit" class="btn btn-gold"><i class="fas fa-save"></i> Enregistrer les coefficients</button>
            </div>
        </form>
    <?php endif; ?>
</div>

<style>
.filiere-del { color: var(--red); margin-left: 6px; font-size: 0.9rem; }
.filiere-del:hover { opacity: 0.7; }
</style>

<?php include __DIR__ . '/../includes/footer.php'; ?>
