<?php
session_start();

// Connexion à la base de données
try {
    $pdo = new PDO("mysql:host=localhost;dbname=rendezvs;charset=utf8", "root", "");
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Erreur de connexion : " . $e->getMessage());
}

// Vérification de session - Personnel uniquement
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'personnel') {
    header('Location: ../login.php');
    exit();
}

$personnelId = $_SESSION['user_id'];
$personnelName = $_SESSION['name'] ?? 'Personnel';

// Vérifier si l'ID du message est fourni
if (!isset($_GET['id']) || empty($_GET['id'])) {
    header('Location: index.php'); // Redirection vers le tableau de bord
    exit();
}

$messageId = intval($_GET['id']);

// Récupérer le message à modifier
$message = null;
$errors = [];

try {
    $stmt = $pdo->prepare("
        SELECT id, nom, num_tele, email, sujet, message, date_envoi, user_id
        FROM messages
        WHERE id = ? AND user_id = ?
    ");
    $stmt->execute([$messageId, $personnelId]);
    $message = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$message) {
        $errors[] = "Message non trouvé ou vous n'avez pas l'autorisation de le modifier.";
    }
} catch (PDOException $e) {
    $errors[] = "Erreur de base de données : " . $e->getMessage();
}

// Traitement du formulaire de modification
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_message']) && $message) {
    $nom = trim($_POST['nom'] ?? '');
    $num_tele = trim($_POST['num_tele'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $sujet = trim($_POST['sujet'] ?? '');
    $messageText = trim($_POST['message'] ?? '');
    
    // Validation
    $errors = [];
    
    if (empty($nom)) {
        $errors[] = "Le nom est obligatoire.";
    }
    
    if (empty($email)) {
        $errors[] = "L'email est obligatoire.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "L'email n'est pas valide.";
    }
    
    if (empty($sujet)) {
        $errors[] = "Le sujet est obligatoire.";
    }
    
    if (empty($messageText)) {
        $errors[] = "Le message est obligatoire.";
    }
    
    if (!empty($num_tele) && !preg_match('/^[0-9+\-\s()]+$/', $num_tele)) {
        $errors[] = "Le numéro de téléphone n'est pas valide.";
    }
    
    // Si pas d'erreurs, mettre à jour le message
    if (empty($errors)) {
        try {
            $stmt = $pdo->prepare("
                UPDATE messages 
                SET nom = ?, num_tele = ?, email = ?, sujet = ?, message = ?
                WHERE id = ? AND user_id = ?
            ");
            $stmt->execute([$nom, $num_tele, $email, $sujet, $messageText, $messageId, $personnelId]);
            
            // Succès - redirection avec message de succès
            $successMessage = "Message mis à jour avec succès !";
            // Recharger les données du message mis à jour
            $stmt = $pdo->prepare("
                SELECT id, nom, num_tele, email, sujet, message, date_envoi, user_id
                FROM messages
                WHERE id = ? AND user_id = ?
            ");
            $stmt->execute([$messageId, $personnelId]);
            $message = $stmt->fetch(PDO::FETCH_ASSOC);
            
        } catch (PDOException $e) {
            $errors[] = "Erreur lors de la mise à jour : " . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Modifier le Message - Personnel</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
            line-height: 1.6;
            color: #333;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
        }

        .container {
            max-width: 800px;
            margin: 0 auto;
        }

        .header {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            border-radius: 20px;
            padding: 2rem;
            margin-bottom: 2rem;
            text-align: center;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
        }

        .header h1 {
            font-size: 2.5rem;
            font-weight: 700;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            margin-bottom: 0.5rem;
        }

        .header p {
            color: #666;
            font-size: 1.1rem;
        }

        .actions {
            display: flex;
            gap: 1rem;
            margin-bottom: 2rem;
            flex-wrap: wrap;
        }

        .btn {
            padding: 0.8rem 1.5rem;
            border: none;
            border-radius: 10px;
            cursor: pointer;
            font-weight: 600;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            transition: all 0.3s ease;
            font-size: 0.95rem;
        }

        .btn-back {
            background: rgba(255, 255, 255, 0.2);
            color: white;
            border: 2px solid rgba(255, 255, 255, 0.3);
        }

        .btn-back:hover {
            background: rgba(255, 255, 255, 0.3);
            border-color: rgba(255, 255, 255, 0.5);
            transform: translateY(-2px);
        }

        .form-container {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            border-radius: 20px;
            padding: 2rem;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
        }

        .form-group {
            margin-bottom: 1.5rem;
        }

        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 600;
            color: #333;
            font-size: 0.95rem;
        }

        .form-group input,
        .form-group textarea {
            width: 100%;
            padding: 0.8rem 1rem;
            border: 2px solid #e2e8f0;
            border-radius: 10px;
            font-size: 1rem;
            transition: all 0.3s ease;
            background: white;
        }

        .form-group input:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }

        .form-group textarea {
            min-height: 120px;
            resize: vertical;
        }

        .form-group small {
            color: #666;
            font-size: 0.85rem;
            margin-top: 0.25rem;
            display: block;
        }

        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            padding: 1rem 2rem;
            font-size: 1rem;
            font-weight: 600;
            border-radius: 10px;
            cursor: pointer;
            transition: all 0.3s ease;
            width: 100%;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(102, 126, 234, 0.3);
        }

        .error-list {
            background: #fee;
            color: #c53030;
            padding: 1rem;
            border-radius: 10px;
            margin-bottom: 1.5rem;
            border-left: 4px solid #c53030;
        }

        .error-list ul {
            list-style-type: none;
            margin: 0;
            padding: 0;
        }

        .error-list li {
            margin-bottom: 0.5rem;
        }

        .error-list li:last-child {
            margin-bottom: 0;
        }

        .success-message {
            background: #f0fff4;
            color: #22543d;
            padding: 1rem;
            border-radius: 10px;
            margin-bottom: 1.5rem;
            border-left: 4px solid #38a169;
        }

        .message-info {
            background: #f7fafc;
            padding: 1rem;
            border-radius: 10px;
            margin-bottom: 1.5rem;
            border-left: 4px solid #667eea;
        }

        .message-info p {
            margin: 0;
            color: #666;
            font-size: 0.9rem;
        }

        .error-container {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            border-radius: 20px;
            padding: 2rem;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
            text-align: center;
        }

        .error-container h2 {
            color: #c53030;
            margin-bottom: 1rem;
        }

        @media (max-width: 768px) {
            .container {
                padding: 0 10px;
            }
            
            .header {
                padding: 1.5rem;
            }
            
            .header h1 {
                font-size: 2rem;
            }
            
            .form-container {
                padding: 1.5rem;
            }
            
            .actions {
                flex-direction: column;
            }
            
            .btn {
                justify-content: center;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Modifier le Message</h1>
            <p>Bienvenue, <?php echo htmlspecialchars($personnelName); ?></p>
        </div>

        <div class="actions">
            <a href="dashboard.php" class="btn btn-back">
                ← Retour au tableau de bord
            </a>
        </div>

        <?php if (!empty($errors) || !$message): ?>
            <div class="error-container">
                <h2>Erreur</h2>
                <?php if (!empty($errors)): ?>
                    <div class="error-list">
                        <ul>
                            <?php foreach ($errors as $error): ?>
                                <li><?php echo htmlspecialchars($error); ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>
                <a href="index.php" class="btn btn-back">Retour au tableau de bord</a>
            </div>
        <?php else: ?>
            <div class="form-container">
                <?php if (!empty($errors)): ?>
                    <div class="error-list">
                        <ul>
                            <?php foreach ($errors as $error): ?>
                                <li><?php echo htmlspecialchars($error); ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>

                <?php if (isset($successMessage)): ?>
                    <div class="success-message">
                        <?php echo htmlspecialchars($successMessage); ?>
                    </div>
                <?php endif; ?>

                <div class="message-info">
                    <p><strong>Date d'envoi original :</strong> <?php echo date('d/m/Y à H:i', strtotime($message['date_envoi'])); ?></p>
                </div>

                <form method="POST" action="">
                    <div class="form-group">
                        <label for="nom">Nom complet *</label>
                        <input type="text" 
                               id="nom" 
                               name="nom" 
                               value="<?php echo htmlspecialchars($message['nom']); ?>" 
                               required>
                    </div>

                    <div class="form-group">
                        <label for="email">Email *</label>
                        <input type="email" 
                               id="email" 
                               name="email" 
                               value="<?php echo htmlspecialchars($message['email']); ?>" 
                               required>
                    </div>

                    <div class="form-group">
                        <label for="num_tele">Numéro de téléphone</label>
                        <input type="tel" 
                               id="num_tele" 
                               name="num_tele" 
                               value="<?php echo htmlspecialchars($message['num_tele'] ?? ''); ?>" 
                               placeholder="Ex: +212 6 12 34 56 78">
                        <small>Optionnel - Format: numéros, espaces, +, -, () autorisés</small>
                    </div>

                    <div class="form-group">
                        <label for="sujet">Sujet *</label>
                        <input type="text" 
                               id="sujet" 
                               name="sujet" 
                               value="<?php echo htmlspecialchars($message['sujet']); ?>" 
                               required>
                    </div>

                    <div class="form-group">
                        <label for="message">Message *</label>
                        <textarea id="message" 
                                  name="message" 
                                  required 
                                  placeholder="Votre message..."><?php echo htmlspecialchars($message['message']); ?></textarea>
                    </div>

                    <button type="submit" name="update_message" class="btn-primary">
                        Mettre à jour le message
                    </button>
                </form>
            </div>
        <?php endif; ?>
    </div>

    <script>
        // Auto-resize textarea
        const textarea = document.getElementById('message');
        if (textarea) {
            textarea.addEventListener('input', function() {
                this.style.height = 'auto';
                this.style.height = Math.max(120, this.scrollHeight) + 'px';
            });

            // Initial resize
            textarea.style.height = Math.max(120, textarea.scrollHeight) + 'px';
        }

        // Form validation
        const form = document.querySelector('form');
        if (form) {
            form.addEventListener('submit', function(e) {
                const nom = document.getElementById('nom').value.trim();
                const email = document.getElementById('email').value.trim();
                const sujet = document.getElementById('sujet').value.trim();
                const message = document.getElementById('message').value.trim();

                if (!nom || !email || !sujet || !message) {
                    e.preventDefault();
                    alert('Veuillez remplir tous les champs obligatoires.');
                    return false;
                }

                // Email validation
                const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
                if (!emailRegex.test(email)) {
                    e.preventDefault();
                    alert('Veuillez saisir une adresse email valide.');
                    return false;
                }

                // Phone validation if provided
                const numTele = document.getElementById('num_tele').value.trim();
                if (numTele) {
                    const phoneRegex = /^[0-9+\-\s()]+$/;
                    if (!phoneRegex.test(numTele)) {
                        e.preventDefault();
                        alert('Le numéro de téléphone contient des caractères non autorisés.');
                        return false;
                    }
                }
            });
        }
    </script>
</body>
</html>