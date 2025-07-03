<?php
session_start();

// Vérifier si l'utilisateur est connecté
if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit();
}

// Configuration de la base de données
$host = 'localhost';
$dbname = 'rendezvs';
$username = 'root';
$password = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", "root", "");
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    die("Erreur de connexion : " . $e->getMessage());
}

$user_id = $_SESSION['user_id'];
$user_name = $_SESSION['name'] ?? 'Utilisateur';

// Traitement des actions AJAX
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    switch ($_POST['action']) {
        case 'add':
            try {
                $is_available = isset($_POST['is_available']) ? (int)$_POST['is_available'] : 1;
                $stmt = $pdo->prepare("
                    INSERT INTO disponibilites (personnel_id, date, heure_debut, heure_fin, is_available, created_at) 
                    VALUES (?, ?, ?, ?, ?, NOW())
                ");
                $stmt->execute([
                    $user_id,
                    $_POST['date'],
                    $_POST['heure_debut'],
                    $_POST['heure_fin'],
                    $is_available
                ]);
                $message = $is_available ? 'Disponibilité ajoutée avec succès!' : 'Créneau occupé ajouté avec succès!';
                echo json_encode(['success' => true, 'message' => $message]);
            } catch(PDOException $e) {
                echo json_encode(['success' => false, 'message' => 'Erreur: ' . $e->getMessage()]);
            }
            exit;
            
        case 'update':
            try {
                $is_available = isset($_POST['is_available']) ? (int)$_POST['is_available'] : 1;
                $stmt = $pdo->prepare("
                    UPDATE disponibilites 
                    SET date = ?, heure_debut = ?, heure_fin = ?, is_available = ? 
                    WHERE id = ? AND personnel_id = ?
                ");
                $stmt->execute([
                    $_POST['date'],
                    $_POST['heure_debut'],
                    $_POST['heure_fin'],
                    $is_available,
                    $_POST['id'],
                    $user_id
                ]);
                echo json_encode(['success' => true, 'message' => 'Disponibilité modifiée avec succès!']);
            } catch(PDOException $e) {
                echo json_encode(['success' => false, 'message' => 'Erreur: ' . $e->getMessage()]);
            }
            exit;
            
        case 'delete':
            try {
                $stmt = $pdo->prepare("DELETE FROM disponibilites WHERE id = ? AND personnel_id = ?");
                $stmt->execute([$_POST['id'], $user_id]);
                echo json_encode(['success' => true, 'message' => 'Disponibilité supprimée avec succès!']);
            } catch(PDOException $e) {
                echo json_encode(['success' => false, 'message' => 'Erreur: ' . $e->getMessage()]);
            }
            exit;
            
        case 'duplicate':
            try {
                // Récupérer la disponibilité à dupliquer
                $stmt = $pdo->prepare("SELECT * FROM disponibilites WHERE id = ? AND personnel_id = ?");
                $stmt->execute([$_POST['id'], $user_id]);
                $disponibilite = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($disponibilite) {
                    // Créer une nouvelle date (jour suivant)
                    $new_date = date('Y-m-d', strtotime($disponibilite['date'] . ' +1 day'));
                    
                    $stmt = $pdo->prepare("
                        INSERT INTO disponibilites (personnel_id, date, heure_debut, heure_fin, is_available, created_at) 
                        VALUES (?, ?, ?, ?, ?, NOW())
                    ");
                    $stmt->execute([
                        $user_id,
                        $new_date,
                        $disponibilite['heure_debut'],
                        $disponibilite['heure_fin'],
                        $disponibilite['is_available']
                    ]);
                    echo json_encode(['success' => true, 'message' => 'Disponibilité dupliquée avec succès!']);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Disponibilité non trouvée!']);
                }
            } catch(PDOException $e) {
                echo json_encode(['success' => false, 'message' => 'Erreur: ' . $e->getMessage()]);
            }
            exit;
            
        case 'toggle_status':
            try {
                // Récupérer le statut actuel
                $stmt = $pdo->prepare("SELECT is_available FROM disponibilites WHERE id = ? AND personnel_id = ?");
                $stmt->execute([$_POST['id'], $user_id]);
                $current_status = $stmt->fetchColumn();
                
                // Inverser le statut
                $new_status = $current_status ? 0 : 1;
                
                $stmt = $pdo->prepare("UPDATE disponibilites SET is_available = ? WHERE id = ? AND personnel_id = ?");
                $stmt->execute([$new_status, $_POST['id'], $user_id]);
                
                $message = $new_status ? 'Créneau marqué comme disponible!' : 'Créneau marqué comme occupé!';
                echo json_encode(['success' => true, 'message' => $message]);
            } catch(PDOException $e) {
                echo json_encode(['success' => false, 'message' => 'Erreur: ' . $e->getMessage()]);
            }
            exit;
    }
}

// Récupérer les disponibilités du personnel connecté
$filter_date = $_GET['filter_date'] ?? '';
$filter_status = $_GET['filter_status'] ?? '';
$search = $_GET['search'] ?? '';

$query = "
    SELECT d.*, u.name as personnel_name,
           CASE 
               WHEN EXISTS (
                   SELECT 1 FROM rendezvous r 
                   WHERE r.personnel_id = d.personnel_id 
                   AND DATE(r.date) = d.date 
                   AND r.heure BETWEEN d.heure_debut AND d.heure_fin
                   AND r.status != 'cancelled'
               ) THEN 0 
               ELSE d.is_available 
           END as is_actually_available
    FROM disponibilites d
    JOIN users u ON d.personnel_id = u.id
    WHERE d.personnel_id = ?
";

$params = [$user_id];

if ($filter_date) {
    $query .= " AND d.date = ?";
    $params[] = $filter_date;
}

if ($filter_status) {
    if ($filter_status === 'disponible') {
        $query .= " AND d.is_available = 1";
    } elseif ($filter_status === 'occupe') {
        $query .= " AND d.is_available = 0";
    } elseif ($filter_status === 'rdv') {
        $query .= " AND EXISTS (
            SELECT 1 FROM rendezvous r 
            WHERE r.personnel_id = d.personnel_id 
            AND DATE(r.date) = d.date 
            AND r.heure BETWEEN d.heure_debut AND d.heure_fin
            AND r.status != 'cancelled'
        )";
    }
}

$query .= " ORDER BY d.date DESC, d.heure_debut ASC";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$disponibilites = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Récupérer les rendez-vous pour vérifier les créneaux occupés
$rdv_query = "
    SELECT DATE(date) as rdv_date, heure, 
           u.name as client_name,
           'Service' as service_name
    FROM rendezvous r
    JOIN users u ON r.client_id = u.id
    WHERE r.personnel_id = ? AND r.status != 'cancelled'
";
$rdv_stmt = $pdo->prepare($rdv_query);
$rdv_stmt->execute([$user_id]);
$rendez_vous = $rdv_stmt->fetchAll(PDO::FETCH_ASSOC);

// Organiser les RDV par date et heure
$rdv_by_date_time = [];
foreach ($rendez_vous as $rdv) {
    $rdv_by_date_time[$rdv['rdv_date']][$rdv['heure']] = $rdv;
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mes Disponibilités - Amsoft</title>
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
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 2rem;
        }

        nav {
            display: flex;
            justify-content: space-between;
            align-items: center;
            height: 70px;
        }

        .logo {
            font-size: 1.5rem;
            font-weight: 800;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .nav-links {
            display: flex;
            gap: 2rem;
        }

        .nav-links a {
            text-decoration: none;
            color: #333;
            font-weight: 500;
            padding: 0.5rem 1rem;
            border-radius: 8px;
            transition: all 0.3s ease;
        }

        .nav-links a:hover {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            transform: translateY(-2px);
        }

        .nav-links a.active {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }

        .nav-links a[href="../logout.php"] {
            background: #ff4757;
            color: white;
        }

        /* Main content */
        main {
            padding: 100px 0 4rem;
            min-height: 100vh;
        }

        .page-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 25px;
            padding: 2rem;
            color: white;
            text-align: center;
            margin-bottom: 3rem;
            position: relative;
        }

        .page-header::before {
            content: '⏰';
            position: absolute;
            top: -10px;
            right: 20px;
            font-size: 3rem;
            opacity: 0.3;
        }

        .page-header h1 {
            font-size: 2.5rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
        }

        .page-header p {
            font-size: 1.1rem;
            opacity: 0.9;
        }

        .user-info {
            background: white;
            border-radius: 15px;
            padding: 1.5rem;
            margin-bottom: 2rem;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .user-details h3 {
            color: #333;
            margin-bottom: 0.5rem;
        }

        .user-details p {
            color: #666;
            font-size: 0.9rem;
        }

        .action-bar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
            flex-wrap: wrap;
            gap: 1rem;
        }

        .action-buttons {
            display: flex;
            gap: 1rem;
            flex-wrap: wrap;
        }

        .search-bar {
            display: flex;
            align-items: center;
            background: white;
            border-radius: 10px;
            padding: 0.5rem;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            flex: 1;
            max-width: 400px;
        }

        .search-bar input {
            border: none;
            outline: none;
            padding: 0.5rem;
            font-size: 1rem;
            flex: 1;
        }

        .search-bar .search-icon {
            color: #667eea;
            margin-left: 0.5rem;
        }

        .btn-add {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 0.8rem 1.5rem;
            border: none;
            border-radius: 10px;
            cursor: pointer;
            font-weight: 600;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }

        .btn-add:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
        }

        .btn-add-busy {
            background: linear-gradient(135deg, #ff6b6b 0%, #ff8e8e 100%);
            color: white;
            padding: 0.8rem 1.5rem;
            border: none;
            border-radius: 10px;
            cursor: pointer;
            font-weight: 600;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }

        .btn-add-busy:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(255, 107, 107, 0.4);
        }

        .disponibilites-grid {
            display: grid;
            gap: 1.5rem;
        }

        .disponibilite-card {
            background: white;
            border-radius: 15px;
            padding: 2rem;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
            transition: all 0.3s ease;
            border-left: 4px solid #667eea;
        }

        .disponibilite-card.occupied {
            border-left-color: #ff6b6b;
        }

        .disponibilite-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.15);
        }

        .disponibilite-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1rem;
        }

        .personnel-info {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .personnel-avatar {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: bold;
            font-size: 1.2rem;
        }

        .personnel-avatar.occupied {
            background: linear-gradient(135deg, #ff6b6b 0%, #ff8e8e 100%);
        }

        .personnel-details h3 {
            font-size: 1.2rem;
            color: #333;
            margin-bottom: 0.2rem;
        }

        .personnel-details p {
            color: #666;
            font-size: 0.9rem;
        }

        .disponibilite-date {
            background: #f8fafc;
            padding: 0.5rem 1rem;
            border-radius: 8px;
            font-weight: 600;
            color: #333;
        }

        .time-info {
            background: #f8fafc;
            padding: 1.5rem;
            border-radius: 10px;
            margin-bottom: 1.5rem;
            text-align: center;
        }

        .time-range {
            font-size: 1.5rem;
            font-weight: 700;
            color: #333;
            margin-bottom: 0.5rem;
        }

        .status-badge {
            display: inline-block;
            padding: 0.3rem 1rem;
            border-radius: 15px;
            font-weight: 600;
            font-size: 0.9rem;
        }

        .status-disponible {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .status-occupe {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        .status-rdv {
            background: #fff3cd;
            color: #856404;
            border: 1px solid #ffeaa7;
        }

        .rdv-info {
            background: #ffe6e6;
            padding: 1rem;
            border-radius: 8px;
            margin-top: 1rem;
            border-left: 4px solid #dc3545;
        }

        .rdv-info h4 {
            color: #dc3545;
            margin-bottom: 0.5rem;
        }

        .rdv-info p {
            color: #333;
            font-size: 0.9rem;
        }

        .disponibilite-actions {
            display: flex;
            gap: 1rem;
            flex-wrap: wrap;
        }

        .btn {
            padding: 0.6rem 1.2rem;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-block;
            font-size: 0.9rem;
        }

        .btn-edit {
            background: #17a2b8;
            color: white;
        }

        .btn-delete {
            background: #dc3545;
            color: white;
        }

        .btn-duplicate {
            background: #6f42c1;
            color: white;
        }

        .btn-toggle {
            background: #ffc107;
            color: #333;
        }

        .btn-toggle.occupied {
            background: #28a745;
            color: white;
        }

        .btn:hover {
            transform: translateY(-2px);
            opacity: 0.9;
        }

        .modal {
            display: none;
            position: fixed;
            z-index: 2000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.5);
            backdrop-filter: blur(5px);
        }

        .modal-content {
            background-color: white;
            margin: 5% auto;
            padding: 2rem;
            border-radius: 15px;
            width: 90%;
            max-width: 600px;
            max-height: 80vh;
            overflow-y: auto;
        }

        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
            padding-bottom: 1rem;
            border-bottom: 2px solid #f1f3f4;
        }

        .modal-header h2 {
            color: #333;
            font-size: 1.5rem;
        }

        .close {
            color: #aaa;
            font-size: 28px;
            font-weight: bold;
            cursor: pointer;
            transition: color 0.3s ease;
        }

        .close:hover {
            color: #333;
        }

        .form-group {
            margin-bottom: 1.5rem;
        }

        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 600;
            color: #333;
        }

        .form-group input,
        .form-group select {
            width: 100%;
            padding: 0.8rem;
            border: 2px solid #e2e8f0;
            border-radius: 8px;
            font-size: 1rem;
            transition: border-color 0.3s ease;
        }

        .form-group input:focus,
        .form-group select:focus {
            outline: none;
            border-color: #667eea;
        }

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
        }

        .status-selector {
            display: flex;
            gap: 1rem;
            margin-top: 0.5rem;
        }

        .status-option {
            flex: 1;
            padding: 1rem;
            border: 2px solid #e2e8f0;
            border-radius: 8px;
            cursor: pointer;
            text-align: center;
            transition: all 0.3s ease;
        }

        .status-option.selected {
            border-color: #667eea;
            background: #f0f4ff;
        }

        .status-option.occupied.selected {
            border-color: #ff6b6b;
            background: #fff0f0;
        }

        .status-icon {
            font-size: 2rem;
            margin-bottom: 0.5rem;
        }

        .status-text {
            font-weight: 600;
            color: #333;
        }

        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 1rem 2rem;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
            font-size: 1rem;
            transition: all 0.3s ease;
            width: 100%;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
        }

        .alert {
            padding: 1rem 1.5rem;
            border-radius: 10px;
            margin-bottom: 2rem;
            display: none;
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

        .filter-bar {
            background: white;
            padding: 1.5rem;
            border-radius: 15px;
            margin-bottom: 2rem;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }

        .filter-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            align-items: end;
        }

        .empty-state {
            text-align: center;
            padding: 3rem;
            background: white;
            border-radius: 15px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
        }

        .empty-state-icon {
            font-size: 4rem;
            margin-bottom: 1rem;
            opacity: 0.3;
        }

        .empty-state h3 {
            color: #333;
            margin-bottom: 1rem;
        }

        .empty-state p {
            color: #666;
            margin-bottom: 2rem;
        }

        @media (max-width: 768px) {
            .container {
                padding: 0 1rem;
            }

            nav {
                flex-direction: column;
                height: auto;
                padding: 1rem 0;
            }

            .nav-links {
                flex-wrap: wrap;
                justify-content: center;
                gap: 1rem;
                margin-top: 1rem;
            }

            main {
                padding: 160px 0 2rem;
            }

            .user-info {
                flex-direction: column;
                text-align: center;
                gap: 1rem;
            }

            .action-bar {
                flex-direction: column;
                align-items: stretch;
            }

            .search-bar {
                max-width: none;
                order: 3;
            }

            .action-buttons {
                order: 1;
                justify-content: center;
            }

            .disponibilite-actions {
                justify-content: center;
            }

            .form-row {
                grid-template-columns: 1fr;
            }

            .status-selector {
                flex-direction: column;
            }
        }
    </style>
