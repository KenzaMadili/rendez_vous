<?php
include("../includes/database.php");
session_start();

// Vérification des permissions admin
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit();
}

$messages = [];
$errors = [];

// Suppression d'un utilisateur
if (isset($_GET['delete']) && !empty($_GET['delete'])) {
    $id = intval($_GET['delete']);
    
    // Empêcher la suppression de son propre compte
    if ($id == $_SESSION['user_id']) {
        $errors[] = "Vous ne pouvez pas supprimer votre propre compte.";
    } else {
        // Vérifier si l'utilisateur existe
        $check = mysqli_query($link, "SELECT id, name FROM users WHERE id = $id");
        if (mysqli_num_rows($check) > 0) {
            $user = mysqli_fetch_assoc($check);
            
            // Commencer une transaction
            mysqli_begin_transaction($link);
            
            try {
                // Supprimer les relations dans service_personnel
                mysqli_query($link, "DELETE FROM service_personnel WHERE personnel_id = $id");
                
                // Supprimer les messages de l'utilisateur
                mysqli_query($link, "DELETE FROM messages WHERE user_id = $id");
                
                // Mettre à jour les rendez-vous au lieu de les supprimer
                mysqli_query($link, "UPDATE rendezvous SET status = 'annule' WHERE client_id = $id OR personnel_id = $id");
                
                // Supprimer l'utilisateur
                $result = mysqli_query($link, "DELETE FROM users WHERE id = $id");
                
                if ($result) {
                    mysqli_commit($link);
                    $messages[] = "Utilisateur '{$user['name']}' supprimé avec succès.";
                } else {
                    throw new Exception("Erreur lors de la suppression");
                }
            } catch (Exception $e) {
                mysqli_rollback($link);
                $errors[] = "Erreur lors de la suppression de l'utilisateur.";
            }
        } else {
            $errors[] = "Utilisateur introuvable.";
        }
    }
}

// Mise à jour du rôle
if (isset($_POST['update_role'])) {
    $id = intval($_POST['user_id']);
    $new_role = mysqli_real_escape_string($link, $_POST['new_role']);
    
    // Empêcher de changer son propre rôle
    if ($id == $_SESSION['user_id']) {
        $errors[] = "Vous ne pouvez pas modifier votre propre rôle.";
    } else {
        $valid_roles = ['admin', 'personnel', 'client'];
        if (in_array($new_role, $valid_roles)) {
            $result = mysqli_query($link, "UPDATE users SET role = '$new_role' WHERE id = $id");
            if ($result) {
                $messages[] = "Rôle mis à jour avec succès.";
                
                // Si on change de personnel vers autre chose, désactiver ses services
                if ($new_role !== 'personnel') {
                    mysqli_query($link, "UPDATE service_personnel SET is_active = 0 WHERE personnel_id = $id");
                }
            } else {
                $errors[] = "Erreur lors de la mise à jour du rôle.";
            }
        } else {
            $errors[] = "Rôle invalide.";
        }
    }
}

// Mise à jour du service d'un personnel
if (isset($_POST['update_service'])) {
    $personnel_id = intval($_POST['personnel_id']);
    $new_service_id = intval($_POST['service_id']);
    
    mysqli_begin_transaction($link);
    
    try {
        // Désactiver tous les services actuels
        mysqli_query($link, "UPDATE service_personnel SET is_active = 0 WHERE personnel_id = $personnel_id");
        
        // Ajouter le nouveau service si spécifié
        if ($new_service_id > 0) {
            // Vérifier si cette association existe déjà
            $existing = mysqli_query($link, "SELECT id FROM service_personnel WHERE service_id = $new_service_id AND personnel_id = $personnel_id");
            
            if (mysqli_num_rows($existing) > 0) {
                // Réactiver l'association existante
                mysqli_query($link, "UPDATE service_personnel SET is_active = 1 WHERE service_id = $new_service_id AND personnel_id = $personnel_id");
            } else {
                // Créer une nouvelle association
                mysqli_query($link, "INSERT INTO service_personnel (service_id, personnel_id, is_active, created_at) VALUES ($new_service_id, $personnel_id, 1, NOW())");
            }
        }
        
        mysqli_commit($link);
        $messages[] = "Service mis à jour avec succès.";
    } catch (Exception $e) {
        mysqli_rollback($link);
        $errors[] = "Erreur lors de la mise à jour du service.";
    }
}

