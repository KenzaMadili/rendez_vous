<?php
// Configuration de sécurité de session AVANT de démarrer la session
ini_set('session.cookie_httponly', 1);
ini_set('session.cookie_secure', 1);
ini_set('session.cookie_samesite', 'Strict');
ini_set('session.use_strict_mode', 1);
ini_set('session.cookie_lifetime', 0); // Cookie expire à la fermeture du navigateur

// Démarrer la session après configuration
session_start();

// Régénérer l'ID de session pour éviter la fixation
if (session_status() === PHP_SESSION_ACTIVE) {
    session_regenerate_id(true);
}

include 'includes/config.php';

// Protection CSRF
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Gestion des tentatives de connexion (limitation du taux)
if (!isset($_SESSION['login_attempts'])) {
    $_SESSION['login_attempts'] = 0;
    $_SESSION['last_attempt'] = 0;
}

$max_attempts = 5;
$lockout_time = 300; // 5 minutes
$erreur = '';

// Vérifier si l'utilisateur est déjà bloqué
if ($_SESSION['login_attempts'] >= $max_attempts) {
    $time_since_last_attempt = time() - $_SESSION['last_attempt'];
    if ($time_since_last_attempt < $lockout_time) {
        $remaining_time = $lockout_time - $time_since_last_attempt;
        $erreur = "Trop de tentatives échouées. Réessayez dans " . ceil($remaining_time / 60) . " minutes.";
    } else {
        // Reset des tentatives après expiration du délai
        $_SESSION['login_attempts'] = 0;
    }
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Vérification CSRF
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        $erreur = "Erreur de sécurité. Veuillez réessayer.";
    } else if ($_SESSION['login_attempts'] < $max_attempts) {
        // Validation et sanitisation des entrées
        $email = filter_var(trim($_POST['email']), FILTER_VALIDATE_EMAIL);
        $password = $_POST['password'] ?? '';

        if (!$email) {
            $erreur = "Adresse email invalide.";
        } else if (strlen($password) < 1) {
            $erreur = "Mot de passe requis.";
        } else {
            try {
                // Vérifier d'abord quelles colonnes existent
                $columns_query = $conn->query("SHOW COLUMNS FROM users");
                $existing_columns = [];
                while ($column = $columns_query->fetch(PDO::FETCH_ASSOC)) {
                    $existing_columns[] = $column['Field'];
                }
                
                // Construire la requête selon les colonnes disponibles
                $select_fields = "id, email, password, role, name";
                $has_security_columns = in_array('failed_attempts', $existing_columns) && 
                                       in_array('locked_until', $existing_columns);
                $has_active_column = in_array('active', $existing_columns);
                
                if ($has_security_columns) {
                    $select_fields .= ", failed_attempts, locked_until";
                }
                
                // Construire la clause WHERE
                $where_clause = "email = ?";
                if ($has_active_column) {
                    $where_clause .= " AND active = 1";
                }
                
                $stmt = $conn->prepare("SELECT {$select_fields} FROM users WHERE {$where_clause}");
                $stmt->execute([$email]);
                $user = $stmt->fetch(PDO::FETCH_ASSOC);

                if ($user) {
                    // Vérifier si le compte est verrouillé (si la colonne existe)
                    if ($has_security_columns && 
                        isset($user['locked_until']) && 
                        $user['locked_until'] && 
                        strtotime($user['locked_until']) > time()) {
                        $erreur = "Compte temporairement verrouillé. Réessayez plus tard.";
                    } else if (password_verify($password, $user['password'])) {
                        // Authentification réussie
                        
                        // Reset des compteurs d'échec (si les colonnes existent)
                        if ($has_security_columns) {
                            try {
                                $reset_stmt = $conn->prepare("UPDATE users SET failed_attempts = 0, locked_until = NULL WHERE id = ?");
                                $reset_stmt->execute([$user['id']]);
                            } catch (PDOException $e) {
                                // Ignorer l'erreur si les colonnes n'existent pas
                            }
                        }
                        
                        // Régénérer l'ID de session pour éviter la fixation
                        session_regenerate_id(true);
                        
                        // Stocker les informations de session de manière sécurisée
                        $_SESSION['user_id'] = (int)$user['id'];
                        $_SESSION['role'] = htmlspecialchars($user['role'], ENT_QUOTES, 'UTF-8');
                        $_SESSION['name'] = htmlspecialchars($user['name'], ENT_QUOTES, 'UTF-8');
                        $_SESSION['email'] = htmlspecialchars($user['email'], ENT_QUOTES, 'UTF-8');
                        $_SESSION['login_time'] = time();
                        $_SESSION['login_attempts'] = 0;
                        
                        // Log de connexion réussie (si la table existe)
                        try {
                            $log_stmt = $conn->prepare("INSERT INTO login_logs (user_id, ip_address, user_agent, success, login_time) VALUES (?, ?, ?, 1, NOW())");
                            $log_stmt->execute([
                                $user['id'], 
                                $_SERVER['REMOTE_ADDR'] ?? 'unknown',
                                $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
                            ]);
                        } catch (PDOException $e) {
                            // Ignorer l'erreur si la table n'existe pas
                        }

                        // Redirection sécurisée selon le rôle
                        $allowed_roles = ['admin', 'personnel', 'client'];
                        if (in_array($user['role'], $allowed_roles)) {
                            $redirect_url = $user['role'] . '/dashboard.php';
                            header("Location: " . $redirect_url);
                            exit();
                        } else {
                            $erreur = "Rôle utilisateur non valide.";
                        }
                    } else {
                        // Mot de passe incorrect
                        if ($has_security_columns) {
                            try {
                                $failed_attempts = ($user['failed_attempts'] ?? 0) + 1;
                                $locked_until = null;
                                
                                // Verrouiller le compte après 5 tentatives échouées
                                if ($failed_attempts >= 5) {
                                    $locked_until = date('Y-m-d H:i:s', time() + 900); // 15 minutes
                                }
                                
                                // Mettre à jour les tentatives échouées
                                $update_stmt = $conn->prepare("UPDATE users SET failed_attempts = ?, locked_until = ? WHERE id = ?");
                                $update_stmt->execute([$failed_attempts, $locked_until, $user['id']]);
                            } catch (PDOException $e) {
                                // Ignorer l'erreur si les colonnes n'existent pas
                            }
                        }
                        
                        $erreur = "Email ou mot de passe incorrect.";
                    }
                } else {
                    $erreur = "Email ou mot de passe incorrect.";
                }
                
                // Incrémenter les tentatives de session en cas d'échec
                if ($erreur && $erreur !== "Compte temporairement verrouillé. Réessayez plus tard.") {
                    $_SESSION['login_attempts']++;
                    $_SESSION['last_attempt'] = time();
                    
                    // Log de tentative échouée (si la table existe)
                    try {
                        $log_stmt = $conn->prepare("INSERT INTO login_logs (user_id, email, ip_address, user_agent, success, login_time) VALUES (?, ?, ?, ?, 0, NOW())");
                        $log_stmt->execute([
                            $user['id'] ?? null,
                            $email,
                            $_SERVER['REMOTE_ADDR'] ?? 'unknown',
                            $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
                        ]);
                    } catch (PDOException $e) {
                        // Ignorer l'erreur si la table n'existe pas
                    }
                }
                
            } catch (PDOException $e) {
                error_log("Erreur de base de données lors de la connexion: " . $e->getMessage());
                $erreur = "Erreur système. Veuillez réessayer plus tard.";
            }
        }
    }
}

