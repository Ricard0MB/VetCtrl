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

        /* ========== PATITAS DECORATIVAS (como en forgot_password) ========== */
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
        /* Ajuste para que el contenido esté por encima */
        .auth-wrapper {
            position: relative;
            z-index: 1;
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
                    <input type="password" id="password" name="password" placeholder="••••••••" required>

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
        // Definición de pasos del tutorial
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
        let manualStart = false;   // indica si se abrió manualmente (desde enlace ayuda)
        let skipFlag = localStorage.getItem('tutorial_seen'); // 'skipped', 'completed', o null

        // Elementos del DOM
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

        // Función para renderizar el paso actual
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
            // Control de botones
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
            // Actualizar indicadores
            const dots = document.querySelectorAll('.step-dot');
            dots.forEach((dot, i) => {
                if (i === currentStep) dot.classList.add('active');
                else dot.classList.remove('active');
            });
        }

        // Ayuda visual (iconos y texto de ejemplo)
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

        // Abrir el tutorial (desde inicio automático o manual)
        function openTutorial(startFrom = 0, fromManual = false) {
            currentStep = startFrom;
            renderStep();
            overlay.classList.add('active');
            tutorialActive = true;
            manualStart = fromManual;
            // Ocultar prompt inicial si está visible
            if (initialPrompt.classList.contains('show')) {
                initialPrompt.classList.remove('show');
            }
        }

        // Cerrar el tutorial
        function closeTutorial() {
            overlay.classList.remove('active');
            tutorialActive = false;
            // Si el tutorial se completó (no se cerró con X) y no fue inicio manual, guardar como completado
            if (!manualStart && currentStep === steps.length - 1 && nextBtn.textContent === 'Finalizar') {
                localStorage.setItem('tutorial_seen', 'completed');
            }
        }

        // Navegación
        function nextStep() {
            if (currentStep < steps.length - 1) {
                currentStep++;
                renderStep();
            } else {
                // Finalizar tutorial
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

        // Mostrar prompt inicial (solo si no se ha visto tutorial antes)
        function maybeShowInitialPrompt() {
            if (skipFlag === 'skipped' || skipFlag === 'completed') return;
            // Si ya ha sido completado o saltado, no mostramos
            setTimeout(() => {
                initialPrompt.classList.add('show');
            }, 1000);
        }

        // Eventos
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

        // Inicialización
        document.addEventListener('DOMContentLoaded', () => {
            // Generar indicadores de pasos
            for (let i = 0; i < steps.length; i++) {
                const dot = document.createElement('div');
                dot.classList.add('step-dot');
                if (i === 0) dot.classList.add('active');
                stepIndicators.appendChild(dot);
            }
            // Verificar si debe mostrar el prompt
            maybeShowInitialPrompt();

            // Cerrar prompt si se hace clic fuera (opcional)
            document.addEventListener('click', (e) => {
                if (initialPrompt.classList.contains('show') && !initialPrompt.contains(e.target)) {
                    // No cerramos automáticamente para no molestar, pero se puede implementar si se desea
                }
            });
        });

        // El resto del código original (fade-out y flash message) se mantiene
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
    <!-- Font Awesome (necesario para iconos) -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <!-- Footer agregado -->
    <?php include 'includes/footer.php'; ?>
</body>
</html>
