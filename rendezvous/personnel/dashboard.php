<?php
session_start();
// Vérifier si l'utilisateur est connecté et est du personnel
if (!isset($_SESSION['name']) || $_SESSION['role'] !== 'personnel') {
    header('Location: ../login.php');
    exit();
}

// Connexion à la base de données
require_once '../includes/database.php';

$personnel_id = $_SESSION['user_id'];

// Calcul des statistiques
try {
    // Rendez-vous de ce mois
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as count 
        FROM rendezvous 
        WHERE personnel_id = ? 
        AND MONTH(date) = MONTH(CURRENT_DATE()) 
        AND YEAR(date) = YEAR(CURRENT_DATE())
    ");
    $stmt->execute([$personnel_id]);
    $rdv_ce_mois = $stmt->fetch()['count'];

    // Rendez-vous d'aujourd'hui
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as count 
        FROM rendezvous 
        WHERE personnel_id = ? 
        AND DATE(date) = CURRENT_DATE()
    ");
    $stmt->execute([$personnel_id]);
    $rdv_aujourd_hui = $stmt->fetch()['count'];

    // Rendez-vous en attente
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as count 
        FROM rendezvous 
        WHERE personnel_id = ? 
        AND status = 'pending'
    ");
    $stmt->execute([$personnel_id]);
    $rdv_en_attente = $stmt->fetch()['count'];

    // Rendez-vous confirmés
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as count 
        FROM rendezvous 
        WHERE personnel_id = ? 
        AND status = 'confirmed'
    ");
    $stmt->execute([$personnel_id]);
    $rdv_confirmes = $stmt->fetch()['count'];

    // Chiffre d'affaires du mois
    $stmt = $pdo->prepare("
        SELECT COALESCE(SUM(prix), 0) as total 
        FROM rendezvous 
        WHERE personnel_id = ? 
        AND status = 'completed'
        AND MONTH(date) = MONTH(CURRENT_DATE()) 
        AND YEAR(date) = YEAR(CURRENT_DATE())
    ");
    $stmt->execute([$personnel_id]);
    $ca_mois = $stmt->fetch()['total'];

    // Taux de présence (rendez-vous confirmés et complétés / total des rendez-vous passés)
    $stmt = $pdo->prepare("
        SELECT 
            COUNT(CASE WHEN status IN ('confirmed', 'completed') THEN 1 END) as confirmes,
            COUNT(*) as total
        FROM rendezvous 
        WHERE personnel_id = ? 
        AND date < CURRENT_DATE()
    ");
    $stmt->execute([$personnel_id]);
    $presence_data = $stmt->fetch();
    $taux_presence = $presence_data['total'] > 0 ? round(($presence_data['confirmes'] / $presence_data['total']) * 100) : 0;

    // Messages récents (envoyés au personnel)
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as count 
        FROM messages 
        WHERE user_id = ? 
        AND date_envoi >= DATE_SUB(NOW(), INTERVAL 7 DAY)
    ");
    $stmt->execute([$personnel_id]);
    $messages_recents = $stmt->fetch()['count'];

    // Services actifs du personnel
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as count 
        FROM service_personnel sp
        INNER JOIN services s ON sp.service_id = s.id
        WHERE sp.personnel_id = ? 
        AND sp.is_active = 1 
        AND s.is_active = 1
    ");
    $stmt->execute([$personnel_id]);
    $services_actifs = $stmt->fetch()['count'];

    // Prochains rendez-vous (aujourd'hui et demain)
    $stmt = $pdo->prepare("
        SELECT r.*, u.name as client_name, s.name as service_name
        FROM rendezvous r
        INNER JOIN users u ON r.client_id = u.id
        INNER JOIN services s ON r.service_id = s.id
        WHERE r.personnel_id = ? 
        AND r.date BETWEEN CURRENT_DATE() AND DATE_ADD(CURRENT_DATE(), INTERVAL 15 DAY)
        AND r.status IN ('pending', 'confirmed')
        ORDER BY r.date ASC, r.heure ASC
        LIMIT 5
    ");
    $stmt->execute([$personnel_id]);
    $prochains_rdv = $stmt->fetchAll();

    // Rendez-vous récents nécessitant une action
    $stmt = $pdo->prepare("
        SELECT r.*, u.name as client_name, s.name as service_name
        FROM rendezvous r
        INNER JOIN users u ON r.client_id = u.id
        INNER JOIN services s ON r.service_id = s.id
        WHERE r.personnel_id = ? 
        AND r.status = 'pending'
        ORDER BY r.created_at DESC
        LIMIT 5
    ");
    $stmt->execute([$personnel_id]);
    $rdv_en_attente_details = $stmt->fetchAll();

    // Récupérer les services du personnel
    $stmt = $pdo->prepare("
        SELECT s.name, s.price, s.duration, sp.is_active
        FROM services s
        INNER JOIN service_personnel sp ON s.id = sp.service_id
        WHERE sp.personnel_id = ?
        ORDER BY s.name
    ");
    $stmt->execute([$personnel_id]);
    $mes_services = $stmt->fetchAll();

    // Récupérer le nom de l'utilisateur depuis la table users
    $stmt = $pdo->prepare("SELECT name, email FROM users WHERE id = ?");
    $stmt->execute([$personnel_id]);
    $user_info = $stmt->fetch();
    $nom_utilisateur = $user_info['name'] ?? "Personnel";
    $email_utilisateur = $user_info['email'] ?? "";

    // Statistiques par service (ce mois)
    $stmt = $pdo->prepare("
        SELECT s.name, COUNT(r.id) as nb_rdv, COALESCE(SUM(r.prix), 0) as ca
        FROM services s
        INNER JOIN service_personnel sp ON s.id = sp.service_id
        LEFT JOIN rendezvous r ON s.id = r.service_id AND r.personnel_id = ? 
            AND MONTH(r.date) = MONTH(CURRENT_DATE()) 
            AND YEAR(r.date) = YEAR(CURRENT_DATE())
            AND r.status IN ('confirmed', 'completed')
        WHERE sp.personnel_id = ? AND sp.is_active = 1
        GROUP BY s.id, s.name
        ORDER BY nb_rdv DESC
    ");
    $stmt->execute([$personnel_id, $personnel_id]);
    $stats_services = $stmt->fetchAll();

} catch (PDOException $e) {
    // En cas d'erreur, utiliser des valeurs par défaut
    $rdv_ce_mois = 0;
    $rdv_aujourd_hui = 0;
    $rdv_en_attente = 0;
    $rdv_confirmes = 0;
    $ca_mois = 0;
    $taux_presence = 0;
    $messages_recents = 0;
    $services_actifs = 0;
    $nom_utilisateur = "Utilisateur";
    $email_utilisateur = "";
    $prochains_rdv = [];
    $rdv_en_attente_details = [];
    $mes_services = [];
    $stats_services = [];
    
    // Log de l'erreur pour debug
    error_log("Erreur Dashboard: " . $e->getMessage());
}

// Fonction pour formater le prix
function formatPrice($price) {
    return number_format($price, 2, ',', ' ') . ' DH';
}

// Fonction pour formater la durée
function formatDuration($duration) {
    $hours = intval($duration / 60);
    $minutes = $duration % 60;
    if ($hours > 0) {
        return $hours . 'h' . ($minutes > 0 ? ' ' . $minutes . 'min' : '');
    }
    return $minutes . 'min';
}

// Fonction pour formater le statut en français
function formatStatus($status) {
    $statuses = [
        'pending' => 'En attente',
        'confirmed' => 'Confirmé',
        'completed' => 'Terminé',
        'cancelled' => 'Annulé'
    ];
    return $statuses[$status] ?? $status;
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Espace Personnel - Dashboard</title>
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
            background: #f8fafc;
            overflow-x: hidden;
        }

        /* Header moderne */
        header {
            position: fixed;
            top: 0;
            width: 100%;
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            border-bottom: 1px solid rgba(0, 0, 0, 0.1);
            z-index: 1000;
            transition: all 0.3s ease;
        }

        .container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 0 2rem;
        }

        nav {
            display: flex;
            justify-content: center;
            align-items: center;
            height: 70px;
            gap: 2rem;
        }

        nav a {
            text-decoration: none;
            color: #333;
            font-weight: 500;
            padding: 0.5rem 1rem;
            border-radius: 8px;
            transition: all 0.3s ease;
            position: relative;
        }

        nav a:hover {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            transform: translateY(-2px);
        }

        nav a[href="../logout.php"] {
            background: #ff4757;
            color: white;
        }

        nav a[href="../logout.php"]:hover {
            background: #ff3742;
            box-shadow: 0 5px 15px rgba(255, 71, 87, 0.3);
        }

        /* Main content */
        main {
            padding: 100px 0 4rem;
            min-height: 100vh;
        }

        /* Welcome section */
        .welcome-section {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 25px;
            padding: 3rem;
            color: white;
            text-align: center;
            margin-bottom: 3rem;
            position: relative;
            overflow: hidden;
        }

        .welcome-section::before {
            content: '';
            position: absolute;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><circle cx="50" cy="50" r="2" fill="rgba(255,255,255,0.1)"/></svg>') repeat;
            animation: float 20s ease-in-out infinite;
        }

        .welcome-section h1 {
            font-size: 2.5rem;
            font-weight: 700;
            margin-bottom: 1rem;
            position: relative;
            z-index: 2;
        }

        .welcome-section p {
            font-size: 1.2rem;
            opacity: 0.9;
            position: relative;
            z-index: 2;
        }

        /* Quick stats */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1.5rem;
            margin-bottom: 3rem;
        }

        .stat-card {
            background: white;
            border-radius: 15px;
            padding: 2rem;
            text-align: center;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }

        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.4), transparent);
            transition: left 0.5s;
        }

        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.15);
        }

        .stat-card:hover::before {
            left: 100%;
        }

        .stat-number {
            font-size: 2.5rem;
            font-weight: 700;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            margin-bottom: 0.5rem;
        }

        .stat-label {
            color: #666;
            font-weight: 500;
            font-size: 0.9rem;
        }

        /* Dashboard layout */
        .dashboard-layout {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 2rem;
            margin-bottom: 3rem;
        }

        .main-content {
            display: grid;
            gap: 2rem;
        }

        .sidebar {
            display: grid;
            gap: 2rem;
        }

        /* Dashboard cards */
        .dashboard-card {
            background: white;
            border-radius: 20px;
            padding: 2.5rem;
            box-shadow: 0 8px 30px rgba(0, 0, 0, 0.1);
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }

        .dashboard-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 20px 50px rgba(0, 0, 0, 0.15);
        }

        .card-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 1.5rem;
        }

        .card-title {
            font-size: 1.5rem;
            font-weight: 600;
            color: #333;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .card-icon {
            font-size: 1.5rem;
        }

        .card-content {
            color: #666;
            line-height: 1.7;
        }

        /* Prochains rendez-vous */
        .rdv-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 1rem;
            border: 1px solid #e2e8f0;
            border-radius: 10px;
            margin-bottom: 1rem;
            transition: all 0.3s ease;
        }

        .rdv-item:hover {
            background: #f8fafc;
            border-color: #667eea;
        }

        .rdv-info h4 {
            color: #333;
            margin-bottom: 0.25rem;
        }

        .rdv-info p {
            color: #666;
            font-size: 0.9rem;
            margin-bottom: 0.25rem;
        }

        .rdv-time {
            text-align: right;
            color: #667eea;
            font-weight: 600;
        }

        /* Status badges */
        .status-badge {
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
            text-transform: uppercase;
            display: inline-block;
            margin-top: 0.25rem;
        }

        .status-pending {
            background: #fef3c7;
            color: #d97706;
        }

        .status-confirmed {
            background: #d1fae5;
            color: #059669;
        }

        .status-completed {
            background: #dbeafe;
            color: #2563eb;
        }

        .status-cancelled {
            background: #fee2e2;
            color: #dc2626;
        }

        /* Services list */
        .service-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 1rem;
            border-bottom: 1px solid #e2e8f0;
        }

        .service-item:last-child {
            border-bottom: none;
        }

        .service-info h4 {
            color: #333;
            margin-bottom: 0.25rem;
        }

        .service-info p {
            color: #666;
            font-size: 0.9rem;
        }

        .service-stats {
            text-align: right;
            font-size: 0.9rem;
        }

        .service-stats .price {
            color: #059669;
            font-weight: 600;
        }

        /* Buttons */
        .btn {
            display: inline-block;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 0.8rem 2rem;
            border-radius: 10px;
            text-decoration: none;
            font-weight: 600;
            transition: all 0.3s ease;
            border: none;
            cursor: pointer;
            font-size: 0.9rem;
        }

        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(102, 126, 234, 0.4);
        }

        .btn-sm {
            padding: 0.5rem 1rem;
            font-size: 0.8rem;
        }

        .btn-outline {
            background: transparent;
            border: 2px solid #667eea;
            color: #667eea;
        }

        .btn-outline:hover {
            background: #667eea;
            color: white;
        }

        /* Quick actions */
        .quick-actions {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-bottom: 2rem;
        }

        .quick-action {
            background: white;
            border-radius: 15px;
            padding: 1.5rem;
            text-align: center;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
            transition: all 0.3s ease;
            text-decoration: none;
            color: #333;
        }

        .quick-action:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.15);
            color: #333;
        }

        .quick-action-icon {
            font-size: 2rem;
            margin-bottom: 1rem;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .quick-action h3 {
            font-size: 1.1rem;
            margin-bottom: 0.5rem;
        }

        .quick-action p {
            color: #666;
            font-size: 0.9rem;
        }

        /* Animations */
        @keyframes float {
            0%, 100% { transform: translateY(0px) rotate(0deg); }
            50% { transform: translateY(-20px) rotate(180deg); }
        }

        /* Responsive */
        @media (max-width: 1024px) {
            .dashboard-layout {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 768px) {
            .container {
                padding: 0 1rem;
            }

            nav {
                flex-wrap: wrap;
                gap: 1rem;
                padding: 1rem 0;
                height: auto;
            }

            main {
                padding: 140px 0 2rem;
            }

            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }

            .welcome-section {
                padding: 2rem;
            }

            .welcome-section h1 {
                font-size: 2rem;
            }

            .welcome-section p {
                font-size: 1rem;
            }

            .quick-actions {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 480px) {
            nav {
                justify-content: flex-start;
                overflow-x: auto;
                padding-bottom: 1rem;
            }

            .stats-grid {
                grid-template-columns: 1fr;
            }

            .rdv-item {
                flex-direction: column;
                align-items: flex-start;
                gap: 0.5rem;
            }

            .rdv-time {
                text-align: left;
            }
        }

        /* Empty state */
        .empty-state {
            text-align: center;
            padding: 2rem;
            color: #666;
        }

        .empty-state-icon {
            font-size: 3rem;
            margin-bottom: 1rem;
            opacity: 0.5;
        }

        /* Alert message for errors */
        .alert {
            padding: 1rem;
            border-radius: 10px;
            margin-bottom: 1rem;
            background: #fef3c7;
            border: 1px solid #fbbf24;
            color: #92400e;
        }
    </style>
</head>
<body>
    <header>
        <div class="container">
            <nav>
                <a href="#Accueil">Accueil</a>
                <a href="rdv.php">Mes rendez-vous</a>
                <a href="dispo.php">Mes disponibilités</a>
                <a href="contact.php">Support</a>
                <a href="profil.php">Profil</a>
                <a href="../logout.php">Déconnecter</a>
            </nav>
        </div>
    </header>

    <main>
        <div class="container">
            <!-- Section de bienvenue -->
            <div class="welcome-section">
                <h1>Bonjour <?php echo htmlspecialchars($nom_utilisateur); ?></h1>
                <p>Tableau de bord - Gérez votre activité professionnelle</p>
            </div>

            <!-- Statistiques rapides -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-number"><?php echo $rdv_ce_mois; ?></div>
                    <div class="stat-label">Rendez-vous ce mois</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?php echo $rdv_aujourd_hui; ?></div>
                    <div class="stat-label">Rendez-vous aujourd'hui</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?php echo $rdv_en_attente; ?></div>
                    <div class="stat-label">En attente</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?php echo $rdv_confirmes; ?></div>
                    <div class="stat-label">Confirmés</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?php echo formatPrice($ca_mois); ?></div>
                    <div class="stat-label">CA ce mois</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?php echo $taux_presence; ?>%</div>
                    <div class="stat-label">Taux de présence</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?php echo $services_actifs; ?></div>
                    <div class="stat-label">Services actifs</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?php echo $messages_recents; ?></div>
                    <div class="stat-label">Messages envoyés</div>
                </div>
            </div>

            <!-- Actions rapides -->
            <div class="quick-actions">
                <a href="rdv.php" class="quick-action">
                    <div class="quick-action-icon">📅</div>
                    <h3>Mes Rendez-vous</h3>
                    <p>Gérer mes appointments</p>
                </a>
                <a href="dispo.php" class="quick-action">
                    <div class="quick-action-icon">🕒</div>
                    <h3>Disponibilités</h3>
                    <p>Définir mes créneaux</p>
                </a>
                
                <a href="histo.php" class="quick-action">
                    <div class="quick-action-icon">💬</div>
                    <h3>Messages</h3>
                    <p>Communications avec l'admin</p>
                </a>
            </div>

            <!-- Layout principal -->
            <div class="dashboard-layout">
                <div class="main-content">
                    <!-- Prochains rendez-vous -->
                    <div class="dashboard-card">
                        <div class="card-header">
                            <h3 class="card-title">
                                <span class="card-icon">📅</span>
                                Prochains rendez-vous
                            </h3>
                            <a href="rdv.php" class="btn btn-sm">Voir tout</a>
                        </div>
                        <div class="card-content">
                            <?php if (empty($prochains_rdv)): ?>
                                <div class="empty-state">
                                    <div class="empty-state-icon">📅</div>
                                    <p>Aucun rendez-vous programmé pour les prochains jours</p>
                                </div>
                            <?php else: ?>
                                <?php foreach ($prochains_rdv as $rdv): ?>
                                    <div class="rdv-item">
                                        <div class="rdv-info">
                                            <h4><?php echo htmlspecialchars($rdv['client_name']); ?></h4>
                                            <p><?php echo htmlspecialchars($rdv['service_name']); ?> - <?php echo formatPrice($rdv['prix']); ?></p>
                                            <span class="status-badge status-<?php echo $rdv['status']; ?>">
                                                <?php echo formatStatus($rdv['status']); ?>
                                            </span>
                                            <?php if (!empty($rdv['notes'])): ?>
                                                <p><small>Note: <?php echo htmlspecialchars($rdv['notes']); ?></small></p>
                                            <?php endif; ?>
                                        </div>
                                        <div class="rdv-time">
                                            <strong><?php echo date('d/m', strtotime($rdv['date'])); ?></strong><br>
                                            <span><?php echo date('H:i', strtotime($rdv['heure'])); ?></span><br>
                                            <small><?php echo formatDuration($rdv['duration']); ?></small>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Rendez-vous en attente -->
                    <?php if (!empty($rdv_en_attente_details)): ?>
                    <div class="dashboard-card">
                        <div class="card-header">
                            <h3 class="card-title">
                                <span class="card-icon">⏳</span>
                                Demandes en attente
                            </h3>
                            <span class="status-badge status-pending"><?php echo count($rdv_en_attente_details); ?> demandes</span>
                        </div>
                        <div class="card-content">
                            <?php foreach ($rdv_en_attente_details as $rdv): ?>
                                <div class="rdv-item">
                                    <div class="rdv-info">
                                        <h4><?php echo htmlspecialchars($rdv['client_name']); ?></h4>
                                        <p><?php echo htmlspecialchars($rdv['service_name']); ?> - <?php echo formatPrice($rdv['prix']); ?></p>
                                        <small>Demandé le <?php echo date('d/m/Y à H:i', strtotime($rdv['created_at'])); ?></small>
                                        <?php if (!empty($rdv['notes'])): ?>
                                            <p><small>Note: <?php echo htmlspecialchars($rdv['notes']); ?></small></p>
                                        <?php endif; ?>
                                    </div>
                                    <div class="rdv-time">
                                        <strong><?php echo date('d/m', strtotime($rdv['date'])); ?></strong><br>
                                        <span><?php echo date('H:i', strtotime($rdv['heure'])); ?></span><br>
                                        <small><?php echo formatDuration($rdv['duration']); ?></small>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>

                <div class="sidebar">
                    <!-- Mes services -->
                    <div class="dashboard-card">
                        <div class="card-header">
                            <h3 class="card-title">
                                <span class="card-icon">⚡</span>
                                Mes services
                            </h3>
                            
                        </div>
                        <div class="card-content">
                            <?php if (empty($mes_services)): ?>
                                <div class="empty-state">
                                    <div class="empty-state-icon">⚡</div>
                                    <p>Aucun service configuré</p>
                                    <a href="services.php" class="btn btn-sm" style="margin-top: 1rem;">Ajouter un service</a>
                                </div>
                            <?php else: ?>
                                <?php foreach ($mes_services as $service): ?>
                                    <div class="service-item">
                                        <div class="service-info">
                                            <h4><?php echo htmlspecialchars($service['name']); ?></h4>
                                            <p><?php echo formatDuration($service['duration']); ?></p>
                                            <?php if (!$service['is_active']): ?>
                                                <span class="status-badge status-cancelled">Inactif</span>
                                            <?php endif; ?>
                                        </div>
                                        <div class="service-stats">
                                            <div class="price"><?php echo formatPrice($service['price']); ?></div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Statistiques par service -->
                    <?php if (!empty($stats_services)): ?>
                    <div class="dashboard-card">
                        <div class="card-header">
                            <h3 class="card-title">
                                <span class="card-icon">📊</span>
                                Performance ce mois
                            </h3>
                        </div>
                        <div class="card-content">
                            <?php foreach ($stats_services as $stat): ?>
                                <div class="service-item">
                                    <div class="service-info">
                                        <h4><?php echo htmlspecialchars($stat['name']); ?></h4>
                                        <p><?php echo $stat['nb_rdv']; ?> rendez-vous</p>
                                    </div>
                                    <div class="service-stats">
                                        <div class="price"><?php echo formatPrice($stat['ca']); ?></div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endif; ?>

                    <!-- Informations du profil -->
                    <div class="dashboard-card">
                        <div class="card-header">
                            <h3 class="card-title">
                                <span class="card-icon">👤</span>
                                Mon profil
                            </h3>
                            <a href="profil.php" class="btn btn-sm">Modifier</a>
                        </div>
                        <div class="card-content">
                            <div class="service-item">
                                <div class="service-info">
                                    <h4>Nom</h4>
                                    <p><?php echo htmlspecialchars($nom_utilisateur); ?></p>
                                </div>
                            </div>
                            <div class="service-item">
                                <div class="service-info">
                                    <h4>Email</h4>
                                    <p><?php echo htmlspecialchars($email_utilisateur); ?></p>
                                </div>
                            </div>
                            <div class="service-item">
                                <div class="service-info">
                                    <h4>Services actifs</h4>
                                    <p><?php echo $services_actifs; ?> service(s)</p>
                                </div>
                            </div>
                            <div class="service-item">
                                <div class="service-info">
                                    <h4>Taux de présence</h4>
                                    <p><?php echo $taux_presence; ?>%</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <script>
        // Animation au scroll
        window.addEventListener('scroll', function() {
            const header = document.querySelector('header');
            if (window.scrollY > 50) {
                header.style.background = 'rgba(255, 255, 255, 0.98)';
                header.style.boxShadow = '0 2px 20px rgba(0, 0, 0, 0.1)';
            } else {
                header.style.background = 'rgba(255, 255, 255, 0.95)';
                header.style.boxShadow = 'none';
            }
        });

        // Animation des cartes au chargement
        document.addEventListener('DOMContentLoaded', function() {
            const cards = document.querySelectorAll('.stat-card, .dashboard-card, .quick-action');
            cards.forEach((card, index) => {
                card.style.opacity = '0';
                card.style.transform = 'translateY(20px)';
                setTimeout(() => {
                    card.style.transition = 'all 0.5s ease';
                    card.style.opacity = '1';
                    card.style.transform = 'translateY(0)';
                }, index * 100);
            });
        });

        // Fonction pour actualiser les données automatiquement
        function refreshData() {
            // Recharger la page toutes les 5 minutes pour avoir des données fraîches
            setTimeout(() => {
                location.reload();
            }, 300000); // 5 minutes
        }

        // Démarrer l'actualisation automatique
        refreshData();

        // Notification pour les nouveaux rendez-vous en attente
        <?php if ($rdv_en_attente > 0): ?>
        document.addEventListener('DOMContentLoaded', function() {
            // Créer une notification discrète
            const notification = document.createElement('div');
            notification.style.cssText = `
                position: fixed;
                top: 90px;
                right: 20px;
                background: linear-gradient(135deg, #ff6b6b, #ee5a24);
                color: white;
                padding: 1rem 1.5rem;
                border-radius: 10px;
                box-shadow: 0 5px 15px rgba(0,0,0,0.2);
                z-index: 1001;
                font-weight: 600;
                cursor: pointer;
                transform: translateX(300px);
                transition: transform 0.3s ease;
            `;
            notification.innerHTML = `
                <span style="margin-right: 0.5rem;">🔔</span>
                ${<?php echo $rdv_en_attente; ?>} demande(s) en attente
            `;
            
            document.body.appendChild(notification);
            
            // Animer l'entrée
            setTimeout(() => {
                notification.style.transform = 'translateX(0)';
            }, 1000);
            
            // Rediriger vers les rendez-vous au clic
            notification.addEventListener('click', () => {
                window.location.href = 'rdv.php';
            });
            
            // Masquer après 5 secondes
            setTimeout(() => {
                notification.style.transform = 'translateX(300px)';
                setTimeout(() => notification.remove(), 300);
            }, 5000);
        });
        <?php endif; ?>
    </script>
</body>
</html>