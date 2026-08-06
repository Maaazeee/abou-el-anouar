<?php
require_once __DIR__ . '/../includes/auth.php';
require_role('surveillant');
require_once __DIR__ . '/../includes/pdf.php';

$page_title = 'Emploi du temps';

$jours = ['', 'Dimanche', 'Lundi', 'Mardi', 'Mercredi', 'Jeudi', 'Vendredi'];
$jour_num = ['dimanche' => 1, 'lundi' => 2, 'mardi' => 3, 'mercredi' => 4, 'jeudi' => 5, 'vendredi' => 6];
$classes = q('SELECT id, nom FROM classes ORDER BY id')->fetchAll();
$matieres = q('SELECT id, nom FROM matieres ORDER BY nom')->fetchAll();
$professeurs = q('SELECT id, nom, prenom FROM professeurs ORDER BY nom')->fetchAll();
$default_slots = [['08:00', '09:00'], ['09:00', '10:00'], ['10:00', '11:00'], ['11:00', '12:00'], ['14:00', '15:00'], ['15:00', '16:00']];

function parse_jour($v) {
    $v = strtolower(trim($v));
    if (is_numeric($v)) return (int)$v;
    $map = ['dimanche' => 1, 'lundi' => 2, 'mardi' => 3, 'mercredi' => 4, 'jeudi' => 5, 'vendredi' => 6];
    return $map[$v] ?? 0;
}

function upsert_seance($classe_id, $jour, $debut, $fin, $matiere_id, $prof_id) {
    $exists = q("SELECT id FROM emploi_du_temps WHERE classe_id=? AND jour=? AND heure_debut=? AND heure_fin=?",
                [$classe_id, $jour, $debut, $fin])->fetch();
    if ($exists) {
        q("UPDATE emploi_du_temps SET matiere_id=?, professeur_id=? WHERE id=?",
          [$matiere_id, $prof_id, $exists['id']]);
    } else {
        q("INSERT INTO emploi_du_temps (classe_id, jour, heure_debut, heure_fin, matiere_id, professeur_id) VALUES (?,?,?,?,?,?)",
          [$classe_id, $jour, $debut, $fin, $matiere_id, $prof_id]);
    }
}

$classe_filter = (int)($_GET['classe'] ?? ($classes[0]['id'] ?? 0));

// ---- AJOUT / MODIFICATION D'UNE SÉANCE ----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_seance'])) {
    $id = (int)($_POST['id'] ?? 0);
    $jour = parse_jour($_POST['jour'] ?? 0);
    $heure_debut = $_POST['heure_debut'] ?? '';
    $heure_fin = $_POST['heure_fin'] ?? '';
    $matiere_id = (int)($_POST['matiere_id'] ?? 0);
    $professeur_id = (int)($_POST['professeur_id'] ?? 0);
    $classe_id = (int)($_POST['classe_id'] ?? $classe_filter);

    if ($jour >= 1 && $jour <= 5 && $heure_debut && $heure_fin) {
        if ($id > 0) {
            q('UPDATE emploi_du_temps SET jour=?, heure_debut=?, heure_fin=?, matiere_id=?, professeur_id=? WHERE id=?',
              [$jour, $heure_debut, $heure_fin, $matiere_id ?: null, $professeur_id ?: null, $id]);
            set_flash('success', 'Séance modifiée.');
        } else {
            q('INSERT INTO emploi_du_temps (classe_id, jour, heure_debut, heure_fin, matiere_id, professeur_id) VALUES (?,?,?,?,?,?)',
              [$classe_id, $jour, $heure_debut, $heure_fin, $matiere_id ?: null, $professeur_id ?: null]);
            set_flash('success', 'Séance ajoutée.');
        }
    } else {
        set_flash('error', 'Jour et horaires obligatoires.');
    }
    header('Location: emploi_du_temps.php?classe=' . $classe_id);
    exit;
}

// ---- GÉNÉRER LES CRÉNEAUX PAR DÉFAUT ----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['generer'])) {
    $count = 0;
    foreach ($default_slots as $slot) {
        for ($j = 1; $j <= 5; $j++) {
            upsert_seance($classe_filter, $j, $slot[0], $slot[1], null, null);
            $count++;
        }
    }
    set_flash('success', "Grille générée : $count créneaux créés (Dimanche → Jeudi). Vendredi : jour de repos. Cliquez sur une case pour la remplir.");
    header('Location: emploi_du_temps.php?classe=' . $classe_filter);
    exit;
}

