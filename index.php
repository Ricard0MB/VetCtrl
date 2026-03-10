<?php session_start(); ?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>VetCtrl · Iniciar Sesión</title>
    <!-- Ruta correcta a los estilos (public/css/) -->
    <link rel="stylesheet" href="public/css/style_auth.css">
    <style>
        body.fade-out {
            opacity: 0;
            transition: opacity 0.25s ease;
        }
    </style>
</head>
<body>
    <div class="auth-wrapper">
        <div class="auth-card">
            <!-- Imagen: ruta correcta (public/images/) -->
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

                <!-- Formulario: acción corregida a auth/login.php (sin public) -->
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
</div>
            </div>
        </div>
    </div>

    <script>
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
</body>
</html>