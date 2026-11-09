<?php
// api/modules/chatbot.php - Versión Segura y Contextual

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

// 2. Construir el Prompt según el ROL (HEREDOC: no requiere escapar comillas)
$permisoRegistro = $isOwner ? "NO puedes registrar mascotas. Solo el admin/vet puede." : "SÍ puedes registrar mascotas para cualquier dueño.";

$prompt = <<<PROMPT
Eres VetBot, el asistente virtual de VetCtrl.
Usuario: '{$user['username']}' | Rol: '{$user['role_name']}'
Mascotas accesibles:
{$listaMascotasTexto}
Regla de tu rol: {$permisoRegistro}
Estadísticas actuales: {$stats['total_pets']} mascotas, {$stats['citas_pendientes']} citas pendientes.

REGLAS DE RESPUESTA (obligatorias):
- Si el usuario pide AGENDAR una cita -> responde ÚNICAMENTE con este JSON, sin texto adicional:
  {"accion":"agendar_cita","pet_id":ID,"fecha":"YYYY-MM-DD HH:MM:SS","motivo":"texto"}
- Si el usuario pide REGISTRAR una mascota y eres Admin/Vet -> responde ÚNICAMENTE con este JSON:
  {"accion":"registrar_mascota","nombre":"texto","especie":"texto","genero":"Macho/Hembra"}
- Si hay AMBIGÜEDAD (varias mascotas con el mismo nombre, o no queda claro a cuál se refiere) -> responde ÚNICAMENTE con este JSON:
  {"accion":"pedir_aclaracion","pregunta":"texto","opciones":[{"id":1,"texto":"Luna (Perro)"}]}
- Para CUALQUIER OTRA COSA (saludos, dudas, consultas, permisos denegados) -> responde en TEXTO PLANO, sin JSON, sin llaves, sin comillas dobles alrededor de la respuesta.

NUNCA mezcles texto y JSON en la misma respuesta. NUNCA uses bloques de código.

Pregunta: {$message}
PROMPT;

// 3. Llamada a Gemini (key y modelo desde variables de entorno)
$apiKey = getenv('GEMINI_API_KEY');
if (!$apiKey) {
    jsonError('Error de configuración: Falta la variable GEMINI_API_KEY en el servidor.', 500);
}
$modelo = getenv('GEMINI_MODEL') ?: 'gemini-3.5-flash';
$url = "https://generativelanguage.googleapis.com/v1beta/models/{$modelo}:generateContent?key=" . $apiKey;

$data = [
    "contents" => [["parts" => [["text" => $prompt]]]]
];

$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
curl_setopt($ch, CURLOPT_TIMEOUT, 20);
$response = curl_exec($ch);
$curlErr = curl_error($ch);
curl_close($ch);

if ($response === false) {
    jsonError('Error de conexión con Gemini: ' . $curlErr, 502);
}

$json = json_decode($response, true);
$textoIA = trim($json['candidates'][0]['content']['parts'][0]['text'] ?? '');

// LOG de depuración (míralo en Render -> Logs si algo falla)
error_log("GEMINI RAW: " . $textoIA);

// 4. Parseo tolerante: intenta JSON; si no, muestra texto
$accionData = null;
$responsePayload = ['texto' => 'No entendí tu solicitud.'];

if ($textoIA !== '') {
    // Limpieza: bloques ```json ... ```
    $jsonLimpio = $textoIA;
    if (preg_match('/```(?:json)?\s*(.*?)\s*```/s', $jsonLimpio, $m)) {
        $jsonLimpio = $m[1];
    }
    // Comillas tipográficas
    $jsonLimpio = str_replace(['“', '”', '‘', '’'], ['"', '"', "'", "'"], $jsonLimpio);
    $jsonLimpio = trim($jsonLimpio);

    // Intento 1: parsear tal cual
    $parsed = json_decode($jsonLimpio, true);

    // Intento 2: si falló, extraer el primer objeto {...}
    if (!is_array($parsed) && preg_match('/\{.*\}/s', $jsonLimpio, $m2)) {
        $parsed = json_decode($m2[0], true);
    }

    if (is_array($parsed) && isset($parsed['accion'])) {
        $accionData = $parsed;
    } elseif (is_array($parsed)) {
        $textoExtraido = null;
        foreach (['respuesta', 'texto', 'message', 'reply', 'mensaje'] as $k) {
            if (!empty($parsed[$k]) && is_string($parsed[$k])) {
                $textoExtraido = $parsed[$k];
                break;
            }
        }
        $responsePayload = ['texto' => $textoExtraido ?? $textoIA];
    } else {
        $responsePayload = ['texto' => $textoIA];
    }

    error_log("JSON PARSEADO: " . json_encode($accionData));
}

