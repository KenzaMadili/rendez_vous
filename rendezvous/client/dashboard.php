<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tableau de Bord Client - Am Soft</title>
    <?php 
    // Démarrer la session si elle n'est pas déjà démarrée
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    // Vérifier si l'utilisateur est connecté
    if (!isset($_SESSION['name']) || empty($_SESSION['name']) || !isset($_SESSION['user_id'])) {
        header("Location: ../login.php");
        exit();
    }

    // Configuration de la base de données
    $host = 'localhost';
    $dbname = 'rendezvs';
    $username = 'root';
    $password = '';

    try {
        $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    } catch(PDOException $e) {
        die("Erreur de connexion : " . $e->getMessage());
    }

    // Récupérer les statistiques de l'utilisateur
    $user_id = $_SESSION['user_id'];
    
    // Compter les rendez-vous à venir
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM rendezvous WHERE client_id = ? AND date >= CURDATE() AND status != 'annule'");
    $stmt->execute([$user_id]);
    $rdv_a_venir = $stmt->fetch()['count'];
    
    // Compter les rendez-vous terminés
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM rendezvous WHERE client_id = ? AND status = 'termine'");
    $stmt->execute([$user_id]);
    $rdv_termines = $stmt->fetch()['count'];
    
    // Compter les services utilisés (distincts)
    $stmt = $pdo->prepare("SELECT COUNT(DISTINCT service_id) as count FROM rendezvous WHERE client_id = ?");
    $stmt->execute([$user_id]);
    $services_utilises = $stmt->fetch()['count'];
    
    // Récupérer les prochains rendez-vous
    $stmt = $pdo->prepare("
        SELECT r.*, s.name as service_name, s.duration, s.price,
               u.name as personnel_name
        FROM rendezvous r 
        JOIN services s ON r.service_id = s.id 
        LEFT JOIN users u ON r.personnel_id = u.id
        WHERE r.client_id = ? AND r.date >= CURDATE() AND r.status != 'annule'
        ORDER BY r.date ASC, r.heure ASC 
        LIMIT 3
    ");
    $stmt->execute([$user_id]);
    $prochains_rdv = $stmt->fetchAll();
    
    // Récupérer les services actifs
    $stmt = $pdo->prepare("SELECT * FROM services WHERE is_active = 1 ORDER BY name ASC LIMIT 4");
    $stmt->execute();
    $services_actifs = $stmt->fetchAll();
    
    // Récupérer les dernières activités
    $stmt = $pdo->prepare("
        SELECT r.*, s.name as service_name, r.created_at
        FROM rendezvous r 
        JOIN services s ON r.service_id = s.id 
        WHERE r.client_id = ? 
        ORDER BY r.created_at DESC 
        LIMIT 5
    ");
    $stmt->execute([$user_id]);
    $dernieres_activites = $stmt->fetchAll();

    // Fonction pour obtenir les initiales
    function getUserInitials($name) {
        if (empty($name)) return 'U';
        $words = explode(' ', trim($name));
        if (count($words) >= 2) {
            return strtoupper(substr($words[0], 0, 1) . substr($words[1], 0, 1));
        }
        return strtoupper(substr($name, 0, 2));
    }
    
    // Variables sécurisées
    $userName = htmlspecialchars($_SESSION['name'], ENT_QUOTES, 'UTF-8');
    $userInitials = getUserInitials($_SESSION['name']);
    
    // Fonction pour formater les dates
    function formatDate($date) {
        return date('d/m/Y', strtotime($date));
    }
    
    function formatTime($time) {
        return date('H:i', strtotime($time));
    }
    
    function getStatusBadge($status) {
        switch($status) {
            case 'confirme': return '<span class="status-badge status-confirmed">Confirmé</span>';
            case 'en_attente': return '<span class="status-badge status-pending">En attente</span>';
            case 'termine': return '<span class="status-badge status-completed">Terminé</span>';
            case 'annule': return '<span class="status-badge status-cancelled">Annulé</span>';
            default: return '<span class="status-badge">' . ucfirst($status) . '</span>';
        }
    }
    ?>
    <style>
        :root {
            --primary-gradient: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            --success-color: #10b981;
            --warning-color: #f59e0b;
            --danger-color: #ef4444;
            --info-color: #3b82f6;
            --glass-bg: rgba(255, 255, 255, 0.95);
            --shadow-light: 0 8px 32px rgba(0, 0, 0, 0.1);
            --shadow-medium: 0 12px 40px rgba(0, 0, 0, 0.15);
            --border-radius: 16px;
            --border-radius-large: 24px;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            line-height: 1.6;
            color: #1f2937;
            background: linear-gradient(135deg, #f8fafc 0%, #e2e8f0 100%);
            overflow-x: hidden;
        }

        /* Header */
        header {
            position: fixed;
            top: 0;
            width: 100%;
            background: var(--glass-bg);
            backdrop-filter: blur(20px);
            border-bottom: 1px solid rgba(255, 255, 255, 0.2);
            z-index: 1000;
            transition: all 0.3s ease;
        }

        .header-content {
            max-width: 1400px;
            margin: 0 auto;
            padding: 0 2rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            height: 80px;
        }

        .logo {
            font-size: 2rem;
            font-weight: 800;
            background: var(--primary-gradient);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .user-menu {
            display: flex;
            align-items: center;
            gap: 1.5rem;
        }

        .user-avatar {
            width: 50px;
            height: 50px;
            background: var(--primary-gradient);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
        }

        .user-avatar:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-medium);
        }

        .user-info {
            display: flex;
            flex-direction: column;
        }

        .user-name {
            font-weight: 600;
            color: #1f2937;
        }

        .user-role {
            font-size: 0.8rem;
            color: var(--success-color);
            font-weight: 500;
        }

        .logout-btn {
            background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
            color: white;
            border: none;
            padding: 0.7rem 1.5rem;
            border-radius: var(--border-radius);
            cursor: pointer;
            font-weight: 600;
            transition: all 0.3s ease;
        }

        .logout-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(239, 68, 68, 0.3);
        }

        /* Main content */
        .main-content {
            padding: 120px 0 4rem;
            min-height: 100vh;
        }

        .container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 0 2rem;
        }

        /* Welcome section */
        .welcome-section {
            background: var(--primary-gradient);
            border-radius: var(--border-radius-large);
            padding: 3rem;
            color: white;
            margin-bottom: 3rem;
            position: relative;
            overflow: hidden;
        }

        .welcome-section::before {
            content: '';
            position: absolute;
            top: -50%;
            right: -50%;
            width: 200%;
            height: 200%;
            background: radial-gradient(circle, rgba(255,255,255,0.1) 1px, transparent 1px);
            background-size: 30px 30px;
            animation: float 20s linear infinite;
        }

        .welcome-content {
            position: relative;
            z-index: 2;
            display: grid;
            grid-template-columns: 1fr auto;
            gap: 2rem;
            align-items: center;
        }

        .welcome-text h1 {
            font-size: 2.5rem;
            font-weight: 800;
            margin-bottom: 0.5rem;
        }

        .welcome-text h2 {
            font-size: 1.8rem;
            font-weight: 600;
            margin-bottom: 1rem;
            opacity: 0.9;
        }

        .welcome-text p {
            font-size: 1.1rem;
            opacity: 0.8;
        }

        /* Stats grid */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 1rem;
        }

        .stat-card {
            background: rgba(255, 255, 255, 0.2);
            backdrop-filter: blur(10px);
            border-radius: var(--border-radius);
            padding: 1.5rem;
            text-align: center;
            border: 1px solid rgba(255, 255, 255, 0.3);
        }

        .stat-number {
            font-size: 2rem;
            font-weight: 800;
            margin-bottom: 0.5rem;
        }

        .stat-label {
            font-size: 0.9rem;
            opacity: 0.9;
        }

        /* Dashboard grid */
        .dashboard-grid {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 2rem;
            margin-bottom: 3rem;
        }

        .main-cards {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 1.5rem;
        }

        .sidebar-cards {
            display: flex;
            flex-direction: column;
            gap: 1.5rem;
        }

        .dashboard-card {
            background: white;
            border-radius: var(--border-radius-large);
            padding: 2rem;
            box-shadow: var(--shadow-light);
            transition: all 0.3s ease;
            border: 1px solid rgba(0, 0, 0, 0.05);
        }

        .dashboard-card:hover {
            transform: translateY(-5px);
            box-shadow: var(--shadow-medium);
        }

        .card-header {
            display: flex;
            align-items: center;
            margin-bottom: 1.5rem;
        }

        .card-icon {
            width: 50px;
            height: 50px;
            border-radius: var(--border-radius);
            background: var(--primary-gradient);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            margin-right: 1rem;
        }

        .card-title {
            font-size: 1.3rem;
            font-weight: 700;
            color: #1f2937;
        }

        .card-content {
            color: #4b5563;
            line-height: 1.6;
            margin-bottom: 1.5rem;
        }

        .btn {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            background: var(--primary-gradient);
            color: white;
            padding: 0.8rem 1.5rem;
            border-radius: var(--border-radius);
            text-decoration: none;
            font-weight: 600;
            transition: all 0.3s ease;
            font-size: 0.9rem;
        }

        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(102, 126, 234, 0.3);
            text-decoration: none;
            color: white;
        }

        /* Sections spéciales */
        .rdv-section, .services-section, .activity-section {
            background: white;
            border-radius: var(--border-radius-large);
            padding: 2rem;
            box-shadow: var(--shadow-light);
            margin-bottom: 2rem;
        }

        .section-title {
            font-size: 1.5rem;
            font-weight: 700;
            color: #1f2937;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        /* Liste des RDV */
        .rdv-list {
            display: flex;
            flex-direction: column;
            gap: 1rem;
        }

        .rdv-item {
            padding: 1.5rem;
            border-radius: var(--border-radius);
            background: #f8fafc;
            border-left: 4px solid;
            transition: all 0.3s ease;
        }

        .rdv-item.confirme { border-left-color: var(--success-color); }
        .rdv-item.en_attente { border-left-color: var(--warning-color); }
        .rdv-item.termine { border-left-color: var(--info-color); }
        .rdv-item.annule { border-left-color: var(--danger-color); }

        .rdv-item:hover {
            transform: translateX(5px);
            box-shadow: var(--shadow-light);
        }

        .rdv-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 0.5rem;
        }

        .rdv-service {
            font-weight: 700;
            color: #1f2937;
        }

        .rdv-datetime {
            color: #6b7280;
            font-size: 0.9rem;
        }

        .rdv-personnel {
            color: #4b5563;
            font-size: 0.9rem;
        }

        /* Status badges */
        .status-badge {
            padding: 0.3rem 0.8rem;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
        }

        .status-confirmed {
            background: rgba(16, 185, 129, 0.1);
            color: var(--success-color);
        }

        .status-pending {
            background: rgba(245, 158, 11, 0.1);
            color: var(--warning-color);
        }

        .status-completed {
            background: rgba(59, 130, 246, 0.1);
            color: var(--info-color);
        }

        .status-cancelled {
            background: rgba(239, 68, 68, 0.1);
            color: var(--danger-color);
        }

        /* Services grid */
        .services-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 1rem;
        }

        .service-item {
            padding: 1.5rem;
            border-radius: var(--border-radius);
            background: #f8fafc;
            border: 1px solid #e5e7eb;
            transition: all 0.3s ease;
        }

        .service-item:hover {
            transform: translateY(-3px);
            box-shadow: var(--shadow-light);
        }

        .service-name {
            font-weight: 700;
            color: #1f2937;
            margin-bottom: 0.5rem;
        }

        .service-info {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: 1rem;
        }

        .service-duration, .service-price {
            font-size: 0.9rem;
            color: #6b7280;
        }

        .service-price {
            font-weight: 600;
            color: var(--success-color);
        }

        /* Activity timeline */
        .activity-list {
            display: flex;
            flex-direction: column;
            gap: 1rem;
        }

        .activity-item {
            padding: 1rem;
            border-radius: var(--border-radius);
            background: #f8fafc;
            border-left: 3px solid var(--primary-gradient);
        }

        .activity-service {
            font-weight: 600;
            color: #1f2937;
        }

        .activity-date {
            font-size: 0.8rem;
            color: #6b7280;
        }

        /* Animations */
        @keyframes float {
            0% { transform: translateX(0px) translateY(0px) rotate(0deg); }
            33% { transform: translateX(30px) translateY(-30px) rotate(120deg); }
            66% { transform: translateX(-20px) translateY(20px) rotate(240deg); }
            100% { transform: translateX(0px) translateY(0px) rotate(360deg); }
        }

        /* Responsive */
        @media (max-width: 1024px) {
            .dashboard-grid {
                grid-template-columns: 1fr;
            }
            
            .main-cards {
                grid-template-columns: 1fr;
            }
            
            .welcome-content {
                grid-template-columns: 1fr;
                text-align: center;
            }
            
            .stats-grid {
                grid-template-columns: repeat(3, 1fr);
            }
        }

        @media (max-width: 768px) {
            .container {
                padding: 0 1rem;
            }
            
            .header-content {
                padding: 0 1rem;
            }
            
            .user-info {
                display: none;
            }
            
            .stats-grid {
                grid-template-columns: 1fr;
            }
            
            .services-grid {
                grid-template-columns: 1fr;
            }
            
            .welcome-section {
                padding: 2rem;
            }
            
            .welcome-text h1 {
                font-size: 2rem;
            }
        }
    </style>
