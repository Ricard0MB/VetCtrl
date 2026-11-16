<?php
// api/modules/chatbot.php - Versión Segura, Rápida y con Menú

require_once __DIR__ . '/../core/db.php';
require_once __DIR__ . '/../core/response.php';
require_once __DIR__ . '/../core/auth.php';

$method = getMethod();
if ($method !== 'POST') jsonError('Método no permitido', 405);

$user = requireAuth();
$db = getDB();
$body = getJsonBody();
$message = trim($body['message'] ?? '');
if (empty($message)) jsonError('El mensaje no puede estar vacío', 400);

// 1. Obtener mascotas según el ROL
$isOwner = ($user['role_name'] === 'Propietario');

if ($isOwner) {
    $stmtPets = $db->prepare("SELECT p.id, p.name, pt.name AS species_name FROM pets p LEFT JOIN pet_types pt ON p.type_id = pt.id WHERE p.owner_id = :id");
    $stmtPets->execute([':id' => $user['id']]);
} else {
    $stmtPets = $db->query("SELECT p.id, p.name, pt.name AS species_name FROM pets p LEFT JOIN pet_types pt ON p.type_id = pt.id");
}
$mascotas = $stmtPets->fetchAll();

$listaMascotasTexto = "";
foreach ($mascotas as $m) {
    $listaMascotasTexto .= "- ID: {$m['id']}, Nombre: {$m['name']}, Especie: {$m['species_name']}\n";
}

