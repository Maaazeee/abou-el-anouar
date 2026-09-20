<?php
require_once __DIR__ . '/includes/auth.php';

// Si déjà connecté → rediriger
if (is_logged_in()) {
    $role = current_user()['role'];
    if ($role === 'parent') {
        header('Location: parent/index.php');
    } elseif ($role === 'admin') {
        header('Location: admin/index.php');
    } elseif ($role === 'surveillant') {
        header('Location: surveillant/index.php');
    } else {
        header('Location: login.php');
    }
    exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($username === '' || $password === '') {
        $error = 'Veuillez remplir tous les champs.';
    } elseif (login($username, $password)) {
        $role = current_user()['role'];
        if ($role === 'parent') {
            header('Location: parent/index.php');
        } elseif ($role === 'admin') {
            header('Location: admin/index.php');
        } else {
            header('Location: surveillant/index.php');
        }
        exit;
    } else {
        $error = 'Identifiants incorrects.';
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Connexion | École Abou el Anouar</title>
    <link rel="stylesheet" href="css/dashboard.css">
    <link rel="icon" type="image/jpg" href="../images/logo_ecole.jpg">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
</head>
<body class="login-body">
    <div class="login-wrap">
        <div class="login-box">
            <div class="login-logo">
                <img src="../images/logo_ecole.jpg" alt="Logo">
            </div>
            <h1>Espace Abou el Anouar</h1>
            <p class="login-sub">Connexion à votre espace</p>

            <?php if ($error): ?>
                <div class="alert alert-error"><?= e($error) ?></div>
            <?php endif; ?>

            <form method="POST" action="" novalidate>
                <div class="login-field">
                    <label for="username">Nom d'utilisateur</label>
                    <div class="login-input">
                        <i class="fas fa-user"></i>
                        <input type="text" id="username" name="username" placeholder="Votre identifiant" required autofocus>
                    </div>
                </div>
                <div class="login-field">
                    <label for="password">Mot de passe</label>
                    <div class="login-input">
                        <i class="fas fa-lock"></i>
                        <input type="password" id="password" name="password" placeholder="••••••••" required>
                    </div>
                </div>
                <button type="submit" class="login-btn">
                    <i class="fas fa-sign-in-alt"></i> Se connecter
                </button>
            </form>

            <a href="../index.html" class="login-back"><i class="fas fa-arrow-left"></i> Retour au site</a>
        </div>
    </div>
</body>
</html>
