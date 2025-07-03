<?php
session_start();

// Vérification de l'authentification
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'client') {
    header("Location: ../login.php");
    exit();
}

// Configuration de la base de données
try {
    $pdo = new PDO("mysql:host=localhost;dbname=rendezvs;charset=utf8mb4", "root", "");
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Erreur de connexion à la base de données: " . $e->getMessage());
}

$clientId = $_SESSION['user_id'];
$message = '';
$messageType = '';

// Traitement des actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'cancel':
                $rdvId = $_POST['rdv_id'];
                // Vérifier que le RDV appartient au client et est en attente
                $stmt = $pdo->prepare("SELECT status FROM rendezvous WHERE id = ? AND client_id = ?");
                $stmt->execute([$rdvId, $clientId]);
                $rdv = $stmt->fetch();
                
                if ($rdv && $rdv['status'] === 'pending') {
                    $stmt = $pdo->prepare("UPDATE rendezvous SET status = 'cancelled' WHERE id = ? AND client_id = ?");
                    if ($stmt->execute([$rdvId, $clientId])) {
                        $message = "Rendez-vous annulé avec succès.";
                        $messageType = "success";
                    } else {
                        $message = "Erreur lors de l'annulation.";
                        $messageType = "error";
                    }
                } else {
                    $message = "Impossible d'annuler ce rendez-vous.";
                    $messageType = "error";
                }
                break;
                
            case 'edit':
                $rdvId = $_POST['rdv_id'];
                $newDate = $_POST['new_date'];
                $newTime = $_POST['new_time'];
                $newNotes = $_POST['new_notes'];
                
                // Vérifier que le RDV appartient au client et est en attente
                $stmt = $pdo->prepare("SELECT status FROM rendezvous WHERE id = ? AND client_id = ?");
                $stmt->execute([$rdvId, $clientId]);
                $rdv = $stmt->fetch();
                
                if ($rdv && $rdv['status'] === 'pending') {
                    // Vérifier que la nouvelle date/heure n'est pas dans le passé
                    $newDateTime = new DateTime($newDate . ' ' . $newTime);
                    $now = new DateTime();
                    
                    if ($newDateTime > $now) {
                        $stmt = $pdo->prepare("UPDATE rendezvous SET date = ?, heure = ?, notes = ? WHERE id = ? AND client_id = ?");
                        if ($stmt->execute([$newDate, $newTime, $newNotes, $rdvId, $clientId])) {
                            $message = "Rendez-vous modifié avec succès.";
                            $messageType = "success";
                        } else {
                            $message = "Erreur lors de la modification.";
                            $messageType = "error";
                        }
                    } else {
                        $message = "La date et l'heure doivent être dans le futur.";
                        $messageType = "error";
                    }
                } else {
                    $message = "Impossible de modifier ce rendez-vous.";
                    $messageType = "error";
                }
                break;
        }
    }
}

