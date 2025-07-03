<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Prendre Rendez-vous - Am Soft</title>
  <?php 
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['name']) || empty($_SESSION['name']) || !isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit();
}

try {
    $pdo = new PDO("mysql:host=localhost;dbname=rendezvs;charset=utf8mb4", "root", "");
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Erreur de connexion à la base de données: " . $e->getMessage());
}

$userName = htmlspecialchars($_SESSION['name'], ENT_QUOTES, 'UTF-8');
$userId = $_SESSION['user_id'];

$stmt = $pdo->prepare("SELECT * FROM services WHERE is_active = 1 ORDER BY name");
$stmt->execute();
$services = $stmt->fetchAll(PDO::FETCH_ASSOC);

$personnel_by_service = [];
foreach ($services as $service) {
    $stmt = $pdo->prepare("SELECT u.id, u.name FROM users u JOIN service_personnel sp ON u.id = sp.personnel_id WHERE sp.service_id = ? AND sp.is_active = 1");
    $stmt->execute([$service['id']]);
    $personnel_by_service[$service['id']] = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function createDefaultAvailabilities($pdo, $personnel_id, $date) {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM disponibilites WHERE personnel_id = ? AND date = ?");
    $stmt->execute([$personnel_id, $date]);
    if ($stmt->fetchColumn() == 0) {
        $stmt = $pdo->prepare("INSERT INTO disponibilites (personnel_id, date, heure_debut, heure_fin, is_available, created_at) VALUES (?, ?, '08:00:00', '12:00:00', 1, NOW()), (?, ?, '14:00:00', '18:00:00', 1, NOW())");
        $stmt->execute([$personnel_id, $date, $personnel_id, $date]);
        echo "<!-- DEBUG: Disponibilités par défaut créées pour le personnel $personnel_id le $date -->";
        return true;
    }
    return false;
}

function checkNoConflictWithExistingAppointments($pdo, $personnel_id, $date, $heure_debut, $duree_minutes) {
    $heure_fin = date('H:i:s', strtotime($heure_debut) + ($duree_minutes * 60));
    echo "<!-- DEBUG CONFLICT: Vérification conflits pour $heure_debut-$heure_fin ($duree_minutes min) -->";
    $stmt = $pdo->prepare("SELECT COUNT(*) as conflicts, GROUP_CONCAT(CONCAT(r.heure, ' (', COALESCE(r.duration, s.duration), 'min)')) as existing_appointments FROM rendezvous r LEFT JOIN services s ON r.service_id = s.id WHERE r.personnel_id = ? AND r.date = ? AND r.status != 'cancelled' AND (TIME(?) < ADDTIME(TIME(r.heure), SEC_TO_TIME(COALESCE(r.duration, s.duration) * 60)) AND ADDTIME(TIME(?), SEC_TO_TIME(? * 60)) > TIME(r.heure))");
    $stmt->execute([$personnel_id, $date, $heure_debut, $heure_debut, $duree_minutes]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $conflicts = $result['conflicts'];
    echo "<!-- DEBUG CONFLICT: Conflits trouvés: $conflicts -->";
    if ($conflicts > 0) {
        echo "<!-- DEBUG CONFLICT: RDV existants en conflit: " . $result['existing_appointments'] . " -->";
    }
    return $conflicts == 0;
}

function checkPersonnelAvailability($pdo, $personnel_id, $date, $heure_debut, $duree_minutes) {
    $heure_fin = date('H:i:s', strtotime($heure_debut) + ($duree_minutes * 60));
    echo "<!-- DEBUG DISPO: Vérification pour personnel_id=$personnel_id, date=$date, heure_debut=$heure_debut, heure_fin=$heure_fin, durée=$duree_minutes min -->";
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM disponibilites d WHERE d.personnel_id = ? AND d.date = ?");
    $stmt->execute([$personnel_id, $date]);
    $dispoCount = $stmt->fetchColumn();
    echo "<!-- DEBUG DISPO: Nombre de disponibilités trouvées: $dispoCount -->";
    if ($dispoCount == 0) {
        echo "<!-- DEBUG DISPO: Création de disponibilités par défaut -->";
        createDefaultAvailabilities($pdo, $personnel_id, $date);
    }
    $stmt = $pdo->prepare("SELECT COUNT(*) as count, GROUP_CONCAT(CONCAT(TIME(heure_debut), '-', TIME(heure_fin), '(', is_available, ')')) as slots FROM disponibilites d WHERE d.personnel_id = ? AND d.date = ? AND d.is_available = 1 AND TIME(?) >= TIME(d.heure_debut) AND TIME(?) <= TIME(d.heure_fin)");
    $stmt->execute([$personnel_id, $date, $heure_debut, $heure_fin]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $disponible = $result['count'] > 0;
    echo "<!-- DEBUG DISPO: Créneaux de disponibilité: " . ($result['slots'] ?: 'aucun') . " -->";
    echo "<!-- DEBUG DISPO: Créneau $heure_debut-$heure_fin couvert: " . ($disponible ? 'OUI' : 'NON') . " -->";
    if (!$disponible) {
        $stmt = $pdo->prepare("SELECT TIME(heure_debut) as debut, TIME(heure_fin) as fin, is_available FROM disponibilites d WHERE d.personnel_id = ? AND d.date = ? ORDER BY d.heure_debut");
        $stmt->execute([$personnel_id, $date]);
        $all_dispos = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo "<!-- DEBUG DISPO: Toutes les disponibilités: " . json_encode($all_dispos) . " -->";
        return false;
    }
    $no_conflict = checkNoConflictWithExistingAppointments($pdo, $personnel_id, $date, $heure_debut, $duree_minutes);
    echo "<!-- DEBUG DISPO: Pas de conflit: " . ($no_conflict ? 'OUI' : 'NON') . " -->";
    return $no_conflict;
}

function calculatePrice($base_price, $base_duration, $selected_duration) {
    return ($base_price / $base_duration) * $selected_duration;
}

$success_message = '';
$error_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $service_id = $_POST['service_id'] ?? '';
    $personnel_id = $_POST['personnel_id'] ?? '';
    $duration = $_POST['duration'] ?? '';
    $date = $_POST['date'] ?? '';
    $heure = $_POST['heure'] ?? '';
    $notes = $_POST['notes'] ?? '';
    echo "<!-- DEBUG POST: service_id=$service_id, personnel_id=$personnel_id, duration=$duration, date=$date, heure=$heure -->";
    if (empty($service_id) || empty($personnel_id) || empty($duration) || empty($date) || empty($heure)) {
        $error_message = "Veuillez remplir tous les champs obligatoires.";
    } else {
        if (strtotime($date) < strtotime(date('Y-m-d'))) {
            $error_message = "La date ne peut pas être dans le passé.";
        } else if (!preg_match('/^([01]?[0-9]|2[0-3]):[0-5][0-9]$/', $heure)) {
            $error_message = "Format d'heure invalide. Utilisez le format HH:MM.";
        } else {
            try {
                $stmt = $pdo->prepare("SELECT name, price, duration FROM services WHERE id = ? AND is_active = 1");
                $stmt->execute([$service_id]);
                $service_info = $stmt->fetch(PDO::FETCH_ASSOC);
                if (!$service_info) {
                    $error_message = "Service introuvable ou inactif.";
                } else {
                    $stmt = $pdo->prepare("SELECT COUNT(*) FROM service_personnel WHERE service_id = ? AND personnel_id = ? AND is_active = 1");
                    $stmt->execute([$service_id, $personnel_id]);
                    if ($stmt->fetchColumn() == 0) {
                        $error_message = "Ce praticien n'est pas assigné à ce service.";
                    } else {
                        $calculated_price = calculatePrice($service_info['price'], $service_info['duration'], $duration);
                        echo "<!-- DEBUG: Prix calculé: $calculated_price pour durée $duration min (base: {$service_info['price']}€ pour {$service_info['duration']}min) -->";
                        debugPersonnelAvailability($pdo, $personnel_id, $date);
                        if (!checkPersonnelAvailability($pdo, $personnel_id, $date, $heure, $duration)) {
                            $error_message = "Ce créneau n'est pas disponible pour le praticien sélectionné.";
                        } else {
                            $stmt = $pdo->prepare("INSERT INTO rendezvous (client_id, personnel_id, service_id, date, heure, prix, duration, status, notes, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, 'pending', ?, NOW(), NOW())");
                            if ($stmt->execute([$userId, $personnel_id, $service_id, $date, $heure, $calculated_price, $duration, $notes])) {
                                $success_message = "Votre rendez-vous a été pris avec succès ! Service: " . htmlspecialchars($service_info['name']) . " - Durée: " . $duration . " min - Prix: " . number_format($calculated_price, 2) . "€";
                                $service_id = $personnel_id = $duration = $date = $heure = $notes = '';
                            } else {
                                $error_message = "Erreur lors de l'insertion du rendez-vous.";
                            }
                        }
                    }
                }
            } catch (PDOException $e) {
                $error_message = "Erreur lors de la prise de rendez-vous : " . $e->getMessage();
                echo "<!-- DEBUG: Erreur PDO: " . $e->getMessage() . " -->";
            }
        }
    }
}

function getAvailableSlots($pdo, $personnel_id, $date) {
    $stmt = $pdo->prepare("SELECT TIME(heure_debut) as heure_debut, TIME(heure_fin) as heure_fin FROM disponibilites d WHERE d.personnel_id = ? AND d.date = ? AND d.is_available = 1 ORDER BY d.heure_debut");
    $stmt->execute([$personnel_id, $date]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function debugPersonnelAvailability($pdo, $personnel_id, $date) {
    echo "<!-- DEBUG COMPLET pour personnel_id=$personnel_id, date=$date -->";
    $stmt = $pdo->prepare("SELECT d.id, TIME(d.heure_debut) as debut, TIME(d.heure_fin) as fin, d.is_available FROM disponibilites d WHERE d.personnel_id = ? AND d.date = ? ORDER BY d.heure_debut");
    $stmt->execute([$personnel_id, $date]);
    $dispos = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo "<!-- DISPONIBILITÉS: " . json_encode($dispos) . " -->";
    $stmt = $pdo->prepare("SELECT r.id, TIME(r.heure) as heure, COALESCE(r.duration, s.duration) as duree, r.status FROM rendezvous r LEFT JOIN services s ON r.service_id = s.id WHERE r.personnel_id = ? AND r.date = ? ORDER BY r.heure");
    $stmt->execute([$personnel_id, $date]);
    $rdvs = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo "<!-- RDV EXISTANTS: " . json_encode($rdvs) . " -->";
}
?>

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
            background: #f8fafc;
        }

        /* Header */
        header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 2rem 0;
            text-align: center;
            position: relative;
            overflow: hidden;
        }

        header::before {
            content: '';
            position: absolute;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><circle cx="50" cy="50" r="2" fill="rgba(255,255,255,0.1)"/></svg>') repeat;
            animation: float 20s ease-in-out infinite;
        }

        .header-content {
            position: relative;
            z-index: 2;
        }

        header h1 {
            font-size: 2.5rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
        }

        header p {
            font-size: 1.2rem;
            opacity: 0.9;
        }

        .back-btn {
            position: absolute;
            top: 2rem;
            left: 2rem;
            background: rgba(255, 255, 255, 0.2);
            color: white;
            padding: 0.5rem 1rem;
            border-radius: 10px;
            text-decoration: none;
            transition: all 0.3s ease;
            z-index: 3;
        }

        .back-btn:hover {
            background: rgba(255, 255, 255, 0.3);
            transform: translateY(-2px);
            text-decoration: none;
            color: white;
        }

        /* Main content */
        .container {
            max-width: 800px;
            margin: 0 auto;
            padding: 3rem 2rem;
        }

        .form-container {
            background: white;
            border-radius: 20px;
            padding: 3rem;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.1);
            margin-bottom: 2rem;
        }

        .form-group {
            margin-bottom: 2rem;
        }

        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 600;
            color: #333;
        }

        .form-group .required {
            color: #e74c3c;
        }

        .form-control {
            width: 100%;
            padding: 1rem;
            border: 2px solid #e1e8ed;
            border-radius: 10px;
            font-size: 1rem;
            transition: all 0.3s ease;
            background: #f8fafc;
        }

        .form-control:focus {
            outline: none;
            border-color: #667eea;
            background: white;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }

        .form-control:disabled {
            background: #e9ecef;
            color: #6c757d;
            cursor: not-allowed;
        }

        select.form-control {
            cursor: pointer;
        }

        select.form-control:disabled {
            cursor: not-allowed;
        }

        textarea.form-control {
            resize: vertical;
            min-height: 100px;
        }

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1.5rem;
        }

        .btn {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 1rem 2rem;
            border: none;
            border-radius: 10px;
            font-size: 1.1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            width: 100%;
        }

        .btn:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 25px rgba(102, 126, 234, 0.4);
        }

        .btn:active {
            transform: translateY(-1px);
        }

        .btn:disabled {
            background: #6c757d;
            cursor: not-allowed;
            transform: none;
            box-shadow: none;
        }

        /* Messages */
        .alert {
            padding: 1rem;
            border-radius: 10px;
            margin-bottom: 2rem;
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

        .service-info {
            background: #e3f2fd;
            padding: 1rem;
            border-radius: 10px;
            margin-top: 0.5rem;
            font-size: 0.9rem;
            color: #1565c0;
        }

        .personnel-info {
            background: #f3e5f5;
            padding: 0.8rem;
            border-radius: 8px;
            margin-top: 0.5rem;
            font-size: 0.9rem;
            color: #4a148c;
        }

        /* Récapitulatif du prix */
        .price-summary {
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
            color: white;
            padding: 1.5rem;
            border-radius: 15px;
            margin: 2rem 0;
            text-align: center;
            box-shadow: 0 8px 25px rgba(40, 167, 69, 0.3);
            opacity: 0;
            transform: translateY(20px);
            transition: all 0.5s ease;
        }

        .price-summary.show {
            opacity: 1;
            transform: translateY(0);
        }

        .price-summary h3 {
            margin-bottom: 0.5rem;
            font-size: 1.3rem;
        }

        .price-amount {
            font-size: 2.5rem;
            font-weight: 700;
            margin: 0.5rem 0;
            text-shadow: 0 2px 4px rgba(0,0,0,0.2);
        }

        .price-details {
            font-size: 0.95rem;
            opacity: 0.9;
            margin-top: 0.5rem;
        }

        /* Animations */
        @keyframes float {
            0%, 100% { transform: translateY(0px) rotate(0deg); }
            50% { transform: translateY(-20px) rotate(180deg); }
        }

        .form-container {
            opacity: 0;
            transform: translateY(30px);
            animation: slideIn 0.6s ease forwards;
        }

        @keyframes slideIn {
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        /* States */
        .hidden {
            display: none;
        }

        .fade-in {
            animation: fadeIn 0.3s ease;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }

        /* Responsive */
        @media (max-width: 768px) {
            .container {
                padding: 2rem 1rem;
            }

            .form-container {
                padding: 2rem;
            }

            .form-row {
                grid-template-columns: 1fr;
                gap: 1rem;
            }

            header h1 {
                font-size: 2rem;
            }

            .back-btn {
                position: static;
                display: inline-block;
                margin-bottom: 1rem;
            }

            .price-amount {
                font-size: 2rem;
            }
        }
    </style>
</head>
<body>
    <header>
        <a href="dashboard.php" class="back-btn">← Retour au tableau de bord</a>
        <div class="header-content">
            <h1>Prendre Rendez-vous</h1>
            <p>Choisissez d'abord votre service, puis le personnel disponible</p>
        </div>
    </header>

    <div class="container">
        <?php if ($success_message): ?>
            <div class="alert alert-success">
                <?= $success_message ?>
            </div>
        <?php endif; ?>

        <?php if ($error_message): ?>
            <div class="alert alert-error">
                <?= $error_message ?>
            </div>
        <?php endif; ?>

        <div class="form-container">
            <form method="POST" action="">
                <!-- Étape 1: Choix du service -->
                <div class="form-group">
                    <label for="service_id">1. Choisissez votre service <span class="required">*</span></label>
                    <select name="service_id" id="service_id" class="form-control" required>
                        <option value="">Sélectionnez un service</option>
                        <?php foreach ($services as $service): ?>
                            <option value="<?= $service['id'] ?>" 
                                    data-duration="<?= $service['duration'] ?>" 
                                    data-price="<?= $service['price'] ?>"
                                    data-name="<?= htmlspecialchars($service['name']) ?>"
                                    data-description="<?= htmlspecialchars($service['description']) ?>"
                                    <?= (isset($_POST['service_id']) && $_POST['service_id'] == $service['id']) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($service['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <div id="service-info" class="service-info hidden">
                        <div id="service-details"></div>
                    </div>
                </div>

                <!-- Étape 2: Choix du personnel -->
                <div class="form-group">
                    <label for="personnel_id">2. Choisissez votre praticien <span class="required">*</span></label>
                    <select name="personnel_id" id="personnel_id" class="form-control" required disabled>
                        <option value="">Sélectionnez d'abord un service</option>
                    </select>
                    <div id="personnel-info" class="personnel-info hidden">
                        Sélectionnez un service pour voir les praticiens disponibles
                    </div>
                </div>

                <!-- Étape 3: Choix de la durée -->
                <div class="form-group">
                    <label for="duration">3. Choisissez la durée <span class="required">*</span></label>
                    <select name="duration" id="duration" class="form-control" required disabled>
                        <option value="">Sélectionnez d'abord un service</option>
                    </select>
                    <div id="duration-info" class="service-info hidden">
                        <div id="duration-details">Sélectionnez un service pour voir les durées disponibles</div>
                    </div>
                </div>

                <!-- Récapitulatif du prix -->
                <div id="price-summary" class="price-summary hidden">
                    <h3>💰 Récapitulatif de votre rendez-vous</h3>
                    <div class="price-amount" id="price-amount">0€</div>
                    <div class="price-details" id="price-details">
                        Sélectionnez un service pour voir le prix
                    </div>
                </div>

                <!-- Étape 4: Date et heure -->
                <div class="form-row">
                    <div class="form-group">
                        <label for="date">4. Date souhaitée <span class="required">*</span></label>
                        <input type="date" name="date" id="date" class="form-control" 
                               min="<?= date('Y-m-d') ?>" 
                               value="<?= isset($_POST['date']) ? $_POST['date'] : '' ?>" required>
                    </div>

                    <div class="form-group">
                        <label for="heure">Heure <span class="required">*</span></label>
                        <select name="heure" id="heure" class="form-control" required disabled>
                            <option value="">Sélectionnez d'abord un praticien et une date</option>
                        </select>
                    </div>
                </div>

                <!-- Étape 5: Notes optionnelles -->
                <div class="form-group">
                    <label for="notes">5. Notes et demandes spéciales (optionnel)</label>
                    <textarea name="notes" id="notes" class="form-control" 
                              placeholder="Précisions particulières, demandes spéciales, préférences..."><?= isset($_POST['notes']) ? htmlspecialchars($_POST['notes']) : '' ?></textarea>
                </div>

                <button type="submit" class="btn" id="submit-btn" disabled>
                    📅 Confirmer le rendez-vous
                </button>
            </form>
        </div>
    </div>

    <script>
        // Données du personnel par service (passées depuis PHP)
        const personnelByService = <?= json_encode($personnel_by_service) ?>;
        
        document.addEventListener('DOMContentLoaded', function() {
            const serviceSelect = document.getElementById('service_id');
            const personnelSelect = document.getElementById('personnel_id');
            const durationSelect = document.getElementById('duration');
            const dateInput = document.getElementById('date');
            const heureSelect = document.getElementById('heure');
            const serviceInfo = document.getElementById('service-info');
            const serviceDetails = document.getElementById('service-details');
            const personnelInfo = document.getElementById('personnel-info');
            const durationInfo = document.getElementById('duration-info');
            const durationDetails = document.getElementById('duration-details');
            const submitBtn = document.getElementById('submit-btn');
            const priceSummary = document.getElementById('price-summary');
            const priceAmount = document.getElementById('price-amount');
            const priceDetails = document.getElementById('price-details');
            const form = document.querySelector('form');

            // Fonction pour calculer le prix exact selon la durée
            function calculatePrice(basePrice, baseDuration, selectedDuration) {
                return (parseFloat(basePrice) / parseInt(baseDuration)) * parseInt(selectedDuration);
            }

            // Fonction pour générer les options de durée
            function generateDurationOptions(baseDuration) {
                const options = [];
                const baseDur = parseInt(baseDuration);
                
                // Générer des options de 30 min à 180 min par intervalles de 30 min
                for (let duration = 30; duration <= 180; duration += 30) {
                    options.push(duration);
                }
                
                // Ajouter aussi la durée de base si elle n'est pas dans la liste
                if (!options.includes(baseDur)) {
                    options.push(baseDur);
                    options.sort((a, b) => a - b);
                }
                
                return options;
            }

            // Fonction pour charger les créneaux disponibles
            function loadAvailableSlots() {
                const personnelId = personnelSelect.value;
                const selectedDate = dateInput.value;
                
                if (!personnelId || !selectedDate) {
                    heureSelect.innerHTML = '<option value="">Sélectionnez d\'abord un praticien et une date</option>';
                    heureSelect.disabled = true;
                    return;
                }
                
                // Ici, vous devriez faire un appel AJAX pour récupérer les créneaux disponibles
                // Pour le moment, je génère des créneaux statiques
                heureSelect.innerHTML = '<option value="">Choisissez une heure</option>';
                
                // Générer les créneaux horaires de 8h à 18h
                for (let h = 8; h <= 18; h++) {
                    for (let m = 0; m < 60; m += 30) {
                        const time = String(h).padStart(2, '0') + ':' + String(m).padStart(2, '0');
                        const option = document.createElement('option');
                        option.value = time;
                        option.textContent = time;
                        
                        // Maintenir la sélection si c'est un POST
                        if (<?= json_encode($_POST['heure'] ?? '') ?> === time) {
                            option.selected = true;
                        }
                        
                        heureSelect.appendChild(option);
                    }
                }
                
                heureSelect.disabled = false;
                checkFormCompletion();
            }

            // Fonction pour mettre à jour les options de durée
            function updateDurationOptions(serviceOption) {
                durationSelect.innerHTML = '<option value="">Chargement...</option>';
                durationSelect.disabled = true;
                
                if (serviceOption && serviceOption.value) {
                    const baseDuration = parseInt(serviceOption.dataset.duration);
                    const durations = generateDurationOptions(baseDuration);
                    
                    durationSelect.innerHTML = '<option value="">Choisissez une durée</option>';
                    
                    durations.forEach(duration => {
                        const option = document.createElement('option');
                        option.value = duration;
                        const hours = Math.floor(duration / 60);
                        const minutes = duration % 60;
                        let timeText = '';
                        
                        if (hours > 0) {
                            timeText += hours + 'h';
                            if (minutes > 0) timeText += minutes + 'min';
                        } else {
                            timeText = minutes + ' min';
                        }
                        
                        option.textContent = timeText;
                        
                        // Marquer la durée de base comme recommandée
                        if (duration === baseDuration) {
                            option.textContent += ' (recommandée)';
                        }
                        
                        // Maintenir la sélection si c'est un POST
                        if (<?= json_encode($_POST['duration'] ?? '') ?> == duration) {
                            option.selected = true;
                        }
                        
                        durationSelect.appendChild(option);
                    });
                    
                    durationSelect.disabled = false;
                    durationDetails.innerHTML = 'Choisissez la durée qui vous convient le mieux';
                    durationInfo.classList.remove('hidden');
                    durationInfo.classList.add('fade-in');
                } else {
                    durationSelect.innerHTML = '<option value="">Sélectionnez d\'abord un service</option>';
                    durationDetails.innerHTML = 'Sélectionnez un service pour voir les durées disponibles';
                    durationInfo.classList.remove('hidden');
                }
                
                updatePriceDisplay();
                checkFormCompletion();
            }

            // Fonction pour mettre à jour l'affichage du prix
            function updatePriceDisplay() {
                const serviceOption = serviceSelect.options[serviceSelect.selectedIndex];
                const selectedDuration = durationSelect.value;
                
                if (serviceOption && serviceOption.value && selectedDuration) {
                    const basePrice = parseFloat(serviceOption.dataset.price);
                    const baseDuration = parseInt(serviceOption.dataset.duration);
                    const duration = parseInt(selectedDuration);
                    const serviceName = serviceOption.dataset.name;
                    const serviceDescription = serviceOption.dataset.description;

                    const calculatedPrice = calculatePrice(basePrice, baseDuration, duration);

                    priceAmount.textContent = calculatedPrice.toFixed(2) + '€';
                    
                    const hours = Math.floor(duration / 60);
                    const minutes = duration % 60;
                    let durationText = '';
                    
                    if (hours > 0) {
                        durationText += hours + 'h';
                        if (minutes > 0) durationText += minutes + 'min';
                    } else {
                        durationText = minutes + ' min';
                    }
                    
                    priceDetails.innerHTML = `
                        <strong>${serviceName}</strong><br>
                        ${serviceDescription ? serviceDescription + '<br>' : ''}
                        Durée choisie: ${durationText}<br>
                        <small>Prix calculé: ${calculatedPrice.toFixed(2)}€ (${(calculatedPrice/duration).toFixed(2)}€/min)</small>
                    `;
                    
                    priceSummary.classList.remove('hidden');
                    setTimeout(() => {
                        priceSummary.classList.add('show');
                    }, 100);
                } else {
                    priceSummary.classList.remove('show');
                    setTimeout(() => {
                        priceSummary.classList.add('hidden');
                    }, 300);
                }
            }

            // Fonction pour mettre à jour le personnel disponible
            // Suite du code JavaScript à partir de updatePersonnelOptions
function updatePersonnelOptions(serviceId) {
    personnelSelect.innerHTML = '<option value="">Chargement...</option>';
    personnelSelect.disabled = true;
    
    if (serviceId && personnelByService[serviceId]) {
        const personnel = personnelByService[serviceId];
        personnelSelect.innerHTML = '<option value="">Choisissez un praticien</option>';
        
        personnel.forEach(person => {
            const option = document.createElement('option');
            option.value = person.id;
            option.textContent = person.name;
            
            // Maintenir la sélection si c'est un POST
            if (<?= json_encode($_POST['personnel_id'] ?? '') ?> == person.id) {
                option.selected = true;
            }
            
            personnelSelect.appendChild(option);
        });
        
        personnelSelect.disabled = false;
        personnelInfo.innerHTML = 'Sélectionnez le praticien de votre choix pour ce service';
        personnelInfo.classList.remove('hidden');
        personnelInfo.classList.add('fade-in');
    } else {
        personnelSelect.innerHTML = '<option value="">Aucun praticien disponible</option>';
        personnelInfo.innerHTML = 'Aucun praticien n\'est disponible pour ce service actuellement';
        personnelInfo.classList.remove('hidden');
    }
    
    // Réinitialiser les heures quand on change de personnel
    heureSelect.innerHTML = '<option value="">Sélectionnez d\'abord un praticien et une date</option>';
    heureSelect.disabled = true;
    
    checkFormCompletion();
}

// Fonction pour vérifier si le formulaire est complet
function checkFormCompletion() {
    const isComplete = serviceSelect.value && 
                      personnelSelect.value && 
                      durationSelect.value && 
                      dateInput.value && 
                      heureSelect.value;
    
    submitBtn.disabled = !isComplete;
    
    if (isComplete) {
        submitBtn.style.background = 'linear-gradient(135deg, #667eea 0%, #764ba2 100%)';
        submitBtn.innerHTML = '✅ Confirmer le rendez-vous';
    } else {
        submitBtn.style.background = '#6c757d';
        submitBtn.innerHTML = '📅 Remplissez tous les champs obligatoires';
    }
}

// Event listeners
serviceSelect.addEventListener('change', function() {
    const selectedOption = this.options[this.selectedIndex];
    
    if (selectedOption.value) {
        // Afficher les informations du service
        const serviceName = selectedOption.dataset.name;
        const serviceDescription = selectedOption.dataset.description;
        const duration = selectedOption.dataset.duration;
        const price = parseFloat(selectedOption.dataset.price).toFixed(2);
        
        serviceDetails.innerHTML = `
            <strong>${serviceName}</strong><br>
            ${serviceDescription ? serviceDescription + '<br>' : ''}
            <span style="color: #28a745; font-weight: 600;">
                Durée de base: ${duration} min - Prix de base: ${price}€
            </span>
        `;
        serviceInfo.classList.remove('hidden');
        serviceInfo.classList.add('fade-in');
        
        // Mettre à jour le personnel disponible
        updatePersonnelOptions(selectedOption.value);
        
        // Mettre à jour les options de durée
        updateDurationOptions(selectedOption);
    } else {
        serviceInfo.classList.add('hidden');
        personnelSelect.innerHTML = '<option value="">Sélectionnez d\'abord un service</option>';
        personnelSelect.disabled = true;
        personnelInfo.classList.add('hidden');
        
        durationSelect.innerHTML = '<option value="">Sélectionnez d\'abord un service</option>';
        durationSelect.disabled = true;
        durationInfo.classList.add('hidden');
        
        priceSummary.classList.remove('show');
        setTimeout(() => {
            priceSummary.classList.add('hidden');
        }, 300);
    }
    
    checkFormCompletion();
});

personnelSelect.addEventListener('change', function() {
    loadAvailableSlots();
    checkFormCompletion();
});

durationSelect.addEventListener('change', function() {
    updatePriceDisplay();
    checkFormCompletion();
});

dateInput.addEventListener('change', function() {
    loadAvailableSlots();
    checkFormCompletion();
});

heureSelect.addEventListener('change', function() {
    checkFormCompletion();
});

// Validation du formulaire avant soumission
form.addEventListener('submit', function(e) {
    const isValid = serviceSelect.value && 
                   personnelSelect.value && 
                   durationSelect.value && 
                   dateInput.value && 
                   heureSelect.value;
    
    if (!isValid) {
        e.preventDefault();
        alert('Veuillez remplir tous les champs obligatoires avant de soumettre le formulaire.');
        return false;
    }
    
    // Validation de la date (ne peut pas être dans le passé)
    const selectedDate = new Date(dateInput.value);
    const today = new Date();
    today.setHours(0, 0, 0, 0);
    
    if (selectedDate < today) {
        e.preventDefault();
        alert('La date sélectionnée ne peut pas être dans le passé.');
        return false;
    }
    
    // Confirmation avant soumission
    const serviceOption = serviceSelect.options[serviceSelect.selectedIndex];
    const personnelOption = personnelSelect.options[personnelSelect.selectedIndex];
    const durationOption = durationSelect.options[durationSelect.selectedIndex];
    
    const confirmMessage = `Confirmer ce rendez-vous ?\n\n` +
                          `Service: ${serviceOption.text}\n` +
                          `Praticien: ${personnelOption.text}\n` +
                          `Durée: ${durationOption.text}\n` +
                          `Date: ${dateInput.value}\n` +
                          `Heure: ${heureSelect.value}\n` +
                          `Prix: ${priceAmount.textContent}`;
    
    if (!confirm(confirmMessage)) {
        e.preventDefault();
        return false;
    }
    
    // Désactiver le bouton de soumission pour éviter les doubles clics
    submitBtn.disabled = true;
    submitBtn.innerHTML = '⏳ Traitement en cours...';
    
    return true;
});

// Animation de chargement des créneaux
function showLoadingSlots() {
    heureSelect.innerHTML = '<option value="">⏳ Chargement des créneaux...</option>';
    heureSelect.disabled = true;
}

// Initialisation : si des valeurs sont déjà sélectionnées (POST), les traiter
document.addEventListener('DOMContentLoaded', function() {
    // Si un service est déjà sélectionné (après un POST par exemple)
    if (serviceSelect.value) {
        const selectedOption = serviceSelect.options[serviceSelect.selectedIndex];
        updatePersonnelOptions(selectedOption.value);
        updateDurationOptions(selectedOption);
    }
    
    // Si un personnel est déjà sélectionné
    if (personnelSelect.value && dateInput.value) {
        loadAvailableSlots();
    }
    
    // Vérifier l'état initial du formulaire
    checkFormCompletion();
});

// Fonction pour réinitialiser le formulaire
function resetForm() {
    serviceSelect.value = '';
    personnelSelect.innerHTML = '<option value="">Sélectionnez d\'abord un service</option>';
    personnelSelect.disabled = true;
    durationSelect.innerHTML = '<option value="">Sélectionnez d\'abord un service</option>';
    durationSelect.disabled = true;
    heureSelect.innerHTML = '<option value="">Sélectionnez d\'abord un praticien et une date</option>';
    heureSelect.disabled = true;
    dateInput.value = '';
    document.getElementById('notes').value = '';
    
    // Masquer tous les panneaux d'information
    serviceInfo.classList.add('hidden');
    personnelInfo.classList.add('hidden');
    durationInfo.classList.add('hidden');
    priceSummary.classList.remove('show');
    priceSummary.classList.add('hidden');
    
    checkFormCompletion();
}

// Gestion des erreurs de chargement
window.addEventListener('error', function(e) {
    console.error('Erreur JavaScript:', e.error);
    // Afficher un message d'erreur à l'utilisateur si nécessaire
});

// Fonction utilitaire pour formater l'heure
function formatTime(time) {
    const [hours, minutes] = time.split(':');
    return `${hours}h${minutes !== '00' ? minutes : ''}`;
}

// Amélioration de l'accessibilité
serviceSelect.setAttribute('aria-describedby', 'service-info');
personnelSelect.setAttribute('aria-describedby', 'personnel-info');
durationSelect.setAttribute('aria-describedby', 'duration-info');

// Auto-focus sur le premier champ
serviceSelect.focus();

        });
    </script>
</body>
</html>