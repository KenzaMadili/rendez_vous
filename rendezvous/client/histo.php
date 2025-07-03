<?php
session_start();

// Connexion à la base de données
try {
    $pdo = new PDO("mysql:host=localhost;dbname=rendezvs;charset=utf8", "root", "");
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Erreur de connexion : " . $e->getMessage());
}

// Vérification de session - Personnel uniquement
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'client') {
    header('Location: ../login.php');
    exit();
}

$personnelId = $_SESSION['user_id'];
$personnelName = $_SESSION['name'] ?? 'Client';

// Gestion de la suppression
if (isset($_POST['delete_message']) && isset($_POST['message_id'])) {
    try {
        $stmt = $pdo->prepare("DELETE FROM messages WHERE id = ? AND user_id = ?");
        $stmt->execute([$_POST['message_id'], $personnelId]);
        $success_message = "Message supprimé avec succès !";
    } catch (PDOException $e) {
        $error_message = "Erreur lors de la suppression : " . $e->getMessage();
    }
}

// Requête pour récupérer les messages envoyés par ce personnel
try {
    $stmt = $pdo->prepare("
        SELECT id, nom, num_tele, email, sujet, message, date_envoi, role
        FROM messages
        WHERE user_id = ?
        ORDER BY date_envoi DESC
    ");
    $stmt->execute([$personnelId]);
    $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $messages = [];
    $error_message = "Erreur lors de la récupération des messages : " . $e->getMessage();
}

// Compter le nombre total de messages
$totalMessages = count($messages);
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mes Messages - Client</title>
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

        .header p {
            color: #666;
            font-size: 1.1rem;
        }

        .user-info {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            border-radius: 15px;
            padding: 1.5rem;
            margin-bottom: 2rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
        }

        .user-details h3 {
            color: #333;
            margin-bottom: 0.5rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .user-details p {
            color: #666;
            font-size: 0.9rem;
        }

        .stats {
            text-align: right;
        }

        .stats-number {
            font-size: 2rem;
            font-weight: 700;
            color: #667eea;
        }

        .stats-label {
            color: #666;
            font-size: 0.9rem;
        }

        .actions {
            display: flex;
            gap: 1rem;
            margin-bottom: 2rem;
            flex-wrap: wrap;
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

        .btn-back {
            background: rgba(255, 255, 255, 0.2);
            color: white;
            border: 2px solid rgba(255, 255, 255, 0.3);
        }

        .btn-back:hover {
            background: rgba(255, 255, 255, 0.3);
            border-color: rgba(255, 255, 255, 0.5);
            transform: translateY(-2px);
        }

        .btn-new {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }

        .btn-new:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
        }

        .btn-refresh {
            background: rgba(40, 167, 69, 0.9);
            color: white;
        }

        .btn-refresh:hover {
            background: rgba(40, 167, 69, 1);
            transform: translateY(-2px);
        }

        .btn-edit {
            background: rgba(255, 193, 7, 0.9);
            color: #333;
            padding: 0.5rem 1rem;
            font-size: 0.85rem;
        }

        .btn-edit:hover {
            background: rgba(255, 193, 7, 1);
            transform: translateY(-1px);
        }

        .btn-delete {
            background: rgba(220, 53, 69, 0.9);
            color: white;
            padding: 0.5rem 1rem;
            font-size: 0.85rem;
        }

        .btn-delete:hover {
            background: rgba(220, 53, 69, 1);
            transform: translateY(-1px);
        }

        .message-list {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            border-radius: 20px;
            padding: 2rem;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
        }

        .message-item {
            background: #f8fafc;
            border-radius: 15px;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
            border-left: 4px solid #667eea;
            transition: all 0.3s ease;
            position: relative;
        }

        .message-item:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.1);
        }

        .message-item:last-child {
            margin-bottom: 0;
        }

        .message-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 1rem;
            flex-wrap: wrap;
            gap: 1rem;
        }

        .message-title {
            flex: 1;
        }

        .sujet {
            font-weight: 700;
            font-size: 1.2rem;
            color: #333;
            margin-bottom: 0.3rem;
        }

        .message-meta {
            display: flex;
            flex-direction: column;
            align-items: flex-end;
            gap: 0.5rem;
        }

        .date {
            font-size: 0.9rem;
            color: #888;
            background: white;
            padding: 0.3rem 0.8rem;
            border-radius: 15px;
            border: 1px solid #e2e8f0;
        }

        .message-actions {
            display: flex;
            gap: 0.5rem;
            margin-top: 0.5rem;
        }

        .contact-info {
            display: flex;
            gap: 1rem;
            margin-bottom: 1rem;
            flex-wrap: wrap;
        }

        .contact-item {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            color: #666;
            font-size: 0.9rem;
        }

        .contenu {
            background: white;
            padding: 1.2rem;
            border-radius: 10px;
            border: 1px solid #e2e8f0;
            line-height: 1.7;
            color: #333;
            margin-bottom: 1rem;
        }

        .empty-state {
            text-align: center;
            padding: 3rem;
            color: #666;
        }

        .empty-state-icon {
            font-size: 4rem;
            margin-bottom: 1rem;
            opacity: 0.3;
        }

        .empty-state h3 {
            margin-bottom: 1rem;
            color: #333;
        }

        .empty-state p {
            margin-bottom: 2rem;
        }

        .message-status {
            position: absolute;
            top: -5px;
            right: -5px;
            background: #28a745;
            color: white;
            border-radius: 50%;
            width: 20px;
            height: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.8rem;
            font-weight: bold;
        }

        .alert {
            background: #f8d7da;
            color: #721c24;
            padding: 1rem;
            border-radius: 10px;
            margin-bottom: 1rem;
            border: 1px solid #f5c6cb;
        }

        .success {
            background: #d4edda;
            color: #155724;
            padding: 1rem;
            border-radius: 10px;
            margin-bottom: 1rem;
            border: 1px solid #c3e6cb;
        }

        .debug-info {
            background: #e7f3ff;
            color: #0066cc;
            padding: 1rem;
            border-radius: 10px;
            margin-bottom: 1rem;
            border: 1px solid #b3d9ff;
            font-size: 0.9rem;
        }

        /* Modal styles */
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
            backdrop-filter: blur(5px);
        }

        .modal-content {
            background-color: white;
            margin: 5% auto;
            padding: 2rem;
            border-radius: 20px;
            width: 90%;
            max-width: 500px;
            position: relative;
            animation: modalSlideIn 0.3s ease;
        }

        @keyframes modalSlideIn {
            from {
                opacity: 0;
                transform: translateY(-50px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
            padding-bottom: 1rem;
            border-bottom: 2px solid #f1f5f9;
        }

        .modal-header h2 {
            color: #333;
            font-size: 1.5rem;
        }

        .close {
            color: #aaa;
            font-size: 2rem;
            font-weight: bold;
            cursor: pointer;
            line-height: 1;
        }

        .close:hover {
            color: #333;
        }

        .modal-body p {
            margin-bottom: 1.5rem;
            color: #666;
            font-size: 1.1rem;
        }

        .modal-actions {
            display: flex;
            gap: 1rem;
            justify-content: flex-end;
        }

        .btn-confirm {
            background: #dc3545;
            color: white;
        }

        .btn-cancel {
            background: #6c757d;
            color: white;
        }

        @media (max-width: 768px) {
            body {
                padding: 10px;
            }

            .header h1 {
                font-size: 2rem;
            }

            .user-info {
                flex-direction: column;
                text-align: center;
                gap: 1rem;
            }

            .stats {
                text-align: center;
            }

            .message-header {
                flex-direction: column;
                align-items: stretch;
            }

            .message-meta {
                align-items: flex-start;
            }

            .contact-info {
                flex-direction: column;
                gap: 0.5rem;
            }

            .message-list {
                padding: 1rem;
            }

            .message-item {
                padding: 1rem;
            }

            .actions {
                flex-direction: column;
            }

            .message-actions {
                flex-direction: column;
            }

            .modal-content {
                margin: 10% auto;
                width: 95%;
            }
        }
    </style>
</head>
<body>

<div class="container">
    <div class="actions">
        <a href="dashboard.php" class="btn btn-back">
            ← Retour au tableau de bord
        </a>
        <a href="contact.php" class="btn btn-new">
            ✉️ Nouveau message
        </a>
        <a href="?refresh=1" class="btn btn-refresh">
            🔄 Actualiser
        </a>
    </div>

    <div class="header">
        <h1>📧 Mes Messages</h1>
        <p>Historique de vos communications avec l'administration</p>
    </div>

    <!-- Affichage des messages de statut -->
    <?php if (isset($_GET['sent'])): ?>
        <div class="success">
            ✅ Votre message a été envoyé avec succès !
        </div>
    <?php endif; ?>

    <?php if (isset($_GET['updated'])): ?>
        <div class="success">
            ✅ Votre message a été modifié avec succès !
        </div>
    <?php endif; ?>

    <?php if (isset($success_message)): ?>
        <div class="success">
            ✅ <?php echo htmlspecialchars($success_message); ?>
        </div>
    <?php endif; ?>

    <?php if (isset($error_message)): ?>
        <div class="alert">
            ⚠️ <?php echo htmlspecialchars($error_message); ?>
        </div>
    <?php endif; ?>

    <!-- Informations de debug (optionnel) -->
    <?php if (isset($_GET['debug'])): ?>
        <div class="debug-info">
            <strong>🔍 Informations de débogage :</strong><br>
            User ID: <?php echo $personnelId; ?><br>
            Role: <?php echo $_SESSION['role']; ?><br>
            Nom: <?php echo htmlspecialchars($personnelName); ?><br>
            Messages trouvés: <?php echo $totalMessages; ?>
        </div>
    <?php endif; ?>

    <div class="user-info">
        <div class="user-details">
            <h3>👤 <?php echo htmlspecialchars($personnelName); ?></h3>
            <p>Client • Messages envoyés à l'administration</p>
        </div>
        <div class="stats">
            <div class="stats-number"><?php echo $totalMessages; ?></div>
            <div class="stats-label">Messages envoyés</div>
        </div>
    </div>

    <div class="message-list">
        <?php if ($totalMessages > 0): ?>
            <?php foreach ($messages as $msg): ?>
                <div class="message-item">
                    <div class="message-status">✓</div>
                    
                    <div class="message-header">
                        <div class="message-title">
                            <div class="sujet">📬 <?php echo htmlspecialchars($msg['sujet']); ?></div>
                        </div>
                        <div class="message-meta">
                            <div class="date">
                                📅 <?php echo date('d/m/Y à H:i', strtotime($msg['date_envoi'])); ?>
                            </div>
                            <div class="message-actions">
                                <a href="edit_message.php?id=<?php echo $msg['id']; ?>" class="btn btn-edit">
                                    ✏️ Modifier
                                </a>
                                <button type="button" class="btn btn-delete" onclick="confirmDelete(<?php echo $msg['id']; ?>, '<?php echo htmlspecialchars(addslashes($msg['sujet'])); ?>')">
                                    🗑️ Supprimer
                                </button>
                            </div>
                        </div>
                    </div>

                    <div class="contact-info">
                        <div class="contact-item">
                            <span>📧</span>
                            <span><?php echo htmlspecialchars($msg['email']); ?></span>
                        </div>
                        <?php if (!empty($msg['num_tele'])): ?>
                            <div class="contact-item">
                                <span>📱</span>
                                <span><?php echo htmlspecialchars($msg['num_tele']); ?></span>
                            </div>
                        <?php endif; ?>
                        <div class="contact-item">
                            <span>👤</span>
                            <span><?php echo htmlspecialchars($msg['nom']); ?></span>
                        </div>
                    </div>

                    <div class="contenu">
                        <?php echo nl2br(htmlspecialchars($msg['message'])); ?>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="empty-state">
                <div class="empty-state-icon">📭</div>
                <h3>Aucun message envoyé</h3>
                <p>Vous n'avez encore envoyé aucun message à l'administration.</p>
                <a href="contact.php" class="btn btn-new">
                    ✉️ Envoyer votre premier message
                </a>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Modal de confirmation de suppression -->
<div id="deleteModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2>🗑️ Confirmer la suppression</h2>
            <span class="close" onclick="closeDeleteModal()">&times;</span>
        </div>
        <div class="modal-body">
            <p>Êtes-vous sûr de vouloir supprimer le message "<strong id="messageSubject"></strong>" ?</p>
            <p style="color: #dc3545; font-weight: 600;">⚠️ Cette action est irréversible.</p>
        </div>
        <div class="modal-actions">
            <button type="button" class="btn btn-cancel" onclick="closeDeleteModal()">
                ❌ Annuler
            </button>
            <form method="POST" style="display: inline;">
                <input type="hidden" name="message_id" id="deleteMessageId">
                <button type="submit" name="delete_message" class="btn btn-confirm">
                    🗑️ Supprimer définitivement
                </button>
            </form>
        </div>
    </div>
</div>

<script>
function confirmDelete(messageId, messageSubject) {
    document.getElementById('deleteMessageId').value = messageId;
    document.getElementById('messageSubject').textContent = messageSubject;
    document.getElementById('deleteModal').style.display = 'block';
}

function closeDeleteModal() {
    document.getElementById('deleteModal').style.display = 'none';
}

// Fermer le modal en cliquant à l'extérieur
window.onclick = function(event) {
    const modal = document.getElementById('deleteModal');
    if (event.target == modal) {
        modal.style.display = 'none';
    }
}

// Fermer le modal avec la touche Echap
document.addEventListener('keydown', function(event) {
    if (event.key === 'Escape') {
        closeDeleteModal();
    }
});
</script>

</body>
</html>