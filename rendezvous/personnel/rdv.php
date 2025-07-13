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
        // Calculer l'heure de fin
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
        
        // Récupérer les disponibilités adjacentes pour potentielle fusion
        $stmt = $pdo->prepare("
            SELECT id, heure_debut, heure_fin 
            FROM disponibilites 
            WHERE personnel_id = ? 
            AND date = ? 
            AND is_available = 1
            AND (
                TIME(heure_fin) = TIME(?) 
                OR TIME(heure_debut) = TIME(?)
            )
            ORDER BY heure_debut
        ");
        $stmt->execute([$personnel_id, $date, $heure, $heure_fin]);
        $disponibilites_adjacentes = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Déterminer les nouvelles bornes après fusion
        $nouveau_debut = $heure;
        $nouvelle_fin = $heure_fin;
        $ids_a_supprimer = [];
        
        foreach ($disponibilites_adjacentes as $adj) {
            if ($adj['heure_fin'] === $heure) {
                // Disponibilité qui finit juste avant notre créneau
                $nouveau_debut = $adj['heure_debut'];
                $ids_a_supprimer[] = $adj['id'];
            } elseif ($adj['heure_debut'] === $heure_fin) {
                // Disponibilité qui commence juste après notre créneau
                $nouvelle_fin = $adj['heure_fin'];
                $ids_a_supprimer[] = $adj['id'];
            }
        }
        
        // Supprimer les disponibilités adjacentes
        if (!empty($ids_a_supprimer)) {
            $placeholders = str_repeat('?,', count($ids_a_supprimer) - 1) . '?';
            $stmt = $pdo->prepare("DELETE FROM disponibilites WHERE id IN ($placeholders)");
            $stmt->execute($ids_a_supprimer);
        }
        
        // Créer la nouvelle disponibilité fusionnée
        $stmt = $pdo->prepare("
            INSERT INTO disponibilites (personnel_id, date, heure_debut, heure_fin, is_available, created_at) 
            VALUES (?, ?, ?, ?, 1, NOW())
        ");
        $stmt->execute([$personnel_id, $date, $nouveau_debut, $nouvelle_fin]);
        
        return true;
    } catch (PDOException $e) {
        error_log("Erreur lors de la libération du créneau : " . $e->getMessage());
        return false;
    }
}

