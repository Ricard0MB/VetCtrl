<?php
session_start();
require_once '../includes/config.php';

// Verificar permisos
$user_role = $_SESSION['role_name'] ?? '';
if (!in_array($user_role, ['Veterinario', 'admin'])) {
    header("Location: welcome.php");
    exit;
}

// Obtener ID del empleado
$employee_id = intval($_GET['id'] ?? 0);

if ($employee_id <= 0) {
    header("Location: employee_list.php");
    exit;
}

// Consultar datos del empleado
$sql = "SELECT u.*, r.name as role_name 
        FROM users u 
        LEFT JOIN roles r ON u.role_id = r.id 
        WHERE u.id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $employee_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    header("Location: employee_list.php");
    exit;
}

$employee = $result->fetch_assoc();
$stmt->close();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Detalles de Empleado - VetControl</title>
    <link rel="stylesheet" href="../public/css/style.css">
    <style>
        body {
            padding-top: 80px;
            background-color: #f8f9fa;
            font-family: 'Segoe UI', Arial, sans-serif;
        }
        
        .container {
            max-width: 1000px;
            margin: 0 auto;
            padding: 20px;
        }
        
        .header {
            background: linear-gradient(135deg, #1b4332 0%, #2d6a4f 100%);
            color: white;
            padding: 25px;
            border-radius: 10px;
            margin-bottom: 30px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        }
        
        .header h1 {
            font-size: 2rem;
            margin-bottom: 10px;
            color: white;
        }
        
        .header-actions {
            display: flex;
            gap: 10px;
            margin-top: 15px;
        }
        
        .btn {
            padding: 10px 20px;
            border-radius: 6px;
            text-decoration: none;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        
        .btn-back {
            background: #6c757d;
            color: white;
        }
        
        .btn-edit {
            background: #0dcaf0;
            color: white;
        }
        
        .btn-list {
            background: #40916c;
            color: white;
        }
        
        .btn:hover {
            opacity: 0.9;
        }
        
        /* Tarjeta de perfil */
        .profile-card {
            background: white;
            border-radius: 10px;
            padding: 30px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.05);
            margin-bottom: 30px;
        }
        
        .profile-header {
            display: flex;
            align-items: center;
            gap: 25px;
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 2px solid #eee;
        }
        
        .profile-avatar {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            background: linear-gradient(135deg, #1b4332 0%, #2d6a4f 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 3rem;
            color: white;
        }
        
        .profile-info h2 {
            margin: 0;
            color: #1b4332;
            font-size: 1.8rem;
        }
        
        .profile-info .position {
            color: #40916c;
            font-size: 1.2rem;
            margin: 5px 0;
        }
        
        .status-badge {
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 0.9rem;
            font-weight: 600;
            display: inline-block;
        }
        
        .status-active {
            background: #d1e7dd;
            color: #0f5132;
        }
        
        .status-inactive {
            background: #fff3cd;
            color: #856404;
        }
        
        .status-suspended {
            background: #f8d7da;
            color: #842029;
        }
        
        /* Grid de información */
        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 25px;
        }
        
        .info-section {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            border-left: 4px solid #40916c;
        }
        
        .info-section h3 {
            color: #1b4332;
            margin-top: 0;
            margin-bottom: 15px;
            padding-bottom: 10px;
            border-bottom: 1px solid #dee2e6;
        }
        
        .info-item {
            margin-bottom: 15px;
        }
        
        .info-item:last-child {
            margin-bottom: 0;
        }
        
        .info-label {
            font-weight: 600;
            color: #495057;
            display: block;
            margin-bottom: 5px;
        }
        
        .info-value {
            color: #212529;
            padding: 8px;
            background: white;
            border-radius: 4px;
            border: 1px solid #dee2e6;
            min-height: 38px;
            display: flex;
            align-items: center;
        }
        
        .empty-value {
            color: #6c757d;
            font-style: italic;
        }
        
        /* Responsive */
        @media (max-width: 768px) {
            .profile-header {
                flex-direction: column;
                text-align: center;
            }
            
            .info-grid {
                grid-template-columns: 1fr;
            }
            
            .header-actions {
                flex-wrap: wrap;
            }
        }
    </style>
</head>
<body>
    <?php include '../includes/navbar.php'; ?>
    
    <div class="container">
        <div class="header">
            <h1>👤 Detalles del Empleado</h1>
            <div class="header-actions">
                <a href="employee_list.php" class="btn btn-back">← Volver a la lista</a>
                <a href="employee_edit.php?id=<?php echo $employee_id; ?>" class="btn btn-edit">✏️ Editar Empleado</a>
                <a href="employee_list.php" class="btn btn-list">📋 Ver todos</a>
            </div>
        </div>
        
        <!-- Tarjeta de perfil -->
        <div class="profile-card">
            <div class="profile-header">
                <div class="profile-avatar">
                    <?php echo strtoupper(substr($employee['first_name'] ?? 'E', 0, 1) . substr($employee['last_name'] ?? 'M', 0, 1)); ?>
                </div>
                <div class="profile-info">
                    <h2><?php echo htmlspecialchars($employee['first_name'] . ' ' . $employee['last_name']); ?></h2>
                    <div class="position"><?php echo htmlspecialchars($employee['position']); ?></div>
                    <div>
                        <?php 
                        $status_class = 'status-' . $employee['status'];
                        $status_text = ucfirst($employee['status']);
                        ?>
                        <span class="status-badge <?php echo $status_class; ?>">
                            <?php echo $status_text; ?>
                        </span>
                        <span style="margin-left: 10px; color: #6c757d;">
                            <?php echo htmlspecialchars($employee['role_name']); ?>
                        </span>
                    </div>
                </div>
            </div>
            
            <!-- Grid de información -->
            <div class="info-grid">
                <!-- Información Personal -->
                <div class="info-section">
                    <h3>📝 Información Personal</h3>
                    
                    <div class="info-item">
                        <span class="info-label">Cédula de Identidad:</span>
                        <div class="info-value">
                            <?php echo htmlspecialchars($employee['ci'] ?? '<span class="empty-value">No especificada</span>'); ?>
                        </div>
                    </div>
                    
                    <div class="info-item">
                        <span class="info-label">Email:</span>
                        <div class="info-value">
                            <?php echo htmlspecialchars($employee['email']); ?>
                        </div>
                    </div>
                    
                    <div class="info-item">
                        <span class="info-label">Teléfono:</span>
                        <div class="info-value">
                            <?php echo htmlspecialchars($employee['phone'] ?? '<span class="empty-value">No especificado</span>'); ?>
                        </div>
                    </div>
                    
                    <div class="info-item">
                        <span class="info-label">Dirección:</span>
                        <div class="info-value">
                            <?php echo htmlspecialchars($employee['address'] ?? '<span class="empty-value">No especificada</span>'); ?>
                        </div>
                    </div>
                </div>
                
                <!-- Información Laboral -->
                <div class="info-section">
                    <h3>💼 Información Laboral</h3>
                    
                    <div class="info-item">
                        <span class="info-label">Cargo/Puesto:</span>
                        <div class="info-value">
                            <?php echo htmlspecialchars($employee['position']); ?>
                        </div>
                    </div>
                    
                    <div class="info-item">
                        <span class="info-label">Rol en el Sistema:</span>
                        <div class="info-value">
                            <?php echo htmlspecialchars($employee['role_name']); ?> (ID: <?php echo $employee['role_id']; ?>)
                        </div>
                    </div>
                    
                    <div class="info-item">
                        <span class="info-label">Estado:</span>
                        <div class="info-value">
                            <span class="status-badge <?php echo 'status-' . $employee['status']; ?>">
                                <?php echo ucfirst($employee['status']); ?>
                            </span>
                        </div>
                    </div>
                </div>
                
                <!-- Información de Acceso -->
                <div class="info-section">
                    <h3>🔑 Información de Acceso</h3>
                    
                    <div class="info-item">
                        <span class="info-label">Nombre de Usuario:</span>
                        <div class="info-value">
                            <?php echo htmlspecialchars($employee['username']); ?>
                        </div>
                    </div>
                    
                    <div class="info-item">
                        <span class="info-label">Fecha de Registro:</span>
                        <div class="info-value">
                            <?php echo date('d/m/Y H:i', strtotime($employee['created_at'])); ?>
                        </div>
                    </div>
                    
                    <div class="info-item">
                        <span class="info-label">ID de Usuario:</span>
                        <div class="info-value">
                            <?php echo $employee['id']; ?>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Sección de notas adicionales -->
            <div style="margin-top: 30px; padding: 20px; background: #fff3cd; border-radius: 6px; border-left: 4px solid #ffc107;">
                <h4 style="margin-top: 0; color: #856404;">ℹ️ Notas importantes</h4>
                <p style="margin-bottom: 0; color: #856404;">
                    • Solo usuarios con rol de Veterinario o Administrador pueden ver esta información.<br>
                    • La contraseña no se muestra por razones de seguridad.<br>
                    • Para cambiar el estado del empleado, utilice la opción de edición.
                </p>
            </div>
        </div>
        
        <!-- Botones de acción -->
        <div style="display: flex; gap: 15px; justify-content: center; margin-top: 20px;">
            <a href="employee_edit.php?id=<?php echo $employee_id; ?>" 
               class="btn" style="background: #0dcaf0; color: white; text-decoration: none;">
                ✏️ Editar Información
            </a>
            <a href="employee_list.php" 
               class="btn" style="background: #6c757d; color: white; text-decoration: none;">
                📋 Volver a la Lista
            </a>
            <?php if (in_array($user_role, ['admin'])): ?>
                <a href="employee_delete.php?id=<?php echo $employee_id; ?>" 
                   class="btn" style="background: #dc3545; color: white; text-decoration: none;"
                   onclick="return confirm('¿Está seguro de eliminar este empleado? Esta acción no se puede deshacer.')">
                    🗑️ Eliminar Empleado
                </a>
            <?php endif; ?>
        </div>
    </div>
    
    <?php include '../includes/footer.php'; ?>
    
    <script>
        // Confirmación para eliminar
        document.addEventListener('DOMContentLoaded', function() {
            const deleteLinks = document.querySelectorAll('a[href*="employee_delete"]');
            deleteLinks.forEach(link => {
                link.addEventListener('click', function(e) {
                    if (!confirm('¿Está completamente seguro de eliminar este empleado?\n\nEsta acción: \n• Eliminará permanentemente el registro\n• No podrá ser deshecha\n• Afectará cualquier dato relacionado')) {
                        e.preventDefault();
                    }
                });
            });
        });
    </script>
</body>
</html>
<?php
$conn->close();
?>