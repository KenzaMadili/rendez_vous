<?php
session_start();

// Vérifier si l'utilisateur est connecté et est admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: login.php');
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

// Traitement des actions
if ($_POST) {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'delete_message':
                $messageId = (int)$_POST['message_id'];
                $stmt = $pdo->prepare("DELETE FROM messages WHERE id = ?");
                $stmt->execute([$messageId]);
                echo json_encode(['success' => true]);
                exit();
        }
    }
}

// Récupération des filtres
$roleFilter = $_GET['role'] ?? '';
$dateFilter = $_GET['date'] ?? '';
$searchFilter = $_GET['search'] ?? '';

// Construction de la requête avec filtres
$whereConditions = [];
$params = [];

if ($roleFilter) {
    $whereConditions[] = "m.role = ?";
    $params[] = $roleFilter;
}

if ($dateFilter) {
    $whereConditions[] = "DATE(m.date_envoi) = ?";
    $params[] = $dateFilter;
}

if ($searchFilter) {
    $whereConditions[] = "(m.nom LIKE ? OR m.email LIKE ? OR m.sujet LIKE ? OR m.message LIKE ?)";
    $searchParam = '%' . $searchFilter . '%';
    $params = array_merge($params, [$searchParam, $searchParam, $searchParam, $searchParam]);
}

$whereClause = $whereConditions ? 'WHERE ' . implode(' AND ', $whereConditions) : '';

// Requête principale pour les messages
$query = "
    SELECT 
        m.*,
        u.name as user_name,
        u.email as user_email
    FROM messages m
    LEFT JOIN users u ON m.user_id = u.id
    $whereClause
    ORDER BY m.date_envoi DESC
";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$messages = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Statistiques
$statsQuery = "
    SELECT 
        COUNT(*) as total_messages,
        COUNT(*) as unread_messages,
        SUM(CASE WHEN DATE(date_envoi) = CURDATE() THEN 1 ELSE 0 END) as today_messages,
        SUM(CASE WHEN role = 'client' THEN 1 ELSE 0 END) as client_messages
    FROM messages
";

