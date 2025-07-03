<?php
/**
 * Fichier de fonctions pour l'authentification et la gestion des utilisateurs
 * Ce fichier contient toutes les fonctions liées aux sessions et aux rôles
 */

// Démarrer la session si elle n'est pas déjà active
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * Vérifier si l'utilisateur est connecté
 * @return bool
 */
function is_logged_in() {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

/**
 * Vérifier si l'utilisateur est un administrateur
 * @return bool
 */
function is_admin() {
    return isset($_SESSION['role']) && $_SESSION['role'] === 'admin';
}

/**
 * Vérifier si l'utilisateur est du personnel
 * @return bool
 */
function is_personnel() {
    return isset($_SESSION['role']) && $_SESSION['role'] === 'personnel';
}

/**
 * Vérifier si l'utilisateur est un client
 * @return bool
 */
function is_client() {
    return isset($_SESSION['role']) && $_SESSION['role'] === 'client';
}

/**
 * Vérifier le rôle de l'utilisateur
 * @param string $required_role
 * @return bool
 */
function has_role($required_role) {
    return isset($_SESSION['role']) && $_SESSION['role'] === $required_role;
}

/**
 * Obtenir le rôle de l'utilisateur actuel
 * @return string|null
 */
function get_user_role() {
    return $_SESSION['role'] ?? null;
}

/**
 * Obtenir le nom de l'utilisateur actuel
 * @return string|null
 */
function get_user_name() {
    return $_SESSION['name'] ?? null;
}

/**
 * Obtenir l'ID de l'utilisateur actuel
 * @return int|null
 */
function get_user_id() {
    return $_SESSION['user_id'] ?? null;
}

/**
 * Rediriger si l'utilisateur n'est pas connecté
 * @param string $redirect_url URL de redirection (par défaut: login.php)
 */
function redirect_if_not_logged_in($redirect_url = '../login.php') {
    if (!is_logged_in()) {
        header("Location: " . $redirect_url);
        exit();
    }
}

/**
 * Rediriger si l'utilisateur n'est pas administrateur
 * @param string $redirect_url URL de redirection
 */
function redirect_if_not_admin($redirect_url = '../unauthorized.php') {
    if (!is_logged_in() || !is_admin()) {
        header("Location: " . $redirect_url);
        exit();
    }
}

/**
 * Rediriger si l'utilisateur n'est pas du personnel
 * @param string $redirect_url URL de redirection
 */
function redirect_if_not_personnel($redirect_url = '../unauthorized.php') {
    if (!is_logged_in() || !is_personnel()) {
        header("Location: " . $redirect_url);
        exit();
    }
}

/**
 * Rediriger si l'utilisateur n'est pas client
 * @param string $redirect_url URL de redirection
 */
function redirect_if_not_client($redirect_url = '../unauthorized.php') {
    if (!is_logged_in() || !is_client()) {
        header("Location: " . $redirect_url);
        exit();
    }
}

/**
 * Vérifier les permissions pour un rôle spécifique
 * @param string $required_role
 * @param string $redirect_url
 */
function require_role($required_role, $redirect_url = '../unauthorized.php') {
    if (!is_logged_in() || !has_role($required_role)) {
        header("Location: " . $redirect_url);
        exit();
    }
}

/**
 * Fonction pour rediriger selon le rôle
 * @param string $role
 * @return string
 */
function get_dashboard_url($role) {
    switch ($role) {
        case 'admin':
            return 'admin/dashboard.php';
        case 'personnel':
            return 'personnel/dashboard.php';
        case 'client':
            return 'client/dashboard.php';
        default:
            return 'login.php';
    }
}

/**
 * Rediriger vers le tableau de bord approprié selon le rôle
 */
function redirect_to_dashboard() {
    if (is_logged_in()) {
        $role = get_user_role();
        $dashboard_url = get_dashboard_url($role);
        header("Location: " . $dashboard_url);
        exit();
    }
}

/**
 * Fonction pour déconnecter l'utilisateur
 */
function logout() {
    // Détruire toutes les variables de session
    $_SESSION = array();
    
    // Détruire le cookie de session si il existe
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params["path"], $params["domain"],
            $params["secure"], $params["httponly"]
        );
    }
    
    // Détruire la session
    session_destroy();
}

/**
 * Afficher un message d'erreur formaté
 * @param string $message
 * @return string
 */
function show_error($message) {
    return '<div class="alert alert-danger">' . htmlspecialchars($message) . '</div>';
}

/**
 * Afficher un message de succès formaté
 * @param string $message
 * @return string
 */
function show_success($message) {
    return '<div class="alert alert-success">' . htmlspecialchars($message) . '</div>';
}

/**
 * Générer un token CSRF
 * @return string
 */
function generate_csrf_token() {
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Vérifier le token CSRF
 * @param string $token
 * @return bool
 */
function verify_csrf_token($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Formater une date pour l'affichage
 * @param string $date
 * @param string $format
 * @return string
 */
function format_date($date, $format = 'd/m/Y H:i') {
    return date($format, strtotime($date));
}

/**
 * Vérifier si une adresse email est valide
 * @param string $email
 * @return bool
 */
function is_valid_email($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

/**
 * Générer un mot de passe aléatoire
 * @param int $length
 * @return string
 */
function generate_random_password($length = 12) {
    $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%^&*';
    return substr(str_shuffle($chars), 0, $length);
}

?>