// Ajout d'un utilisateur
if (isset($_POST['add_user'])) {
    $name = trim(mysqli_real_escape_string($link, $_POST['name']));
    $email = trim(mysqli_real_escape_string($link, $_POST['email']));
    $password = $_POST['password'];
    $role = mysqli_real_escape_string($link, $_POST['role']);
    $service_id = isset($_POST['service_id']) ? intval($_POST['service_id']) : 0;
    
    // Validations
    if (strlen($name) < 2) {
        $errors[] = "Le nom doit contenir au moins 2 caractères.";
    }
    
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "L'adresse email n'est pas valide.";
    }
    
    if (strlen($password) < 6) {
        $errors[] = "Le mot de passe doit contenir au moins 6 caractères.";
    }
    
    $valid_roles = ['admin', 'personnel', 'client'];
    if (!in_array($role, $valid_roles)) {
        $errors[] = "Rôle invalide.";
    }
    
    // Vérifier l'unicité de l'email
    $check_email = mysqli_query($link, "SELECT id FROM users WHERE email = '$email'");
    if (mysqli_num_rows($check_email) > 0) {
        $errors[] = "Cet email est déjà utilisé.";
    }
    
    // Si pas d'erreurs, procéder à l'insertion
    if (empty($errors)) {
        $password_hash = password_hash($password, PASSWORD_DEFAULT);
        
        mysqli_begin_transaction($link);
        
        try {
            // Insertion utilisateur
            $insert_user = mysqli_query($link, "INSERT INTO users (name, email, password, role, created_at) VALUES ('$name', '$email', '$password_hash', '$role', NOW())");
            
            if ($insert_user) {
                $new_user_id = mysqli_insert_id($link);
                
                // Si c'est un personnel, associer un service
                if ($role === 'personnel' && $service_id > 0) {
                    mysqli_query($link, "INSERT INTO service_personnel (service_id, personnel_id, is_active, created_at) VALUES ($service_id, $new_user_id, 1, NOW())");
                }
                
                mysqli_commit($link);
                $messages[] = "Utilisateur '$name' ajouté avec succès.";
            } else {
                throw new Exception("Erreur lors de l'insertion");
            }
        } catch (Exception $e) {
            mysqli_rollback($link);
            $errors[] = "Erreur lors de l'ajout de l'utilisateur.";
        }
    }
}

