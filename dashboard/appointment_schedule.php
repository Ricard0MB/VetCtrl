<?php
session_start();

if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header("Location: ../index.php");
    exit;
}

require_once '../includes/config.php';

$username = $_SESSION["username"] ?? 'Usuario';
$user_id = $_SESSION['user_id'] ?? 0;
$role_name = $_SESSION['role_name'] ?? 'Propietario';

if (!in_array($role_name, ['Propietario', 'Veterinario', 'admin'])) {
    header("Location: welcome.php?error=access_denied");
    exit;
}

// Configuración horaria
$config = ['horario_apertura' => '08:00', 'horario_cierre' => '18:00', 'dias_trabajo' => 'Lunes a Viernes'];
$stmt = $conn->query("SELECT config_key, config_value FROM system_config WHERE config_key IN ('horario_apertura', 'horario_cierre', 'dias_trabajo')");
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) $config[$row['config_key']] = $row['config_value'];

function parseWorkingDays($daysString) {
    $daysMap = ['Lunes'=>1,'Martes'=>2,'Miércoles'=>3,'Miercoles'=>3,'Jueves'=>4,'Viernes'=>5,'Sábado'=>6,'Sabado'=>6,'Domingo'=>7];
    $daysString = trim($daysString);
    if (preg_match('/([a-zA-ZáéíóúÁÉÍÓÚ]+)\s*a\s*([a-zA-ZáéíóúÁÉÍÓÚ]+)/i', $daysString, $matches)) {
        $start = $matches[1]; $end = $matches[2];
        if (isset($daysMap[$start]) && isset($daysMap[$end])) {
            $result = []; for ($i = $daysMap[$start]; $i <= $daysMap[$end]; $i++) $result[] = $i; return $result;
        }
    }
    $parts = preg_split('/[,\s]+/', $daysString);
    $result = [];
    foreach ($parts as $part) if (isset($daysMap[trim($part)])) $result[] = $daysMap[trim($part)];
    return $result;
}
$allowedDays = parseWorkingDays($config['dias_trabajo']);
$pets = $vets = [];
$error = $success = '';

try {
    $stmt_role = $conn->prepare("SELECT id FROM roles WHERE name = :role_name");
    $stmt_role->bindValue(':role_name', 'Veterinario');
    $stmt_role->execute();
    $vet_role = $stmt_role->fetch(PDO::FETCH_ASSOC);
    if (!$vet_role) $error = "Rol 'Veterinario' no configurado.";
    else {
        $vet_role_id = $vet_role['id'];
        $stmt_vets = $conn->prepare("SELECT id, username, CONCAT('Dr(a). ', username) AS display_name FROM users WHERE role_id = :role_id ORDER BY username");
        $stmt_vets->bindValue(':role_id', $vet_role_id);
        $stmt_vets->execute();
        $vets = $stmt_vets->fetchAll(PDO::FETCH_ASSOC);
        if (empty($vets)) $error = "No hay veterinarios registrados.";
    }
    if ($role_name === 'Propietario') {
        $stmt_pets = $conn->prepare("SELECT id, name FROM pets WHERE owner_id = :owner_id ORDER BY name");
        $stmt_pets->bindValue(':owner_id', $user_id);
    } else {
        $stmt_pets = $conn->prepare("SELECT id, name FROM pets ORDER BY name");
    }
    $stmt_pets->execute();
    $pets = $stmt_pets->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) { $error = "Error al cargar datos: " . $e->getMessage(); }

function isWithinWorkingHours($datetime, $open, $close, $allowedDays) {
    $timestamp = strtotime($datetime); if (!$timestamp) return false;
    if (!in_array(date('N', $timestamp), $allowedDays)) return false;
    $hour = date('H:i', $timestamp); return ($hour >= $open && $hour <= $close);
}