$statsStmt = $pdo->query($statsQuery);
$stats = $statsStmt->fetch(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Messages Reçus - Administration</title>
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
            max-width: 1400px;
            margin: 0 auto;
            background: white;
            border-radius: 15px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.1);
            overflow: hidden;
        }

        .header {
            background: linear-gradient(135deg, #2c3e50 0%, #3498db 100%);
            color: white;
            padding: 30px;
            text-align: center;
            position: relative;
        }

        .header h1 {
            font-size: 2.5rem;
            margin-bottom: 10px;
            text-shadow: 2px 2px 4px rgba(0,0,0,0.3);
        }

        .header p {
            font-size: 1.1rem;
            opacity: 0.9;
        }

        .logout-btn {
            position: absolute;
            top: 20px;
            right: 20px;
            background: rgba(255,255,255,0.2);
            color: white;
            border: 1px solid rgba(255,255,255,0.3);
            padding: 8px 16px;
            border-radius: 20px;
            text-decoration: none;
            transition: all 0.3s ease;
        }

        .logout-btn:hover {
            background: rgba(255,255,255,0.3);
        }

        .stats-bar {
            display: flex;
            justify-content: space-around;
            background: #f8f9fa;
            padding: 20px;
            border-bottom: 1px solid #e9ecef;
        }

        .stat-item {
            text-align: center;
            padding: 15px;
            background: white;
            border-radius: 10px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            min-width: 120px;
        }

        .stat-number {
            font-size: 2rem;
            font-weight: bold;
            color: #3498db;
            margin-bottom: 5px;
        }

        .stat-label {
            font-size: 0.9rem;
            color: #6c757d;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .filters {
            padding: 20px;
            background: #f8f9fa;
            border-bottom: 1px solid #e9ecef;
        }

        .filter-form {
            display: flex;
            gap: 15px;
            align-items: center;
            flex-wrap: wrap;
        }

        .filter-group {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .filter-group label {
            font-weight: 600;
            color: #495057;
        }

        select, input {
            padding: 8px 12px;
            border: 1px solid #ced4da;
            border-radius: 6px;
            font-size: 14px;
        }

        .search-box {
            padding: 10px 15px;
            border: 2px solid #e9ecef;
            border-radius: 25px;
            width: 300px;
            font-size: 14px;
            transition: all 0.3s ease;
        }

        .search-box:focus {
            outline: none;
            border-color: #3498db;
            box-shadow: 0 0 0 3px rgba(52, 152, 219, 0.2);
        }

        .filter-btn {
            background: #3498db;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 500;
            transition: all 0.3s ease;
        }

        .filter-btn:hover {
            background: #2980b9;
        }

        .messages-container {
            padding: 20px;
            max-height: 600px;
            overflow-y: auto;
        }

        .message-card {
            background: white;
            border: 1px solid #e9ecef;
            border-radius: 12px;
            margin-bottom: 15px;
            padding: 20px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
            transition: all 0.3s ease;
            position: relative;
        }

        .message-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(0,0,0,0.15);
        }

        .message-card.recent {
            border-left: 5px solid #3498db;
            background: #f8f9ff;
        }

        .message-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 15px;
            flex-wrap: wrap;
            gap: 10px;
        }

        .sender-info {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .sender-avatar {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            background: linear-gradient(135deg, #3498db, #2980b9);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: bold;
            font-size: 1.2rem;
            text-shadow: 1px 1px 2px rgba(0,0,0,0.3);
        }

        .sender-details h3 {
            color: #2c3e50;
            margin-bottom: 5px;
            font-size: 1.1rem;
        }

        .sender-contact {
            font-size: 0.9rem;
            color: #6c757d;
        }

        .message-meta {
            text-align: right;
        }

        .role-badge {
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 8px;
            display: inline-block;
        }

        .role-client {
            background: #e3f2fd;
            color: #1976d2;
        }

        .role-admin {
            background: #fce4ec;
            color: #c2185b;
        }

        .role-personnel {
            background: #e8f5e8;
            color: #388e3c;
        }

        .message-date {
            font-size: 0.9rem;
            color: #6c757d;
        }

        .message-subject {
            font-size: 1.2rem;
            font-weight: bold;
            color: #2c3e50;
            margin-bottom: 12px;
            padding: 8px 0;
            border-bottom: 2px solid #e9ecef;
        }

        .message-content {
            color: #495057;
            line-height: 1.6;
            font-size: 1rem;
            margin-bottom: 15px;
        }

        .message-actions {
            display: flex;
            gap: 10px;
            justify-content: flex-end;
            margin-top: 15px;
            padding-top: 15px;
            border-top: 1px solid #e9ecef;
        }

        .btn {
            padding: 8px 16px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-size: 0.9rem;
            font-weight: 500;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }

        .btn-primary {
            background: #3498db;
            color: white;
        }

        .btn-primary:hover {
            background: #2980b9;
            transform: translateY(-1px);
        }

        .btn-success {
            background: #27ae60;
            color: white;
        }

        .btn-success:hover {
            background: #219a52;
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

        .no-messages {
            text-align: center;
            padding: 60px 20px;
            color: #6c757d;
        }

        .no-messages i {
            font-size: 4rem;
            margin-bottom: 20px;
            opacity: 0.5;
        }

        .priority-high {
            border-left: 5px solid #e74c3c;
        }

        .priority-medium {
            border-left: 5px solid #f39c12;
        }

        .priority-low {
            border-left: 5px solid #27ae60;
        }

        /* Dropdown pour les options de réponse */
        .reply-dropdown {
            position: relative;
            display: inline-block;
        }

        .reply-dropdown-content {
            display: none;
            position: absolute;
            background-color: white;
            min-width: 200px;
            box-shadow: 0px 8px 16px 0px rgba(0,0,0,0.2);
            border-radius: 6px;
            z-index: 1;
            bottom: 100%;
            right: 0;
            border: 1px solid #e9ecef;
        }

        .reply-dropdown-content a {
            color: #495057;
            padding: 12px 16px;
            text-decoration: none;
            display: block;
            transition: background-color 0.3s;
        }

        .reply-dropdown-content a:hover {
            background-color: #f8f9fa;
        }

        .reply-dropdown-content a:first-child {
            border-radius: 6px 6px 0 0;
        }

        .reply-dropdown-content a:last-child {
            border-radius: 0 0 6px 6px;
        }

        .reply-dropdown:hover .reply-dropdown-content {
            display: block;
        }

        @media (max-width: 768px) {
            .stats-bar {
                flex-direction: column;
                gap: 15px;
            }

            .filter-form {
                flex-direction: column;
                align-items: stretch;
            }

            .search-box {
                width: 100%;
            }

            .message-header {
                flex-direction: column;
                align-items: flex-start;
            }

            .message-meta {
                text-align: left;
            }

            .message-actions {
                justify-content: flex-start;
                flex-wrap: wrap;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <a href="logout.php" class="logout-btn">🚪 Déconnexion</a>
            <h1>📧 Messages Reçus</h1>
            <p>Gestion des messages de l'administration</p>
        </div>

        <div class="stats-bar">
            <div class="stat-item">
                <div class="stat-number"><?= $stats['total_messages'] ?></div>
                <div class="stat-label">Total Messages</div>
            </div>
            <div class="stat-item">
                <div class="stat-number"><?= $stats['unread_messages'] ?></div>
                <div class="stat-label">Messages</div>
            </div>
            <div class="stat-item">
                <div class="stat-number"><?= $stats['today_messages'] ?></div>
                <div class="stat-label">Aujourd'hui</div>
            </div>
            <div class="stat-item">
                <div class="stat-number"><?= $stats['client_messages'] ?></div>
                <div class="stat-label">Utilisateurs</div>
            </div>
        </div>

        <div class="filters">
            <form method="GET" class="filter-form">
                <div class="filter-group">
                    <label for="role">Rôle:</label>
                    <select name="role" id="role">
                        <option value="">Tous les rôles</option>
                        <option value="client" <?= $roleFilter === 'client' ? 'selected' : '' ?>>Client</option>
                        <option value="personnel" <?= $roleFilter === 'personnel' ? 'selected' : '' ?>>Personnel</option>
                        <option value="admin" <?= $roleFilter === 'admin' ? 'selected' : '' ?>>Admin</option>
                    </select>
                </div>
                <div class="filter-group">
                    <label for="date">Date:</label>
                    <input type="date" name="date" id="date" value="<?= htmlspecialchars($dateFilter) ?>">
                </div>
                <div class="filter-group">
                    <input type="text" name="search" class="search-box" placeholder="🔍 Rechercher par nom, email ou sujet..." value="<?= htmlspecialchars($searchFilter) ?>">
                </div>
                <button type="submit" class="filter-btn">🔍 Filtrer</button>
            </form>
        </div>

        <div class="messages-container">
            <?php if (empty($messages)): ?>
                <div class="no-messages">
                    <i>📭</i>
                    <h3>Aucun message trouvé</h3>
                    <p>Aucun message ne correspond à vos critères de recherche.</p>
                </div>
            <?php else: ?>
                <?php foreach ($messages as $message): ?>
                    <?php
                    $initials = '';
                    $nameParts = explode(' ', $message['nom']);
                    foreach ($nameParts as $part) {
                        $initials .= strtoupper(substr($part, 0, 1));
                    }
                    
                    $date = new DateTime($message['date_envoi']);
                    $formattedDate = $date->format('d/m/Y H:i');
                    
                    // Déterminer la priorité
                    $priority = 'low';
                    $subject = strtolower($message['sujet']);
                    if (strpos($subject, 'urgent') !== false || strpos($subject, 'réclamation') !== false || strpos($subject, 'problème') !== false) {
                        $priority = 'high';
                    } elseif (strpos($subject, 'question') !== false || strpos($subject, 'demande') !== false) {
                        $priority = 'medium';
                    }
                    
                    $isRecent = (time() - strtotime($message['date_envoi'])) < (24 * 60 * 60); // Messages des dernières 24h
                    
                    // Préparer les liens mailto pour différents clients
                    $emailTo = urlencode($message['email']);
                    $emailSubject = urlencode('Re: ' . $message['sujet']);
                    $emailBody = urlencode("Bonjour " . $message['nom'] . ",\n\nEn réponse à votre message:\n\"" . substr($message['message'], 0, 100) . "...\"\n\nCordialement,\nL'équipe d'administration");
                    
                    $gmailUrl = "https://mail.google.com/mail/?view=cm&fs=1&to={$emailTo}&su={$emailSubject}&body={$emailBody}";
                    $outlookUrl = "https://outlook.live.com/mail/0/deeplink/compose?to={$emailTo}&subject={$emailSubject}&body={$emailBody}";
                    $yahooUrl = "https://compose.mail.yahoo.com/?to={$emailTo}&subject={$emailSubject}&body={$emailBody}";
                    $defaultMailto = "mailto:{$emailTo}?subject={$emailSubject}&body={$emailBody}";
                    ?>
                    <div class="message-card priority-<?= $priority ?> <?= $isRecent ? 'recent' : '' ?>">
                        <div class="message-header">
                            <div class="sender-info">
                                <div class="sender-avatar"><?= $initials ?></div>
                                <div class="sender-details">
                                    <h3><?= htmlspecialchars($message['nom']) ?></h3>
                                    <div class="sender-contact">
                                        📧 <?= htmlspecialchars($message['email']) ?><br>
                                        📱 <?= htmlspecialchars($message['num_tele']) ?>
                                    </div>
                                </div>
                            </div>
                            <div class="message-meta">
                                <span class="role-badge role-<?= $message['role'] ?>"><?= htmlspecialchars($message['role']) ?></span>
                                <div class="message-date"><?= $formattedDate ?></div>
                            </div>
                        </div>
                        
                        <div class="message-subject"><?= htmlspecialchars($message['sujet']) ?></div>
                        <div class="message-content"><?= nl2br(htmlspecialchars($message['message'])) ?></div>
                        
                        <div class="message-actions">
                            <div class="reply-dropdown">
                                <button class="btn btn-primary">
                                    ↩️ Répondre
                                </button>
                                <div class="reply-dropdown-content">
                                    <a href="<?= $defaultMailto ?>" title="Ouvre votre client de messagerie par défaut">
                                        📧 Client par défaut
                                    </a>
                                    <a href="<?= $gmailUrl ?>" target="_blank" title="Ouvrir dans Gmail">
                                        🌐 Gmail (Web)
                                    </a>
                                    <a href="<?= $outlookUrl ?>" target="_blank" title="Ouvrir dans Outlook Web">
                                        🌐 Outlook (Web)
                                    </a>
                                    <a href="<?= $yahooUrl ?>" target="_blank" title="Ouvrir dans Yahoo Mail">
                                        🌐 Yahoo Mail
                                    </a>
                                    <a href="javascript:void(0)" onclick="copyEmailInfo('<?= htmlspecialchars($message['email']) ?>', '<?= htmlspecialchars('Re: ' . $message['sujet']) ?>', '<?= htmlspecialchars($message['nom']) ?>')" title="Copier les informations">
                                        📋 Copier infos
                                    </a>
                                </div>
                            </div>
                            <button class="btn btn-danger" onclick="deleteMessage(<?= $message['id'] ?>)">
                                🗑️ Supprimer
                            </button>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <script>
        // Supprimer message
        function deleteMessage(messageId) {
            if (confirm('Êtes-vous sûr de vouloir supprimer ce message ?')) {
                const formData = new FormData();
                formData.append('action', 'delete_message');
                formData.append('message_id', messageId);
                
                fetch('', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        location.reload();
                    } else {
                        alert('Erreur lors de la suppression');
                    }
                })
                .catch(error => {
                    console.error('Erreur:', error);
                    alert('Erreur lors de la suppression');
                });
            }
        }

        // Copier les informations de contact dans le presse-papiers
        function copyEmailInfo(email, subject, name) {
            const emailInfo = `Email: ${email}\nSujet: ${subject}\nNom: ${name}`;
            
            if (navigator.clipboard) {
                navigator.clipboard.writeText(emailInfo).then(() => {
                    // Créer une notification temporaire
                    showNotification('Informations copiées dans le presse-papiers !');
                }).catch(err => {
                    console.error('Erreur lors de la copie:', err);
                    fallbackCopyText(emailInfo);
                });
            } else {
                fallbackCopyText(emailInfo);
            }
        }

        // Fonction de fallback pour copier le texte
        function fallbackCopyText(text) {
            const textArea = document.createElement('textarea');
            textArea.value = text;
            textArea.style.position = 'fixed';
            textArea.style.left = '-999999px';
            textArea.style.top = '-999999px';
            document.body.appendChild(textArea);
            textArea.focus();
            textArea.select();
            
            try {
                document.execCommand('copy');
                showNotification('Informations copiées dans le presse-papiers !');
            } catch (err) {
                console.error('Erreur lors de la copie:', err);
                alert('Impossible de copier automatiquement. Veuillez copier manuellement:\n' + text);
            }
            
            document.body.removeChild(textArea);
        }

        // Afficher une notification temporaire
        function showNotification(message) {
            const notification = document.createElement('div');
            notification.innerHTML = message;
            notification.style.cssText = `
                position: fixed;
                top: 20px;
                right: 20px;
                background: #27ae60;
                color: white;
                padding: 15px 20px;
                border-radius: 5px;
                box-shadow: 0 4px 12px rgba(0,0,0,0.2);
                z-index: 10000;
                font-family: inherit;
                font-size: 14px;
                animation: slideIn 0.3s ease;
            `;
            
            document.body.appendChild(notification);
            
            setTimeout(() => {
                notification.style.animation = 'slideOut 0.3s ease';
                setTimeout(() => {
                    if (notification.parentNode) {
                        notification.parentNode.removeChild(notification);
                    }
                }, 300);
            }, 3000);
        }

        // Ajouter les animations CSS
        const style = document.createElement('style');
        style.textContent = `
            @keyframes slideIn {
                from { transform: translateX(100%); opacity: 0; }
                to { transform: translateX(0); opacity: 1; }
            }
            @keyframes slideOut {
                from { transform: translateX(0); opacity: 1; }
                to { transform: translateX(100%); opacity: 0; }
            }
        `;
        document.head.appendChild(style);
    </script>
</body>
</html>