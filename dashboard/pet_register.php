<?php
session_start();
require_once '../includes/config.php'; // $conn es un objeto PDO
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

// ========== MAPA DE LONGEVIDAD POR ESPECIE (en años) ==========
$lifespanMap = [
    'perro' => 18,
    'gato' => 20,
    'tortuga' => 100,
    'conejo' => 12,
    'hámster' => 3,
    'ave' => 20,
    'pez' => 5,
    'reptil' => 30,
    'roedor' => 4,
    'caballo' => 30,
    'cerdo' => 15,
    'otros' => 20
];

/**
 * Obtiene la edad máxima permitida para un tipo de mascota dado su nombre.
 * @param string $typeName
 * @return int
 */
function getMaxAgeForType($typeName) {
    global $lifespanMap;
    $key = strtolower(trim($typeName));
    return $lifespanMap[$key] ?? $lifespanMap['otros'];
}

// ========== ASEGURAR QUE EXISTAN TODAS LAS ESPECIES NECESARIAS ==========
$requiredTypes = [
    'Perro', 'Gato', 'Tortuga', 'Conejo', 'Hámster',
    'Ave', 'Pez', 'Reptil', 'Roedor', 'Caballo', 'Cerdo', 'Otros'
];
try {
    // Obtener los nombres de especies existentes
    $stmtCheck = $conn->query("SELECT name FROM pet_types");
    $existingNames = $stmtCheck->fetchAll(PDO::FETCH_COLUMN);
    $existingNamesLower = array_map('strtolower', $existingNames);

    foreach ($requiredTypes as $typeName) {
        if (!in_array(strtolower($typeName), $existingNamesLower)) {
            // Insertar la especie faltante
            $stmtInsert = $conn->prepare("INSERT INTO pet_types (name) VALUES (:name)");
            $stmtInsert->bindValue(':name', $typeName);
            $stmtInsert->execute();
        }
    }
} catch (PDOException $e) {
    $error = "Error al verificar especies: " . $e->getMessage();
}
// ==============================================================

