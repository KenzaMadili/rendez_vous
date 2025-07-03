<?php
session_start();

// Vérification : connecté + rôle admin
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'admin') {
    header("Location: ../login.php");
    exit();
}

try {
    $pdo = new PDO("mysql:host=localhost;dbname=rendezvs;charset=utf8mb4", "root", "");
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Erreur de connexion à la base : " . $e->getMessage());
}

$success = '';
$error = '';
$editService = null;

// Gestion de la suppression de service
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_service'])) {
    $serviceId = intval($_POST['service_id']);
    
    try {
        $pdo->beginTransaction();
        
        // Vérifier si le service existe
        $stmt = $pdo->prepare("SELECT name FROM services WHERE id = ?");
        $stmt->execute([$serviceId]);
        $service = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$service) {
            throw new Exception("Service non trouvé.");
        }
        
        // Supprimer les assignations de personnel
        $stmt = $pdo->prepare("DELETE FROM service_personnel WHERE service_id = ?");
        $stmt->execute([$serviceId]);
        
        // Supprimer les rendez-vous associés
        $stmt = $pdo->prepare("DELETE FROM rendezvous WHERE service_id = ?");
        $stmt->execute([$serviceId]);
        
        // Supprimer le service
        $stmt = $pdo->prepare("DELETE FROM services WHERE id = ?");
        $stmt->execute([$serviceId]);
        
        $pdo->commit();
        $success = "Service '" . htmlspecialchars($service['name']) . "' supprimé avec succès !";
        
    } catch (Exception $e) {
        $pdo->rollBack();
        $error = "Erreur lors de la suppression : " . $e->getMessage();
    }
}

// Gestion de la modification de service
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_service'])) {
    $serviceId = intval($_POST['service_id']);
    $name = trim($_POST['name'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $duration = intval($_POST['duration'] ?? 0);
    $price = floatval($_POST['price'] ?? 0);
    $is_active = isset($_POST['is_active']) ? 1 : 0;

    // Validation
    $errors = [];
    if (empty($name)) $errors[] = "Le nom du service est requis";
    if (empty($description)) $errors[] = "La description est requise";
    if ($duration <= 0) $errors[] = "La durée doit être supérieure à 0";
    if ($price < 0) $errors[] = "Le prix ne peut pas être négatif";

    if (empty($errors)) {
        try {
            $stmt = $pdo->prepare("UPDATE services SET name = ?, description = ?, duration = ?, price = ?, is_active = ? WHERE id = ?");
            $stmt->execute([$name, $description, $duration, $price, $is_active, $serviceId]);
            $success = "Service modifié avec succès !";
        } catch (PDOException $e) {
            $error = "Erreur lors de la modification : " . $e->getMessage();
        }
    } else {
        $error = implode(", ", $errors);
    }
}

// Récupération du service à modifier
if (isset($_GET['edit']) && is_numeric($_GET['edit'])) {
    $editId = intval($_GET['edit']);
    $stmt = $pdo->prepare("SELECT * FROM services WHERE id = ?");
    $stmt->execute([$editId]);
    $editService = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$editService) {
        $error = "Service non trouvé.";
    }
}

