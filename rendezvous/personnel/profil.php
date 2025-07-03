<?php
session_start();

// Security: Regenerate session ID periodically
if (!isset($_SESSION['last_regeneration']) || time() - $_SESSION['last_regeneration'] > 300) {
    session_regenerate_id(true);
    $_SESSION['last_regeneration'] = time();
}

// Authentication check - Accept both 'client' and 'personnel' roles
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['client', 'personnel', 'admin'])) {
    header("Location: ../login.php");
    exit();
}

// Database connection
try {
    $pdo = new PDO("mysql:host=localhost;dbname=rendezvs;charset=utf8mb4", "root", "");
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Erreur de connexion à la base de données: " . $e->getMessage());
}

// Get user information
$user_id = $_SESSION['user_id'];
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    header("Location: ../login.php");
    exit();
}

// Sanitize and prepare user data
$name = htmlspecialchars($user['name'] ?? 'Non spécifié', ENT_QUOTES, 'UTF-8');
$email = htmlspecialchars($user['email'] ?? 'Non spécifié', ENT_QUOTES, 'UTF-8');
$role = htmlspecialchars($user['role'] ?? 'Non spécifié', ENT_QUOTES, 'UTF-8');
$member_since = isset($user['created_at']) ? date('d/m/Y', strtotime($user['created_at'])) : 'Non disponible';

// Initialize statistics variables
$stats = [];