// ---- VIDER LA GRILLE ----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['vider'])) {
    q('DELETE FROM emploi_du_temps WHERE classe_id = ?', [$classe_filter]);
    set_flash('success', 'Grille de la classe vidée.');
    header('Location: emploi_du_temps.php?classe=' . $classe_filter);
    exit;
}

// ---- COPIER DEPUIS UNE AUTRE CLASSE ----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['copier'])) {
    $source = (int)$_POST['source_classe'];
    if ($source && $source !== $classe_filter) {
        $rows = q('SELECT jour, heure_debut, heure_fin, matiere_id, professeur_id FROM emploi_du_temps WHERE classe_id=?', [$source])->fetchAll();
        foreach ($rows as $r) {
            upsert_seance($classe_filter, $r['jour'], $r['heure_debut'], $r['heure_fin'], $r['matiere_id'], $r['professeur_id']);
        }
        set_flash('success', count($rows) . ' séance(s) copiée(s) depuis ' . e($classes[array_search($source, array_column($classes, 'id'))]['nom'] ?? '?'));
    } else {
        set_flash('error', 'Choisissez une classe source valide.');
    }
    header('Location: emploi_du_temps.php?classe=' . $classe_filter);
    exit;
}

// ---- SUPPRESSION ----
if (isset($_GET['delete'])) {
    q('DELETE FROM emploi_du_temps WHERE id = ?', [(int)$_GET['delete']]);
    set_flash('success', 'Séance supprimée.');
    header('Location: emploi_du_temps.php?classe=' . $classe_filter);
    exit;
}

