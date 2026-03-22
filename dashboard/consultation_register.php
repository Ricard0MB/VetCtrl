<?php
session_start();

if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header("Location: ../index.php");
    exit;
}

require_once '../includes/config.php';

$username = $_SESSION["username"] ?? 'Veterinario';
$user_id = $_SESSION['user_id'] ?? 0;
$role_name = $_SESSION['role_name'] ?? 'Propietario';

if (!in_array($role_name, ['Veterinario', 'admin'])) {
    header("Location: welcome.php?error=access_denied");
    exit;
}

// Cargar configuración de horario
$config = [
    'horario_apertura' => '08:00',
    'horario_cierre' => '18:00',
    'dias_trabajo' => 'Lunes a Viernes'
];
$stmt = $conn->query("SELECT config_key, config_value FROM system_config WHERE config_key IN ('horario_apertura', 'horario_cierre', 'dias_trabajo')");
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $config[$row['config_key']] = $row['config_value'];
}

// Función para parsear días laborables en un array de números (1 = lunes, 7 = domingo)
function parseWorkingDays($daysString) {
    $daysMap = [
        'Lunes' => 1, 'Martes' => 2, 'Miércoles' => 3, 'Miercoles' => 3,
        'Jueves' => 4, 'Viernes' => 5, 'Sábado' => 6, 'Sabado' => 6,
        'Domingo' => 7
    ];
    $daysString = trim($daysString);
    // Si contiene "a", asumimos rango
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
    // Si no es rango, tratar como lista separada por comas
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

$pets = [];
$error = '';
$success = '';
$preselected_pet_id = isset($_GET['pet_id']) ? intval($_GET['pet_id']) : 0;

try {
    $sql_pets = "SELECT p.id, p.name, u.username as owner_name 
                 FROM pets p 
                 LEFT JOIN users u ON p.owner_id = u.id 
                 ORDER BY p.name ASC";
    $stmtPets = $conn->query($sql_pets);
    $pets = $stmtPets->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error = "Error al cargar mascotas: " . $e->getMessage();
}

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

if ($_SERVER["REQUEST_METHOD"] == "POST" && empty($error)) {
    $pet_id = intval($_POST['pet_id'] ?? 0);
    $consultation_date = $_POST['consultation_date'] ?? '';
    $reason = trim($_POST['reason'] ?? '');
    $diagnosis = trim($_POST['diagnosis'] ?? '');
    $treatment = trim($_POST['treatment'] ?? '');
    $notes = trim($_POST['notes'] ?? '');

    if ($pet_id <= 0 || empty($consultation_date) || empty($diagnosis)) {
        $error = "Los campos mascota, fecha y diagnóstico son obligatorios.";
    } else {
        $timestamp = strtotime($consultation_date);
        if (!$timestamp) {
            $error = "Fecha inválida.";
        } elseif (!isWithinWorkingHours($consultation_date, $config['horario_apertura'], $config['horario_cierre'], $allowedDays)) {
            $error = "La consulta debe estar dentro del horario laboral ({$config['horario_apertura']} a {$config['horario_cierre']}) y en días laborables (" . implode(', ', array_map(function($d) {
                $daysNames = ['', 'Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado', 'Domingo'];
                return $daysNames[$d];
            }, $allowedDays)) . ").";
        } else {
            $formatted_date = date('Y-m-d H:i:s', $timestamp);
            try {
                $sql_insert = "INSERT INTO consultations (pet_id, attendant_id, consultation_date, reason, diagnosis, treatment, notes) 
                               VALUES (:pet_id, :attendant_id, :date, :reason, :diagnosis, :treatment, :notes)";
                $stmtInsert = $conn->prepare($sql_insert);
                $stmtInsert->bindValue(':pet_id', $pet_id, PDO::PARAM_INT);
                $stmtInsert->bindValue(':attendant_id', $user_id, PDO::PARAM_INT);
                $stmtInsert->bindValue(':date', $formatted_date, PDO::PARAM_STR);
                $stmtInsert->bindValue(':reason', $reason, PDO::PARAM_STR);
                $stmtInsert->bindValue(':diagnosis', $diagnosis, PDO::PARAM_STR);
                $stmtInsert->bindValue(':treatment', $treatment, PDO::PARAM_STR);
                $stmtInsert->bindValue(':notes', $notes, PDO::PARAM_STR);
                $stmtInsert->execute();

                $new_id = $conn->lastInsertId();
                require_once '../includes/bitacora_function.php';
                $action = "Nueva consulta #$new_id registrada para mascota ID $pet_id";
                log_to_bitacora($conn, $action, $username, $_SESSION['role_id'] ?? 0);
                $success = "Consulta registrada correctamente.";
                $_POST = [];
            } catch (PDOException $e) {
                $error = "Error al registrar: " . $e->getMessage();
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Registrar Consulta - VetCtrl</title>
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
        .alert-success { background: #d4edda; color: #155724; border-left-color: #28a745; }
        .alert-danger { background: #f8d7da; color: #721c24; border-left-color: #dc3545; }
        label { display: block; margin: 15px 0 5px; font-weight: 600; color: #1b4332; }
        select, input[type="datetime-local"], textarea { width: 100%; padding: 10px; border: 2px solid #e0e0e0; border-radius: 6px; box-sizing: border-box; }
        select:focus, input:focus, textarea:focus { border-color: #40916c; outline: none; }
        .btn { padding: 12px 25px; border: none; border-radius: 6px; font-weight: 600; cursor: pointer; background: #40916c; color: white; width: 100%; margin-top: 10px; }
        .btn:hover { background: #2d6a4f; }
        .help-text { font-size: 0.85rem; color: #6c757d; margin-top: 5px; }
    </style>
    <script>
        // Configuración desde PHP
        const openTime = "<?php echo $config['horario_apertura']; ?>";
        const closeTime = "<?php echo $config['horario_cierre']; ?>";
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
            const totalMinutes = date.getHours() * 60 + date.getMinutes();
            const openMinutes = parseTimeToMinutes(openTime);
            const closeMinutes = parseTimeToMinutes(closeTime);
            return (totalMinutes >= openMinutes && totalMinutes <= closeMinutes);
        }

        function validateDateTime() {
            const input = document.getElementById('consultation_date');
            if (!input.value) return true;
            let selectedDate = new Date(input.value);
            if (!isWorkingDay(selectedDate)) {
                alert('Las consultas solo pueden registrarse en días laborables.');
                input.value = '';
                return false;
            }
            if (!isWithinWorkingHours(selectedDate)) {
                alert('La consulta debe estar dentro del horario laboral (' + openTime + ' a ' + closeTime + ').');
                input.value = '';
                return false;
            }
            return true;
        }

        document.addEventListener('DOMContentLoaded', function() {
            const dateInput = document.getElementById('consultation_date');
            if (dateInput) {
                let now = new Date();
                now.setMinutes(now.getMinutes() - now.getTimezoneOffset());
                let minDateTime = now.toISOString().slice(0,16);
                // Si hoy es día laborable y la hora actual es menor que la hora de apertura, ajustar el mínimo a la hora de apertura de hoy
                if (isWorkingDay(now) && now.getHours() * 60 + now.getMinutes() < parseTimeToMinutes(openTime)) {
                    let [openHour, openMin] = openTime.split(':');
                    let newNow = new Date(now);
                    newNow.setHours(parseInt(openHour), parseInt(openMin), 0);
                    minDateTime = newNow.toISOString().slice(0,16);
                }
                dateInput.min = minDateTime;
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
        <span>Registrar Consulta</span>
    </div>

    <div class="container">
        <h1><i class="fas fa-stethoscope"></i> Registrar Nueva Consulta</h1>

        <?php if ($error): ?>
            <div class="alert alert-danger"><i class="fas fa-exclamation-triangle"></i> <?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        <?php if ($success): ?>
            <div class="alert alert-success"><i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($success); ?></div>
        <?php endif; ?>

        <form method="post">
            <label for="pet_id">Mascota:</label>
            <select name="pet_id" id="pet_id" required>
                <option value="">Seleccione una mascota</option>
                <?php foreach ($pets as $pet): ?>
                    <option value="<?php echo $pet['id']; ?>" <?php echo ($preselected_pet_id == $pet['id']) ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($pet['name']); ?>
                        <?php if (!empty($pet['owner_name'])) echo ' (Dueño: ' . htmlspecialchars($pet['owner_name']) . ')'; ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <label for="consultation_date">Fecha y hora (Horario laboral: <?php echo $config['horario_apertura']; ?> - <?php echo $config['horario_cierre']; ?>):</label>
            <input type="datetime-local" name="consultation_date" id="consultation_date" required value="<?php echo htmlspecialchars($_POST['consultation_date'] ?? date('Y-m-d\TH:i')); ?>">
            <div class="help-text">Solo se permiten registros dentro del horario laboral y días hábiles (<?php echo $config['dias_trabajo']; ?>).</div>

            <label for="reason">Motivo (opcional):</label>
            <input type="text" name="reason" id="reason" value="<?php echo htmlspecialchars($_POST['reason'] ?? ''); ?>">

            <label for="diagnosis">Diagnóstico:</label>
            <textarea name="diagnosis" id="diagnosis" rows="4" required><?php echo htmlspecialchars($_POST['diagnosis'] ?? ''); ?></textarea>

            <label for="treatment">Tratamiento:</label>
            <textarea name="treatment" id="treatment" rows="4"><?php echo htmlspecialchars($_POST['treatment'] ?? ''); ?></textarea>

            <label for="notes">Notas:</label>
            <textarea name="notes" id="notes" rows="4"><?php echo htmlspecialchars($_POST['notes'] ?? ''); ?></textarea>

            <button type="submit" class="btn"><i class="fas fa-save"></i> Registrar Consulta</button>
        </form>
    </div>

    <?php include_once '../includes/footer.php'; ?>
</body>
</html>
