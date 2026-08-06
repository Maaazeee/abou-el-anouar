<?php
// =====================================================
//  BARRE EXPORT / IMPORT (CSV + PDF)
//  Variables attendues : $export_base, $import_columns
// =====================================================
$export_base = $export_base ?? '';
$import_columns = $import_columns ?? '';
$no_import = $no_import ?? false;
?>
<div class="export-bar no-print">
    <div class="export-actions">
        <a class="btn btn-primary btn-sm" href="<?= e($export_base) ?>&export=csv" title="Télécharger en CSV"><i class="fas fa-file-csv"></i> CSV</a>
        <a class="btn btn-primary btn-sm" href="<?= e($export_base) ?>&export=pdf" title="Télécharger en PDF"><i class="fas fa-file-pdf"></i> PDF</a>
        <?php if (!$no_import): ?>
        <button class="btn btn-gold btn-sm" data-modal-open="modalImport"><i class="fas fa-file-import"></i> Importer CSV</button>
        <?php endif; ?>
    </div>
</div>

<div class="modal-overlay" id="modalImport">
    <div class="modal">
        <form method="POST" enctype="multipart/form-data">
            <div class="modal-head">
                <h3><i class="fas fa-file-import" style="color:var(--gold)"></i> Importer depuis un fichier CSV</h3>
                <button type="button" class="modal-close" data-modal-close><i class="fas fa-times"></i></button>
            </div>
            <div class="modal-body">
                <p class="import-help">Séparateur <strong>;</strong> (point-virgule) — première ligne = entêtes de colonnes.</p>
                <p class="import-help">Colonnes attendues : <code><?= e($import_columns) ?></code></p>
                <div class="form-group" style="margin-top:16px;">
                    <label>Fichier CSV</label>
                    <input type="file" name="import_csv" accept=".csv,text/csv" required>
                </div>
            </div>
            <div class="modal-foot">
                <button type="button" class="btn btn-danger" data-modal-close>Annuler</button>
                <button type="submit" name="do_import" class="btn btn-gold"><i class="fas fa-upload"></i> Importer</button>
            </div>
        </form>
    </div>
</div>