// Requête pour récupérer les rendez-vous
$stmt = $pdo->prepare("
    SELECT r.id, r.date, r.heure, r.status, r.notes, r.created_at,
           s.name AS service_name, s.duration, s.price,
           u.name AS personnel_name
    FROM rendezvous r
    JOIN services s ON r.service_id = s.id
    JOIN users u ON r.personnel_id = u.id
    WHERE r.client_id = ?
    ORDER BY r.date DESC, r.heure DESC
");
$stmt->execute([$clientId]);
$rendezvous = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fonction pour formater le statut
function formatStatus($status) {
    switch ($status) {
        case 'pending': return '⏳ En attente';
        case 'confirmed': return '✅ Confirmé';
        case 'cancelled': return '❌ Annulé';
        case 'completed': return '✔️ Terminé';
        default: return ucfirst($status);
    }
}

// Fonction pour formater la date
function formatDate($date) {
    $dateObj = new DateTime($date);
    return $dateObj->format('d/m/Y');
}

// Fonction pour formater l'heure
function formatTime($time) {
    return date('H:i', strtotime($time));
}

// Fonction pour déterminer la classe CSS selon le statut
function getStatusClass($status) {
    switch ($status) {
        case 'pending': return 'status-pending';
        case 'confirmed': return 'status-confirmed';
        case 'cancelled': return 'status-cancelled';
        case 'completed': return 'status-completed';
        default: return 'status-default';
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mes Rendez-vous</title>
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
            padding: 2rem;
        }

        .container {
            max-width: 1000px;
            margin: 0 auto;
        }

        h1 {
            color: #fff;
            text-align: center;
            margin-bottom: 2rem;
            font-size: 2.5rem;
            text-shadow: 0 2px 4px rgba(0,0,0,0.3);
        }

        .message {
            padding: 1rem;
            margin-bottom: 1rem;
            border-radius: 8px;
            font-weight: 500;
        }

        .message.success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .message.error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        .no-appointments {
            background: #fff;
            padding: 3rem;
            border-radius: 15px;
            text-align: center;
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
        }

        .no-appointments h2 {
            color: #666;
            margin-bottom: 1rem;
        }

        .no-appointments p {
            color: #999;
            font-size: 1.1rem;
        }

        .rdv {
            background: #fff;
            padding: 1.5rem;
            border-radius: 15px;
            margin-bottom: 1.5rem;
            box-shadow: 0 10px 30px rgba(0,0,0,0.15);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            border-left: 5px solid #667eea;
        }

        .rdv:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 40px rgba(0,0,0,0.2);
        }

        .rdv-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 1rem;
            flex-wrap: wrap;
            gap: 1rem;
        }

        .rdv-title {
            color: #333;
            font-size: 1.3rem;
            font-weight: 600;
            margin: 0;
        }

        .rdv-info {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1rem;
            margin-bottom: 1rem;
        }

        .info-item {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .info-label {
            font-weight: 600;
            color: #555;
            min-width: 80px;
        }

        .info-value {
            color: #333;
        }

        .status {
            padding: 0.5rem 1rem;
            border-radius: 25px;
            font-weight: 600;
            font-size: 0.9rem;
            text-align: center;
            min-width: 120px;
        }

        .status-pending {
            background: #fff3cd;
            color: #856404;
            border: 1px solid #ffeaa7;
        }

        .status-confirmed {
            background: #d4edda;
            color: #155724;
            border: 1px solid #00b894;
        }

        .status-cancelled {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #d63031;
        }

        .status-completed {
            background: #d1ecf1;
            color: #0c5460;
            border: 1px solid #74b9ff;
        }

        .status-default {
            background: #e2e3e5;
            color: #383d41;
            border: 1px solid #ced4da;
        }

        .rdv-notes {
            background: #f8f9fa;
            padding: 1rem;
            border-radius: 8px;
            margin-top: 1rem;
            border-left: 4px solid #667eea;
        }

        .rdv-notes strong {
            color: #667eea;
            display: block;
            margin-bottom: 0.5rem;
        }

        .rdv-actions {
            margin-top: 1.5rem;
            display: flex;
            gap: 1rem;
            flex-wrap: wrap;
        }

        .btn {
            padding: 0.7rem 1.5rem;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
            text-decoration: none;
            display: inline-block;
            transition: all 0.3s ease;
            font-size: 0.9rem;
        }

        .btn-cancel {
            background: #d63031;
            color: white;
        }

        .btn-cancel:hover {
            background: #b71c1c;
            transform: translateY(-2px);
        }

        .btn-modify {
            background: #74b9ff;
            color: white;
        }

        .btn-modify:hover {
            background: #0984e3;
            transform: translateY(-2px);
        }

        .btn-save {
            background: #00b894;
            color: white;
        }

        .btn-save:hover {
            background: #00a085;
            transform: translateY(-2px);
        }

        .btn-cancel-edit {
            background: #6c757d;
            color: white;
        }

        .btn-cancel-edit:hover {
            background: #5a6268;
            transform: translateY(-2px);
        }

        .price {
            color: #00b894;
            font-weight: 600;
            font-size: 1.1rem;
        }

        .duration {
            color: #667eea;
            font-weight: 500;
        }

        .edit-form {
            background: #f8f9fa;
            padding: 1.5rem;
            border-radius: 8px;
            margin-top: 1rem;
            border: 2px solid #667eea;
            display: none;
        }

        .edit-form.show {
            display: block;
        }

        .form-group {
            margin-bottom: 1rem;
        }

        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 600;
            color: #333;
        }

        .form-group input,
        .form-group textarea {
            width: 100%;
            padding: 0.7rem;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 1rem;
        }

        .form-group textarea {
            height: 100px;
            resize: vertical;
        }

        .form-actions {
            display: flex;
            gap: 1rem;
            flex-wrap: wrap;
        }

        @media (max-width: 768px) {
            body {
                padding: 1rem;
            }

            h1 {
                font-size: 2rem;
            }

            .rdv {
                padding: 1rem;
            }

            .rdv-header {
                flex-direction: column;
                align-items: flex-start;
            }

            .rdv-info {
                grid-template-columns: 1fr;
            }

            .rdv-actions,
            .form-actions {
                flex-direction: column;
            }

            .btn {
                text-align: center;
            }
        }

        .back-link {
            display: inline-block;
            margin-bottom: 1rem;
            color: #fff;
            text-decoration: none;
            padding: 0.5rem 1rem;
            background: rgba(255,255,255,0.2);
            border-radius: 8px;
            transition: background 0.3s ease;
        }

        .back-link:hover {
            background: rgba(255,255,255,0.3);
        }

        .hidden {
            display: none !important;
        }
    </style>
