<?php
session_start();

// Security: Regenerate session ID periodically
if (!isset($_SESSION['last_regeneration']) || time() - $_SESSION['last_regeneration'] > 300) {
    session_regenerate_id(true);
    $_SESSION['last_regeneration'] = time();
}

// Authentication check
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit();
}

// Database connection (à adapter selon votre configuration)
// $pdo = new PDO('mysql:host=localhost;dbname=your_db', $username, $password);

$errors = [];
$success = false;
$current_name = $_SESSION['name'] ?? '';
$current_email = $_SESSION['email'] ?? '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRF protection
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $errors[] = "Token de sécurité invalide.";
    } else {
        // Validate and sanitize input
        $new_name = trim($_POST['name'] ?? '');
        $new_email = trim($_POST['email'] ?? '');
        $current_password = $_POST['current_password'] ?? '';
        $new_password = $_POST['new_password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';

        // Validation
        if (empty($new_name)) {
            $errors[] = "Le nom est obligatoire.";
        } elseif (strlen($new_name) < 2) {
            $errors[] = "Le nom doit contenir au moins 2 caractères.";
        } elseif (strlen($new_name) > 100) {
            $errors[] = "Le nom ne peut pas dépasser 100 caractères.";
        }

        if (empty($new_email)) {
            $errors[] = "L'email est obligatoire.";
        } elseif (!filter_var($new_email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = "Format d'email invalide.";
        }

        // Password validation (only if user wants to change password)
        if (!empty($new_password)) {
            if (empty($current_password)) {
                $errors[] = "Le mot de passe actuel est requis pour changer de mot de passe.";
            }
            if (strlen($new_password) < 8) {
                $errors[] = "Le nouveau mot de passe doit contenir au moins 8 caractères.";
            }
            if ($new_password !== $confirm_password) {
                $errors[] = "La confirmation du mot de passe ne correspond pas.";
            }
            if (!preg_match('/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)/', $new_password)) {
                $errors[] = "Le mot de passe doit contenir au moins une minuscule, une majuscule et un chiffre.";
            }
        }

        // If no errors, update the profile
        if (empty($errors)) {
            try {
                // Simulate database update (remplacez par votre logique de base de données)
                /*
                $stmt = $pdo->prepare("UPDATE users SET name = ?, email = ? WHERE id = ?");
                $stmt->execute([$new_name, $new_email, $_SESSION['user_id']]);
                
                // Update password if provided
                if (!empty($new_password)) {
                    // Verify current password first
                    $stmt = $pdo->prepare("SELECT password FROM users WHERE id = ?");
                    $stmt->execute([$_SESSION['user_id']]);
                    $user = $stmt->fetch();
                    
                    if (!password_verify($current_password, $user['password'])) {
                        $errors[] = "Mot de passe actuel incorrect.";
                    } else {
                        $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                        $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
                        $stmt->execute([$hashed_password, $_SESSION['user_id']]);
                    }
                }
                */

                if (empty($errors)) {
                    // Update session data
                    $_SESSION['name'] = $new_name;
                    $_SESSION['email'] = $new_email;
                    $success = true;
                    $current_name = $new_name;
                    $current_email = $new_email;
                }
            } catch (Exception $e) {
                $errors[] = "Erreur lors de la mise à jour. Veuillez réessayer.";
            }
        }
    }
}

// Generate CSRF token
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Modifier mon profil - Espace client">
    <title>Modifier Mon Profil - Espace Client</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .form-container {
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1);
            overflow: hidden;
            max-width: 600px;
            width: 100%;
            position: relative;
        }

        .form-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px;
            text-align: center;
            position: relative;
        }

        .form-header::before {
            content: '';
            position: absolute;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: radial-gradient(circle, rgba(255,255,255,0.1) 0%, transparent 70%);
            animation: rotate 20s linear infinite;
        }

        @keyframes rotate {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        .form-title {
            font-size: 1.8rem;
            font-weight: 600;
            margin-bottom: 10px;
            position: relative;
            z-index: 1;
        }

        .form-subtitle {
            opacity: 0.9;
            font-size: 1rem;
            position: relative;
            z-index: 1;
        }

        .form-content {
            padding: 40px 30px;
        }

        .alert {
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-weight: 500;
        }

        .alert-success {
            background: #f0fff4;
            color: #2f855a;
            border: 1px solid #9ae6b4;
        }

        .alert-error {
            background: #fed7d7;
            color: #c53030;
            border: 1px solid #feb2b2;
        }

        .form-section {
            margin-bottom: 30px;
        }

        .section-title {
            font-size: 1.2rem;
            font-weight: 600;
            color: #4a5568;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid #e2e8f0;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-label {
            display: block;
            font-weight: 600;
            color: #4a5568;
            margin-bottom: 8px;
            font-size: 0.9rem;
        }

        .form-input {
            width: 100%;
            padding: 12px 16px;
            border: 2px solid #e2e8f0;
            border-radius: 8px;
            font-size: 1rem;
            transition: all 0.3s ease;
            background: #f8f9ff;
        }

        .form-input:focus {
            outline: none;
            border-color: #667eea;
            background: white;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }

        .password-requirements {
            background: #f7fafc;
            border: 1px solid #e2e8f0;
            border-radius: 6px;
            padding: 12px;
            margin-top: 8px;
            font-size: 0.85rem;
            color: #4a5568;
        }

        .requirement {
            display: flex;
            align-items: center;
            margin-bottom: 4px;
        }

        .requirement:last-child {
            margin-bottom: 0;
        }

        .requirement-icon {
            margin-right: 8px;
            font-size: 0.8rem;
        }

        .actions {
            display: flex;
            gap: 15px;
            justify-content: center;
            margin-top: 30px;
            flex-wrap: wrap;
        }

        .btn {
            padding: 12px 24px;
            border-radius: 25px;
            text-decoration: none;
            font-weight: 600;
            transition: all 0.3s ease;
            border: none;
            cursor: pointer;
            font-size: 0.9rem;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(102, 126, 234, 0.3);
        }

        .btn-primary:disabled {
            opacity: 0.6;
            cursor: not-allowed;
            transform: none;
        }

        .btn-secondary {
            background: #e2e8f0;
            color: #4a5568;
        }

        .btn-secondary:hover {
            background: #cbd5e0;
            transform: translateY(-2px);
        }

        .toggle-password {
            position: relative;
        }

        .password-toggle {
            position: absolute;
            right: 12px;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            cursor: pointer;
            color: #718096;
            font-size: 1.2rem;
        }

        @media (max-width: 480px) {
            .form-container {
                margin: 10px;
            }
            
            .form-header {
                padding: 20px;
            }
            
            .form-content {
                padding: 30px 20px;
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
    <div class="form-container">
        <div class="form-header">
            <h1 class="form-title">Modifier Mon Profil</h1>
            <p class="form-subtitle">Mettre à jour mes informations</p>
        </div>

        <div class="form-content">
            <?php if ($success): ?>
                <div class="alert alert-success">
                    ✅ Votre profil a été mis à jour avec succès !
                </div>
            <?php endif; ?>

            <?php if (!empty($errors)): ?>
                <div class="alert alert-error">
                    ❌ Erreurs détectées :
                    <ul style="margin: 8px 0 0 20px;">
                        <?php foreach ($errors as $error): ?>
                            <li><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <form method="POST" action="" id="profileForm">
                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">

                <div class="form-section">
                    <h2 class="section-title">👤 Informations personnelles</h2>
                    
                    <div class="form-group">
                        <label for="name" class="form-label">Nom complet *</label>
                        <input type="text" 
                               id="name" 
                               name="name" 
                               class="form-input" 
                               value="<?= htmlspecialchars($current_name, ENT_QUOTES, 'UTF-8') ?>" 
                               required
                               maxlength="100">
                    </div>

                    <div class="form-group">
                        <label for="email" class="form-label">Adresse email *</label>
                        <input type="email" 
                               id="email" 
                               name="email" 
                               class="form-input" 
                               value="<?= htmlspecialchars($current_email, ENT_QUOTES, 'UTF-8') ?>" 
                               required>
                    </div>
                </div>

                <div class="form-section">
                    <h2 class="section-title">🔒 Changer le mot de passe</h2>
                    <p style="color: #718096; font-size: 0.9rem; margin-bottom: 20px;">
                        Laissez vide si vous ne souhaitez pas changer votre mot de passe
                    </p>
                    
                    <div class="form-group">
                        <label for="current_password" class="form-label">Mot de passe actuel</label>
                        <div class="toggle-password">
                            <input type="password" 
                                   id="current_password" 
                                   name="current_password" 
                                   class="form-input">
                            <button type="button" class="password-toggle" onclick="togglePassword('current_password')">👁️</button>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="new_password" class="form-label">Nouveau mot de passe</label>
                        <div class="toggle-password">
                            <input type="password" 
                                   id="new_password" 
                                   name="new_password" 
                                   class="form-input"
                                   onkeyup="validatePassword()">
                            <button type="button" class="password-toggle" onclick="togglePassword('new_password')">👁️</button>
                        </div>
                        <div class="password-requirements">
                            <div class="requirement" id="length-req">
                                <span class="requirement-icon">❌</span>
                                Au moins 8 caractères
                            </div>
                            <div class="requirement" id="lowercase-req">
                                <span class="requirement-icon">❌</span>
                                Une lettre minuscule
                            </div>
                            <div class="requirement" id="uppercase-req">
                                <span class="requirement-icon">❌</span>
                                Une lettre majuscule
                            </div>
                            <div class="requirement" id="number-req">
                                <span class="requirement-icon">❌</span>
                                Un chiffre
                            </div>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="confirm_password" class="form-label">Confirmer le nouveau mot de passe</label>
                        <div class="toggle-password">
                            <input type="password" 
                                   id="confirm_password" 
                                   name="confirm_password" 
                                   class="form-input"
                                   onkeyup="validatePasswordMatch()">
                            <button type="button" class="password-toggle" onclick="togglePassword('confirm_password')">👁️</button>
                        </div>
                        <div id="password-match" style="margin-top: 8px; font-size: 0.85rem;"></div>
                    </div>
                </div>

                <div class="actions">
                    <button type="submit" class="btn btn-primary" id="submitBtn">
                        💾 Enregistrer les modifications
                    </button>
                    <a href="profil.php" class="btn btn-secondary">
                        ❌ Annuler
                    </a>
                </div>
            </form>
        </div>
    </div>

    <script>
        function togglePassword(fieldId) {
            const field = document.getElementById(fieldId);
            const button = field.nextElementSibling;
            
            if (field.type === 'password') {
                field.type = 'text';
                button.textContent = '🙈';
            } else {
                field.type = 'password';
                button.textContent = '👁️';
            }
        }

        function validatePassword() {
            const password = document.getElementById('new_password').value;
            
            // Length check
            const lengthReq = document.getElementById('length-req');
            if (password.length >= 8) {
                lengthReq.querySelector('.requirement-icon').textContent = '✅';
                lengthReq.style.color = '#2f855a';
            } else {
                lengthReq.querySelector('.requirement-icon').textContent = '❌';
                lengthReq.style.color = '#4a5568';
            }
            
            // Lowercase check
            const lowercaseReq = document.getElementById('lowercase-req');
            if (/[a-z]/.test(password)) {
                lowercaseReq.querySelector('.requirement-icon').textContent = '✅';
                lowercaseReq.style.color = '#2f855a';
            } else {
                lowercaseReq.querySelector('.requirement-icon').textContent = '❌';
                lowercaseReq.style.color = '#4a5568';
            }
            
            // Uppercase check
            const uppercaseReq = document.getElementById('uppercase-req');
            if (/[A-Z]/.test(password)) {
                uppercaseReq.querySelector('.requirement-icon').textContent = '✅';
                uppercaseReq.style.color = '#2f855a';
            } else {
                uppercaseReq.querySelector('.requirement-icon').textContent = '❌';
                uppercaseReq.style.color = '#4a5568';
            }
            
            // Number check
            const numberReq = document.getElementById('number-req');
            if (/\d/.test(password)) {
                numberReq.querySelector('.requirement-icon').textContent = '✅';
                numberReq.style.color = '#2f855a';
            } else {
                numberReq.querySelector('.requirement-icon').textContent = '❌';
                numberReq.style.color = '#4a5568';
            }
            
            validatePasswordMatch();
        }

        function validatePasswordMatch() {
            const password = document.getElementById('new_password').value;
            const confirmPassword = document.getElementById('confirm_password').value;
            const matchDiv = document.getElementById('password-match');
            
            if (confirmPassword === '') {
                matchDiv.textContent = '';
                return;
            }
            
            if (password === confirmPassword) {
                matchDiv.textContent = '✅ Les mots de passe correspondent';
                matchDiv.style.color = '#2f855a';
            } else {
                matchDiv.textContent = '❌ Les mots de passe ne correspondent pas';
                matchDiv.style.color = '#c53030';
            }
        }

        // Form validation before submit
        document.getElementById('profileForm').addEventListener('submit', function(e) {
            const name = document.getElementById('name').value.trim();
            const email = document.getElementById('email').value.trim();
            
            if (name.length < 2) {
                alert('Le nom doit contenir au moins 2 caractères.');
                e.preventDefault();
                return;
            }
            
            if (!email || !email.includes('@')) {
                alert('Veuillez saisir un email valide.');
                e.preventDefault();
                return;
            }
            
            const newPassword = document.getElementById('new_password').value;
            const confirmPassword = document.getElementById('confirm_password').value;
            
            if (newPassword && newPassword !== confirmPassword) {
                alert('Les mots de passe ne correspondent pas.');
                e.preventDefault();
                return;
            }
            
            // Disable submit button to prevent double submission
            document.getElementById('submitBtn').disabled = true;
        });
    </script>
</body>
</html>