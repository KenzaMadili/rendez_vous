<?php
/**
 * Configuration de l'application
 * Fichier de configuration principal pour la connexion à la base de données
 * et les paramètres généraux de l'application
 */

// Configuration de la base de données
define('DB_HOST', 'localhost:3306');   // Hôte de la base de données avec port explicite
define('DB_NAME', 'rendezvs');       // Nom de votre base de données
define('DB_USER', 'root');             // Nom d'utilisateur MySQL (par défaut XAMPP)
define('DB_PASS', '');                 // Mot de passe MySQL (vide par défaut XAMPP)
define('DB_CHARSET', 'utf8mb4');       // Jeu de caractères

// Alternative: essayez 127.0.0.1 si localhost ne fonctionne pas
// define('DB_HOST', '127.0.0.1:3306');

// Configuration de l'application
define('APP_NAME', 'Système de Rendez-vous');
define('APP_VERSION', '1.0.0');
define('APP_URL', 'http://localhost/rendezvous'); // URL de base de votre application

// Configuration de sécurité
define('HASH_ALGO', PASSWORD_DEFAULT); // Algorithme de hachage des mots de passe
define('SESSION_LIFETIME', 3600);      // Durée de session en secondes (1 heure)

// Configuration des erreurs (en développement)
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Configuration de session (seulement si la session n'est pas déjà active)
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_lifetime', SESSION_LIFETIME);
    ini_set('session.gc_maxlifetime', SESSION_LIFETIME);
}

try {
    // Création de la connexion PDO
    $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
    
    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
        PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES " . DB_CHARSET
    ];
    
    $conn = new PDO($dsn, DB_USER, DB_PASS, $options);
    
    // Test de la connexion (optionnel, peut être retiré)
    // $conn->query("SELECT 1");
    
} catch (PDOException $e) {
    // En cas d'erreur de connexion
    error_log("Erreur de connexion à la base de données: " . $e->getMessage());
    
    // Message d'erreur détaillé en mode développement
    if (ini_get('display_errors')) {
        die("Erreur de connexion à la base de données: " . $e->getMessage() . "<br>
             Vérifiez que :<br>
             - MySQL est démarré dans XAMPP<br>
             - La base de données '" . DB_NAME . "' existe<br>
             - Les paramètres de connexion sont corrects");
    } else {
        // Message d'erreur générique pour l'utilisateur en production
        die("Erreur de connexion à la base de données. Veuillez réessayer plus tard.");
    }
}

/**
 * Fonction utilitaire pour nettoyer les données d'entrée
 * @param string $data
 * @return string
 */
function clean_input($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data);
    return $data;
}

/**
 * Fonction pour créer un utilisateur de démonstration
 * (À utiliser uniquement en phase de développement)
 */
function create_demo_users() {
    global $conn;
    
    $demo_users = [
        [
            'name' => 'Administrateur Demo',
            'email' => 'admin@demo.com',
            'password' => password_hash('password123', PASSWORD_DEFAULT),
            'role' => 'admin'
        ],
        [
            'name' => 'Personnel Demo',
            'email' => 'staff@demo.com',
            'password' => password_hash('password123', PASSWORD_DEFAULT),
            'role' => 'personnel'
        ],
        [
            'name' => 'Client Demo',
            'email' => 'client@demo.com',
            'password' => password_hash('password123', PASSWORD_DEFAULT),
            'role' => 'client'
        ]
    ];
    
    try {
        foreach ($demo_users as $user) {
            // Vérifier si l'utilisateur existe déjà
            $stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
            $stmt->execute([$user['email']]);
            
            if (!$stmt->fetch()) {
                // Créer l'utilisateur s'il n'existe pas
                $stmt = $conn->prepare("INSERT INTO users (name, email, password, role, created_at) VALUES (?, ?, ?, ?, NOW())");
                $stmt->execute([$user['name'], $user['email'], $user['password'], $user['role']]);
            }
        }
        return true;
    } catch (PDOException $e) {
        error_log("Erreur lors de la création des utilisateurs de démonstration: " . $e->getMessage());
        return false;
    }
}

// Timezone par défaut
date_default_timezone_set('Africa/Casablanca');

// Variables globales utiles
$current_year = date('Y');
$current_date = date('Y-m-d');
$current_datetime = date('Y-m-d H:i:s');

/* 
 * Décommentez la ligne suivante pour créer automatiquement 
 * les utilisateurs de démonstration lors du premier chargement
 * (À supprimer en production)
 */
// create_demo_users();

?>