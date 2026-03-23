<?php session_start(); ?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>VetCtrl · Iniciar Sesión</title>
    <link rel="stylesheet" href="public/css/style_auth.css">
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

        .auth-wrapper {
            position: relative;
            z-index: 1;
        }

        /* ========== ESTILOS DEL TUTORIAL (sin cambios) ========== */
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
        .tutorial-header button:hover {
            opacity: 1;
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
        .step-desc a:hover {
            text-decoration: underline;
        }
        .step-image-placeholder {
            background: #f0f0f0;
            border-radius: 12px;
            padding: 20px;
            text-align: center;
            margin: 15px 0;
            color: #6c757d;
            font-size: 0.9rem;
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
        .btn-prev {
            background: #e9ecef;
            color: #495057;
        }
        .btn-prev:hover {
            background: #dee2e6;
        }
        .btn-next {
            background: #40916c;
            color: white;
        }
        .btn-next:hover {
            background: #2d6a4f;
        }
        .btn-skip {
            background: transparent;
            color: #6c757d;
            border: 1px solid #ced4da;
        }
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
        .initial-prompt.show {
            transform: translateX(0);
        }
        .initial-prompt p {
            margin: 0;
            font-weight: 500;
        }
        .prompt-buttons {
            display: flex;
            gap: 10px;
            justify-content: flex-end;
        }
        .prompt-buttons button {
            padding: 6px 12px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 500;
        }
        .btn-yes {
            background: #40916c;
            color: white;
        }
        .btn-no {
            background: #f8f9fa;
            border: 1px solid #dee2e6;
        }
        footer {
            margin-top: 30px;
            text-align: center;
            font-size: 0.8rem;
            color: #2e7d32;
            background: rgba(255, 255, 255, 0.7);
            padding: 10px 20px;
            border-radius: 40px;
            backdrop-filter: blur(4px);
            width: auto;
            z-index: 2;
        }
        footer p { margin: 0; }
        @media (max-width: 550px) {
            .container { padding: 30px 20px; }
            h1 { font-size: 1.6rem; }
        }

        /* ========== ESTILOS PARA EL TOGGLE DE CONTRASEÑA ========== */
        .password-wrapper {
            position: relative;
            width: 100%;
        }
        .password-wrapper input {
            width: 100%;
            padding-right: 45px; /* espacio para el botón */
        }
        .toggle-password {
            position: absolute;
            right: 12px;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            cursor: pointer;
            font-size: 1.4rem;
            padding: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            opacity: 0.7;
            transition: opacity 0.2s;
        }
        .toggle-password:hover {
            opacity: 1;
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
                        <input type="password" id="password" name="password" placeholder="••••••••" required>
                        <button type="button" id="togglePassword" class="toggle-password" aria-label="Mostrar contraseña">
                            🐶🙈
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

    <!-- Contenedor del tutorial -->
    <div id="tutorialOverlay" class="tutorial-overlay">
        <div class="tutorial-modal">
            <div class="tutorial-header">
                <h3><i class="fas fa-graduation-cap"></i> Tutorial de VetCtrl</h3>
                <button id="closeTutorialBtn">&times;</button>
            </div>
            <div class="tutorial-content" id="tutorialContent">
                <!-- contenido dinámico -->
            </div>
            <div class="tutorial-footer">
                <button id="prevStepBtn" class="btn-prev" style="visibility: hidden;">← Anterior</button>
                <button id="nextStepBtn" class="btn-next">Siguiente →</button>
            </div>
            <div class="step-indicators" id="stepIndicators"></div>
        </div>
    </div>

    <!-- Prompt inicial (se mostrará solo la primera vez) -->
    <div id="initialPrompt" class="initial-prompt">
        <p>🎓 ¿Quieres conocer cómo usar VetCtrl?</p>
        <div class="prompt-buttons">
            <button id="promptYes" class="btn-yes">Sí, comenzar</button>
            <button id="promptNo" class="btn-no">No, gracias</button>
        </div>
    </div>

    <script>
        // Definición de pasos del tutorial (sin cambios)
        const steps = [
            {
                title: "📝 Registro de cuenta",
                desc: `¿Eres nuevo? Haz clic en <a href="auth/register.php">Regístrate</a> para crear una cuenta. Completa tus datos y recuerda verificar tu correo electrónico.`
            },
            {
                title: "🔑 Iniciar sesión",
                desc: "Una vez registrado, ingresa tu usuario o correo y tu contraseña en el formulario principal. Presiona el botón 'Ingresar' para acceder al sistema."
            },
            {
                title: "🔄 Cambiar contraseña",
                desc: "Si olvidaste tu contraseña, haz clic en <strong>¿Olvidaste tu contraseña?</strong> (debajo del formulario). Recibirás un enlace de recuperación por correo."
            },
            {
                title: "📧 Verificar tu correo",
                desc: "Cuando te registres o solicites cambio de contraseña, revisa tu bandeja de entrada y también la carpeta de <strong>SPAM / Correo no deseado</strong>. Allí encontrarás los enlaces de confirmación."
            }
        ];

        let currentStep = 0;
        let tutorialActive = false;
        let manualStart = false;
        let skipFlag = localStorage.getItem('tutorial_seen');

        const overlay = document.getElementById('tutorialOverlay');
        const tutorialContent = document.getElementById('tutorialContent');
        const prevBtn = document.getElementById('prevStepBtn');
        const nextBtn = document.getElementById('nextStepBtn');
        const closeBtn = document.getElementById('closeTutorialBtn');
        const stepIndicators = document.getElementById('stepIndicators');
        const initialPrompt = document.getElementById('initialPrompt');
        const promptYes = document.getElementById('promptYes');
        const promptNo = document.getElementById('promptNo');
        const helpLink = document.getElementById('helpLink');

        function renderStep() {
            const step = steps[currentStep];
            tutorialContent.innerHTML = `
                <div class="step-title">${step.title}</div>
                <div class="step-desc">${step.desc}</div>
                <div class="step-image-placeholder">
                    <i class="fas fa-${getIconForStep(currentStep)}" style="font-size: 2.5rem; margin-bottom: 10px; display: block;"></i>
                    ${getStepImageHint(currentStep)}
                </div>
            `;
            prevBtn.style.visibility = currentStep === 0 ? 'hidden' : 'visible';
            if (currentStep === steps.length - 1) {
                nextBtn.textContent = 'Finalizar';
                nextBtn.classList.add('btn-skip');
                nextBtn.classList.remove('btn-next');
            } else {
                nextBtn.textContent = 'Siguiente →';
                nextBtn.classList.remove('btn-skip');
                nextBtn.classList.add('btn-next');
            }
            const dots = document.querySelectorAll('.step-dot');
            dots.forEach((dot, i) => {
                if (i === currentStep) dot.classList.add('active');
                else dot.classList.remove('active');
            });
        }

        function getIconForStep(stepIndex) {
            const icons = ['user-plus', 'sign-in-alt', 'key', 'envelope'];
            return icons[stepIndex];
        }
        function getStepImageHint(stepIndex) {
            const hints = [
                '📍 Busca el botón "Regístrate" en la parte inferior.',
                '📍 Usuario o correo + contraseña → presiona "Ingresar".',
                '📍 Enlace "¿Olvidaste tu contraseña?" justo debajo del formulario.',
                '📍 Si no ves el correo, revisa la carpeta de SPAM.'
            ];
            return hints[stepIndex];
        }

        function openTutorial(startFrom = 0, fromManual = false) {
            currentStep = startFrom;
            renderStep();
            overlay.classList.add('active');
            tutorialActive = true;
            manualStart = fromManual;
            if (initialPrompt.classList.contains('show')) {
                initialPrompt.classList.remove('show');
            }
        }

        function closeTutorial() {
            overlay.classList.remove('active');
            tutorialActive = false;
            if (!manualStart && currentStep === steps.length - 1 && nextBtn.textContent === 'Finalizar') {
                localStorage.setItem('tutorial_seen', 'completed');
            }
        }

        function nextStep() {
            if (currentStep < steps.length - 1) {
                currentStep++;
                renderStep();
            } else {
                if (!manualStart) {
                    localStorage.setItem('tutorial_seen', 'completed');
                }
                closeTutorial();
            }
        }
        function prevStep() {
            if (currentStep > 0) {
                currentStep--;
                renderStep();
            }
        }

        function maybeShowInitialPrompt() {
            if (skipFlag === 'skipped' || skipFlag === 'completed') return;
            setTimeout(() => {
                initialPrompt.classList.add('show');
            }, 1000);
        }

        promptYes.addEventListener('click', () => {
            initialPrompt.classList.remove('show');
            openTutorial(0, false);
        });
        promptNo.addEventListener('click', () => {
            initialPrompt.classList.remove('show');
            localStorage.setItem('tutorial_seen', 'skipped');
        });
        helpLink.addEventListener('click', (e) => {
            e.preventDefault();
            openTutorial(0, true);
        });
        nextBtn.addEventListener('click', nextStep);
        prevBtn.addEventListener('click', prevStep);
        closeBtn.addEventListener('click', () => {
            closeTutorial();
        });

        document.addEventListener('DOMContentLoaded', () => {
            for (let i = 0; i < steps.length; i++) {
                const dot = document.createElement('div');
                dot.classList.add('step-dot');
                if (i === 0) dot.classList.add('active');
                stepIndicators.appendChild(dot);
            }
            maybeShowInitialPrompt();
        });

        // ========== TOGGLE CONTRASEÑA (perrito con orejas) ==========
        const togglePassword = document.getElementById('togglePassword');
        const passwordInput = document.getElementById('password');

        if (togglePassword && passwordInput) {
            togglePassword.addEventListener('click', function() {
                const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
                passwordInput.setAttribute('type', type);
                // Cambiar el ícono del perrito
                if (type === 'text') {
                    // Contraseña visible: perrito con ojos abiertos
                    togglePassword.textContent = '🐶👀';
                    togglePassword.setAttribute('aria-label', 'Ocultar contraseña');
                } else {
                    // Contraseña oculta: perrito tapándose los ojos con las orejas
                    togglePassword.textContent = '🐶🙈';
                    togglePassword.setAttribute('aria-label', 'Mostrar contraseña');
                }
            });
        }

        // ========== FLASH MESSAGE Y FADE-OUT (sin cambios) ==========
        document.addEventListener('DOMContentLoaded', function() {
            const el = document.getElementById('flash-msg');
            if (el) {
                setTimeout(() => {
                    el.style.transition = 'opacity 0.5s';
                    el.style.opacity = '0';
                    setTimeout(() => {
                        el.remove();
                        const url = new URL(window.location);
                        url.searchParams.delete('msg');
                        window.history.replaceState({}, document.title, url.pathname + url.search + url.hash);
                    }, 550);
                }, 4000);
            }

            const form = document.getElementById('loginForm');
            const btn = document.getElementById('loginBtn');
            const btnText = btn.querySelector('.btn-text');
            const btnLoader = btn.querySelector('.btn-loader');

            form.addEventListener('submit', function(e) {
                e.preventDefault();
                btnText.style.display = 'none';
                btnLoader.style.display = 'inline';
                btn.disabled = true;
                btn.style.opacity = '0.8';
                document.body.classList.add('fade-out');
                setTimeout(() => {
                    form.submit();
                }, 250);
            });
        });
    </script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <?php include 'includes/footer.php'; ?>
</body>
</html>
