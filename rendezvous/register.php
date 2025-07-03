<?php
require_once 'includes/config.php';
session_start();

$erreur = '';
$success = '';

// Protection CSRF
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Rate limiting basique (à améliorer avec Redis/Memcache en production)
if (!isset($_SESSION['register_attempts'])) {
    $_SESSION['register_attempts'] = 0;
    $_SESSION['last_attempt_time'] = time();
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Vérification CSRF
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        $erreur = "Token de sécurité invalide. Veuillez recharger la page.";
    } else {
        // Rate limiting
        $current_time = time();
        if ($_SESSION['register_attempts'] >= 5 && ($current_time - $_SESSION['last_attempt_time']) < 900) { // 15 minutes
            $erreur = "Trop de tentatives. Veuillez patienter 15 minutes.";
        } else {
            // Reset attempts après 15 minutes
            if (($current_time - $_SESSION['last_attempt_time']) >= 900) {
                $_SESSION['register_attempts'] = 0;
            }

            $nom = trim($_POST['nom']);
            $email = trim(strtolower($_POST['email']));
            $password = $_POST['password'];
            $confirm = $_POST['confirm_password'];

            // Validation renforcée
            if (empty($nom) || empty($email) || empty($password) || empty($confirm)) {
                $erreur = "Tous les champs sont requis.";
            } elseif (strlen($nom) < 2 || strlen($nom) > 100) {
                $erreur = "Le nom doit contenir entre 2 et 100 caractères.";
            } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 255) {
                $erreur = "Email invalide ou trop long.";
            } elseif (strlen($password) < 8) {
                $erreur = "Le mot de passe doit contenir au moins 8 caractères.";
            } elseif (!preg_match('/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&])[A-Za-z\d@$!%*?&]/', $password)) {
                $erreur = "Le mot de passe doit contenir au moins : 1 minuscule, 1 majuscule, 1 chiffre et 1 caractère spécial.";
            } elseif ($password !== $confirm) {
                $erreur = "Les mots de passe ne correspondent pas.";
            } elseif (!preg_match('/^[a-zA-ZÀ-ÿ\s\'-]+$/', $nom)) {
                $erreur = "Le nom ne doit contenir que des lettres, espaces, apostrophes et tirets.";
            } else {
                $_SESSION['register_attempts']++;
                $_SESSION['last_attempt_time'] = $current_time;

                try {
                    $check = $conn->prepare("SELECT id FROM users WHERE email = ?");
                    $check->execute([$email]);

                    if ($check->rowCount() > 0) {
                        $erreur = "Cet email est déjà utilisé.";
                    } else {
                        // Hachage sécurisé avec coût adaptatif
                        $options = [
                            'cost' => 12, // Augmenter selon la puissance du serveur
                        ];
                        $hashed = password_hash($password, PASSWORD_ARGON2ID, $options);
                        $role = "client";
                        
                        $stmt = $conn->prepare("INSERT INTO users (name, email, password, role, created_at) VALUES (?, ?, ?, ?, NOW())");
                        if ($stmt->execute([$nom, $email, $hashed, $role])) {
                            // Log de sécurité
                            error_log("Nouveau compte créé: " . $email . " depuis IP: " . $_SERVER['REMOTE_ADDR']);
                            
                            $success = "Compte créé avec succès ! <a href='login.php'>Connectez-vous maintenant</a>";
                            
                            // Reset des tentatives après succès
                            $_SESSION['register_attempts'] = 0;
                            
                            // Optionnel: Redirection automatique après 3 secondes
                            // header("refresh:3;url=login.php");
                        } else {
                            $erreur = "Une erreur technique est survenue. Veuillez réessayer.";
                            error_log("Erreur insertion utilisateur: " . print_r($stmt->errorInfo(), true));
                        }
                    }
                } catch (PDOException $e) {
                    $erreur = "Erreur de base de données. Veuillez réessayer.";
                    error_log("Erreur PDO registration: " . $e->getMessage());
                }
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <meta http-equiv="X-Content-Type-Options" content="nosniff">
    <meta http-equiv="X-Frame-Options" content="DENY">
    <meta http-equiv="X-XSS-Protection" content="1; mode=block">
    <title>Inscription - Créer votre compte</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
</head>
<style>
    * {
        margin: 0;
        padding: 0;
        box-sizing: border-box;
    }

    body {
        font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
        line-height: 1.6;
        background: linear-gradient(135deg, #667eea 0%, #764ba2 50%, #f093fb 100%);
        min-height: 100vh;
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 2rem;
        position: relative;
        overflow-x: hidden;
    }

    body::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background: 
            radial-gradient(circle at 20% 80%, rgba(120, 119, 198, 0.3) 0%, transparent 50%),
            radial-gradient(circle at 80% 20%, rgba(255, 119, 198, 0.3) 0%, transparent 50%),
            radial-gradient(circle at 40% 40%, rgba(120, 219, 255, 0.2) 0%, transparent 50%);
        pointer-events: none;
    }

    .register-container {
        width: 100%;
        max-width: 480px;
        position: relative;
        z-index: 1;
    }

    .register-card {
        background: rgba(255, 255, 255, 0.95);
        backdrop-filter: blur(20px);
        border: 1px solid rgba(255, 255, 255, 0.3);
        border-radius: 24px;
        padding: 3rem 2.5rem;
        box-shadow: 
            0 20px 40px rgba(0, 0, 0, 0.1),
            0 8px 16px rgba(0, 0, 0, 0.05),
            inset 0 1px 0 rgba(255, 255, 255, 0.6);
        animation: slideInUp 0.8s cubic-bezier(0.34, 1.56, 0.64, 1);
        position: relative;
        overflow: hidden;
    }

    .register-card::before {
        content: '';
        position: absolute;
        top: 0;
        left: -100%;
        width: 100%;
        height: 100%;
        background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.4), transparent);
        transition: left 0.5s;
        pointer-events: none;
    }

    .register-card:hover::before {
        left: 100%;
    }

    .register-header {
        text-align: center;
        margin-bottom: 2.5rem;
        position: relative;
    }

    .register-header::before {
        content: '🚀';
        font-size: 3rem;
        display: block;
        margin-bottom: 1rem;
        animation: bounce 2s infinite;
    }

    .register-header h1 {
        font-size: 2.5rem;
        font-weight: 700;
        background: linear-gradient(135deg, #667eea 0%, #764ba2 50%, #f093fb 100%);
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
        background-clip: text;
        margin-bottom: 0.5rem;
        letter-spacing: -0.02em;
    }

    .register-header p {
        color: #6b7280;
        font-size: 1.1rem;
        font-weight: 400;
    }

    .form-group {
        margin-bottom: 1.8rem;
        position: relative;
    }

    .form-label {
        display: block;
        margin-bottom: 0.75rem;
        font-weight: 600;
        color: #374151;
        font-size: 0.95rem;
        position: relative;
    }

    .required::after {
        content: '*';
        color: #ef4444;
        margin-left: 4px;
        font-weight: bold;
    }

    .form-input {
        width: 100%;
        padding: 1.2rem 1.5rem;
        border: 2px solid #e5e7eb;
        border-radius: 16px;
        font-size: 1rem;
        font-weight: 400;
        background: rgba(255, 255, 255, 0.8);
        backdrop-filter: blur(10px);
        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        position: relative;
        z-index: 1;
    }

    .form-input:focus {
        outline: none;
        border-color: #667eea;
        background: rgba(255, 255, 255, 0.95);
        box-shadow: 
            0 0 0 4px rgba(102, 126, 234, 0.1),
            0 8px 16px rgba(102, 126, 234, 0.15);
        transform: translateY(-2px);
    }

    .form-input:hover:not(:focus) {
        border-color: #d1d5db;
        transform: translateY(-1px);
    }

    .form-input.error {
        border-color: #ef4444;
        background: rgba(239, 68, 68, 0.05);
    }

    .password-strength {
        margin-top: 0.5rem;
        padding: 0.75rem;
        background: rgba(102, 126, 234, 0.05);
        border-radius: 12px;
        font-size: 0.85rem;
        display: none;
    }

    .password-strength.show {
        display: block;
        animation: fadeIn 0.3s ease-out;
    }

    .strength-item {
        display: flex;
        align-items: center;
        margin-bottom: 0.25rem;
        color: #6b7280;
        transition: color 0.3s;
    }

    .strength-item.valid {
        color: #10b981;
    }

    .strength-item::before {
        content: '○';
        margin-right: 0.5rem;
        font-weight: bold;
    }

    .strength-item.valid::before {
        content: '●';
        color: #10b981;
    }

    .register-btn {
        width: 100%;
        background: linear-gradient(135deg, #667eea 0%, #764ba2 50%, #f093fb 100%);
        background-size: 200% 200%;
        color: white;
        border: none;
        border-radius: 16px;
        padding: 1.2rem;
        font-size: 1.1rem;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
        position: relative;
        overflow: hidden;
        margin-top: 1rem;
        letter-spacing: 0.025em;
    }

    .register-btn:hover {
        background-position: 100% 0;
        transform: translateY(-3px);
        box-shadow: 
            0 12px 24px rgba(102, 126, 234, 0.4),
            0 6px 12px rgba(118, 75, 162, 0.3);
    }

    .register-btn:active {
        transform: translateY(-1px);
    }

    .register-btn:disabled {
        opacity: 0.6;
        cursor: not-allowed;
        transform: none;
        box-shadow: none;
    }

    .alert {
        padding: 1.2rem;
        border-radius: 16px;
        margin-bottom: 2rem;
        font-weight: 500;
        display: flex;
        align-items: center;
        gap: 0.75rem;
        animation: slideInDown 0.5s ease-out;
        border: 1px solid transparent;
    }

    .alert-error {
        background: linear-gradient(135deg, rgba(239, 68, 68, 0.1) 0%, rgba(220, 38, 38, 0.05) 100%);
        border-color: rgba(239, 68, 68, 0.2);
        color: #dc2626;
    }

    .alert-success {
        background: linear-gradient(135deg, rgba(34, 197, 94, 0.1) 0%, rgba(22, 163, 74, 0.05) 100%);
        border-color: rgba(34, 197, 94, 0.2);
        color: #16a34a;
    }

    .alert::before {
        font-size: 1.25rem;
        flex-shrink: 0;
    }

    .alert-error::before {
        content: '⚠️';
    }

    .alert-success::before {
        content: '✅';
    }

    .register-footer {
        text-align: center;
        margin-top: 2.5rem;
        padding-top: 2rem;
        border-top: 1px solid rgba(0, 0, 0, 0.1);
    }

    .register-footer p {
        color: #6b7280;
        margin-bottom: 1rem;
        font-size: 0.95rem;
    }

    .register-footer a {
        color: #667eea;
        text-decoration: none;
        font-weight: 600;
        padding: 0.5rem 1rem;
        border-radius: 8px;
        transition: all 0.3s;
        display: inline-block;
    }

    .register-footer a:hover {
        background: rgba(102, 126, 234, 0.1);
        transform: translateY(-1px);
    }

    .security-info {
        background: linear-gradient(135deg, rgba(102, 126, 234, 0.05) 0%, rgba(118, 75, 162, 0.03) 100%);
        border: 1px solid rgba(102, 126, 234, 0.1);
        border-radius: 16px;
        padding: 1.5rem;
        margin-top: 2rem;
        font-size: 0.9rem;
        color: #6b7280;
    }

    .security-info h4 {
        color: #667eea;
        margin-bottom: 0.75rem;
        font-weight: 600;
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }

    .security-info h4::before {
        content: '🔒';
    }

    /* Animations */
    @keyframes slideInUp {
        from {
            opacity: 0;
            transform: translateY(50px) scale(0.95);
        }
        to {
            opacity: 1;
            transform: translateY(0) scale(1);
        }
    }

    @keyframes slideInDown {
        from {
            opacity: 0;
            transform: translateY(-20px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }

    @keyframes fadeIn {
        from { opacity: 0; }
        to { opacity: 1; }
    }

    @keyframes bounce {
        0%, 100% { transform: translateY(0); }
        50% { transform: translateY(-10px); }
    }

    /* Responsive */
    @media (max-width: 640px) {
        body {
            padding: 1rem;
        }
        
        .register-card {
            padding: 2rem 1.5rem;
        }
        
        .register-header h1 {
            font-size: 2rem;
        }
        
        .form-input {
            padding: 1rem 1.25rem;
        }
    }

    /* Dark mode support */
    @media (prefers-color-scheme: dark) {
        .register-card {
            background: rgba(17, 24, 39, 0.95);
            border-color: rgba(75, 85, 99, 0.3);
        }
        
        .form-label {
            color: #e5e7eb;
        }
        
        .form-input {
            background: rgba(31, 41, 55, 0.8);
            border-color: #4b5563;
            color: #e5e7eb;
        }
        
        .form-input:focus {
            background: rgba(31, 41, 55, 0.95);
        }
    }
</style>

<body>
    <div class="register-container">
        <div class="register-card">
            <div class="register-header">
                <h1>Inscription</h1>
                <p>Créez votre compte en quelques étapes</p>
            </div>

            <?php if ($erreur): ?>
                <div class="alert alert-error" role="alert">
                    <?= htmlspecialchars($erreur) ?>
                </div>
            <?php elseif ($success): ?>
                <div class="alert alert-success" role="alert">
                    <?= $success ?>
                </div>
            <?php endif; ?>

            <form method="POST" action="register.php" id="registerForm" autocomplete="on">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                
                <div class="form-group">
                    <label for="nom" class="form-label required">Nom complet</label>
                    <input 
                        type="text" 
                        name="nom" 
                        id="nom" 
                        class="form-input" 
                        required
                        autocomplete="name"
                        maxlength="100"
                        pattern="[a-zA-ZÀ-ÿ\s\'-]+"
                        title="Seules les lettres, espaces, apostrophes et tirets sont autorisés"
                        value="<?= isset($_POST['nom']) ? htmlspecialchars($_POST['nom']) : '' ?>"
                        placeholder="Jean Dupont"
                    >
                </div>

                <div class="form-group">
                    <label for="email" class="form-label required">Adresse email</label>
                    <input 
                        type="email" 
                        name="email" 
                        id="email" 
                        class="form-input" 
                        required
                        autocomplete="email"
                        maxlength="255"
                        value="<?= isset($_POST['email']) ? htmlspecialchars($_POST['email']) : '' ?>"
                        placeholder="jean.dupont@exemple.com"
                    >
                </div>

                <div class="form-group">
                    <label for="password" class="form-label required">Mot de passe</label>
                    <input 
                        type="password" 
                        name="password" 
                        id="password" 
                        class="form-input" 
                        required
                        autocomplete="new-password"
                        minlength="8"
                        placeholder="••••••••"
                    >
                    <div class="password-strength" id="passwordStrength">
                        <div class="strength-item" id="length">Au moins 8 caractères</div>
                        <div class="strength-item" id="lowercase">Une lettre minuscule</div>
                        <div class="strength-item" id="uppercase">Une lettre majuscule</div>
                        <div class="strength-item" id="number">Un chiffre</div>
                        <div class="strength-item" id="special">Un caractère spécial (@$!%*?&)</div>
                    </div>
                </div>

                <div class="form-group">
                    <label for="confirm_password" class="form-label required">Confirmer le mot de passe</label>
                    <input 
                        type="password" 
                        name="confirm_password" 
                        id="confirm_password" 
                        class="form-input" 
                        required
                        autocomplete="new-password"
                        placeholder="••••••••"
                    >
                </div>

                <button type="submit" class="register-btn" id="submitBtn">
                    Créer mon compte
                </button>
            </form>

            <div class="register-footer">
                <p>Vous avez déjà un compte ?</p>
                <a href="login.php">Se connecter</a>
            </div>

            <div class="security-info">
                <h4>Sécurité & Confidentialité</h4>
                <p>Vos données sont protégées . Nous ne partagerons jamais vos informations personnelles avec des tiers.</p>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const form = document.getElementById('registerForm');
            const passwordInput = document.getElementById('password');
            const confirmInput = document.getElementById('confirm_password');
            const submitBtn = document.getElementById('submitBtn');
            const strengthDiv = document.getElementById('passwordStrength');
            
            // Focus automatique sur le premier champ
            document.getElementById('nom').focus();

            // Validation temps réel du mot de passe
            passwordInput.addEventListener('input', function() {
                const password = this.value;
                const checks = {
                    length: password.length >= 8,
                    lowercase: /[a-z]/.test(password),
                    uppercase: /[A-Z]/.test(password),
                    number: /\d/.test(password),
                    special: /[@$!%*?&]/.test(password)
                };

                // Afficher/masquer l'indicateur de force
                if (password.length > 0) {
                    strengthDiv.classList.add('show');
                } else {
                    strengthDiv.classList.remove('show');
                }

                // Mettre à jour les indicateurs
                Object.keys(checks).forEach(check => {
                    const element = document.getElementById(check);
                    if (checks[check]) {
                        element.classList.add('valid');
                    } else {
                        element.classList.remove('valid');
                    }
                });

                // Validation globale
                validateForm();
            });

            // Validation de la confirmation
            confirmInput.addEventListener('input', validateForm);

            function validateForm() {
                const password = passwordInput.value;
                const confirm = confirmInput.value;
                const isPasswordValid = password.length >= 8 && 
                    /[a-z]/.test(password) && 
                    /[A-Z]/.test(password) && 
                    /\d/.test(password) && 
                    /[@$!%*?&]/.test(password);
                const isConfirmValid = password === confirm && confirm.length > 0;

                // Feedback visuel
                if (confirm.length > 0) {
                    if (isConfirmValid) {
                        confirmInput.classList.remove('error');
                        confirmInput.style.borderColor = '#10b981';
                    } else {
                        confirmInput.classList.add('error');
                    }
                }

                // État du bouton
                const isFormValid = isPasswordValid && isConfirmValid;
                submitBtn.disabled = !isFormValid;
            }

            // Validation côté client avant soumission
            form.addEventListener('submit', function(e) {
                const nom = document.getElementById('nom').value.trim();
                const email = document.getElementById('email').value.trim();
                const password = passwordInput.value;
                const confirm = confirmInput.value;

                let errors = [];

                // Validation du nom
                if (nom.length < 2 || nom.length > 100) {
                    errors.push('Le nom doit contenir entre 2 et 100 caractères.');
                }
                if (!/^[a-zA-ZÀ-ÿ\s\'-]+$/.test(nom)) {
                    errors.push('Le nom contient des caractères non autorisés.');
                }

                // Validation de l'email
                const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
                if (!emailRegex.test(email)) {
                    errors.push('Format d\'email invalide.');
                }

                // Validation du mot de passe
                if (password.length < 8) {
                    errors.push('Le mot de passe doit contenir au moins 8 caractères.');
                }
                if (!/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&])[A-Za-z\d@$!%*?&]/.test(password)) {
                    errors.push('Le mot de passe ne respecte pas les critères de sécurité.');
                }
                if (password !== confirm) {
                    errors.push('Les mots de passe ne correspondent pas.');
                }

                if (errors.length > 0) {
                    e.preventDefault();
                    alert('Erreurs détectées:\n\n' + errors.join('\n'));
                    return false;
                }

                // Indication de chargement
                submitBtn.innerHTML = 'Création en cours...';
                submitBtn.disabled = true;
            });

            // Protection contre la soumission multiple
            let submitted = false;
            form.addEventListener('submit', function(e) {
                if (submitted) {
                    e.preventDefault();
                    return false;
                }
                submitted = true;
            });

            // Auto-complétion sécurisée
            document.querySelectorAll('input').forEach(input => {
                input.addEventListener('invalid', function() {
                    this.classList.add('error');
                });
                
                input.addEventListener('input', function() {
                    if (this.classList.contains('error') && this.validity.valid) {
                        this.classList.remove('error');
                    }
                });
            });
        });

        // Protection contre les attaques par timing
        window.addEventListener('beforeunload', function() {
            // Nettoyage des données sensibles si nécessaire
            const inputs = document.querySelectorAll('input[type="password"]');
            inputs.forEach(input => input.value = '');
        });
    </script>
</body>
</html>