// Estadísticas
$stmtStats = $db->prepare("
    SELECT 
        (SELECT COUNT(*) FROM pets WHERE owner_id = :id1) as total_pets,
        (SELECT COUNT(*) FROM appointments WHERE attendant_id = :id2 AND status = 'PENDIENTE') as citas_pendientes
");
$stmtStats->execute([':id1' => $user['id'], ':id2' => $user['id']]);
$stats = $stmtStats->fetch();

$permisoRegistro = $isOwner ? "NO puedes registrar mascotas. Solo el admin/vet puede." : "SÍ puedes registrar mascotas para cualquier dueño.";

// 2. Prompt actualizado con menú y aviso médico
$prompt = <<<PROMPT
Eres VetBot, el asistente virtual de VetCtrl.
Usuario: '{$user['username']}' | Rol: '{$user['role_name']}'
Mascotas accesibles:
{$listaMascotasTexto}
Regla de tu rol: {$permisoRegistro}
Estadísticas actuales: {$stats['total_pets']} mascotas, {$stats['citas_pendientes']} citas pendientes.

REGLAS DE RESPUESTA (obligatorias):
- Si el usuario envía "🩺 Consulta médica" o pregunta por síntomas/salud -> Responde amablemente y SIEMPRE termina con este texto exacto en una línea aparte: "⚠️ Recuerde que esta información es solo un consejo orientativo y no reemplaza una visita al especialista veterinario."
- Si el usuario pide AGENDAR una cita -> responde ÚNICAMENTE con este JSON: {"accion":"agendar_cita","pet_id":ID,"fecha":"YYYY-MM-DD HH:MM:SS","motivo":"texto"}
- Si el usuario pide REGISTRAR una mascota y eres Admin/Vet -> Si faltan datos, pregúntalos. Si los tienes, responde SOLO con JSON: {"accion":"registrar_mascota","nombre":"texto","especie":"texto","genero":"Macho/Hembra"}
- Si el usuario pide EDITAR una mascota -> Pregúntale qué desea cambiar. Si ya lo dijo, responde SOLO con JSON: {"accion":"editar_mascota","pet_id":ID,"nombre":"texto"}
- Si hay AMBIGÜEDAD -> responde ÚNICAMENTE con este JSON: {"accion":"pedir_aclaracion","pregunta":"texto","opciones":[{"id":1,"texto":"Luna (Perro)"}]}
- Para CUALQUIER OTRA COSA -> responde en TEXTO PLANO, sin JSON.

NUNCA mezcles texto y JSON. NUNCA uses bloques de código.

Pregunta: {$message}
PROMPT;

// 3. Llamada a Gemini (Optimizada para velocidad)
$apiKey = getenv('GEMINI_API_KEY');
if (!$apiKey) jsonError('Error de configuración: Falta GEMINI_API_KEY', 500);
$modelo = getenv('GEMINI_MODEL') ?: 'gemini-3.5-flash';
$url = "https://generativelanguage.googleapis.com/v1beta/models/{$modelo}:generateContent?key=" . $apiKey;

$data = [
    "contents" => [["parts" => [["text" => $prompt]]]],
    // 👇 ESTO HACE QUE RESPONDA EN 1 O 2 SEGUNDOS
    "generationConfig" => [
        "thinkingConfig" => ["thinkingLevel" => "minimal"] 
    ]
];

$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
curl_setopt($ch, CURLOPT_TIMEOUT, 25);
$response = curl_exec($ch);
$curlErr = curl_error($ch);
curl_close($ch);

if ($response === false) jsonError('Error de conexión: ' . $curlErr, 502);

$json = json_decode($response, true);
$textoIA = trim($json['candidates'][0]['content']['parts'][0]['text'] ?? '');
$responsePayload = ['texto' => 'No entendí tu solicitud.'];

// 4. Parseo tolerante
if ($textoIA !== '') {
    $jsonLimpio = trim($textoIA);
    if (preg_match('/```(?:json)?\s*(.*?)\s*```/s', $jsonLimpio, $m)) {
        $jsonLimpio = $m[1];
    }
    $jsonLimpio = str_replace(['“', '”', '‘', '’'], ['"', '"', "'", "'"], $jsonLimpio);
    if (preg_match('/\{.*\}/s', $jsonLimpio, $m2)) {
        $jsonLimpio = $m2[0];
    }

    $accionData = json_decode($jsonLimpio, true);

    if (is_array($accionData) && isset($accionData['accion'])) {
        try {
            if ($accionData['accion'] === 'pedir_aclaracion') {
                $responsePayload = ['tipo' => 'opciones', 'pregunta' => $accionData['pregunta'], 'opciones' => $accionData['opciones']];
            } 
            elseif ($accionData['accion'] === 'agendar_cita') {
                $pet_id = (int)($accionData['pet_id'] ?? 0);
                $fecha = $accionData['fecha'] ?? '';
                $stmtCheck = $db->prepare("SELECT owner_id FROM pets WHERE id = :id");
                $stmtCheck->execute([':id' => $pet_id]);
                $dueno = $stmtCheck->fetch();
                if ($pet_id <= 0 || ($isOwner && (int)$dueno['owner_id'] !== (int)$user['id'])) {
                    $responsePayload = ['texto' => "❌ No tienes permiso o la mascota no es válida."];
                } else {
                    $stmt = $db->prepare("INSERT INTO appointments (pet_id, attendant_id, appointment_date, reason, status) VALUES (:pet_id, :attendant_id, :date, :reason, 'PENDIENTE')");
                    $stmt->execute([':pet_id' => $pet_id, ':attendant_id' => $user['id'], ':date' => $fecha, ':reason' => "IA: " . ($accionData['motivo'] ?? '')]);
                    $responsePayload = ['texto' => "✅ He agendado la cita para el día **{$fecha}**."];
                }
            }
            elseif ($accionData['accion'] === 'registrar_mascota') {
                if ($isOwner) {
                    $responsePayload = ['texto' => "❌ Los propietarios no pueden registrar mascotas desde el chat."];
                } else {
                    $especie = $accionData['especie'] ?? '';
                    $stmtEsp = $db->prepare("SELECT id FROM pet_types WHERE name LIKE :e LIMIT 1");
                    $stmtEsp->execute([':e' => "%$especie%"]);
                    $tipo = $stmtEsp->fetch();
                    if ($tipo) {
                        $stmt = $db->prepare("INSERT INTO pets (name, owner_id, attendant_id, type_id, gender, created_at) VALUES (:name, :owner, :attendant, :type, :gender, NOW())");
                        $stmt->execute([':name' => $accionData['nombre'], ':owner' => $user['id'], ':attendant' => $user['id'], ':type' => $tipo['id'], ':gender' => $accionData['genero']]);
                        $responsePayload = ['texto' => "✅ ¡Paciente **{$accionData['nombre']}** registrado con éxito!"];
                    } else {
                        $responsePayload = ['texto' => "❌ Especie '{$especie}' no válida o no existe en el sistema."];
                    }
                }
            }
            elseif ($accionData['accion'] === 'editar_mascota') {
                $pet_id = (int)($accionData['pet_id'] ?? 0);
                $stmtCheck = $db->prepare("SELECT owner_id FROM pets WHERE id = :id");
                $stmtCheck->execute([':id' => $pet_id]);
                $dueno = $stmtCheck->fetch();
                
                if ($pet_id <= 0 || ($isOwner && (int)$dueno['owner_id'] !== (int)$user['id'])) {
                    $responsePayload = ['texto' => "❌ No tienes permiso para editar esa mascota."];
                } else {
                    $stmt = $db->prepare("UPDATE pets SET name = :name WHERE id = :id");
                    $stmt->execute([':name' => $accionData['nombre'], ':id' => $pet_id]);
                    $responsePayload = ['texto' => "✅ He actualizado el nombre de la mascota a **{$accionData['nombre']}**."];
                }
            }
        } catch (Exception $e) {
            error_log("Error acción: " . $e->getMessage());
        }
    } else {
        // Si es texto plano, verificar si es consulta médica para forzar el aviso
        if (stripos($message, 'consulta') !== false || stripos($message, 'síntoma') !== false || stripos($message, 'duele') !== false || stripos($message, 'salud') !== false) {
            if (strpos($textoIA, '⚠️ Recuerde') === false) {
                $textoIA .= "\n\n⚠️ Recuerde que esta información es solo un consejo orientativo y no reemplaza una visita al especialista veterinario.";
            }
        }
        $responsePayload = ['texto' => $textoIA];
    }
}

jsonSuccess(['reply' => $responsePayload], 'Respuesta del bot');
