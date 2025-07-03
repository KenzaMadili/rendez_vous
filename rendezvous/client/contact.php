<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Contactez AM Soft - Solutions informatiques à Marrakech">
    <title>Contactez AM Soft - Solutions Informatiques</title>
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

        .header {
            text-align: center;
            color: white;
            margin-bottom: 40px;
            position: relative;
        }

        .header h1 {
            font-size: 3rem;
            font-weight: 700;
            margin-bottom: 15px;
            text-shadow: 0 4px 8px rgba(0, 0, 0, 0.3);
        }

        .header p {
            font-size: 1.2rem;
            opacity: 0.9;
            font-weight: 300;
        }

        .contact-wrapper {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 40px;
            margin-bottom: 30px;
        }

        .contact-info {
            background: rgba(255, 255, 255, 0.95);
            border-radius: 20px;
            padding: 40px;
            backdrop-filter: blur(10px);
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1);
            height: fit-content;
        }

        .contact-form {
            background: rgba(255, 255, 255, 0.95);
            border-radius: 20px;
            padding: 40px;
            backdrop-filter: blur(10px);
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1);
        }

        .section-title {
            font-size: 1.8rem;
            font-weight: 600;
            color: #4a5568;
            margin-bottom: 30px;
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .contact-item {
            display: flex;
            align-items: center;
            margin-bottom: 25px;
            padding: 20px;
            background: #f8f9ff;
            border-radius: 12px;
            border-left: 4px solid #667eea;
            transition: all 0.3s ease;
        }

        .contact-item:hover {
            transform: translateX(5px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.1);
        }

        .contact-icon {
            font-size: 2rem;
            margin-right: 20px;
            min-width: 60px;
            text-align: center;
        }

        .contact-details h3 {
            color: #4a5568;
            font-weight: 600;
            margin-bottom: 5px;
            font-size: 1.1rem;
        }

        .contact-details p {
            color: #718096;
            font-size: 1rem;
            line-height: 1.4;
        }

        .contact-details a {
            color: #667eea;
            text-decoration: none;
            transition: color 0.3s ease;
        }

        .contact-details a:hover {
            color: #764ba2;
            text-decoration: underline;
        }

        .form-group {
            margin-bottom: 25px;
        }

        .form-label {
            display: block;
            font-weight: 600;
            color: #4a5568;
            margin-bottom: 8px;
            font-size: 1rem;
        }

        .form-input, .form-textarea {
            width: 100%;
            padding: 15px 20px;
            border: 2px solid #e2e8f0;
            border-radius: 12px;
            font-size: 1rem;
            transition: all 0.3s ease;
            background: #f8f9ff;
            font-family: inherit;
        }

        .form-input:focus, .form-textarea:focus {
            outline: none;
            border-color: #667eea;
            background: white;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
            transform: translateY(-2px);
        }

        .form-textarea {
            min-height: 120px;
            resize: vertical;
        }

        .form-submit {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            padding: 15px 40px;
            border-radius: 25px;
            font-size: 1.1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 10px;
            margin: 0 auto;
        }

        .form-submit:hover {
            transform: translateY(-3px);
            box-shadow: 0 15px 30px rgba(102, 126, 234, 0.4);
        }

        .form-submit:disabled {
            opacity: 0.6;
            cursor: not-allowed;
            transform: none;
        }

        .back-button {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            background: rgba(255, 255, 255, 0.2);
            color: white;
            text-decoration: none;
            padding: 12px 24px;
            border-radius: 25px;
            font-weight: 600;
            transition: all 0.3s ease;
            backdrop-filter: blur(10px);
            border: 2px solid rgba(255, 255, 255, 0.3);
        }

        .back-button:hover {
            background: rgba(255, 255, 255, 0.3);
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(0, 0, 0, 0.2);
        }

        .company-info {
            background: rgba(255, 255, 255, 0.1);
            border-radius: 15px;
            padding: 25px;
            margin-top: 30px;
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.2);
        }

        .company-info h3 {
            color: #4a5568;
            font-weight: 600;
            margin-bottom: 15px;
            font-size: 1.2rem;
        }

        .company-info p {
            color: #718096;
            line-height: 1.6;
            margin-bottom: 10px;
        }

        .social-links {
            display: flex;
            gap: 15px;
            margin-top: 20px;
        }

        .social-link {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 45px;
            height: 45px;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 50%;
            color: #667eea;
            text-decoration: none;
            font-size: 1.2rem;
            transition: all 0.3s ease;
            border: 2px solid rgba(102, 126, 234, 0.3);
        }

        .social-link:hover {
            background: #667eea;
            color: white;
            transform: translateY(-3px);
            box-shadow: 0 8px 16px rgba(102, 126, 234, 0.3);
        }

        .success-message {
            background: #f0fff4;
            border: 2px solid #9ae6b4;
            color: #2f855a;
            padding: 15px 20px;
            border-radius: 12px;
            margin-bottom: 20px;
            display: none;
            font-weight: 500;
        }

        .error-message {
            background: #fed7d7;
            border: 2px solid #feb2b2;
            color: #c53030;
            padding: 15px 20px;
            border-radius: 12px;
            margin-bottom: 20px;
            display: none;
            font-weight: 500;
        }

        @media (max-width: 768px) {
            .contact-wrapper {
                grid-template-columns: 1fr;
                gap: 20px;
            }

            .header h1 {
                font-size: 2.5rem;
            }

            .contact-info, .contact-form {
                padding: 25px;
            }

            .contact-item {
                flex-direction: column;
                text-align: center;
                padding: 20px 15px;
            }

            .contact-icon {
                margin-right: 0;
                margin-bottom: 10px;
            }

            .form-submit {
                width: 100%;
                justify-content: center;
            }
        }

        @media (max-width: 480px) {
            .header h1 {
                font-size: 2rem;
            }

            .contact-info, .contact-form {
                padding: 20px;
            }

            .section-title {
                font-size: 1.5rem;
            }
        }

        /* Animation pour les éléments au chargement */
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

        .contact-info, .contact-form {
            animation: fadeInUp 0.6s ease-out;
        }

        .contact-form {
            animation-delay: 0.2s;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Contactez AM Soft</h1>
            <p>Votre partenaire technologique à Marrakech</p>
        </div>

        <div class="contact-wrapper">
            <div class="contact-info">
                <h2 class="section-title">
                    📍 Nos Coordonnées
                </h2>

                <div class="contact-item">
                    <div class="contact-icon">🏢</div>
                    <div class="contact-details">
                        <h3>Adresse</h3>
                        <p>Quartier Technopark<br>Marrakech, Maroc</p>
                    </div>
                </div>

                <div class="contact-item">
                    <div class="contact-icon">📧</div>
                    <div class="contact-details">
                        <h3>Email</h3>
                        <p><a href="mailto:contact@amsoft.ma">contact@amsoft.ma</a></p>
                    </div>
                </div>

                <div class="contact-item">
                    <div class="contact-icon">📞</div>
                    <div class="contact-details">
                        <h3>Téléphone</h3>
                        <p><a href="tel:+212600000000">+212 6 00 00 00 00</a></p>
                    </div>
                </div>

                <div class="contact-item">
                    <div class="contact-icon">🕒</div>
                    <div class="contact-details">
                        <h3>Horaires</h3>
                        <p>Lundi - Vendredi : 9h - 18h<br>Samedi : 9h - 13h</p>
                    </div>
                </div>

                <div class="company-info">
                    <h3>À propos d'AM Soft</h3>
                    <p>Spécialiste en solutions informatiques et développement logiciel depuis plus de 10 ans.</p>
                    <p>Nous accompagnons les entreprises dans leur transformation digitale.</p>
                    
                    <div class="social-links">
                        <a href="#" class="social-link" title="LinkedIn">💼</a>
                        <a href="#" class="social-link" title="Facebook">📘</a>
                        <a href="#" class="social-link" title="Twitter">🐦</a>
                        <a href="#" class="social-link" title="Instagram">📷</a>
                    </div>
                </div>
            </div>

            <div class="contact-form">
                <h2 class="section-title">
                    💬 Envoyez-nous un Message
                </h2>

                <div class="success-message" id="successMessage">
                    ✅ Votre message a été envoyé avec succès ! Nous vous répondrons dans les plus brefs délais.
                </div>

                <div class="error-message" id="errorMessage">
                    ❌ Une erreur est survenue. Veuillez réessayer.
                </div>

                <form action="send_message.php" method="POST" id="contactForm">
                    <div class="form-group">
                        <label for="nom" class="form-label">Votre nom *</label>
                        <input type="text" 
                               id="nom" 
                               name="nom" 
                               class="form-input" 
                               required
                               placeholder="Entrez votre nom complet">
                    </div>

                    <div class="form-group">
                        <label for="email" class="form-label">Votre email *</label>
                        <input type="email" 
                               id="email" 
                               name="email" 
                               class="form-input" 
                               required
                               placeholder="votre.email@exemple.com">
                    </div>

                    <div class="form-group">
                        <label for="telephone" class="form-label">Téléphone</label>
                        <input type="tel" 
                               id="telephone" 
                               name="telephone" 
                               class="form-input"
                               placeholder="+212 6 00 00 00 00">
                    </div>

                    <div class="form-group">
                        <label for="sujet" class="form-label">Sujet</label>
                        <select id="sujet" name="sujet" class="form-input">
                            <option value="">Sélectionnez un sujet</option>
                            <option value="devis">Demande de devis</option>
                            <option value="support">Support technique</option>
                            <option value="information">Demande d'information</option>
                            <option value="partenariat">Partenariat</option>
                            <option value="autre">Autre</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="message" class="form-label">Message *</label>
                        <textarea id="message" 
                                  name="message" 
                                  class="form-textarea" 
                                  required
                                  placeholder="Décrivez votre demande en détail..."></textarea>
                        <div style="text-align: right; font-size: 0.85rem; color: #718096; margin-top: 5px;">
                            <span id="charCount">0</span>/500 caractères
                        </div>
                    </div>

                    <button type="submit" class="form-submit" id="submitBtn">
                        <span>📤</span>
                        Envoyer le Message
                    </button>
                </form>
            </div>
        </div>

        <div style="text-align: center; margin-top: 30px;">
            <a href="dashboard.php" class="back-button">
                ⬅️ Retour au tableau de bord
            </a>
        </div>
    </div>

    <script>
        // Compteur de caractères pour le message
        const messageTextarea = document.getElementById('message');
        const charCount = document.getElementById('charCount');
        
        messageTextarea.addEventListener('input', function() {
            const currentLength = this.value.length;
            charCount.textContent = currentLength;
            
            if (currentLength > 500) {
                charCount.style.color = '#c53030';
                this.value = this.value.substring(0, 500);
                charCount.textContent = '500';
            } else if (currentLength > 450) {
                charCount.style.color = '#d69e2e';
            } else {
                charCount.style.color = '#718096';
            }
        });

        // Validation du formulaire
        document.getElementById('contactForm').addEventListener('submit', function(e) {
            const nom = document.getElementById('nom').value.trim();
            const email = document.getElementById('email').value.trim();
            const message = document.getElementById('message').value.trim();
            
            if (nom.length < 2) {
                alert('Le nom doit contenir au moins 2 caractères.');
                e.preventDefault();
                return;
            }
            
            if (!email || !email.includes('@') || !email.includes('.')) {
                alert('Veuillez saisir un email valide.');
                e.preventDefault();
                return;
            }
            
            if (message.length < 10) {
                alert('Le message doit contenir au moins 10 caractères.');
                e.preventDefault();
                return;
            }
            
            // Désactiver le bouton pour éviter les doubles soumissions
            const submitBtn = document.getElementById('submitBtn');
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<span>⏳</span> Envoi en cours...';
            
            // Réactiver après 5 secondes en cas d'erreur
            setTimeout(() => {
                submitBtn.disabled = false;
                submitBtn.innerHTML = '<span>📤</span> Envoyer le Message';
            }, 5000);
        });

        // Animation des éléments au scroll
        const observerOptions = {
            threshold: 0.1,
            rootMargin: '0px 0px -50px 0px'
        };

        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    entry.target.style.opacity = '1';
                    entry.target.style.transform = 'translateY(0)';
                }
            });
        }, observerOptions);

        document.querySelectorAll('.contact-item').forEach(item => {
            item.style.opacity = '0';
            item.style.transform = 'translateY(20px)';
            item.style.transition = 'all 0.6s ease';
            observer.observe(item);
        });

        // Effet de focus automatique sur le premier champ
        window.addEventListener('load', () => {
            document.getElementById('nom').focus();
        });
    </script>
</body>
</html>