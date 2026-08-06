<?php
// =====================================================
//  Authentification et contrôle d'accès
// =====================================================

require_once __DIR__ . '/../config/config.php';

function login($username, $password) {
    $stmt = q('SELECT * FROM users WHERE username = ?', [$username]);
    $user = $stmt->fetch();

    if ($user && password_verify($password, $user['password'])) {
        $_SESSION['user'] = [
            'id' => $user['id'],
            'username' => $user['username'],
            'role' => $user['role'],
            'nom' => $user['nom'],
            'prenom' => $user['prenom'],
            'email' => $user['email'],
        ];
        return true;
    }
    return false;
}

function logout() {
    unset($_SESSION['user']);
}

function is_logged_in() {
    return isset($_SESSION['user']);
}

function require_login() {
    if (!is_logged_in()) {
        header('Location: ' . url('login.php'));
        exit;
    }
}

function require_role($roles) {
    require_login();
    $user = current_user();
    $roles = (array)$roles;
    // L'admin est super-utilisateur : il passe aussi sur les pages du surveillant
    if ($user['role'] === 'admin' && in_array('surveillant', $roles)) {
        return;
    }
    if (!in_array($user['role'], $roles)) {
        header('Location: ' . url('login.php'));
        exit;
    }
}

function user_fullname() {
    $u = current_user();
    return trim(($u['prenom'] ?? '') . ' ' . ($u['nom'] ?? ''));
}
