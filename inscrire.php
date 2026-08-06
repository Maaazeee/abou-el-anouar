<?php
// =====================================================
//  Enregistrement des pré-inscriptions (site public)
//  Reçoit le formulaire de pre-inscription.html via AJAX
// =====================================================

require __DIR__ . '/espace/config/config.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit(json_encode(['ok' => false, 'error' => 'Méthode non autorisée.']));
}

$nom = trim($_POST['nom'] ?? '');
$prenom = trim($_POST['prenom'] ?? '');
if ($nom === '' || $prenom === '') {
    http_response_code(400);
    exit(json_encode(['ok' => false, 'error' => 'Le nom et le prénom sont obligatoires.']));
}

$cycle = $_POST['cycleSouhaite'] ?? '';
if (!in_array($cycle, ['primaire', 'moyenne', 'secondaire'], true)) {
    $cycle = '';
}

$sante = (($_POST['sante'] ?? 'non') === 'oui')
    ? (trim($_POST['santeDetail'] ?? '') ?: 'Oui')
    : '';

q('INSERT INTO preinscriptions (nom, prenom, date_naissance, lieu_naissance, nationalite, classe_actuelle, etablissement_actuel, cycle, sante, civilite, parent_nom, parent_prenom, lien_parente, parent_tel, parent_email, parent_adresse, parent_profession)
   VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
  [$nom, $prenom,
   trim($_POST['dateNaissance'] ?? '') ?: null,
   trim($_POST['lieuNaissance'] ?? '') ?: null,
   trim($_POST['nationalite'] ?? '') ?: 'Algérienne',
   trim($_POST['classeActuelle'] ?? '') ?: null,
   trim($_POST['etablissementActuel'] ?? '') ?: null,
   $cycle,
   $sante,
   (($_POST['civilite'] ?? 'Mme') === 'M') ? 'M' : 'Mme',
   trim($_POST['parentNom'] ?? '') ?: null,
   trim($_POST['parentPrenom'] ?? '') ?: null,
   trim($_POST['lienParente'] ?? '') ?: null,
   trim($_POST['parentTel'] ?? '') ?: null,
   trim($_POST['parentEmail'] ?? '') ?: null,
   trim($_POST['parentAdresse'] ?? '') ?: null,
   trim($_POST['parentProfession'] ?? '') ?: null]);

exit(json_encode(['ok' => true]));
