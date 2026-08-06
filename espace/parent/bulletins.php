<?php
require_once __DIR__ . '/../includes/parent.php';
require_once __DIR__ . '/../includes/pdf.php';
require_once __DIR__ . '/../includes/moyennes.php';

$page_title = 'Bulletin';
$enfants = parent_enfants();
$enfant = parent_enfant_selectionne($enfants);
$trimestre = (int)($_GET['trimestre'] ?? 1) ?: 1;
$matieres = q('SELECT id, nom FROM matieres ORDER BY id')->fetchAll();

$moy = [];
$gen = null;
if ($enfant) {
    [$moy, $gen] = calcule_moyennes_eleve($enfant['id'], $trimestre);
}

if (isset($_GET['export']) && $enfant) {
    $slug = strtolower(preg_replace('/[^a-z0-9]+/i', '_', $enfant['prenom'] . '_' . $enfant['nom']));
    if ($_GET['export'] === 'csv') {
        $headers = ['matiere', 'moyenne', 'mention'];
        $rows = [];
        foreach ($matieres as $m) {
            if (isset($moy[$m['id']]) && $moy[$m['id']] !== null) {
                $rows[] = ['matiere' => $m['nom'], 'moyenne' => number_format($moy[$m['id']], 2, ',', ' '), 'mention' => bulletin_mention($moy[$m['id']])];
            }
        }
        $rows[] = ['matiere' => 'MOYENNE GENERALE', 'moyenne' => $gen !== null ? number_format($gen, 2, ',', ' ') : '', 'mention' => $gen !== null ? bulletin_mention($gen) : ''];
        export_csv("bulletin_{$slug}_T{$trimestre}.csv", $headers, $rows);
    } else {
        $pdf = new PDF_Ecole();
        $pdf->AliasNbPages();
        $pdf->set_doc_title(pdf_str('Bulletin scolaire — ' . $enfant['prenom'] . ' ' . $enfant['nom'] . ' — Trimestre ' . $trimestre));
        $pdf->AddPage();
        $pdf->SetFont('Helvetica', 'B', 12);
        $pdf->SetTextColor(27, 58, 92);
        $pdf->Cell(0, 7, pdf_str($enfant['prenom'] . ' ' . $enfant['nom']), 0, 1, 'L');
        $pdf->SetFont('Helvetica', '', 9);
        $pdf->SetTextColor(120, 130, 145);
        $pdf->Cell(0, 5, pdf_str('Classe : ' . ($enfant['classe'] ?? '—') . '   |   Trimestre : ' . $trimestre . ($enfant['date_naissance'] ? '   |   Né(e) le ' . $enfant['date_naissance'] : '')), 0, 1, 'L');
        $pdf->Ln(3);
        $pdf->SetFillColor(27, 58, 92);
        $pdf->SetTextColor(255, 255, 255);
        $pdf->SetFont('Helvetica', 'B', 9);
        $pdf->Cell(100, 7, pdf_str('Matière'), 1, 0, 'C', true);
        $pdf->Cell(45, 7, pdf_str('Moyenne /20'), 1, 0, 'C', true);
        $pdf->Cell(45, 7, pdf_str('Mention'), 1, 1, 'C', true);
        $pdf->SetTextColor(38, 50, 56);
        foreach ($matieres as $m) {
            $mv = $moy[$m['id']] ?? null;
            $pdf->SetFont('Helvetica', '', 9);
            $pdf->Cell(100, 6, pdf_str($m['nom']), 1, 0, 'L');
            $pdf->Cell(45, 6, $mv !== null ? number_format($mv, 2, '.', ' ') : pdf_str('—'), 1, 0, 'C');
            $pdf->Cell(45, 6, $mv !== null ? pdf_str(bulletin_mention($mv)) : '', 1, 1, 'C');
        }
        $pdf->SetFont('Helvetica', 'B', 9);
        $pdf->SetFillColor(248, 240, 218);
        $pdf->Cell(100, 8, pdf_str('MOYENNE GÉNÉRALE'), 1, 0, 'L', true);
        $pdf->Cell(45, 8, $gen !== null ? number_format($gen, 2, '.', ' ') : pdf_str('—'), 1, 0, 'C', true);
        $pdf->Cell(45, 8, $gen !== null ? pdf_str(bulletin_mention($gen)) : '', 1, 1, 'C', true);
        $pdf->Ln(8);
        $pdf->SetFont('Helvetica', '', 9);
        $pdf->SetTextColor(120, 130, 145);
        $pdf->Cell(0, 6, pdf_str('Appréciation du surveillant :'), 0, 1, 'L');
        $pdf->Ln(12);
        $pdf->Cell(0, 6, pdf_str('Le surveillant                                             Le directeur'), 0, 1, 'L');
        $pdf->Output('I', "bulletin_{$slug}_T{$trimestre}.pdf");
    }
    exit;
}

include __DIR__ . '/../includes/header_parent.php';
?>

<div class="section-title"><i class="fas fa-file-invoice"></i> Bulletin de l'élève</div>
<p class="section-sub">Moyennes par matière et générale, avec mention et bulletin PDF imprimable.</p>

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
    <?php $export_base = 'bulletins.php?enfant=' . $enfant['id'] . '&trimestre=' . $trimestre; $no_import = true; include __DIR__ . '/../includes/export_ui.php'; ?>

    <div class="card">
        <div class="card-head">
            <h3><i class="fas fa-users"></i> Bulletin — <span class="badge badge-blue"><?= e($enfant['prenom']) ?> <?= e($enfant['nom']) ?></span>
                <span class="badge badge-blue"><?= e($enfant['classe'] ?: '—') ?></span>
                <span class="badge badge-gold">Trimestre <?= $trimestre ?></span></h3>
        </div>
        <div class="table-wrap">
            <table class="data">
                <thead>
                    <tr><th>Matière</th><th>Moyenne /20</th><th>Mention</th></tr>
                </thead>
                <tbody>
                    <?php $has = false; foreach ($matieres as $m): ?>
                        <?php if (isset($moy[$m['id']]) && $moy[$m['id']] !== null): $has = true; ?>
                            <tr>
                                <td><?= e($m['nom']) ?></td>
                                <td><strong style="color:<?= $moy[$m['id']] >= 10 ? 'var(--green)' : 'var(--red)' ?>"><?= number_format($moy[$m['id']], 2, ',', ' ') ?></strong></td>
                                <td><span class="badge <?= $moy[$m['id']] >= 10 ? 'badge-green' : 'badge-red' ?>"><?= e(bulletin_mention($moy[$m['id']])) ?></span></td>
                            </tr>
                        <?php endif; ?>
                    <?php endforeach; ?>
                    <?php if (!$has): ?>
                        <tr><td colspan="3"><div class="empty-state"><i class="fas fa-file-invoice"></i><p>Aucune note pour ce trimestre.</p></div></td></tr>
                    <?php endif; ?>
                    <tr>
                        <td><strong>Moyenne générale</strong></td>
                        <td><strong style="color:<?= $gen !== null && $gen >= 10 ? 'var(--green)' : 'var(--red)' ?>"><?= $gen !== null ? number_format($gen, 2, ',', ' ') : '—' ?></strong></td>
                        <td><?= $gen !== null ? '<span class="badge ' . ($gen >= 10 ? 'badge-green' : 'badge-red') . '">' . e(bulletin_mention($gen)) . '</span>' : '<span class="badge badge-gray">Aucune note</span>' ?></td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
<?php endif; ?>

<?php include __DIR__ . '/../includes/footer_parent.php'; ?>
