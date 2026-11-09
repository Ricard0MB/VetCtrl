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
    // Solo sus propias mascotas
    $stmtPets = $db->prepare("SELECT p.id, p.name, pt.name AS species_name FROM pets p LEFT JOIN pet_types pt ON p.type_id = pt.id WHERE p.owner_id = :id");
    $stmtPets->execute([':id' => $user['id']]);
} else {
    // Admin y Vet ven todas
    $stmtPets = $db->query("SELECT p.id, p.name, pt.name AS species_name FROM pets p LEFT JOIN pet_types pt ON p.type_id = pt.id");
}
$mascotas = $stmtPets->fetchAll();

$listaMascotasTexto = "";
foreach ($mascotas as $m) {
    $listaMascotasTexto .= "- ID: {$m['id']}, Nombre: {$m['name']}\n";
}

// Estadísticas
$stmtStats = $db->prepare("
    SELECT 
        (SELECT COUNT(*) FROM pets WHERE owner_id = :id1) as total_pets,
        (SELECT COUNT(*) FROM appointments WHERE attendant_id = :id2 AND status = 'PENDIENTE') as citas_pendientes
");
$stmtStats->execute([':id1' => $user['id'], ':id2' => $user['id']]);
$stats = $stmtStats->fetch();

// 2. Construir el Prompt según el ROL
$permisoRegistro = $isOwner ? "NO puedes registrar otras mascotas. Solo el admin/vet puede." : "SÍ puedes registrar mascotas para cualquier dueño.";
$prompt = "Eres VetBot, el asistente virtual de VetCtrl.
Usuario: '{$user['username']}' | Rol: '{$user['role_name']}'
Mascotas accesibles:
$listaMascotasTexto
Regla de tu rol: $permisoRegistro
Estadísticas actuales: {$stats['total_pets']} mascotas, {$stats['citas_pendientes']} citas pendientes.

TIENES 3 ACCIONES. Responde SOLO con JSON si aplica:
1. AGENDAR CITA: {"accion": "agendar_cita", "pet_id": ID, "fecha": "YYYY-MM-DD HH:MM:SS", "motivo": "texto"}
2. REGISTRAR MASCOTA (Solo Admin/Vet): {"accion": "registrar_mascota", "nombre": "texto", "especie": "Perro/Gato", "genero": "Macho/Hembra"}
3. PEDIR ACLARACIÓN: Si el usuario pide algo con una mascota pero hay más de un nombre igual o no queda claro a cuál se refiere, devuelve: {"accion": "pedir_aclaracion", "pregunta": "¿A cuál de estas mascotas te refieres?", "opciones": [{"id": 1, "texto": "Luna (Perro)"}, {"id": 5, "texto": "Luna (Gato)"}]}

Si es una pregunta normal, responde amablemente.

Pregunta: " . $message;

// 3. Llamada a Gemini
$apiKey = 'AQ.Ab8RN6KayvMR5uPzBKesHE8XoXiXQT_Y8-IUXXkottni2NOI9g'; 
$url = "https://generativelanguage.googleapis.com/v1beta/models/gemini-3.5-flash-lite:generateContent?key=" . $apiKey;

$data = ["contents" => [["parts" => [["text" => $prompt]]]]];
$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
curl_setopt($ch, CURLOPT_TIMEOUT, 15);
$response = curl_exec($ch);
curl_close($ch);

$json = json_decode($response, true);
$textoIA = $json['candidates'][0]['content']['parts'][0]['text'] ?? '';
$responsePayload = ['texto' => $textoIA]; // Respuesta por defecto

// 4. Procesar acciones
try {
    $jsonLimpio = trim(str_replace(['```json', '```'], '', $textoIA));
    $accionData = json_decode($jsonLimpio, true);

    if ($accionData && isset($accionData['accion'])) {
        
        // --- PEDIR ACLARACIÓN (Botones) ---
        if ($accionData['accion'] === 'pedir_aclaracion') {
            $responsePayload = [
                'tipo' => 'opciones',
                'pregunta' => $accionData['pregunta'],
                'opciones' => $accionData['opciones']
            ];
        }
        // --- AGENDAR CITA ---
        elseif ($accionData['accion'] === 'agendar_cita') {
            $pet_id = (int)$accionData['pet_id'];
            
            // Seguridad: Si es propietario, verificar que la mascota sea suya
            $stmtCheck = $db->prepare("SELECT owner_id FROM pets WHERE id = :id");
            $stmtCheck->execute([':id' => $pet_id]);
            $dueno = $stmtCheck->fetch();

            if ($isOwner && $dueno['owner_id'] != $user['id']) {
                $responsePayload = ['texto' => "❌ No tienes permiso para agendar una cita para esa mascota."];
            } else {
                $fecha = $accionData['fecha'];
                $motivo = $accionData['motivo'];
                $stmt = $db->prepare("INSERT INTO appointments (pet_id, attendant_id, appointment_date, reason, status) VALUES (:pet_id, :attendant_id, :date, :reason, 'PENDIENTE')");
                $stmt->execute([':pet_id' => $pet_id, ':attendant_id' => $user['id'], ':date' => $fecha, ':reason' => "IA: " . $motivo]);
                
                $nombre = "tu mascota";
                foreach ($mascotas as $m) { if ($m['id'] == $pet_id) $nombre = $m['name']; }
                $responsePayload = ['texto' => "✅ He agendado la cita para **{$nombre}** el día **{$fecha}**."];
            }
        }
        // --- REGISTRAR MASCOTA ---
        elseif ($accionData['accion'] === 'registrar_mascota') {
            if ($isOwner) {
                $responsePayload = ['texto' => "❌ Lo siento, los propietarios no pueden registrar mascotas desde el chat. Por favor, usa la pestaña 'Registrar Mascota'."];
            } else {
                $nombre = $accionData['nombre'];
                $especie = $accionData['especie'];
                $genero = $accionData['genero'];

                $stmtEsp = $db->prepare("SELECT id FROM pet_types WHERE name LIKE :e LIMIT 1");
                $stmtEsp->execute([':e' => "%$especie%"]);
                $tipo = $stmtEsp->fetch();
                
                if ($tipo) {
                    $stmt = $db->prepare("INSERT INTO pets (name, owner_id, attendant_id, type_id, gender, created_at) VALUES (:name, :owner, :attendant, :type, :gender, NOW())");
                    $stmt->execute([':name' => $nombre, ':owner' => $user['id'], ':attendant' => $user['id'], ':type' => $tipo['id'], ':gender' => $genero]);
                    $responsePayload = ['texto' => "✅ ¡Paciente registrado! He añadido a **{$nombre}** ({$especie}) a tu lista."];
                } else {
                    $responsePayload = ['texto' => "No pude registrar la mascota porque la especie '{$especie}' no existe."];
                }
            }
        }
    }
} catch (Exception $e) {
    // Mantiene la respuesta de texto por defecto
}

jsonSuccess(['reply' => $responsePayload], 'Respuesta del bot');