// Headers de sécurité
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('X-XSS-Protection: 1; mode=block');
header('Referrer-Policy: strict-origin-when-cross-origin');
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Connexion - Am Soft</title>
    <meta name="robots" content="noindex, nofollow">
    <style>
        /* Reset et base */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
            line-height: 1.6;
            color: #333;
            overflow-x: hidden;
        }

        /* Header simple pour la page de connexion */
        .login-header-bar {
            position: fixed;
            top: 0;
            width: 100%;
            height: 70px;
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            border-bottom: 1px solid rgba(0, 0, 0, 0.1);
            z-index: 1000;
            display: flex;
            align-items: center;
            padding: 0 2rem;
        }

        .login-logo {
            font-size: 1.8rem;
            font-weight: 800;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            text-decoration: none;
        }

        .back-home {
            margin-left: auto;
            color: #667eea;
            text-decoration: none;
            font-weight: 500;
            padding: 0.5rem 1rem;
            border-radius: 8px;
            transition: all 0.3s ease;
        }

        .back-home:hover {
            background: rgba(102, 126, 234, 0.1);
        }

        /* Container principal */
        .login-container {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            padding: 2rem 0;
            padding-top: 90px;
            position: relative;
            overflow: hidden;
        }

        .login-container::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><defs><pattern id="grain" width="100" height="100" patternUnits="userSpaceOnUse"><circle cx="25" cy="25" r="1" fill="white" opacity="0.1"/><circle cx="75" cy="75" r="1" fill="white" opacity="0.1"/><circle cx="75" cy="25" r="0.5" fill="white" opacity="0.1"/><circle cx="25" cy="75" r="0.5" fill="white" opacity="0.1"/></pattern></defs><rect width="100" height="100" fill="url(%23grain)"/></svg>');
            animation: grain 20s linear infinite;
        }

        .login-card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.2);
            border-radius: 20px;
            padding: 3rem;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.2);
            width: 100%;
            max-width: 450px;
            margin: 0 1rem;
            animation: slideInUp 0.6s ease-out;
            position: relative;
            z-index: 1;
        }

        .login-header {
            text-align: center;
            margin-bottom: 2rem;
        }

        .login-header h2 {
            font-size: 2.2rem;
            font-weight: 700;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            margin-bottom: 0.5rem;
        }

        .login-header p {
            color: #666;
            font-size: 1rem;
        }

        .form-group {
            margin-bottom: 1.5rem;
            position: relative;
        }

        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 600;
            color: #333;
            font-size: 0.95rem;
        }

        .form-input {
            width: 100%;
            padding: 1rem 1.25rem;
            border: 2px solid #e2e8f0;
            border-radius: 12px;
            font-size: 1rem;
            transition: all 0.3s ease;
            background: white;
            box-sizing: border-box;
            position: relative;
        }

        .form-input:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
            transform: translateY(-2px);
        }

        .form-input:hover {
            border-color: #cbd5e0;
        }

        .password-container {
            position: relative;
        }

        .password-toggle {
            position: absolute;
            right: 15px;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            cursor: pointer;
            color: #667eea;
            font-size: 1.2rem;
        }

        .login-btn {
            width: 100%;
            padding: 1rem;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            border-radius: 12px;
            font-size: 1.1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            margin-top: 1rem;
            position: relative;
            overflow: hidden;
        }

        .login-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(102, 126, 234, 0.3);
        }

        .login-btn:active {
            transform: translateY(0);
        }

        .login-btn:disabled {
            opacity: 0.6;
            cursor: not-allowed;
            transform: none;
        }

        .error-message {
            background: rgba(239, 68, 68, 0.1);
            border: 1px solid rgba(239, 68, 68, 0.2);
            color: #dc2626;
            padding: 1rem;
            border-radius: 12px;
            margin-bottom: 1.5rem;
            text-align: center;
            font-weight: 500;
            animation: shake 0.5s ease-in-out;
        }

        .success-message {
            background: rgba(34, 197, 94, 0.1);
            border: 1px solid rgba(34, 197, 94, 0.2);
            color: #16a34a;
            padding: 1rem;
            border-radius: 12px;
            margin-bottom: 1.5rem;
            text-align: center;
            font-weight: 500;
        }

        .login-footer {
            text-align: center;
            margin-top: 2rem;
            padding-top: 2rem;
            border-top: 1px solid #e2e8f0;
        }

        .login-footer p {
            color: #666;
            margin-bottom: 1rem;
        }

        .login-footer a {
            color: #667eea;
            text-decoration: none;
            font-weight: 600;
            transition: color 0.3s ease;
        }

        .login-footer a:hover {
            color: #764ba2;
            text-decoration: underline;
        }

        .demo-accounts {
            background: rgba(102, 126, 234, 0.05);
            border: 1px solid rgba(102, 126, 234, 0.1);
            border-radius: 12px;
            padding: 1.5rem;
            margin-top: 2rem;
        }

        .demo-accounts h4 {
            color: #667eea;
            margin-bottom: 1rem;
            font-size: 1rem;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .demo-accounts ul {
            list-style: none;
            padding: 0;
            margin: 0;
        }

        .demo-accounts li {
            padding: 0.5rem 0;
            color: #555;
            font-size: 0.9rem;
            border-bottom: 1px solid rgba(102, 126, 234, 0.1);
            cursor: pointer;
            transition: all 0.3s ease;
            border-radius: 6px;
            padding: 0.5rem;
            margin-bottom: 0.5rem;
        }

        .demo-accounts li:hover {
            background: rgba(102, 126, 234, 0.1);
            transform: translateX(5px);
        }

        .demo-accounts li:last-child {
            border-bottom: none;
            margin-bottom: 0;
        }

        .demo-accounts li strong {
            color: #667eea;
        }

        .ripple {
            position: absolute;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.6);
            transform: scale(0);
            animation: ripple-animation 0.6s linear;
        }

        /* Animations */
        @keyframes slideInUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        @keyframes shake {
            0%, 100% { transform: translateX(0); }
            25% { transform: translateX(-5px); }
            75% { transform: translateX(5px); }
        }

        @keyframes grain {
            0%, 100% { transform: translate(0, 0); }
            10% { transform: translate(-5%, -5%); }
            20% { transform: translate(-10%, 5%); }
            30% { transform: translate(5%, -10%); }
            40% { transform: translate(-5%, 15%); }
            50% { transform: translate(-10%, 5%); }
            60% { transform: translate(15%, 0%); }
            70% { transform: translate(0%, 10%); }
            80% { transform: translate(-15%, 0%); }
            90% { transform: translate(10%, 5%); }
        }

        @keyframes ripple-animation {
            to {
                transform: scale(4);
                opacity: 0;
            }
        }

        /* Responsive */
        @media (max-width: 768px) {
            .login-card {
                padding: 2rem;
                margin: 0 1rem;
            }
            
            .login-header h2 {
                font-size: 1.8rem;
            }

            .login-header-bar {
                padding: 0 1rem;
            }

            .demo-accounts {
                font-size: 0.85rem;
            }
        }
    </style>
