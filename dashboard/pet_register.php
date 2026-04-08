<?php
session_start();
require_once '../includes/config.php';
require_once '../includes/bitacora_function.php';

if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header("Location: ../index.php");
    exit;
}

$username = $_SESSION["username"] ?? 'Usuario';
$user_id = $_SESSION['user_id'] ?? 0;
$role_name = $_SESSION['role_name'] ?? 'Propietario';

if (!in_array($role_name, ['Propietario', 'Veterinario', 'admin'])) {
    header("Location: welcome.php?error=access_denied");
    exit;
}

$pet_types = [];
$breeds = [];
$owners = [];
$error = '';
$success = '';

// Mapa de longevidad
$lifespanMap = [
    'perro' => 18, 'gato' => 20, 'tortuga' => 100, 'conejo' => 12, 'hámster' => 3,
    'ave' => 20, 'pez' => 5, 'reptil' => 30, 'roedor' => 4, 'caballo' => 30,
    'cerdo' => 15, 'otros' => 20
];

function getMaxAgeForType($typeName) {
    global $lifespanMap;
    $key = strtolower(trim($typeName));
    return $lifespanMap[$key] ?? $lifespanMap['otros'];
}

// Asegurar especies esenciales
$requiredTypes = ['Perro', 'Gato', 'Tortuga', 'Conejo', 'Hámster', 'Ave', 'Pez', 'Reptil', 'Roedor', 'Caballo', 'Cerdo', 'Otros'];
try {
    $stmtCheck = $conn->query("SELECT name FROM pet_types");
    $existingNames = $stmtCheck->fetchAll(PDO::FETCH_COLUMN);
    $existingNamesLower = array_map('strtolower', $existingNames);
    foreach ($requiredTypes as $typeName) {
        if (!in_array(strtolower($typeName), $existingNamesLower)) {
            $stmtInsert = $conn->prepare("INSERT INTO pet_types (name, attendant_id) VALUES (:name, :attendant_id)");
            $stmtInsert->bindValue(':name', $typeName);
            $stmtInsert->bindValue(':attendant_id', $user_id, PDO::PARAM_INT);
            $stmtInsert->execute();
        }
    }
} catch (PDOException $e) {
    $error = "Error al verificar especies: " . $e->getMessage();
}