// ---- EXPORT ----
if (isset($_GET['export'])) {
    $data = q('SELECT c.nom AS classe, e.jour, e.heure_debut, e.heure_fin, m.nom AS matiere, p.prenom, p.nom AS pnom
               FROM emploi_du_temps e
               JOIN classes c ON c.id = e.classe_id
               LEFT JOIN matieres m ON m.id = e.matiere_id
               LEFT JOIN professeurs p ON p.id = e.professeur_id
               ORDER BY c.id, e.jour, e.heure_debut')->fetchAll();
    $rows = [];
    foreach ($data as $d) {
        $rows[] = [
            'classe' => $d['classe'],
            'jour' => $jours[$d['jour']],
            'heure_debut' => substr($d['heure_debut'], 0, 5),
            'heure_fin' => substr($d['heure_fin'], 0, 5),
            'matiere' => $d['matiere'] ?? '',
            'professeur' => $d['prenom'] ? ($d['prenom'] . ' ' . $d['pnom']) : '',
        ];
    }
    if ($_GET['export'] === 'csv') {
        export_csv('emploi_du_temps.csv', ['classe', 'jour', 'heure_debut', 'heure_fin', 'matiere', 'professeur'], $rows);
    } else {
        pdf_export('Emploi du temps', ['Classe', 'Jour', 'Début', 'Fin', 'Matière', 'Professeur'], $rows, 'emploi_du_temps');
    }
}

// ---- IMPORT ----
if (isset($_POST['do_import']) && isset($_FILES['import_csv']) && $_FILES['import_csv']['error'] === 0) {
    $lines = parse_csv($_FILES['import_csv']['tmp_name']);
    $count = 0;
    $errors = 0;
    foreach ($lines as $r) {
        $classe_id = lookup_id('classes', 'nom', $r['classe'] ?? '');
        $jour = parse_jour($r['jour'] ?? '');
        $debut = trim($r['heure_debut'] ?? '');
        $fin = trim($r['heure_fin'] ?? '');
        if (!$classe_id || !$jour || $jour > 5 || !$debut || !$fin) { $errors++; continue; }
        $matiere_id = lookup_id('matieres', 'nom', $r['matiere'] ?? '');
        $prof_id = lookup_prof_id($r['professeur'] ?? '');
        upsert_seance($classe_id, $jour, $debut, $fin, $matiere_id, $prof_id);
        $count++;
    }
    set_flash('success', "$count séance(s) importée(s)." . ($errors ? " $errors ligne(s) ignorée(s)." : ''));
    header('Location: emploi_du_temps.php?classe=' . $classe_filter);
    exit;
}

// ---- DONNÉES POUR LA GRILLE ----
$slots = q('SELECT DISTINCT heure_debut, heure_fin FROM emploi_du_temps WHERE classe_id=? ORDER BY heure_debut', [$classe_filter])->fetchAll();
$seances = q('SELECT e.*, m.nom AS matiere, p.nom AS pnom, p.prenom AS pprenom
              FROM emploi_du_temps e
              LEFT JOIN matieres m ON m.id = e.matiere_id
              LEFT JOIN professeurs p ON p.id = e.professeur_id
              WHERE e.classe_id = ?
              ORDER BY e.jour, e.heure_debut', [$classe_filter])->fetchAll();
$grid = [];
foreach ($seances as $s) {
    $grid[$s['jour']][substr($s['heure_debut'], 0, 5) . '|' . substr($s['heure_fin'], 0, 5)] = $s;
}

$export_base = 'emploi_du_temps.php?classe=' . $classe_filter;
$import_columns = 'classe;jour;heure_debut;heure_fin;matiere;professeur';

include __DIR__ . '/../includes/header.php';
?>

<div class="section-title"><i class="fas fa-calendar-week"></i> Emploi du temps</div>
<p class="section-sub">Cliquez sur une case pour la remplir. Vous pouvez générer la grille, la copier d'une autre classe ou la vider.</p>

<?php
$export_base = $export_base;
$import_columns = $import_columns;
include __DIR__ . '/../includes/export_ui.php';
?>

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
    <div style="display:flex; gap:8px; flex-wrap:wrap;">
        <form method="POST" onsubmit="return confirm('Créer les créneaux par défaut (6 créneaux × 5 jours, Dimanche → Jeudi) pour cette classe ?');">
            <button class="btn btn-gold" name="generer" value="1"><i class="fas fa-magic"></i> Générer la grille</button>
        </form>
        <form method="POST" onsubmit="return confirm('Vider toute la grille de cette classe ?');">
            <button class="btn btn-danger" name="vider" value="1"><i class="fas fa-trash"></i> Vider</button>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-head">
        <h3><i class="fas fa-th"></i> Grille hebdomadaire — <span class="badge badge-blue"><?= e($classes[array_search($classe_filter, array_column($classes, 'id'))]['nom'] ?? '') ?></span></h3>
    </div>
    <div class="card-body" style="padding:14px;">
        <?php if (!$slots): ?>
            <div class="empty-state">
                <i class="fas fa-calendar-minus"></i>
                <p>Aucun créneau pour cette classe.</p>
                <p style="font-size:0.85rem; margin-top:8px;">Cliquez sur <strong>« Générer la grille »</strong> pour créer les créneaux par défaut, ou copiez depuis une autre classe :</p>
                <form method="POST" style="display:flex; gap:10px; justify-content:center; margin-top:14px; flex-wrap:wrap;">
                    <select name="source_classe" style="padding:10px 14px; border:1.5px solid var(--border); border-radius:9px;">
                        <?php foreach ($classes as $c): if ($c['id'] == $classe_filter) continue; ?>
                            <option value="<?= $c['id'] ?>"><?= e($c['nom']) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <button class="btn btn-primary" name="copier" value="1"><i class="fas fa-copy"></i> Copier depuis cette classe</button>
                </form>
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
                                        <td class="filled-cell" style="cursor:pointer; background:#f0f6ef;"
                                            onclick="editSeance(<?= $s['id'] ?>, '<?= $j ?>', '<?= $debut ?>', '<?= $fin ?>', <?= (int)$s['matiere_id'] ?>, <?= (int)$s['professeur_id'] ?>)">
                                            <div class="cell-content">
                                                <strong><?= e($s['matiere']) ?></strong>
                                                <span class="slot-prof"><?= $s['pprenom'] ? e($s['pprenom']) . ' ' . e($s['pnom']) : '—' ?></span>
                                            </div>
                                            <a class="slot-tag" href="?delete=<?= $s['id'] ?>&classe=<?= $classe_filter ?>"
                                               onclick="event.stopPropagation(); return confirm('Supprimer cette séance ?');">
                                                <i class="fas fa-trash"></i> supprimer
                                            </a>
                                        </td>
                                    <?php else: ?>
                                        <td class="free-cell"
                                            onclick="editSeance(0, '<?= $j ?>', '<?= $debut ?>', '<?= $fin ?>', 0, 0)">
                                            <span class="slot-tag"><i class="fas fa-plus"></i> <?= $s ? 'modifier' : 'remplir' ?></span>
                                        </td>
                                    <?php endif; ?>
                                <?php endfor; ?>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <p class="section-sub" style="margin:12px 0 0; font-size:0.85rem;"><i class="fas fa-moon"></i> Vendredi : jour de repos (aucun cours).</p>
            <div style="margin-top:12px;">
                <form method="POST" style="display:flex; gap:10px; align-items:center; flex-wrap:wrap;">
                    <label style="font-size:0.85rem; font-weight:600; color:var(--blue);">Copier depuis une autre classe :</label>
                    <select name="source_classe" style="padding:8px 12px; border:1.5px solid var(--border); border-radius:9px;">
                        <?php foreach ($classes as $c): if ($c['id'] == $classe_filter) continue; ?>
                            <option value="<?= $c['id'] ?>"><?= e($c['nom']) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <button class="btn btn-sm btn-primary" name="copier" value="1"><i class="fas fa-copy"></i> Copier</button>
                </form>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- MODAL AJOUT / ÉDITION SÉANCE -->
<div class="modal-overlay" id="modalSeance">
    <div class="modal">
        <form method="POST">
            <input type="hidden" name="id" id="s_id" value="0">
            <input type="hidden" name="classe_id" value="<?= $classe_filter ?>">
            <div class="modal-head">
                <h3 id="s_titre"><i class="fas fa-plus-circle" style="color:var(--gold)"></i> Ajouter une séance</h3>
                <button type="button" class="modal-close" data-modal-close><i class="fas fa-times"></i></button>
            </div>
            <div class="modal-body">
                <div class="form-grid">
                    <div class="form-group">
                        <label>Jour *</label>
                        <select name="jour" id="s_jour" required>
                            <?php for ($j = 1; $j <= 5; $j++): ?>
                                <option value="<?= $j ?>"><?= $jours[$j] ?></option>
                            <?php endfor; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Heure début *</label>
                        <input type="time" name="heure_debut" id="s_debut" required>
                    </div>
                    <div class="form-group">
                        <label>Heure fin *</label>
                        <input type="time" name="heure_fin" id="s_fin" required>
                    </div>
                    <div class="form-group">
                        <label>Matière</label>
                        <select name="matiere_id" id="s_matiere">
                            <option value="">— Aucune —</option>
                            <?php foreach ($matieres as $m): ?>
                                <option value="<?= $m['id'] ?>"><?= e($m['nom']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Professeur</label>
                        <select name="professeur_id" id="s_prof">
                            <option value="">— Aucun —</option>
                            <?php foreach ($professeurs as $p): ?>
                                <option value="<?= $p['id'] ?>"><?= e($p['prenom']) ?> <?= e($p['nom']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
            </div>
            <div class="modal-foot">
                <button type="button" class="btn btn-danger" data-modal-close>Annuler</button>
                <button type="submit" name="save_seance" class="btn btn-gold"><i class="fas fa-check"></i> Enregistrer</button>
            </div>
        </form>
    </div>
</div>

<script>
function editSeance(id, jour, debut, fin, matiere, prof) {
    document.getElementById('s_id').value = id;
    document.getElementById('s_jour').value = jour;
    document.getElementById('s_debut').value = debut;
    document.getElementById('s_fin').value = fin;
    document.getElementById('s_matiere').value = matiere;
    document.getElementById('s_prof').value = prof;
    document.getElementById('s_titre').innerHTML = (id ? '<i class="fas fa-edit" style="color:var(--gold)"></i> Modifier la séance' : '<i class="fas fa-plus-circle" style="color:var(--gold)"></i> Ajouter une séance');
    document.getElementById('modalSeance').classList.add('open');
}
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
