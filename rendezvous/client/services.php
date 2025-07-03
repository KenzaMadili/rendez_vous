<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit();
}

try {
    $pdo = new PDO("mysql:host=localhost;dbname=rendezvs;charset=utf8mb4", "root", "");
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Erreur de connexion à la base de données: " . $e->getMessage());
}

// Récupérer les services actifs avec leurs personnels associés
$query = "
    SELECT 
        s.id AS service_id, s.name AS service_name, s.description, s.duration, s.price,
        u.name AS personnel_name, u.id AS personnel_id
    FROM services s
    LEFT JOIN service_personnel sp ON s.id = sp.service_id AND sp.is_active = 1
    LEFT JOIN users u ON sp.personnel_id = u.id
    WHERE s.is_active = 1
    ORDER BY s.name, u.name
";
$stmt = $pdo->query($query);
$results = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Organiser les résultats par service
$services = [];
foreach ($results as $row) {
    $sid = $row['service_id'];
    if (!isset($services[$sid])) {
        $services[$sid] = [
            'name' => $row['service_name'],
            'description' => $row['description'],
            'duration' => $row['duration'],
            'price' => $row['price'],
            'personnel' => [],
        ];
    }
    if ($row['personnel_name']) {
        $services[$sid]['personnel'][] = [
            'name' => $row['personnel_name'],
            'id' => $row['personnel_id']
        ];
    }
}

// Calculer les statistiques
$totalServices = count($services);
$totalPersonnel = 0;
$avgPrice = 0;
$avgDuration = 0;

foreach ($services as $service) {
    $totalPersonnel += count($service['personnel']);
    $avgPrice += $service['price'];
    $avgDuration += $service['duration'];
}

