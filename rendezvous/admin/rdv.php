<?php
include("../includes/database.php");

// 🔁 Traitement de mise à jour du statut
if (isset($_POST['modifier_statut'])) {
    $id = intval($_POST['rendezvous_id']);
    $nouveau_statut = $_POST['statut'];

    $statuts_valides = ['pending', 'confirmed', 'cancelled'];
    if (in_array($nouveau_statut, $statuts_valides)) {
        try {
            executeQuery("UPDATE rendezvous SET status = ?, updated_at = NOW() WHERE id = ?", [$nouveau_statut, $id]);
            header("Location: rdv.php");
            exit();
        } catch (Exception $e) {
            $erreur = "Erreur lors de la mise à jour : " . $e->getMessage();
        }
    } else {
        $erreur = "Statut non valide.";
    }
}

// ➕ Ajout ou report d'un rendez-vous
if (isset($_POST['ajouter_rdv'])) {
    $client_id = intval($_POST['client_id']);
    $personnel_id = intval($_POST['personnel_id']);
    $service_id = intval($_POST['service_id']);
    $date = $_POST['date'];
    $heure = $_POST['heure'];
    $notes = $_POST['notes'];

    // Récupérer les informations du service
    try {
        $stmt = executeQuery("SELECT price, duration FROM services WHERE id = ?", [$service_id]);
        $service = $stmt->fetch();
        $prix = $service['price'];
        $duration = $service['duration'];

        executeQuery("INSERT INTO rendezvous (client_id, personnel_id, service_id, date, heure, prix, duration, status, notes, created_at, updated_at)
                      VALUES (?, ?, ?, ?, ?, ?, ?, 'pending', ?, NOW(), NOW())",
                      [$client_id, $personnel_id, $service_id, $date, $heure, $prix, $duration, $notes]);
        $succes = "Rendez-vous ajouté avec succès !";
    } catch (Exception $e) {
        $erreur = "Erreur lors de l'ajout : " . $e->getMessage();
    }
}

if (isset($_POST['reporter_rdv'])) {
    $id = intval($_POST['rendezvous_id']);
    $nouvelle_date = $_POST['nouvelle_date'];
    $nouvelle_heure = $_POST['nouvelle_heure'];

    try {
        executeQuery("UPDATE rendezvous SET date = ?, heure = ?, updated_at = NOW() WHERE id = ?", [$nouvelle_date, $nouvelle_heure, $id]);
        $succes = "Rendez-vous reporté avec succès !";
    } catch (Exception $e) {
        $erreur = "Erreur lors du report : " . $e->getMessage();
    }
}

// 🗑️ Supprimer un rendez-vous
if (isset($_POST['supprimer_rdv'])) {
    $id = intval($_POST['rendezvous_id']);
    try {
        executeQuery("DELETE FROM rendezvous WHERE id = ?", [$id]);
        $succes = "Rendez-vous supprimé avec succès !";
    } catch (Exception $e) {
        $erreur = "Erreur lors de la suppression : " . $e->getMessage();
    }
}

$utilisateurs = [];
$clients = [];
$personnels = [];
$services = [];

try {
    // Charger tous les utilisateurs avec leurs informations complètes
    $stmt = executeQuery("SELECT id, name, email, role, created_at FROM users");
    while ($user = $stmt->fetch()) {
        $utilisateurs[$user['id']] = [
            'name' => $user['name'],
            'email' => $user['email'],
            'role' => $user['role'],
            'created_at' => $user['created_at']
        ];
        if ($user['role'] === 'client') $clients[$user['id']] = $user['name'];
        if ($user['role'] === 'personnel') $personnels[$user['id']] = $user['name'];
    }
    
    // Charger tous les services avec prix et durée
    $stmt2 = executeQuery("SELECT id, name, description, duration, price, is_active FROM services WHERE is_active = 1");
    while ($srv = $stmt2->fetch()) {
        $services[$srv['id']] = [
            'name' => $srv['name'],
            'description' => $srv['description'],
            'duration' => $srv['duration'],
            'price' => $srv['price']
        ];
    }
} catch (Exception $e) {
    $erreur = "Erreur lors du chargement des données.";
}

// Statistiques avancées
$stats = [
    'total' => 0,
    'pending' => 0,
    'confirmed' => 0,
    'cancelled' => 0,
    'revenus_total' => 0,
    'revenus_confirmes' => 0
];

