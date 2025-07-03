<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>À Propos - Am Soft</title>
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

        /* Hero section */
        .hero-about {
            padding: 120px 0 80px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            text-align: center;
            position: relative;
            overflow: hidden;
        }

        .hero-about::before {
            content: '';
            position: absolute;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><circle cx="50" cy="50" r="2" fill="rgba(255,255,255,0.1)"/></svg>') repeat;
            animation: float 20s ease-in-out infinite;
        }

        .hero-about h1 {
            font-size: 3.5rem;
            font-weight: 800;
            margin-bottom: 1rem;
            animation: fadeInUp 1s ease-out;
            position: relative;
            z-index: 2;
        }

        .hero-about p {
            font-size: 1.3rem;
            opacity: 0.9;
            max-width: 700px;
            margin: 0 auto;
            animation: fadeInUp 1s ease-out 0.2s both;
            position: relative;
            z-index: 2;
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

        /* Section notre histoire */
        .story-section {
            background: white;
            border-radius: 25px;
            padding: 4rem;
            margin-bottom: 4rem;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.1);
            position: relative;
            overflow: hidden;
        }

        .story-section::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 6px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }

        .story-section h2 {
            font-size: 2.5rem;
            font-weight: 700;
            margin-bottom: 2rem;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .story-content {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 3rem;
            align-items: center;
        }

        .story-text p {
            font-size: 1.1rem;
            margin-bottom: 1.5rem;
            color: #666;
            line-height: 1.8;
        }

        .story-image {
            text-align: center;
            font-size: 8rem;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        /* Section valeurs */
        .values-section {
            margin-bottom: 4rem;
        }

        .values-section h2 {
            text-align: center;
            font-size: 2.5rem;
            font-weight: 700;
            margin-bottom: 3rem;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .values-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 2rem;
        }

        .value-card {
            background: white;
            border-radius: 20px;
            padding: 2.5rem;
            text-align: center;
            box-shadow: 0 8px 30px rgba(0, 0, 0, 0.1);
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }

        .value-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.4), transparent);
            transition: left 0.5s;
        }

        .value-card:hover {
            transform: translateY(-10px);
            box-shadow: 0 20px 50px rgba(0, 0, 0, 0.15);
        }

        .value-card:hover::before {
            left: 100%;
        }

        .value-icon {
            font-size: 3rem;
            margin-bottom: 1.5rem;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .value-card h3 {
            font-size: 1.4rem;
            font-weight: 600;
            margin-bottom: 1rem;
            color: #333;
        }

        .value-card p {
            color: #666;
            line-height: 1.7;
        }

        /* Section équipe */
        .team-section {
            background: white;
            border-radius: 25px;
            padding: 4rem;
            margin-bottom: 4rem;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.1);
        }

        .team-section h2 {
            text-align: center;
            font-size: 2.5rem;
            font-weight: 700;
            margin-bottom: 3rem;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .team-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 2rem;
        }

        .team-member {
            text-align: center;
            padding: 2rem;
            border-radius: 20px;
            transition: all 0.3s ease;
        }

        .team-member:hover {
            background: #f8fafc;
            transform: translateY(-5px);
        }

        .member-avatar {
            width: 120px;
            height: 120px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 50%;
            margin: 0 auto 1.5rem;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 3rem;
            color: white;
            font-weight: 700;
        }

        .team-member h3 {
            font-size: 1.3rem;
            font-weight: 600;
            margin-bottom: 0.5rem;
            color: #333;
        }

        .team-member .role {
            color: #667eea;
            font-weight: 500;
            margin-bottom: 1rem;
        }

        .team-member p {
            color: #666;
            font-size: 0.95rem;
            line-height: 1.6;
        }

        /* Section mission */
        .mission-section {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 25px;
            padding: 4rem;
            color: white;
            text-align: center;
            margin-bottom: 4rem;
            position: relative;
            overflow: hidden;
        }

        .mission-section::before {
            content: '';
            position: absolute;
            top: -50%;
            right: -50%;
            width: 200%;
            height: 200%;
            background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><circle cx="50" cy="50" r="1" fill="rgba(255,255,255,0.1)"/></svg>') repeat;
            animation: float 15s ease-in-out infinite reverse;
        }

        .mission-section h2 {
            font-size: 2.5rem;
            font-weight: 700;
            margin-bottom: 2rem;
            position: relative;
            z-index: 2;
        }

        .mission-section p {
            font-size: 1.2rem;
            line-height: 1.8;
            max-width: 800px;
            margin: 0 auto;
            opacity: 0.95;
            position: relative;
            z-index: 2;
        }

        /* Section contact */
        .contact-section {
            background: white;
            border-radius: 25px;
            padding: 4rem;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.1);
            text-align: center;
        }

        .contact-section h2 {
            font-size: 2.5rem;
            font-weight: 700;
            margin-bottom: 2rem;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .contact-info {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 2rem;
            margin-top: 3rem;
        }

        .contact-item {
            padding: 2rem;
            border-radius: 15px;
            background: #f8fafc;
            transition: all 0.3s ease;
        }

        .contact-item:hover {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            transform: translateY(-5px);
        }

        .contact-icon {
            font-size: 2.5rem;
            margin-bottom: 1rem;
        }

        .contact-item h3 {
            font-size: 1.2rem;
            font-weight: 600;
            margin-bottom: 0.5rem;
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

        @keyframes float {
            0%, 100% { transform: translateY(0px) rotate(0deg); }
            50% { transform: translateY(-20px) rotate(180deg); }
        }

        /* Responsive */
        @media (max-width: 768px) {
            .story-content {
                grid-template-columns: 1fr;
                gap: 2rem;
            }

            .story-image {
                font-size: 4rem;
            }

            .hero-about h1 {
                font-size: 2.5rem;
            }

            .hero-about p {
                font-size: 1.1rem;
            }

            .story-section,
            .team-section,
            .mission-section,
            .contact-section {
                padding: 2rem;
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
                <a href="help.php">Aide</a>
                <a href="about.php" class="active">À propos</a>
            </nav>
        </div>
    </header>

    <!-- Hero Section -->
    <section class="hero-about">
        <div class="container">
            <h1>À Propos d'Am Soft</h1>
            <p>Votre partenaire technologique de confiance à Marrakech, spécialisé dans les solutions digitales innovantes pour simplifier votre quotidien professionnel.</p>
        </div>
    </section>

    <!-- Contenu principal -->
    <section class="main-content">
        <div class="container">
            <!-- Notre Histoire -->
            <div class="story-section">
                <h2>Notre Histoire</h2>
                <div class="story-content">
                    <div class="story-text">
                        <p>Fondée en 2020 à Marrakech, Am Soft est née de la vision de créer des solutions technologiques qui simplifient réellement la vie des entreprises et des professionnels. Dans une ville où tradition et modernité se rencontrent, nous avons choisi d'être les pionniers de la transformation digitale.</p>
                        
                        <p>Depuis nos débuts, nous nous sommes spécialisés dans le développement de plateformes de gestion de rendez-vous et d'outils collaboratifs. Notre approche unique combine l'expertise technique internationale avec une compréhension profonde du marché local marocain.</p>
                        
                        <p>Aujourd'hui, nous sommes fiers de servir des centaines d'entreprises à travers le Maroc, en leur offrant des solutions sur mesure qui augmentent leur productivité et améliorent leur relation client.</p>
                    </div>
                    <div class="story-image">
                        🏢
                    </div>
                </div>
            </div>

            <!-- Nos Valeurs -->
            <div class="values-section">
                <h2>Nos Valeurs</h2>
                <div class="values-grid">
                    <div class="value-card">
                        <div class="value-icon">🚀</div>
                        <h3>Innovation</h3>
                        <p>Nous repoussons constamment les limites technologiques pour offrir des solutions avant-gardistes qui anticipent les besoins de demain.</p>
                    </div>
                    <div class="value-card">
                        <div class="value-icon">🎯</div>
                        <h3>Excellence</h3>
                        <p>Chaque projet est une opportunité de dépasser les attentes. Nous nous engageons à livrer uniquement des produits de la plus haute qualité.</p>
                    </div>
                    <div class="value-card">
                        <div class="value-icon">🤝</div>
                        <h3>Partenariat</h3>
                        <p>Nous croyons en des relations durables basées sur la confiance mutuelle, l'écoute active et l'accompagnement personnalisé de nos clients.</p>
                    </div>
                    <div class="value-card">
                        <div class="value-icon">🌍</div>
                        <h3>Impact Local</h3>
                        <p>Enracinés à Marrakech, nous contribuons activement au développement de l'écosystème technologique local et à la formation des talents.</p>
                    </div>
                </div>
            </div>

            <!-- Notre Équipe -->
            <div class="team-section">
                <h2>Notre Équipe</h2>
                <div class="team-grid">
                    <div class="team-member">
                        <div class="member-avatar">AM</div>
                        <h3>Ahmed Mansouri</h3>
                        <div class="role">Fondateur & CEO</div>
                        <p>Visionnaire passionné avec 15 ans d'expérience dans le développement logiciel et l'entrepreneuriat technologique.</p>
                    </div>
                    <div class="team-member">
                        <div class="member-avatar">SB</div>
                        <h3>Sofia Benali</h3>
                        <div class="role">Directrice Technique</div>
                        <p>Experte en architecture système et intelligence artificielle, elle supervise l'innovation technique de nos solutions.</p>
                    </div>
                    <div class="team-member">
                        <div class="member-avatar">YK</div>
                        <h3>Youssef Kabbaj</h3>
                        <div class="role">Responsable Développement</div>
                        <p>Développeur full-stack passionné, spécialisé dans les technologies web modernes et l'expérience utilisateur.</p>
                    </div>
                    <div class="team-member">
                        <div class="member-avatar">LT</div>
                        <h3>Laila Tazi</h3>
                        <div class="role">Responsable Client</div>
                        <p>Garante de la satisfaction client, elle assure l'accompagnement et le support de nos utilisateurs au quotidien.</p>
                    </div>
                </div>
            </div>

            <!-- Notre Mission -->
            <div class="mission-section">
                <h2>Notre Mission</h2>
                <p>Chez Am Soft, notre mission est de démocratiser l'accès aux technologies avancées pour toutes les entreprises, quelle que soit leur taille. Nous croyons que chaque organisation mérite des outils performants pour optimiser ses processus, améliorer la collaboration et offrir une expérience exceptionnelle à ses clients. À travers nos solutions intuitives et notre support dédié, nous accompagnons la transformation digitale du tissu économique marocain, en contribuant à son rayonnement sur la scène internationale.</p>
            </div>

            <!-- Contact -->
            <div class="contact-section">
                <h2>Nous Contacter</h2>
                <p>Basés au cœur de Marrakech, nous sommes toujours disponibles pour discuter de vos projets et vous accompagner dans votre transformation digitale.</p>
                
                <div class="contact-info">
                    <div class="contact-item">
                        <div class="contact-icon">📍</div>
                        <h3>Adresse</h3>
                        <p>Boulevard Mohammed VI<br>Gueliz, Marrakech 40000<br>Maroc</p>
                    </div>
                    <div class="contact-item">
                        <div class="contact-icon">📞</div>
                        <h3>Téléphone</h3>
                        <p>+212 5 24 00 00 00<br>+212 6 00 00 00 00</p>
                    </div>
                    <div class="contact-item">
                        <div class="contact-icon">📧</div>
                        <h3>Email</h3>
                        <p>contact@amsoft.ma<br>support@amsoft.ma</p>
                    </div>
                    <div class="contact-item">
                        <div class="contact-icon">🕒</div>
                        <h3>Horaires</h3>
                        <p>Lun - Ven: 9h - 18h<br>Sam: 9h - 13h</p>
                    </div>
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
        // Header scroll effect
        window.addEventListener('scroll', function() {
            const header = document.querySelector('header');
            if (window.scrollY > 100) {
                header.style.background = 'rgba(255, 255, 255, 0.98)';
            } else {
                header.style.background = 'rgba(255, 255, 255, 0.95)';
            }
        });

        // Intersection Observer pour les animations
        const observerOptions = {
            threshold: 0.1,
            rootMargin: '0px 0px -50px 0px'
        };

        const observer = new IntersectionObserver(function(entries) {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    entry.target.style.opacity = '1';
                    entry.target.style.transform = 'translateY(0)';
                }
            });
        }, observerOptions);

        // Observer tous les éléments avec animation
        document.querySelectorAll('.value-card, .team-member, .story-section, .mission-section').forEach(el => {
            el.style.opacity = '0';
            el.style.transform = 'translateY(30px)';
            el.style.transition = 'all 0.6s ease-out';
            observer.observe(el);
        });

        // Animation des cartes au survol
        document.querySelectorAll('.value-card').forEach(card => {
            card.addEventListener('mouseenter', function() {
                this.style.transform = 'translateY(-10px) scale(1.02)';
            });
            
            card.addEventListener('mouseleave', function() {
                this.style.transform = 'translateY(-10px) scale(1)';
            });
        });
    </script>
</body>
</html>