</head>
<body>
    <header>
        <nav class="container">
            <div class="logo">AMSOFT</div>
            <div class="nav-links">
                <a href="dashboard.php">🏠 Accueil</a>
                <a href="rdv.php">📅 Mes RDV</a>
                <a href="dispon.php" class="active">⏰ Mes Disponibilités</a>
                <a href="../logout.php">🚪 Déconnexion</a>
            </div>
        </nav>
    </header>

    <main class="container">
        <div class="page-header">
            <h1>⏰ Mes Disponibilités</h1>
            <p>Gérez vos créneaux horaires et votre planning</p>
        </div>

        <div class="user-info">
            <div class="user-details">
                <h3>👤 <?php echo htmlspecialchars($user_name); ?></h3>
                <p>Vos disponibilités et rendez-vous</p>
            </div>
        </div>

        <div id="alert-success" class="alert alert-success">
            <strong>Succès!</strong> <span id="success-message"></span>
        </div>

        <div id="alert-error" class="alert alert-error">
            <strong>Erreur!</strong> <span id="error-message"></span>
        </div>

        <div class="filter-bar">
            <div class="filter-row">
                <div class="form-group">
                    <label for="filter-date">Date</label>
                    <input type="date" id="filter-date" value="<?php echo $filter_date; ?>">
                </div>
                <div class="form-group">
                    <label for="filter-status">Statut</label>
                    <select id="filter-status">
                        <option value="">Tous les statuts</option>
                        <option value="disponible" <?php echo $filter_status === 'disponible' ? 'selected' : ''; ?>>Disponible</option>
                        <option value="occupe" <?php echo $filter_status === 'occupe' ? 'selected' : ''; ?>>Occupé</option>
                        <option value="rdv" <?php echo $filter_status === 'rdv' ? 'selected' : ''; ?>>Avec RDV</option>
                    </select>
                </div>
                <div class="form-group">
                    <button type="button" class="btn-primary" onclick="applyFilters()">🔍 Filtrer</button>
                </div>
            </div>
        </div>

        <div class="action-bar">
            <div class="search-bar">
                <span class="search-icon">🔍</span>
                <input type="text" placeholder="Rechercher par date..." id="search-input" value="<?php echo htmlspecialchars($search); ?>">
            </div>
            <div class="action-buttons">
                <button class="btn-add" onclick="openAddModal('available')">
                    ✅ Ajouter disponibilité
                </button>
                <button class="btn-add-busy" onclick="openAddModal('busy')">
                    ❌ Ajouter créneau occupé
                </button>
            </div>
        </div>

        <div class="disponibilites-grid">
            <?php if (empty($disponibilites)): ?>
                <div class="empty-state">
                    <div class="empty-state-icon">📅</div>
                    <h3>Aucune disponibilité trouvée</h3>
                    <p>Commencez par ajouter vos créneaux de disponibilité pour que les clients puissent prendre rendez-vous.</p>
                    <button class="btn-add" onclick="openAddModal('available')">
                        ✅ Ajouter ma première disponibilité
                    </button>
                </div>
            <?php else: ?>
                <?php foreach ($disponibilites as $dispo): ?>
                    <div class="disponibilite-card <?php echo !$dispo['is_actually_available'] ? 'occupied' : ''; ?>">
                        <div class="disponibilite-header">
                            <div class="personnel-info">
                                <div class="personnel-avatar <?php echo !$dispo['is_actually_available'] ? 'occupied' : ''; ?>">
                                    <?php echo strtoupper(substr($dispo['personnel_name'], 0, 1)); ?>
                                </div>
                                <div class="personnel-details">
                                    <h3><?php echo htmlspecialchars($dispo['personnel_name']); ?></h3>
                                    <p>Am Soft - Personnel </p>
                                </div>
                            </div>
                            <div class="disponibilite-date">
                                📅 <?php echo date('d/m/Y', strtotime($dispo['date'])); ?>
                            </div>
                        </div>

                        <div class="time-info">
                            <div class="time-range">
                                🕐 <?php echo date('H:i', strtotime($dispo['heure_debut'])); ?> - <?php echo date('H:i', strtotime($dispo['heure_fin'])); ?>
                            </div>
                            <span class="status-badge <?php 
                                if (!$dispo['is_actually_available']) {
                                    echo 'status-rdv';
                                } elseif ($dispo['is_available']) {
                                    echo 'status-disponible';
                                } else {
                                    echo 'status-occupe';
                                }
                            ?>">
                                <?php 
                                    if (!$dispo['is_actually_available']) {
                                        echo '📋 Avec RDV';
                                    } elseif ($dispo['is_available']) {
                                        echo '✅ Disponible';
                                    } else {
                                        echo '❌ Occupé';
                                    }
                                ?>
                            </span>
                        </div>

                        <?php 
                        // Vérifier s'il y a des RDV dans ce créneau
                        $has_rdv = false;
                        $rdv_details = [];
                        
                        if (isset($rdv_by_date_time[$dispo['date']])) {
                            foreach ($rdv_by_date_time[$dispo['date']] as $heure => $rdv) {
                                $heure_rdv = strtotime($heure);
                                $heure_debut = strtotime($dispo['heure_debut']);
                                $heure_fin = strtotime($dispo['heure_fin']);
                                
                                if ($heure_rdv >= $heure_debut && $heure_rdv <= $heure_fin) {
                                    $has_rdv = true;
                                    $rdv_details[] = $rdv;
                                }
                            }
                        }
                        
                        if ($has_rdv): ?>
                            <div class="rdv-info">
                                <h4>📋 Rendez-vous programmés :</h4>
                                <?php foreach ($rdv_details as $rdv): ?>
                                    <p>🕐 <?php echo $rdv['heure']; ?> - <?php echo htmlspecialchars($rdv['client_name']); ?> (<?php echo htmlspecialchars($rdv['service_name']); ?>)</p>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>

                        <div class="disponibilite-actions">
                            <button class="btn btn-edit" onclick="editDisponibilite(<?php echo htmlspecialchars(json_encode($dispo)); ?>)">
                                ✏️ Modifier
                            </button>
                            
                            <button class="btn btn-toggle <?php echo !$dispo['is_available'] ? 'occupied' : ''; ?>" 
                                    onclick="toggleStatus(<?php echo $dispo['id']; ?>, <?php echo $dispo['is_available']; ?>)">
                                <?php echo $dispo['is_available'] ? '❌ Marquer occupé' : '✅ Marquer disponible'; ?>
                            </button>
                            
                            <button class="btn btn-duplicate" onclick="duplicateDisponibilite(<?php echo $dispo['id']; ?>)">
                                📋 Dupliquer
                            </button>
                            
                            <button class="btn btn-delete" onclick="deleteDisponibilite(<?php echo $dispo['id']; ?>)">
                                🗑️ Supprimer
                            </button>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </main>

    <!-- Modal pour ajouter/modifier une disponibilité -->
    <div id="disponibiliteModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 id="modal-title">Ajouter une disponibilité</h2>
                <span class="close" onclick="closeModal()">&times;</span>
            </div>
            
            <form id="disponibiliteForm">
                <input type="hidden" id="disponibilite-id" name="id">
                <input type="hidden" id="form-action" name="action" value="add">
                
                <div class="form-group">
                    <label for="date">Date</label>
                    <input type="date" id="date" name="date" required>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="heure_debut">Heure de début</label>
                        <input type="time" id="heure_debut" name="heure_debut" required>
                    </div>
                    <div class="form-group">
                        <label for="heure_fin">Heure de fin</label>
                        <input type="time" id="heure_fin" name="heure_fin" required>
                    </div>
                </div>
                
                <div class="form-group">
                    <label>Statut</label>
                    <div class="status-selector">
                        <div class="status-option" data-value="1" onclick="selectStatus(1)">
                            <div class="status-icon">✅</div>
                            <div class="status-text">Disponible</div>
                        </div>
                        <div class="status-option occupied" data-value="0" onclick="selectStatus(0)">
                            <div class="status-icon">❌</div>
                            <div class="status-text">Occupé</div>
                        </div>
                    </div>
                    <input type="hidden" id="is_available" name="is_available" value="1">
                </div>
                
                <button type="submit" class="btn-primary">
                    💾 Enregistrer
                </button>
            </form>
        </div>
    </div>

    <script>
        // Variables globales
        let currentModal = null;

        // Fonction pour ouvrir le modal d'ajout
        function openAddModal(type = 'available') {
            document.getElementById('modal-title').textContent = 'Ajouter une disponibilité';
            document.getElementById('form-action').value = 'add';
            document.getElementById('disponibilite-id').value = '';
            document.getElementById('disponibiliteForm').reset();
            
            // Définir la date d'aujourd'hui par défaut
            const today = new Date().toISOString().split('T')[0];
            document.getElementById('date').value = today;
            
            // Définir le statut selon le type
            if (type === 'busy') {
                selectStatus(0);
                document.getElementById('modal-title').textContent = 'Ajouter un créneau occupé';
            } else {
                selectStatus(1);
            }
            
            document.getElementById('disponibiliteModal').style.display = 'block';
        }

        // Fonction pour modifier une disponibilité
        function editDisponibilite(dispo) {
            document.getElementById('modal-title').textContent = 'Modifier la disponibilité';
            document.getElementById('form-action').value = 'update';
            document.getElementById('disponibilite-id').value = dispo.id;
            document.getElementById('date').value = dispo.date;
            document.getElementById('heure_debut').value = dispo.heure_debut;
            document.getElementById('heure_fin').value = dispo.heure_fin;
            
            selectStatus(parseInt(dispo.is_available));
            
            document.getElementById('disponibiliteModal').style.display = 'block';
        }

        // Fonction pour sélectionner le statut
        function selectStatus(value) {
            document.querySelectorAll('.status-option').forEach(option => {
                option.classList.remove('selected');
            });
            
            document.querySelector(`[data-value="${value}"]`).classList.add('selected');
            document.getElementById('is_available').value = value;
        }

        // Fonction pour fermer le modal
        function closeModal() {
            document.getElementById('disponibiliteModal').style.display = 'none';
        }

        // Fonction pour basculer le statut
        function toggleStatus(id, currentStatus) {
            if (confirm('Êtes-vous sûr de vouloir changer le statut de cette disponibilité ?')) {
                const formData = new FormData();
                formData.append('action', 'toggle_status');
                formData.append('id', id);
                
                fetch('', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        showAlert('success', data.message);
                        setTimeout(() => location.reload(), 1500);
                    } else {
                        showAlert('error', data.message);
                    }
                })
                .catch(error => {
                    showAlert('error', 'Erreur de connexion');
                });
            }
        }

        // Fonction pour dupliquer une disponibilité
        function duplicateDisponibilite(id) {
            if (confirm('Voulez-vous dupliquer cette disponibilité pour le jour suivant ?')) {
                const formData = new FormData();
                formData.append('action', 'duplicate');
                formData.append('id', id);
                
                fetch('', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        showAlert('success', data.message);
                        setTimeout(() => location.reload(), 1500);
                    } else {
                        showAlert('error', data.message);
                    }
                })
                .catch(error => {
                    showAlert('error', 'Erreur de connexion');
                });
            }
        }

        // Fonction pour supprimer une disponibilité
        function deleteDisponibilite(id) {
            if (confirm('Êtes-vous sûr de vouloir supprimer cette disponibilité ?')) {
                const formData = new FormData();
                formData.append('action', 'delete');
                formData.append('id', id);
                
                fetch('', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        showAlert('success', data.message);
                        setTimeout(() => location.reload(), 1500);
                    } else {
                        showAlert('error', data.message);
                    }
                })
                .catch(error => {
                    showAlert('error', 'Erreur de connexion');
                });
            }
        }

        // Fonction pour afficher les alertes
        function showAlert(type, message) {
            const alertElement = document.getElementById(`alert-${type}`);
            const messageElement = document.getElementById(`${type}-message`);
            
            messageElement.textContent = message;
            alertElement.style.display = 'block';
            
            setTimeout(() => {
                alertElement.style.display = 'none';
            }, 5000);
        }

        // Fonction pour appliquer les filtres
        function applyFilters() {
            const filterDate = document.getElementById('filter-date').value;
            const filterStatus = document.getElementById('filter-status').value;
            const search = document.getElementById('search-input').value;
            
            const params = new URLSearchParams();
            if (filterDate) params.append('filter_date', filterDate);
            if (filterStatus) params.append('filter_status', filterStatus);
            if (search) params.append('search', search);
            
            window.location.href = '?' + params.toString();
        }

        // Gestion du formulaire
        document.getElementById('disponibiliteForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            
            fetch('', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showAlert('success', data.message);
                    closeModal();
                    setTimeout(() => location.reload(), 1500);
                } else {
                    showAlert('error', data.message);
                }
            })
            .catch(error => {
                showAlert('error', 'Erreur de connexion');
            });
        });

        // Fermer le modal en cliquant à l'extérieur
        window.onclick = function(event) {
            const modal = document.getElementById('disponibiliteModal');
            if (event.target === modal) {
                closeModal();
            }
        }

        // Recherche en temps réel
        document.getElementById('search-input').addEventListener('keyup', function(e) {
            if (e.key === 'Enter') {
                applyFilters();
            }
        });

        // Validation des heures
        document.getElementById('heure_debut').addEventListener('change', function() {
            const heureDebut = this.value;
            const heureFin = document.getElementById('heure_fin').value;
            
            if (heureFin && heureDebut >= heureFin) {
                alert('L\'heure de début doit être antérieure à l\'heure de fin');
                this.value = '';
            }
        });

        document.getElementById('heure_fin').addEventListener('change', function() {
            const heureDebut = document.getElementById('heure_debut').value;
            const heureFin = this.value;
            
            if (heureDebut && heureFin <= heureDebut) {
                alert('L\'heure de fin doit être postérieure à l\'heure de début');
                this.value = '';
            }
        });
    </script>
</body>
</html>