<?php session_start(); ?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>VetCtrl · Registro</title>
    <link rel="stylesheet" href="css/style_auth.css">
</head>
<body>
    <div class="auth-wrapper">
        <div class="auth-card">
            <!-- Imagen a la izquierda -->
            <div class="auth-image">
                <img src="images/fondo-mascotas.jpg" alt="Mascotas felices">
            </div>
            <!-- Formulario a la derecha -->
            <div class="auth-form">
                <div class="brand">
                    <h1>VetCtrl</h1>
                </div>
                <h2>Crear Cuenta</h2>
                
                <?php
                if (isset($_SESSION['registration_success'])) {
                    echo '<div class="success-message">' . $_SESSION['registration_success'] . '</div>';
                    unset($_SESSION['registration_success']);
                }
                if (isset($_SESSION['registration_error'])) {
                    echo '<div class="error-message">' . $_SESSION['registration_error'] . '</div>';
                    unset($_SESSION['registration_error']);
                }
                ?>

                <form action="../auth/register.php" method="POST">
                    <label for="nombre_completo">Nombre y Apellido</label>
                    <input type="text" id="nombre_completo" name="nombre_completo" placeholder="Ej: Juan Pérez" required>

                    <label for="cedula">Cédula de Identidad</label>
                    <input type="text" id="cedula" name="cedula" placeholder="Ej: V-12345678" required>

                    <label for="username">Nombre de Usuario</label>
                    <input type="text" id="username" name="username" placeholder="Ej: juanperez" required>

                    <label for="email">Correo Electrónico</label>
                    <input type="email" id="email" name="email" placeholder="tucorreo@ejemplo.com" required>

                    <label for="password">Contraseña</label>
                    <input type="password" id="password" name="password" placeholder="Mínimo 6 caracteres" required>

                    <label for="confirm_password">Confirmar Contraseña</label>
                    <input type="password" id="confirm_password" name="confirm_password" placeholder="Repite tu contraseña" required>

                    <button type="submit" class="btn-submit">Registrarse</button>
                </form>

                <div class="auth-links">
                    <p>¿Ya tienes cuenta? <a href="../index.php">Inicia sesión</a></p>
                </div>
            </div>
        </div>
    </div>
</body>
</html>