// 5. Procesar acciones
try {
    if (is_array($accionData) && isset($accionData['accion'])) {

        // --- PEDIR ACLARACIÓN (Botones) ---
        if ($accionData['accion'] === 'pedir_aclaracion') {
            $responsePayload = [
                'tipo' => 'opciones',
                'pregunta' => $accionData['pregunta'] ?? '¿A cuál te refieres?',
                'opciones' => $accionData['opciones'] ?? []
            ];
        }
        // --- AGENDAR CITA ---
        elseif ($accionData['accion'] === 'agendar_cita') {
            $pet_id = (int)($accionData['pet_id'] ?? 0);
            $fecha = $accionData['fecha'] ?? '';
            $motivo = $accionData['motivo'] ?? '';

            $dt = DateTime::createFromFormat('Y-m-d H:i:s', $fecha);
            $fechaValida = $dt && $dt->format('Y-m-d H:i:s') === $fecha && $dt > new DateTime();

            if ($pet_id <= 0) {
                $responsePayload = ['texto' => "❌ No se especificó una mascota válida."];
            } elseif (!$fechaValida) {
                $responsePayload = ['texto' => "❌ La fecha '{$fecha}' no es válida o ya pasó. Usa formato YYYY-MM-DD HH:MM:SS."];
            } else {
                $stmtCheck = $db->prepare("SELECT owner_id FROM pets WHERE id = :id");
                $stmtCheck->execute([':id' => $pet_id]);
                $dueno = $stmtCheck->fetch();

                if (!$dueno) {
                    $responsePayload = ['texto' => "❌ La mascota no existe."];
                } elseif ($isOwner && (int)$dueno['owner_id'] !== (int)$user['id']) {
                    $responsePayload = ['texto' => "❌ No tienes permiso para agendar una cita para esa mascota."];
                } else {
                    $stmt = $db->prepare("INSERT INTO appointments (pet_id, attendant_id, appointment_date, reason, status) VALUES (:pet_id, :attendant_id, :date, :reason, 'PENDIENTE')");
                    $stmt->execute([':pet_id' => $pet_id, ':attendant_id' => $user['id'], ':date' => $fecha, ':reason' => "IA: " . $motivo]);

                    $nombre = "tu mascota";
                    foreach ($mascotas as $m) { if ((int)$m['id'] === $pet_id) { $nombre = $m['name']; break; } }
                    $responsePayload = ['texto' => "✅ He agendado la cita para **{$nombre}** el día **{$fecha}**."];
                }
            }
        }
        // --- REGISTRAR MASCOTA ---
        elseif ($accionData['accion'] === 'registrar_mascota') {
            if ($isOwner) {
                $responsePayload = ['texto' => "❌ Lo siento, los propietarios no pueden registrar mascotas desde el chat. Por favor, usa la pestaña 'Registrar Mascota'."];
            } else {
                $nombre = $accionData['nombre'] ?? '';
                $especie = $accionData['especie'] ?? '';
                $genero = $accionData['genero'] ?? '';

                if ($nombre === '' || $especie === '') {
                    $responsePayload = ['texto' => "❌ Me faltan datos para registrar la mascota. Necesito al menos nombre y especie."];
                } else {
                    // Búsqueda dinámica: busca "oveja" en tu tabla pet_types
                    $stmtEsp = $db->prepare("SELECT id FROM pet_types WHERE name LIKE :e LIMIT 1");
                    $stmtEsp->execute([':e' => "%$especie%"]);
                    $tipo = $stmtEsp->fetch();

                    if ($tipo) {
                        $stmt = $db->prepare("INSERT INTO pets (name, owner_id, attendant_id, type_id, gender, created_at) VALUES (:name, :owner, :attendant, :type, :gender, NOW())");
                        $stmt->execute([':name' => $nombre, ':owner' => $user['id'], ':attendant' => $user['id'], ':type' => $tipo['id'], ':gender' => $genero]);
                        $responsePayload = ['texto' => "✅ ¡Paciente registrado! He añadido a **{$nombre}** ({$especie}) a tu lista."];
                    } else {
                        // Mensaje de error dinámico y útil
                        $responsePayload = ['texto' => "No pude registrar la mascota porque la especie '{$especie}' no está registrada en el sistema. Verifica las especies disponibles en la app."];
                    }
                }
            }
        }
    }
} catch (Exception $e) {
    error_log("CHATBOT EXCEPTION: " . $e->getMessage());
}

jsonSuccess(['reply' => $responsePayload], 'Respuesta del bot');