try {
    // Statistiques par statut
    $stmt = executeQuery("SELECT status, COUNT(*) as count, SUM(prix) as revenus FROM rendezvous GROUP BY status");
    while ($row = $stmt->fetch()) {
        $stats[$row['status']] = $row['count'];
        $stats['total'] += $row['count'];
        $stats['revenus_total'] += $row['revenus'];
        if ($row['status'] === 'completed') {
            $stats['revenus_confirmes'] = $row['revenus'];
        }
    }
} catch (Exception $e) {
    // Ignorer l'erreur pour les statistiques
}

// Filtres
$filtre_statut = isset($_GET['statut']) ? $_GET['statut'] : '';
$filtre_personnel = isset($_GET['personnel']) ? intval($_GET['personnel']) : 0;
$filtre_client = isset($_GET['client']) ? intval($_GET['client']) : 0;
$filtre_date = isset($_GET['date']) ? $_GET['date'] : '';

$where_conditions = [];
$params = [];

if ($filtre_statut && in_array($filtre_statut, ['pending', 'confirmed', 'cancelled'])) {
    $where_conditions[] = "r.status = ?";
    $params[] = $filtre_statut;
}
if ($filtre_personnel) {
    $where_conditions[] = "r.personnel_id = ?";
    $params[] = $filtre_personnel;
}
if ($filtre_client) {
    $where_conditions[] = "r.client_id = ?";
    $params[] = $filtre_client;
}
if ($filtre_date) {
    $where_conditions[] = "r.date = ?";
    $params[] = $filtre_date;
}