// Récupérer les services pour les formulaires
$services_query = mysqli_query($link, "SELECT id, name FROM services WHERE is_active = 1 ORDER BY name");
$service_options = "";
while ($srv = mysqli_fetch_assoc($services_query)) {
    $service_options .= "<option value='{$srv['id']}'>{$srv['name']}</option>";
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion des Utilisateurs - Admin</title>
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
            max-width: 1200px;
            margin: 0 auto;
        }
        
        h1 {
            text-align: center;
            color: white;
            margin-bottom: 2rem;
            font-size: 2.5rem;
            text-shadow: 2px 2px 4px rgba(0,0,0,0.3);
        }
        
        h2 {
            color: #2c3e50;
            margin: 2rem 0 1rem 0;
            padding: 10px;
            background: rgba(255,255,255,0.9);
            border-radius: 8px;
            text-align: center;
        }
        
        .alert {
            padding: 15px;
            margin: 10px 0;
            border-radius: 8px;
            font-weight: 500;
        }
        
        .alert-success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .alert-danger {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        .table-container {
            background: white;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            margin: 20px 0;
            overflow: hidden;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
        }
        
        th {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 15px 10px;
            font-weight: 600;
            text-align: center;
        }
        
        td {
            padding: 12px 10px;
            text-align: center;
            border-bottom: 1px solid #eee;
        }
        
        tr:hover {
            background-color: #f8f9fa;
        }
        
        .btn {
            padding: 8px 15px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 12px;
            font-weight: 500;
            text-decoration: none;
            display: inline-block;
            margin: 2px;
            transition: all 0.3s ease;
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
            background: #e67e22;
        }
        
        .btn-primary {
            background: #3498db;
            color: white;
        }
        
        .btn-success {
            background: #27ae60;
            color: white;
        }
        
        .btn-success:hover {
            background: #229954;
            transform: translateY(-2px);
        }
        
        .add-form {
            background: white;
            padding: 30px;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            margin: 30px 0;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 5px;
            color: #2c3e50;
            font-weight: 500;
        }
        
        .form-group input,
        .form-group select {
            width: 100%;
            padding: 12px;
            border: 2px solid #e1e5e9;
            border-radius: 8px;
            font-size: 14px;
            transition: border-color 0.3s ease;
        }
        
        .form-group input:focus,
        .form-group select:focus {
            outline: none;
            border-color: #667eea;
        }
        
        .form-inline {
            display: inline-block;
            margin: 2px;
        }
        
        .form-inline select {
            width: auto;
            padding: 5px;
            margin-right: 5px;
        }
        
        .stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background: white;
            padding: 20px;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            text-align: center;
        }
        
        .stat-number {
            font-size: 2rem;
            font-weight: bold;
            color: #667eea;
        }
        
        .stat-label {
            color: #666;
            margin-top: 5px;
        }
        
        .badge {
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: bold;
            text-transform: uppercase;
        }
        
        .badge-admin { background: #e74c3c; color: white; }
        .badge-personnel { background: #3498db; color: white; }
        .badge-client { background: #27ae60; color: white; }
        
        @media (max-width: 768px) {
            .table-container {
                overflow-x: auto;
            }
            
            table {
                min-width: 600px;
            }
            
            h1 {
                font-size: 1.8rem;
            }
        }
    </style>
</head>
<body>

<div class="container">
    <h1>🛠️ Gestion des Utilisateurs</h1>

    <!-- Messages de succès et d'erreur -->
    <?php foreach ($messages as $message): ?>
        <div class="alert alert-success">✅ <?= htmlspecialchars($message) ?></div>
    <?php endforeach; ?>

    <?php foreach ($errors as $error): ?>
        <div class="alert alert-danger">❌ <?= htmlspecialchars($error) ?></div>
    <?php endforeach; ?>

    <!-- Statistiques -->
    <div class="stats">
        <?php
        $admin_count = mysqli_num_rows(mysqli_query($link, "SELECT id FROM users WHERE role='admin'"));
        $personnel_count = mysqli_num_rows(mysqli_query($link, "SELECT id FROM users WHERE role='personnel'"));
        $client_count = mysqli_num_rows(mysqli_query($link, "SELECT id FROM users WHERE role='client'"));
        ?>
        <div class="stat-card">
            <div class="stat-number"><?= $admin_count ?></div>
            <div class="stat-label">Administrateurs</div>
        </div>
        <div class="stat-card">
            <div class="stat-number"><?= $personnel_count ?></div>
            <div class="stat-label">Personnel</div>
        </div>
        <div class="stat-card">
            <div class="stat-number"><?= $client_count ?></div>
            <div class="stat-label">Clients</div>
        </div>
    </div>

    <!-- Admins -->
    <div class="table-container">
        <h2>🔐 Administrateurs</h2>
        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Nom</th>
                    <th>Email</th>
                    <th>Créé le</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php
                $res = mysqli_query($link, "SELECT * FROM users WHERE role='admin' ORDER BY id DESC");
                while ($row = mysqli_fetch_assoc($res)) {
                    $is_current_user = ($row['id'] == $_SESSION['user_id']);
                    echo "<tr" . ($is_current_user ? " style='background-color: #e8f5e8;'" : "") . ">
                        <td>{$row['id']}</td>
                        <td>{$row['name']}" . ($is_current_user ? " <strong>(Vous)</strong>" : "") . "</td>
                        <td>{$row['email']}</td>
                        <td>" . date('d/m/Y H:i', strtotime($row['created_at'])) . "</td>
                        <td>";
                    
                    if (!$is_current_user) {
                        echo "<a href='?delete={$row['id']}' onclick=\"return confirm('Êtes-vous sûr de vouloir supprimer cet administrateur ?')\" class='btn btn-danger'>🗑️ Supprimer</a>";
                    } else {
                        echo "<span class='badge badge-admin'>Votre compte</span>";
                    }
                    
                    echo "</td></tr>";
                }
                ?>
            </tbody>
        </table>
    </div>

    <!-- Personnel -->
    <div class="table-container">
        <h2>👨‍⚕️ Personnel</h2>
        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Nom</th>
                    <th>Email</th>
                    <th>Service</th>
                    <th>Créé le</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php
                $res = mysqli_query($link, "
                    SELECT u.*, s.name AS service_name, s.id AS current_service_id
                    FROM users u
                    LEFT JOIN service_personnel sp ON u.id = sp.personnel_id AND sp.is_active = 1
                    LEFT JOIN services s ON sp.service_id = s.id
                    WHERE u.role='personnel'
                    ORDER BY u.id DESC
                ");
                
                while ($row = mysqli_fetch_assoc($res)) {
                    $service_name = $row['service_name'] ?? "<em style='color: #999;'>Aucun service</em>";
                    echo "<tr>
                        <td>{$row['id']}</td>
                        <td>{$row['name']}</td>
                        <td>{$row['email']}</td>
                        <td>
                            <form method='POST' class='form-inline'>
                                <input type='hidden' name='personnel_id' value='{$row['id']}'>
                                <select name='service_id'>";
                    
                    echo "<option value=''>-- Aucun service --</option>";
                    mysqli_data_seek($services_query, 0);
                    while ($srv = mysqli_fetch_assoc($services_query)) {
                        $selected = ($srv['id'] == $row['current_service_id']) ? "selected" : "";
                        echo "<option value='{$srv['id']}' $selected>{$srv['name']}</option>";
                    }
                    
                    echo "          </select>
                                <button type='submit' name='update_service' class='btn btn-warning'>Modifier</button>
                            </form>
                        </td>
                        <td>" . date('d/m/Y H:i', strtotime($row['created_at'])) . "</td>
                        <td>
                            <a href='?delete={$row['id']}' onclick=\"return confirm('Supprimer ce membre du personnel ?')\" class='btn btn-danger'>🗑️ Supprimer</a>
                        </td>
                    </tr>";
                }
                ?>
            </tbody>
        </table>
    </div>

    <!-- Clients -->
    <div class="table-container">
        <h2>👥 Clients</h2>
        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Nom</th>
                    <th>Email</th>
                    <th>Créé le</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php
                $res = mysqli_query($link, "SELECT * FROM users WHERE role='client' ORDER BY id DESC");
                while ($row = mysqli_fetch_assoc($res)) {
                    echo "<tr>
                        <td>{$row['id']}</td>
                        <td>{$row['name']}</td>
                        <td>{$row['email']}</td>
                        <td>" . date('d/m/Y H:i', strtotime($row['created_at'])) . "</td>
                        <td>
                            <a href='?delete={$row['id']}' onclick=\"return confirm('Supprimer ce client ?')\" class='btn btn-danger'>🗑️ Supprimer</a>
                        </td>
                    </tr>";
                }
                ?>
            </tbody>
        </table>
    </div>

    <!-- Formulaire d'ajout -->
    <div class="add-form">
        <h2>➕ Ajouter un nouvel utilisateur</h2>
        <form method="POST">
            <div class="form-group">
                <label for="name">Nom complet *</label>
                <input type="text" id="name" name="name" required minlength="2">
            </div>
            
            <div class="form-group">
                <label for="email">Adresse e-mail *</label>
                <input type="email" id="email" name="email" required>
            </div>
            
            <div class="form-group">
                <label for="password">Mot de passe *</label>
                <input type="password" id="password" name="password" required minlength="6">
            </div>
            
            <div class="form-group">
                <label for="role">Rôle *</label>
                <select name="role" id="roleSelect" onchange="toggleServiceSelect()" required>
                    <option value="">-- Sélectionner un rôle --</option>
                    <option value="admin">Administrateur</option>
                    <option value="personnel">Personnel</option>
                    <option value="client">Client</option>
                </select>
            </div>
            
            <div class="form-group" id="serviceGroup" style="display: none;">
                <label for="service_id">Service (pour le personnel)</label>
                <select name="service_id" id="serviceSelect">
                    <option value="">-- Sélectionner un service --</option>
                    <?= $service_options ?>
                </select>
            </div>
            
            <button type="submit" name="add_user" class="btn btn-success">
                ➕ Ajouter l'utilisateur
            </button>
        </form>
    </div>
</div>

<script>
function toggleServiceSelect() {
    const role = document.getElementById('roleSelect').value;
    const serviceGroup = document.getElementById('serviceGroup');
    serviceGroup.style.display = (role === 'personnel') ? 'block' : 'none';
}

// Confirmation de suppression améliorée
document.querySelectorAll('a.btn-danger').forEach(function(link) {
    link.addEventListener('click', function(e) {
        if (!confirm('⚠️ Cette action est irréversible. Êtes-vous absolument sûr de vouloir supprimer cet utilisateur ?')) {
            e.preventDefault();
        }
    });
});
</script>

</body>
</html>