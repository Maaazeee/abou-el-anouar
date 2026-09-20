<?php
require_once __DIR__ . '/../includes/auth.php';
require_role('surveillant');

$page_title = 'Tableau de bord';

// Statistiques
$nb_profs = q('SELECT COUNT(*) c FROM professeurs')->fetch()['c'];
$nb_eleves = q('SELECT COUNT(*) c FROM eleves')->fetch()['c'];
$nb_classes = q('SELECT COUNT(*) c FROM classes')->fetch()['c'];
$nb_matieres = q('SELECT COUNT(*) c FROM matieres')->fetch()['c'];

// Absences récentes
$abs_profs_recent = q("SELECT p.nom, p.prenom, a.date_absence, a.justifiee
    FROM absences_profs a JOIN professeurs p ON p.id = a.professeur_id
    ORDER BY a.date_absence DESC LIMIT 5")->fetchAll();
$abs_eleves_recent = q("SELECT e.nom, e.prenom, a.date_absence, a.justifiee, c.nom AS classe
    FROM absences_eleves a
    JOIN eleves e ON e.id = a.eleve_id
    LEFT JOIN classes c ON c.id = e.classe_id
    ORDER BY a.date_absence DESC LIMIT 5")->fetchAll();

// Examens à venir
$examens_venir = q("SELECT e.date_examen, e.heure_debut, c.nom AS classe, m.nom AS matiere, e.salle
    FROM emploi_examens e
    LEFT JOIN classes c ON c.id = e.classe_id
    LEFT JOIN matieres m ON m.id = e.matiere_id
    WHERE e.date_examen >= CURRENT_DATE
    ORDER BY e.date_examen ASC, e.heure_debut ASC LIMIT 6")->fetchAll();

// Jours de la semaine (Dimanche = 1)
$jours = ['', 'Dimanche', 'Lundi', 'Mardi', 'Mercredi', 'Jeudi', 'Vendredi'];

include __DIR__ . '/../includes/header.php';
?>

<div class="cards-grid">
    <div class="stat-card">
        <div class="stat-ic"><i class="fas fa-chalkboard-teacher"></i></div>
        <div>
            <div class="stat-num"><?= $nb_profs ?></div>
            <div class="stat-label">Professeurs</div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-ic green"><i class="fas fa-user-graduate"></i></div>
        <div>
            <div class="stat-num"><?= $nb_eleves ?></div>
            <div class="stat-label">Élèves inscrits</div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-ic gold"><i class="fas fa-door-open"></i></div>
        <div>
            <div class="stat-num"><?= $nb_classes ?></div>
            <div class="stat-label">Classes</div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-ic teal"><i class="fas fa-book"></i></div>
        <div>
            <div class="stat-num"><?= $nb_matieres ?></div>
            <div class="stat-label">Matières</div>
        </div>
    </div>
</div>

<div class="cards-grid">
    <div class="card">
        <div class="card-head">
            <h3><i class="fas fa-file-signature" style="color:var(--gold)"></i> Prochains examens</h3>
            <a href="examens.php" class="btn btn-gold btn-sm"><i class="fas fa-arrow-right"></i> Gérer</a>
        </div>
        <div class="table-wrap">
            <table class="data">
                <thead>
                    <tr>
                        <th>Date</th><th>Heure</th><th>Classe</th><th>Matière</th><th>Salle</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!$examens_venir): ?>
                        <tr><td colspan="5"><div class="empty-state"><i class="fas fa-calendar-check"></i><p>Aucun examen planifié.</p></div></td></tr>
                    <?php endif; ?>
                    <?php foreach ($examens_venir as $ex): ?>
                        <tr>
                            <td><?= e($ex['date_examen']) ?></td>
                            <td><?= e(substr($ex['heure_debut'], 0, 5)) ?></td>
                            <td><span class="badge badge-blue"><?= e($ex['classe']) ?></span></td>
                            <td><?= e($ex['matiere']) ?></td>
                            <td><?= e($ex['salle']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="cards-grid">
    <div class="card">
        <div class="card-head">
            <h3><i class="fas fa-user-times" style="color:var(--red)"></i> Absences professeurs (récentes)</h3>
            <a href="absences.php?type=profs" class="btn btn-sm btn-danger">Gérer</a>
        </div>
        <div class="table-wrap">
            <table class="data">
                <thead><tr><th>Professeur</th><th>Date</th><th>État</th></tr></thead>
                <tbody>
                    <?php if (!$abs_profs_recent): ?>
                        <tr><td colspan="3"><div class="empty-state"><i class="fas fa-user-check"></i><p>Aucune absence récente.</p></div></td></tr>
                    <?php endif; ?>
                    <?php foreach ($abs_profs_recent as $a): ?>
                        <tr>
                            <td><?= e($a['prenom']) ?> <?= e($a['nom']) ?></td>
                            <td><?= e($a['date_absence']) ?></td>
                            <td><?= $a['justifiee'] ? '<span class="badge badge-green">Justifiée</span>' : '<span class="badge badge-red">Non justifiée</span>' ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div class="card">
        <div class="card-head">
            <h3><i class="fas fa-user-times" style="color:var(--red)"></i> Absences élèves (récentes)</h3>
            <a href="absences.php?type=eleves" class="btn btn-sm btn-danger">Gérer</a>
        </div>
        <div class="table-wrap">
            <table class="data">
                <thead><tr><th>Élève</th><th>Classe</th><th>Date</th><th>État</th></tr></thead>
                <tbody>
                    <?php if (!$abs_eleves_recent): ?>
                        <tr><td colspan="4"><div class="empty-state"><i class="fas fa-user-check"></i><p>Aucune absence récente.</p></div></td></tr>
                    <?php endif; ?>
                    <?php foreach ($abs_eleves_recent as $a): ?>
                        <tr>
                            <td><?= e($a['prenom']) ?> <?= e($a['nom']) ?></td>
                            <td><span class="badge badge-blue"><?= e($a['classe']) ?></span></td>
                            <td><?= e($a['date_absence']) ?></td>
                            <td><?= $a['justifiee'] ? '<span class="badge badge-green">Justifiée</span>' : '<span class="badge badge-red">Non justifiée</span>' ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
