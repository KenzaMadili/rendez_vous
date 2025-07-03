<?php
include("../includes/database.php"); // Assure-toi que le chemin est correct

// Statistiques des rendez-vous
try {
    $total = executeQuery("SELECT COUNT(*) AS total FROM rendezvous")->fetch()['total'];
    $en_attente = executeQuery("SELECT COUNT(*) AS total FROM rendezvous WHERE status = 'pending'")->fetch()['total'];
    $accepte = executeQuery("SELECT COUNT(*) AS total FROM rendezvous WHERE status = 'confirmed'")->fetch()['total'];
    $refuse = executeQuery("SELECT COUNT(*) AS total FROM rendezvous WHERE status = 'cancelled'")->fetch()['total'];
    $termine = executeQuery("SELECT COUNT(*) AS total FROM rendezvous WHERE status = 'completed'")->fetch()['total'];
    
    // Statistiques par service (les plus demandés)
    $services_populaires = executeQuery("
        SELECT s.name, COUNT(r.id) as total_rdv 
        FROM services s 
        LEFT JOIN rendezvous r ON s.id = r.service_id 
        GROUP BY s.id, s.name 
        ORDER BY total_rdv DESC 
        LIMIT 5
    ")->fetchAll();
    
    // Statistiques par personnel
    $personnel_actif = executeQuery("
        SELECT u.name, COUNT(r.id) as total_rdv 
        FROM users u 
        LEFT JOIN rendezvous r ON u.id = r.personnel_id 
        WHERE u.role = 'personnel'
        GROUP BY u.id, u.name 
        ORDER BY total_rdv DESC 
        LIMIT 5
    ")->fetchAll();
    
} catch (Exception $e) {
    $erreur = "Erreur lors de la récupération des statistiques : " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Statistiques des Rendez-vous</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
        }

        .header {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            border-radius: 20px;
            padding: 2rem;
            margin-bottom: 2rem;
            text-align: center;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
        }

        .header h1 {
            font-size: 2.5rem;
            font-weight: 700;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            margin-bottom: 0.5rem;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .stat-card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            border-radius: 15px;
            padding: 1.5rem;
            text-align: center;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
            transition: transform 0.3s ease;
        }

        .stat-card:hover {
            transform: translateY(-5px);
        }

        .stat-number {
            font-size: 2.5rem;
            font-weight: bold;
            margin-bottom: 0.5rem;
        }

        .stat-total { color: #667eea; }
        .stat-pending { color: #f6ad55; }
        .stat-confirmed { color: #38a169; }
        .stat-cancelled { color: #e53e3e; }
        .stat-completed { color: #805ad5; }

        .stat-label {
            color: #666;
            font-weight: 600;
            font-size: 1rem;
        }

        .card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            border-radius: 20px;
            padding: 2rem;
            margin-bottom: 2rem;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
        }

        .card-title {
            font-size: 1.5rem;
            font-weight: 700;
            color: #4a5568;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .charts-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(400px, 1fr));
            gap: 2rem;
        }

        .list-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 1rem;
            background: #f8fafc;
            border-radius: 10px;
            margin-bottom: 0.5rem;
            transition: background-color 0.3s ease;
        }

        .list-item:hover {
            background: #e2e8f0;
        }

        .list-item-name {
            font-weight: 600;
            color: #4a5568;
        }

        .list-item-count {
            font-weight: bold;
            color: #667eea;
            background: #e6fffa;
            padding: 0.25rem 0.75rem;
            border-radius: 50px;
            font-size: 0.9rem;
        }

        .btn {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.8rem 1.5rem;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            text-decoration: none;
            border-radius: 10px;
            font-weight: 600;
            transition: all 0.3s ease;
            border: none;
            cursor: pointer;
        }

        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(102, 126, 234, 0.3);
        }

        .btn-back {
            background: rgba(255, 255, 255, 0.2);
            color: white;
            border: 2px solid rgba(255, 255, 255, 0.3);
        }

        .btn-back:hover {
            background: rgba(255, 255, 255, 0.3);
            border-color: rgba(255, 255, 255, 0.5);
        }

        .error {
            background: #fed7d7;
            color: #742a2a;
            padding: 1rem;
            border-radius: 10px;
            border-left: 4px solid #e53e3e;
            margin-bottom: 2rem;
        }

        .empty-state {
            text-align: center;
            padding: 2rem;
            color: #666;
        }

        .actions-bar {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            border-radius: 15px;
            padding: 1.5rem;
            margin-bottom: 2rem;
            display: flex;
            justify-content: center;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
        }

        @media (max-width: 768px) {
            .container {
                padding: 0 10px;
            }
            
            .header {
                padding: 1.5rem;
            }
            
            .header h1 {
                font-size: 2rem;
            }
            
            .card {
                padding: 1.5rem;
            }
            
            .charts-grid {
                grid-template-columns: 1fr;
            }
            
            .stats-grid {
                grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>📊 Statistiques des Rendez-vous</h1>
            <p>Vue d'ensemble des rendez-vous et performances</p>
        </div>

        <?php if (isset($erreur)): ?>
            <div class="error">
                ❌ <?= htmlspecialchars($erreur) ?>
            </div>
        <?php else: ?>
            <!-- Statistiques principales -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-number stat-total"><?= $total ?></div>
                    <div class="stat-label">📅 Total Rendez-vous</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number stat-pending"><?= $en_attente ?></div>
                    <div class="stat-label">⏳ En Attente</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number stat-confirmed"><?= $accepte ?></div>
                    <div class="stat-label">✅ Confirmés</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number stat-cancelled"><?= $refuse ?></div>
                    <div class="stat-label">❌ Annulés</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number stat-completed"><?= $termine ?></div>
                    <div class="stat-label">🏁 Terminés</div>
                </div>
            </div>

            <!-- Graphiques et listes -->
            <div class="charts-grid">
                <!-- Services les plus demandés -->
                <div class="card">
                    <h2 class="card-title">🏆 Services les Plus Demandés</h2>
                    <?php if (empty($services_populaires)): ?>
                        <div class="empty-state">
                            <p>Aucun service trouvé</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($services_populaires as $service): ?>
                            <div class="list-item">
                                <span class="list-item-name"><?= htmlspecialchars($service['name']) ?></span>
                                <span class="list-item-count"><?= $service['total_rdv'] ?> RDV</span>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>

                <!-- Personnel le plus actif -->
                <div class="card">
                    <h2 class="card-title">👥 Personnel le Plus Actif</h2>
                    <?php if (empty($personnel_actif)): ?>
                        <div class="empty-state">
                            <p>Aucun personnel trouvé</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($personnel_actif as $personnel): ?>
                            <div class="list-item">
                                <span class="list-item-name"><?= htmlspecialchars($personnel['name']) ?></span>
                                <span class="list-item-count"><?= $personnel['total_rdv'] ?> RDV</span>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>

        <!-- Barre d'actions -->
        <div class="actions-bar" style="background-color: purple">
            <a href="rdv.php" class="btn btn-back">← Retour à la gestion des rendez-vous</a>
        </div>
    </div>
</body>
</html>