</head>
<body>
    <div class="container">
        <a href="dashboard.php" class="back-link">← Retour au tableau de bord</a>
        
        <h1>Mes Rendez-vous</h1>

        <?php if ($message): ?>
            <div class="message <?= $messageType ?>">
                <?= htmlspecialchars($message) ?>
            </div>
        <?php endif; ?>

        <?php if (empty($rendezvous)): ?>
            <div class="no-appointments">
                <h2>📅 Aucun rendez-vous</h2>
                <p>Vous n'avez encore pris aucun rendez-vous.</p>
                <br>
                <a href="prise_rdv.php" class="btn btn-modify">Prendre un rendez-vous</a>
            </div>
        <?php else: ?>
            <?php foreach ($rendezvous as $rdv): ?>
                <div class="rdv" id="rdv-<?= $rdv['id'] ?>">
                    <div class="rdv-header">
                        <h3 class="rdv-title">
                            <?= htmlspecialchars($rdv['service_name']) ?>
                            <br><small style="color: #666; font-weight: normal;">avec <?= htmlspecialchars($rdv['personnel_name']) ?></small>
                        </h3>
                        <div class="status <?= getStatusClass($rdv['status']) ?>">
                            <?= formatStatus($rdv['status']) ?>
                        </div>
                    </div>

                    <div class="rdv-display" id="display-<?= $rdv['id'] ?>">
                        <div class="rdv-info">
                            <div class="info-item">
                                <span class="info-label">📅 Date :</span>
                                <span class="info-value"><?= formatDate($rdv['date']) ?></span>
                            </div>
                            <div class="info-item">
                                <span class="info-label">⏰ Heure :</span>
                                <span class="info-value"><?= formatTime($rdv['heure']) ?></span>
                            </div>
                            <div class="info-item">
                                <span class="info-label">⏱️ Durée :</span>
                                <span class="info-value duration"><?= $rdv['duration'] ?> minutes</span>
                            </div>
                            <div class="info-item">
                                <span class="info-label">💰 Prix :</span>
                                <span class="info-value price"><?= number_format($rdv['price'], 2) ?> DH</span>
                            </div>
                        </div>

                        <?php if (!empty($rdv['notes'])): ?>
                            <div class="rdv-notes">
                                <strong>📝 Remarques :</strong>
                                <?= nl2br(htmlspecialchars($rdv['notes'])) ?>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- Formulaire d'édition (caché par défaut) -->
                    <?php if ($rdv['status'] === 'pending'): ?>
                        <div class="edit-form" id="edit-form-<?= $rdv['id'] ?>">
                            <h4 style="margin-bottom: 1rem; color: #667eea;">✏️ Modifier le rendez-vous</h4>
                            <form method="POST">
                                <input type="hidden" name="action" value="edit">
                                <input type="hidden" name="rdv_id" value="<?= $rdv['id'] ?>">
                                
                                <div class="form-group">
                                    <label for="new_date_<?= $rdv['id'] ?>">Nouvelle date :</label>
                                    <input type="date" id="new_date_<?= $rdv['id'] ?>" name="new_date" 
                                           value="<?= $rdv['date'] ?>" required min="<?= date('Y-m-d') ?>">
                                </div>
                                
                                <div class="form-group">
                                    <label for="new_time_<?= $rdv['id'] ?>">Nouvelle heure :</label>
                                    <input type="time" id="new_time_<?= $rdv['id'] ?>" name="new_time" 
                                           value="<?= $rdv['heure'] ?>" required>
                                </div>
                                
                                <div class="form-group">
                                    <label for="new_notes_<?= $rdv['id'] ?>">Remarques :</label>
                                    <textarea id="new_notes_<?= $rdv['id'] ?>" name="new_notes" 
                                              placeholder="Remarques particulières..."><?= htmlspecialchars($rdv['notes']) ?></textarea>
                                </div>
                                
                                <div class="form-actions">
                                    <button type="submit" class="btn btn-save">💾 Sauvegarder</button>
                                    <button type="button" class="btn btn-cancel-edit" 
                                            onclick="cancelEdit(<?= $rdv['id'] ?>)">❌ Annuler</button>
                                </div>
                            </form>
                        </div>
                    <?php endif; ?>

                    <!-- Actions disponibles seulement pour les RDV en attente -->
                    <?php if ($rdv['status'] === 'pending'): ?>
                        <div class="rdv-actions" id="actions-<?= $rdv['id'] ?>">
                            <button type="button" class="btn btn-modify" 
                                    onclick="showEditForm(<?= $rdv['id'] ?>)">
                                ✏️ Modifier
                            </button>
                            <form method="POST" style="display: inline;" 
                                  onsubmit="return confirm('Êtes-vous sûr de vouloir annuler ce rendez-vous ?')">
                                <input type="hidden" name="action" value="cancel">
                                <input type="hidden" name="rdv_id" value="<?= $rdv['id'] ?>">
                                <button type="submit" class="btn btn-cancel">❌ Annuler</button>
                            </form>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <script>
        // Animation d'apparition des rendez-vous
        document.addEventListener('DOMContentLoaded', function() {
            const rdvElements = document.querySelectorAll('.rdv');
            rdvElements.forEach((rdv, index) => {
                rdv.style.opacity = '0';
                rdv.style.transform = 'translateY(20px)';
                
                setTimeout(() => {
                    rdv.style.transition = 'opacity 0.5s ease, transform 0.5s ease';
                    rdv.style.opacity = '1';
                    rdv.style.transform = 'translateY(0)';
                }, index * 100);
            });
        });

        // Afficher le formulaire d'édition
        function showEditForm(rdvId) {
            const display = document.getElementById('display-' + rdvId);
            const editForm = document.getElementById('edit-form-' + rdvId);
            const actions = document.getElementById('actions-' + rdvId);
            
            display.style.display = 'none';
            actions.style.display = 'none';
            editForm.classList.add('show');
        }

        // Annuler l'édition
        function cancelEdit(rdvId) {
            const display = document.getElementById('display-' + rdvId);
            const editForm = document.getElementById('edit-form-' + rdvId);
            const actions = document.getElementById('actions-' + rdvId);
            
            display.style.display = 'block';
            actions.style.display = 'flex';
            editForm.classList.remove('show');
        }

        // Validation des dates
        document.addEventListener('DOMContentLoaded', function() {
            const dateInputs = document.querySelectorAll('input[type="date"]');
            dateInputs.forEach(input => {
                input.addEventListener('change', function() {
                    const selectedDate = new Date(this.value);
                    const today = new Date();
                    today.setHours(0, 0, 0, 0);
                    
                    if (selectedDate < today) {
                        alert('La date ne peut pas être dans le passé.');
                        this.value = '';
                    }
                });
            });
        });
    </script>
</body>
</html>