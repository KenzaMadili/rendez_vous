<?php
/**
 * Configuration de la base de données
 * Fichier de connexion PDO pour le système de prise de rendez-vous
 */

// Configuration de la base de données
define('DB_HOST', 'localhost');        // Adresse du serveur de base de données
define('DB_NAME', 'rendezvs');       // Nom de la base de données
define('DB_USER', 'root');             // Nom d'utilisateur de la base de données
define('DB_PASS', '');                 // Mot de passe de la base de données (vide pour XAMPP par défaut)
define('DB_CHARSET', 'utf8mb4');       // Jeu de caractères

$host = "localhost";
$user = "root";
$password = ""; // ou le mot de passe de ton MySQL
$dbname = "rendezvs"; // à remplacer par le nom réel de ta base
$link = mysqli_connect($host, $user, $password, $dbname);

if (!$link) {
    die("Échec de la connexion à la base de données : " . mysqli_connect_error());
}
// Options PDO pour une connexion sécurisée
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,    // Mode d'erreur : exceptions
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,         // Mode de récupération par défaut
    PDO::ATTR_EMULATE_PREPARES   => false,                    // Désactiver l'émulation des requêtes préparées
    PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES " . DB_CHARSET // Définir le charset
];

try {
    // Créer la connexion PDO
    $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
    $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
    
    // Optionnel : Message de succès pour le débogage (à supprimer en production)
    // echo "Connexion à la base de données réussie !";
    
} catch (PDOException $e) {
    // En cas d'erreur de connexion
    error_log("Erreur de connexion à la base de données : " . $e->getMessage());
    
    // Message d'erreur générique pour l'utilisateur (ne pas exposer les détails techniques)
    die("Erreur de connexion à la base de données. Veuillez contacter l'administrateur.");
}

/**
 * Fonction utilitaire pour exécuter des requêtes préparées
 * 
 * @param string $sql La requête SQL
 * @param array $params Les paramètres de la requête
 * @return PDOStatement
 */
function executeQuery($sql, $params = []) {
    global $pdo;
    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    } catch (PDOException $e) {
        error_log("Erreur lors de l'exécution de la requête : " . $e->getMessage());
        throw new Exception("Erreur lors de l'exécution de la requête");
    }
}

/**
 * Fonction pour commencer une transaction
 */
function beginTransaction() {
    global $pdo;
    return $pdo->beginTransaction();
}

/**
 * Fonction pour valider une transaction
 */
function commit() {
    global $pdo;
    return $pdo->commit();
}

/**
 * Fonction pour annuler une transaction
 */
function rollback() {
    global $pdo;
    return $pdo->rollback();
}

/**
 * Fonction pour obtenir le dernier ID inséré
 */
function getLastInsertId() {
    global $pdo;
    return $pdo->lastInsertId();
}

/**
 * Fonction pour tester la connexion à la base de données
 * Utile pour les pages d'administration
 */
function testDatabaseConnection() {
    global $pdo;
    try {
        $stmt = $pdo->query("SELECT 1");
        return true;
    } catch (PDOException $e) {
        return false;
    }
}

/**
 * Fonction pour nettoyer et sécuriser les données d'entrée
 * 
 * @param string $data Les données à nettoyer
 * @return string Les données nettoyées
 */
function cleanInput($data) {
    $data = trim($data);                    // Supprimer les espaces
    $data = stripslashes($data);            // Supprimer les antislashes
    $data = htmlspecialchars($data);        // Convertir les caractères spéciaux
    return $data;
}

/**
 * Configuration des fuseaux horaires
 */
date_default_timezone_set('Africa/Casablanca'); // Fuseau horaire du Maroc

/**
 * Configuration des sessions (à décommenter si nécessaire)
 */
// if (session_status() == PHP_SESSION_NONE) {
//     session_start();
// }

// Configuration pour l'environnement de développement/production
if (!defined('ENVIRONMENT')) {
    define('ENVIRONMENT', 'development'); // Changer en 'production' pour la mise en ligne
}

// Gestion des erreurs selon l'environnement
if (ENVIRONMENT === 'development') {
    // Afficher toutes les erreurs en développement
    ini_set('display_errors', 1);
    ini_set('display_startup_errors', 1);
    error_reporting(E_ALL);
} else {
    // Masquer les erreurs en production
    ini_set('display_errors', 0);
    ini_set('display_startup_errors', 0);
    error_reporting(0);
}

?>