</head>
<body>
    <header>
        <div class="header-content">
            <div class="logo">Am Soft</div>
            <div class="user-menu">
                <a href="profil.php" class="user-avatar"><?= $userInitials ?></a>
                <div class="user-info">
                    <span class="user-name"><?= $userName ?></span>
                    <span class="user-role">● Client</span>
                </div>
                <button class="logout-btn" onclick="logout()">Déconnexion</button>
            </div>
        </div>
    </header>

    <main class="main-content">
        <div class="container">
            <!-- Section de bienvenue avec statistiques -->
            <section class="welcome-section">
                <div class="welcome-content">
                    <div class="welcome-text">
                        <h1>Am Soft Société</h1>
                        <h2>Bienvenue, <?= $userName ?></h2>
                        <p>Gérez vos rendez-vous et découvrez nos services professionnels</p>
                    </div>
                    <div class="stats-grid">
                        <div class="stat-card">
                            <div class="stat-number"><?= $rdv_a_venir ?></div>
                            <div class="stat-label">RDV à venir</div>
                        </div>
                        <div class="stat-card">
                            <div class="stat-number"><?= $rdv_termines ?></div>
                            <div class="stat-label">RDV terminés</div>
                        </div>
                        <div class="stat-card">
                            <div class="stat-number"><?= $services_utilises ?></div>
                            <div class="stat-label">Services utilisés</div>
                        </div>
                    </div>
                </div>
            </section>

            <!-- Dashboard principal -->
            <div class="dashboard-grid">
                <div class="main-cards">
                    <div class="dashboard-card">
                        <div class="card-header">
                            <div class="card-icon">📅</div>
                            <h3 class="card-title">Nouveau Rendez-vous</h3>
                        </div>
                        <div class="card-content">
                            <p>Planifiez un nouveau rendez-vous avec notre équipe professionnelle. Sélectionnez le service souhaité et choisissez votre créneau.</p>
                        </div>
                        <a href="prise_rdv.php" class="btn">➕ Prendre RDV</a>
                    </div>

                    <div class="dashboard-card">
                        <div class="card-header">
                            <div class="card-icon">📋</div>
                            <h3 class="card-title">Mes Rendez-vous</h3>
                        </div>
                        <div class="card-content">
                            <p>Consultez, modifiez ou annulez vos rendez-vous. Suivez le statut de vos réservations en temps réel.</p>
                        </div>
                        <a href="liste_rdv.php" class="btn">👁️ Voir tout</a>
                    </div>

                    <div class="dashboard-card">
                        <div class="card-header">
                            <div class="card-icon">⚙️</div>
                            <h3 class="card-title">Mon Profil</h3>
                        </div>
                        <div class="card-content">
                            <p>Gérez vos informations personnelles et vos préférences de notification.</p>
                        </div>
                        <a href="profil.php" class="btn">✏️ Modifier</a>
                    </div>

                    <div class="dashboard-card">
                        <div class="card-header">
                            <div class="card-icon">📞</div>
                            <h3 class="card-title">Support</h3>
                        </div>
                        <div class="card-content">
                            <p>Notre équipe support est là pour vous aider. Contactez-nous pour toute question.</p>
                        </div>
                        <a href="contact.php" class="btn">💬 Contacter</a>
                    </div>

                    <div class="dashboard-card">
                        <div class="card-header">
                            <div class="card-icon">📅</div>
                            <h3 class="card-title">Historique de messages</h3>
                        </div>
                        <div class="card-content">
                            <p> Voir modifier ou supprimer les messages que tu as envoyé vers l'administration</p>
                        </div>
                        <a href="histo.php" class="btn">➕ Voir vos messages</a>
                    </div>
                </div>

                <div class="sidebar-cards">
                    <!-- Prochains RDV -->
                    <div class="rdv-section">
                        <h3 class="section-title">🗓️ Prochains Rendez-vous</h3>
                        <?php if (empty($prochains_rdv)): ?>
                            <p style="color: #6b7280; text-align: center; padding: 2rem;">
                                Aucun rendez-vous à venir.<br>
                                <a href="prise_rdv.php" style="color: #667eea;">Prendre un rendez-vous</a>
                            </p>
                        <?php else: ?>
                            <div class="rdv-list">
                                <?php foreach($prochains_rdv as $rdv): ?>
                                    <div class="rdv-item <?= $rdv['status'] ?>">
                                        <div class="rdv-header">
                                            <span class="rdv-service"><?= htmlspecialchars($rdv['service_name']) ?></span>
                                            <?= getStatusBadge($rdv['status']) ?>
                                        </div>
                                        <div class="rdv-datetime">
                                            📅 <?= formatDate($rdv['date']) ?> à <?= formatTime($rdv['heure']) ?>
                                        </div>
                                        <?php if($rdv['personnel_name']): ?>
                                            <div class="rdv-personnel">
                                                👤 Avec <?= htmlspecialchars($rdv['personnel_name']) ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- Activité récente -->
                    <div class="activity-section">
                        <h3 class="section-title">📊 Activité Récente</h3>
                        <?php if (empty($dernieres_activites)): ?>
                            <p style="color: #6b7280; text-align: center;">Aucune activité récente</p>
                        <?php else: ?>
                            <div class="activity-list">
                                <?php foreach(array_slice($dernieres_activites, 0, 3) as $activite): ?>
                                    <div class="activity-item">
                                        <div class="activity-service"><?= htmlspecialchars($activite['service_name']) ?></div>
                                        <div class="activity-date">
                                            <?= date('d/m/Y à H:i', strtotime($activite['created_at'])) ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Services disponibles -->
            <div class="services-section">
                <h3 class="section-title">🏢 Nos Services</h3>
                <?php if (empty($services_actifs)): ?>
                    <p style="color: #6b7280; text-align: center;">Aucun service disponible pour le moment</p>
                <?php else: ?>
                    <div class="services-grid">
                        <?php foreach($services_actifs as $service): ?>
                            <div class="service-item">
                                <div class="service-name"><?= htmlspecialchars($service['name']) ?></div>
                                <p style="color: #6b7280; font-size: 0.9rem; margin: 0.5rem 0;">
                                    <?= htmlspecialchars(substr($service['description'], 0, 100)) ?>...
                                </p>
                                <div class="service-info">
                                    <span class="service-duration">⏱️ <?= $service['duration'] ?> min</span>
                                    <span class="service-price"><?= number_format($service['price'], 0, ',', ' ') ?> DH</span>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <div style="text-align: center; margin-top: 1.5rem;">
                        <a href="services.php" class="btn">Voir tous les services</a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </main>

    <script>
        // Fonction de déconnexion
        function logout() {
            if (confirm('Êtes-vous sûr de vouloir vous déconnecter ?')) {
                window.location.href = '../logout.php';
            }
        }

        // Animation d'apparition des éléments
        document.addEventListener('DOMContentLoaded', function() {
            const elements = document.querySelectorAll('.dashboard-card, .rdv-section, .services-section, .activity-section');
            
            const observer = new IntersectionObserver((entries) => {
                entries.forEach((entry, index) => {
                    if (entry.isIntersecting) {
                        setTimeout(() => {
                            entry.target.style.opacity = '1';
                            entry.target.style.transform = 'translateY(0)';
                        }, index * 100);
                    }
                });
            }, { threshold: 0.1 });

            elements.forEach(element => {
                element.style.opacity = '0';
                element.style.transform = 'translateY(30px)';
                element.style.transition = 'opacity 0.6s ease, transform 0.6s ease';
                observer.observe(element);
            });
        });

        // Mise à jour automatique des statistiques (optionnel)
        function updateStats() {
            // Ici vous pouvez ajouter du code AJAX pour mettre à jour les stats
            // sans recharger la page
        }

       // Actualiser les stats toutes les 5 minutes
        setInterval(updateStats, 300000);

        // Gestion des notifications en temps réel (optionnel)
        function checkForUpdates() {
            // Vérifier s'il y a de nouveaux rendez-vous confirmés ou des changements
            fetch('api/check_updates.php')
                .then(response => response.json())
                .then(data => {
                    if (data.hasUpdates) {
                        showNotification('Vos rendez-vous ont été mis à jour', 'info');
                    }
                })
                .catch(error => console.log('Erreur lors de la vérification des mises à jour'));
        }

        // Fonction pour afficher les notifications
        function showNotification(message, type = 'info') {
            const notification = document.createElement('div');
            notification.className = `notification notification-${type}`;
            notification.innerHTML = `
                <div class="notification-content">
                    <span>${message}</span>
                    <button onclick="this.parentElement.parentElement.remove()">×</button>
                </div>
            `;
            
            // Styles pour les notifications
            notification.style.cssText = `
                position: fixed;
                top: 100px;
                right: 20px;
                background: white;
                padding: 1rem 1.5rem;
                border-radius: 10px;
                box-shadow: 0 10px 30px rgba(0,0,0,0.2);
                z-index: 9999;
                max-width: 350px;
                transform: translateX(100%);
                transition: transform 0.3s ease;
            `;
            
            document.body.appendChild(notification);
            
            // Animation d'apparition
            setTimeout(() => {
                notification.style.transform = 'translateX(0)';
            }, 100);
            
            // Suppression automatique après 5 secondes
            setTimeout(() => {
                notification.style.transform = 'translateX(100%)';
                setTimeout(() => notification.remove(), 300);
            }, 5000);
        }

        // Vérifier les mises à jour toutes les 2 minutes
        setInterval(checkForUpdates, 120000);

        // Gestion du scroll pour l'effet parallax sur le header
        window.addEventListener('scroll', function() {
            const header = document.querySelector('header');
            const scrolled = window.pageYOffset;
            
            if (scrolled > 50) {
                header.style.background = 'rgba(255, 255, 255, 0.98)';
                header.style.backdropFilter = 'blur(30px)';
            } else {
                header.style.background = 'rgba(255, 255, 255, 0.95)';
                header.style.backdropFilter = 'blur(20px)';
            }
        });

        // Préchargement des pages importantes
        function preloadPages() {
            const importantPages = ['prise_rdv.php', 'liste_rdv.php', 'profil.php'];
            importantPages.forEach(page => {
                const link = document.createElement('link');
                link.rel = 'prefetch';
                link.href = page;
                document.head.appendChild(link);
            });
        }

        // Lancer le préchargement après le chargement de la page
        window.addEventListener('load', preloadPages);

        // Gestion des raccourcis clavier
        document.addEventListener('keydown', function(e) {
            // Ctrl/Cmd + N : Nouveau rendez-vous
            if ((e.ctrlKey || e.metaKey) && e.key === 'n') {
                e.preventDefault();
                window.location.href = 'prise_rdv.php';
            }
            
            // Ctrl/Cmd + R : Mes rendez-vous
            if ((e.ctrlKey || e.metaKey) && e.key === 'r') {
                e.preventDefault();
                window.location.href = 'liste_rdv.php';
            }
            
            // Ctrl/Cmd + P : Profil
            if ((e.ctrlKey || e.metaKey) && e.key === 'p') {
                e.preventDefault();
                window.location.href = 'profil.php';
            }
        });

        // Amélioration de l'accessibilité
        document.querySelectorAll('.btn, .user-avatar, .logout-btn').forEach(element => {
            element.addEventListener('keydown', function(e) {
                if (e.key === 'Enter' || e.key === ' ') {
                    e.preventDefault();
                    this.click();
                }
            });
        });

        // Debug : log des erreurs JavaScript
        window.addEventListener('error', function(e) {
            console.error('Erreur JavaScript:', e.error);
        });

        // Performance : lazy loading des images si il y en a
        if ('IntersectionObserver' in window) {
            const imageObserver = new IntersectionObserver((entries, observer) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        const img = entry.target;
                        img.src = img.dataset.src;
                        img.classList.remove('lazy');
                        imageObserver.unobserve(img);
                    }
                });
            });

            document.querySelectorAll('img[data-src]').forEach(img => {
                imageObserver.observe(img);
            });
        }
    </script>

    <!-- CSS supplémentaire pour les notifications -->
    <style>
        .notification {
            font-family: inherit;
        }
        
        .notification-content {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 1rem;
        }
        
        .notification-content button {
            background: none;
            border: none;
            font-size: 1.2rem;
            cursor: pointer;
            color: #6b7280;
            padding: 0;
            width: 20px;
            height: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .notification-content button:hover {
            color: #374151;
        }
        
        .notification-info {
            border-left: 4px solid #3b82f6;
        }
        
        .notification-success {
            border-left: 4px solid #10b981;
        }
        
        .notification-warning {
            border-left: 4px solid #f59e0b;
        }
        
        .notification-error {
            border-left: 4px solid #ef4444;
        }

        /* Amélioration de l'accessibilité */
        .btn:focus,
        .user-avatar:focus,
        .logout-btn:focus {
            outline: 2px solid #667eea;
            outline-offset: 2px;
        }

        /* Indicateur de chargement */
        .loading {
            opacity: 0.7;
            pointer-events: none;
            position: relative;
        }

        .loading::after {
            content: '';
            position: absolute;
            top: 50%;
            left: 50%;
            width: 20px;
            height: 20px;
            margin: -10px 0 0 -10px;
            border: 2px solid #667eea;
            border-top: 2px solid transparent;
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
    </style>
</body>
</html>