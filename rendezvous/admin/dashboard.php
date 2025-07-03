<?php
session_start();

// Vérifier si l'utilisateur est connecté et est admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../login.php');
    exit();
}

// Configuration de la base de données
try {
    $pdo = new PDO('mysql:host=localhost;dbname=rendezvs', 'root', '');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    die("Erreur de connexion : " . $e->getMessage());
}

// Récupérer les statistiques avancées
try {
    // Statistiques des utilisateurs
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM users WHERE role = 'client'");
    $total_clients = $stmt->fetch()['total'];
    
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM users WHERE role = 'personnel'");
    $total_personnel = $stmt->fetch()['total'];
    
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM users WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY) AND role != 'admin'");
    $new_users_week = $stmt->fetch()['total'];
    
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM users WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY) AND role != 'admin'");
    $new_users_month = $stmt->fetch()['total'];

    // Statistiques des rendez-vous
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM rendezvous WHERE DATE(date) = CURDATE()");
    $rdv_today = $stmt->fetch()['total'];
    
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM rendezvous WHERE DATE(date) = DATE_ADD(CURDATE(), INTERVAL 1 DAY)");
    $rdv_tomorrow = $stmt->fetch()['total'];
    
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM rendezvous WHERE WEEK(date) = WEEK(CURDATE()) AND YEAR(date) = YEAR(CURDATE())");
    $rdv_week = $stmt->fetch()['total'];
    
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM rendezvous WHERE MONTH(date) = MONTH(CURDATE()) AND YEAR(date) = YEAR(CURDATE())");
    $rdv_month = $stmt->fetch()['total'];
    
    // Statistiques par statut
    $stmt = $pdo->query("SELECT status, COUNT(*) as count FROM rendezvous GROUP BY status");
    $rdv_by_status = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
    
    // Messages
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM messages");
    $total_messages = $stmt->fetch()['total'];
    
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM messages WHERE DATE(date_envoi) >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)");
    $messages_week = $stmt->fetch()['total'];

    // Services
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM services WHERE is_active = 1");
    $active_services = $stmt->fetch()['total'];
    
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM services WHERE is_active = 0");
    $inactive_services = $stmt->fetch()['total'];

    // Chiffre d'affaires
    $stmt = $pdo->query("SELECT COALESCE(SUM(prix), 0) as total FROM rendezvous WHERE status = 'completed' AND MONTH(date) = MONTH(CURDATE()) AND YEAR(date) = YEAR(CURDATE())");
    $revenue_month = $stmt->fetch()['total'];
    
    $stmt = $pdo->query("SELECT COALESCE(SUM(prix), 0) as total FROM rendezvous WHERE status = 'completed' AND DATE(date) = CURDATE()");
    $revenue_today = $stmt->fetch()['total'];

    // Derniers rendez-vous avec plus de détails
    $stmt = $pdo->prepare("
        SELECT r.*, 
               uc.name as client_name, 
               uc.email as client_email,
               up.name as personnel_name,
               s.name as service_name,
               s.duration as service_duration
        FROM rendezvous r 
        JOIN users uc ON r.client_id = uc.id 
        JOIN users up ON r.personnel_id = up.id 
        JOIN services s ON r.service_id = s.id
        ORDER BY r.created_at DESC 
        LIMIT 8
    ");
    $stmt->execute();
    $recent_rdv = $stmt->fetchAll();

    // Top services les plus demandés
    $stmt = $pdo->prepare("
        SELECT s.name, s.price, COUNT(r.id) as bookings, COALESCE(SUM(r.prix), 0) as revenue
        FROM services s
        LEFT JOIN rendezvous r ON s.id = r.service_id
        WHERE s.is_active = 1
        GROUP BY s.id, s.name, s.price
        ORDER BY bookings DESC
        LIMIT 5
    ");
    $stmt->execute();
    $top_services = $stmt->fetchAll();

    // Personnel le plus actif
    $stmt = $pdo->prepare("
        SELECT u.name, u.email, COUNT(r.id) as appointments, 
               COALESCE(SUM(r.prix), 0) as revenue,
               COUNT(CASE WHEN r.status = 'completed' THEN 1 END) as completed
        FROM users u
        LEFT JOIN rendezvous r ON u.id = r.personnel_id
        WHERE u.role = 'personnel'
        GROUP BY u.id, u.name, u.email
        ORDER BY appointments DESC
        LIMIT 5
    ");
    $stmt->execute();
    $top_personnel = $stmt->fetchAll();

    // Messages récents - CORRECTION: enlever la colonne "services" qui n'existe pas
    $stmt = $pdo->prepare("
        SELECT nom, email, sujet, LEFT(message, 100) as message_preview, date_envoi
        FROM messages 
        ORDER BY date_envoi DESC 
        LIMIT 5
    ");
    $stmt->execute();
    $recent_messages = $stmt->fetchAll();

    // Données pour les graphiques (derniers 7 jours)
    $stmt = $pdo->prepare("
        SELECT DATE(date) as jour, COUNT(*) as count
        FROM rendezvous 
        WHERE date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
        GROUP BY DATE(date)
        ORDER BY DATE(date)
    ");
    $stmt->execute();
    $rdv_chart_data = $stmt->fetchAll();

} catch(PDOException $e) {
    $error_message = "Erreur lors de la récupération des données : " . $e->getMessage();
}

// Nom de l'admin connecté
$admin_name = $_SESSION['name'] ?? 'Administrateur';
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Administrateur - Système de Rendez-vous</title>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/3.9.1/chart.min.js"></script>
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
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
            min-height: 100vh;
        }

        /* Header moderne avec glassmorphism */
        header {
            position: fixed;
            top: 0;
            width: 100%;
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(20px);
            border-bottom: 1px solid rgba(255, 255, 255, 0.2);
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
            height: 80px;
            gap: 1rem;
            flex-wrap: wrap;
        }

        nav a {
            text-decoration: none;
            color: #333;
            font-weight: 600;
            padding: 0.7rem 1.5rem;
            border-radius: 25px;
            transition: all 0.3s ease;
            position: relative;
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.2);
        }

        nav a:hover {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            transform: translateY(-3px);
            box-shadow: 0 10px 25px rgba(102, 126, 234, 0.3);
        }

        nav a[href="../logout.php"] {
            background: linear-gradient(135deg, #ff4757 0%, #ff3838 100%);
            color: white;
        }

        nav a[href="../logout.php"]:hover {
            background: linear-gradient(135deg, #ff3838 0%, #ff2f2f 100%);
            box-shadow: 0 10px 25px rgba(255, 71, 87, 0.4);
        }

        /* Main content */
        main {
            padding: 120px 0 4rem;
            min-height: 100vh;
        }

        /* Welcome section avec animation */
        .welcome-section {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 30px;
            padding: 4rem;
            color: white;
            text-align: center;
            margin-bottom: 3rem;
            position: relative;
            overflow: hidden;
            box-shadow: 0 20px 60px rgba(102, 126, 234, 0.3);
        }

        .welcome-section::before {
            content: '';
            position: absolute;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: radial-gradient(circle, rgba(255,255,255,0.1) 1px, transparent 1px);
            background-size: 50px 50px;
            animation: float 20s ease-in-out infinite;
        }

        .welcome-section h1 {
            font-size: 3rem;
            font-weight: 800;
            margin-bottom: 1rem;
            position: relative;
            z-index: 2;
            text-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }

        .welcome-section p {
            font-size: 1.3rem;
            opacity: 0.95;
            position: relative;
            z-index: 2;
        }

        /* Quick stats avec nouveaux indicateurs */
        .stats-section {
            margin-bottom: 3rem;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .stat-card {
            background: rgba(255, 255, 255, 0.9);
            backdrop-filter: blur(20px);
            border-radius: 20px;
            padding: 2rem;
            text-align: center;
            box-shadow: 0 8px 30px rgba(0, 0, 0, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.2);
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
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.3), transparent);
            transition: left 0.6s;
        }

        .stat-card:hover {
            transform: translateY(-8px);
            box-shadow: 0 20px 50px rgba(0, 0, 0, 0.15);
        }

        .stat-card:hover::before {
            left: 100%;
        }

        .stat-number {
            font-size: 2.5rem;
            font-weight: 800;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            margin-bottom: 0.5rem;
            display: block;
        }

        .stat-label {
            color: #666;
            font-weight: 600;
            font-size: 0.95rem;
        }

        .stat-trend {
            font-size: 0.8rem;
            margin-top: 0.5rem;
            padding: 0.2rem 0.5rem;
            border-radius: 15px;
            font-weight: 500;
        }

        .trend-up {
            background: #d4edda;
            color: #155724;
        }

        .trend-down {
            background: #f8d7da;
            color: #721c24;
        }

        /* Dashboard grid amélioré */
        .dashboard-content {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 2rem;
            margin-bottom: 3rem;
        }

        .main-content {
            display: flex;
            flex-direction: column;
            gap: 2rem;
        }

        .sidebar-content {
            display: flex;
            flex-direction: column;
            gap: 2rem;
        }

        .dashboard-card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            border-radius: 25px;
            padding: 2.5rem;
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.2);
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }

        .dashboard-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.4), transparent);
            transition: left 0.5s;
        }

        .dashboard-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 25px 60px rgba(0, 0, 0, 0.15);
        }

        .dashboard-card:hover::before {
            left: 100%;
        }

        .card-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 2rem;
        }

        .card-title {
            font-size: 1.4rem;
            font-weight: 700;
            color: #333;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .card-icon {
            font-size: 1.5rem;
        }

        /* Tableaux stylisés */
        .data-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 1rem;
        }

        .data-table th {
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            padding: 1rem;
            text-align: left;
            font-weight: 600;
            color: #495057;
            border-bottom: 2px solid #dee2e6;
        }

        .data-table td {
            padding: 1rem;
            border-bottom: 1px solid #dee2e6;
            transition: all 0.3s ease;
        }

        .data-table tr:hover {
            background: rgba(102, 126, 234, 0.05);
        }

        /* Status badges */
        .status-badge {
            padding: 0.4rem 1rem;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .status-pending { background: #fff3cd; color: #856404; }
        .status-confirmed { background: #d4edda; color: #155724; }
        .status-cancelled { background: #f8d7da; color: #721c24; }
        .status-completed { background: #d1ecf1; color: #0c5460; }

        /* Graphiques */
        .chart-container {
            position: relative;
            height: 300px;
            margin-top: 1rem;
        }

        /* Boutons modernes */
        .btn {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 0.8rem 1.5rem;
            border-radius: 15px;
            text-decoration: none;
            font-weight: 600;
            transition: all 0.3s ease;
            border: none;
            cursor: pointer;
            font-size: 0.9rem;
        }

        .btn:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 25px rgba(102, 126, 234, 0.4);
        }

        .btn-small {
            padding: 0.5rem 1rem;
            font-size: 0.8rem;
        }

        /* Alertes */
        .alert {
            padding: 1.5rem;
            margin-bottom: 2rem;
            border-radius: 15px;
            background: linear-gradient(135deg, #f8d7da 0%, #f5c6cb 100%);
            color: #721c24;
            border: 1px solid #f5c6cb;
            backdrop-filter: blur(10px);
        }

        /* Quick actions */
        .quick-actions {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-bottom: 2rem;
        }

        .quick-action {
            background: rgba(255, 255, 255, 0.9);
            border-radius: 15px;
            padding: 1.5rem;
            text-align: center;
            text-decoration: none;
            color: #333;
            transition: all 0.3s ease;
            border: 1px solid rgba(255, 255, 255, 0.2);
        }

        .quick-action:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
            color: #667eea;
        }

        .quick-action-icon {
            font-size: 2rem;
            margin-bottom: 0.5rem;
            display: block;
        }

        /* Revenue display */
        .revenue-card {
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
            color: white;
        }

        .revenue-number {
            font-size: 2rem;
            font-weight: 800;
            margin-bottom: 0.5rem;
        }

        /* Animations */
        @keyframes float {
            0%, 100% { transform: translateY(0px) rotate(0deg); }
            50% { transform: translateY(-20px) rotate(180deg); }
        }

        @keyframes slideIn {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .slide-in {
            animation: slideIn 0.6s ease forwards;
        }

        /* Responsive */
        @media (max-width: 1200px) {
            .dashboard-content {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 768px) {
            .container {
                padding: 0 1rem;
            }

            nav {
                height: auto;
                padding: 1rem 0;
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

            .quick-actions {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        @media (max-width: 480px) {
            .stats-grid {
                grid-template-columns: 1fr;
            }

            .quick-actions {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <header>
        <div class="container">
            <nav>
                <a href="#dashboard">🏠 Dashboard</a>
                <a href="users.php">👥 Utilisateurs</a>
                <a href="rdv.php">📅 Rendez-vous</a>
                <a href="services.php">🛠️ Services</a>
                <a href="stats.php">📊 Statistiques</a>
                <a href="messages.php">💬 Messages</a>
                <a href="profil.php">👤 Profil</a>
                <a href="../logout.php">🚪 Déconnexion</a>
            </nav>
        </div>
    </header>

    <main>
        <div class="container">
            <?php if (isset($error_message)): ?>
                <div class="alert">
                    ❌ <?php echo htmlspecialchars($error_message); ?>
                </div>
            <?php endif; ?>

            <!-- Section de bienvenue -->
            <section class="welcome-section slide-in">
                <h1>👋 Bienvenue, <?php echo htmlspecialchars($admin_name); ?></h1>
                <p>Tableau de bord administrateur - Système de gestion des rendez-vous</p>
            </section>

            <!-- Actions rapides -->
            <section class="quick-actions slide-in">
                <a href="rdv.php?filter=today" class="quick-action">
                    <span class="quick-action-icon">📅</span>
                    <strong>RDV du jour</strong>
                    <div><?php echo $rdv_today ?? '0'; ?> rendez-vous</div>
                </a>
                <a href="rdv.php?status=pending" class="quick-action">
                    <span class="quick-action-icon">⏳</span>
                    <strong>En attente</strong>
                    <div><?php echo $rdv_by_status['pending'] ?? '0'; ?> à valider</div>
                </a>
                <a href="messages.php" class="quick-action">
                    <span class="quick-action-icon">📨</span>
                    <strong>Nouveaux messages</strong>
                    <div><?php echo $messages_week ?? '0'; ?> cette semaine</div>
                </a>
                <a href="users.php?filter=recent" class="quick-action">
                    <span class="quick-action-icon">👤</span>
                    <strong>Nouveaux utilisateurs</strong>
                    <div><?php echo $new_users_week ?? '0'; ?> cette semaine</div>
                </a>
            </section>

            <!-- Statistiques principales -->
            <section class="stats-section">
                <div class="stats-grid">
                    <div class="stat-card slide-in">
                        <div class="stat-number"><?php echo $total_clients ?? '0'; ?></div>
                        <div class="stat-label">👥 Clients inscrits</div>
                        <div class="stat-trend trend-up">+<?php echo $new_users_month ?? '0'; ?> ce mois</div>
                    </div>
                    <div class="stat-card slide-in">
                        <div class="stat-number"><?php echo $total_personnel ?? '0'; ?></div>
                        <div class="stat-label">👨‍💼 Personnel actif</div>
                    </div>
                    <div class="stat-card slide-in">
                        <div class="stat-number"><?php echo $rdv_month ?? '0'; ?></div>
                        <div class="stat-label">📅 RDV ce mois</div>
                        <div class="stat-trend trend-up">+<?php echo $rdv_week ?? '0'; ?> cette semaine</div>
                    </div>
                    <div class="stat-card slide-in">
                        <div class="stat-number"><?php echo $active_services ?? '0'; ?></div>
                        <div class="stat-label">🛠️ Services actifs</div>
                    </div>
                    <div class="stat-card revenue-card slide-in">
                        <div class="revenue-number"><?php echo number_format($revenue_month ?? 0, 0, ',', ' '); ?> DH</div>
                        <div class="stat-label">💰 CA du mois</div>
                        <div style="font-size: 0.9rem; opacity: 0.9;">Aujourd'hui: <?php echo number_format($revenue_today ?? 0, 0, ',', ' '); ?> DH</div>
                    </div>
                    <div class="stat-card slide-in">
                        <div class="stat-number"><?php echo $total_messages ?? '0'; ?></div>
                        <div class="stat-label">📨 Messages reçus</div>
                        <div class="stat-trend trend-up">+<?php echo $messages_week ?? '0'; ?> cette semaine</div>
                    </div>
                </div>
            </section>

            <!-- Contenu principal -->
            <div class="dashboard-content">
                <div class="main-content">
                    <!-- Graphique des rendez-vous -->
                    <div class="dashboard-card slide-in">
                        <div class="card-header">
                            <h3 class="card-title">📈 Évolution des rendez-vous (7 derniers jours)</h3>
                        </div>
                        <div class="chart-container">
                            <canvas id="rdvChart"></canvas>
                        </div>
                    </div>

                    <!-- Derniers rendez-vous -->
                    <div class="dashboard-card slide-in">
                        <div class="card-header">
                            <h3 class="card-title">📅 Derniers rendez-vous</h3>
                            <a href="rdv.php" class="btn btn-small">Voir tout</a>
                        </div>
                        <?php if (!empty($recent_rdv)): ?>
                            <div style="overflow-x: auto;">
                                <table class="data-table">
                                    <thead>
                                        <tr>
                                            <th>Client</th>
                                            <th>Personnel</th>
                                            <th>Service</th>
                                            <th>Date</th>
                                            <th>Prix</th>
                                            <th>Statut</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach (array_slice($recent_rdv, 0, 6) as $rdv): ?>
                                            <tr>
                                                <td><strong><?php echo htmlspecialchars($rdv['client_name']); ?></strong></td>
                                                <td><?php echo htmlspecialchars($rdv['personnel_name']); ?></td>
                                                <td><?php echo htmlspecialchars($rdv['service_name']); ?></td>
                                                <td>
                                                    <?php echo date('d/m/Y', strtotime($rdv['date'])); ?><br>
                                                    <small><?php echo date('H:i', strtotime($rdv['heure'])); ?></small>
                                                </td>
                                                <td><strong><?php echo number_format($rdv['prix'], 0, ',', ' '); ?> DH</strong></td>
                                                <td>
                                                    <span class="status-badge status-<?php echo $rdv['status']; ?>">
                                                        <?php 
                                                            $statuts = [
                                                                'pending' => 'En attente',
                                                                'confirmed' => 'Confirmé',
                                                                'cancelled' => 'Annulé',
                                                                'completed' => 'Terminé'
                                                            ];
                                                            echo $statuts[$rdv['status']] ?? ucfirst($rdv['status']);
                                                        ?>
                                                    </span>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                           <p style="text-align: center; color: #666; padding: 2rem;">
                                🤷‍♂️ Aucun rendez-vous récent trouvé
                            </p>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="sidebar-content">
                    <!-- Top services -->
                    <div class="dashboard-card slide-in">
                        <div class="card-header">
                            <h3 class="card-title">🏆 Top Services</h3>
                            <a href="services.php" class="btn btn-small">Gérer</a>
                        </div>
                        <?php if (!empty($top_services)): ?>
                            <div class="top-services">
                                <?php foreach ($top_services as $service): ?>
                                    <div style="display: flex; justify-content: space-between; align-items: center; padding: 1rem 0; border-bottom: 1px solid #eee;">
                                        <div>
                                            <strong><?php echo htmlspecialchars($service['name']); ?></strong><br>
                                            <small><?php echo number_format($service['price'], 0, ',', ' '); ?> DH • <?php echo $service['bookings']; ?> réservations</small>
                                        </div>
                                        <div style="text-align: right;">
                                            <strong><?php echo number_format($service['revenue'], 0, ',', ' '); ?> DH</strong>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <p style="text-align: center; color: #666; padding: 1rem;">Aucun service trouvé</p>
                        <?php endif; ?>
                    </div>

                    <!-- Personnel actif -->
                    <div class="dashboard-card slide-in">
                        <div class="card-header">
                            <h3 class="card-title">👨‍💼 Personnel le plus actif</h3>
                            <a href="users.php?role=personnel" class="btn btn-small">Voir tout</a>
                        </div>
                        <?php if (!empty($top_personnel)): ?>
                            <div class="top-personnel">
                                <?php foreach ($top_personnel as $personnel): ?>
                                    <div style="padding: 1rem 0; border-bottom: 1px solid #eee;">
                                        <div style="display: flex; justify-content: space-between;">
                                            <strong><?php echo htmlspecialchars($personnel['name']); ?></strong>
                                            <span class="stat-number" style="font-size: 1.2rem;"><?php echo $personnel['appointments']; ?></span>
                                        </div>
                                        <small style="color: #666;">
                                            <?php echo $personnel['completed']; ?> terminés • 
                                            <?php echo number_format($personnel['revenue'], 0, ',', ' '); ?> DH CA
                                        </small>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <p style="text-align: center; color: #666; padding: 1rem;">Aucun personnel trouvé</p>
                        <?php endif; ?>
                    </div>

                    <!-- Messages récents -->
                    <div class="dashboard-card slide-in">
                        <div class="card-header">
                            <h3 class="card-title">📨 Messages récents</h3>
                            <a href="messages.php" class="btn btn-small">Voir tout</a>
                        </div>
                        <?php if (!empty($recent_messages)): ?>
                            <div class="recent-messages">
                                <?php foreach ($recent_messages as $message): ?>
                                    <div style="padding: 1rem 0; border-bottom: 1px solid #eee;">
                                        <div style="display: flex; justify-content: space-between; margin-bottom: 0.5rem;">
                                            <strong><?php echo htmlspecialchars($message['nom']); ?></strong>
                                            <small><?php echo date('d/m H:i', strtotime($message['date_envoi'])); ?></small>
                                        </div>
                                        <div style="font-weight: 600; color: #667eea; margin-bottom: 0.3rem;">
                                            <?php echo htmlspecialchars($message['sujet']); ?>
                                        </div>
                                        <div style="color: #666; font-size: 0.9rem;">
                                            <?php echo htmlspecialchars($message['message_preview']); ?>...
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <p style="text-align: center; color: #666; padding: 1rem;">Aucun message récent</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Statistiques par statut -->
            <div class="dashboard-card slide-in">
                <div class="card-header">
                    <h3 class="card-title">📊 Répartition des rendez-vous par statut</h3>
                </div>
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem;">
                    <?php 
                    $status_labels = [
                        'pending' => ['En attente', '⏳', '#ffc107'],
                        'confirmed' => ['Confirmés', '✅', '#28a745'],
                        'completed' => ['Terminés', '🏁', '#17a2b8'],
                        'cancelled' => ['Annulés', '❌', '#dc3545']
                    ];
                    
                    foreach ($status_labels as $status => $info): 
                        $count = $rdv_by_status[$status] ?? 0;
                    ?>
                        <div style="background: <?php echo $info[2]; ?>15; padding: 1.5rem; border-radius: 15px; text-align: center; border: 2px solid <?php echo $info[2]; ?>30;">
                            <div style="font-size: 2rem; margin-bottom: 0.5rem;"><?php echo $info[1]; ?></div>
                            <div style="font-size: 2rem; font-weight: 800; color: <?php echo $info[2]; ?>;"><?php echo $count; ?></div>
                            <div style="color: #666; font-weight: 600;"><?php echo $info[0]; ?></div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </main>

    <script>
        // Animation d'apparition des cartes
        document.addEventListener('DOMContentLoaded', function() {
            const cards = document.querySelectorAll('.slide-in');
            cards.forEach((card, index) => {
                setTimeout(() => {
                    card.style.opacity = '0';
                    card.style.transform = 'translateY(30px)';
                    card.style.transition = 'all 0.6s ease';
                    
                    setTimeout(() => {
                        card.style.opacity = '1';
                        card.style.transform = 'translateY(0)';
                    }, 100);
                }, index * 100);
            });

            // Graphique des rendez-vous
            const ctx = document.getElementById('rdvChart');
            if (ctx) {
                const chartData = <?php echo json_encode($rdv_chart_data ?? []); ?>;
                
                const labels = chartData.map(item => {
                    const date = new Date(item.jour);
                    return date.toLocaleDateString('fr-FR', { day: '2-digit', month: '2-digit' });
                });
                
                const data = chartData.map(item => item.count);
                
                new Chart(ctx, {
                    type: 'line',
                    data: {
                        labels: labels,
                        datasets: [{
                            label: 'Rendez-vous',
                            data: data,
                            borderColor: 'rgb(102, 126, 234)',
                            backgroundColor: 'rgba(102, 126, 234, 0.1)',
                            borderWidth: 3,
                            fill: true,
                            tension: 0.4,
                            pointBackgroundColor: 'rgb(102, 126, 234)',
                            pointBorderColor: '#fff',
                            pointBorderWidth: 2,
                            pointRadius: 6
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: {
                                display: false
                            }
                        },
                        scales: {
                            y: {
                                beginAtZero: true,
                                grid: {
                                    color: 'rgba(0, 0, 0, 0.1)'
                                }
                            },
                            x: {
                                grid: {
                                    color: 'rgba(0, 0, 0, 0.1)'
                                }
                            }
                        }
                    }
                });
            }

            // Mise à jour automatique toutes les 5 minutes
            setInterval(() => {
                location.reload();
            }, 300000);
        });

        // Notification de nouveaux messages/rendez-vous
        function showNotification(message) {
            if (Notification.permission === 'granted') {
                new Notification('Système de RDV', {
                    body: message,
                    icon: '/favicon.ico'
                });
            }
        }

        // Demander la permission pour les notifications
        if (Notification.permission === 'default') {
            Notification.requestPermission();
        }
    </script>
</body>
</html>