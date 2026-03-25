<?php session_start(); ?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>VetCtrl · Iniciar Sesión</title>
    <link rel="stylesheet" href="public/css/style_auth.css">
    <link rel="stylesheet" href="../public/css/responsive.css">
    <style>
        body.fade-out {
            opacity: 0;
            transition: opacity 0.25s ease;
        }

        /* ========== CENTRADO GENERAL Y FOOTER ========== */
        body {
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            margin: 0;
            padding: 20px;
            box-sizing: border-box;
        }

        .auth-wrapper {
            width: 100%;
            max-width: 1100px;
            margin: 0 auto 20px auto;
            position: relative;
            z-index: 1;
        }

        /* ========== PATITAS DECORATIVAS ========== */
        body::before {
            content: "🐾";
            font-size: 120px;
            opacity: 0.05;
            position: absolute;
            bottom: 20px;
            left: 20px;
            pointer-events: none;
            transform: rotate(-15deg);
            z-index: 0;
        }
        body::after {
            content: "🐾";
            font-size: 180px;
            opacity: 0.05;
            position: absolute;
            top: 20px;
            right: 20px;
            pointer-events: none;
            transform: rotate(15deg);
            z-index: 0;
        }

        /* ========== ESTILOS DEL TUTORIAL ========== */
        .tutorial-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.7);
            z-index: 1000;
            display: flex;
            justify-content: center;
            align-items: center;
            opacity: 0;
            visibility: hidden;
            transition: all 0.3s ease;
        }
        .tutorial-overlay.active {
            opacity: 1;
            visibility: visible;
        }
        .tutorial-modal {
            background: white;
            max-width: 550px;
            width: 90%;
            border-radius: 20px;
            box-shadow: 0 20px 35px rgba(0,0,0,0.2);
            overflow: hidden;
            position: relative;
            transform: translateY(20px);
            transition: transform 0.3s ease;
        }
        .tutorial-overlay.active .tutorial-modal {
            transform: translateY(0);
        }
        .tutorial-header {
            background: #1b4332;
            color: white;
            padding: 15px 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .tutorial-header h3 {
            margin: 0;
            font-size: 1.2rem;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .tutorial-header button {
            background: none;
            border: none;
            color: white;
            font-size: 1.5rem;
            cursor: pointer;
            opacity: 0.8;
            transition: opacity 0.2s;
        }
        .tutorial-content {
            padding: 25px;
            min-height: 300px;
        }
        .step-title {
            font-size: 1.4rem;
            font-weight: bold;
            color: #1b4332;
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .step-desc {
            font-size: 1rem;
            line-height: 1.5;
            color: #333;
            margin-bottom: 20px;
        }
        .step-desc a {
            color: #40916c;
            text-decoration: none;
            font-weight: bold;
        }
        .step-image-placeholder {
            background: #f0f0f0;
            border-radius: 12px;
            padding: 20px;
            text-align: center;
            margin: 15px 0;
            color: #6c757d;
        }
        .tutorial-footer {
            display: flex;
            justify-content: space-between;
            padding: 15px 25px 25px;
            border-top: 1px solid #e0e0e0;
        }
        .tutorial-footer button {
            padding: 8px 20px;
            border: none;
            border-radius: 40px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
        }
        .btn-prev { background: #e9ecef; color: #495057; }
        .btn-next { background: #40916c; color: white; }
        .btn-skip { background: transparent; color: #6c757d; border: 1px solid #ced4da; }
        .step-indicators {
            display: flex;
            justify-content: center;
            gap: 8px;
            margin-top: 10px;
        }
        .step-dot {
            width: 8px;
            height: 8px;
            background: #cbd5e0;
            border-radius: 50%;
            transition: background 0.2s;
        }
        .step-dot.active {
            background: #40916c;
            width: 24px;
            border-radius: 12px;
        }

        /* ========== PROMPT INICIAL ========== */
        .initial-prompt {
            position: fixed;
            bottom: 20px;
            right: 20px;
            background: white;
            border-radius: 12px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.15);
            padding: 15px 20px;
            max-width: 300px;
            z-index: 1001;
            display: flex;
            flex-direction: column;
            gap: 10px;
            border-left: 4px solid #40916c;
            transform: translateX(120%);
            transition: transform 0.4s ease;
        }
        .initial-prompt.show { transform: translateX(0); }
        .prompt-buttons { display: flex; gap: 10px; justify-content: flex-end; }
        .prompt-buttons button { padding: 6px 12px; border: none; border-radius: 6px; cursor: pointer; font-weight: 500; }
        .btn-yes { background: #40916c; color: white; }
        .btn-no { background: #f8f9fa; border: 1px solid #dee2e6; }

        footer {
            margin-top: 30px;
            text-align: center;
            font-size: 0.8rem;
            color: #2e7d32;
            background: rgba(255, 255, 255, 0.7);
            padding: 10px 20px;
            border-radius: 40px;
            backdrop-filter: blur(4px);
            z-index: 2;
        }

        /* ========== ESTILOS PARA EL TOGGLE DE CONTRASEÑA (IMÁGENES) ========== */
        .password-wrapper {
            position: relative;
            width: 100%;
            display: flex;
            align-items: center;
        }
        .password-wrapper input {
            width: 100%;
            padding-right: 45px;
        }
        .toggle-password {
            position: absolute;
            right: 10px;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            cursor: pointer;
            padding: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            opacity: 0.8;
            transition: opacity 0.2s;
            height: 30px;
            width: 30px;
        }
        .toggle-password:hover { opacity: 1; }
        .toggle-password img {
            max-width: 100%;
            max-height: 100%;
            object-fit: contain;
            pointer-events: none;
        }
    </style>
</head>
<body>
    <div class="auth-wrapper">
        <div class="auth-card">
            <div class="auth-image">
                <img src="public/images/fondo-mascotas.jpg" alt="Mascotas felices">
            </div>
            <div class="auth-form">
                <div class="brand">
                    <h1>VetCtrl</h1>
                </div>
                <h2>Iniciar Sesión</h2>
                
                <?php
                if (isset($_SESSION['login_error'])) {
                    echo '<div class="error-message">' . $_SESSION['login_error'] . '</div>';
                    unset($_SESSION['login_error']);
                }
                if (isset($_GET['msg']) && !empty($_GET['msg'])) {
                    echo '<div id="flash-msg" class="success-message">' . htmlspecialchars($_GET['msg']) . '</div>';
                }
                ?>

                <form id="loginForm" action="auth/login.php" method="POST">
                    <label for="user_input">Usuario o Correo</label>
                    <input type="text" id="user_input" name="user_input" placeholder="ejemplo@correo.com / usuario" required>

                    <label for="password">Contraseña</label>
                    <div class="password-wrapper">
                        <input type="password" id="password" name="password" placeholder="••••••••" required autocomplete="new-password">
                        <button type="button" id="togglePassword" class="toggle-password" aria-label="Mostrar contraseña">
                            <img src="public/images/perro.png" id="toggleIcon" alt="Icono mostrar">
                        </button>
                    </div>

                    <button type="submit" id="loginBtn" class="btn-submit">
                        <span class="btn-text">Ingresar</span>
                        <span class="btn-loader" style="display: none;">⏳</span>
                    </button>
                </form>

                <div class="auth-links">
                    <p>¿No tienes cuenta? <a href="auth/register.php">Regístrate</a></p>
                    <p><a href="public/forgot_password.php">¿Olvidaste tu contraseña?</a></p>
                    <p><a href="#" id="helpLink">¿No sabes qué hacer?</a></p>
                </div>
            </div>
        </div>
    </div>

    <div id="tutorialOverlay" class="tutorial-overlay">
        <div class="tutorial-modal">
            <div class="tutorial-header">
                <h3><i class="fas fa-graduation-cap"></i> Tutorial de VetCtrl</h3>
                <button id="closeTutorialBtn">&times;</button>
            </div>
            <div class="tutorial-content" id="tutorialContent"></div>
            <div class="tutorial-footer">
                <button id="prevStepBtn" class="btn-prev" style="visibility: hidden;">← Anterior</button>
                <button id="nextStepBtn" class="btn-next">Siguiente →</button>
            </div>
            <div class="step-indicators" id="stepIndicators"></div>
        </div>
    </div>

    <div id="initialPrompt" class="initial-prompt">
        <p>🎓 ¿Quieres conocer cómo usar VetCtrl?</p>
        <div class="prompt-buttons">
            <button id="promptYes" class="btn-yes">Sí, comenzar</button>
            <button id="promptNo" class="btn-no">No, gracias</button>
        </div>
    </div>

    <script>
        const steps = [
            { title: "📝 Registro", desc: `Crea una cuenta en <a href="auth/register.php">Regístrate</a>.` },
            { title: "🔑 Iniciar sesión", desc: "Ingresa tus credenciales para acceder." },
            { title: "🔄 Recuperación", desc: "Usa el enlace si olvidaste tu contraseña." },
            { title: "📧 Verificación", desc: "Revisa tu bandeja de entrada o SPAM." }
        ];

        let currentStep = 0;
        const overlay = document.getElementById('tutorialOverlay');
        const tutorialContent = document.getElementById('tutorialContent');
        const prevBtn = document.getElementById('prevStepBtn');
        const nextBtn = document.getElementById('nextStepBtn');
        const closeBtn = document.getElementById('closeTutorialBtn');
        const stepIndicators = document.getElementById('stepIndicators');

        function renderStep() {
            const step = steps[currentStep];
            tutorialContent.innerHTML = `
                <div class="step-title">${step.title}</div>
                <div class="step-desc">${step.desc}</div>
                <div class="step-image-placeholder">
                    <i class="fas fa-info-circle" style="font-size: 2.5rem; margin-bottom: 10px; display: block;"></i>
                    Sigue las instrucciones para continuar.
                </div>
            `;
            prevBtn.style.visibility = currentStep === 0 ? 'hidden' : 'visible';
            nextBtn.textContent = currentStep === steps.length - 1 ? 'Finalizar' : 'Siguiente →';
            updateDots();
        }

        function updateDots() {
            const dots = document.querySelectorAll('.step-dot');
            dots.forEach((dot, i) => dot.classList.toggle('active', i === currentStep));
        }

        function openTutorial() {
            currentStep = 0;
            renderStep();
            overlay.classList.add('active');
            document.getElementById('initialPrompt').classList.remove('show');
        }

        nextBtn.addEventListener('click', () => {
            if (currentStep < steps.length - 1) { currentStep++; renderStep(); }
            else { overlay.classList.remove('active'); localStorage.setItem('tutorial_seen', 'completed'); }
        });

        prevBtn.addEventListener('click', () => { if (currentStep > 0) { currentStep--; renderStep(); } });
        closeBtn.addEventListener('click', () => overlay.classList.remove('active'));
        document.getElementById('helpLink').addEventListener('click', (e) => { e.preventDefault(); openTutorial(); });
        document.getElementById('promptYes').addEventListener('click', openTutorial);
        document.getElementById('promptNo').addEventListener('click', () => {
            document.getElementById('initialPrompt').classList.remove('show');
            localStorage.setItem('tutorial_seen', 'skipped');
        });

        document.addEventListener('DOMContentLoaded', () => {
            steps.forEach((_, i) => {
                const dot = document.createElement('div');
                dot.classList.add('step-dot');
                if (i === 0) dot.classList.add('active');
                stepIndicators.appendChild(dot);
            });
            if (!localStorage.getItem('tutorial_seen')) {
                setTimeout(() => document.getElementById('initialPrompt').classList.add('show'), 1000);
            }
        });

        // ========== TOGGLE CONTRASEÑA (Lógica con rutas relativas a public/images/) ==========
        const togglePassword = document.getElementById('togglePassword');
        const passwordInput = document.getElementById('password');
        const toggleIcon = document.getElementById('toggleIcon');

        if (togglePassword && passwordInput && toggleIcon) {
            togglePassword.addEventListener('click', function() {
                const isPassword = passwordInput.getAttribute('type') === 'password';
                passwordInput.setAttribute('type', isPassword ? 'text' : 'password');
                
                if (isPassword) {
                    toggleIcon.src = 'public/images/cama.png'; // Perro atento
                    togglePassword.setAttribute('aria-label', 'Ocultar contraseña');
                } else {
                    toggleIcon.src = 'public/images/perro.png'; // Perro durmiendo
                    togglePassword.setAttribute('aria-label', 'Mostrar contraseña');
                }
            });
        }

        // ========== ENVÍO DE FORMULARIO ==========
        document.getElementById('loginForm').addEventListener('submit', function(e) {
            e.preventDefault();
            const btn = document.getElementById('loginBtn');
            btn.querySelector('.btn-text').style.display = 'none';
            btn.querySelector('.btn-loader').style.display = 'inline';
            btn.disabled = true;
            document.body.classList.add('fade-out');
            setTimeout(() => this.submit(), 250);
        });
    </script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <?php include 'includes/footer.php'; ?>
</body>
</html>
