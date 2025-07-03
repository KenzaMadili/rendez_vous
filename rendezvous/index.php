<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Am Soft - Plateforme de Rendez-vous</title>
    <style>
        /* Reset et base */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        :root {
            --primary-gradient: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            --secondary-gradient: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
            --dark-gradient: linear-gradient(135deg, #1a202c 0%, #2d3748 100%);
            --glass-bg: rgba(255, 255, 255, 0.1);
            --glass-border: rgba(255, 255, 255, 0.2);
            --shadow-light: 0 8px 32px rgba(0, 0, 0, 0.1);
            --shadow-medium: 0 12px 40px rgba(0, 0, 0, 0.15);
            --shadow-heavy: 0 20px 60px rgba(0, 0, 0, 0.2);
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
            line-height: 1.6;
            color: #333;
            overflow-x: hidden;
            background: #f8fafc;
        }

        /* Smooth scrolling */
        html {
            scroll-behavior: smooth;
        }

        /* Header moderne avec glassmorphism */
        header {
            position: fixed;
            top: 0;
            width: 100%;
            height: 80px;
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            border-bottom: 1px solid rgba(0, 0, 0, 0.1);
            z-index: 1000;
            transition: all 0.3s ease;
        }

        header.scrolled {
            background: rgba(255, 255, 255, 0.98);
            box-shadow: var(--shadow-light);
        }

        .header-content {
            max-width: 1400px;
            margin: 0 auto;
            padding: 0 2rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            height: 80px;
        }

        .logo {
            font-size: 2rem;
            font-weight: 800;
            background: var(--primary-gradient);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            cursor: pointer;
            transition: transform 0.3s ease;
        }

        .logo:hover {
            transform: scale(1.05);
        }

        nav {
            display: flex;
            gap: 2.5rem;
            align-items: center;
        }

        nav a {
            text-decoration: none;
            color: #333;
            font-weight: 500;
            position: relative;
            transition: all 0.3s ease;
            padding: 0.5rem 1rem;
            border-radius: 8px;
        }

        nav a:hover {
            color: #667eea;
            background: rgba(102, 126, 234, 0.1);
        }

        .mobile-menu {
            display: none;
            flex-direction: column;
            cursor: pointer;
            padding: 0.5rem;
        }

        .mobile-menu span {
            width: 25px;
            height: 3px;
            background: #333;
            margin: 3px 0;
            transition: 0.3s;
            border-radius: 2px;
        }

        /* Hero section avec animations avancées */
        .hero {
            min-height: 150vh;
            background: var(--primary-gradient);
            display: flex;
            align-items: center;
            position: relative;
            overflow: hidden;
            padding-top: 80px;
        }

        .hero::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><defs><pattern id="grain" width="100" height="100" patternUnits="userSpaceOnUse"><circle cx="25" cy="25" r="1" fill="white" opacity="0.1"/><circle cx="75" cy="75" r="1" fill="white" opacity="0.1"/><circle cx="75" cy="25" r="0.5" fill="white" opacity="0.1"/><circle cx="25" cy="75" r="0.5" fill="white" opacity="0.1"/></pattern></defs><rect width="100" height="100" fill="url(%23grain)"/></svg>');
            animation: grain 20s linear infinite;
        }

        .hero-content {
            max-width: 1400px;
            margin: 0 auto;
            padding: 0 2rem;
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 4rem;
            align-items: center;
            position: relative;
            z-index: 1;
        }

        .hero-text {
            color: white;
        }

        .hero-text h1 {
            font-size: 4rem;
            font-weight: 900;
            margin-bottom: 1.5rem;
            line-height: 1.1;
            animation: fadeInUp 1s ease-out;
            background: linear-gradient(135deg, #ffffff 0%, #f0f8ff 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .hero-text .highlight {
            background: linear-gradient(135deg, #ffd700 0%, #ffed4e 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .hero-text p {
            font-size: 1.4rem;
            margin-bottom: 3rem;
            opacity: 0.9;
            line-height: 1.6;
            animation: fadeInUp 1s ease-out 0.2s both;
        }

        .cta-buttons {
            display: flex;
            gap: 1.5rem;
            animation: fadeInUp 1s ease-out 0.4s both;
        }

        .btn {
            padding: 1.2rem 2.5rem;
            border-radius: 15px;
            text-decoration: none;
            font-weight: 600;
            font-size: 1.1rem;
            transition: all 0.3s ease;
            display: inline-block;
            position: relative;
            overflow: hidden;
            cursor: pointer;
            border: none;
        }

        .btn::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.2), transparent);
            transition: left 0.5s;
        }

        .btn:hover::before {
            left: 100%;
        }

        .btn-primary {
            background: white;
            color: #667eea;
            box-shadow: 0 8px 32px rgba(255, 255, 255, 0.3);
        }

        .btn-primary:hover {
            transform: translateY(-3px);
            box-shadow: 0 12px 40px rgba(255, 255, 255, 0.4);
        }

        .btn-secondary {
            background: transparent;
            color: white;
            border: 2px solid white;
        }

        .btn-secondary:hover {
            background: white;
            color: #667eea;
            transform: translateY(-3px);
        }

        /* Visualisation hero améliorée */
        .hero-visual {
            display: flex;
            justify-content: center;
            align-items: center;
            position: relative;
        }

        .floating-cards {
            position: relative;
            width: 100%;
            height: 500px;
        }

        .card {
            position: absolute;
            background: var(--glass-bg);
            backdrop-filter: blur(20px);
            border: 1px solid var(--glass-border);
            border-radius: 20px;
            padding: 2rem;
            color: white;
            box-shadow: var(--shadow-light);
            transition: all 0.3s ease;
            cursor: pointer;
        }

        .card:hover {
            transform: scale(1.05);
            background: rgba(255, 255, 255, 0.2);
        }

        .card-1 {
            top: 0;
            left: 0;
            width: 300px;
            animation: float 6s ease-in-out infinite;
        }

        .card-2 {
            top: 150px;
            right: 0;
            width: 280px;
            animation: float 6s ease-in-out infinite reverse;
        }

        .card-3 {
            bottom: 0;
            left: 50px;
            width: 250px;
            animation: float 6s ease-in-out 2s infinite;
        }

        .card h3 {
            font-size: 1.3rem;
            margin-bottom: 0.8rem;
            color: white;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .card p {
            font-size: 1rem;
            opacity: 0.9;
            line-height: 1.5;
        }

        /* Section Features améliorée */
        .features {
            padding: 8rem 0;
            background: linear-gradient(135deg, #f8fafc 0%, #e2e8f0 100%);
            position: relative;
        }

        .container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 0 2rem;
        }

        .section-title {
            text-align: center;
            margin-bottom: 5rem;
        }

        .section-title h2 {
            font-size: 3rem;
            font-weight: 800;
            margin-bottom: 1.5rem;
            background: var(--primary-gradient);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .section-title p {
            font-size: 1.2rem;
            color: #64748b;
            max-width: 600px;
            margin: 0 auto;
        }

        .features-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
            gap: 2.5rem;
        }

        .feature-card {
            background: white;
            padding: 3rem;
            border-radius: 25px;
            box-shadow: var(--shadow-light);
            transition: all 0.4s ease;
            border: 1px solid rgba(0, 0, 0, 0.05);
            position: relative;
            overflow: hidden;
        }

        .feature-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: var(--primary-gradient);
            transform: scaleX(0);
            transition: transform 0.3s ease;
        }

        .feature-card:hover::before {
            transform: scaleX(1);
        }

        .feature-card:hover {
            transform: translateY(-10px);
            box-shadow: var(--shadow-medium);
        }

        .feature-icon {
            width: 80px;
            height: 80px;
            background: var(--primary-gradient);
            border-radius: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 2rem;
            font-size: 2rem;
            transition: all 0.3s ease;
        }

        .feature-card:hover .feature-icon {
            transform: rotate(5deg) scale(1.1);
        }

        .feature-card h3 {
            font-size: 1.5rem;
            font-weight: 700;
            margin-bottom: 1rem;
            color: #1a202c;
        }

        .feature-card p {
            color: #64748b;
            line-height: 1.7;
            font-size: 1.1rem;
        }

        /* Nouvelle section statistiques */
        .stats {
            padding: 6rem 0;
            background: var(--dark-gradient);
            color: white;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 3rem;
            text-align: center;
        }

        .stat-item {
            padding: 2rem;
        }

        .stat-number {
            font-size: 3.5rem;
            font-weight: 900;
            background: linear-gradient(135deg, #ffd700 0%, #ffed4e 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            display: block;
            margin-bottom: 1rem;
        }

        .stat-label {
            font-size: 1.2rem;
            font-weight: 600;
            opacity: 0.9;
        }

        /* Section CTA */
        .cta-section {
            padding: 8rem 0;
            background: var(--secondary-gradient);
            text-align: center;
            color: white;
        }

        .cta-content {
            max-width: 800px;
            margin: 0 auto;
        }

        .cta-content h2 {
            font-size: 3rem;
            font-weight: 800;
            margin-bottom: 2rem;
        }

        .cta-content p {
            font-size: 1.3rem;
            margin-bottom: 3rem;
            opacity: 0.9;
        }

        /* Footer moderne */
        footer {
            background: #0f172a;
            color: white;
            padding: 4rem 0 2rem;
        }

        .footer-content {
            max-width: 1400px;
            margin: 0 auto;
            padding: 0 2rem;
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 3rem;
        }

        .footer-section h3 {
            font-size: 1.3rem;
            font-weight: 700;
            margin-bottom: 1.5rem;
            background: var(--primary-gradient);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .footer-section p,
        .footer-section a {
            color: #94a3b8;
            text-decoration: none;
            margin-bottom: 0.5rem;
            display: block;
            transition: color 0.3s ease;
        }

        .footer-section a:hover {
            color: white;
        }

        .footer-bottom {
            border-top: 1px solid #334155;
            margin-top: 3rem;
            padding-top: 2rem;
            text-align: center;
            color: #64748b;
        }

        /* Animations améliorées */
        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(50px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        @keyframes float {
            0%, 100% { transform: translateY(0px) rotate(0deg); }
            25% { transform: translateY(-10px) rotate(1deg); }
            50% { transform: translateY(-20px) rotate(0deg); }
            75% { transform: translateY(-10px) rotate(-1deg); }
        }

        @keyframes grain {
            0%, 100% { transform: translate(0, 0); }
            10% { transform: translate(-5%, -5%); }
            20% { transform: translate(-10%, 5%); }
            30% { transform: translate(5%, -10%); }
            40% { transform: translate(-5%, 15%); }
            50% { transform: translate(-10%, 5%); }
            60% { transform: translate(15%, 0%); }
            70% { transform: translate(0%, 10%); }
            80% { transform: translate(-15%, 0%); }
            90% { transform: translate(10%, 5%); }
        }

        /* Scroll animations */
        .animate-on-scroll {
            opacity: 0;
            transform: translateY(50px);
            transition: all 0.8s ease;
        }

        .animate-on-scroll.animated {
            opacity: 1;
            transform: translateY(0);
        }

        /* Responsive design amélioré */
        @media (max-width: 1024px) {
            .hero-content {
                gap: 3rem;
            }
            
            .hero-text h1 {
                font-size: 3rem;
            }
        }

        @media (max-width: 768px) {
            .mobile-menu {
                display: flex;
            }

            nav {
                display: none;
            }

            .hero-content {
                grid-template-columns: 1fr;
                text-align: center;
                gap: 2rem;
            }

            .hero-text h1 {
                font-size: 2.5rem;
            }

            .cta-buttons {
                flex-direction: column;
                align-items: center;
            }

            .floating-cards {
                display: none;
            }

            .features-grid {
                grid-template-columns: 1fr;
            }

            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }

            .section-title h2 {
                font-size: 2.2rem;
            }

            .cta-content h2 {
                font-size: 2.2rem;
            }
        }

        @media (max-width: 480px) {
            .hero-text h1 {
                font-size: 2rem;
            }
            
            .stats-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <!-- Header -->
    <header id="header">
        <div class="header-content">
            <div class="logo">Am Soft</div>
            <nav>
                <a href="#accueil">Accueil</a>
                <a href="help.php">Aide</a>
                <a href="about.php">À propos</a>
            </nav>
            <div class="mobile-menu">
                <span></span>
                <span></span>
                <span></span>
            </div>
        </div>
    </header>

    <!-- Section Hero -->
    <section class="hero" id="accueil">
        <div class="hero-content">
            <div class="hero-text">
                <h1>Révolutionnez la gestion de vos <span class="highlight">rendez-vous</span></h1>
                <p>Découvrez la plateforme la plus moderne et intuitive pour planifier, organiser et suivre tous vos rendez-vous professionnels et personnels avec une efficacité inégalée.</p>
                <div class="cta-buttons">
                    <a href="login.php" class="btn btn-primary">Commencer maintenant</a>
                    <a href="register.php" class="btn btn-secondary">Créer un compte</a>
                </div>
            </div>
            <div class="hero-visual">
                <div class="floating-cards">
                    <div class="card card-1">
                        <h3>📅 <span>Planification intelligente</span></h3>
                        <p>Créez et organisez vos rendez-vous avec notre système intelligent qui s'adapte à votre rythme de vie.</p>
                    </div>
                    <div class="card card-2">
                        <h3>🔔 <span>Alertes avancées</span></h3>
                        <p>Recevez des notifications personnalisées et ne manquez plus jamais un rendez-vous important.</p>
                    </div>
                    <div class="card card-3">
                        <h3>👥 <span>Collaboration d'équipe</span></h3>
                        <p>Partagez et synchronisez vos calendriers pour une coordination parfaite.</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Section Statistiques -->
    <section class="stats">
        <div class="container">
            <div class="stats-grid">
                <div class="stat-item animate-on-scroll">
                    <span class="stat-number">5K+</span>
                    <span class="stat-label">Utilisateurs actifs</span>
                </div>
                <div class="stat-item animate-on-scroll">
                    <span class="stat-number">10K+</span>
                    <span class="stat-label">Rendez-vous planifiés</span>
                </div>
                <div class="stat-item animate-on-scroll">
                    <span class="stat-number">99.9%</span>
                    <span class="stat-label">Temps de disponibilité</span>
                </div>
                <div class="stat-item animate-on-scroll">
                    <span class="stat-number">24/7</span>
                    <span class="stat-label">Support technique</span>
                </div>
            </div>
        </div>
    </section>

    <!-- Section Features -->
    <section class="features" id="fonctionnalites">
        <div class="container">
            <div class="section-title">
                <h2>Pourquoi choisir Am Soft ?</h2>
                <p>Découvrez les fonctionnalités révolutionnaires qui transformeront votre façon de gérer les rendez-vous</p>
            </div>
            <div class="features-grid">
                <div class="feature-card animate-on-scroll">
                    <div class="feature-icon">🚀</div>
                    <h3>Interface révolutionnaire</h3>
                    <p>Une expérience utilisateur exceptionnelle avec une interface moderne, intuitive et personnalisable qui s'adapte à tous vos besoins.</p>
                </div>
                <div class="feature-card animate-on-scroll">
                    <div class="feature-icon">🔒</div>
                    <h3>Sécurité militaire</h3>
                    <p>Protection maximale de vos données avec cryptage de niveau des mots de passe pour une tranquillité d'esprit totale.</p>
                </div>
                <div class="feature-card animate-on-scroll">
                    <div class="feature-icon">📱</div>
                    <h3>Synchronisation universelle</h3>
                    <p>Accès instantané depuis tous vos appareils avec synchronisation en temps réel et mode hors ligne disponible.</p>
                </div>
                
                <div class="feature-card animate-on-scroll">
                    <div class="feature-icon">📊</div>
                    <h3>Analytics avancés</h3>
                    <p>Tableaux de bord détaillés avec métriques personnalisées pour analyser et optimiser votre productivité.</p>
                </div>
                
            </div>
        </div>
    </section>

    <!-- Section CTA -->
    <section class="cta-section">
        <div class="container">
            <div class="cta-content">
                <h2>Prêt à transformer votre productivité ?</h2>
                <p>Rejoignez des milliers d'utilisateurs qui ont déjà révolutionné leur gestion du temps avec Am Soft.</p>
                <div class="cta-buttons">
                    <a href="register.php" class="btn btn-primary">Essayer gratuitment</a>
                    <a href="login.php" class="btn btn-secondary">Vous avez déja un compte</a>
                </div>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer>
        <div class="footer-content">
            <div class="footer-section">
                <h3>Am Soft</h3>
                <p>La plateforme de gestion de rendez-vous la plus avancée au monde.</p>
                <p>Transformez votre productivité dès aujourd'hui.</p>
            </div>
        <div class="footer-section">
                <h3>Support</h3>
                <a href="help.php">Centre d'aide</a>
                <a href="about.php">À propos nous</a>
                <a href="register.php">s'inscrire</a>
                <a href="login.php">se connecter</a>
            </div>
        <div class="footer-section">
               <h3>Entreprise</h3>
               <p>Email : amsoft@gmail.com | Téléphone : +212 5 24 00 00 00</p>
               <p>Adresse :Centre Kairaouane , Marrakech, Maroc</p>
        </div>
        <div class="footer-bottom">
            <p>&copy; 2025 Am Soft. Tous droits réservés. | Politique de confidentialité | Conditions d'utilisation</p>
        </div>
    </footer>

    <script>
        // Animation au scroll
        function animateOnScroll() {
            const elements = document.querySelectorAll('.animate-on-scroll');
            const observer = new IntersectionObserver((entries) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        entry.target.classList.add('animated');
                    }
                });
            }, { threshold: 0.1 });

            elements.forEach(el => observer.observe(el));
        }

        // Header scroll effect
        function handleHeaderScroll() {
            const header = document.getElementById('header');
            window.addEventListener('scroll', () => {
                if (window.scrollY > 100) {
                    header.classList.add('scrolled');
                } else {
                    header.classList.remove('scrolled');
                }
            });
        }

        // Animation des statistiques
        function animateStats() {
            const statNumbers = document.querySelectorAll('.stat-number');
            const observer = new IntersectionObserver((entries) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        const target = entry.target;
                        const finalNumber = target.textContent;
                        const isPercentage = finalNumber.includes('%');
                        const isTime = finalNumber.includes('/');
                        const number = parseInt(finalNumber.replace(/[^\d]/g, ''));
                        
                        if (!isTime && !isPercentage) {
                            animateNumber(target, 0, number, finalNumber);
                        }
                        observer.unobserve(target);
                    }
                });
            });

            statNumbers.forEach(stat => observer.observe(stat));
        }

        function animateNumber(element, start, end, suffix) {
            const duration = 2000;
            const startTime = performance.now();
            
            function update(currentTime) {
                const elapsed = currentTime - startTime;
                const progress = Math.min(elapsed / duration, 1);
                const current = Math.floor(progress * (end - start) + start);
                
                if (current >= 1000) {
                    element.textContent = (current / 1000).toFixed(0) + 'K+';
                } else {
                    element.textContent = current + suffix.replace(/\d/g, '');
                }
                
                if (progress < 1) {
                    requestAnimationFrame(update);
                }
            }
            
            requestAnimationFrame(update);
        }

        // Effet de parallaxe léger
        function addParallaxEffect() {
            window.addEventListener('scroll', () => {
                const scrolled = window.pageYOffset;
                const parallaxElements = document.querySelectorAll('.floating-cards');
                
                parallaxElements.forEach(element => {
                    const speed = 0.2;
                    element.style.transform = `translateY(${scrolled * speed}px)`;
                });
            });
        }

        // Smooth scroll pour les liens d'ancrage
        function initSmoothScroll() {
            document.querySelectorAll('a[href="#"]').forEach(anchor => {
                anchor.addEventListener('click', function (e) {
                    e.preventDefault();
                    const target = document.querySelector(this.getAttribute('href'));
                    if (target) {
                        target.scrollIntoView({
                            behavior: 'smooth',
                            block: 'start'
                        });
                    }
                });
            });
        }

        // Menu mobile
        function initMobileMenu() {
            const mobileMenu = document.querySelector('.mobile-menu');
            const nav = document.querySelector('nav');
            
            mobileMenu.addEventListener('click', () => {
                nav.style.display = nav.style.display === 'flex' ? 'none' : 'flex';
                nav.style.position = 'absolute';
                nav.style.top = '80px';
                nav.style.left = '0';
                nav.style.right = '0';
                nav.style.background = 'white';
                nav.style.flexDirection = 'column';
                nav.style.padding = '2rem';
                nav.style.boxShadow = '0 4px 6px rgba(0,0,0,0.1)';
            });
        }

        // Initialisation
        document.addEventListener('DOMContentLoaded', function() {
            animateOnScroll();
            handleHeaderScroll();
            animateStats();
            addParallaxEffect();
            initSmoothScroll();
            initMobileMenu();
        });

        // Effet de cursor personnalisé
        document.addEventListener('mousemove', (e) => {
            const cursor = document.querySelector('.cursor');
            if (!cursor) {
                const newCursor = document.createElement('div');
                newCursor.className = 'cursor';
                newCursor.style.cssText = `
                    position: fixed;
                    width: 20px;
                    height: 20px;
                    background: rgba(102, 126, 234, 0.3);
                    border-radius: 50%;
                    pointer-events: none;
                    z-index: 9999;
                    transition: all 0.1s ease;
                `;
                document.body.appendChild(newCursor);
            }
            
            const cursor2 = document.querySelector('.cursor');
            if (cursor2) {
                cursor2.style.left = e.clientX - 10 + 'px';
                cursor2.style.top = e.clientY - 10 + 'px';
            }
        });

        // Performance optimization
        let ticking = false;
        function updateAnimations() {
            // Optimisation des animations
            if (!ticking) {
                requestAnimationFrame(() => {
                    ticking = false;
                });
                ticking = true;
            }
        }

        window.addEventListener('scroll', updateAnimations);
    </script>
</body>
</html>