if ($_SERVER["REQUEST_METHOD"] == "POST" && empty($error)) {
    $pet_id = intval($_POST['pet_id'] ?? 0);
    $vet_id = intval($_POST['vet_id'] ?? 0);
    $appointment_date = $_POST['appointment_date'] ?? '';
    $reason = trim($_POST['reason'] ?? '');
    if ($pet_id <= 0 || $vet_id <= 0 || empty($appointment_date) || empty($reason)) $error = "Todos los campos son obligatorios.";
    else {
        $timestamp = strtotime($appointment_date);
        if (!$timestamp || $timestamp <= time()) $error = "La fecha debe ser futura.";
        elseif (!isWithinWorkingHours($appointment_date, $config['horario_apertura'], $config['horario_cierre'], $allowedDays)) $error = "La cita debe estar dentro del horario laboral ({$config['horario_apertura']} a {$config['horario_cierre']}) y días hábiles.";
        else {
            $formatted = date('Y-m-d H:i:s', $timestamp);
            $insert_sql = "INSERT INTO appointments (pet_id, attendant_id, appointment_date, reason, status) VALUES (:pet_id, :vet_id, :date, :reason, 'PENDIENTE')";
            $insert_stmt = $conn->prepare($insert_sql);
            $insert_stmt->bindValue(':pet_id', $pet_id);
            $insert_stmt->bindValue(':vet_id', $vet_id);
            $insert_stmt->bindValue(':date', $formatted);
            $insert_stmt->bindValue(':reason', $reason);
            if ($insert_stmt->execute()) { $success = "Cita agendada correctamente."; $_POST = []; }
            else $error = "Error al agendar.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Agendar Cita - VetCtrl</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:opsz,wght@14..32,300;14..32,400;14..32,500;14..32,600;14..32,700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Inter', sans-serif; background: #f4f7f9; padding-top: 72px; }
        :root { --vet-dark: #1b4332; --vet-primary: #40916c; --shadow-md: 0 8px 20px rgba(0,0,0,0.05); --radius-lg: 16px; }
        .breadcrumb { max-width: 600px; margin: 0 auto 1rem auto; padding: 0.5rem 1.5rem; font-size: 0.85rem; }
        .breadcrumb a { color: var(--vet-primary); text-decoration: none; }
        .container { max-width: 600px; margin: 1.5rem auto; background: white; border-radius: var(--radius-lg); padding: 2rem; box-shadow: var(--shadow-md); }
        h1 { color: var(--vet-dark); border-bottom: 2px solid var(--vet-primary); padding-bottom: 0.75rem; margin-bottom: 1.5rem; display: flex; align-items: center; gap: 0.75rem; font-size: 1.6rem; }
        .alert { display: flex; align-items: center; gap: 0.75rem; padding: 0.8rem 1rem; border-radius: 12px; margin-bottom: 1rem; border-left: 4px solid; }
        .alert-success { background: #e6f4ea; color: #155724; border-left-color: #28a745; }
        .alert-danger { background: #f8d7da; color: #721c24; border-left-color: #dc3545; }
        label { display: block; margin: 1rem 0 0.4rem; font-weight: 600; color: var(--vet-dark); }
        select, input[type="datetime-local"], textarea { width: 100%; padding: 0.7rem; border: 1px solid #d0d8d0; border-radius: 10px; font-family: inherit; }
        select:focus, input:focus, textarea:focus { border-color: var(--vet-primary); outline: none; box-shadow: 0 0 0 2px rgba(64,145,108,0.2); }
        .btn { background: var(--vet-primary); color: white; padding: 0.7rem; border: none; border-radius: 8px; font-weight: 600; width: 100%; cursor: pointer; margin-top: 1.5rem; transition: 0.2s; }
        .btn:hover { background: var(--vet-dark); }
        .btn-secondary { background: #6c757d; text-align: center; text-decoration: none; display: inline-block; margin-top: 0.5rem; }
        .help-text { font-size: 0.75rem; color: #6c757d; margin-top: 0.3rem; }
        .no-pets { background: #fff3cd; padding: 1rem; border-radius: 12px; text-align: center; margin-bottom: 1rem; }
        @media (max-width: 768px) { .container { margin: 1rem; padding: 1.2rem; } }
    </style>
    <script>
        const openTime = "<?php echo $config['horario_apertura']; ?>", closeTime = "<?php echo $config['horario_cierre']; ?>", allowedDaysNumbers = <?php echo json_encode($allowedDays); ?>;
        function parseTimeToMinutes(t){ let p=t.split(':'); return parseInt(p[0])*60+parseInt(p[1]); }
        function getDayNumber(d){ let day=d.getDay(); return day===0?7:day; }
        function isWorkingDay(d){ return allowedDaysNumbers.includes(getDayNumber(d)); }
        function isWithinWorkingHours(d){ let total=d.getHours()*60+d.getMinutes(), open=parseTimeToMinutes(openTime), close=parseTimeToMinutes(closeTime); return total>=open && total<=close; }
        function validateDateTime(){
            let input=document.getElementById('appointment_date');
            if(!input.value) return true;
            let selectedDate=new Date(input.value);
            if(selectedDate<=new Date()){ alert('La fecha debe ser futura.'); input.value=''; return false; }
            if(!isWorkingDay(selectedDate)){ alert('Las citas solo en días laborables.'); input.value=''; return false; }
            if(!isWithinWorkingHours(selectedDate)){ alert('La cita debe estar en horario laboral ('+openTime+' a '+closeTime+').'); input.value=''; return false; }
            return true;
        }
        document.addEventListener('DOMContentLoaded',function(){
            let input=document.getElementById('appointment_date');
            if(input){
                let now=new Date(); now.setMinutes(now.getMinutes()-now.getTimezoneOffset());
                let minDateTime=now.toISOString().slice(0,16);
                if(isWorkingDay(now) && now.getHours()*60+now.getMinutes()<parseTimeToMinutes(openTime)){
                    let [oh,om]=openTime.split(':'); let n=new Date(now); n.setHours(parseInt(oh),parseInt(om),0);
                    minDateTime=n.toISOString().slice(0,16);
                }
                input.min=minDateTime;
                input.addEventListener('change',validateDateTime);
            }
        });
    </script>
</head>
<body>
    <?php include '../includes/navbar.php'; ?>
    <div class="breadcrumb"><a href="welcome.php">Inicio</a> <span>›</span> <span>Agendar Cita</span></div>
    <div class="container">
        <h1><i class="fas fa-calendar-plus"></i> Agendar Nueva Cita</h1>
        <?php if ($error): ?><div class="alert alert-danger"><i class="fas fa-exclamation-triangle"></i> <?php echo htmlspecialchars($error); ?></div><?php endif; ?>
        <?php if ($success): ?><div class="alert alert-success"><i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($success); ?></div><?php endif; ?>
        <?php if (empty($pets)): ?>
            <div class="no-pets"><p>No hay mascotas disponibles.</p><?php if ($role_name==='Propietario'): ?><a href="pet_register.php" class="btn btn-secondary">Registrar Mascota</a><?php endif; ?></div>
        <?php elseif (empty($vets)): ?>
            <div class="no-pets"><p>No hay veterinarios disponibles.</p></div>
        <?php else: ?>
            <form method="post">
                <label for="pet_id">Mascota:</label>
                <select name="pet_id" id="pet_id" required><?php foreach($pets as $pet): ?><option value="<?php echo $pet['id']; ?>"><?php echo htmlspecialchars($pet['name']); ?></option><?php endforeach; ?></select>
                <label for="vet_id">Veterinario:</label>
                <select name="vet_id" id="vet_id" required><?php foreach($vets as $vet): ?><option value="<?php echo $vet['id']; ?>"><?php echo htmlspecialchars($vet['display_name']); ?></option><?php endforeach; ?></select>
                <label for="appointment_date">Fecha y hora (<?php echo $config['horario_apertura']; ?> - <?php echo $config['horario_cierre']; ?>):</label>
                <input type="datetime-local" name="appointment_date" id="appointment_date" required value="<?php echo htmlspecialchars($_POST['appointment_date'] ?? ''); ?>">
                <div class="help-text">Solo días laborables (<?php echo $config['dias_trabajo']; ?>).</div>
                <label for="reason">Motivo:</label>
                <textarea name="reason" id="reason" rows="4" required><?php echo htmlspecialchars($_POST['reason'] ?? ''); ?></textarea>
                <button type="submit" class="btn"><i class="fas fa-save"></i> Agendar Cita</button>
            </form>
            <a href="appointment_list.php" class="btn btn-secondary" style="display:block; text-align:center; margin-top:1rem;"><i class="fas fa-list"></i> Ver citas</a>
        <?php endif; ?>
    </div>
    <?php include_once '../includes/footer.php'; ?>
</body>
</html>