// Fonction pour vérifier si un créneau est déjà occupé
function isSlotOccupied($pdo, $personnel_id, $date, $heure, $duration) {
    try {
        $heure_fin = date('H:i:s', strtotime($heure) + ($duration * 60));
        
        // Vérifier s'il y a des disponibilités "occupées" qui chevauchent
        $stmt = $pdo->prepare("
            SELECT COUNT(*) 
            FROM disponibilites 
            WHERE personnel_id = ? 
            AND date = ? 
            AND is_available = 0
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
        
        return $stmt->fetchColumn() > 0;
    } catch (PDOException $e) {
        error_log("Erreur lors de la vérification du créneau : " . $e->getMessage());
        return false;
    }
}

// Fonction pour vérifier si le personnel a des disponibilités pour la date donnée
function hasAvailabilityForDate($pdo, $personnel_id, $date, $heure, $duration) {
    try {
        $heure_fin = date('H:i:s', strtotime($heure) + ($duration * 60));
        
        // Vérifier s'il y a une disponibilité qui couvre complètement le créneau demandé
        $stmt = $pdo->prepare("
            SELECT COUNT(*) 
            FROM disponibilites 
            WHERE personnel_id = ? 
            AND date = ? 
            AND is_available = 1
            AND TIME(heure_debut) <= TIME(?)
            AND TIME(heure_fin) >= TIME(?)
        ");
        $stmt->execute([$personnel_id, $date, $heure, $heure_fin]);
        
        return $stmt->fetchColumn() > 0;
    } catch (PDOException $e) {
        error_log("Erreur lors de la vérification des disponibilités : " . $e->getMessage());
        return false;
    }
}

if ($_POST) {
    $action = $_POST['action'] ?? '';
    $rdv_id = $_POST['rdv_id'] ?? '';

    if ($action && $rdv_id) {
        try {
            // Commencer une transaction
            $pdo->beginTransaction();
            
            // Récupérer les informations du RDV pour gérer les disponibilités
            $stmt = $pdo->prepare("
                SELECT r.*, s.duration as service_duration, s.name as service_name
                FROM rendezvous r
                LEFT JOIN services s ON r.service_id = s.id
                WHERE r.id = ? AND r.personnel_id = ?
            ");
            $stmt->execute([$rdv_id, $personnel_id]);
            $rdv_info = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$rdv_info) {
                throw new Exception("Rendez-vous non trouvé");
            }
            
            // Déterminer la durée effective du RDV
            $duration_effective = $rdv_info['service_duration'] ?? 60; // Par défaut 60 minutes
            
            if ($action === 'confirmer') {
                // Vérifier si le créneau n'est pas déjà occupé
                if (isSlotOccupied($pdo, $personnel_id, $rdv_info['date'], $rdv_info['heure'], $duration_effective)) {
                    throw new Exception("Ce créneau est déjà occupé par un autre rendez-vous");
                }
                
                // Vérifier si le personnel a des disponibilités pour cette date/heure
                if (!hasAvailabilityForDate($pdo, $personnel_id, $rdv_info['date'], $rdv_info['heure'], $duration_effective)) {
                    throw new Exception("Aucune disponibilité trouvée pour ce créneau");
                }
                
                // Confirmer le RDV
                $stmt = $pdo->prepare("UPDATE rendezvous SET status = 'confirmed', updated_at = NOW() WHERE id = ? AND personnel_id = ?");
                $stmt->execute([$rdv_id, $personnel_id]);
                
                // Marquer le créneau comme occupé dans les disponibilités
                if (!markSlotAsUnavailable($pdo, $personnel_id, $rdv_info['date'], $rdv_info['heure'], $duration_effective)) {
                    throw new Exception("Erreur lors de la mise à jour des disponibilités");
                }
                
                $message = "Rendez-vous confirmé avec succès et créneau marqué comme occupé";
                
            } elseif ($action === 'annuler') {
                // Si le RDV était confirmé, libérer le créneau
                if ($rdv_info['status'] === 'confirmed') {
                    markSlotAsAvailable($pdo, $personnel_id, $rdv_info['date'], $rdv_info['heure'], $duration_effective);
                }
                
                // Annuler le RDV
                $stmt = $pdo->prepare("UPDATE rendezvous SET status = 'cancelled', updated_at = NOW() WHERE id = ? AND personnel_id = ?");
                $stmt->execute([$rdv_id, $personnel_id]);
                
                $message = "Rendez-vous annulé avec succès";
                
            } elseif ($action === 'terminer') {
                // Terminer le RDV
                $stmt = $pdo->prepare("UPDATE rendezvous SET status = 'completed', updated_at = NOW() WHERE id = ? AND personnel_id = ?");
                $stmt->execute([$rdv_id, $personnel_id]);
                
                // Libérer le créneau (le RDV est terminé)
                if ($rdv_info['status'] === 'confirmed') {
                    markSlotAsAvailable($pdo, $personnel_id, $rdv_info['date'], $rdv_info['heure'], $duration_effective);
                }
                
                $message = "Rendez-vous marqué comme terminé";
                
            } elseif ($action === 'reporter' && isset($_POST['nouvelle_date'], $_POST['nouvelle_heure'])) {
                $nouvelle_date = $_POST['nouvelle_date'];
                $nouvelle_heure = $_POST['nouvelle_heure'];
                
                // Vérifier si le nouveau créneau n'est pas déjà occupé
                if (isSlotOccupied($pdo, $personnel_id, $nouvelle_date, $nouvelle_heure, $duration_effective)) {
                    throw new Exception("Le nouveau créneau est déjà occupé");
                }
                
                // Vérifier si le personnel a des disponibilités pour le nouveau créneau
                if (!hasAvailabilityForDate($pdo, $personnel_id, $nouvelle_date, $nouvelle_heure, $duration_effective)) {
                    throw new Exception("Aucune disponibilité trouvée pour le nouveau créneau");
                }
                
                // Si le RDV était confirmé, libérer l'ancien créneau
                if ($rdv_info['status'] === 'confirmed') {
                    markSlotAsAvailable($pdo, $personnel_id, $rdv_info['date'], $rdv_info['heure'], $duration_effective);
                }
                
                // Mettre à jour le RDV avec le nouveau créneau
                $stmt = $pdo->prepare("UPDATE rendezvous SET date = ?, heure = ?, status = 'pending', updated_at = NOW() WHERE id = ? AND personnel_id = ?");
                $stmt->execute([$nouvelle_date, $nouvelle_heure, $rdv_id, $personnel_id]);
                
                $message = "Rendez-vous reporté avec succès";
            }
            
            // Valider la transaction
            $pdo->commit();
            
        } catch (PDOException $e) {
            $pdo->rollback();
            $error = "Erreur lors de la mise à jour : " . $e->getMessage();
        } catch (Exception $e) {
            $pdo->rollback();
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
               s.price AS prix,
               s.duration as service_duration
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

        .rdv-date, .rdv-duree {
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
                height: auto;
                padding: 1rem 0;
            }

            .page-header h1 {
                font-size: 2rem;
            }

            .tabs {
                flex-direction: column;
                gap: 0.5rem;
            }

            .tab {
                padding: 0.8rem 1rem;
            }

            .rdv-header {
                flex-direction: column;
                gap: 1rem;
                align-items: flex-start;
            }

            .rdv-info {
                grid-template-columns: 1fr;
            }

            .rdv-actions {
                flex-direction: column;
            }

            .btn {
                width: 100%;
                text-align: center;
            }

            .modal-content {
                width: 95%;
                margin: 5% auto;
                padding: 1.5rem;
            }
        }

        .empty-state {
            text-align: center;
            padding: 3rem;
            color: #64748b;
        }

        .empty-state svg {
            width: 100px;
            height: 100px;
            margin: 0 auto 1rem;
            opacity: 0.5;
        }

        .empty-state h3 {
            margin-bottom: 0.5rem;
            color: #374151;
        }

        .loading {
            display: inline-block;
            width: 20px;
            height: 20px;
            border: 2px solid #f3f3f3;
            border-top: 2px solid #667eea;
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
    </style>
</head>
<body>
    <header>
        <div class="container">
            <nav>
                <a href="dashboard.php">🏠 Tableau de bord</a>
                <a href="rdv.php">📅 Mes Rendez-vous</a>
                <a href="dispo.php">🕒 Disponibilités</a>
                <a href="../logout.php">🚪 Déconnexion</a>
            </nav>
        </div>
    </header>

    <main>
        <div class="container">
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

            <div class="page-header">
                <h1>📅 Mes Rendez-vous</h1>
                <p>Gérez vos rendez-vous clients - Confirmez, reportez ou annulez</p>
            </div>

            <div class="tabs">
                <div class="tab active" data-tab="pending">
                    En attente (<?php echo count($rdv_en_attente); ?>)
                </div>
                <div class="tab" data-tab="confirmed">
                    Confirmés (<?php echo count($rdv_confirmes); ?>)
                </div>
                <div class="tab" data-tab="history">
                    Historique (<?php echo count($rdv_passes); ?>)
                </div>
            </div>

            <!-- Rendez-vous en attente -->
            <div id="pending" class="tab-content active">
                <div class="rdv-grid">
                    <?php if (empty($rdv_en_attente)): ?>
                        <div class="empty-state">
                            <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 15l-5-5 1.41-1.41L10 14.17l7.59-7.59L19 8l-9 9z" fill="currentColor"/>
                            </svg>
                            <h3>Aucun rendez-vous en attente</h3>
                            <p>Tous vos rendez-vous sont traités !</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($rdv_en_attente as $rdv): ?>
                            <div class="rdv-card">
                                <div class="rdv-header">
                                    <div class="rdv-date">
                                        🗓️ <?php echo date('d/m/Y', strtotime($rdv['date'])); ?> à <?php echo date('H:i', strtotime($rdv['heure'])); ?>
                                    </div>
                                    <div class="rdv-status status-pending">
                                        ⏳ En attente
                                    </div>
                                </div>

                                <div class="rdv-info">
                                    <div class="info-item">
                                        <span class="info-icon">👤</span>
                                        <span><strong>Client:</strong> <?php echo htmlspecialchars($rdv['client_nom']); ?></span>
                                    </div>
                                    <div class="info-item">
                                        <span class="info-icon">📧</span>
                                        <span><?php echo htmlspecialchars($rdv['client_email']); ?></span>
                                    </div>
                                    <div class="info-item">
                                        <span class="info-icon">💼</span>
                                        <span><strong>Service:</strong> <?php echo htmlspecialchars($rdv['service_nom']); ?></span>
                                    </div>
                                    <div class="info-item">
                                        <span class="info-icon">⏱️</span>
                                        <span><strong>Durée:</strong> <?php echo $rdv['service_duration']; ?> min</span>
                                    </div>
                                    <div class="info-item">
                                        <span class="info-icon">💰</span>
                                        <span><strong>Prix:</strong> <?php echo number_format($rdv['prix'], 0, ',', ' '); ?> DH</span>
                                    </div>
                                </div>

                                <?php if ($rdv['notes']): ?>
                                    <div class="info-item">
                                        <span class="info-icon">📝</span>
                                        <span><strong>Notes:</strong> <?php echo htmlspecialchars($rdv['notes']); ?></span>
                                    </div>
                                <?php endif; ?>

                                <div class="rdv-actions">
                                    <form method="POST" style="display: inline;">
                                        <input type="hidden" name="action" value="confirmer">
                                        <input type="hidden" name="rdv_id" value="<?php echo $rdv['id']; ?>">
                                        <button type="submit" class="btn btn-confirm">✅ Confirmer</button>
                                    </form>
                                    <button class="btn btn-reschedule" onclick="openRescheduleModal(<?php echo $rdv['id']; ?>, '<?php echo $rdv['date']; ?>', '<?php echo $rdv['heure']; ?>')">
                                        📅 Reporter
                                    </button>
                                    <form method="POST" style="display: inline;">
                                        <input type="hidden" name="action" value="annuler">
                                        <input type="hidden" name="rdv_id" value="<?php echo $rdv['id']; ?>">
                                        <button type="submit" class="btn btn-cancel" onclick="return confirm('Êtes-vous sûr de vouloir annuler ce rendez-vous ?')">❌ Annuler</button>
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
                            <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <path d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41z" fill="currentColor"/>
                            </svg>
                            <h3>Aucun rendez-vous confirmé</h3>
                            <p>Les rendez-vous confirmés apparaîtront ici</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($rdv_confirmes as $rdv): ?>
                            <div class="rdv-card">
                                <div class="rdv-header">
                                    <div class="rdv-date">
                                        🗓️ <?php echo date('d/m/Y', strtotime($rdv['date'])); ?> à <?php echo date('H:i', strtotime($rdv['heure'])); ?>
                                    </div>
                                    <div class="rdv-status status-confirmed">
                                        ✅ Confirmé
                                    </div>
                                </div>

                                <div class="rdv-info">
                                    <div class="info-item">
                                        <span class="info-icon">👤</span>
                                        <span><strong>Client:</strong> <?php echo htmlspecialchars($rdv['client_nom']); ?></span>
                                    </div>
                                    <div class="info-item">
                                        <span class="info-icon">📧</span>
                                        <span><?php echo htmlspecialchars($rdv['client_email']); ?></span>
                                    </div>
                                    <div class="info-item">
                                        <span class="info-icon">💼</span>
                                        <span><strong>Service:</strong> <?php echo htmlspecialchars($rdv['service_nom']); ?></span>
                                    </div>
                                    <div class="info-item">
                                        <span class="info-icon">⏱️</span>
                                        <span><strong>Durée:</strong> <?php echo $rdv['service_duration']; ?> min</span>
                                    </div>
                                    <div class="info-item">
                                        <span class="info-icon">💰</span>
                                        <span><strong>Prix:</strong> <?php echo number_format($rdv['prix'], 0, ',', ' '); ?> DH</span>
                                    </div>
                                </div>

                                <?php if ($rdv['notes']): ?>
                                    <div class="info-item">
                                        <span class="info-icon">📝</span>
                                        <span><strong>Notes:</strong> <?php echo htmlspecialchars($rdv['notes']); ?></span>
                                    </div>
                                <?php endif; ?>

                                <div class="rdv-actions">
                                    <form method="POST" style="display: inline;">
                                        <input type="hidden" name="action" value="terminer">
                                        <input type="hidden" name="rdv_id" value="<?php echo $rdv['id']; ?>">
                                        <button type="submit" class="btn btn-complete">✅ Terminer</button>
                                    </form>
                                    <button class="btn btn-reschedule" onclick="openRescheduleModal(<?php echo $rdv['id']; ?>, '<?php echo $rdv['date']; ?>', '<?php echo $rdv['heure']; ?>')">
                                        📅 Reporter
                                    </button>
                                    <form method="POST" style="display: inline;">
                                        <input type="hidden" name="action" value="annuler">
                                        <input type="hidden" name="rdv_id" value="<?php echo $rdv['id']; ?>">
                                        <button type="submit" class="btn btn-cancel" onclick="return confirm('Êtes-vous sûr de vouloir annuler ce rendez-vous ?')">❌ Annuler</button>
                                    </form>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Historique -->
            <div id="history" class="tab-content">
                <div class="rdv-grid">
                    <?php if (empty($rdv_passes)): ?>
                        <div class="empty-state">
                            <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <path d="M13 3c-4.97 0-9 4.03-9 9H1l3.89 3.89.07.14L9 12H6c0-3.87 3.13-7 7-7s7 3.13 7 7-3.13 7-7 7c-1.93 0-3.68-.79-4.94-2.06l-1.42 1.42C8.27 19.99 10.51 21 13 21c4.97 0 9-4.03 9-9s-4.03-9-9-9zm-1 5v5l4.28 2.54.72-1.21-3.5-2.08V8H12z" fill="currentColor"/>
                            </svg>
                            <h3>Aucun historique</h3>
                            <p>L'historique de vos rendez-vous apparaîtra ici</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($rdv_passes as $rdv): ?>
                            <div class="rdv-card">
                                <div class="rdv-header">
                                    <div class="rdv-date">
                                        🗓️ <?php echo date('d/m/Y', strtotime($rdv['date'])); ?> à <?php echo date('H:i', strtotime($rdv['heure'])); ?>
                                    </div>
                                    <div class="rdv-status status-<?php echo $rdv['status']; ?>">
                                        <?php echo $rdv['status'] === 'completed' ? '✅' : '❌'; ?>
                                        <?php echo getStatusText($rdv['status']); ?>
                                    </div>
                                </div>

                                <div class="rdv-info">
                                    <div class="info-item">
                                        <span class="info-icon">👤</span>
                                        <span><strong>Client:</strong> <?php echo htmlspecialchars($rdv['client_nom']); ?></span>
                                    </div>
                                    <div class="info-item">
                                        <span class="info-icon">📧</span>
                                        <span><?php echo htmlspecialchars($rdv['client_email']); ?></span>
                                    </div>
                                    <div class="info-item">
                                        <span class="info-icon">💼</span>
                                        <span><strong>Service:</strong> <?php echo htmlspecialchars($rdv['service_nom']); ?></span>
                                    </div>
                                    <div class="info-item">
                                        <span class="info-icon">⏱️</span>
                                        <span><strong>Durée:</strong> <?php echo $rdv['service_duration']; ?> min</span>
                                    </div>
                                    <div class="info-item">
                                        <span class="info-icon">💰</span>
                                        <span><strong>Prix:</strong> <?php echo number_format($rdv['prix'], 0, ',', ' '); ?> DH</span>
                                    </div>
                                </div>

                                <?php if ($rdv['notes']): ?>
                                    <div class="info-item">
                                        <span class="info-icon">📝</span>
                                        <span><strong>Notes:</strong> <?php echo htmlspecialchars($rdv['notes']); ?></span>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </main>

    <!-- Modal de report -->
    <div id="rescheduleModal" class="modal">
        <div class="modal-content">
            <span class="close">&times;</span>
            <h2>📅 Reporter le rendez-vous</h2>
            <form method="POST">
                <input type="hidden" name="action" value="reporter">
                <input type="hidden" name="rdv_id" id="reschedule_rdv_id">
                
                <div class="form-group">
                    <label for="nouvelle_date">Nouvelle date:</label>
                    <input type="date" id="nouvelle_date" name="nouvelle_date" required>
                </div>
                
                <div class="form-group">
                    <label for="nouvelle_heure">Nouvelle heure:</label>
                    <input type="time" id="nouvelle_heure" name="nouvelle_heure" required>
                </div>
                
                <div class="rdv-actions">
                    <button type="submit" class="btn btn-confirm">✅ Reporter</button>
                    <button type="button" class="btn btn-cancel" onclick="closeRescheduleModal()">❌ Annuler</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        // Gestion des onglets
        document.querySelectorAll('.tab').forEach(tab => {
            tab.addEventListener('click', function() {
                // Retirer la classe active de tous les onglets
                document.querySelectorAll('.tab').forEach(t => t.classList.remove('active'));
                document.querySelectorAll('.tab-content').forEach(tc => tc.classList.remove('active'));
                
                // Ajouter la classe active à l'onglet cliqué
                this.classList.add('active');
                document.getElementById(this.dataset.tab).classList.add('active');
            });
        });

        // Gestion du modal de report
        function openRescheduleModal(rdvId, currentDate, currentTime) {
            document.getElementById('reschedule_rdv_id').value = rdvId;
            document.getElementById('nouvelle_date').value = currentDate;
            document.getElementById('nouvelle_heure').value = currentTime;
            document.getElementById('rescheduleModal').style.display = 'block';
        }

        function closeRescheduleModal() {
            document.getElementById('rescheduleModal').style.display = 'none';
        }

        // Fermer le modal en cliquant sur la croix
        document.querySelector('.close').addEventListener('click', closeRescheduleModal);

        // Fermer le modal en cliquant en dehors
        window.addEventListener('click', function(event) {
            const modal = document.getElementById('rescheduleModal');
            if (event.target === modal) {
                closeRescheduleModal();
            }
        });

        // Définir la date minimale à aujourd'hui
        document.getElementById('nouvelle_date').min = new Date().toISOString().split('T')[0];

        // Animation d'entrée pour les cartes
        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    entry.target.style.animation = 'slideInUp 0.6s ease-out';
                }
            });
        });

        document.querySelectorAll('.rdv-card').forEach(card => {
            observer.observe(card);
        });

        // Ajouter l'animation CSS
        const style = document.createElement('style');
        style.textContent = `
            @keyframes slideInUp {
                from {
                    opacity: 0;
                    transform: translateY(30px);
                }
                to {
                    opacity: 1;
                    transform: translateY(0);
                }
            }
        `;
        document.head.appendChild(style);
    </script>
</body>
</html>