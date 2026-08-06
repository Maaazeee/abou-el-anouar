<?php
// =====================================================
//  Espace Parent — authentification + enfants liés
//  Lien parent → élève(s) : users.email = eleves.email_parent
// =====================================================

require_once __DIR__ . '/auth.php';
require_role('parent');

// Liste des enfants du parent connecté
function parent_enfants() {
    $u = current_user();
    $email = trim($u['email'] ?? '');
    if ($email === '') return [];
    return q('SELECT e.*, c.nom AS classe, c.cycle
              FROM eleves e
              LEFT JOIN classes c ON c.id = e.classe_id
              WHERE e.email_parent = ?
              ORDER BY e.nom, e.prenom', [$email])->fetchAll();
}

// Enfant sélectionné (via ?enfant=) ou premier enfant, sinon null
function parent_enfant_selectionne($enfants) {
    if (!$enfants) return null;
    $id = (int)($_GET['enfant'] ?? 0);
    foreach ($enfants as $e) {
        if ($e['id'] === $id) return $e;
    }
    return $enfants[0];
}
