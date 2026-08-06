# Espace Surveillant — École Privée Abou el Anouar

Backend PHP/MySQL de l'espace surveillant : gestion de l'emploi du temps, des professeurs, des élèves, des absences, des notes et des examens.

## Installation (XAMPP)

1. **Démarrez Apache et MySQL** depuis le panneau de contrôle XAMPP (ou WAMP/MAMP).
2. **Copiez le projet** dans le dossier web de votre serveur local :
   - XAMPP : `C:\xampp\htdocs\`
   - Le dossier du projet doit être accessible, ex : `http://localhost/Projet 6 ecole abou al anouar/`
3. **Initialisez la base de données** en ouvrant dans le navigateur :
   ```
   http://localhost/<dossier-du-projet>/espace/config/init_db.php
   ```
   Ce script crée la base `ecole_abou_anouar`, toutes les tables et les données d'exemple.
4. **Connectez-vous** à l'espace :
   ```
   http://localhost/<dossier-du-projet>/espace/login.php
   ```
   - Surveillant : `surveillant` / `surveillant123`
   - Admin : `admin` / `admin123`

## Sécurité

- Après installation, **supprimez ou protégez** le fichier `espace/config/init_db.php` (il réinitialise la base à chaque exécution).
- Changez les identifiants de démonstration avant mise en production.
- La configuration MySQL se trouve dans `espace/config/config.php` (par défaut : `root` sans mot de passe, comme XAMPP).

## Fonctionnalités

| Page | Rôle |
|------|------|
| `index.php` | Tableau de bord : statistiques, prochains examens, absences récentes |
| `emploi_du_temps.php` | Emploi du temps par classe (ajout / suppression de séances) |
| `professeurs.php` | Fiches professeurs, taux horaire, prix/heure, total paiement, absences |
| `eleves.php` | Liste des élèves par classe, coordonnées des parents |
| `absences.php` | Absences professeurs et élèves (séparées), justifiées / non justifiées |
| `notes.php` | Saisie des notes (contrôles / examens) par classe et matière |
| `examens.php` | Emploi du temps des examens (date, heure, salle) |

## Structure

```
espace/
├── config/
│   ├── config.php        # Connexion PDO + helpers
│   └── init_db.php       # Installation de la base (à exécuter une fois)
├── includes/
│   ├── auth.php          # Login / session / contrôle des rôles
│   ├── header.php        # Layout : sidebar + topbar
│   └── footer.php        # Pied de page
├── css/dashboard.css     # Styles de l'espace
├── js/dashboard.js       # Modales, sidebar mobile, confirmations
├── surveillant/          # Pages de l'espace surveillant
├── login.php             # Page de connexion
└── logout.php            # Déconnexion
```

## Base de données (tables)

`users` · `classes` · `matieres` · `professeurs` · `eleves` · `emploi_du_temps` · `absences_profs` · `absences_eleves` · `notes` · `emploi_examens`

La journée scolaire algérienne est prise en compte : Dimanche = jour 1 → Vendredi = jour 6.