if ($totalServices > 0) {
    $avgPrice = $avgPrice / $totalServices;
    $avgDuration = $avgDuration / $totalServices;
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Services Disponibles - Rendezvous</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            color: #333;
        }

        .header {
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(10px);
            padding: 2rem 0;
            text-align: center;
            color: white;
            position: relative;
        }

        .header h1 {
            font-size: 2.5rem;
            margin-bottom: 0.5rem;
            font-weight: 700;
        }

        .header p {
            font-size: 1.1rem;
            opacity: 0.9;
        }

        .nav-links {
            position: absolute;
            top: 2rem;
            left: 2rem;
            display: flex;
            gap: 1rem;
        }

        .nav-btn {
            background: rgba(255, 255, 255, 0.2);
            color: white;
            padding: 0.7rem 1.2rem;
            border-radius: 25px;
            text-decoration: none;
            transition: all 0.3s ease;
            font-weight: 500;
        }

        .nav-btn:hover {
            background: rgba(255, 255, 255, 0.3);
            transform: translateY(-2px);
            text-decoration: none;
            color: white;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 2rem;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1.5rem;
            margin-bottom: 3rem;
        }

        .stat-card {
            background: white;
            padding: 2rem;
            border-radius: 15px;
            text-align: center;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
            transition: transform 0.3s ease;
        }

        .stat-card:hover {
            transform: translateY(-5px);
        }

        .stat-number {
            font-size: 2.5rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
        }

        .stat-label {
            color: #666;
            font-size: 0.9rem;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .stat-card.services .stat-number { color: #667eea; }
        .stat-card.personnel .stat-number { color: #f39c12; }
        .stat-card.price .stat-number { color: #27ae60; }
        .stat-card.duration .stat-number { color: #e74c3c; }

        .section-title {
            background: white;
            padding: 1.5rem 2rem;
            margin: 2rem 0 1rem 0;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
        }

        .section-title h2 {
            color: #333;
            font-size: 1.5rem;
            font-weight: 600;
        }

        .services-grid {
            display: grid;
            gap: 2rem;
        }

        .service-card {
            background: white;
            border-radius: 20px;
            padding: 2rem;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }

        .service-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 5px;
            height: 100%;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }

        .service-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.15);
        }

        .service-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 1.5rem;
        }

        .service-title {
            font-size: 1.4rem;
            font-weight: 600;
            color: #333;
            margin-bottom: 0.5rem;
        }

        .service-description {
            color: #666;
            line-height: 1.6;
            margin-bottom: 1.5rem;
        }

        .service-details {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
            gap: 1.5rem;
            margin-bottom: 1.5rem;
        }

        .detail-item {
            display: flex;
            flex-direction: column;
        }

        .detail-label {
            font-size: 0.8rem;
            color: #666;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-bottom: 0.3rem;
        }

        .detail-value {
            font-weight: 600;
            color: #333;
            font-size: 1rem;
        }

        .price-badge {
            background: linear-gradient(135deg, #27ae60 0%, #2ecc71 100%);
            color: white;
            padding: 0.5rem 1rem;
            border-radius: 20px;
            font-weight: 600;
            font-size: 1.1rem;
            text-align: center;
            min-width: 120px;
        }

        .personnel-section {
            background: #f8f9fa;
            padding: 1.5rem;
            border-radius: 15px;
            margin-top: 1.5rem;
        }

        .personnel-title {
            font-size: 1rem;
            color: #333;
            margin-bottom: 1rem;
            font-weight: 600;
        }

        .personnel-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
        }

        .personnel-item {
            background: white;
            padding: 1rem;
            border-radius: 10px;
            text-align: center;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
            transition: all 0.3s ease;
        }

        .personnel-item:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
        }

        .personnel-name {
            font-weight: 600;
            color: #333;
        }

        .no-personnel {
            color: #666;
            font-style: italic;
            text-align: center;
            padding: 1rem;
        }

        .empty-state {
            text-align: center;
            padding: 4rem 2rem;
            background: white;
            border-radius: 20px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
        }

        .empty-state h3 {
            font-size: 1.5rem;
            color: #666;
            margin-bottom: 1rem;
        }

        .empty-state p {
            color: #888;
            margin-bottom: 2rem;
        }

        .cta-btn {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 1rem 2rem;
            border-radius: 25px;
            text-decoration: none;
            font-weight: 600;
            transition: all 0.3s ease;
            display: inline-block;
        }

        .cta-btn:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 25px rgba(102, 126, 234, 0.4);
            text-decoration: none;
            color: white;
        }

        .book-btn {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 0.8rem 1.5rem;
            border-radius: 25px;
            text-decoration: none;
            font-weight: 600;
            transition: all 0.3s ease;
            display: inline-block;
            margin-top: 1rem;
        }

        .book-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(102, 126, 234, 0.4);
            text-decoration: none;
            color: white;
        }

        @media (max-width: 768px) {
            .nav-links {
                position: static;
                justify-content: center;
                margin-top: 1rem;
            }

            .header {
                padding: 3rem 1rem 2rem;
            }

            .container {
                padding: 1rem;
            }

            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
                gap: 1rem;
            }

            .stat-card {
                padding: 1.5rem;
            }

            .service-card {
                padding: 1.5rem;
            }

            .service-header {
                flex-direction: column;
                gap: 1rem;
            }

            .service-details {
                grid-template-columns: 1fr;
                gap: 1rem;
            }

            .personnel-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="nav-links">
            <a href="../dashboard.php" class="nav-btn">Tableau de bord</a>
            <a href="../appointments/book.php" class="nav-btn">Prendre RDV</a>
        </div>
        <h1>Services Disponibles</h1>
        <p>Découvrez nos services et prenez rendez-vous facilement</p>
    </div>

    <div class="container">
        <!-- Statistiques -->
        <div class="stats-grid">
            <div class="stat-card services">
                <div class="stat-number"><?= $totalServices ?></div>
                <div class="stat-label">Services</div>
            </div>
            <div class="stat-card personnel">
                <div class="stat-number"><?= $totalPersonnel ?></div>
                <div class="stat-label">Personnel</div>
            </div>
            <div class="stat-card price">
                <div class="stat-number"><?= number_format($avgPrice, 0) ?></div>
                <div class="stat-label">Prix Moyen (MAD)</div>
            </div>
            <div class="stat-card duration">
                <div class="stat-number"><?= number_format($avgDuration, 0) ?></div>
                <div class="stat-label">Durée Moy. (min)</div>
            </div>
        </div>

        <?php if (empty($services)): ?>
            <div class="empty-state">
                <h3>Aucun service disponible</h3>
                <p>Il n'y a aucun service disponible pour le moment. Revenez plus tard ou contactez-nous pour plus d'informations.</p>
                <a href="../dashboard.php" class="cta-btn">Retour au tableau de bord</a>
            </div>
        <?php else: ?>
            <div class="section-title">
                <h2>Nos Services</h2>
            </div>

            <div class="services-grid">
                <?php foreach ($services as $service): ?>
                    <div class="service-card">
                        <div class="service-header">
                            <div>
                                <div class="service-title"><?= htmlspecialchars($service['name']) ?></div>
                            </div>
                            <div class="price-badge">
                                <?= number_format($service['price'], 2, ',', ' ') ?> MAD
                            </div>
                        </div>

                        <div class="service-description">
                            <?= nl2br(htmlspecialchars($service['description'])) ?>
                        </div>

                        <div class="service-details">
                            <div class="detail-item">
                                <div class="detail-label">Durée</div>
                                <div class="detail-value"><?= htmlspecialchars($service['duration']) ?> minutes</div>
                            </div>
                            <div class="detail-item">
                                <div class="detail-label">Personnel</div>
                                <div class="detail-value"><?= count($service['personnel']) ?> disponible(s)</div>
                            </div>
                        </div>

                        <?php if (!empty($service['personnel'])): ?>
                            <div class="personnel-section">
                                <div class="personnel-title">Personnel disponible</div>
                                <div class="personnel-grid">
                                    <?php foreach ($service['personnel'] as $personnel): ?>
                                        <div class="personnel-item">
                                            <div class="personnel-name"><?= htmlspecialchars($personnel['name']) ?></div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        <?php else: ?>
                            <div class="personnel-section">
                                <div class="no-personnel">Aucun personnel affecté pour l'instant</div>
                            </div>
                        <?php endif; ?>

                        <a href="prise_rdv.php<?= $service['id'] ?? '' ?>" class="book-btn">
                            Prendre rendez-vous
                        </a>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>