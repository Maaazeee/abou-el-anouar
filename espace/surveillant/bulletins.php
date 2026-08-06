<?php
require_once __DIR__ . '/../includes/auth.php';
require_role('surveillant');
require_once __DIR__ . '/../includes/pdf.php';
require_once __DIR__ . '/../includes/moyennes.php';

$page_title = 'Bulletins';
$classes = q('SELECT id, nom FROM classes ORDER BY id')->fetchAll();
$matieres = q('SELECT id, nom FROM matieres ORDER BY id')->fetchAll();

$classe_filter = (int)($_GET['classe'] ?? ($classes[0]['id'] ?? 0));
$trimestre = (int)($_GET['trimestre'] ?? 1) ?: 1;
$classe_nom = $classes[array_search($classe_filter, array_column($classes, 'id'))]['nom'] ?? '';

$eleves = q('SELECT id, nom, prenom, date_naissance FROM eleves WHERE classe_id = ? ORDER BY nom', [$classe_filter])->fetchAll();

// Moyennes par élève / matière + moyenne générale (helper partagé)
$moy = [];
$gen = [];
foreach ($eleves as $el) {
    [$el_moy, $el_gen] = calcule_moyennes_eleve($el['id'], $trimestre);
    $moy[$el['id']] = $el_moy;
    $gen[$el['id']] = $el_gen;
}

// ---- EXPORT ----
if (isset($_GET['export'])) {
    $slug = strtolower(preg_replace('/[^a-z0-9]+/i', '_', $classe_nom));
    if ($_GET['export'] === 'csv') {
        $headers = ['eleve', 'classe', 'trimestre'];
        foreach ($matieres as $m) { $headers[] = 'moy_' . slugify($m['nom']); }
        $headers[] = 'moyenne_generale';
        $rows = [];
        foreach ($eleves as $el) {
            $row = ['eleve' => $el['prenom'] . ' ' . $el['nom'], 'classe' => $classe_nom, 'trimestre' => $trimestre];
            foreach ($matieres as $m) { $row['moy_' . slugify($m['nom'])] = $moy[$el['id']][$m['id']] ?? ''; }
            $row['moyenne_generale'] = $gen[$el['id']] ?? '';
            $rows[] = $row;
        }
        export_csv("bulletins_{$slug}_T{$trimestre}.csv", $headers, $rows);
    } else {
        $pdf = new PDF_Ecole();
        $pdf->AliasNbPages();
        $pdf->set_doc_title(pdf_str("Bulletin de la classe {$classe_nom} — $trimestre" . ($trimestre == 1 ? 'er' : 'ème') . ' trimestre'));
        foreach ($eleves as $el) {
            $pdf->AddPage();
            $pdf->SetFont('Helvetica', 'B', 12);
            $pdf->SetTextColor(27, 58, 92);
            $pdf->Cell(0, 7, pdf_str($el['prenom'] . ' ' . $el['nom']), 0, 1, 'L');
            $pdf->SetFont('Helvetica', '', 9);
            $pdf->SetTextColor(120, 130, 145);
            $pdf->Cell(0, 5, pdf_str('Classe : ' . $classe_nom . '   |   Trimestre : ' . $trimestre . ($el['date_naissance'] ? '   |   Né(e) le ' . $el['date_naissance'] : '')), 0, 1, 'L');
            $pdf->Ln(3);
            $pdf->SetFillColor(27, 58, 92);
            $pdf->SetTextColor(255, 255, 255);
            $pdf->SetFont('Helvetica', 'B', 9);
            $pdf->Cell(100, 7, pdf_str('Matière'), 1, 0, 'C', true);
            $pdf->Cell(45, 7, pdf_str('Moyenne /20'), 1, 0, 'C', true);
            $pdf->Cell(45, 7, pdf_str('Mention'), 1, 1, 'C', true);
            $pdf->SetTextColor(38, 50, 56);
            foreach ($matieres as $m) {
                $mv = $moy[$el['id']][$m['id']] ?? null;
                $pdf->SetFont('Helvetica', '', 9);
                $pdf->Cell(100, 6, pdf_str($m['nom']), 1, 0, 'L');
                $pdf->Cell(45, 6, $mv !== null ? number_format($mv, 2, '.', ' ') : pdf_str('—'), 1, 0, 'C');
                $pdf->Cell(45, 6, $mv !== null ? pdf_str(bulletin_mention($mv)) : '', 1, 1, 'C');
            }
            $g = $gen[$el['id']];
            $pdf->SetFont('Helvetica', 'B', 9);
            $pdf->SetFillColor(248, 240, 218);
            $pdf->Cell(100, 8, pdf_str('MOYENNE GÉNÉRALE'), 1, 0, 'L', true);
            $pdf->Cell(45, 8, $g !== null ? number_format($g, 2, '.', ' ') : pdf_str('—'), 1, 0, 'C', true);
            $pdf->Cell(45, 8, $g !== null ? pdf_str(bulletin_mention($g)) : '', 1, 1, 'C', true);
            $pdf->Ln(8);
            $pdf->SetFont('Helvetica', '', 9);
            $pdf->SetTextColor(120, 130, 145);
            $pdf->Cell(0, 6, pdf_str('Appréciation du surveillant :'), 0, 1, 'L');
            $pdf->Ln(12);
            $pdf->Cell(0, 6, pdf_str('Le surveillant                                             Le directeur'), 0, 1, 'L');
        }
        $pdf->Output('I', "bulletin_{$slug}_T{$trimestre}.pdf");
    }
    exit;
}

