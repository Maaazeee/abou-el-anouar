<?php
// =====================================================
//  Configuration de la base de données
//  École Privée Abou el Anouar - Espace Surveillant
// =====================================================

define('DB_HOST', 'localhost');
define('DB_NAME', 'ecole_abou_anouar');
define('DB_USER', 'root');
define('DB_PASS', ''); // Par défaut XAMPP/WAMP : mot de passe vide

// Session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Connexion PDO
function db() {
    static $pdo = null;
    if ($pdo === null) {
        try {
            $pdo = new PDO(
                'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4',
                DB_USER,
                DB_PASS,
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                ]
            );
        } catch (PDOException $e) {
            die('Erreur de connexion à la base de données : ' . $e->getMessage());
        }
    }
    return $pdo;
}

// Chemins
define('BASE_URL', str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'])) . '/../');

function url($path = '') {
    return BASE_URL . ltrim($path, '/');
}

// Messages flash
function set_flash($type, $message) {
    $_SESSION['flash'][] = ['type' => $type, 'message' => $message];
}

function get_flash() {
    if (!empty($_SESSION['flash'])) {
        $flashes = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $flashes;
    }
    return [];
}

// Requête helper
function q($sql, $params = []) {
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    return $stmt;
}

// Sécurité
function e($text) {
    return htmlspecialchars($text ?? '', ENT_QUOTES, 'UTF-8');
}

// Utilisateur courant
function current_user() {
    return $_SESSION['user'] ?? null;
}

// ---------------------------------------------------------
//  EXPORT / IMPORT CSV
// ---------------------------------------------------------

// Télécharge un fichier CSV (séparateur ; compatible Excel FR)
function export_csv($filename, $headers, $rows) {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    $out = fopen('php://output', 'w');
    fwrite($out, "\xEF\xBB\xBF"); // BOM UTF-8
    fputcsv($out, $headers, ';');
    foreach ($rows as $r) {
        fputcsv($out, array_values($r), ';');
    }
    fclose($out);
    exit;
}

// Lit un CSV téléversé et renvoie un tableau associatif
function parse_csv($tmp_path) {
    $rows = [];
    $headers = null;
    $fp = fopen($tmp_path, 'r');
    while (($line = fgetcsv($fp, 0, ';')) !== false) {
        if ($headers === null) {
            $headers = array_map('trim', $line);
            $headers[0] = preg_replace('/^\xEF\xBB\xBF/', '', $headers[0]); // BOM
            continue;
        }
        $rows[] = array_combine($headers, array_pad($line, count($headers), ''));
    }
    fclose($fp);
    return $rows;
}

// Retourne l'id d'un enregistrement par valeur, ou null
function lookup_id($table, $col, $val) {
    $val = trim($val ?? '');
    if ($val === '') return null;
    $row = q("SELECT id FROM `$table` WHERE `$col` = ? LIMIT 1", [$val])->fetch();
    return $row ? $row['id'] : null;
}

// Retrouve un professeur par "Prénom Nom" ou "Nom Prénom"
function lookup_prof_id($value) {
    $value = trim($value ?? '');
    if ($value === '') return null;
    $parts = preg_split('/\s+/', $value);
    if (count($parts) >= 2) {
        $nom = end($parts);
        $prenom = implode(' ', array_slice($parts, 0, -1));
        $row = q("SELECT id FROM professeurs WHERE (prenom = ? AND nom = ?) OR (prenom = ? AND nom = ?) LIMIT 1",
                 [$prenom, $nom, $nom, $prenom])->fetch();
        if ($row) return $row['id'];
    }
    $row = q("SELECT id FROM professeurs WHERE nom = ? LIMIT 1", [$value])->fetch();
    return $row ? $row['id'] : null;
}

// Nom du mois pour l'entête des PDF
function pdf_month($date) {
    $m = ['', 'Janvier', 'Février', 'Mars', 'Avril', 'Mai', 'Juin',
          'Juillet', 'Août', 'Septembre', 'Octobre', 'Novembre', 'Décembre'];
    $ts = strtotime($date);
    return $m[(int)date('n', $ts)] . ' ' . date('Y', $ts);
}

// Chaîne sécurisée pour les noms de colonnes (export CSV)
function slugify($text) {
    $text = strtolower(iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $text));
    $text = preg_replace('/[^a-z0-9]+/', '_', $text);
    return trim($text, '_');
}
