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

// Initialize variables
$assigned_services = [];
$total_services = 0;
$active_services = 0;
$appointments_count = 0;
$client_appointments = [];
$client_stats = [
    'total_appointments' => 0,
    'completed_appointments' => 0,
    'confirmed_appointments' => 0,
    'pending_appointments' => 0,
    'cancelled_appointments' => 0,
    'total_spent' => 0,
    'favorite_service' => null,
    'next_appointment' => null
];

// Get services assigned to this personnel (if user is personnel)
if ($user['role'] === 'personnel') {
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
    
    $total_services = count($assigned_services);
    $active_services = count(array_filter($assigned_services, function($service) {
        return $service['is_active'] == 1 && $service['assignment_active'] == 1;
    }));
    
    // Get appointments count for personnel
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM rendezvous WHERE personnel_id = ?");
    $stmt->execute([$user_id]);
    $appointments_count = $stmt->fetchColumn();
}

// Get client statistics and appointments (if user is client)
if ($user['role'] === 'client') {
    // Get all client appointments with service and personnel details
    $query = "
        SELECT 
            r.*,
            s.name as service_name,
            s.description as service_description,
            u.name as personnel_name
        FROM rendezvous r
        LEFT JOIN services s ON r.service_id = s.id
        LEFT JOIN users u ON r.personnel_id = u.id
        WHERE r.client_id = ?
        ORDER BY r.date DESC, r.heure DESC
    ";
    $stmt = $pdo->prepare($query);
    $stmt->execute([$user_id]);
    $client_appointments = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Calculate statistics
    $client_stats['total_appointments'] = count($client_appointments);
    
    foreach ($client_appointments as $appointment) {
        // Count by status
        switch ($appointment['status']) {
            case 'completed':
                $client_stats['completed_appointments']++;
                break;
            case 'pending':
                $client_stats['pending_appointments']++;
                break;
            case 'confirmed':
                $client_stats['confirmed_appointments']++;
                break;
            case 'cancelled':
                $client_stats['cancelled_appointments']++;
                break;
        }
        
        // Calculate total spent (only for completed appointments)
        if ($appointment['status'] === 'completed') {
            $client_stats['total_spent'] += $appointment['prix'];
        }
    }
    
    // Get favorite service (most booked service)
    $query = "
        SELECT s.name, COUNT(*) as booking_count
        FROM rendezvous r
        INNER JOIN services s ON r.service_id = s.id
        WHERE r.client_id = ?
        GROUP BY r.service_id, s.name
        ORDER BY booking_count DESC
        LIMIT 1
    ";
    $stmt = $pdo->prepare($query);
    $stmt->execute([$user_id]);
    $favorite_service = $stmt->fetch(PDO::FETCH_ASSOC);
    $client_stats['favorite_service'] = $favorite_service ? $favorite_service['name'] : null;
    
    // Get next upcoming appointment
    $query = "
        SELECT r.*, s.name as service_name, u.name as personnel_name
        FROM rendezvous r
        LEFT JOIN services s ON r.service_id = s.id
        LEFT JOIN users u ON r.personnel_id = u.id
        WHERE r.client_id = ? AND r.status IN ('pending', 'confirmed') 
        AND CONCAT(r.date, ' ', r.heure) > NOW()
        ORDER BY r.date ASC, r.heure ASC
        LIMIT 1
    ";
    $stmt = $pdo->prepare($query);
    $stmt->execute([$user_id]);
    $client_stats['next_appointment'] = $stmt->fetch(PDO::FETCH_ASSOC);
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
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
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

        .stat-card.total .stat-number { color: #667eea; }
        .stat-card.completed .stat-number { color: #27ae60; }
        .stat-card.pending .stat-number { color: #f39c12; }
        .stat-card.cancelled .stat-number { color: #e74c3c; }
        .stat-card.spent .stat-number { color: #8e44ad; }

        .next-appointment {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 1.5rem;
            border-radius: 15px;
            margin-top: 1rem;
        }

        .next-appointment h3 {
            margin-bottom: 1rem;
            font-size: 1.2rem;
        }

        .appointment-details {
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
        }

        .appointment-item {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 0.9rem;
            opacity: 0.9;
        }

        .services-section, .appointments-section {
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

        .services-grid, .appointments-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 1.5rem;
        }

        .service-card, .appointment-card {
            background: #f8f9ff;
            padding: 1.5rem;
            border-radius: 15px;
            border-left: 4px solid #667eea;
            transition: all 0.3s ease;
        }

        .service-card:hover, .appointment-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.1);
        }

        .appointment-card.completed { border-left-color: #27ae60; }
        .appointment-card.pending { border-left-color: #f39c12; }
        .appointment-card.cancelled { border-left-color: #e74c3c; }

        .service-card.inactive {
            opacity: 0.6;
            border-left-color: #bbb;
        }

        .service-name, .appointment-service {
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

        .service-details, .appointment-details-card {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 1rem;
        }

        .service-meta, .appointment-meta {
            display: flex;
            gap: 1rem;
            font-size: 0.85rem;
            color: #666;
            flex-wrap: wrap;
        }

        .service-status, .appointment-status {
            padding: 0.3rem 0.8rem;
            border-radius: 15px;
            font-size: 0.8rem;
            font-weight: 600;
        }

        .status-active, .status-completed {
            background: #d4edda;
            color: #155724;
        }

        .status-inactive, .status-cancelled {
            background: #f8d7da;
            color: #721c24;
        }

        .status-pending {
            background: #fff3cd;
            color: #856404;
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

        .empty-services, .empty-appointments {
            text-align: center;
            padding: 2rem;
            color: #666;
        }

        .favorite-service {
            background: #fff8dc;
            border: 1px solid #ddd;
            border-radius: 8px;
            padding: 15px;
            margin-top: 15px;
            text-align: center;
        }

        @media (max-width: 768px) {
            .profile-grid {
                grid-template-columns: 1fr;
                gap: 1rem;
            }
            
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }
            
            .services-grid, .appointments-grid {
                grid-template-columns: 1fr;
            }
            
            .profile-header {
                padding: 30px 20px 20px;
            }
            
            .profile-content {
                padding: 30px 20px;
            }
            
            .services-section, .appointments-section {
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

                    <?php if ($user['role'] === 'client' && $client_stats['favorite_service']): ?>
                    <div class="favorite-service">
                        <strong>🏆 Service Préféré:</strong><br>
                        <?= htmlspecialchars($client_stats['favorite_service']) ?>
                    </div>
                    <?php endif; ?>

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
                    <h2 style="position: relative; z-index: 1;">
                        <?= $user['role'] === 'client' ? 'Mes Statistiques' : 'Mes Statistiques' ?>
                    </h2>
                </div>
                <div class="profile-content">
                    <?php if ($user['role'] === 'personnel'): ?>
                    <div class="stats-grid">
                        <div class="stat-card total">
                            <div class="stat-number"><?= $total_services ?></div>
                            <div class="stat-label">Services Assignés</div>
                        </div>
                        <div class="stat-card completed">
                            <div class="stat-number"><?= $active_services ?></div>
                            <div class="stat-label">Services Actifs</div>
                        </div>
                        <div class="stat-card pending">
                            <div class="stat-number"><?= $appointments_count ?></div>
                            <div class="stat-label">Rendez-vous</div>
                        </div>
                    </div>
                    <?php else: ?>
                    <div class="stats-grid">
                        <div class="stat-card total">
                            <div class="stat-number"><?= $client_stats['total_appointments'] ?></div>
                            <div class="stat-label">Total RDV</div>
                        </div>
                        <div class="stat-card completed">
                            <div class="stat-number"><?= $client_stats['completed_appointments'] ?></div>
                            <div class="stat-label">Terminés</div>
                        </div>
                        <div class="stat-card pending">
                            <div class="stat-number"><?= $client_stats['pending_appointments'] ?></div>
                            <div class="stat-label">En Attente</div>
                        </div>
                        <div class="stat-card cancelled">
                            <div class="stat-number"><?= $client_stats['cancelled_appointments'] ?></div>
                            <div class="stat-label">Annulés</div>
                        </div>
                        <div class="stat-card spent">
                            <div class="stat-number"><?= number_format($client_stats['total_spent'], 0) ?></div>
                            <div class="stat-label">MAD Dépensés</div>
                        </div>
                    </div>

                    <?php if ($client_stats['next_appointment']): ?>
                    <div class="next-appointment">
                        <h3>🗓️ Prochain Rendez-vous</h3>
                        <div class="appointment-details">
                            <div class="appointment-item">
                                <span>📋</span>
                                <span><?= htmlspecialchars($client_stats['next_appointment']['service_name']) ?></span>
                            </div>
                            <div class="appointment-item">
                                <span>👨‍⚕️</span>
                                <span><?= htmlspecialchars($client_stats['next_appointment']['personnel_name']) ?></span>
                            </div>
                            <div class="appointment-item">
                                <span>📅</span>
                                <span><?= date('d/m/Y', strtotime($client_stats['next_appointment']['date'])) ?></span>
                            </div>
                            <div class="appointment-item">
                                <span>🕐</span>
                                <span><?= date('H:i', strtotime($client_stats['next_appointment']['heure'])) ?></span>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>

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

        <!-- Historique des rendez-vous (pour les clients seulement) -->
        <?php if ($user['role'] === 'client'): ?>
        <div class="appointments-section">
            <h2 class="section-title">Historique des Rendez-vous</h2>
            
            <?php if (empty($client_appointments)): ?>
                <div class="empty-appointments">
                    <h3>Aucun rendez-vous</h3>
                    <p>Vous n'avez pas encore pris de rendez-vous. <a href="services.php">Réservez maintenant</a>!</p>
                </div>
            <?php else: ?>
                <div class="appointments-grid">
                    <?php 
                    // Afficher seulement les 6 derniers rendez-vous
                    $recent_appointments = array_slice($client_appointments, 0, 6);
                    foreach ($recent_appointments as $appointment): 
                    ?>
                        <div class="appointment-card <?= $appointment['status'] ?>">
                            <div class="appointment-service">
                                <?= htmlspecialchars($appointment['service_name'] ?? 'Service supprimé') ?>
                            </div>
                            <div class="service-description">
                                Avec: <?= htmlspecialchars($appointment['personnel_name'] ?? 'Personnel non assigné') ?>
                            </div>
                            <div class="appointment-details-card">
                                <div class="appointment-meta">
                                    <span>📅 <?= date('d/m/Y', strtotime($appointment['date'])) ?></span>
                                    <span>🕐 <?= date('H:i', strtotime($appointment['heure'])) ?></span>
                                    <span>⏱️ <?= $appointment['duration'] ?> min</span>
                                    <span>💰 <?= number_format($appointment['prix'], 2) ?> MAD</span>
                                </div>
                                <div class="appointment-status status-<?= $appointment['status'] ?>">
                                    <?php
                                    switch ($appointment['status']) {
                                        case 'completed': echo 'Terminé'; break;
                                        case 'pending': echo 'En attente'; break;
                                        case 'confirmed': echo 'Confirmé'; break;
                                        case 'cancelled': echo 'Annulé'; break;
                                        default: echo ucfirst($appointment['status']);
                                    }
                                    ?>
                                </div>
                            </div>
                            <?php if ($appointment['notes']): ?>
                            <div style="margin-top: 10px; font-size: 0.85rem; color: #666; font-style: italic;">
                                📝 <?= htmlspecialchars($appointment['notes']) ?>
                            </div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
                
                <?php if (count($client_appointments) > 6): ?>
                <div style="text-align: center; margin-top: 20px;">
                    <a href="liste_rdv.php" class="btn btn-secondary">
                        Voir tous mes rendez-vous (<?= count($client_appointments) ?>)
                    </a>
                </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </div>
</body>
</html>