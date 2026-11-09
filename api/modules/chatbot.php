<?php
// api/modules/chatbot.php - Versión Multitarea

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

// 1. Contexto: Obtener mascotas del usuario
$stmtPets = $db->prepare("SELECT p.id, p.name, pt.name AS species_name FROM pets p LEFT JOIN pet_types pt ON p.type_id = pt.id WHERE p.owner_id = :id");
$stmtPets->execute([':id' => $user['id']]);
$mascotas = $stmtPets->fetchAll();

$listaMascotasTexto = "";
foreach ($mascotas as $m) {
    $listaMascotasTexto .= "- ID: {$m['id']}, Nombre: {$m['name']}\n";
}

// Estadísticas básicas
$stmtStats = $db->prepare("
    SELECT 
        (SELECT COUNT(*) FROM pets WHERE owner_id = :id1) as total_pets,
        (SELECT COUNT(*) FROM appointments WHERE attendant_id = :id2 AND status = 'PENDIENTE') as citas_pendientes
");
$stmtStats->execute([':id1' => $user['id'], ':id2' => $user['id']]);
$stats = $stmtStats->fetch();

// 2. Prompt del Sistema (El manual de instrucciones para la IA)
$prompt = "Eres VetBot, el asistente virtual inteligente de VetCtrl.
Usuario: '{$user['username']}' | Rol: '{$user['role_name']}'
Mascotas del usuario:
$listaMascotasTexto
Sus estadísticas actuales: Tiene {$stats['total_pets']} mascotas y {$stats['citas_pendientes']} citas pendientes.

TIENES 3 CAPACIDADES. Si el usuario pide una acción, RESPONDE ÚNICAMENTE CON UN JSON VÁLIDO (sin texto extra):

1. AGENDAR CITA:
   {"accion": "agendar_cita", "pet_id": ID, "fecha": "YYYY-MM-DD HH:MM:SS", "motivo": "texto"}
   (Asume horario 08:00 - 18:00. Calcula la fecha según lo que pida el usuario).

2. REGISTRAR MASCOTA (Paciente):
   {"accion": "registrar_mascota", "nombre": "texto", "especie": "Perro/Gato/Ave/etc", "genero": "Macho/Hembra"}

3. CONSULTAR ESTADÍSTICAS:
   {"accion": "ver_estadisticas"}

Si no es ninguna de las 3 acciones anteriores, responde de forma amable y profesional a la pregunta del usuario.

Pregunta del usuario: " . $message;

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
$botResponse = $textoIA;

// 4. Detectar y ejecutar acciones
try {
    // Limpiamos el texto por si Gemini agrega comillas de markdown
    $jsonLimpio = trim(str_replace(['```json', '```'], '', $textoIA));
    $accionData = json_decode($jsonLimpio, true);

    if ($accionData && isset($accionData['accion'])) {
        
        // --- ACCIÓN: AGENDAR CITA ---
        if ($accionData['accion'] === 'agendar_cita') {
            $pet_id = (int)$accionData['pet_id'];
            $fecha = $accionData['fecha'];
            $motivo = $accionData['motivo'];

            $stmt = $db->prepare("INSERT INTO appointments (pet_id, attendant_id, appointment_date, reason, status) VALUES (:pet_id, :attendant_id, :date, :reason, 'PENDIENTE')");
            $stmt->execute([':pet_id' => $pet_id, ':attendant_id' => $user['id'], ':date' => $fecha, ':reason' => "IA: " . $motivo]);
            
            $nombre = "tu mascota";
            foreach ($mascotas as $m) { if ($m['id'] == $pet_id) $nombre = $m['name']; }
            $botResponse = "✅ He agendado la cita para **{$nombre}** el día **{$fecha}** (Motivo: {$motivo}).";
        }

        // --- ACCIÓN: REGISTRAR MASCOTA ---
        elseif ($accionData['accion'] === 'registrar_mascota') {
            $nombre = $accionData['nombre'];
            $especie = $accionData['especie'];
            $genero = $accionData['genero'];

            // Buscar el ID de la especie
            $stmtEsp = $db->prepare("SELECT id FROM pet_types WHERE name LIKE :e LIMIT 1");
            $stmtEsp->execute([':e' => "%$especie%"]);
            $tipo = $stmtEsp->fetch();
            
            if ($tipo) {
                $stmt = $db->prepare("INSERT INTO pets (name, owner_id, attendant_id, type_id, gender, created_at) VALUES (:name, :owner, :attendant, :type, :gender, NOW())");
                $stmt->execute([':name' => $nombre, ':owner' => $user['id'], ':attendant' => $user['id'], ':type' => $tipo['id'], ':gender' => $genero]);
                $botResponse = "✅ ¡Paciente registrado! He añadido a **{$nombre}** ({$especie} - {$genero}) a tu lista de mascotas.";
            } else {
                $botResponse = "No pude registrar a la mascota porque la especie '{$especie}' no está en el sistema. Contacta al veterinario.";
            }
        }

        // --- ACCIÓN: ESTADÍSTICAS ---
        elseif ($accionData['accion'] === 'ver_estadisticas') {
            $botResponse = "📊 Aquí tienes tus estadísticas:\n- Tienes **{$stats['total_pets']}** mascotas registradas.\n- Tienes **{$stats['citas_pendientes']}** citas médicas pendientes.";
        }
    }
} catch (Exception $e) {
    // Si falla el JSON, usamos la respuesta normal de texto que dio la IA
}

jsonSuccess(['reply' => $botResponse], 'Respuesta del bot');