$where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion des Rendez-vous - Admin</title>
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
            max-width: 1600px;
            margin: 0 auto;
        }

        h1 {
            text-align: center;
            color: white;
            margin-bottom: 30px;
            font-size: 2.5rem;
            text-shadow: 0 2px 4px rgba(0,0,0,0.3);
        }

        h2 {
            color: #333;
            margin-bottom: 20px;
            font-size: 1.5rem;
        }

        .alert {
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 8px;
            font-weight: 500;
        }

        .alert-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .alert-error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        .section {
            background: white;
            border-radius: 15px;
            padding: 25px;
            margin-bottom: 30px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
            font-size: 0.9rem;
        }

        th, td {
            padding: 12px 8px;
            text-align: left;
            border-bottom: 1px solid #e0e0e0;
            vertical-align: top;
        }

        th {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            font-size: 0.8rem;
            position: sticky;
            top: 0;
        }

        tr:hover {
            background: #f8f9ff;
        }

        .btn {
            padding: 6px 12px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-weight: 500;
            text-decoration: none;
            display: inline-block;
            transition: all 0.3s ease;
            font-size: 0.8rem;
            margin: 1px;
        }

        .btn-primary {
            background: #667eea;
            color: white;
        }

        .btn-primary:hover {
            background: #5a6fd8;
            transform: translateY(-1px);
        }

        .btn-danger {
            background: #e74c3c;
            color: white;
        }

        .btn-danger:hover {
            background: #c0392b;
            transform: translateY(-1px);
        }

        .btn-warning {
            background: #f39c12;
            color: white;
        }

        .btn-warning:hover {
            background: #d68910;
            transform: translateY(-1px);
        }

        .btn-success {
            background: #2ecc71;
            color: white;
        }

        .btn-success:hover {
            background: #27ae60;
            transform: translateY(-1px);
        }

        .form-inline {
            display: flex;
            align-items: center;
            gap: 5px;
            flex-wrap: wrap;
            margin-bottom: 5px;
        }

        .form-inline select,
        .form-inline input {
            padding: 4px 8px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 0.8rem;
        }

        .add-form {
            background: white;
            border-radius: 15px;
            padding: 30px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            max-width: 600px;
            margin: 0 auto 30px auto;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #333;
        }

        .form-group input,
        .form-group select {
            width: 100%;
            padding: 12px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-size: 1rem;
            transition: border-color 0.3s ease;
        }

        .form-group input:focus,
        .form-group select:focus {
            outline: none;
            border-color: #667eea;
        }

        .status-badge {
            padding: 4px 8px;
            border-radius: 20px;
            font-size: 0.7rem;
            font-weight: 600;
            text-transform: uppercase;
            display: inline-block;
        }

        .status-pending {
            background: #fff3cd;
            color: #856404;
        }

        .status-confirmed {
            background: #d4edda;
            color: #155724;
        }

        .status-cancelled {
            background: #f8d7da;
            color: #721c24;
        }

        .stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: white;
            padding: 20px;
            border-radius: 15px;
            text-align: center;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }

        .stat-number {
            font-size: 1.8rem;
            font-weight: 700;
            margin-bottom: 5px;
        }

        .stat-label {
            color: #666;
            font-size: 0.8rem;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .stat-total .stat-number { color: #667eea; }
        .stat-pending .stat-number { color: #f39c12; }
        .stat-confirmed .stat-number { color: #2ecc71; }
        .stat-cancelled .stat-number { color: #e74c3c; }
        .stat-revenus .stat-number { color: #8e44ad; }

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
        }

        .filters {
            background: white;
            padding: 20px;
            border-radius: 15px;
            margin-bottom: 20px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }

        .filters-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            align-items: end;
        }

        .price-tag {
            font-weight: 600;
            color: #2ecc71;
        }

        .duration-tag {
            font-size: 0.8rem;
            color: #666;
            background: #f8f9fa;
            padding: 2px 6px;
            border-radius: 10px;
        }

        .user-info {
            font-size: 0.85rem;
        }

        .user-email {
            color: #666;
            font-size: 0.75rem;
        }

        .table-container {
            overflow-x: auto;
            max-height: 600px;
            overflow-y: auto;
        }

        .actions-column {
            min-width: 250px;
        }

        @media (max-width: 768px) {
            .container {
                padding: 10px;
            }
            
            h1 {
                font-size: 2rem;
            }
            
            .section {
                padding: 15px;
            }
            
            table {
                font-size: 0.8rem;
            }
            
            th, td {
                padding: 8px 4px;
            }
            
            .form-row {
                grid-template-columns: 1fr;
            }

            .filters-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>

<div class="container">
    <h1>🏥 Gestion des Rendez-vous - Administration</h1>
    
    <?php if (isset($erreur)): ?>
        <div class="alert alert-error">❌ <?= htmlspecialchars($erreur) ?></div>
    <?php endif; ?>
    
    <?php if (isset($succes)): ?>
        <div class="alert alert-success">✅ <?= htmlspecialchars($succes) ?></div>
    <?php endif; ?>

    <!-- Statistiques -->
    <div class="stats">
        <div class="stat-card stat-total">
            <div class="stat-number"><?= $stats['total'] ?></div>
            <div class="stat-label">Total RDV</div>
        </div>
        <div class="stat-card stat-pending">
            <div class="stat-number"><?= $stats['pending'] ?></div>
            <div class="stat-label">En attente</div>
        </div>
        <div class="stat-card stat-confirmed">
            <div class="stat-number"><?= $stats['confirmed'] ?></div>
            <div class="stat-label">Confirmés</div>
        </div>
        <div class="stat-card stat-cancelled">
            <div class="stat-number"><?= $stats['cancelled'] ?></div>
            <div class="stat-label">Annulés</div>
        </div>
        <div class="stat-card stat-revenus">
            <div class="stat-number"><?= number_format($stats['revenus_total'], 2) ?>DH</div>
            <div class="stat-label">Revenus Total</div>
        </div>
        <div class="stat-card stat-revenus">
            <div class="stat-number"><?= number_format($stats['revenus_confirmes'], 2) ?>DH</div>
            <div class="stat-label">Revenus Confirmés</div>
        </div>
    </div>

    <!-- Filtres -->
    <div class="filters">
        <h3>🔍 Filtres</h3>
        <form method="GET" class="filters-grid">
            <div class="form-group">
                <label>Statut</label>
                <select name="statut">
                    <option value="">Tous les statuts</option>
                    <option value="pending" <?= $filtre_statut == 'pending' ? 'selected' : '' ?>>En attente</option>
                    <option value="confirmed" <?= $filtre_statut == 'confirmed' ? 'selected' : '' ?>>Confirmé</option>
                    <option value="cancelled" <?= $filtre_statut == 'cancelled' ? 'selected' : '' ?>>Annulé</option>
                </select>
            </div>
            <div class="form-group">
                <label>Personnel</label>
                <select name="personnel">
                    <option value="">Tout le personnel</option>
                    <?php foreach ($personnels as $id => $nom): ?>
                        <option value="<?= $id ?>" <?= $filtre_personnel == $id ? 'selected' : '' ?>><?= htmlspecialchars($nom) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label>Client</label>
                <select name="client">
                    <option value="">Tous les clients</option>
                    <?php foreach ($clients as $id => $nom): ?>
                        <option value="<?= $id ?>" <?= $filtre_client == $id ? 'selected' : '' ?>><?= htmlspecialchars($nom) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label>Date</label>
                <input type="date" name="date" value="<?= htmlspecialchars($filtre_date) ?>">
            </div>
            <div class="form-group">
                <label>&nbsp;</label>
                <button type="submit" class="btn btn-primary">🔍 Filtrer</button>
                <a href="rdv.php" class="btn btn-warning">🔄 Reset</a>
            </div>
        </form>
    </div>

    <!-- Formulaire d'ajout -->
    <div class="add-form">
        <h2>➕ Ajouter un Rendez-vous</h2>
        <form method="POST">
            <div class="form-group">
                <label for="client_id">Client</label>
                <select name="client_id" id="client_id" required>
                    <option value="">Sélectionner un client</option>
                    <?php foreach ($clients as $id => $nom): ?>
                        <option value="<?= $id ?>"><?= htmlspecialchars($nom) ?> (<?= htmlspecialchars($utilisateurs[$id]['email']) ?>)</option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label for="personnel_id">Personnel</label>
                <select name="personnel_id" id="personnel_id" required>
                    <option value="">Sélectionner un personnel</option>
                    <?php foreach ($personnels as $id => $nom): ?>
                        <option value="<?= $id ?>"><?= htmlspecialchars($nom) ?> (<?= htmlspecialchars($utilisateurs[$id]['email']) ?>)</option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label for="service_id">Service</label>
                <select name="service_id" id="service_id" required>
                    <option value="">Sélectionner un service</option>
                    <?php foreach ($services as $id => $info): ?>
                        <option value="<?= $id ?>"><?= htmlspecialchars($info['name']) ?> (<?= $info['duration'] ?>min - <?= number_format($info['price'], 2) ?>€)</option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label for="date">Date</label>
                    <input type="date" name="date" id="date" required>
                </div>
                <div class="form-group">
                    <label for="heure">Heure</label>
                    <input type="time" name="heure" id="heure" required>
                </div>
            </div>

            <div class="form-group">
                <label for="notes">Notes (facultatif)</label>
                <input type="text" name="notes" id="notes" placeholder="Notes sur le rendez-vous...">
            </div>

            <button type="submit" name="ajouter_rdv" class="btn btn-success">✅ Ajouter le Rendez-vous</button>
        </form>
    </div>

    <!-- Tableau des rendez-vous -->
    <div class="section">
        <h2>📋 Liste des Rendez-vous</h2>
        <div class="table-container">
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Client</th>
                        <th>Personnel</th>
                        <th>Service</th>
                        <th>Date & Heure</th>
                        <th>Prix</th>
                        <th>Durée</th>
                        <th>Statut</th>
                        <th>Notes</th>
                        <th>Dates</th>
                        <th class="actions-column">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    try {
                        $stmt = executeQuery("SELECT r.*, 
                                            c.name as client_name, c.email as client_email,
                                            p.name as personnel_name, p.email as personnel_email,
                                            s.name as service_name, s.description as service_desc
                                            FROM rendezvous r
                                            LEFT JOIN users c ON r.client_id = c.id
                                            LEFT JOIN users p ON r.personnel_id = p.id  
                                            LEFT JOIN services s ON r.service_id = s.id
                                            $where_clause
                                            ORDER BY r.date DESC, r.heure DESC", $params);
                        while ($row = $stmt->fetch()):
                            $status_class = 'status-' . $row['status'];
                            $status_text = [
                                'pending' => 'En attente',
                                'confirmed' => 'Confirmé',
                                'cancelled' => 'Annulé'
                            ][$row['status']] ?? $row['status'];
                    ?>
                        <tr>
                            <td><strong>#<?= $row['id'] ?></strong></td>
                            <td>
                                <div class="user-info">
                                    <strong><?= htmlspecialchars($row['client_name'] ?? 'N/A') ?></strong>
                                    <div class="user-email"><?= htmlspecialchars($row['client_email'] ?? '') ?></div>
                                </div>
                            </td>
                            <td>
                                <div class="user-info">
                                    <strong><?= htmlspecialchars($row['personnel_name'] ?? 'N/A') ?></strong>
                                    <div class="user-email"><?= htmlspecialchars($row['personnel_email'] ?? '') ?></div>
                                </div>
                            </td>
                            <td>
                                <strong><?= htmlspecialchars($row['service_name'] ?? 'N/A') ?></strong>
                                <?php if ($row['service_desc']): ?>
                                    <div style="font-size: 0.75rem; color: #666;"><?= htmlspecialchars(substr($row['service_desc'], 0, 50)) ?>...</div>
                                <?php endif; ?>
                            </td>
                            <td>
                                <strong><?= date('d/m/Y', strtotime($row['date'])) ?></strong><br>
                                <span style="color: #666;"><?= date('H:i', strtotime($row['heure'])) ?></span>
                            </td>
                            <td>
                                <span class="price-tag"><?= number_format($row['prix'], 2) ?>DH</span>
                            </td>
                            <td>
                                <span class="duration-tag"><?= $row['duration'] ?> min</span>
                            </td>
                            <td>
                                <span class="status-badge <?= $status_class ?>">
                                    <?= $status_text ?>
                                </span>
                            </td>
                            <td><?= htmlspecialchars($row['notes']) ?: '-' ?></td>
                            <td>
                                <div style="font-size: 0.75rem; color: #666;">
                                    <div>Créé: <?= date('d/m/Y H:i', strtotime($row['created_at'])) ?></div>
                                    <?php if ($row['updated_at'] !== $row['created_at']): ?>
                                        <div>MAJ: <?= date('d/m/Y H:i', strtotime($row['updated_at'])) ?></div>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <td class="actions-column">
                                <!-- Modifier le statut -->
                                <form method="POST" class="form-inline">
                                    <input type="hidden" name="rendezvous_id" value="<?= $row['id'] ?>">
                                    <select name="statut">
                                        <option value="pending"<?= $row['status'] == 'pending' ? ' selected' : '' ?>>En attente</option>
                                        <option value="confirmed"<?= $row['status'] == 'confirmed' ? ' selected' : '' ?>>Confirmé</option>
                                        <option value="cancelled"<?= $row['status'] == 'cancelled' ? ' selected' : '' ?>>Annulé</option>
                                    </select>
                                    <button type="submit" name="modifier_statut" class="btn btn-primary">✔</button>
                                </form>
                                
                                <!-- Reporter le rendez-vous -->
                                <form method="POST" class="form-inline">
                                    <input type="hidden" name="rendezvous_id" value="<?= $row['id'] ?>">
                                    <input type="date" name="nouvelle_date" value="<?= $row['date'] ?>" required style="width: 110px;">
                                    <input type="time" name="nouvelle_heure" value="<?= $row['heure'] ?>" required style="width: 80px;">
                                    <button type="submit" name="reporter_rdv" class="btn btn-warning">📅</button>
                                </form>

                                <!-- Supprimer -->
                                <form method="POST" class="form-inline" onsubmit="return confirm('Êtes-vous sûr de vouloir supprimer ce rendez-vous ?')">
                                    <input type="hidden" name="rendezvous_id" value="<?= $row['id'] ?>">
                                    <button type="submit" name="supprimer_rdv" class="btn btn-danger">🗑️</button>
                                </form>
                            </td>
                        </tr>
                    <?php 
                        endwhile;
                    } catch (Exception $e) {
                        echo "<tr><td colspan='11' style='text-align: center; color: #e74c3c;'>❌ Erreur lors du chargement des rendez-vous : " . htmlspecialchars($e->getMessage()) . "</td></tr>";
                    }
                    ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
// Auto-refresh toutes les 5 minutes
setTimeout(function() {
    location.reload();
}, 300000);

// Confirmation avant suppression
function confirmerSuppression() {
    return confirm('⚠️ Êtes-vous sûr de vouloir supprimer ce rendez-vous ? Cette action est irréversible.');
}
</script>

</body>
</html>