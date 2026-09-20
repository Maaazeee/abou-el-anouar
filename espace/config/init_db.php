<?php
// =====================================================
//  INITIALISATION DE LA BASE DE DONNÉES (PostgreSQL)
//  À exécuter UNE SEULE FOIS :
//   - En local : http://localhost/.../espace/config/init_db.php?cle=abou-anouar-2026
//   - Sur Render : lancé automatiquement par le job d'initialisation (DATABASE_URL)
// =====================================================

error_reporting(E_ALL);
ini_set('display_errors', 1);

echo '<meta charset="UTF-8"><pre style="font-family:Consolas;background:#122a45;color:#e0c46a;padding:20px;border-radius:8px;">';

// --- Garde de sécurité : clé obligatoire pour réinitialiser la base ---
// Hors Render uniquement ; sur Render la clé n'est pas demandée (DATABASE_URL fourni)
// En CLI : php init_db.php abou-anouar-2026
$is_render = (bool)getenv('DATABASE_URL') || getenv('RENDER') === 'true';
$cle_cli = $argv[1] ?? '';
if (!$is_render && ($_GET['cle'] ?? '') !== 'abou-anouar-2026' && $cle_cli !== 'abou-anouar-2026') {
    http_response_code(403);
    exit("Accès refusé.\n\nPour (ré)initialiser la base en local, utilisez :\n  espace/config/init_db.php?cle=abou-anouar-2026\n  ou : php init_db.php abou-anouar-2026\n\nEn production : supprimez ce fichier après installation.");
}

// --- Connexion ---
function init_db_info() {
    $url = getenv('DATABASE_URL');
    if ($url) {
        $p = parse_url($url);
        return [
            'host' => $p['host'] ?? 'localhost',
            'port' => (int)($p['port'] ?? 5432),
            'db'   => ltrim($p['path'] ?? '', '/'),
            'user' => rawurldecode($p['user'] ?? 'postgres'),
            'pass' => rawurldecode($p['pass'] ?? ''),
        ];
    }
    return [
        'host' => getenv('PGHOST') ?: 'localhost',
        'port' => (int)(getenv('PGPORT') ?: 5432),
        'db'   => getenv('PGDATABASE') ?: 'ecole_abou_anouar',
        'user' => getenv('PGUSER') ?: 'postgres',
        'pass' => getenv('PGPASSWORD') ?: '',
    ];
}

$info = init_db_info();
$dsn = 'pgsql:host=' . $info['host'] . ';port=' . $info['port'];

try {
    $pdo = new PDO("$dsn;dbname=" . $info['db'], $info['user'], $info['pass']);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $created = false;
} catch (PDOException $e) {
    // La base n'existe pas encore (cas local) : on la crée via la base maintenance "postgres"
    try {
        $adb = new PDO("$dsn;dbname=postgres", $info['user'], $info['pass']);
        $adb->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $adb->exec('CREATE DATABASE "' . $info['db'] . '"');
        $pdo = new PDO("$dsn;dbname=" . $info['db'], $info['user'], $info['pass']);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $created = true;
    } catch (PDOException $e2) {
        die("Impossible de se connecter à PostgreSQL : " . $e2->getMessage() . "\n\nVérifiez que PostgreSQL est démarré et que DATABASE_URL / PG* sont correctement configurés (Render).");
    }
}
echo ($created ? "✔ Base de données '" . $info['db'] . "' créée\n" : "✔ Connexion à la base '" . $info['db'] . "'\n");

// --- Réinitialisation (optionnelle) ---
// Mode "force" : on reconstruit entièrement le schéma (DROP + CREATE).
//   - Sur Render : variable d'environnement INIT_FORCE=1
//   - En local     : init_db.php?cle=abou-anouar-2026&force=1
$force = (getenv('INIT_FORCE') === '1') || (($_GET['force'] ?? '') === '1');
if ($force) {
    $pdo->exec('DROP SCHEMA IF EXISTS public CASCADE');
    $pdo->exec('CREATE SCHEMA public');
    echo "✔ Schéma public réinitialisé (mode force)\n";
}

// --- Tables ---
$sql = [];

