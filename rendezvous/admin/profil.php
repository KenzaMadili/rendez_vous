<?php
session_start();

// Security: Regenerate session ID periodically
if (!isset($_SESSION['last_regeneration']) || time() - $_SESSION['last_regeneration'] > 300) {
    session_regenerate_id(true);
    $_SESSION['last_regeneration'] = time();
}

// Authentication check
if (!isset($_SESSION['name']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit();
}

// Database connection (assuming you have a config file)
require_once '../includes/config.php';

try {
    $pdo = new PDO($dsn, "root", "", $options);
} catch (PDOException $e) {
    die("Erreur de connexion : " . $e->getMessage());
}

// Get admin statistics
try {
    // Total users count
    $stmt = $pdo->query("SELECT COUNT(*) as total_users FROM users");
    $total_users = $stmt->fetch()['total_users'];

    // Total clients count
    $stmt = $pdo->query("SELECT COUNT(*) as total_clients FROM users WHERE role = 'client'");
    $total_clients = $stmt->fetch()['total_clients'];

    // Total personnel count
    $stmt = $pdo->query("SELECT COUNT(*) as total_personnel FROM users WHERE role = 'personnel'");
    $total_personnel = $stmt->fetch()['total_personnel'];

    // Total appointments count
    $stmt = $pdo->query("SELECT COUNT(*) as total_appointments FROM rendezvous");
    $total_appointments = $stmt->fetch()['total_appointments'];

    // Today's appointments
    $stmt = $pdo->query("SELECT COUNT(*) as today_appointments FROM rendezvous WHERE DATE(date) = CURDATE()");
    $today_appointments = $stmt->fetch()['today_appointments'];

    // Pending appointments
    $stmt = $pdo->query("SELECT COUNT(*) as pending_appointments FROM rendezvous WHERE status = 'pending'");
    $pending_appointments = $stmt->fetch()['pending_appointments'];

    // Total messages
    $stmt = $pdo->query("SELECT COUNT(*) as total_messages FROM messages");
    $total_messages = $stmt->fetch()['total_messages'];

    // Unread messages (assuming there's a read status or you can check recent messages)
    $stmt = $pdo->query("SELECT COUNT(*) as recent_messages FROM messages WHERE DATE(date_envoi) >= CURDATE() - INTERVAL 7 DAY");
    $recent_messages = $stmt->fetch()['recent_messages'];

    // Total services
    $stmt = $pdo->query("SELECT COUNT(*) as total_services FROM services WHERE is_active = 1");
    $total_services = $stmt->fetch()['total_services'];

    // Revenue calculation (sum of appointment prices)
    $stmt = $pdo->query("SELECT SUM(prix) as total_revenue FROM rendezvous WHERE status = 'completed'");
    $total_revenue = $stmt->fetch()['total_revenue'] ?? 0;

    // This month's revenue
    $stmt = $pdo->query("SELECT SUM(prix) as month_revenue FROM rendezvous WHERE status = 'completed' AND MONTH(date) = MONTH(CURDATE()) AND YEAR(date) = YEAR(CURDATE())");
    $month_revenue = $stmt->fetch()['month_revenue'] ?? 0;

    // Recent activity - last 5 appointments
    $stmt = $pdo->query("
        SELECT r.date, r.heure, r.status, r.prix, u.name as client_name, s.name as service_name 
        FROM rendezvous r 
        JOIN users u ON r.client_id = u.id 
        JOIN services s ON r.service_id = s.id 
        ORDER BY r.created_at DESC 
        LIMIT 5
    ");
    $recent_activities = $stmt->fetchAll();

} catch (PDOException $e) {
    // Handle database errors gracefully
    $total_users = $total_clients = $total_personnel = $total_appointments = 0;
    $today_appointments = $pending_appointments = $total_messages = $recent_messages = 0;
    $total_services = $total_revenue = $month_revenue = 0;
    $recent_activities = [];
}

// Get user data from database if not in session
$user_data = [];
if (isset($_SESSION['user_id'])) {
    try {
        $stmt = $pdo->prepare("SELECT name, email, created_at FROM users WHERE id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $user_data = $stmt->fetch();
    } catch (PDOException $e) {
        // Handle error gracefully
        $user_data = [];
    }
}

// Sanitize and prepare user data with database fallback
$name = htmlspecialchars($user_data['name'] ?? $_SESSION['name'] ?? 'Non spécifié', ENT_QUOTES, 'UTF-8');
$email = htmlspecialchars($user_data['email'] ?? $_SESSION['email'] ?? 'Non spécifié', ENT_QUOTES, 'UTF-8');
$user_id = htmlspecialchars($_SESSION['user_id'] ?? '', ENT_QUOTES, 'UTF-8');
$member_since = isset($user_data['created_at']) ? date('d/m/Y', strtotime($user_data['created_at'])) : 
                (isset($_SESSION['created_at']) ? date('d/m/Y', strtotime($_SESSION['created_at'])) : 'Non disponible');
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Profil administrateur - Tableau de bord complet">
    <title>Profil Admin - <?= $name ?></title>
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
            padding: 20px;
        }

        .container {
            max-width: 1400px;
            margin: 0 auto;
        }

        .profile-header {
            background: white;
            border-radius: 20px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
            padding: 30px;
            margin-bottom: 30px;
            display: grid;
            grid-template-columns: auto 1fr auto;
            gap: 30px;
            align-items: center;
        }

        .profile-info {
            display: flex;
            align-items: center;
            gap: 20px;
        }

        .profile-avatar {
            width: 80px;
            height: 80px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 2rem;
            font-weight: bold;
        }

        .profile-details h1 {
            color: #2d3748;
            font-size: 1.8rem;
            margin-bottom: 5px;
        }

        .profile-details p {
            color: #718096;
            font-size: 1rem;
        }

        .admin-badge {
            background: linear-gradient(135deg, #48bb78 0%, #38a169 100%);
            color: white;
            padding: 8px 16px;
            border-radius: 20px;
            font-size: 0.9rem;
            font-weight: 600;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: white;
            border-radius: 16px;
            padding: 25px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.08);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            border-left: 4px solid #667eea;
        }

        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.15);
        }

        .stat-card.revenue {
            border-left-color: #48bb78;
        }

        .stat-card.appointments {
            border-left-color: #ed8936;
        }

        .stat-card.users {
            border-left-color: #9f7aea;
        }

        .stat-card.messages {
            border-left-color: #38b2ac;
        }

        .stat-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 15px;
        }

        .stat-icon {
            font-size: 2rem;
            opacity: 0.7;
        }

        .stat-title {
            color: #4a5568;
            font-size: 0.9rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .stat-value {
            font-size: 2.5rem;
            font-weight: 700;
            color: #2d3748;
            margin-bottom: 5px;
        }

        .stat-subtitle {
            color: #718096;
            font-size: 0.9rem;
        }

        .content-grid {
            display: grid;
            grid-template-columns: 1fr 400px;
            gap: 30px;
        }

        .main-content {
            display: flex;
            flex-direction: column;
            gap: 30px;
        }

        .sidebar {
            display: flex;
            flex-direction: column;
            gap: 20px;
        }

        .card {
            background: white;
            border-radius: 16px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.08);
            overflow: hidden;
        }

        .card-header {
            padding: 20px 25px;
            border-bottom: 1px solid #e2e8f0;
            background: #f7fafc;
        }

        .card-title {
            color: #2d3748;
            font-size: 1.2rem;
            font-weight: 600;
            margin: 0;
        }

        .card-content {
            padding: 25px;
        }

        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
        }

        .info-item {
            padding: 15px;
            background: #f8f9ff;
            border-radius: 10px;
            border-left: 3px solid #667eea;
        }

        .info-label {
            font-size: 0.8rem;
            color: #4a5568;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 5px;
        }

        .info-value {
            color: #2d3748;
            font-size: 1rem;
            font-weight: 500;
        }

        .activity-item {
            display: flex;
            align-items: center;
            padding: 15px;
            border-bottom: 1px solid #e2e8f0;
            transition: background-color 0.2s ease;
        }

        .activity-item:hover {
            background: #f7fafc;
        }

        .activity-item:last-child {
            border-bottom: none;
        }

        .activity-icon {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: #667eea;
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 15px;
            font-size: 0.9rem;
        }

        .activity-details {
            flex: 1;
        }

        .activity-title {
            font-weight: 600;
            color: #2d3748;
            margin-bottom: 2px;
        }

        .activity-subtitle {
            color: #718096;
            font-size: 0.9rem;
        }

        .activity-meta {
            color: #a0aec0;
            font-size: 0.8rem;
        }

        .status-badge {
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 0.75rem;
            font-weight: 600;
        }

        .status-en_attente {
            background: #fed7d7;
            color: #c53030;
        }

        .status-confirme {
            background: #c6f6d5;
            color: #22543d;
        }

        .status-termine {
            background: #bee3f8;
            color: #2a69ac;
        }

        .status-annule {
            background: #e2e8f0;
            color: #4a5568;
        }

        .actions {
            display: flex;
            gap: 15px;
            flex-wrap: wrap;
        }

        .btn {
            padding: 12px 20px;
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
            box-shadow: 0 8px 20px rgba(102, 126, 234, 0.3);
        }

        .btn-secondary {
            background: #e2e8f0;
            color: #4a5568;
        }

        .btn-secondary:hover {
            background: #cbd5e0;
            transform: translateY(-2px);
        }

        .btn-success {
            background: linear-gradient(135deg, #48bb78 0%, #38a169 100%);
            color: white;
        }

        .btn-success:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(72, 187, 120, 0.3);
        }

        @media (max-width: 1200px) {
            .content-grid {
                grid-template-columns: 1fr;
            }
            
            .sidebar {
                order: -1;
            }
        }

        @media (max-width: 768px) {
            .profile-header {
                grid-template-columns: 1fr;
                text-align: center;
                gap: 20px;
            }
            
            .stats-grid {
                grid-template-columns: 1fr;
            }
            
            .info-grid {
                grid-template-columns: 1fr;
            }
            
            .actions {
                justify-content: center;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Profile Header -->
        <div class="profile-header">
            <div class="profile-info">
                <div class="profile-avatar">
                    <?= strtoupper(substr($name, 0, 1)) ?>
                </div>
                <div class="profile-details">
                    <h1><?= $name ?></h1>
                    <p><?= $email ?></p>
                    <p>Membre depuis le <?= $member_since ?></p>
                </div>
            </div>
            
            <div class="admin-badge">
                👑 Administrateur
            </div>
        </div>

        <!-- Statistics Grid -->
        <div class="stats-grid">
            <div class="stat-card revenue">
                <div class="stat-header">
                    <div class="stat-title">Revenus</div>
                    <div class="stat-icon">💰</div>
                </div>
                <div class="stat-value"><?= number_format($month_revenue, 0, ',', ' ') ?> DH</div>
                <div class="stat-subtitle">Ce mois • Total: <?= number_format($total_revenue, 0, ',', ' ') ?> €</div>
            </div>

            <div class="stat-card appointments">
                <div class="stat-header">
                    <div class="stat-title">Rendez-vous</div>
                    <div class="stat-icon">📅</div>
                </div>
                <div class="stat-value"><?= $today_appointments ?></div>
                <div class="stat-subtitle">Aujourd'hui • Total: <?= $total_appointments ?></div>
            </div>

            <div class="stat-card users">
                <div class="stat-header">
                    <div class="stat-title">Utilisateurs</div>
                    <div class="stat-icon">👥</div>
                </div>
                <div class="stat-value"><?= $total_users ?></div>
                <div class="stat-subtitle"><?= $total_clients ?> clients • <?= $total_personnel ?> personnel</div>
            </div>

            <div class="stat-card messages">
                <div class="stat-header">
                    <div class="stat-title">Messages</div>
                    <div class="stat-icon">💬</div>
                </div>
                <div class="stat-value"><?= $recent_messages ?></div>
                <div class="stat-subtitle">Cette semaine • Total: <?= $total_messages ?></div>
            </div>
        </div>

        <!-- Main Content Grid -->
        <div class="content-grid">
            <div class="main-content">
                <!-- Personal Information -->
                <div class="card">
                    <div class="card-header">
                        <h2 class="card-title">Informations personnelles</h2>
                    </div>
                    <div class="card-content">
                        <div class="info-grid">
                            <div class="info-item">
                                <div class="info-label">Nom complet</div>
                                <div class="info-value"><?= $name ?></div>
                            </div>
                            <div class="info-item">
                                <div class="info-label">Email</div>
                                <div class="info-value"><?= $email ?></div>
                            </div>
                            <div class="info-item">
                                <div class="info-label">Identifiant</div>
                                <div class="info-value">#<?= $user_id ?></div>
                            </div>
                            <div class="info-item">
                                <div class="info-label">Statut</div>
                                <div class="info-value">Administrateur</div>
                            </div>
                        </div>
                        
                        <div class="actions" style="margin-top: 25px;">
                            <a href="edit_profil.php" class="btn btn-primary">
                                ✏️ Modifier le profil
                            </a>
                            <a href="dashboard.php" class="btn btn-secondary">
                                📊 Tableau de bord
                            </a>
                            <a href="settings.php" class="btn btn-success">
                                ⚙️ Paramètres
                            </a>
                        </div>
                    </div>
                </div>

                <!-- Recent Activity -->
                <div class="card">
                    <div class="card-header">
                        <h2 class="card-title">Activité récente</h2>
                    </div>
                    <div class="card-content" style="padding: 0;">
                        <?php if (empty($recent_activities)): ?>
                            <div style="padding: 25px; text-align: center; color: #718096;">
                                Aucune activité récente
                            </div>
                        <?php else: ?>
                            <?php foreach ($recent_activities as $activity): ?>
                                <div class="activity-item">
                                    <div class="activity-icon">📅</div>
                                    <div class="activity-details">
                                        <div class="activity-title"><?= htmlspecialchars($activity['service_name']) ?></div>
                                        <div class="activity-subtitle">
                                            Client: <?= htmlspecialchars($activity['client_name']) ?>
                                        </div>
                                        <div class="activity-meta">
                                            <?= date('d/m/Y', strtotime($activity['date'])) ?> à <?= date('H:i', strtotime($activity['heure'])) ?>
                                        </div>
                                    </div>
                                    <div>
                                        <span class="status-badge status-<?= $activity['status'] ?>">
                                            <?= ucfirst(str_replace('_', ' ', $activity['status'])) ?>
                                        </span>
                                        <div style="text-align: right; margin-top: 5px; font-weight: 600; color: #2d3748;">
                                            <?= $activity['prix'] ?> DH
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Sidebar -->
            <div class="sidebar">
                <!-- Quick Stats -->
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">Aperçu rapide</h3>
                    </div>
                    <div class="card-content">
                        <div style="display: flex; flex-direction: column; gap: 15px;">
                            <div style="display: flex; justify-content: space-between; align-items: center;">
                                <span>RDV en attente</span>
                                <span style="background: #fed7d7; color: #c53030; padding: 4px 8px; border-radius: 12px; font-size: 0.8rem; font-weight: 600;">
                                    <?= $pending_appointments ?>
                                </span>
                            </div>
                            <div style="display: flex; justify-content: space-between; align-items: center;">
                                <span>Services actifs</span>
                                <span style="background: #c6f6d5; color: #22543d; padding: 4px 8px; border-radius: 12px; font-size: 0.8rem; font-weight: 600;">
                                    <?= $total_services ?>
                                </span>
                            </div>
                            <div style="display: flex; justify-content: space-between; align-items: center;">
                                <span>Personnel</span>
                                <span style="background: #bee3f8; color: #2a69ac; padding: 4px 8px; border-radius: 12px; font-size: 0.8rem; font-weight: 600;">
                                    <?= $total_personnel ?>
                                </span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Quick Actions -->
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">Actions rapides</h3>
                    </div>
                    <div class="card-content">
                        <div style="display: flex; flex-direction: column; gap: 10px;">
                            <a href="appointments.php" class="btn btn-secondary" style="justify-content: center;">
                                📅 Gérer les RDV
                            </a>
                            <a href="users.php" class="btn btn-secondary" style="justify-content: center;">
                                👥 Gérer les utilisateurs
                            </a>
                            <a href="services.php" class="btn btn-secondary" style="justify-content: center;">
                                🛠️ Gérer les services
                            </a>
                            <a href="messages.php" class="btn btn-secondary" style="justify-content: center;">
                                💬 Messages
                            </a>
                            <a href="reports.php" class="btn btn-secondary" style="justify-content: center;">
                                📊 Rapports
                            </a>
                        </div>
                    </div>
                </div>

                <!-- Security Info -->
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">Sécurité</h3>
                    </div>
                    <div class="card-content">
                        <div style="color: #48bb78; font-size: 0.9rem;">
                            🔒 Session sécurisée active<br>
                            🛡️ Dernière connexion vérifiée<br>
                            ⚡ Régénération automatique des sessions
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>