try {
    $stmtTypes = $conn->query("SELECT id, name FROM pet_types ORDER BY name");
    $pet_types = $stmtTypes->fetchAll(PDO::FETCH_ASSOC);
    $stmtBreeds = $conn->query("SELECT id, name, type_id FROM breeds ORDER BY name");
    $breeds = $stmtBreeds->fetchAll(PDO::FETCH_ASSOC);
    if (in_array($role_name, ['Veterinario', 'admin'])) {
        $stmtOwners = $conn->query("SELECT id, username, first_name, last_name FROM users WHERE role_id = 3 ORDER BY first_name");
        $owners = $stmtOwners->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (PDOException $e) {
    $error = "Error al cargar datos: " . $e->getMessage();
}

function sanitizePetName($name) {
    $name = preg_replace('/[0-9]/', '', $name);
    $name = preg_replace('/[^a-zA-ZáéíóúüñÁÉÍÓÚÜÑ\s\-\']/u', '', $name);
    $name = preg_replace('/\s+/', ' ', $name);
    return trim($name);
}
function containsProfanity($name) {
    $profanityList = ['puta','puto','pendejo','cabrón','cabron','coño','cojones','joder','mierda','imbécil','imbecil','gilipollas','zorra','bastardo','malparido','hijueputa','maricón','maricon','chucha','concha','culiao','weon','weón','weona','ctm','conchetumare','fuck','shit','bitch','asshole','bastard','cunt','dick','pussy','whore','slut','motherfucker'];
    $lower = strtolower($name);
    foreach ($profanityList as $word) if (strpos($lower, $word) !== false) return true;
    return false;
}
function hasExcessiveRepeats($name) { return preg_match('/(.)\1{3,}/u', $name); }
function containsLetter($name) { return preg_match('/[a-zA-ZáéíóúüñÁÉÍÓÚÜÑ]/u', $name); }

if ($_SERVER["REQUEST_METHOD"] == "POST" && empty($error)) {
    $name = trim($_POST['name'] ?? '');
    $type_id = intval($_POST['type_id'] ?? 0);
    $breed_id = !empty($_POST['breed_id']) ? intval($_POST['breed_id']) : null;
    $gender = $_POST['gender'] ?? null;
    $dob = $_POST['dob'] ?? null;
    $medical_history = trim($_POST['medical_history'] ?? '');

    if (empty($name)) {
        $error = "El nombre de la mascota es obligatorio.";
    } else {
        $name = sanitizePetName($name);
        if (strlen($name) < 2) $error = "El nombre debe tener al menos 2 caracteres válidos.";
        elseif (strlen($name) > 50) $error = "El nombre no puede exceder los 50 caracteres.";
        elseif (!containsLetter($name)) $error = "El nombre debe contener al menos una letra.";
        elseif (hasExcessiveRepeats($name)) $error = "El nombre no puede tener un mismo carácter repetido más de 3 veces seguidas.";
        elseif (containsProfanity($name)) $error = "El nombre contiene palabras inapropiadas.";
    }

    if (empty($error)) {
        if (in_array($role_name, ['Veterinario', 'admin'])) {
            $owner_id = intval($_POST['owner_id'] ?? 0);
            if ($owner_id <= 0) $error = "Debe seleccionar un dueño.";
        } else {
            $owner_id = $user_id;
        }
    }

    if (empty($error) && $type_id == 0) $error = "Debe seleccionar una especie.";

    if (empty($error) && !empty($dob)) {
        $typeName = '';
        foreach ($pet_types as $pt) if ($pt['id'] == $type_id) { $typeName = $pt['name']; break; }
        if (empty($typeName)) $error = "Especie no válida.";
        else {
            $maxAge = getMaxAgeForType($typeName);
            $dobDate = new DateTime($dob);
            $today = new DateTime();
            $age = $today->diff($dobDate)->y;
            if ($age < 0) $error = "La fecha de nacimiento no puede ser futura.";
            elseif ($age > $maxAge) $error = "La edad ($age años) excede la esperanza de vida máxima para $typeName ($maxAge años).";
        }
    }

    if (empty($error)) {
        try {
            $sql = "INSERT INTO pets (name, owner_id, attendant_id, type_id, breed_id, gender, date_of_birth, medical_history) 
                    VALUES (:name, :owner_id, :attendant_id, :type_id, :breed_id, :gender, :dob, :medical_history)";
            $stmt = $conn->prepare($sql);
            $stmt->bindValue(':name', $name);
            $stmt->bindValue(':owner_id', $owner_id, PDO::PARAM_INT);
            $stmt->bindValue(':attendant_id', $user_id, PDO::PARAM_INT);
            $stmt->bindValue(':type_id', $type_id, PDO::PARAM_INT);
            $stmt->bindValue(':breed_id', $breed_id, PDO::PARAM_INT);
            $stmt->bindValue(':gender', $gender);
            $stmt->bindValue(':dob', $dob);
            $stmt->bindValue(':medical_history', $medical_history);
            $stmt->execute();

            $new_id = $conn->lastInsertId();
            $action = "Nueva mascota registrada: $name (ID $new_id)";
            log_to_bitacora($conn, $action, $username, $_SESSION['role_id'] ?? 0);
            $success = "Mascota registrada correctamente.";
            $_POST = [];
        } catch (PDOException $e) {
            $error = "Error al registrar: " . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registrar Mascota - VetCtrl</title>
    <link rel="stylesheet" href="../public/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary-dark: #1b4332;
            --primary: #2d6a4f;
            --primary-light: #40916c;
            --accent: #b68b40;
        }
        body {
            background-color: #f4f7fc;
            padding-top: 70px;
            font-family: 'Inter', system-ui, 'Segoe UI', sans-serif;
        }
        .breadcrumb {
            max-width: 600px;
            margin: 10px auto 0;
            padding: 10px 20px;
            font-size: 0.9rem;
        }
        .breadcrumb a { color: var(--primary-light); text-decoration: none; }
        .container {
            max-width: 600px;
            margin: 20px auto;
            background: white;
            padding: 30px;
            border-radius: 32px;
            box-shadow: 0 10px 25px -5px rgba(0,0,0,0.05);
            border: 1px solid #eef2f8;
        }
        h1 {
            color: var(--primary-dark);
            border-bottom: 3px solid var(--accent);
            padding-bottom: 12px;
            margin-bottom: 25px;
            display: flex;
            align-items: center;
            gap: 12px;
        }
        .alert {
            padding: 15px 20px;
            border-radius: 20px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 12px;
            border-left: 5px solid;
        }
        .alert-success { background: #e0f2e9; color: #1e7b4a; border-left-color: #1e7b4a; }
        .alert-danger { background: #fee7e7; color: #b91c1c; border-left-color: #b91c1c; }
        label {
            display: block;
            margin: 18px 0 6px;
            font-weight: 600;
            color: var(--primary-dark);
            font-size: 0.85rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        input, select, textarea {
            width: 100%;
            padding: 12px;
            border: 2px solid #e2e8f0;
            border-radius: 16px;
            transition: 0.2s;
        }
        input:focus, select:focus, textarea:focus {
            border-color: var(--primary-light);
            outline: none;
            box-shadow: 0 0 0 3px rgba(64,145,108,0.2);
        }
        .btn {
            padding: 12px 25px;
            border: none;
            border-radius: 40px;
            font-weight: 600;
            cursor: pointer;
            background: var(--primary);
            color: white;
            width: 100%;
            margin-top: 25px;
            transition: 0.2s;
        }
        .btn:hover {
            background: var(--primary-dark);
            transform: translateY(-2px);
        }
        .btn-secondary {
            background: #eef2f8;
            color: var(--primary-dark);
            width: auto;
            display: inline-block;
            margin-top: 20px;
            text-decoration: none;
            padding: 10px 24px;
        }
        .error-message {
            color: #dc3545;
            font-size: 0.75rem;
            margin-top: 4px;
            display: none;
        }
        .input-error {
            border-color: #dc3545 !important;
        }
        @media (max-width: 640px) {
            .container { padding: 20px; margin: 15px; }
        }
    </style>
    <script>
        const lifespanMap = <?php echo json_encode($lifespanMap); ?>;
        const petTypes = <?php echo json_encode($pet_types); ?>;
        const profanityList = ['puta','puto','pendejo','cabrón','cabron','coño','cojones','joder','mierda','imbécil','imbecil','gilipollas','zorra','bastardo','malparido','hijueputa','maricón','maricon','chucha','concha','culiao','weon','weón','weona','ctm','conchetumare','fuck','shit','bitch','asshole','bastard','cunt','dick','pussy','whore','slut','motherfucker'];

        function containsProfanity(str) {
            const lower = str.toLowerCase();
            for (let word of profanityList) if (lower.includes(word)) return true;
            return false;
        }
        function hasExcessiveRepeats(str) { return /(.)\1{3,}/.test(str); }
        function containsLetter(str) { return /[a-zA-ZáéíóúüñÁÉÍÓÚÜÑ]/.test(str); }

        function validatePetName() {
            const nameInput = document.getElementById('name');
            let name = nameInput.value;
            name = name.replace(/[0-9]/g, '');
            name = name.replace(/[^a-zA-ZáéíóúüñÁÉÍÓÚÜÑ\s\-\']/g, '');
            name = name.replace(/\s+/g, ' ').trim();
            nameInput.value = name;

            let isValid = true;
            let errorMsg = '';
            if (name.length < 2) { errorMsg = 'Mínimo 2 caracteres.'; isValid = false; }
            else if (name.length > 50) { errorMsg = 'Máximo 50 caracteres.'; isValid = false; }
            else if (!containsLetter(name)) { errorMsg = 'Debe contener al menos una letra.'; isValid = false; }
            else if (hasExcessiveRepeats(name)) { errorMsg = 'No puede tener un mismo carácter repetido más de 3 veces.'; isValid = false; }
            else if (containsProfanity(name)) { errorMsg = 'Contiene palabras inapropiadas.'; isValid = false; }

            const errorSpan = document.getElementById('name-error');
            if (!isValid) {
                nameInput.classList.add('input-error');
                errorSpan.textContent = errorMsg;
                errorSpan.style.display = 'block';
            } else {
                nameInput.classList.remove('input-error');
                errorSpan.style.display = 'none';
            }
            return isValid;
        }

        function validateDateOfBirth() {
            const dobInput = document.getElementById('dob');
            const dob = dobInput.value;
            const typeSelect = document.getElementById('type_id');
            const selectedOption = typeSelect.options[typeSelect.selectedIndex];
            const typeName = selectedOption ? selectedOption.textContent.trim().toLowerCase() : '';
            const errorSpan = document.getElementById('dob-error');
            if (!dob) { errorSpan.style.display = 'none'; return true; }

            let maxAge = lifespanMap['otros'] || 20;
            if (typeName && lifespanMap[typeName] !== undefined) maxAge = lifespanMap[typeName];

            const today = new Date();
            const birthDate = new Date(dob);
            if (isNaN(birthDate.getTime())) {
                errorSpan.textContent = 'Fecha inválida.';
                errorSpan.style.display = 'block';
                dobInput.classList.add('input-error');
                return false;
            }
            if (birthDate > today) {
                errorSpan.textContent = 'La fecha no puede ser futura.';
                errorSpan.style.display = 'block';
                dobInput.classList.add('input-error');
                return false;
            }
            let age = today.getFullYear() - birthDate.getFullYear();
            const monthDiff = today.getMonth() - birthDate.getMonth();
            if (monthDiff < 0 || (monthDiff === 0 && today.getDate() < birthDate.getDate())) age--;
            if (age > maxAge) {
                errorSpan.textContent = `La edad (${age} años) excede la esperanza de vida máxima para ${selectedOption.textContent} (${maxAge} años).`;
                errorSpan.style.display = 'block';
                dobInput.classList.add('input-error');
                return false;
            } else {
                errorSpan.style.display = 'none';
                dobInput.classList.remove('input-error');
                return true;
            }
        }

        document.addEventListener('DOMContentLoaded', function() {
            document.getElementById('name-error').style.display = 'none';
            document.getElementById('dob-error').style.display = 'none';
            const nameInput = document.getElementById('name');
            nameInput.addEventListener('input', validatePetName);
            nameInput.addEventListener('blur', validatePetName);
            const typeSelect = document.getElementById('type_id');
            const dobInput = document.getElementById('dob');
            typeSelect.addEventListener('change', validateDateOfBirth);
            dobInput.addEventListener('change', validateDateOfBirth);
            dobInput.addEventListener('input', validateDateOfBirth);

            const form = document.querySelector('form');
            form.addEventListener('submit', function(e) {
                let valid = true;
                if (!validatePetName()) valid = false;
                if (dobInput && !validateDateOfBirth()) valid = false;
                if (!valid) { e.preventDefault(); alert('Por favor corrige los errores en el formulario.'); }
            });

            const allBreeds = <?php echo json_encode($breeds, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
            function filterBreeds() {
                const typeSelect = document.getElementById('type_id');
                const breedSelect = document.getElementById('breed_id');
                const selectedType = typeSelect.value;
                breedSelect.innerHTML = '<option value="">-- Sin raza --</option>';
                if (selectedType) {
                    const filtered = allBreeds.filter(b => b.type_id == selectedType);
                    filtered.forEach(b => {
                        const opt = document.createElement('option');
                        opt.value = b.id;
                        opt.textContent = b.name;
                        if (b.id == <?php echo json_encode($_POST['breed_id'] ?? 0); ?>) opt.selected = true;
                        breedSelect.appendChild(opt);
                    });
                }
            }
            filterBreeds();
            typeSelect.addEventListener('change', filterBreeds);
        });
    </script>
</head>
<body>
    <?php include '../includes/navbar.php'; ?>

    <div class="breadcrumb">
        <a href="welcome.php">Inicio</a> <span>›</span>
        <span>Registrar Mascota</span>
    </div>

    <div class="container">
        <h1><i class="fas fa-paw"></i> Registrar Nueva Mascota</h1>

        <?php if ($error): ?>
            <div class="alert alert-danger"><i class="fas fa-exclamation-triangle"></i> <?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        <?php if ($success): ?>
            <div class="alert alert-success"><i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($success); ?></div>
        <?php endif; ?>

        <form method="post">
            <?php if (in_array($role_name, ['Veterinario', 'admin'])): ?>
                <label for="owner_id">Dueño:</label>
                <select name="owner_id" id="owner_id" required>
                    <option value="">Seleccione un dueño</option>
                    <?php foreach ($owners as $owner): 
                        $owner_name = trim($owner['first_name'] . ' ' . $owner['last_name']);
                        if (empty($owner_name)) $owner_name = $owner['username'];
                    ?>
                        <option value="<?php echo $owner['id']; ?>" <?php echo (isset($_POST['owner_id']) && $_POST['owner_id'] == $owner['id']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($owner_name); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            <?php endif; ?>

            <label for="name">Nombre de la mascota:</label>
            <input type="text" name="name" id="name" value="<?php echo htmlspecialchars($_POST['name'] ?? ''); ?>" required maxlength="50">
            <div id="name-error" class="error-message"></div>

            <label for="type_id">Especie:</label>
            <select name="type_id" id="type_id" required>
                <option value="">Seleccione...</option>
                <?php foreach ($pet_types as $type): ?>
                    <option value="<?php echo $type['id']; ?>" <?php echo (isset($_POST['type_id']) && $_POST['type_id'] == $type['id']) ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($type['name']); ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <label for="breed_id">Raza:</label>
            <select name="breed_id" id="breed_id">
                <option value="">-- Sin raza --</option>
            </select>

            <label for="gender">Género:</label>
            <select name="gender" id="gender">
                <option value="">-- Seleccione --</option>
                <option value="Macho" <?php echo (isset($_POST['gender']) && $_POST['gender'] == 'Macho') ? 'selected' : ''; ?>>Macho</option>
                <option value="Hembra" <?php echo (isset($_POST['gender']) && $_POST['gender'] == 'Hembra') ? 'selected' : ''; ?>>Hembra</option>
                <option value="N/D" <?php echo (isset($_POST['gender']) && $_POST['gender'] == 'N/D') ? 'selected' : ''; ?>>N/D</option>
            </select>

            <label for="dob">Fecha de nacimiento:</label>
            <input type="date" name="dob" id="dob" value="<?php echo htmlspecialchars($_POST['dob'] ?? ''); ?>" max="<?php echo date('Y-m-d'); ?>">
            <div id="dob-error" class="error-message"></div>

            <label for="medical_history">Historial médico / Observaciones:</label>
            <textarea name="medical_history" id="medical_history" rows="4"><?php echo htmlspecialchars($_POST['medical_history'] ?? ''); ?></textarea>

            <button type="submit" class="btn"><i class="fas fa-save"></i> Registrar Mascota</button>
        </form>

        <a href="pet_list.php" class="btn-secondary"><i class="fas fa-list"></i> Ver listado</a>
    </div>

    <?php include_once '../includes/footer.php'; ?>
</body>
</html>