</head>
<body>
    <!-- Header simple -->
    <div class="login-header-bar">
        <a href="index.php" class="login-logo">Am Soft</a>
        <a href="index.php" class="back-home">← Retour à l'accueil</a>
    </div>

    <div class="login-container">
        <div class="login-card">
            <div class="login-header">
                <h2>Connexion</h2>
                <p>Accédez à votre espace personnel</p>
            </div>

            <?php if ($erreur): ?>
                <div class="error-message">
                    <i>⚠️</i> <?= htmlspecialchars($erreur, ENT_QUOTES, 'UTF-8') ?>
                </div>
            <?php endif; ?>

            <?php if (isset($_GET['registered']) && $_GET['registered'] == '1'): ?>
                <div class="success-message">
                    <i>✅</i> Inscription réussie ! Vous pouvez maintenant vous connecter.
                </div>
            <?php endif; ?>

            <?php if ($_SESSION['login_attempts'] < $max_attempts): ?>
            <form method="POST" action="login.php" id="loginForm">
                <!-- Token CSRF -->
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8') ?>">
                
                <div class="form-group">
                    <label for="email">Adresse email</label>
                    <input type="email" name="email" id="email" class="form-input" required 
                           placeholder="votre@email.com" 
                           maxlength="255"
                           autocomplete="email"
                           value="<?= isset($_POST['email']) ? htmlspecialchars($_POST['email'], ENT_QUOTES, 'UTF-8') : '' ?>">
                </div>

                <div class="form-group">
                    <label for="password">Mot de passe</label>
                    <div class="password-container">
                        <input type="password" name="password" id="password" class="form-input" required 
                               placeholder="Votre mot de passe"
                               maxlength="255"
                               autocomplete="current-password">
                        <button type="button" class="password-toggle" onclick="togglePassword()" tabindex="-1">
                            👁️
                        </button>
                    </div>
                </div>

                <button type="submit" class="login-btn" id="loginBtn">
                    Se connecter
                </button>
            </form>
            <?php endif; ?>

            <div class="login-footer">
                <p>Pas encore inscrit ?</p>
                <a href="register.php">Créer un compte gratuitement</a>
            </div>

            <!-- Section comptes de démonstration -->
            <div class="demo-accounts">
                <h4>🔧 Comptes de démonstration</h4>
                <ul>
                    <li onclick="fillDemo('admin@demo.com', 'password123')">
                        <strong>Admin:</strong> admin@demo.com / password123
                    </li>
                    <li onclick="fillDemo('staff@demo.com', 'password123')">
                        <strong>Personnel:</strong> staff@demo.com / password123
                    </li>
                    <li onclick="fillDemo('client@demo.com', 'password123')">
                        <strong>Client:</strong> client@demo.com / password123
                    </li>
                </ul>
            </div>
        </div>
    </div>

    <script>
        // CSP inline script protection
        'use strict';
        
        // Fonction pour basculer la visibilité du mot de passe
        function togglePassword() {
            const passwordInput = document.getElementById('password');
            const toggleBtn = document.querySelector('.password-toggle');
            
            if (passwordInput && toggleBtn) {
                if (passwordInput.type === 'password') {
                    passwordInput.type = 'text';
                    toggleBtn.textContent = '🙈';
                } else {
                    passwordInput.type = 'password';
                    toggleBtn.textContent = '👁️';
                }
            }
        }

        // Fonction pour remplir les champs avec les données de démo
        function fillDemo(email, password) {
            const emailField = document.getElementById('email');
            const passwordField = document.getElementById('password');
            
            if (emailField && passwordField) {
                emailField.value = email;
                passwordField.value = password;
                
                // Animation de remplissage
                emailField.style.transform = 'scale(1.05)';
                passwordField.style.transform = 'scale(1.05)';
                
                setTimeout(() => {
                    emailField.style.transform = 'scale(1)';
                    passwordField.style.transform = 'scale(1)';
                }, 200);
            }
        }

        // Animation d'entrée pour les champs
        document.querySelectorAll('.form-input').forEach(input => {
            input.addEventListener('focus', function() {
                if (this.parentElement) {
                    this.parentElement.style.transform = 'scale(1.02)';
                }
            });
            
            input.addEventListener('blur', function() {
                if (this.parentElement) {
                    this.parentElement.style.transform = 'scale(1)';
                }
            });
        });

        // Effet de ripple sur le bouton
        const loginBtn = document.querySelector('.login-btn');
        if (loginBtn) {
            loginBtn.addEventListener('click', function(e) {
                let ripple = document.createElement('span');
                ripple.classList.add('ripple');
                this.appendChild(ripple);
                
                let x = e.clientX - e.target.offsetLeft;
                let y = e.clientY - e.target.offsetTop;
                
                ripple.style.left = x + 'px';
                ripple.style.top = y + 'px';
                
                setTimeout(() => {
                    ripple.remove();
                }, 600);
            });
        }

        // Validation en temps réel
        const loginForm = document.getElementById('loginForm');
        if (loginForm) {
            loginForm.addEventListener('submit', function(e) {
                const email = document.getElementById('email').value.trim();
                const password = document.getElementById('password').value;
                const btn = document.getElementById('loginBtn');
                
                if (!email || !password) {
                    e.preventDefault();
                    if (btn) {
                        btn.style.background = 'linear-gradient(135deg, #ef4444 0%, #dc2626 100%)';
                        setTimeout(() => {
                            btn.style.background = 'linear-gradient(135deg, #667eea 0%, #764ba2 100%)';
                        }, 1000);
                    }
                    return;
                }
                
                // Animation de chargement
                if (btn) {
                    btn.innerHTML = '⏳ Connexion...';
                    btn.disabled = true;
                }
            });
        }

        // Auto-focus et animation d'entrée
        document.addEventListener('DOMContentLoaded', function() {
            setTimeout(() => {
                const emailField = document.getElementById('email');
                if (emailField) {
                    emailField.focus();
                }
            }, 500);
        });

        // Validation email en temps réel
        const emailField = document.getElementById('email');
        if (emailField) {
            emailField.addEventListener('input', function() {
                const email = this.value;
                const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
                
                if (email && !emailRegex.test(email)) {
                    this.style.borderColor = '#ef4444';
                } else {
                    this.style.borderColor = '#e2e8f0';
                }
            });
        }
    </script>
</body>
</html>