$sql[] = "CREATE TABLE users (
    id SERIAL PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    role VARCHAR(20) NOT NULL CHECK (role IN ('admin','surveillant','parent','professeur')),
    nom VARCHAR(100),
    prenom VARCHAR(100),
    email VARCHAR(150),
    telephone VARCHAR(30),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)";

$sql[] = "CREATE TABLE classes (
    id SERIAL PRIMARY KEY,
    nom VARCHAR(50) NOT NULL,
    cycle VARCHAR(20) NOT NULL CHECK (cycle IN ('primaire','moyenne','secondaire')),
    annee VARCHAR(20)
)";

$sql[] = "CREATE TABLE matieres (
    id SERIAL PRIMARY KEY,
    nom VARCHAR(100) NOT NULL
)";

$sql[] = "CREATE TABLE professeurs (
    id SERIAL PRIMARY KEY,
    nom VARCHAR(100) NOT NULL,
    prenom VARCHAR(100) NOT NULL,
    email VARCHAR(150),
    adresse TEXT,
    telephone VARCHAR(30),
    matiere_id INT,
    prix_heure DECIMAL(8,2) DEFAULT 0,
    total_heures DECIMAL(8,2) DEFAULT 0,
    FOREIGN KEY (matiere_id) REFERENCES matieres(id) ON DELETE SET NULL
)";

$sql[] = "CREATE TABLE eleves (
    id SERIAL PRIMARY KEY,
    nom VARCHAR(100) NOT NULL,
    prenom VARCHAR(100) NOT NULL,
    date_naissance DATE,
    lieu_naissance VARCHAR(100),
    nationalite VARCHAR(50) DEFAULT 'Algérienne',
    sexe VARCHAR(10) DEFAULT 'M' CHECK (sexe IN ('M','F')),
    classe_id INT,
    adresse TEXT,
    telephone_parent VARCHAR(30),
    email_parent VARCHAR(150),
    date_inscription DATE,
    FOREIGN KEY (classe_id) REFERENCES classes(id) ON DELETE SET NULL
)";

$sql[] = "CREATE TABLE emploi_du_temps (
    id SERIAL PRIMARY KEY,
    classe_id INT NOT NULL,
    jour SMALLINT NOT NULL,
    heure_debut TIME NOT NULL,
    heure_fin TIME NOT NULL,
    matiere_id INT,
    professeur_id INT,
    FOREIGN KEY (classe_id) REFERENCES classes(id) ON DELETE CASCADE,
    FOREIGN KEY (matiere_id) REFERENCES matieres(id) ON DELETE SET NULL,
    FOREIGN KEY (professeur_id) REFERENCES professeurs(id) ON DELETE SET NULL
)";

$sql[] = "CREATE TABLE absences_profs (
    id SERIAL PRIMARY KEY,
    professeur_id INT NOT NULL,
    date_absence DATE NOT NULL,
    motif VARCHAR(255),
    justifiee SMALLINT DEFAULT 0,
    FOREIGN KEY (professeur_id) REFERENCES professeurs(id) ON DELETE CASCADE
)";

$sql[] = "CREATE TABLE absences_eleves (
    id SERIAL PRIMARY KEY,
    eleve_id INT NOT NULL,
    date_absence DATE NOT NULL,
    motif VARCHAR(255),
    justifiee SMALLINT DEFAULT 0,
    FOREIGN KEY (eleve_id) REFERENCES eleves(id) ON DELETE CASCADE
)";

$sql[] = "CREATE TABLE notes (
    id SERIAL PRIMARY KEY,
    eleve_id INT NOT NULL,
    matiere_id INT NOT NULL,
    type_note VARCHAR(10) NOT NULL CHECK (type_note IN ('controle','examen')),
    note DECIMAL(4,2) NOT NULL,
    coefficient DECIMAL(3,1) DEFAULT 1,
    date_note DATE,
    trimestre SMALLINT DEFAULT 1,
    FOREIGN KEY (eleve_id) REFERENCES eleves(id) ON DELETE CASCADE,
    FOREIGN KEY (matiere_id) REFERENCES matieres(id) ON DELETE CASCADE
)";

