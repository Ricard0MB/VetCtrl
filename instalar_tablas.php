<?php
// instalar_tablas.php - Ejecuta esto UNA SOLA VEZ
session_start();
require_once __DIR__ . '/includes/config.php';

echo "<h2>🔧 Instalando tablas en Aiven</h2>";

try {
    // 1. Verificar conexión
    echo "<h3>📡 Verificando conexión...</h3>";
    if ($conn) {
        echo "✅ Conexión exitosa a: " . getenv('DB_HOST') . "<br>";
    }
    
    // 2. Crear tabla users (con la estructura que usa tu login)
    echo "<h3>📦 Creando tabla 'users'...</h3>";
    
    $sql_users = "
    CREATE TABLE IF NOT EXISTS users (
        id INT AUTO_INCREMENT PRIMARY KEY,
        username VARCHAR(50) NOT NULL UNIQUE,
        email VARCHAR(100) NOT NULL UNIQUE,
        password VARCHAR(255) NOT NULL,
        role_id INT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_username (username),
        INDEX idx_email (email)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ";
    
    $conn->exec($sql_users);
    echo "✅ Tabla 'users' creada correctamente<br>";
    
    // 3. Crear tabla roles
    echo "<h3>📦 Creando tabla 'roles'...</h3>";
    
    $sql_roles = "
    CREATE TABLE IF NOT EXISTS roles (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(50) NOT NULL UNIQUE,
        description VARCHAR(255),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ";
    
    $conn->exec($sql_roles);
    echo "✅ Tabla 'roles' creada correctamente<br>";
    
    // 4. Insertar roles básicos
    echo "<h3>👥 Insertando roles básicos...</h3>";
    
    $check_roles = $conn->query("SELECT COUNT(*) FROM roles")->fetchColumn();
    if ($check_roles == 0) {
        $insert_roles = "
        INSERT INTO roles (name, description) VALUES
        ('admin', 'Administrador del sistema'),
        ('usuario', 'Usuario regular'),
        ('invitado', 'Invitado con acceso limitado');
        ";
        $conn->exec($insert_roles);
        echo "✅ Roles insertados correctamente<br>";
    } else {
        echo "ℹ️ Los roles ya existen, saltando inserción<br>";
    }
    
    // 5. Crear un usuario de prueba (opcional)
    echo "<h3>🔐 Creando usuario de prueba...</h3>";
    
    $check_users = $conn->query("SELECT COUNT(*) FROM users")->fetchColumn();
    if ($check_users == 0) {
        $hashed_password = password_hash('admin123', PASSWORD_DEFAULT);
        $insert_user = "
        INSERT INTO users (username, email, password, role_id) VALUES
        ('admin', 'admin@ejemplo.com', :password, 1)
        ";
        $stmt = $conn->prepare($insert_user);
        $stmt->execute([':password' => $hashed_password]);
        echo "✅ Usuario de prueba creado:<br>";
        echo "👤 Usuario: admin<br>";
        echo "🔑 Contraseña: admin123<br>";
    } else {
        echo "ℹ️ Ya existen usuarios, saltando creación<br>";
    }
    
    // 6. Verificar tablas creadas
    echo "<h3>📋 Verificando tablas en la base de datos:</h3>";
    $tables = $conn->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
    echo "<ul>";
    foreach ($tables as $table) {
        echo "<li>✅ $table</li>";
    }
    echo "</ul>";
    
    echo "<hr>";
    echo "<h3 style='color: green'>✅ INSTALACIÓN COMPLETADA CON ÉXITO</h3>";
    echo "<p>Ya puedes hacer login con:</p>";
    echo "<ul>";
    echo "<li><strong>Usuario:</strong> admin</li>";
    echo "<li><strong>Contraseña:</strong> admin123</li>";
    echo "</ul>";
    echo "<p><a href='index.php'>➡️ Ir al login</a></p>";
    
} catch (PDOException $e) {
    echo "<h3 style='color: red'>❌ ERROR:</h3>";
    echo "<pre>" . $e->getMessage() . "</pre>";
    
    // Mensaje específico según el error
    if (strpos($e->getMessage(), 'privileges') !== false) {
        echo "<p>⚠️ El usuario no tiene permisos para crear tablas.</p>";
    }
}
?>