// Statistics for PERSONNEL
if ($user['role'] === 'personnel') {
    // Get services assigned to this personnel
    $query = "
        SELECT 
            s.id, s.name, s.description, s.duration, s.price, s.is_active,
            sp.is_active as assignment_active, sp.created_at as assigned_since
        FROM services s
        INNER JOIN service_personnel sp ON s.id = sp.service_id
        WHERE sp.personnel_id = ?
        ORDER BY s.name
    ";
    $stmt = $pdo->prepare($query);
    $stmt->execute([$user_id]);
    $assigned_services = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Total services assigned
    $stats['total_services'] = count($assigned_services);
    
    // Active services
    $stats['active_services'] = count(array_filter($assigned_services, function($service) {
        return $service['is_active'] == 1 && $service['assignment_active'] == 1;
    }));
    
    // Total appointments for this personnel
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM rendezvous WHERE personnel_id = ?");
    $stmt->execute([$user_id]);
    $stats['total_appointments'] = $stmt->fetchColumn();
    
    // Confirmed appointments
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM rendezvous WHERE personnel_id = ? AND status = 'confirmed'");
    $stmt->execute([$user_id]);
    $stats['confirmed_appointments'] = $stmt->fetchColumn();
    
    // Pending appointments
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM rendezvous WHERE personnel_id = ? AND status = 'pending'");
    $stmt->execute([$user_id]);
    $stats['pending_appointments'] = $stmt->fetchColumn();
    
    // Total revenue (sum of prix for confirmed appointments)
    $stmt = $pdo->prepare("SELECT COALESCE(SUM(prix), 0) FROM rendezvous WHERE personnel_id = ? AND status = 'completed'");
    $stmt->execute([$user_id]);
    $stats['total_revenue'] = $stmt->fetchColumn();
    
    // This month's appointments
    $stmt = $pdo->prepare("
        SELECT COUNT(*) FROM rendezvous 
        WHERE personnel_id = ? 
        AND MONTH(date) = MONTH(CURRENT_DATE()) 
        AND YEAR(date) = YEAR(CURRENT_DATE())
    ");
    $stmt->execute([$user_id]);
    $stats['month_appointments'] = $stmt->fetchColumn();
}

// Statistics for CLIENT
elseif ($user['role'] === 'client') {
    // Total appointments for this client
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM rendezvous WHERE client_id = ?");
    $stmt->execute([$user_id]);
    $stats['total_appointments'] = $stmt->fetchColumn();
    
    // Confirmed appointments
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM rendezvous WHERE client_id = ? AND status = 'confirmed'");
    $stmt->execute([$user_id]);
    $stats['confirmed_appointments'] = $stmt->fetchColumn();
    
    // Pending appointments
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM rendezvous WHERE client_id = ? AND status = 'pending'");
    $stmt->execute([$user_id]);
    $stats['pending_appointments'] = $stmt->fetchColumn();
    
    // Cancelled appointments
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM rendezvous WHERE client_id = ? AND status = 'cancelled'");
    $stmt->execute([$user_id]);
    $stats['cancelled_appointments'] = $stmt->fetchColumn();
    
    // Total spent
    $stmt = $pdo->prepare("SELECT COALESCE(SUM(prix), 0) FROM rendezvous WHERE client_id = ? AND status = 'confirmed'");
    $stmt->execute([$user_id]);
    $stats['total_spent'] = $stmt->fetchColumn();
    
    // Messages sent
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM messages WHERE id_user = ?");
    $stmt->execute([$user_id]);
    $stats['messages_sent'] = $stmt->fetchColumn();
    
    // Favorite services (most booked services)
    $stmt = $pdo->prepare("
        SELECT s.name, COUNT(*) as bookings
        FROM rendezvous r
        JOIN services s ON r.service_id = s.id
        WHERE r.client_id = ?
        GROUP BY s.id, s.name
        ORDER BY bookings DESC
        LIMIT 3
    ");
    $stmt->execute([$user_id]);
    $stats['favorite_services'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Statistics for ADMIN
elseif ($user['role'] === 'admin') {
    // Total users
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM users");
    $stmt->execute();
    $stats['total_users'] = $stmt->fetchColumn();
    
    // Total clients
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE role = 'client'");
    $stmt->execute();
    $stats['total_clients'] = $stmt->fetchColumn();
    
    // Total personnel
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE role = 'personnel'");
    $stmt->execute();
    $stats['total_personnel'] = $stmt->fetchColumn();
    
    // Total services
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM services");
    $stmt->execute();
    $stats['total_services'] = $stmt->fetchColumn();
    
    // Active services
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM services WHERE is_active = 1");
    $stmt->execute();
    $stats['active_services'] = $stmt->fetchColumn();
    
    // Total appointments
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM rendezvous");
    $stmt->execute();
    $stats['total_appointments'] = $stmt->fetchColumn();
    
    // Total messages
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM messages");
    $stmt->execute();
    $stats['total_messages'] = $stmt->fetchColumn();
    
    // Total revenue
    $stmt = $pdo->prepare("SELECT COALESCE(SUM(prix), 0) FROM rendezvous WHERE status = 'confirmed'");
    $stmt->execute();
    $stats['total_revenue'] = $stmt->fetchColumn();
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Profil utilisateur - Espace client">
    <title>Mon Profil - <?= ucfirst($role) ?></title>
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
            max-width: 1200px;
            margin: 0 auto;
        }

        .profile-grid {
            display: grid;
            grid-template-columns: 1fr 2fr;
            gap: 2rem;
            margin-bottom: 2rem;
        }

        .profile-card {
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1);
            overflow: hidden;
            position: relative;
        }

        .profile-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 40px 30px 30px;
            text-align: center;
            position: relative;
        }

        .profile-header::before {
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

        .profile-avatar {
            width: 80px;
            height: 80px;
            background: rgba(255, 255, 255, 0.2);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 20px;
            font-size: 2rem;
            font-weight: bold;
            position: relative;
            z-index: 1;
        }

        .profile-title {
            font-size: 1.8rem;
            font-weight: 600;
            margin-bottom: 10px;
            position: relative;
            z-index: 1;
        }

        .profile-subtitle {
            opacity: 0.9;
            font-size: 1rem;
            position: relative;
            z-index: 1;
        }

        .role-badge {
            display: inline-block;
            padding: 5px 15px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
            margin-top: 10px;
            position: relative;
            z-index: 1;
        }

        .role-personnel { background: rgba(241, 196, 15, 0.2); color: #f1c40f; }
        .role-client { background: rgba(52, 152, 219, 0.2); color: #3498db; }
        .role-admin { background: rgba(231, 76, 60, 0.2); color: #e74c3c; }

        .profile-content {
            padding: 40px 30px;
        }

        .info-group {
            margin-bottom: 25px;
            padding: 20px;
            background: #f8f9ff;
            border-radius: 12px;
            border-left: 4px solid #667eea;
            transition: all 0.3s ease;
        }

        .info-group:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.1);
        }

        .info-label {
            font-weight: 600;
            color: #4a5568;
            font-size: 0.9rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 8px;
        }

        .info-value {
            color: #2d3748;
            font-size: 1.1rem;
            font-weight: 500;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
            gap: 1rem;
        }

        .stat-card {
            background: white;
            padding: 1.5rem;
            border-radius: 15px;
            text-align: center;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
            transition: transform 0.3s ease;
        }

        .stat-card:hover {
            transform: translateY(-3px);
        }

        .stat-number {
            font-size: 2rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
        }

        .stat-label {
            color: #666;
            font-size: 0.85rem;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .stat-card.primary .stat-number { color: #667eea; }
        .stat-card.success .stat-number { color: #27ae60; }
        .stat-card.warning .stat-number { color: #f39c12; }
        .stat-card.danger .stat-number { color: #e74c3c; }
        .stat-card.info .stat-number { color: #3498db; }

        .services-section, .favorites-section {
            background: white;
            border-radius: 20px;
            padding: 2rem;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
            margin-top: 2rem;
        }

        .section-title {
            font-size: 1.5rem;
            font-weight: 600;
            color: #333;
            margin-bottom: 1.5rem;
            padding-bottom: 0.5rem;
            border-bottom: 2px solid #667eea;
        }

        .services-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 1.5rem;
        }

        .service-card {
            background: #f8f9ff;
            padding: 1.5rem;
            border-radius: 15px;
            border-left: 4px solid #667eea;
            transition: all 0.3s ease;
        }

        .service-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.1);
        }

        .service-card.inactive {
            opacity: 0.6;
            border-left-color: #bbb;
        }

        .service-name {
            font-size: 1.2rem;
            font-weight: 600;
            color: #333;
            margin-bottom: 0.5rem;
        }

        .service-description {
            color: #666;
            line-height: 1.5;
            margin-bottom: 1rem;
            font-size: 0.9rem;
        }

        .service-details {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 1rem;
        }

        .service-meta {
            display: flex;
            gap: 1rem;
            font-size: 0.85rem;
            color: #666;
        }

        .service-status {
            padding: 0.3rem 0.8rem;
            border-radius: 15px;
            font-size: 0.8rem;
            font-weight: 600;
        }

        .status-active {
            background: #d4edda;
            color: #155724;
        }

        .status-inactive {
            background: #f8d7da;
            color: #721c24;
        }

        .favorite-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 1rem;
            background: #f8f9ff;
            border-radius: 10px;
            margin-bottom: 1rem;
            border-left: 4px solid #667eea;
        }

        .favorite-name {
            font-weight: 600;
            color: #333;
        }

        .favorite-count {
            background: #667eea;
            color: white;
            padding: 0.3rem 0.8rem;
            border-radius: 15px;
            font-size: 0.85rem;
            font-weight: 600;
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

        .btn-secondary {
            background: #e2e8f0;
            color: #4a5568;
        }

        .btn-secondary:hover {
            background: #cbd5e0;
            transform: translateY(-2px);
        }

        .security-info {
            background: #f0fff4;
            border: 1px solid #9ae6b4;
            border-radius: 8px;
            padding: 15px;
            margin-top: 20px;
            font-size: 0.9rem;
            color: #2f855a;
        }

        .empty-services {
            text-align: center;
            padding: 2rem;
            color: #666;
        }

        @media (max-width: 768px) {
            .profile-grid {
                grid-template-columns: 1fr;
                gap: 1rem;
            }
            
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }
            
            .services-grid {
                grid-template-columns: 1fr;
            }
            
            .profile-header {
                padding: 30px 20px 20px;
            }
            
            .profile-content {
                padding: 30px 20px;
            }
            
            .services-section {
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
        <div class="profile-grid">
            <!-- Profil utilisateur -->
            <div class="profile-card">
                <div class="profile-header">
                    <div class="profile-avatar">
                        <?= strtoupper(substr($name, 0, 1)) ?>
                    </div>
                    <h1 class="profile-title"><?= $name ?></h1>
                    <p class="profile-subtitle">Espace <?= ucfirst($role) ?></p>
                    <span class="role-badge role-<?= $role ?>">
                        <?= strtoupper($role) ?>
                    </span>
                </div>

                <div class="profile-content">
                    <div class="info-group">
                        <div class="info-label">Adresse email</div>
                        <div class="info-value"><?= $email ?></div>
                    </div>

                    <div class="info-group">
                        <div class="info-label">Identifiant</div>
                        <div class="info-value">#<?= $user_id ?></div>
                    </div>

                    <div class="info-group">
                        <div class="info-label">Membre depuis</div>
                        <div class="info-value"><?= $member_since ?></div>
                    </div>

                    <div class="actions">
                        <a href="edit_profil.php" class="btn btn-primary">
                            ✏️ Modifier le profil
                        </a>
                        <a href="dashboard.php" class="btn btn-secondary">
                            ⬅️ Tableau de bord
                        </a>
                    </div>

                    <div class="security-info">
                        🔒 Vos informations personnelles sont protégées et chiffrées.
                    </div>
                </div>
            </div>

            <!-- Statistiques -->
            <div class="profile-card">
                <div class="profile-header">
                    <h2 style="position: relative; z-index: 1;">Mes Statistiques</h2>
                </div>
                <div class="profile-content">
                    <div class="stats-grid">
                        <?php if ($user['role'] === 'personnel'): ?>
                            <div class="stat-card primary">
                                <div class="stat-number"><?= $stats['total_services'] ?></div>
                                <div class="stat-label">Services Assignés</div>
                            </div>
                            <div class="stat-card success">
                                <div class="stat-number"><?= $stats['active_services'] ?></div>
                                <div class="stat-label">Services Actifs</div>
                            </div>
                            <div class="stat-card info">
                                <div class="stat-number"><?= $stats['total_appointments'] ?></div>
                                <div class="stat-label">Total RDV</div>
                            </div>
                            <div class="stat-card success">
                                <div class="stat-number"><?= $stats['confirmed_appointments'] ?></div>
                                <div class="stat-label">RDV Confirmés</div>
                            </div>
                            <div class="stat-card warning">
                                <div class="stat-number"><?= $stats['pending_appointments'] ?></div>
                                <div class="stat-label">RDV En Attente</div>
                            </div>
                            <div class="stat-card primary">
                                <div class="stat-number"><?= number_format($stats['total_revenue'], 0) ?> MAD</div>
                                <div class="stat-label">Revenus Totaux</div>
                            </div>
                            <div class="stat-card info">
                                <div class="stat-number"><?= $stats['month_appointments'] ?></div>
                                <div class="stat-label">RDV Ce Mois</div>
                            </div>
                        
                        <?php elseif ($user['role'] === 'client'): ?>
                            <div class="stat-card primary">
                                <div class="stat-number"><?= $stats['total_appointments'] ?></div>
                                <div class="stat-label">Total RDV</div>
                            </div>
                            <div class="stat-card success">
                                <div class="stat-number"><?= $stats['confirmed_appointments'] ?></div>
                                <div class="stat-label">RDV Confirmés</div>
                            </div>
                            <div class="stat-card warning">
                                <div class="stat-number"><?= $stats['pending_appointments'] ?></div>
                                <div class="stat-label">RDV En Attente</div>
                            </div>
                            <div class="stat-card danger">
                                <div class="stat-number"><?= $stats['cancelled_appointments'] ?></div>
                                <div class="stat-label">RDV Annulés</div>
                            </div>
                            <div class="stat-card primary">
                                <div class="stat-number"><?= number_format($stats['total_spent'], 0) ?> MAD</div>
                                <div class="stat-label">Total Dépensé</div>
                            </div>
                            <div class="stat-card info">
                                <div class="stat-number"><?= $stats['messages_sent'] ?></div>
                                <div class="stat-label">Messages Envoyés</div>
                            </div>
                        
                        <?php elseif ($user['role'] === 'admin'): ?>
                            <div class="stat-card primary">
                                <div class="stat-number"><?= $stats['total_users'] ?></div>
                                <div class="stat-label">Total Utilisateurs</div>
                            </div>
                            <div class="stat-card info">
                                <div class="stat-number"><?= $stats['total_clients'] ?></div>
                                <div class="stat-label">Clients</div>
                            </div>
                            <div class="stat-card success">
                                <div class="stat-number"><?= $stats['total_personnel'] ?></div>
                                <div class="stat-label">Personnel</div>
                            </div>
                            <div class="stat-card primary">
                                <div class="stat-number"><?= $stats['total_services'] ?></div>
                                <div class="stat-label">Total Services</div>
                            </div>
                            <div class="stat-card success">
                                <div class="stat-number"><?= $stats['active_services'] ?></div>
                                <div class="stat-label">Services Actifs</div>
                            </div>
                            <div class="stat-card warning">
                                <div class="stat-number"><?= $stats['total_appointments'] ?></div>
                                <div class="stat-label">Total RDV</div>
                            </div>
                            <div class="stat-card info">
                                <div class="stat-number"><?= $stats['total_messages'] ?></div>
                                <div class="stat-label">Messages</div>
                            </div>
                            <div class="stat-card primary">
                                <div class="stat-number"><?= number_format($stats['total_revenue'], 0) ?> MAD</div>
                                <div class="stat-label">Revenus Totaux</div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Services favoris pour les clients -->
        <?php if ($user['role'] === 'client' && !empty($stats['favorite_services'])): ?>
        <div class="favorites-section">
            <h2 class="section-title">Mes Services Favoris</h2>
            <?php foreach ($stats['favorite_services'] as $favorite): ?>
                <div class="favorite-item">
                    <div class="favorite-name"><?= htmlspecialchars($favorite['name']) ?></div>
                    <div class="favorite-count"><?= $favorite['bookings'] ?> réservation(s)</div>
                </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <!-- Services assignés (pour le personnel seulement) -->
        <?php if ($user['role'] === 'personnel'): ?>
        <div class="services-section">
            <h2 class="section-title">Mes Services Assignés</h2>
            
            <?php if (empty($assigned_services)): ?>
                <div class="empty-services">
                    <h3>Aucun service assigné</h3>
                    <p>Vous n'avez pas encore de services assignés. Contactez votre administrateur pour plus d'informations.</p>
                </div>
            <?php else: ?>
                <div class="services-grid">
                    <?php foreach ($assigned_services as $service): ?>
                        <div class="service-card <?= ($service['is_active'] && $service['assignment_active']) ? '' : 'inactive' ?>">
                            <div class="service-name"><?= htmlspecialchars($service['name']) ?></div>
                            <div class="service-description">
                                <?= htmlspecialchars($service['description']) ?>
                            </div>
                            <div class="service-details">
                                <div class="service-meta">
                                    <span>⏱️ <?= $service['duration'] ?> min</span>
                                    <span>💰 <?= number_format($service['price'], 2) ?> MAD</span>
                                    <span>📅 Assigné le <?= date('d/m/Y', strtotime($service['assigned_since'])) ?></span>
                                </div>
                                <div class="service-status <?= ($service['is_active'] && $service['assignment_active']) ? 'status-active' : 'status-inactive' ?>">
                                    <?= ($service['is_active'] && $service['assignment_active']) ? 'Actif' : 'Inactif' ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </div>
</body>
</html>