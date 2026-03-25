<?php
session_start();

if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header("Location: ../index.php");
    exit;
}

require_once '../includes/config.php'; // $conn es un objeto PDO

$username = $_SESSION["username"] ?? 'Veterinario';
$user_id = $_SESSION['user_id'] ?? 0;
$role_name = $_SESSION['role_name'] ?? 'Propietario';

// Solo veterinario y admin pueden editar consultas (según matriz)
if (!in_array($role_name, ['Veterinario', 'admin'])) {
    header("Location: welcome.php?error=access_denied");
    exit;
}

// Cargar configuración del sistema (horario laboral)
$config = [
    'horario_apertura' => '08:00',
    'horario_cierre' => '18:00',
    'dias_trabajo' => 'Lunes a Viernes'
];
$stmtConfig = $conn->query("SELECT config_key, config_value FROM system_config WHERE config_key IN ('horario_apertura', 'horario_cierre', 'dias_trabajo')");
while ($row = $stmtConfig->fetch(PDO::FETCH_ASSOC)) {
    $config[$row['config_key']] = $row['config_value'];
}

// Función para parsear días laborables en array de números (1=lunes, 7=domingo)
function parseWorkingDays($daysString) {
    $daysMap = [
        'Lunes' => 1, 'Martes' => 2, 'Miércoles' => 3, 'Miercoles' => 3,
        'Jueves' => 4, 'Viernes' => 5, 'Sábado' => 6, 'Sabado' => 6,
        'Domingo' => 7
    ];
    $daysString = trim($daysString);
    // Rango con "a"
    if (preg_match('/([a-zA-ZáéíóúÁÉÍÓÚ]+)\s*a\s*([a-zA-ZáéíóúÁÉÍÓÚ]+)/i', $daysString, $matches)) {
        $start = $matches[1];
        $end = $matches[2];
        if (isset($daysMap[$start]) && isset($daysMap[$end])) {
            $startNum = $daysMap[$start];
            $endNum = $daysMap[$end];
            $result = [];
            for ($i = $startNum; $i <= $endNum; $i++) {
                $result[] = $i;
            }
            return $result;
        }
    }
    // Lista separada por comas o espacios
    $parts = preg_split('/[,\s]+/', $daysString);
    $result = [];
    foreach ($parts as $part) {
        $part = trim($part);
        if (isset($daysMap[$part])) {
            $result[] = $daysMap[$part];
        }
    }
    return $result;
}

$allowedDays = parseWorkingDays($config['dias_trabajo']);

// Función para validar fecha/hora según horario laboral
function isWithinWorkingHours($datetime, $open, $close, $allowedDays) {
    $timestamp = strtotime($datetime);
    if (!$timestamp) return false;
    
    $dayOfWeek = date('N', $timestamp); // 1=Monday, 7=Sunday
    if (!in_array($dayOfWeek, $allowedDays)) {
        return false;
    }
    
    $hour = date('H:i', $timestamp);
    return ($hour >= $open && $hour <= $close);
}

$consultation = null;
$pets = [];
$error = '';
$consultation_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($consultation_id <= 0) {
    header("Location: consultation_history.php?error=invalid");
    exit;
}

try {
    // Obtener lista de mascotas (todas, para selección)
    $stmtPets = $conn->query("SELECT id, name FROM pets ORDER BY name ASC");
    $pets = $stmtPets->fetchAll(PDO::FETCH_ASSOC);

    // Obtener datos de la consulta
    $sql = "SELECT * FROM consultations WHERE id = :id";
    $stmt = $conn->prepare($sql);
    $stmt->bindValue(':id', $consultation_id, PDO::PARAM_INT);
    $stmt->execute();
    $consultation = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$consultation) {
        header("Location: consultation_history.php?error=notfound");
        exit;
    }

    // Verificar que el usuario pueda editar (attendant_id debe coincidir o ser admin)
    if ($role_name !== 'admin' && $consultation['attendant_id'] != $user_id) {
        header("Location: consultation_history.php?error=unauthorized");
        exit;
    }

} catch (PDOException $e) {
    $error = "Error al cargar datos: " . $e->getMessage();
}