// Gestion de l'ajout de service
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_service'])) {
    $name = trim($_POST['name'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $duration = intval($_POST['duration'] ?? 0);
    $price = floatval($_POST['price'] ?? 0);
    $is_active = isset($_POST['is_active']) ? 1 : 0;

    // Validation
    $errors = [];
    if (empty($name)) $errors[] = "Le nom du service est requis";
    if (empty($description)) $errors[] = "La description est requise";
    if ($duration <= 0) $errors[] = "La durée doit être supérieure à 0";
    if ($price < 0) $errors[] = "Le prix ne peut pas être négatif";

    if (empty($errors)) {
        try {
            $stmt = $pdo->prepare("INSERT INTO services (name, description, duration, price, is_active, created_at) VALUES (?, ?, ?, ?, ?, NOW())");
            $stmt->execute([$name, $description, $duration, $price, $is_active]);
            $success = "Service ajouté avec succès !";
        } catch (PDOException $e) {
            $error = "Erreur lors de l'ajout : " . $e->getMessage();
        }
    } else {
        $error = implode(", ", $errors);
    }
}

// Gestion des actions (activation/désactivation)
if (isset($_GET['action']) && isset($_GET['id'])) {
    $serviceId = intval($_GET['id']);
    $action = $_GET['action'];
    
    if ($action === 'toggle_status') {
        try {
            $stmt = $pdo->prepare("UPDATE services SET is_active = NOT is_active WHERE id = ?");
            $stmt->execute([$serviceId]);
            $success = "Statut du service mis à jour avec succès !";
        } catch (PDOException $e) {
            $error = "Erreur lors de la modification : " . $e->getMessage();
        }
    }
}

// Récupérer les services avec statistiques et personnel
$sql = "SELECT s.*, 
        COUNT(DISTINCT sp.personnel_id) as personnel_count,
        COUNT(DISTINCT r.id) as rendezvous_count,
        GROUP_CONCAT(
            CASE 
                WHEN sp.is_active = 1 THEN u.name 
                ELSE NULL 
            END 
            ORDER BY u.name 
            SEPARATOR ', '
        ) as personnel_names
        FROM services s
        LEFT JOIN service_personnel sp ON s.id = sp.service_id
        LEFT JOIN users u ON sp.personnel_id = u.id AND u.role = 'personnel'
        LEFT JOIN rendezvous r ON s.id = r.service_id
        GROUP BY s.id
        ORDER BY s.created_at DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute();
$services = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Statistiques générales
$stats = [
    'total' => count($services),
    'active' => count(array_filter($services, function($s) { return $s['is_active']; })),
    'inactive' => count(array_filter($services, function($s) { return !$s['is_active']; }))
];

$adminName = $_SESSION['name'] ?? 'Admin';
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion des Services - Admin</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
            line-height: 1.6;
            color: #333;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
        }

        .container {
            max-width: 1400px;
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

        .header p {
            color: #666;
            font-size: 1.1rem;
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
        .stat-active { color: #38a169; }
        .stat-inactive { color: #e53e3e; }

        .stat-label {
            color: #666;
            font-weight: 600;
        }

        .card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            border-radius: 20px;
            padding: 2rem;
            margin-bottom: 2rem;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
        }

        .card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
            padding-bottom: 1rem;
            border-bottom: 2px solid #f0f4f8;
        }

        .card-title {
            font-size: 1.5rem;
            font-weight: 700;
            color: #4a5568;
        }

        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 1.5rem;
            align-items: start;
        }

        .form-group {
            margin-bottom: 1.5rem;
        }

        .form-label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 600;
            color: #4a5568;
        }

        .form-control {
            width: 100%;
            padding: 0.8rem 1rem;
            border: 2px solid #e2e8f0;
            border-radius: 10px;
            font-size: 1rem;
            transition: all 0.3s ease;
            background: white;
        }

        .form-control:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }

        .form-control.textarea {
            resize: vertical;
            min-height: 100px;
        }

        .checkbox-group {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            margin-top: 1rem;
        }

        .checkbox-group input[type="checkbox"] {
            width: 20px;
            height: 20px;
            accent-color: #667eea;
        }

        .checkbox-group label {
            font-weight: 500;
            color: #4a5568;
            margin: 0;
        }

        .btn {
            padding: 0.8rem 1.5rem;
            border: none;
            border-radius: 10px;
            cursor: pointer;
            font-weight: 600;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            transition: all 0.3s ease;
            font-size: 0.95rem;
        }

        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }

        .btn-primary:hover {
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

        .btn-toggle-form {
            background: #4299e1;
            color: white;
        }

        .btn-toggle-form:hover {
            background: #3182ce;
            transform: translateY(-2px);
        }

        .btn-cancel {
            background: #718096;
            color: white;
        }

        .btn-cancel:hover {
            background: #4a5568;
            transform: translateY(-2px);
        }

        .edit-mode {
            background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
            border: 2px solid #f093fb;
        }

        .actions-bar {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            border-radius: 15px;
            padding: 1.5rem;
            margin-bottom: 2rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 1rem;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
        }

        .search-box {
            flex: 1;
            max-width: 400px;
        }

        .search-box input {
            width: 100%;
            padding: 0.8rem 1rem;
            border: 2px solid #e2e8f0;
            border-radius: 10px;
            font-size: 1rem;
            transition: all 0.3s ease;
        }

        .search-box input:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }

        .services-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 1rem;
        }

        .services-table th,
        .services-table td {
            padding: 1rem;
            text-align: left;
            border-bottom: 1px solid #e2e8f0;
            vertical-align: top;
        }

        .services-table th {
            background: #f8fafc;
            font-weight: 600;
            color: #4a5568;
            position: sticky;
            top: 0;
        }

        .services-table tbody tr {
            transition: background-color 0.2s ease;
        }

        .services-table tbody tr:hover {
            background: #f8fafc;
        }

        .status-badge {
            padding: 0.25rem 0.75rem;
            border-radius: 50px;
            font-size: 0.8rem;
            font-weight: 600;
            text-align: center;
            display: inline-block;
        }

        .status-active {
            background: #c6f6d5;
            color: #22543d;
        }

        .status-inactive {
            background: #fed7d7;
            color: #742a2a;
        }

        .actions-cell {
            display: flex;
            gap: 0.5rem;
            align-items: center;
            flex-wrap: wrap;
        }

        .btn-sm {
            padding: 0.4rem 0.8rem;
            font-size: 0.8rem;
            border-radius: 6px;
            text-decoration: none;
            font-weight: 500;
            transition: all 0.3s ease;
            border: none;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 0.25rem;
        }

        .btn-edit {
            background: #4299e1;
            color: white;
        }

        .btn-edit:hover {
            background: #3182ce;
            transform: translateY(-1px);
        }

        .btn-delete {
            background: #e53e3e;
            color: white;
        }

        .btn-delete:hover {
            background: #c53030;
            transform: translateY(-1px);
        }

        .btn-toggle {
            background: #38a169;
            color: white;
        }

        .btn-toggle:hover {
            background: #2f855a;
            transform: translateY(-1px);
        }

        .btn-toggle.inactive {
            background: #718096;
        }

        .btn-toggle.inactive:hover {
            background: #4a5568;
        }

        .empty-state {
            text-align: center;
            padding: 3rem;
            color: #666;
        }

        .empty-state h3 {
            margin-bottom: 1rem;
            color: #4a5568;
        }

        .alert {
            padding: 1rem;
            border-radius: 10px;
            margin-bottom: 1rem;
            font-weight: 500;
        }

        .alert-success {
            background: #c6f6d5;
            color: #22543d;
            border-left: 4px solid #38a169;
        }

        .alert-error {
            background: #fed7d7;
            color: #742a2a;
            border-left: 4px solid #e53e3e;
        }

        .service-description {
            max-width: 200px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .service-description:hover {
            white-space: normal;
            overflow: visible;
        }

        .stats-mini {
            font-size: 0.8rem;
            color: #666;
        }

        .form-section {
            display: none;
            animation: slideDown 0.3s ease;
        }

        .form-section.active {
            display: block;
        }

        .personnel-list {
            max-width: 250px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
            font-size: 0.9rem;
            color: #4a5568;
            background: #f8fafc;
            padding: 0.5rem;
            border-radius: 6px;
            border-left: 3px solid #667eea;
        }

        .personnel-list:hover {
            white-space: normal;
            overflow: visible;
            z-index: 10;
            position: relative;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
        }

        .personnel-empty {
            color: #a0aec0;
            font-style: italic;
            font-size: 0.8rem;
        }

        .personnel-item {
            display: inline-block;
            background: #e2e8f0;
            color: #4a5568;
            padding: 0.2rem 0.5rem;
            border-radius: 4px;
            font-size: 0.8rem;
            margin: 0.1rem;
        }

        /* Modal Styles */
        .modal-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            backdrop-filter: blur(5px);
            display: none;
            z-index: 1000;
        }

        .modal {
            position: fixed;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            background: white;
            border-radius: 15px;
            padding: 2rem;
            max-width: 500px;
            width: 90%;
            max-height: 90vh;
            overflow-y: auto;
            box-shadow: 0 20px 50px rgba(0, 0, 0, 0.3);
            z-index: 1001;
        }

        .modal-header {
            display: flex;
            align-items: center;
            margin-bottom: 1.5rem;
            padding-bottom: 1rem;
            border-bottom: 2px solid #f0f4f8;
        }

        .modal-title {
            font-size: 1.25rem;
            font-weight: 700;
            color: #e53e3e;
            margin: 0;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .modal-body {
            margin-bottom: 2rem;
        }

        .modal-service-info {
            background: #f8fafc;
            border-radius: 10px;
            padding: 1rem;
            margin: 1rem 0;
        }

        .modal-service-info h4 {
            color: #4a5568;
            margin-bottom: 0.5rem;
            font-size: 1.1rem;
        }

        .modal-service-info p {
            color: #666;
            margin: 0.25rem 0;
        }

        .modal-warning {
            background: #fed7d7;
            border: 1px solid #feb2b2;
            border-radius: 8px;
            padding: 1rem;
            margin: 1rem 0;
            color: #742a2a;
        }

        .modal-warning ul {
            margin: 0.5rem 0 0 1.5rem;
        }

        .modal-actions {
            display: flex;
            gap: 1rem;
            justify-content: flex-end;
        }

        .btn-modal-cancel {
            background: #e2e8f0;
            color: #4a5568;
            border: none;
            padding: 0.75rem 1.5rem;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
            transition: all 0.3s ease;
        }

        .btn-modal-cancel:hover {
            background: #cbd5e0;
        }

        .btn-modal-confirm {
            background: #e53e3e;
            color: white;
            border: none;
            padding: 0.75rem 1.5rem;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
            transition: all 0.3s ease;
        }

        .btn-modal-confirm:hover {
            background: #c53030;
        }

        @keyframes slideDown {
            from {
                opacity: 0;
                transform: translateY(-20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
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
            
            .form-grid {
                grid-template-columns: 1fr;
            }
            
            .actions-bar {
                flex-direction: column;
                align-items: stretch;
            }
            
            .search-box {
                max-width: 100%;
            }
            
            .services-table {
                font-size: 0.9rem;
            }
            
            .services-table th,
            .services-table td {
                padding: 0.75rem 0.5rem;
            }
            
            .actions-cell {
                flex-direction: column;
                align-items: stretch;
            }

            .modal {
                width: 95%;
                padding: 1.5rem;
            }

            .personnel-list {
                max-width: 150px;
                font-size: 0.8rem;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Gestion des Services</h1>
            <p>Bienvenue, <?php echo htmlspecialchars($adminName); ?></p>
        </div>

        <!-- Statistiques -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-number stat-total"><?php echo $stats['total']; ?></div>
                <div class="stat-label">Total Services</div>
            </div>
            <div class="stat-card">
                <div class="stat-number stat-active"><?php echo $stats['active']; ?></div>
                <div class="stat-label">Services Actifs</div>
            </div>
            <div class="stat-card">
                <div class="stat-number stat-inactive"><?php echo $stats['inactive']; ?></div>
                <div class="stat-label">Services Inactifs</div>
            </div>
        </div>

        <!-- Messages d'alerte -->
        <?php if (!empty($success)): ?>
            <div class="alert alert-success">
                ✅ <?php echo htmlspecialchars($success); ?>
            </div>
        <?php endif; ?>

        <?php if (!empty($error)): ?>
            <div class="alert alert-error">
                ❌ <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>

        <!-- Formulaire d'ajout/modification de service -->
        <div class="card <?php echo $editService ? 'edit-mode' : ''; ?>">
            <div class="card-header">
                <h2 class="card-title">
                    <?php echo $editService ? '✏️ Modifier le service: ' . htmlspecialchars($editService['name']) : 'Ajouter un nouveau service'; ?>
                </h2>
                <div style="display: flex; gap: 1rem;">
                    <?php if ($editService): ?>
                        <a href="?" class="btn btn-cancel">❌ Annuler la modification</a>
                    <?php endif; ?>
                    <button type="button" class="btn btn-toggle-form" onclick="toggleForm()" 
                            <?php echo $editService ? 'style="display: none;"' : ''; ?>>
                        <span id="toggleText">📝 Afficher le formulaire</span>
                    </button>
                </div>
            </div>
            
            <div class="form-section <?php echo $editService ? 'active' : ''; ?>" id="serviceForm">
                <form method="POST" action="">
                    <?php if ($editService): ?>
                        <input type="hidden" name="service_id" value="<?php echo $editService['id']; ?>">
                    <?php endif; ?>
                    
                    <div class="form-grid">
                        <div>
                            <div class="form-group">
                                <label for="name" class="form-label">Nom du service *</label>
                                <input type="text" id="name" name="name" class="form-control" 
                                       placeholder="Ex: Consultation générale" 
                                       value="<?php echo $editService ? htmlspecialchars($editService['name']) : ''; ?>" 
                                       required>
                            </div>

                            <div class="form-group">
                                <label for="duration" class="form-label">Durée (minutes) *</label>
                                <input type="number" id="duration" name="duration" class="form-control" 
                                       placeholder="Ex: 30" min="1" 
                                       value="<?php echo $editService ? $editService['duration'] : ''; ?>" 
                                       required>
                            </div>

                            <div class="form-group">
                                <label for="price" class="form-label">Prix (MAD) *</label>
                                <input type="number" id="price" name="price" class="form-control" 
                                       placeholder="Ex: 200.00" min="0" step="0.01" 
                                       value="<?php echo $editService ? $editService['price'] : ''; ?>" 
                                       required>
                            </div>
                        </div>

                        <div>
                            <div class="form-group">
                                <label for="description" class="form-label">Description *</label>
                                <textarea id="description" name="description" class="form-control textarea" 
                                          placeholder="Description détaillée du service..." 
                                          required><?php echo $editService ? htmlspecialchars($editService['description']) : ''; ?></textarea>
                            <div class="checkbox-group">
                                <input type="checkbox" id="is_active" name="is_active" 
                                       <?php echo ($editService && $editService['is_active']) || !$editService ? 'checked' : ''; ?>>
                                <label for="is_active">Service actif</label>
                            </div>
                        </div>
                    </div>

                    <div style="margin-top: 2rem; text-align: center;">
                        <button type="submit" name="<?php echo $editService ? 'edit_service' : 'add_service'; ?>" 
                                class="btn btn-primary">
                            <?php echo $editService ? '💾 Modifier le service' : '➕ Ajouter le service'; ?>
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Barre d'actions -->
        <div class="actions-bar">
            <div class="search-box">
                <input type="text" id="searchInput" placeholder="🔍 Rechercher un service..." 
                       onkeyup="filterServices()">
            </div>
            <div style="display: flex; gap: 1rem;">
                <a href="../admin/dashboard.php" class="btn btn-back">⬅️ Retour au tableau de bord</a>
            </div>
        </div>

        <!-- Liste des services -->
        <div class="card">
            <div class="card-header">
                <h2 class="card-title">📋 Liste des services</h2>
                <div class="stats-mini">
                    Total: <?php echo count($services); ?> services
                </div>
            </div>

            <?php if (empty($services)): ?>
                <div class="empty-state">
                    <h3>Aucun service trouvé</h3>
                    <p>Commencez par ajouter votre premier service using the form above.</p>
                </div>
            <?php else: ?>
                <div style="overflow-x: auto;">
                    <table class="services-table" id="servicesTable">
                        <thead>
                            <tr>
                                <th>Service</th>
                                <th>Description</th>
                                <th>Personnel Assigné</th>
                                <th>Durée</th>
                                <th>Prix</th>
                                <th>Statut</th>
                                <th>Statistiques</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($services as $service): ?>
                                <tr>
                                    <td>
                                        <strong><?php echo htmlspecialchars($service['name']); ?></strong>
                                        <div style="font-size: 0.8rem; color: #666; margin-top: 0.25rem;">
                                            Créé le <?php echo date('d/m/Y', strtotime($service['created_at'])); ?>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="service-description" title="<?php echo htmlspecialchars($service['description']); ?>">
                                            <?php echo htmlspecialchars($service['description']); ?>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="personnel-list">
                                            <?php if (!empty($service['personnel_names'])): ?>
                                                <?php 
                                                $personnel_names = explode(', ', $service['personnel_names']);
                                                foreach ($personnel_names as $name): ?>
                                                    <span class="personnel-item"><?php echo htmlspecialchars($name); ?></span>
                                                <?php endforeach; ?>
                                            <?php else: ?>
                                                <span class="personnel-empty">Aucun personnel assigné</span>
                                            <?php endif; ?>
                                        </div>
                                        <div style="font-size: 0.7rem; color: #666; margin-top: 0.25rem;">
                                            <?php echo $service['personnel_count']; ?> personnel(s)
                                        </div>
                                    </td>
                                    <td>
                                        <strong><?php echo $service['duration']; ?> min</strong>
                                    </td>
                                    <td>
                                        <strong><?php echo number_format($service['price'], 2); ?> MAD</strong>
                                    </td>
                                    <td>
                                        <span class="status-badge <?php echo $service['is_active'] ? 'status-active' : 'status-inactive'; ?>">
                                            <?php echo $service['is_active'] ? '✅ Actif' : '❌ Inactif'; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div style="font-size: 0.8rem; color: #666;">
                                            <div>📅 <?php echo $service['rendezvous_count']; ?> RDV</div>
                                            <div>👥 <?php echo $service['personnel_count']; ?> personnel</div>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="actions-cell">
                                            <a href="?edit=<?php echo $service['id']; ?>" 
                                               class="btn-sm btn-edit" title="Modifier">
                                                ✏️ Modifier
                                            </a>
                                            
                                            <a href="?action=toggle_status&id=<?php echo $service['id']; ?>" 
                                               class="btn-sm btn-toggle <?php echo !$service['is_active'] ? 'inactive' : ''; ?>" 
                                               title="<?php echo $service['is_active'] ? 'Désactiver' : 'Activer'; ?>">
                                                <?php echo $service['is_active'] ? '⏸️ Désactiver' : '▶️ Activer'; ?>
                                            </a>
                                            
                                            <button type="button" 
                                                    class="btn-sm btn-delete" 
                                                    onclick="confirmDelete(<?php echo $service['id']; ?>, '<?php echo htmlspecialchars($service['name'], ENT_QUOTES); ?>', <?php echo $service['personnel_count']; ?>, <?php echo $service['rendezvous_count']; ?>)"
                                                    title="Supprimer">
                                                🗑️ Supprimer
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Modal de confirmation de suppression -->
    <div class="modal-overlay" id="deleteModal">
        <div class="modal">
            <div class="modal-header">
                <h3 class="modal-title">🗑️ Confirmer la suppression</h3>
            </div>
            <div class="modal-body">
                <p>Êtes-vous sûr de vouloir supprimer ce service ?</p>
                <div class="modal-service-info" id="serviceInfo">
                    <!-- Les informations du service seront ajoutées ici par JavaScript -->
                </div>
                <div class="modal-warning">
                    <strong>⚠️ Attention :</strong> Cette action est irréversible et va :
                    <ul id="warningList">
                        <!-- Les avertissements seront ajoutés ici par JavaScript -->
                    </ul>
                </div>
            </div>
            <div class="modal-actions">
                <button type="button" class="btn-modal-cancel" onclick="closeDeleteModal()">
                    Annuler
                </button>
                <form method="POST" style="display: inline;" id="deleteForm">
                    <input type="hidden" name="service_id" id="deleteServiceId">
                    <button type="submit" name="delete_service" class="btn-modal-confirm">
                        🗑️ Supprimer définitivement
                    </button>
                </form>
            </div>
        </div>
    </div>

    <script>
        // Fonction pour basculer l'affichage du formulaire
        function toggleForm() {
            const form = document.getElementById('serviceForm');
            const toggleBtn = document.querySelector('.btn-toggle-form');
            const toggleText = document.getElementById('toggleText');
            
            if (form.classList.contains('active')) {
                form.classList.remove('active');
                toggleText.textContent = '📝 Afficher le formulaire';
            } else {
                form.classList.add('active');
                toggleText.textContent = '❌ Masquer le formulaire';
            }
        }

        // Fonction de recherche
        function filterServices() {
            const input = document.getElementById('searchInput');
            const filter = input.value.toLowerCase();
            const table = document.getElementById('servicesTable');
            const rows = table.getElementsByTagName('tr');

            for (let i = 1; i < rows.length; i++) {
                const cells = rows[i].getElementsByTagName('td');
                let found = false;
                
                for (let j = 0; j < cells.length; j++) {
                    const cell = cells[j];
                    if (cell && cell.textContent.toLowerCase().indexOf(filter) > -1) {
                        found = true;
                        break;
                    }
                }
                
                rows[i].style.display = found ? '' : 'none';
            }
        }

        // Fonction pour confirmer la suppression
        function confirmDelete(serviceId, serviceName, personnelCount, rdvCount) {
            const modal = document.getElementById('deleteModal');
            const serviceInfo = document.getElementById('serviceInfo');
            const warningList = document.getElementById('warningList');
            const deleteServiceId = document.getElementById('deleteServiceId');
            
            // Remplir les informations du service
            serviceInfo.innerHTML = `
                <h4>${serviceName}</h4>
                <p><strong>Personnel assigné :</strong> ${personnelCount} personne(s)</p>
                <p><strong>Rendez-vous :</strong> ${rdvCount} RDV</p>
            `;
            
            // Remplir les avertissements
            let warnings = [];
            if (personnelCount > 0) {
                warnings.push(`Supprimer les assignations de ${personnelCount} membre(s) du personnel`);
            }
            if (rdvCount > 0) {
                warnings.push(`Supprimer ${rdvCount} rendez-vous lié(s) à ce service`);
            }
            warnings.push('Supprimer définitivement le service de la base de données');
            
            warningList.innerHTML = warnings.map(warning => `<li>${warning}</li>`).join('');
            
            // Définir l'ID du service à supprimer
            deleteServiceId.value = serviceId;
            
            // Afficher le modal
            modal.style.display = 'block';
        }

        // Fonction pour fermer le modal
        function closeDeleteModal() {
            const modal = document.getElementById('deleteModal');
            modal.style.display = 'none';
        }

        // Fermer le modal en cliquant en dehors
        window.onclick = function(event) {
            const modal = document.getElementById('deleteModal');
            if (event.target === modal) {
                closeDeleteModal();
            }
        }

        // Auto-masquer les alertes après 5 secondes
        document.addEventListener('DOMContentLoaded', function() {
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(function(alert) {
                setTimeout(function() {
                    alert.style.transition = 'opacity 0.5s ease';
                    alert.style.opacity = '0';
                    setTimeout(function() {
                        alert.remove();
                    }, 500);
                }, 5000);
            });
        });
    </script>
</body>
</html>