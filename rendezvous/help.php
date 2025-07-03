<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Aide - Am Soft</title>
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

        .header-content {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 2rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            height: 70px;
        }

        .logo {
            font-size: 1.8rem;
            font-weight: 700;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        nav {
            display: flex;
            gap: 2rem;
        }

        nav a {
            text-decoration: none;
            color: #333;
            font-weight: 500;
            position: relative;
            transition: color 0.3s ease;
        }

        nav a:hover {
            color: #667eea;
        }

        nav a.active {
            color: #667eea;
        }

        nav a::after {
            content: '';
            position: absolute;
            width: 0;
            height: 2px;
            bottom: -5px;
            left: 0;
            background: linear-gradient(135deg, #667eea, #764ba2);
            transition: width 0.3s ease;
        }

        nav a:hover::after,
        nav a.active::after {
            width: 100%;
        }

        /* Hero section pour la page help */
        .hero-help {
            padding: 120px 0 80px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            text-align: center;
        }

        .hero-help h1 {
            font-size: 3rem;
            font-weight: 800;
            margin-bottom: 1rem;
            animation: fadeInUp 1s ease-out;
        }

        .hero-help p {
            font-size: 1.2rem;
            opacity: 0.9;
            max-width: 600px;
            margin: 0 auto;
            animation: fadeInUp 1s ease-out 0.2s both;
        }

        /* Section principale */
        .main-content {
            padding: 6rem 0;
            background: #f8fafc;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 2rem;
        }

        .help-grid {
            display: grid;
            grid-template-columns: 1fr 2fr;
            gap: 4rem;
            margin-bottom: 4rem;
        }

        /* Sidebar navigation */
        .help-sidebar {
            background: white;
            border-radius: 20px;
            padding: 2rem;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
            height: fit-content;
            position: sticky;
            top: 100px;
        }

        .help-sidebar h3 {
            font-size: 1.3rem;
            font-weight: 600;
            margin-bottom: 1.5rem;
            color: #333;
        }

        .help-nav {
            list-style: none;
        }

        .help-nav li {
            margin-bottom: 0.5rem;
        }

        .help-nav a {
            display: block;
            padding: 0.8rem 1rem;
            color: #666;
            text-decoration: none;
            border-radius: 10px;
            transition: all 0.3s ease;
            font-weight: 500;
        }

        .help-nav a:hover,
        .help-nav a.active {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            transform: translateX(5px);
        }

        /* Contenu principal */
        .help-content {
            background: white;
            border-radius: 20px;
            padding: 3rem;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
        }

        .help-section {
            margin-bottom: 3rem;
        }

        .help-section h2 {
            font-size: 2rem;
            font-weight: 700;
            margin-bottom: 1.5rem;
            color: #333;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .help-section h3 {
            font-size: 1.3rem;
            font-weight: 600;
            margin: 2rem 0 1rem;
            color: #333;
        }

        .help-section p {
            margin-bottom: 1rem;
            color: #666;
            line-height: 1.7;
        }

        .help-section ul {
            margin: 1rem 0;
            padding-left: 2rem;
            color: #666;
        }

        .help-section li {
            margin-bottom: 0.5rem;
        }

        /* FAQ Section */
        .faq-item {
            background: #f8fafc;
            border-radius: 15px;
            margin-bottom: 1rem;
            overflow: hidden;
            transition: all 0.3s ease;
        }

        .faq-question {
            padding: 1.5rem;
            cursor: pointer;
            font-weight: 600;
            color: #333;
            display: flex;
            justify-content: space-between;
            align-items: center;
            background: white;
            border-left: 4px solid #667eea;
        }

        .faq-question:hover {
            background: #f8fafc;
        }

        .faq-answer {
            padding: 0 1.5rem 1.5rem;
            color: #666;
            line-height: 1.7;
        }

        .faq-toggle {
            font-size: 1.2rem;
            transition: transform 0.3s ease;
        }

        .faq-item.active .faq-toggle {
            transform: rotate(180deg);
        }

        /* Contact support */
        .support-contact {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 20px;
            padding: 3rem;
            color: white;
            text-align: center;
            margin-top: 4rem;
        }

        .support-contact h2 {
            font-size: 2rem;
            margin-bottom: 1rem;
        }

        .support-contact p {
            font-size: 1.1rem;
            margin-bottom: 2rem;
            opacity: 0.9;
        }

        .contact-buttons {
            display: flex;
            gap: 1rem;
            justify-content: center;
            flex-wrap: wrap;
        }

        .btn {
            padding: 1rem 2rem;
            border-radius: 12px;
            text-decoration: none;
            font-weight: 600;
            font-size: 1.1rem;
            transition: all 0.3s ease;
            display: inline-block;
        }

        .btn-white {
            background: white;
            color: #667eea;
            box-shadow: 0 8px 32px rgba(255, 255, 255, 0.3);
        }

        .btn-white:hover {
            transform: translateY(-3px);
            box-shadow: 0 12px 40px rgba(255, 255, 255, 0.4);
        }

        .btn-outline {
            background: transparent;
            color: white;
            border: 2px solid white;
        }

        .btn-outline:hover {
            background: white;
            color: #667eea;
            transform: translateY(-3px);
        }

        /* Footer */
        footer {
            background: #1a202c;
            color: white;
            padding: 3rem 0 2rem;
            text-align: center;
        }

        .footer-content {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 2rem;
        }

        /* Animations */
        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        /* Responsive */
        @media (max-width: 768px) {
            .help-grid {
                grid-template-columns: 1fr;
                gap: 2rem;
            }

            .help-sidebar {
                position: static;
            }

            .hero-help h1 {
                font-size: 2.5rem;
            }

            .contact-buttons {
                flex-direction: column;
                align-items: center;
            }

            nav {
                display: none;
            }
        }
    </style>
</head>
<body>
    <!-- Header -->
    <header>
        <div class="header-content">
            <div class="logo">Am Soft</div>
            <nav>
                <a href="index.php">Accueil</a>
                <a href="help.php" class="active">Aide</a>
                <a href="about.php">À propos</a>
            </nav>
        </div>
    </header>

    <!-- Hero Section -->
    <section class="hero-help">
        <div class="container">
            <h1>Centre d'aide</h1>
            <p>Trouvez rapidement les réponses à vos questions et apprenez à tirer le meilleur parti de notre plateforme de rendez-vous.</p>
        </div>
    </section>

    <!-- Contenu principal -->
    <section class="main-content">
        <div class="container">
            <div class="help-grid">
                <!-- Sidebar Navigation -->
                <div class="help-sidebar">
                    <h3>Navigation</h3>
                    <ul class="help-nav">
                        <li><a href="#getting-started" class="active">Démarrage rapide</a></li>
                        <li><a href="#account">Gestion du compte</a></li>
                        <li><a href="#appointments">Rendez-vous</a></li>
                        <li><a href="#notifications">Notifications</a></li>
                        <li><a href="#faq">FAQ</a></li>
                        <li><a href="#troubleshooting">Dépannage</a></li>
                    </ul>
                </div>

                <!-- Contenu principal -->
                <div class="help-content">
                    <div id="getting-started" class="help-section">
                        <h2>🚀 Démarrage rapide</h2>
                        <p>Bienvenue sur la plateforme Am Soft ! Suivez ces étapes simples pour commencer :</p>
                        
                        <h3>1. Créer votre compte</h3>
                        <ul>
                            <li>Cliquez sur "Créer un compte" depuis la page d'accueil</li>
                            <li>Remplissez le formulaire avec vos informations</li>
                            <li>Vérifiez votre email pour activer votre compte</li>
                        </ul>

                        <h3>2. Configurer votre profil</h3>
                        <ul>
                            <li>Ajoutez votre photo de profil</li>
                            <li>Complétez vos informations personnelles</li>
                            <li>Définissez vos préférences de notification</li>
                        </ul>

                        <h3>3. Planifier votre premier rendez-vous</h3>
                        <ul>
                            <li>Accédez au tableau de bord</li>
                            <li>Cliquez sur "Nouveau rendez-vous"</li>
                            <li>Remplissez les détails et envoyez l'invitation</li>
                        </ul>
                    </div>

                    <div id="account" class="help-section">
                        <h2>👤 Gestion du compte</h2>
                        
                        <h3>Modifier vos informations</h3>
                        <p>Pour modifier vos informations personnelles :</p>
                        <ul>
                            <li>Connectez-vous à votre compte</li>
                            <li>Allez dans "Paramètres" > "Profil"</li>
                            <li>Modifiez les informations souhaitées</li>
                            <li>Cliquez sur "Sauvegarder"</li>
                        </ul>

                        <h3>Changer votre mot de passe</h3>
                        <p>Pour des raisons de sécurité, nous recommandons de changer régulièrement votre mot de passe :</p>
                        <ul>
                            <li>Allez dans "Paramètres" > "Sécurité"</li>
                            <li>Cliquez sur "Changer le mot de passe"</li>
                            <li>Entrez votre ancien mot de passe et le nouveau</li>
                            <li>Confirmez les modifications</li>
                        </ul>
                    </div>

                    <div id="appointments" class="help-section">
                        <h2>📅 Gestion des rendez-vous</h2>
                        
                        <h3>Créer un rendez-vous</h3>
                        <p>Créer un nouveau rendez-vous est simple et rapide :</p>
                        <ul>
                            <li>Cliquez sur le bouton "+" ou "Nouveau rendez-vous"</li>
                            <li>Sélectionnez la date et l'heure</li>
                            <li>Ajoutez les participants</li>
                            <li>Définissez le lieu (physique ou virtuel)</li>
                            <li>Ajoutez une description si nécessaire</li>
                        </ul>

                        <h3>Modifier ou annuler un rendez-vous</h3>
                        <p>Vous pouvez modifier ou annuler vos rendez-vous à tout moment :</p>
                        <ul>
                            <li>Cliquez sur le rendez-vous dans votre calendrier</li>
                            <li>Sélectionnez "Modifier" ou "Annuler"</li>
                            <li>Les participants seront automatiquement notifiés</li>
                        </ul>
                    </div>

                    <div id="notifications" class="help-section">
                        <h2>🔔 Notifications</h2>
                        
                        <p>Configurez vos notifications pour ne jamais manquer un rendez-vous important :</p>
                        
                        <h3>Types de notifications</h3>
                        <ul>
                            <li><strong>Email :</strong> Recevez des rappels par email</li>
                            <li><strong>SMS :</strong> Notifications par message texte</li>
                            <li><strong>Push :</strong> Notifications sur votre navigateur</li>
                        </ul>

                        <h3>Paramétrer les rappels</h3>
                        <p>Vous pouvez définir plusieurs rappels pour chaque rendez-vous :</p>
                        <ul>
                            <li>15 minutes avant</li>
                            <li>1 heure avant</li>
                            <li>1 jour avant</li>
                            <li>Personnalisé</li>
                        </ul>
                    </div>

                    <div id="faq" class="help-section">
                        <h2>❓ Questions fréquemment posées</h2>
                        
                        <div class="faq-item">
                            <div class="faq-question" onclick="toggleFaq(this)">
                                Comment inviter des participants à un rendez-vous ?
                                <span class="faq-toggle">▼</span>
                            </div>
                            <div class="faq-answer" style="display: none;">
                                Lors de la création d'un rendez-vous, ajoutez les adresses email des participants dans le champ "Invités". Ils recevront automatiquement une invitation par email avec tous les détails.
                            </div>
                        </div>

                        <div class="faq-item">
                            <div class="faq-question" onclick="toggleFaq(this)">
                                Puis-je synchroniser avec mon calendrier existant ?
                                <span class="faq-toggle">▼</span>
                            </div>
                            <div class="faq-answer" style="display: none;">
                                Oui ! Vous pouvez synchroniser votre compte avec Google Calendar, Outlook, et d'autres calendriers populaires. Allez dans Paramètres > Intégrations pour configurer la synchronisation.
                            </div>
                        </div>

                        <div class="faq-item">
                            <div class="faq-question" onclick="toggleFaq(this)">
                                Comment gérer les fuseaux horaires ?
                                <span class="faq-toggle">▼</span>
                            </div>
                            <div class="faq-answer" style="display: none;">
                                La plateforme détecte automatiquement votre fuseau horaire. Vous pouvez le modifier dans vos paramètres de profil. Les invitations affichent l'heure locale de chaque participant.
                            </div>
                        </div>

                        <div class="faq-item">
                            <div class="faq-question" onclick="toggleFaq(this)">
                                Mes données sont-elles sécurisées ?
                                <span class="faq-toggle">▼</span>
                            </div>
                            <div class="faq-answer" style="display: none;">
                                Absolument ! Nous utilisons un chiffrement SSL/TLS pour toutes les communications et stockons vos données de manière sécurisée. Vos informations personnelles ne sont jamais partagées avec des tiers.
                            </div>
                        </div>
                    </div>

                    <div id="troubleshooting" class="help-section">
                        <h2>🔧 Dépannage</h2>
                        
                        <h3>Problèmes de connexion</h3>
                        <p>Si vous ne parvenez pas à vous connecter :</p>
                        <ul>
                            <li>Vérifiez votre nom d'utilisateur et mot de passe</li>
                            <li>Utilisez la fonction "Mot de passe oublié"</li>
                            <li>Vérifiez que votre compte est activé</li>
                            <li>Effacez le cache de votre navigateur</li>
                        </ul>

                        <h3>Notifications non reçues</h3>
                        <p>Si vous ne recevez pas de notifications :</p>
                        <ul>
                            <li>Vérifiez vos paramètres de notification</li>
                            <li>Consultez votre dossier spam/courrier indésirable</li>
                            <li>Vérifiez que votre adresse email est correcte</li>
                            <li>Autorisez les notifications dans votre navigateur</li>
                        </ul>

                        <h3>Problèmes de performance</h3>
                        <p>Pour optimiser les performances :</p>
                        <ul>
                            <li>Utilisez un navigateur récent (Chrome, Firefox, Safari)</li>
                            <li>Vérifiez votre connexion internet</li>
                            <li>Fermez les onglets inutiles</li>
                            <li>Redémarrez votre navigateur</li>
                        </ul>
                    </div>
                </div>
            </div>

            <!-- Section contact support -->
            <div class="support-contact">
                <h2>Besoin d'aide supplémentaire ?</h2>
                <p>Notre équipe support est là pour vous aider. Contactez-nous par email ou téléphone, nous vous répondrons rapidement.</p>
                <div class="contact-buttons">
                    <a href="mailto:support@amsoft.ma" class="btn btn-white">📧 support@amsoft.ma</a>
                    <a href="tel:+212524000000" class="btn btn-outline">📞 +212 5 24 00 00 00</a>
                </div>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer>
        <div class="footer-content">
            <p>&copy; 2025 Am Soft - Marrakech, Maroc. Tous droits réservés.</p>
        </div>
    </footer>

    <script>
        function toggleFaq(element) {
            const faqItem = element.parentElement;
            const answer = faqItem.querySelector('.faq-answer');
            const isActive = faqItem.classList.contains('active');

            // Fermer toutes les autres FAQ
            document.querySelectorAll('.faq-item').forEach(item => {
                item.classList.remove('active');
                item.querySelector('.faq-answer').style.display = 'none';
            });

            // Ouvrir/fermer la FAQ cliquée
            if (!isActive) {
                faqItem.classList.add('active');
                answer.style.display = 'block';
            }
        }

        // Navigation smooth scrolling
        document.querySelectorAll('.help-nav a').forEach(link => {
            link.addEventListener('click', function(e) {
                e.preventDefault();
                const targetId = this.getAttribute('href').substring(1);
                const targetElement = document.getElementById(targetId);
                
                if (targetElement) {
                    targetElement.scrollIntoView({
                        behavior: 'smooth',
                        block: 'start'
                    });

                    // Update active nav item
                    document.querySelectorAll('.help-nav a').forEach(navLink => {
                        navLink.classList.remove('active');
                    });
                    this.classList.add('active');
                }
            });
        });

        // Header scroll effect
        window.addEventListener('scroll', function() {
            const header = document.querySelector('header');
            if (window.scrollY > 100) {
                header.style.background = 'rgba(255, 255, 255, 0.98)';
            } else {
                header.style.background = 'rgba(255, 255, 255, 0.95)';
            }
        });
    </script>
</body>
</html>