// Procesar formulario
if ($_SERVER["REQUEST_METHOD"] == "POST" && !$error) {
    $pet_id = intval($_POST['pet_id'] ?? 0);
    $consultation_date = $_POST['consultation_date'] ?? '';
    $diagnosis = trim($_POST['diagnosis'] ?? '');
    $treatment = trim($_POST['treatment'] ?? '');
    $notes = trim($_POST['notes'] ?? '');

    if ($pet_id <= 0 || empty($consultation_date) || empty($diagnosis)) {
        $error = "Los campos mascota, fecha y diagnóstico son obligatorios.";
    } else {
        $timestamp = strtotime($consultation_date);
        if (!$timestamp) {
            $error = "Fecha inválida.";
        } else {
            $formatted_date = date('Y-m-d H:i:s', $timestamp);
            
            // Validar horario laboral (aunque sea fecha pasada, se valida)
            if (!isWithinWorkingHours($formatted_date, $config['horario_apertura'], $config['horario_cierre'], $allowedDays)) {
                $error = "La fecha y hora deben estar dentro del horario laboral ({$config['horario_apertura']} a {$config['horario_cierre']}) y en días laborables (" . implode(', ', array_map(function($d) {
                    $daysNames = ['', 'Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado', 'Domingo'];
                    return $daysNames[$d];
                }, $allowedDays)) . ").";
            }
        }
        
        if (empty($error)) {
            try {
                $sql_update = "UPDATE consultations SET pet_id = :pet_id, consultation_date = :date, diagnosis = :diagnosis, treatment = :treatment, notes = :notes WHERE id = :id";
                $stmtUpdate = $conn->prepare($sql_update);
                $stmtUpdate->bindValue(':pet_id', $pet_id, PDO::PARAM_INT);
                $stmtUpdate->bindValue(':date', $formatted_date, PDO::PARAM_STR);
                $stmtUpdate->bindValue(':diagnosis', $diagnosis, PDO::PARAM_STR);
                $stmtUpdate->bindValue(':treatment', $treatment, PDO::PARAM_STR);
                $stmtUpdate->bindValue(':notes', $notes, PDO::PARAM_STR);
                $stmtUpdate->bindValue(':id', $consultation_id, PDO::PARAM_INT);
                $stmtUpdate->execute();

                require_once '../includes/bitacora_function.php';
                $action = "Consulta #$consultation_id actualizada";
                log_to_bitacora($conn, $action, $username, $_SESSION['role_id'] ?? 0);

                header("Location: consultation_view.php?id=$consultation_id&success=updated");
                exit;
            } catch (PDOException $e) {
                $error = "Error al actualizar: " . $e->getMessage();
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Editar Consulta - VetCtrl</title>
    <link rel="stylesheet" href="../public/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { background-color: #f4f4f4; padding-top: 70px; font-family: 'Segoe UI', sans-serif; }
        .breadcrumb { max-width: 800px; margin: 10px auto 0; padding: 10px 20px; background: transparent; font-size: 0.95rem; }
        .breadcrumb a { color: #40916c; text-decoration: none; }
        .breadcrumb a:hover { text-decoration: underline; }
        .breadcrumb span { color: #6c757d; }
        .container { max-width: 800px; margin: 20px auto; background: white; padding: 30px; border-radius: 12px; box-shadow: 0 4px 12px rgba(0,0,0,0.08); }
        h1 { color: #1b4332; border-bottom: 2px solid #b68b40; padding-bottom: 10px; margin-bottom: 25px; display: flex; align-items: center; gap: 10px; }
        .alert { padding: 15px 20px; border-radius: 8px; margin-bottom: 20px; border-left: 5px solid; display: flex; align-items: center; gap: 12px; }
        .alert i { font-size: 1.4rem; }
        .alert-danger { background: #f8d7da; color: #721c24; border-left-color: #dc3545; }
        label { display: block; margin: 15px 0 5px; font-weight: 600; color: #1b4332; }
        select, input[type="datetime-local"], textarea { width: 100%; padding: 10px; border: 2px solid #e0e0e0; border-radius: 6px; }
        select:focus, input:focus, textarea:focus { border-color: #40916c; outline: none; }
        .btn { padding: 12px 25px; border: none; border-radius: 6px; font-weight: 600; cursor: pointer; background: #40916c; color: white; }
        .btn:hover { background: #2d6a4f; }
        .btn-secondary { background: #6c757d; text-decoration: none; display: inline-block; margin-top: 20px; }
        .btn-secondary:hover { background: #5a6268; }
        .help-text { font-size: 0.85rem; color: #6c757d; margin-top: 5px; }
    </style>
    <script>
        // Configuración desde PHP
        const openTime = "<?php echo $config['horario_apertura']; ?>";
        const closeTime = "<?php echo $config['horario_cierre']; ?>";
        const workingDaysText = "<?php echo $config['dias_trabajo']; ?>";
        const allowedDaysNumbers = <?php echo json_encode($allowedDays); ?>;

        function parseTimeToMinutes(timeStr) {
            let parts = timeStr.split(':');
            return parseInt(parts[0]) * 60 + parseInt(parts[1]);
        }

        function getDayNumber(date) {
            let day = date.getDay(); // 0=domingo, 1=lunes, ..., 6=sábado
            return day === 0 ? 7 : day;
        }

        function isWorkingDay(date) {
            let dayNum = getDayNumber(date);
            return allowedDaysNumbers.includes(dayNum);
        }

        function isWithinWorkingHours(date) {
            const hours = date.getHours();
            const minutes = date.getMinutes();
            const totalMinutes = hours * 60 + minutes;
            const openMinutes = parseTimeToMinutes(openTime);
            const closeMinutes = parseTimeToMinutes(closeTime);
            return (totalMinutes >= openMinutes && totalMinutes <= closeMinutes);
        }

        function validateDateTime() {
            const input = document.getElementById('consultation_date');
            if (!input.value) return true;
            let selectedDate = new Date(input.value);
            // No se valida que sea futura, solo horario laboral
            if (!isWorkingDay(selectedDate)) {
                alert('La fecha debe ser un día laborable (' + workingDaysText + ').');
                input.value = '';
                return false;
            }
            if (!isWithinWorkingHours(selectedDate)) {
                alert('La hora debe estar dentro del horario laboral (' + openTime + ' a ' + closeTime + ').');
                input.value = '';
                return false;
            }
            return true;
        }

        document.addEventListener('DOMContentLoaded', function() {
            const dateInput = document.getElementById('consultation_date');
            if (dateInput) {
                dateInput.addEventListener('change', validateDateTime);
                dateInput.addEventListener('blur', validateDateTime);
            }
        });
    </script>
</head>
<body>
    <?php include '../includes/navbar.php'; ?>

    <div class="breadcrumb">
        <a href="welcome.php">Inicio</a> <span>›</span>
        <a href="consultation_history.php">Consultas</a> <span>›</span>
        <span>Editar Consulta #<?php echo $consultation_id; ?></span>
    </div>

    <div class="container">
        <h1><i class="fas fa-edit"></i> Editar Consulta #<?php echo $consultation_id; ?></h1>

        <?php if ($error): ?>
            <div class="alert alert-danger"><i class="fas fa-exclamation-triangle"></i> <?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <form method="post">
            <label for="pet_id">Mascota:</label>
            <select name="pet_id" id="pet_id" required>
                <option value="">Seleccione una mascota</option>
                <?php foreach ($pets as $pet): ?>
                    <option value="<?php echo $pet['id']; ?>" <?php echo ($pet['id'] == $consultation['pet_id']) ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($pet['name']); ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <label for="consultation_date">Fecha y hora (Horario laboral: <?php echo $config['horario_apertura']; ?> - <?php echo $config['horario_cierre']; ?>):</label>
            <input type="datetime-local" name="consultation_date" id="consultation_date" value="<?php echo date('Y-m-d\TH:i', strtotime($consultation['consultation_date'])); ?>" required>
            <div class="help-text">Solo se permiten fechas dentro del horario laboral y días hábiles (<?php echo $config['dias_trabajo']; ?>).</div>

            <label for="diagnosis">Diagnóstico:</label>
            <textarea name="diagnosis" id="diagnosis" rows="4" required><?php echo htmlspecialchars($consultation['diagnosis']); ?></textarea>

            <label for="treatment">Tratamiento:</label>
            <textarea name="treatment" id="treatment" rows="4"><?php echo htmlspecialchars($consultation['treatment'] ?? ''); ?></textarea>

            <label for="notes">Notas:</label>
            <textarea name="notes" id="notes" rows="4"><?php echo htmlspecialchars($consultation['notes'] ?? ''); ?></textarea>

            <button type="submit" class="btn"><i class="fas fa-save"></i> Guardar Cambios</button>
        </form>

        <a href="consultation_history.php" class="btn btn-secondary" style="margin-top:20px;"><i class="fas fa-arrow-left"></i> Volver</a>
    </div>

    <?php include_once '../includes/footer.php'; ?>
</body>
</html>
