<?php
// =====================================================
//  Calcul des moyennes — partagé Surveillant / Parent
// =====================================================

require_once __DIR__ . '/../config/config.php';

// Mention selon la moyenne /20
function bulletin_mention($m) {
    if ($m >= 16) return 'Très bien';
    if ($m >= 14) return 'Bien';
    if ($m >= 12) return 'Assez bien';
    if ($m >= 10) return 'Passable';
    return 'Insuffisant';
}

// Retourne [ $moyennesParMatiere (id => moyenne), $moyenneGenerale ]
// Moyennes pondérées par le coefficient de chaque évaluation.
function calcule_moyennes_eleve($eleve_id, $trimestre) {
    $notes = q('SELECT matiere_id, note, coefficient FROM notes WHERE eleve_id = ? AND trimestre = ?',
               [(int)$eleve_id, (int)$trimestre])->fetchAll();
    $moy = [];
    foreach ($notes as $n) {
        $mi = (int)$n['matiere_id'];
        if (!isset($moy[$mi])) $moy[$mi] = ['sum' => 0.0, 'coef' => 0.0];
        $moy[$mi]['sum'] += (float)$n['note'] * (float)$n['coefficient'];
        $moy[$mi]['coef'] += (float)$n['coefficient'];
    }
    $vals = [];
    foreach ($moy as $mi => $agg) {
        $moy[$mi] = $agg['coef'] > 0 ? round($agg['sum'] / $agg['coef'], 2) : null;
        if ($moy[$mi] !== null) $vals[] = $moy[$mi];
    }
    $gen = $vals ? round(array_sum($vals) / count($vals), 2) : null;
    return [$moy, $gen];
}