$export_base = 'bulletins.php?classe=' . $classe_filter . '&trimestre=' . $trimestre;
$import_columns = '';
$no_import = true;

include __DIR__ . '/../includes/header.php';
?>

<div class="section-title"><i class="fas fa-file-invoice"></i> Bulletins & moyennes</div>
<p class="section-sub">Moyennes par matière et générale (trimestre), et bulletin PDF imprimable par élève.</p>

<?php include __DIR__ . '/../includes/export_ui.php'; ?>

<div class="filters">
    <form method="GET" style="display:flex; gap:12px; flex-wrap:wrap; align-items:flex-end; width:100%;">
        <div class="form-group" style="flex:1; min-width:180px;">
            <label>Classe</label>
            <select name="classe" onchange="this.form.submit()">
                <?php foreach ($classes as $c): ?>
                    <option value="<?= $c['id'] ?>" <?= $c['id'] == $classe_filter ? 'selected' : '' ?>><?= e($c['nom']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group" style="min-width:120px;">
            <label>Trimestre</label>
            <select name="trimestre">
                <option value="1" <?= $trimestre === 1 ? 'selected' : '' ?>>1ᵉʳ</option>
                <option value="2" <?= $trimestre === 2 ? 'selected' : '' ?>>2ᵉ</option>
                <option value="3" <?= $trimestre === 3 ? 'selected' : '' ?>>3ᵉ</option>
            </select>
        </div>
        <button class="btn btn-primary" type="submit"><i class="fas fa-filter"></i> Afficher</button>
    </form>
</div>

<div class="card">
    <div class="card-head">
        <h3><i class="fas fa-users"></i> Moyennes — <span class="badge badge-blue"><?= e($classe_nom) ?></span>
            <span class="badge badge-gold">Trimestre <?= $trimestre ?></span></h3>
        <span class="badge badge-green"><?= count($eleves) ?> élève(s)</span>
    </div>
    <div class="table-wrap">
        <table class="data">
            <thead>
                <tr>
                    <th>Élève</th>
                    <?php foreach ($matieres as $m): ?>
                        <th title="<?= e($m['nom']) ?>"><?= e($m['nom']) ?></th>
                    <?php endforeach; ?>
                    <th>Moyenne générale</th>
                    <th>Mention</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!$eleves): ?>
                    <tr><td colspan="<?= count($matieres) + 3 ?>"><div class="empty-state"><i class="fas fa-user-graduate"></i><p>Aucun élève dans cette classe.</p></div></td></tr>
                <?php endif; ?>
                <?php foreach ($eleves as $el): ?>
                    <?php $g = $gen[$el['id']]; ?>
                    <tr>
                        <td><strong><?= e($el['prenom']) ?> <?= e($el['nom']) ?></strong></td>
                        <?php foreach ($matieres as $m): ?>
                            <?php $mv = $moy[$el['id']][$m['id']] ?? null; ?>
                            <td style="text-align:center;">
                                <?= $mv !== null ? number_format($mv, 2, ',', ' ') : '<span style="color:var(--muted)">—</span>' ?>
                            </td>
                        <?php endforeach; ?>
                        <td><strong style="color:<?= $g !== null && $g >= 10 ? 'var(--green)' : 'var(--red)' ?>"><?= $g !== null ? number_format($g, 2, ',', ' ') : '—' ?></strong></td>
                        <td><?= $g !== null ? '<span class="badge ' . ($g >= 10 ? 'badge-green' : 'badge-red') . '">' . e(bulletin_mention($g)) . '</span>' : '<span class="badge badge-gray">Aucune note</span>' ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
