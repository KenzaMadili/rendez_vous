<?php
session_start();
if (!isset($_SESSION['name']) || $_SESSION['role'] !== 'personnel') {
    header('Location: ../login.php');
    exit();
}
require_once '../includes/database.php';

$personnel_id = $_SESSION['user_id'];

// Fonction pour marquer un créneau comme occupé dans les disponibilités
function markSlotAsUnavailable($pdo, $personnel_id, $date, $heure, $duration) {
    try {
        $heure_fin = date('H:i:s', strtotime($heure) + ($duration * 60));
        
        // Récupérer toutes les disponibilités qui chevauchent avec le créneau du RDV
        $stmt = $pdo->prepare("
            SELECT id, heure_debut, heure_fin 
            FROM disponibilites 
            WHERE personnel_id = ? 
            AND date = ? 
            AND is_available = 1
            AND (
                (TIME(heure_debut) < TIME(?) AND TIME(heure_fin) > TIME(?))
                OR (TIME(heure_debut) < TIME(?) AND TIME(heure_fin) > TIME(?))
                OR (TIME(heure_debut) >= TIME(?) AND TIME(heure_fin) <= TIME(?))
            )
        ");
        $stmt->execute([
            $personnel_id, 
            $date, 
            $heure, $heure,
            $heure_fin, $heure_fin,
            $heure, $heure_fin
        ]);
        $disponibilites_chevauchantes = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($disponibilites_chevauchantes as $dispo) {
            $debut_dispo = $dispo['heure_debut'];
            $fin_dispo = $dispo['heure_fin'];
            
            // Supprimer la disponibilité actuelle
            $stmt = $pdo->prepare("DELETE FROM disponibilites WHERE id = ?");
            $stmt->execute([$dispo['id']]);
            
            // Créer les nouvelles disponibilités avant et après le RDV si nécessaire
            // Avant le RDV
            if (strtotime($debut_dispo) < strtotime($heure)) {
                $stmt = $pdo->prepare("
                    INSERT INTO disponibilites (personnel_id, date, heure_debut, heure_fin, is_available, created_at) 
                    VALUES (?, ?, ?, ?, 1, NOW())
                ");
                $stmt->execute([$personnel_id, $date, $debut_dispo, $heure]);
            }
            
            // Après le RDV
            if (strtotime($fin_dispo) > strtotime($heure_fin)) {
                $stmt = $pdo->prepare("
                    INSERT INTO disponibilites (personnel_id, date, heure_debut, heure_fin, is_available, created_at) 
                    VALUES (?, ?, ?, ?, 1, NOW())
                ");
                $stmt->execute([$personnel_id, $date, $heure_fin, $fin_dispo]);
            }
        }
        
        // Créer une disponibilité "occupée" pour le créneau du RDV
        $stmt = $pdo->prepare("
            INSERT INTO disponibilites (personnel_id, date, heure_debut, heure_fin, is_available, created_at) 
            VALUES (?, ?, ?, ?, 0, NOW())
        ");
        $stmt->execute([$personnel_id, $date, $heure, $heure_fin]);
        
        return true;
    } catch (PDOException $e) {
        error_log("Erreur lors de la mise à jour des disponibilités : " . $e->getMessage());
        return false;
    }
}

// Fonction pour libérer un créneau dans les disponibilités
function markSlotAsAvailable($pdo, $personnel_id, $date, $heure, $duration) {
    try {
        $heure_fin = date('H:i:s', strtotime($heure) + ($duration * 60));
        
        // Supprimer la disponibilité "occupée" pour ce créneau
        $stmt = $pdo->prepare("
            DELETE FROM disponibilites 
            WHERE personnel_id = ? 
            AND date = ? 
            AND TIME(heure_debut) = TIME(?) 
            AND TIME(heure_fin) = TIME(?) 
            AND is_available = 0
        ");
        $stmt->execute([$personnel_id, $date, $heure, $heure_fin]);
        
        // Recréer une disponibilité libre pour ce créneau
        $stmt = $pdo->prepare("
            INSERT INTO disponibilites (personnel_id, date, heure_debut, heure_fin, is_available, created_at) 
            VALUES (?, ?, ?, ?, 1, NOW())
        ");
        $stmt->execute([$personnel_id, $date, $heure, $heure_fin]);
        
        // Fusionner avec les disponibilités adjacentes si possible
        fusionnerDisponibilites($pdo, $personnel_id, $date);
        
        return true;
    } catch (PDOException $e) {
        error_log("Erreur lors de la libération du créneau : " . $e->getMessage());
        return false;
    }
}

// Fonction pour fusionner les disponibilités adjacentes
function fusionnerDisponibilites($pdo, $personnel_id, $date) {
    try {
        // Récupérer toutes les disponibilités libres pour ce personnel à cette date
        $stmt = $pdo->prepare("
            SELECT id, heure_debut, heure_fin 
            FROM disponibilites 
            WHERE personnel_id = ? AND date = ? AND is_available = 1
            ORDER BY heure_debut
        ");
        $stmt->execute([$personnel_id, $date]);
        $disponibilites = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $to_delete = [];
        $to_insert = [];
        
        for ($i = 0; $i < count($disponibilites) - 1; $i++) {
            $current = $disponibilites[$i];
            $next = $disponibilites[$i + 1];
            
            // Si la fin de la disponibilité actuelle = début de la suivante
            if ($current['heure_fin'] === $next['heure_debut']) {
                // Marquer pour suppression
                $to_delete[] = $current['id'];
                $to_delete[] = $next['id'];
                
                // Créer une nouvelle disponibilité fusionnée
                $to_insert[] = [
                    'debut' => $current['heure_debut'],
                    'fin' => $next['heure_fin']
                ];
                
                // Sauter la prochaine itération car on a déjà traité next
                $i++;
            }
        }
        
        // Supprimer les anciennes disponibilités
        if (!empty($to_delete)) {
            $placeholders = str_repeat('?,', count($to_delete) - 1) . '?';
            $stmt = $pdo->prepare("DELETE FROM disponibilites WHERE id IN ($placeholders)");
            $stmt->execute($to_delete);
        }
        
        // Insérer les nouvelles disponibilités fusionnées
        foreach ($to_insert as $slot) {
            $stmt = $pdo->prepare("
                INSERT INTO disponibilites (personnel_id, date, heure_debut, heure_fin, is_available, created_at) 
                VALUES (?, ?, ?, ?, 1, NOW())
            ");
            $stmt->execute([$personnel_id, $date, $slot['debut'], $slot['fin']]);
        }
        
    } catch (PDOException $e) {
        error_log("Erreur lors de la fusion des disponibilités : " . $e->getMessage());
    }
}

if ($_POST) {
    $action = $_POST['action'] ?? '';
    $rdv_id = $_POST['rdv_id'] ?? '';

    if ($action && $rdv_id) {
        try {
            // Récupérer les informations du RDV pour gérer les disponibilités
            $stmt = $pdo->prepare("
                SELECT r.*, COALESCE(r.duration, s.duration) as duration_effective
                FROM rendezvous r
                LEFT JOIN services s ON r.service_id = s.id
                WHERE r.id = ? AND r.personnel_id = ?
            ");
            $stmt->execute([$rdv_id, $personnel_id]);
            $rdv_info = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$rdv_info) {
                throw new Exception("Rendez-vous non trouvé");
            }
            
            if ($action === 'confirmer') {
                // Confirmer le RDV
                $stmt = $pdo->prepare("UPDATE rendezvous SET status = 'confirmed' WHERE id = ? AND personnel_id = ?");
                $stmt->execute([$rdv_id, $personnel_id]);
                
                // Marquer le créneau comme occupé dans les disponibilités
                if (markSlotAsUnavailable($pdo, $personnel_id, $rdv_info['date'], $rdv_info['heure'], $rdv_info['duration_effective'])) {
                    $message = "Rendez-vous confirmé avec succès et créneau marqué comme occupé";
                } else {
                    $message = "Rendez-vous confirmé mais erreur lors de la mise à jour des disponibilités";
                }
                
            } elseif ($action === 'annuler') {
                // Annuler le RDV
                $stmt = $pdo->prepare("UPDATE rendezvous SET status = 'cancelled' WHERE id = ? AND personnel_id = ?");
                $stmt->execute([$rdv_id, $personnel_id]);
                
                // Si le RDV était confirmé, libérer le créneau
                if ($rdv_info['status'] === 'confirmed') {
                    markSlotAsAvailable($pdo, $personnel_id, $rdv_info['date'], $rdv_info['heure'], $rdv_info['duration_effective']);
                }
                
                $message = "Rendez-vous annulé avec succès";
                
            } elseif ($action === 'terminer') {
                // Terminer le RDV
                $stmt = $pdo->prepare("UPDATE rendezvous SET status = 'completed' WHERE id = ? AND personnel_id = ?");
                $stmt->execute([$rdv_id, $personnel_id]);
                
                // Libérer le créneau (le RDV est terminé)
                if ($rdv_info['status'] === 'confirmed') {
                    markSlotAsAvailable($pdo, $personnel_id, $rdv_info['date'], $rdv_info['heure'], $rdv_info['duration_effective']);
                }
                
                $message = "Rendez-vous marqué comme terminé";
                
            } elseif ($action === 'reporter' && isset($_POST['nouvelle_date'], $_POST['nouvelle_heure'])) {
                $nouvelle_date = $_POST['nouvelle_date'];
                $nouvelle_heure = $_POST['nouvelle_heure'];
                
                // Si le RDV était confirmé, libérer l'ancien créneau
                if ($rdv_info['status'] === 'confirmed') {
                    markSlotAsAvailable($pdo, $personnel_id, $rdv_info['date'], $rdv_info['heure'], $rdv_info['duration_effective']);
                }
                
                // Mettre à jour le RDV
                $stmt = $pdo->prepare("UPDATE rendezvous SET date = ?, heure = ?, status = 'pending', updated_at = NOW() WHERE id = ? AND personnel_id = ?");
                $stmt->execute([$nouvelle_date, $nouvelle_heure, $rdv_id, $personnel_id]);
                
                $message = "Rendez-vous reporté avec succès";
            }
            
        } catch (PDOException $e) {
            $error = "Erreur lors de la mise à jour : " . $e->getMessage();
        } catch (Exception $e) {
            $error = $e->getMessage();
        }
    }
}

try {
    // Récupérer les rendez-vous avec les informations client et service
    $stmt = $pdo->prepare("
        SELECT r.*, 
               u.name AS client_nom, 
               u.email AS client_email,
               s.name AS service_nom,
               COALESCE(r.duration, s.duration) as duration_effective
        FROM rendezvous r
        JOIN users u ON r.client_id = u.id
        LEFT JOIN services s ON r.service_id = s.id
        WHERE r.personnel_id = ?
        ORDER BY r.date DESC, r.heure DESC
    ");
    $stmt->execute([$personnel_id]);
    $rendez_vous = $stmt->fetchAll();
} catch (PDOException $e) {
    $rendez_vous = [];
    $error = "Erreur lors de la récupération des rendez-vous : " . $e->getMessage();
}

// Filtrer les rendez-vous par statut
$rdv_en_attente = array_filter($rendez_vous, fn($rdv) => $rdv['status'] === 'pending');
$rdv_confirmes = array_filter($rendez_vous, fn($rdv) => $rdv['status'] === 'confirmed');
$rdv_passes = array_filter($rendez_vous, fn($rdv) => in_array($rdv['status'], ['completed', 'cancelled']));

// Fonction pour obtenir le statut en français
function getStatusText($status) {
    switch($status) {
        case 'pending': return 'En attente';
        case 'confirmed': return 'Confirmé';
        case 'completed': return 'Terminé';
        case 'cancelled': return 'Annulé';
        default: return ucfirst($status);
    }
}

// Fonction pour obtenir la classe CSS selon le statut
function getStatusClass($status) {
    switch($status) {
        case 'pending': return 'bg-yellow-100 text-yellow-800';
        case 'confirmed': return 'bg-green-100 text-green-800';
        case 'completed': return 'bg-blue-100 text-blue-800';
        case 'cancelled': return 'bg-red-100 text-red-800';
        default: return 'bg-gray-100 text-gray-800';
    }
}
?>


<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mes Rendez-vous - Espace Personnel</title>
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
            overflow: hidden;
        }

        .page-header::before {
            content: '';
            position: absolute;
            top: -50%;
            right: -50%;
            width: 100%;
            height: 100%;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 50%;
            animation: float 6s ease-in-out infinite;
        }

        @keyframes float {
            0%, 100% { transform: translateY(0px) rotate(0deg); }
            50% { transform: translateY(-20px) rotate(180deg); }
        }

        .page-header h1 {
            font-size: 2.5rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
            position: relative;
            z-index: 1;
        }

        .page-header p {
            position: relative;
            z-index: 1;
            opacity: 0.9;
        }

        .tabs {
            display: flex;
            background: white;
            border-radius: 15px;
            padding: 0.5rem;
            margin-bottom: 2rem;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
            position: relative;
        }

        .tab {
            flex: 1;
            padding: 1rem 2rem;
            text-align: center;
            border-radius: 10px;
            cursor: pointer;
            transition: all 0.3s ease;
            font-weight: 600;
            position: relative;
            z-index: 1;
        }

        .tab.active {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            transform: scale(1.02);
        }

        .tab-content {
            display: none;
            animation: fadeIn 0.5s ease-in;
        }

        .tab-content.active {
            display: block;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .rdv-grid {
            display: grid;
            gap: 1.5rem;
        }

        .rdv-card {
            background: white;
            border-radius: 15px;
            padding: 2rem;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
            transition: all 0.3s ease;
            border-left: 4px solid #667eea;
            position: relative;
            overflow: hidden;
        }

        .rdv-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 2px;
            background: linear-gradient(90deg, #667eea, #764ba2);
            transform: scaleX(0);
            transition: transform 0.3s ease;
        }

        .rdv-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.15);
        }

        .rdv-card:hover::before {
            transform: scaleX(1);
        }

        .rdv-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
        }

        .rdv-date ,.rdv-duree {
            font-size: 1.2rem;
            font-weight: 700;
            color: #333;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .rdv-status {
            padding: 0.5rem 1rem;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .status-pending {
            background: linear-gradient(135deg, #ffeaa7, #fdcb6e);
            color: #2d3436;
        }

        .status-confirmed {
            background: linear-gradient(135deg, #55efc4, #00b894);
            color: white;
        }

        .status-completed {
            background: linear-gradient(135deg, #81ecec, #00cec9);
            color: white;
        }

        .status-cancelled {
            background: linear-gradient(135deg, #fd79a8, #e84393);
            color: white;
        }

        .rdv-info {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-bottom: 1.5rem;
        }

        .info-item {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.5rem;
            background: #f8fafc;
            border-radius: 8px;
            transition: all 0.3s ease;
        }

        .info-item:hover {
            background: #e2e8f0;
            transform: translateX(5px);
        }

        .info-icon {
            font-size: 1.2rem;
            color: #667eea;
        }

        .rdv-actions {
            display: flex;
            gap: 1rem;
            flex-wrap: wrap;
        }

        .btn {
            padding: 0.8rem 1.5rem;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-block;
            position: relative;
            overflow: hidden;
        }

        .btn::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: rgba(255, 255, 255, 0.2);
            transition: left 0.3s ease;
        }

        .btn:hover::before {
            left: 100%;
        }

        .btn-confirm {
            background: linear-gradient(135deg, #55efc4, #00b894);
            color: white;
        }

        .btn-cancel {
            background: linear-gradient(135deg, #fd79a8, #e84393);
            color: white;
        }

        .btn-reschedule {
            background: linear-gradient(135deg, #ffeaa7, #fdcb6e);
            color: #2d3436;
        }

        .btn-complete {
            background: linear-gradient(135deg, #81ecec, #00cec9);
            color: white;
        }

        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.2);
        }

        .alert {
            padding: 1rem 1.5rem;
            border-radius: 10px;
            margin-bottom: 2rem;
            border-left: 4px solid;
            animation: slideIn 0.5s ease-out;
        }

        @keyframes slideIn {
            from { transform: translateX(-100%); opacity: 0; }
            to { transform: translateX(0); opacity: 1; }
        }

        .alert-success {
            background: linear-gradient(135deg, #d4edda, #c3e6cb);
            color: #155724;
            border-left-color: #28a745;
        }

        .alert-error {
            background: linear-gradient(135deg, #f8d7da, #f5c6cb);
            color: #721c24;
            border-left-color: #dc3545;
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
            margin: 10% auto;
            padding: 2rem;
            border-radius: 15px;
            width: 90%;
            max-width: 500px;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.3);
            animation: modalSlideIn 0.3s ease-out;
        }

        @keyframes modalSlideIn {
            from { transform: translateY(-50px); opacity: 0; }
            to { transform: translateY(0); opacity: 1; }
        }

        .close {
            color: #aaa;
            float: right;
            font-size: 28px;
            font-weight: bold;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .close:hover {
            color: #667eea;
            transform: rotate(90deg);
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

        .form-group input {
            width: 100%;
            padding: 1rem;
            border: 2px solid #e2e8f0;
            border-radius: 8px;
            font-size: 1rem;
            transition: all 0.3s ease;
        }

        .form-group input:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
            transform: translateY(-2px);
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

            .tabs {
                flex-direction: column;
            }

            .rdv-info {
                grid-template-columns: 1fr;
            }

            .rdv-actions {
                justify-content: center;
            }

            .page-header h1 {
                font-size: 2rem;
            }
        }

        .empty-state {
            text-align: center;
            color: #666;
            padding: 4rem 2rem;
            background: white;
            border-radius: 15px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
        }

        .empty-state-icon {
            font-size: 4rem;
            margin-bottom: 1rem;
            opacity: 0.5;
        }
    </style>
</head>
<body>
    <header>
        <div class="container">
            <nav>
                <a href="dashboard.php">🏠 Accueil</a>
                <a href="rdv.php" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white;">📅 Mes rendez-vous</a>
                <a href="dispo.php">⏰ Mes disponibilités</a>
                <a href="profil.php">👤 Profil</a>
                <a href="contact.php">📧 Contact</a>
                <a href="../logout.php">🚪 Déconnecter</a>
            </nav>
        </div>
    </header>

    <main>
        <div class="container">
            <div class="page-header">
                <h1>📅 Mes Rendez-vous</h1>
                <p>Gérez efficacement vos rendez-vous clients</p>
            </div>

            <?php if (isset($message)): ?>
                <div class="alert alert-success">
                    ✅ <?php echo htmlspecialchars($message); ?>
                </div>
            <?php endif; ?>

            <?php if (isset($error)): ?>
                <div class="alert alert-error">
                    ❌ <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>

            <div class="tabs">
                <div class="tab active" onclick="showTab('pending')">
                    ⏳ En attente (<?php echo count($rdv_en_attente); ?>)
                </div>
                <div class="tab" onclick="showTab('confirmed')">
                    ✅ Confirmés (<?php echo count($rdv_confirmes); ?>)
                </div>
                <div class="tab" onclick="showTab('completed')">
                    📋 Historique (<?php echo count($rdv_passes); ?>)
                </div>
            </div>

            <!-- Rendez-vous en attente -->
            <div id="pending" class="tab-content active">
                <div class="rdv-grid">
                    <?php if (empty($rdv_en_attente)): ?>
                        <div class="empty-state">
                            <div class="empty-state-icon">⏳</div>
                            <h3>Aucun rendez-vous en attente</h3>
                            <p>Tous vos rendez-vous sont confirmés ou traités.</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($rdv_en_attente as $rdv): ?>
                            <div class="rdv-card">
                                <div class="rdv-header">
                                   
                                    <div class="rdv-status status-<?php echo $rdv['status']; ?>">
                                        <?php echo getStatusText($rdv['status']); ?>
                                    </div>
                                    <div class="rdv-date">
                                        📅 <?php echo date('d/m/Y', strtotime($rdv['date'])); ?> à <?php echo date('H:i', strtotime($rdv['heure'])); ?>
                                    </div>
                                    <div class="rdv-duree">
                                         <?php echo "Durée : ".$rdv['duration']." minutes"; ?>
                                    </div>
                                </div>
                                
                                <div class="rdv-info">
                                    <div class="info-item">
                                        <span class="info-icon">👤</span>
                                        <span><?php echo htmlspecialchars($rdv['client_nom']); ?></span>
                                    </div>
                                    <div class="info-item">
                                        <span class="info-icon">✉️</span>
                                        <span><?php echo htmlspecialchars($rdv['client_email']); ?></span>
                                    </div>
                                    
                                    <div class="info-item">
                                        <span class="info-icon">💵</span>
                                        <span><?php echo htmlspecialchars($rdv['prix']) ," DH"; ?></span>
                                    </div>
                                    <?php if (!empty($rdv['notes'])): ?>
                                    <div class="info-item">
                                        <span class="info-icon">📝</span>
                                        <span><?php echo htmlspecialchars($rdv['notes']); ?></span>
                                    </div>
                                    <?php endif; ?>
                                </div>

                                <div class="rdv-actions">
                                    <form method="POST" style="display: inline;">
                                        <input type="hidden" name="action" value="confirmer">
                                        <input type="hidden" name="rdv_id" value="<?php echo $rdv['id']; ?>">
                                        <button type="submit" class="btn btn-confirm">✅ Confirmer</button>
                                    </form>
                                    
                                    <button class="btn btn-reschedule" onclick="openRescheduleModal(<?php echo $rdv['id']; ?>)">
                                        📅 Reporter
                                    </button>
                                    
                                    <form method="POST" style="display: inline;">
                                        <input type="hidden" name="action" value="annuler">
                                        <input type="hidden" name="rdv_id" value="<?php echo $rdv['id']; ?>">
                                        <button type="submit" class="btn btn-cancel" onclick="return confirm('Êtes-vous sûr de vouloir annuler ce rendez-vous ?')">
                                            ❌ Annuler
                                        </button>
                                    </form>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Rendez-vous confirmés -->
            <div id="confirmed" class="tab-content">
                <div class="rdv-grid">
                    <?php if (empty($rdv_confirmes)): ?>
                        <div class="empty-state">
                            <div class="empty-state-icon">✅</div>
                            <h3>Aucun rendez-vous confirmé</h3>
                            <p>Les rendez-vous confirmés apparaîtront ici.</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($rdv_confirmes as $rdv): ?>
                            <div class="rdv-card">
                                <div class="rdv-header">
                                    <div class="rdv-date">
                                        📅 <?php echo date('d/m/Y', strtotime($rdv['date'])); ?> à <?php echo date('H:i', strtotime($rdv['heure'])); ?>
                                    </div>
                                    <div class="rdv-duree">
                                         <?php echo "Durée : ".$rdv['duration']." minutes"; ?>
                                    </div>
                                    <div class="rdv-status status-<?php echo $rdv['status']; ?>">
                                        <?php echo getStatusText($rdv['status']); ?>
                                    </div>
                                </div>
                                
                                <div class="rdv-info">
                                    <div class="info-item">
                                        <span class="info-icon">👤</span>
                                        <span><?php echo htmlspecialchars($rdv['client_nom']); ?></span>
                                    </div>
                                    <div class="info-item">
                                        <span class="dollar-icon">💵</span>
                                        <span><?php echo htmlspecialchars($rdv['prix']) ," DH"; ?></span>
                                    </div>
                                    <div class="info-item">
                                        <span class="info-icon">✉️</span>
                                        <span><?php echo htmlspecialchars($rdv['client_email']); ?></span>
                                    </div>
                                    <?php if (!empty($rdv['notes'])): ?>
                                    <div class="info-item">
                                        <span class="info-icon">📝</span>
                                        <span><?php echo htmlspecialchars($rdv['notes']); ?></span>
                                    </div>
                                    <?php endif; ?>
                                </div>

                                <div class="rdv-actions">
                                    <form method="POST" style="display: inline;">
                                        <input type="hidden" name="action" value="terminer">
                                        <input type="hidden" name="rdv_id" value="<?php echo $rdv['id']; ?>">
                                        <button type="submit" class="btn btn-complete">✅ Terminer</button>
                                    </form>
                                    
                                    <button class="btn btn-reschedule" onclick="openRescheduleModal(<?php echo $rdv['id']; ?>)">
                                        📅 Reporter
                                    </button>
                                    
                                    <form method="POST" style="display: inline;">
                                        <input type="hidden" name="action" value="annuler">
                                        <input type="hidden" name="rdv_id" value="<?php echo $rdv['id']; ?>">
                                        <button type="submit" class="btn btn-cancel" onclick="return confirm('Êtes-vous sûr de vouloir annuler ce rendez-vous ?')">
                                            ❌ Annuler
                                        </button>
                                    </form>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Rendez-vous passés -->
            <div id="completed" class="tab-content">
                <div class="rdv-grid">
                    <?php if (empty($rdv_passes)): ?>
                        <div class="empty-state">
                            <div class="empty-state-icon">📋</div>
                            <h3>Aucun historique</h3>
                            <p>L'historique de vos rendez-vous apparaîtra ici.</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($rdv_passes as $rdv): ?>
                            <div class="rdv-card">
                                <div class="rdv-header">
                                    <div class="rdv-date">
                                        📅 <?php echo date('d/m/Y', strtotime($rdv['date'])); ?> à <?php echo date('H:i', strtotime($rdv['heure'])); ?>
                                    </div>
                                     <div class="rdv-duree">
                                         <?php echo "Durée : ".$rdv['duration']." minutes"; ?>
                                    </div>
                                    <div class="rdv-status status-<?php echo $rdv['status']; ?>">
                                        <?php echo getStatusText($rdv['status']); ?>
                                    </div>
                                </div>
                                
                                <div class="rdv-info">
                                    <div class="info-item">
                                        <span class="info-icon">👤</span>
                                        <span><?php echo htmlspecialchars($rdv['client_nom']); ?></span>
                                    </div>
                                    <div class="info-item">
                                        <span class="info-icon">✉️</span>
                                        <span><?php echo htmlspecialchars($rdv['client_email']); ?></span>
                                    </div>
                                    <div class="info-item">
                                        <span class="money-icon">💵</span>
                                        <span><?php echo htmlspecialchars($rdv['prix']) ," DH"; ?></span>
                                    </div>
                                    <?php if (!empty($rdv['notes'])): ?>
                                    <div class="info-item">
                                        <span class="info-icon">📝</span>
                                        <span><?php echo htmlspecialchars($rdv['notes']); ?></span>
                                    </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </main>

    <!-- Modal pour reporter un rendez-vous -->
    <div id="rescheduleModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeRescheduleModal()">&times;</span>
            <h2>📅 Reporter le rendez-vous</h2>
            <form method="POST">
                <input type="hidden" name="action" value="reporter">
                <input type="hidden" name="rdv_id" id="reschedule_rdv_id">
                
                <div class="form-group">
                    <label for="nouvelle_date">📅 Nouvelle date :</label>
                    <input type="date" name="nouvelle_date" id="nouvelle_date" required>
                </div>
                
                <div class="form-group">
                    <label for="nouvelle_heure">⏰ Nouvelle heure :</label>
                    <input type="time" name="nouvelle_heure" id="nouvelle_heure" required>
                </div>
                
                <div style="text-align: center; margin-top: 2rem;">
                    <button type="submit" class="btn btn-confirm">✅ Reporter</button>
                    <button type="button" class="btn btn-cancel" onclick="closeRescheduleModal()">❌ Annuler</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function showTab(tabName) {
            // Cacher tous les contenus d'onglets
            const tabContents = document.querySelectorAll('.tab-content');
            tabContents.forEach(content => content.classList.remove('active'));
            
            // Désactiver tous les onglets
            const tabs = document.querySelectorAll('.tab');
            tabs.forEach(tab => tab.classList.remove('active'));
            
            // Afficher le contenu de l'onglet sélectionné
            document.getElementById(tabName).classList.add('active');

            // Activer l'onglet sélectionné
        event.target.classList.add('active');
    }

    function openRescheduleModal(rdvId) {
        document.getElementById('reschedule_rdv_id').value = rdvId;
        document.getElementById('rescheduleModal').style.display = 'block';
        
        // Définir la date minimale à aujourd'hui
        const today = new Date().toISOString().split('T')[0];
        document.getElementById('nouvelle_date').min = today;
        
        // Animation d'ouverture
        setTimeout(() => {
            document.querySelector('.modal-content').style.animation = 'modalSlideIn 0.3s ease-out';
        }, 10);
    }

    function closeRescheduleModal() {
        const modal = document.getElementById('rescheduleModal');
        const modalContent = document.querySelector('.modal-content');
        
        // Animation de fermeture
        modalContent.style.animation = 'modalSlideOut 0.3s ease-out';
        
        setTimeout(() => {
            modal.style.display = 'none';
            // Réinitialiser le formulaire
            document.getElementById('nouvelle_date').value = '';
            document.getElementById('nouvelle_heure').value = '';
        }, 300);
    }

    // Fermer la modal en cliquant à l'extérieur
    window.onclick = function(event) {
        const modal = document.getElementById('rescheduleModal');
        if (event.target === modal) {
            closeRescheduleModal();
        }
    }

    // Gestion des touches clavier
    document.addEventListener('keydown', function(event) {
        if (event.key === 'Escape') {
            closeRescheduleModal();
        }
    });

    // Animation pour les cartes de rendez-vous au chargement
    document.addEventListener('DOMContentLoaded', function() {
        const cards = document.querySelectorAll('.rdv-card');
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

    // Confirmation améliorée pour les actions importantes
    function confirmAction(action, rdvId) {
        let message = '';
        let confirmText = '';
        
        switch(action) {
            case 'annuler':
                message = '⚠️ Êtes-vous sûr de vouloir annuler ce rendez-vous ?';
                confirmText = 'Cette action est irréversible.';
                break;
            case 'terminer':
                message = '✅ Marquer ce rendez-vous comme terminé ?';
                confirmText = 'Le rendez-vous sera déplacé vers l\'historique.';
                break;
        }
        
        return confirm(message + '\n' + confirmText);
    }

    // Auto-refresh pour maintenir les données à jour
    setInterval(function() {
        // Vérifier s'il y a de nouveaux rendez-vous toutes les 5 minutes
        // En production, vous pourriez utiliser AJAX pour récupérer les mises à jour
        const lastUpdate = localStorage.getItem('lastRdvUpdate');
        const now = Date.now();
        
        if (!lastUpdate || (now - parseInt(lastUpdate)) > 300000) { // 5 minutes
            // Optionnel: recharger la page ou faire un appel AJAX
            localStorage.setItem('lastRdvUpdate', now.toString());
        }
    }, 60000); // Vérifier chaque minute

    // Notification de confirmation pour les actions
    function showNotification(message, type = 'success') {
        const notification = document.createElement('div');
        notification.className = `notification notification-${type}`;
        notification.innerHTML = `
            <div class="notification-content">
                <span class="notification-icon">${type === 'success' ? '✅' : '❌'}</span>
                <span class="notification-message">${message}</span>
            </div>
        `;
        
        // Styles pour la notification
        notification.style.cssText = `
            position: fixed;
            top: 20px;
            right: 20px;
            background: ${type === 'success' ? 'linear-gradient(135deg, #55efc4, #00b894)' : 'linear-gradient(135deg, #fd79a8, #e84393)'};
            color: white;
            padding: 1rem 1.5rem;
            border-radius: 10px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.2);
            z-index: 3000;
            animation: slideInRight 0.3s ease-out;
            cursor: pointer;
        `;
        
        document.body.appendChild(notification);
        
        // Supprimer automatiquement après 5 secondes
        setTimeout(() => {
            notification.style.animation = 'slideOutRight 0.3s ease-out';
            setTimeout(() => {
                if (notification.parentNode) {
                    notification.parentNode.removeChild(notification);
                }
            }, 300);
        }, 5000);
        
        // Permettre de fermer en cliquant
        notification.addEventListener('click', () => {
            notification.style.animation = 'slideOutRight 0.3s ease-out';
            setTimeout(() => {
                if (notification.parentNode) {
                    notification.parentNode.removeChild(notification);
                }
            }, 300);
        });
    }

    // CSS supplémentaire pour les animations de notification
    const style = document.createElement('style');
    style.textContent = `
        @keyframes slideInRight {
            from {
                transform: translateX(100%);
                opacity: 0;
            }
            to {
                transform: translateX(0);
                opacity: 1;
            }
        }
        
        @keyframes slideOutRight {
            from {
                transform: translateX(0);
                opacity: 1;
            }
            to {
                transform: translateX(100%);
                opacity: 0;
            }
        }
        
        @keyframes modalSlideOut {
            from {
                transform: translateY(0);
                opacity: 1;
            }
            to {
                transform: translateY(-50px);
                opacity: 0;
            }
        }
        
        .notification-content {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .notification-icon {
            font-size: 1.2rem;
        }
        
        .notification-message {
            font-weight: 600;
        }
    `;
    document.head.appendChild(style);

    // Validation du formulaire de report
    document.getElementById('nouvelle_date').addEventListener('change', function() {
        const selectedDate = new Date(this.value);
        const today = new Date();
        today.setHours(0, 0, 0, 0);
        
        if (selectedDate < today) {
            this.value = '';
            showNotification('⚠️ La date ne peut pas être antérieure à aujourd\'hui', 'error');
        }
    });

    // Gestion des heures d'ouverture pour le report
    document.getElementById('nouvelle_heure').addEventListener('change', function() {
        const selectedHour = parseInt(this.value.split(':')[0]);
        
        // Exemple: heures d'ouverture de 8h à 18h
        if (selectedHour < 8 || selectedHour >= 18) {
            this.value = '';
            showNotification('⚠️ Veuillez sélectionner une heure entre 8h00 et 18h00', 'error');
        }
    });
    </script>
</body>
</html>