$sql[] = "CREATE TABLE emploi_examens (
    id SERIAL PRIMARY KEY,
    classe_id INT NOT NULL,
    matiere_id INT,
    date_examen DATE NOT NULL,
    heure_debut TIME NOT NULL,
    heure_fin TIME NOT NULL,
    salle VARCHAR(50),
    FOREIGN KEY (classe_id) REFERENCES classes(id) ON DELETE CASCADE,
    FOREIGN KEY (matiere_id) REFERENCES matieres(id) ON DELETE SET NULL
)";

$sql[] = "CREATE TABLE filieres (
    id SERIAL PRIMARY KEY,
    nom VARCHAR(100) NOT NULL
)";

$sql[] = "CREATE TABLE matiere_filiere (
    matiere_id INT NOT NULL,
    filiere_id INT NOT NULL DEFAULT 0,
    coefficient DECIMAL(3,1) NOT NULL DEFAULT 1,
    PRIMARY KEY (matiere_id, filiere_id),
    FOREIGN KEY (matiere_id) REFERENCES matieres(id) ON DELETE CASCADE
)";

$sql[] = "CREATE TABLE preinscriptions (
    id SERIAL PRIMARY KEY,
    nom VARCHAR(100) NOT NULL,
    prenom VARCHAR(100) NOT NULL,
    date_naissance DATE,
    lieu_naissance VARCHAR(100),
    nationalite VARCHAR(50) DEFAULT 'Algérienne',
    classe_actuelle VARCHAR(100),
    etablissement_actuel VARCHAR(100),
    cycle VARCHAR(20),
    sante VARCHAR(255),
    civilite VARCHAR(10),
    parent_nom VARCHAR(100),
    parent_prenom VARCHAR(100),
    lien_parente VARCHAR(100),
    parent_tel VARCHAR(30),
    parent_email VARCHAR(150),
    parent_adresse TEXT,
    parent_profession VARCHAR(100),
    statut VARCHAR(10) DEFAULT 'nouveau' CHECK (statut IN ('nouveau','validee','refusee')),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)";

$sql[] = "CREATE TABLE mots_carnet (
    id SERIAL PRIMARY KEY,
    eleve_id INT NOT NULL,
    message TEXT NOT NULL,
    lu SMALLINT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (eleve_id) REFERENCES eleves(id) ON DELETE CASCADE
)";

foreach ($sql as $create) {
    // Idempotent : ne rien faire si la table existe déjà
    $pdo->exec(str_replace('CREATE TABLE ', 'CREATE TABLE IF NOT EXISTS ', $create));
}
echo "✔ Tables créées (users, classes, matieres, professeurs, eleves, emploi_du_temps, absences_profs, absences_eleves, notes, emploi_examens, filieres, matiere_filiere, preinscriptions, mots_carnet)\n";

// --- Données de départ (uniquement si la base est vide) ---
$nb_users = (int)$pdo->query('SELECT COUNT(*) FROM users')->fetchColumn();
if ($nb_users > 0) {
    echo "\n⚠ Données déjà présentes dans la base — aucun seed réinséré (réinitialisation complète possible avec INIT_FORCE=1 ou ?force=1).\n";
    echo '</pre>';
    exit;
}

echo "✔ Base vide → insertion des données de démonstration\n";