try {
    // Obtener tipos de mascota (ahora con todas las especies)
    $stmtTypes = $conn->query("SELECT id, name FROM pet_types ORDER BY name");
    $pet_types = $stmtTypes->fetchAll(PDO::FETCH_ASSOC);

    // Obtener razas
    $stmtBreeds = $conn->query("SELECT id, name, type_id FROM breeds ORDER BY name");
    $breeds = $stmtBreeds->fetchAll(PDO::FETCH_ASSOC);

    // Para veterinario/admin, permitir seleccionar dueño
    if (in_array($role_name, ['Veterinario', 'admin'])) {
        $stmtOwners = $conn->query("SELECT id, username, first_name, last_name FROM users WHERE role_id = 3 ORDER BY first_name");
        $owners = $stmtOwners->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (PDOException $e) {
    $error = "Error al cargar datos: " . $e->getMessage();
}

// ========== FUNCIONES DE VALIDACIÓN DE NOMBRE ==========
function sanitizePetName($name) {
    $name = preg_replace('/[0-9]/', '', $name);
    $name = preg_replace('/[^a-zA-ZáéíóúüñÁÉÍÓÚÜÑ\s\-\']/u', '', $name);
    $name = preg_replace('/\s+/', ' ', $name);
    return trim($name);
}

function containsProfanity($name) {
    $profanityList = [
        'puta', 'puto', 'pendejo', 'cabrón', 'cabron', 'coño', 'cojones', 'joder', 'mierda', 'imbécil', 'imbecil',
        'gilipollas', 'zorra', 'bastardo', 'malparido', 'hijueputa', 'maricón', 'maricon',
        'chucha', 'concha', 'culiao', 'weon', 'weón', 'weona', 'ctm', 'conchetumare',
        'fuck', 'shit', 'bitch', 'asshole', 'bastard', 'cunt', 'dick', 'pussy', 'whore', 'slut', 'motherfucker'
    ];
    $lower = strtolower($name);
    foreach ($profanityList as $word) {
        if (strpos($lower, $word) !== false) return true;
    }
    return false;
}

function hasExcessiveRepeats($name) {
    return preg_match('/(.)\1{3,}/u', $name);
}

function containsLetter($name) {
    return preg_match('/[a-zA-ZáéíóúüñÁÉÍÓÚÜÑ]/u', $name);
}
// ==================================================

if ($_SERVER["REQUEST_METHOD"] == "POST" && empty($error)) {
    $name = trim($_POST['name'] ?? '');
    $type_id = intval($_POST['type_id'] ?? 0);
    $breed_id = !empty($_POST['breed_id']) ? intval($_POST['breed_id']) : null;
    $gender = !empty($_POST['gender']) ? $_POST['gender'] : null;
    $dob = !empty($_POST['dob']) ? $_POST['dob'] : null;
    $medical_history = !empty($_POST['medical_history']) ? trim($_POST['medical_history']) : null;

    // Validación del nombre
    if (empty($name)) {
        $error = "El nombre de la mascota es obligatorio.";
    } else {
        $name = sanitizePetName($name);
        if (strlen($name) < 2) {
            $error = "El nombre debe tener al menos 2 caracteres válidos (letras, espacios, guiones).";
        } elseif (strlen($name) > 50) {
            $error = "El nombre no puede exceder los 50 caracteres.";
        } elseif (!containsLetter($name)) {
            $error = "El nombre debe contener al menos una letra (no solo símbolos).";
        } elseif (hasExcessiveRepeats($name)) {
            $error = "El nombre no puede tener un mismo carácter repetido más de 3 veces seguidas.";
        } elseif (containsProfanity($name)) {
            $error = "El nombre contiene palabras inapropiadas. Por favor, elige otro nombre.";
        }
    }

    // Determinar owner_id
    if (empty($error)) {
        if (in_array($role_name, ['Veterinario', 'admin'])) {
            $owner_id = intval($_POST['owner_id'] ?? 0);
            if ($owner_id <= 0) $error = "Debe seleccionar un dueño para la mascota.";
        } else {
            $owner_id = $user_id;
        }
    }

    // Validar especie
    if (empty($error) && $type_id == 0) {
        $error = "Debe seleccionar una especie.";
    }

    // Validar fecha de nacimiento según especie
    if (empty($error) && !empty($dob)) {
        // Obtener nombre del tipo de mascota
        $typeName = '';
        foreach ($pet_types as $pt) {
            if ($pt['id'] == $type_id) {
                $typeName = $pt['name'];
                break;
            }
        }
        if (empty($typeName)) {
            $error = "Especie no válida.";
        } else {
            $maxAge = getMaxAgeForType($typeName);
            $dobDate = new DateTime($dob);
            $today = new DateTime();
            $age = $today->diff($dobDate)->y;

            if ($age < 0) {
                $error = "La fecha de nacimiento no puede ser futura.";
            } elseif ($age > $maxAge) {
                $error = "La edad de la mascota ($age años) excede la esperanza de vida máxima para $typeName ($maxAge años).";
            }
        }
    }

    if (empty($error) && empty($name)) {
        $error = "El nombre es obligatorio.";
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
            $_POST = []; // Limpiar formulario
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
    <title>Registrar Mascota - VetCtrl</title>
    <link rel="stylesheet" href="../public/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* ===== RESET LOCAL ===== */
        * {
            box-sizing: border-box;
        }
        body {
            background-color: #f8f9fa;
            padding-top: 70px;
            font-family: 'Segoe UI', sans-serif;
        }
        .breadcrumb {
            max-width: 600px;
            margin: 10px auto 0;
            padding: 10px 20px;
            background: transparent;
            font-size: 0.95rem;
            word-break: break-word;
        }
        .breadcrumb a {
            color: #40916c;
            text-decoration: none;
        }
        .breadcrumb a:hover {
            text-decoration: underline;
        }
        .breadcrumb span {
            color: #6c757d;
        }
        .container {
            max-width: 600px;
            margin: 20px auto;
            background: white;
            padding: 30px;
            border-radius: 12px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.08);
            overflow: hidden;
        }
        h1 {
            color: #1b4332;
            border-bottom: 2px solid #b68b40;
            padding-bottom: 10px;
            margin-bottom: 25px;
            display: flex;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap;
        }
        .alert {
            padding: 15px 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            border-left: 5px solid;
            display: flex;
            align-items: center;
            gap: 12px;
        }
        .alert i { font-size: 1.4rem; }
        .alert-success { background: #d4edda; color: #155724; border-left-color: #28a745; }
        .alert-danger { background: #f8d7da; color: #721c24; border-left-color: #dc3545; }

        label {
            display: block;
            margin: 15px 0 5px;
            font-weight: 600;
            color: #1b4332;
        }
        input, select, textarea {
            width: 100%;
            padding: 10px;
            border: 2px solid #e0e0e0;
            border-radius: 6px;
            box-sizing: border-box;
        }
        input:focus, select:focus, textarea:focus {
            border-color: #40916c;
            outline: none;
        }
        .input-error {
            border-color: #dc3545 !important;
        }
        .error-message {
            color: #dc3545;
            font-size: 0.85rem;
            margin-top: 4px;
            display: none;
        }
        .btn {
            padding: 12px 25px;
            border: none;
            border-radius: 6px;
            font-weight: 600;
            cursor: pointer;
            background: #40916c;
            color: white;
            width: 100%;
            margin-top: 20px;
            transition: background 0.3s, transform 0.2s;
        }
        .btn:hover {
            background: #2d6a4f;
            transform: translateY(-2px);
        }
        .btn-secondary {
            background: #6c757d;
            color: white;
            padding: 12px 25px;
            border-radius: 6px;
            text-decoration: none;
            font-weight: 600;
            display: inline-block;
            margin-top: 20px;
            transition: background 0.3s;
            text-align: center;
        }
        .btn-secondary:hover {
            background: #5a6268;
            text-decoration: none;
            color: white;
        }
        @media (max-width: 600px) {
            .btn-secondary {
                width: 100%;
                display: block;
            }
        }
    </style>
    <script>
        // Datos de especies y su longevidad (en años)
        const lifespanMap = <?php echo json_encode($lifespanMap, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
        
        // Lista de tipos de mascotas con id y nombre (se genera desde PHP)
        const petTypes = <?php echo json_encode($pet_types, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
        
        // Lista de groserías
        const profanityList = [
            'puta', 'puto', 'pendejo', 'cabrón', 'cabron', 'coño', 'cojones', 'joder', 'mierda', 'imbécil', 'imbecil',
            'gilipollas', 'zorra', 'bastardo', 'malparido', 'hijueputa', 'maricón', 'maricon',
            'chucha', 'concha', 'culiao', 'weon', 'weón', 'weona', 'ctm', 'conchetumare',
            'fuck', 'shit', 'bitch', 'asshole', 'bastard', 'cunt', 'dick', 'pussy', 'whore', 'slut', 'motherfucker'
        ];

        function containsProfanity(str) {
            const lower = str.toLowerCase();
            for (let word of profanityList) {
                if (lower.includes(word)) return true;
            }
            return false;
        }

        function hasExcessiveRepeats(str) {
            return /(.)\1{3,}/.test(str);
        }

        function containsLetter(str) {
            return /[a-zA-ZáéíóúüñÁÉÍÓÚÜÑ]/.test(str);
        }

        function validatePetName() {
            const nameInput = document.getElementById('name');
            let name = nameInput.value;
            name = name.replace(/[0-9]/g, '');
            name = name.replace(/[^a-zA-ZáéíóúüñÁÉÍÓÚÜÑ\s\-\']/g, '');
            name = name.replace(/\s+/g, ' ').trim();
            nameInput.value = name;

            let isValid = true;
            let errorMsg = '';

            if (name.length < 2) {
                errorMsg = 'El nombre debe tener al menos 2 caracteres válidos.';
                isValid = false;
            } else if (name.length > 50) {
                errorMsg = 'El nombre no puede exceder los 50 caracteres.';
                isValid = false;
            } else if (!containsLetter(name)) {
                errorMsg = 'El nombre debe contener al menos una letra (no solo símbolos).';
                isValid = false;
            } else if (hasExcessiveRepeats(name)) {
                errorMsg = 'El nombre no puede tener un mismo carácter repetido más de 3 veces seguidas.';
                isValid = false;
            } else if (containsProfanity(name)) {
                errorMsg = 'El nombre contiene palabras inapropiadas. Por favor, elige otro nombre.';
                isValid = false;
            }

            const errorSpan = document.getElementById('name-error');
            if (!isValid) {
                nameInput.classList.add('input-error');
                errorSpan.textContent = errorMsg;
                errorSpan.style.display = 'block';
            } else {
                nameInput.classList.remove('input-error');
                errorSpan.textContent = '';
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
            if (!dob) {
                errorSpan.style.display = 'none';
                return true;
            }

            // Obtener longevidad máxima para esta especie
            let maxAge = lifespanMap['otros']; // default
            if (typeName && lifespanMap[typeName] !== undefined) {
                maxAge = lifespanMap[typeName];
            }

            const today = new Date();
            const birthDate = new Date(dob);
            if (isNaN(birthDate.getTime())) {
                errorSpan.textContent = 'Fecha inválida.';
                errorSpan.style.display = 'block';
                dobInput.classList.add('input-error');
                return false;
            }
            if (birthDate > today) {
                errorSpan.textContent = 'La fecha de nacimiento no puede ser futura.';
                errorSpan.style.display = 'block';
                dobInput.classList.add('input-error');
                return false;
            }

            let age = today.getFullYear() - birthDate.getFullYear();
            const monthDiff = today.getMonth() - birthDate.getMonth();
            if (monthDiff < 0 || (monthDiff === 0 && today.getDate() < birthDate.getDate())) {
                age--;
            }
            if (age > maxAge) {
                errorSpan.textContent = `La edad de la mascota (${age} años) excede la esperanza de vida máxima para ${selectedOption.textContent} (${maxAge} años).`;
                errorSpan.style.display = 'block';
                dobInput.classList.add('input-error');
                return false;
            } else {
                errorSpan.style.display = 'none';
                dobInput.classList.remove('input-error');
                return true;
            }
        }

        // Eventos
        document.addEventListener('DOMContentLoaded', function() {
            // Inicializar errores ocultos
            const nameError = document.getElementById('name-error');
            const dobError = document.getElementById('dob-error');
            if (nameError) nameError.style.display = 'none';
            if (dobError) dobError.style.display = 'none';

            const nameInput = document.getElementById('name');
            if (nameInput) {
                nameInput.addEventListener('input', validatePetName);
                nameInput.addEventListener('blur', validatePetName);
            }

            const typeSelect = document.getElementById('type_id');
            const dobInput = document.getElementById('dob');
            if (typeSelect && dobInput) {
                typeSelect.addEventListener('change', validateDateOfBirth);
                dobInput.addEventListener('change', validateDateOfBirth);
                dobInput.addEventListener('input', validateDateOfBirth);
            }

            // Validación final del formulario
            const form = document.querySelector('form');
            if (form) {
                form.addEventListener('submit', function(e) {
                    let valid = true;
                    if (!validatePetName()) valid = false;
                    if (dobInput && !validateDateOfBirth()) valid = false;
                    if (!valid) {
                        e.preventDefault();
                        alert('Por favor corrige los errores en el formulario.');
                    }
                });
            }

            // Filtrar razas
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
            if (typeSelect) typeSelect.addEventListener('change', filterBreeds);
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
