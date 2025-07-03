<?php
session_start();

// Configuration de la base de données
$host = 'localhost';
$dbname = 'rendezvs';
$username = 'root';
$password = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    error_log("Erreur de connexion à la base de données: " . $e->getMessage());
    die("Erreur de connexion à la base de données");
}

// Vérification de la méthode de requête
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: contact.php?error=invalid_method");
    exit;
}

// Vérifier si l'utilisateur est connecté
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
    header("Location: login.php?error=not_logged_in");
    exit;
}

// Récupérer les informations de l'utilisateur connecté
$user_id = $_SESSION['user_id'];
$user_role = $_SESSION['role'];

// Validation que user_id n'est pas vide
if (empty($user_id) || $user_id === null) {
    error_log("ERREUR: user_id est vide dans la session");
    header("Location: contact.php?error=session_invalid");
    exit;
}

// Vérifier que l'utilisateur existe dans la table users
try {
    $check_user = $pdo->prepare("SELECT id, name, email, role FROM users WHERE id = ?");
    $check_user->execute([$user_id]);
    $user_data = $check_user->fetch(PDO::FETCH_ASSOC);
    
    if (!$user_data) {
        error_log("ERREUR: Utilisateur avec ID $user_id n'existe pas dans la table users");
        session_destroy();
        header("Location: login.php?error=user_not_found");
        exit;
    }
} catch (PDOException $e) {
    error_log("ERREUR lors de la vérification utilisateur: " . $e->getMessage());
    header("Location: contact.php?error=db_error");
    exit;
}

// Sécuriser et récupérer les données du formulaire
$nom = htmlspecialchars(trim($_POST['nom'] ?? ''));
$email = htmlspecialchars(trim($_POST['email'] ?? ''));
$num_tele = htmlspecialchars(trim($_POST['num_tele'] ?? ''));
$sujet = htmlspecialchars(trim($_POST['sujet'] ?? ''));
$message = htmlspecialchars(trim($_POST['message'] ?? ''));

// Si le nom ou l'email ne sont pas fournis, utiliser ceux de l'utilisateur connecté
if (empty($nom)) {
    $nom = $user_data['name'];
}
if (empty($email)) {
    $email = $user_data['email'];
}

$date_envoi = date('Y-m-d H:i:s');

// Validation des champs requis
$errors = [];

if (strlen($nom) < 2) {
    $errors[] = "Le nom doit contenir au moins 2 caractères";
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $errors[] = "L'adresse email n'est pas valide";
}

if (empty($sujet)) {
    $errors[] = "Le sujet est requis";
}

if (strlen($message) < 10) {
    $errors[] = "Le message doit contenir au moins 10 caractères";
}

// Validation du numéro de téléphone (optionnel mais si fourni, doit être valide)
if (!empty($num_tele) && !preg_match('/^[0-9+\-\s()]{8,20}$/', $num_tele)) {
    $errors[] = "Le numéro de téléphone n'est pas valide";
}

// Si des erreurs de validation existent
if (!empty($errors)) {
    $error_message = implode(", ", $errors);
    error_log("Erreurs de validation: " . $error_message);
    header("Location: contact.php?error=validation&details=" . urlencode($error_message));
    exit;
}

// Insertion dans la base de données
try {
    $sql = "INSERT INTO messages (nom, email, num_tele, sujet, message, role, date_envoi, user_id) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
    
    $stmt = $pdo->prepare($sql);
    
    $params = [
        $nom,
        $email,
        $num_tele,
        $sujet,
        $message,
        $user_role,
        $date_envoi,
        intval($user_id)
    ];
    
    // Log des données à insérer (pour debug)
    error_log("Insertion message - user_id: $user_id, nom: $nom, email: $email, role: $user_role");
    
    $success = $stmt->execute($params);
    
    if ($success) {
        $message_id = $pdo->lastInsertId();
        
        // Vérification que l'insertion s'est bien passée
        $verify_stmt = $pdo->prepare("SELECT id, user_id, nom, email FROM messages WHERE id = ?");
        $verify_stmt->execute([$message_id]);
        $inserted_message = $verify_stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($inserted_message && $inserted_message['user_id'] == $user_id) {
            error_log("SUCCESS: Message inséré avec ID: $message_id, user_id: " . $inserted_message['user_id']);
            
            // Redirection selon le rôle de l'utilisateur
            if ($user_role === 'personnel') {
                header("Location: dashboard.php?sent=1&message_id=$message_id");
            } else {
                header("Location: contact.php?sent=1&message_id=$message_id");
            }
        } else {
            error_log("ERREUR: Problème lors de la vérification de l'insertion");
            header("Location: contact.php?error=verification_failed");
        }
    } else {
        error_log("ERREUR: Échec de l'insertion du message");
        error_log("PDO Error Info: " . print_r($stmt->errorInfo(), true));
        header("Location: contact.php?error=insertion_failed");
    }
    
} catch (PDOException $e) {
    error_log("ERREUR PDO lors de l'insertion du message: " . $e->getMessage());
    error_log("SQL: $sql");
    error_log("Params: " . print_r($params, true));
    header("Location: contact.php?error=database_error");
}

exit;
?>