// Utilisateurs
$pdo->exec("INSERT INTO users (username, password, role, nom, prenom, email) VALUES
    ('admin', '" . password_hash('admin123', PASSWORD_DEFAULT) . "', 'admin', 'Admin', 'Système', 'admin@abouelanouar.dz'),
    ('surveillant', '" . password_hash('surveillant123', PASSWORD_DEFAULT) . "', 'surveillant', 'Surveillant', 'Principal', 'surveillant@abouelanouar.dz'),
    ('parent', '" . password_hash('parent123', PASSWORD_DEFAULT) . "', 'parent', 'AMRANI', 'Karim', 'parent1@gmail.com')
");
echo "✔ Comptes créés (admin/admin123, surveillant/surveillant123 et parent/parent123)\n";

// Classes
$pdo->exec("INSERT INTO classes (nom, cycle, annee) VALUES
    ('1AP', 'primaire', '2026-2027'), ('2AP', 'primaire', '2026-2027'), ('3AP', 'primaire', '2026-2027'),
    ('4AP', 'primaire', '2026-2027'), ('5AP', 'primaire', '2026-2027'),
    ('1AM', 'moyenne', '2026-2027'), ('2AM', 'moyenne', '2026-2027'), ('3AM', 'moyenne', '2026-2027'), ('4AM', 'moyenne', '2026-2027'),
    ('1AS', 'secondaire', '2026-2027'), ('2AS', 'secondaire', '2026-2027'), ('3AS', 'secondaire', '2026-2027')
");
echo "✔ 12 classes créées (primaire 1AP-5AP, moyenne, secondaire)\n";

// Matières
$pdo->exec("INSERT INTO matieres (nom) VALUES
    ('Mathématiques'), ('Langue Arabe'), ('Langue Française'), ('Langue Anglaise'),
    ('Sciences Physiques'), ('Sciences Naturelles'), ('Histoire-Géographie'),
    ('Éducation Islamique'), ('Informatique'), ('Éducation Physique'),
    ('Philosophie'), ('Sciences Économiques'), ('Amazigh'), ('Éducation Artistique')
");
echo "✔ 14 matières créées\n";

// Professeurs (exemples)
$pdo->exec("INSERT INTO professeurs (nom, prenom, email, adresse, telephone, matiere_id, prix_heure, total_heures) VALUES
    ('BENALI', 'Karim', 'k.benali@abouelanouar.dz', 'Aïn Benian, Alger', '0551234567', 1, 1200.00, 20),
    ('HADJ', 'Amel', 'a.hadj@abouelanouar.dz', 'Bologhine, Alger', '0662345678', 2, 1100.00, 18),
    ('CHERIF', 'Yasmine', 'y.cherif@abouelanouar.dz', 'Chéraga, Alger', '0773456789', 3, 1150.00, 22),
    ('MEZIANE', 'Sofiane', 's.meziane@abouelanouar.dz', 'Dely Ibrahim, Alger', '0554567890', 4, 1150.00, 15),
    ('BOUKERMA', 'Nadia', 'n.boukerma@abouelanouar.dz', 'Aïn Benian, Alger', '0665678901', 5, 1300.00, 16),
    ('SAADI', 'Lyes', 'l.saadi@abouelanouar.dz', 'Baba Hassen, Alger', '0776789012', 6, 1300.00, 14)
");
echo "✔ 6 professeurs d'exemple créés\n";

// Filières (lycée uniquement - avant le lycée : une seule filière "Générale")
$pdo->exec("INSERT INTO filieres (nom) VALUES
    ('Mathématiques'), ('Sciences Expérimentales'), ('Lettres & Langues Étrangères'),
    ('Gestion & Économie'), ('Philosophie')
");
echo "✔ 5 filières créées (lycée 2AS/3AS)\n";

// Coefficients "Générale" (avant le lycée : primaire & moyenne, une seule filière) - filiere_id = 0
$pdo->exec("INSERT INTO matiere_filiere (matiere_id, filiere_id, coefficient) VALUES
    (1, 0, 5), (2, 0, 4), (3, 0, 3), (4, 0, 2), (5, 0, 2), (6, 0, 2),
    (7, 0, 2), (8, 0, 2), (9, 0, 1), (10, 0, 1), (13, 0, 1), (14, 0, 1)
");
echo "✔ Coefficients « Générale » créés (avant le lycée)\n";

// Coefficients par filière (lycée)
$pdo->exec("INSERT INTO matiere_filiere (matiere_id, filiere_id, coefficient) VALUES
    (1, 1, 7), (1, 2, 4), (1, 3, 3), (1, 4, 3), (1, 5, 3),
    (2, 1, 3), (2, 2, 3), (2, 3, 4), (2, 4, 3), (2, 5, 4),
    (3, 1, 2), (3, 2, 2), (3, 3, 5), (3, 4, 2), (3, 5, 2),
    (4, 1, 2), (4, 2, 2), (4, 3, 4), (4, 4, 2), (4, 5, 2),
    (5, 1, 3), (5, 2, 5), (5, 3, 2), (5, 4, 2), (5, 5, 2),
    (11, 1, 2), (11, 2, 2), (11, 3, 2), (11, 4, 2), (11, 5, 6)
");
echo "✔ Coefficients par filière créés (lycée)\n";

// Élèves (exemples - classe 1AM = id 6)
$pdo->exec("INSERT INTO eleves (nom, prenom, date_naissance, lieu_naissance, nationalite, sexe, classe_id, adresse, telephone_parent, email_parent, date_inscription) VALUES
    ('AMRANI', 'Mohamed', '2014-03-12', 'Alger', 'Algérienne', 'M', 6, 'Aïn Benian, Alger', '0551112233', 'parent1@gmail.com', '2025-09-01'),
    ('BENSAID', 'Lina', '2013-11-25', 'Alger', 'Algérienne', 'F', 6, 'Raïs Hamidou, Alger', '0662223344', 'parent2@gmail.com', '2025-09-01'),
    ('DJELLOUL', 'Yacine', '2014-07-08', 'Oran', 'Algérienne', 'M', 6, 'Aïn Benian, Alger', '0773334455', 'parent3@gmail.com', '2025-09-01'),
    ('FERHAT', 'Sara', '2013-05-19', 'Alger', 'Algérienne', 'F', 6, 'Bologhine, Alger', '0554445566', 'parent4@gmail.com', '2025-09-01'),
    ('GUERROUI', 'Anis', '2014-01-30', 'Blida', 'Algérienne', 'M', 6, 'Chéraga, Alger', '0665556677', 'parent5@gmail.com', '2025-09-01'),
    ('HADDAD', 'Meriem', '2013-09-14', 'Alger', 'Algérienne', 'F', 6, 'Dely Ibrahim, Alger', '0776667788', 'parent6@gmail.com', '2025-09-01'),
    ('LAZREG', 'Omar', '2014-02-02', 'Alger', 'Algérienne', 'M', 7, 'Aïn Benian, Alger', '0557778899', 'parent7@gmail.com', '2025-09-01'),
    ('MERABET', 'Yousra', '2013-12-10', 'Alger', 'Algérienne', 'F', 7, 'Baba Hassen, Alger', '0668889900', 'parent8@gmail.com', '2025-09-01')
");
echo "✔ 8 élèves d'exemple créés\n";

// Emploi du temps (exemple classe 1AM = id 6 - Dimanche=1 à Jeudi=5, vendredi = repos)
$pdo->exec("INSERT INTO emploi_du_temps (classe_id, jour, heure_debut, heure_fin, matiere_id, professeur_id) VALUES
    (6, 1, '08:00', '09:00', 1, 1),
    (6, 1, '09:00', '10:00', 2, 2),
    (6, 1, '10:15', '11:15', 4, 4),
    (6, 2, '08:00', '09:00', 3, 3),
    (6, 2, '09:00', '10:00', 5, 5),
    (6, 2, '10:15', '11:15', 2, 2),
    (6, 3, '08:00', '09:00', 6, 6),
    (6, 3, '09:00', '10:00', 1, 1),
    (6, 4, '10:15', '11:15', 4, 4),
    (6, 4, '08:00', '09:00', 3, 3)
");
echo "✔ Emploi du temps d'exemple créé (classe 1AM)\n";

// Absences professeurs
$pdo->exec("INSERT INTO absences_profs (professeur_id, date_absence, motif, justifiee) VALUES
    (1, '2026-09-15', 'Maladie', 1),
    (3, '2026-09-22', 'Raison personnelle', 0)
");
echo "✔ Absences professeurs d'exemple créées\n";

// Absences élèves
$pdo->exec("INSERT INTO absences_eleves (eleve_id, date_absence, motif, justifiee) VALUES
    (1, '2026-09-14', 'Maladie', 1),
    (1, '2026-10-05', '', 0),
    (2, '2026-09-21', 'Rendez-vous médical', 1),
    (3, '2026-09-16', '', 0),
    (3, '2026-10-12', 'Maladie', 1),
    (4, '2026-09-28', 'Convoyage familial', 0),
    (5, '2026-09-20', 'Rendez-vous médical', 1),
    (5, '2026-10-19', '', 0),
    (6, '2026-10-01', 'Maladie', 1),
    (7, '2026-09-23', '', 0),
    (7, '2026-10-08', 'Maladie', 1),
    (8, '2026-10-15', 'Raison familiale', 0)
");
echo "✔ 12 absences élèves d'exemple créées\n";

// Notes
$pdo->exec("INSERT INTO notes (eleve_id, matiere_id, type_note, note, coefficient, date_note, trimestre) VALUES
    (1, 1, 'controle', 14.50, 1, '2026-10-05', 1),
    (1, 1, 'examen', 16.00, 2, '2026-10-30', 1),
    (2, 1, 'controle', 12.00, 1, '2026-10-05', 1),
    (2, 1, 'examen', 13.50, 2, '2026-10-30', 1),
    (3, 1, 'controle', 17.00, 1, '2026-10-05', 1),
    (3, 1, 'examen', 18.00, 2, '2026-10-30', 1),
    (1, 2, 'controle', 15.00, 1, '2026-10-06', 1),
    (2, 2, 'controle', 11.00, 1, '2026-10-06', 1),
    (3, 2, 'controle', 13.00, 1, '2026-10-06', 1)
");
echo "✔ Notes d'exemple créées\n";

// Pré-inscriptions (demandes reçues du site public)
$pdo->exec("INSERT INTO preinscriptions (nom, prenom, date_naissance, lieu_naissance, nationalite, classe_actuelle, etablissement_actuel, cycle, sante, civilite, parent_nom, parent_prenom, lien_parente, parent_tel, parent_email, parent_adresse, parent_profession) VALUES
    ('BENCHERIF', 'Adam', '2016-05-11', 'Alger', 'Algérienne', '4AP', 'École publique Aïn Benian', 'primaire', '', 'M', 'BENCHERIF', 'Ahmed', 'Père', '0552233445', 'a.bencherif@gmail.com', 'Aïn Benian, Alger', 'Ingénieur'),
    ('ZITOUNI', 'Lyna', '2011-09-03', 'Blida', 'Algérienne', '1AM', 'CEM Ben M''hidi', 'moyenne', 'Asthme léger', 'Mme', 'ZITOUNI', 'Fatima', 'Mère', '0663344556', 'f.zitouni@gmail.com', 'Boufarik, Blida', 'Enseignante')
");
echo "✔ 2 pré-inscriptions d'exemple créées\n";

// Mots dans le carnet (exemples - élève AMRANI = id 1)
$pdo->exec("INSERT INTO mots_carnet (eleve_id, message, lu, created_at) VALUES
    (1, 'Très bon comportement en classe cette semaine. Continuez ainsi !', 0, '2026-10-20 09:00:00'),
    (1, 'Merci de renvoyer la fiche de renseignements signée par les parents.', 0, '2026-11-03 09:00:00')
");
echo "✔ 2 mots dans le carnet d'exemple créés (AMRANI Mohamed)\n";

// Emploi du temps examens (classe 1AM = id 6)
$pdo->exec("INSERT INTO emploi_examens (classe_id, matiere_id, date_examen, heure_debut, heure_fin, salle) VALUES
    (6, 1, '2027-01-17', '08:00', '10:00', 'Salle 1'),
    (6, 2, '2027-01-18', '08:00', '10:00', 'Salle 1'),
    (6, 3, '2027-01-19', '08:00', '10:00', 'Salle 2'),
    (6, 4, '2027-01-20', '08:00', '10:00', 'Salle 2')
");
echo "✔ Emploi du temps des examens d'exemple créé\n";

echo "\n✅ BASE DE DONNÉES PRÊTE !\n";
echo "➜ Connectez-vous avec :\n";
echo "   - Surveillant :  surveillant / surveillant123\n";
echo "   - Admin :        admin / admin123\n";
echo "   - Parent :       parent / parent123 (lié à AMRANI Mohamed — email parent1@gmail.com)\n";
echo "\n➜ Lien parent → élève : users.email doit être égal à eleves.email_parent.\n";
echo "\n➜ IMPORTANT : Supprimez ce fichier après installation !\n";
echo "➜ Pour rejouer ce script en local :  init_db.php?cle=abou-anouar-2026\n";
echo '</pre>';