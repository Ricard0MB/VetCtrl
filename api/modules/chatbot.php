<?php
// api/modules/chatbot.php

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

// 1. Obtener información del contexto (CORREGIDO: p.id y p.name)
$stmtPets = $db->prepare("
    SELECT p.id, p.name, pt.name AS species_name 
    FROM pets p 
    LEFT JOIN pet_types pt ON p.type_id = pt.id 
    WHERE p.owner_id = :id
");
$stmtPets->execute([':id' => $user['id']]);
$mascotas = $stmtPets->fetchAll();

$listaMascotasTexto = "";
foreach ($mascotas as $m) {
    $listaMascotasTexto .= "- ID: {$m['id']}, Nombre: {$m['name']}\n";
}

// 2. Prompt con Instrucciones para ACCIONES
$prompt = "Eres VetBot, el asistente de VetCtrl. El usuario es '{$user['username']}'.
MASCOTAS REGISTRADAS DEL USUARIO:
$listaMascotasTexto

INSTRUCCIONES:
1. Si el usuario pide agendar una cita, DEBES responder ÚNICAMENTE con un objeto JSON con este formato exacto (sin texto extra):
   {\"accion\": \"agendar_cita\", \"pet_id\": ID_MASCOTA, \"fecha\": \"YYYY-MM-DD HH:MM:SS\", \"motivo\": \"motivo de la cita\"}
   * Usa el ID de la mascota de la lista de arriba.
   * Asume que el horario es de 08:00 a 18:00 y debes calcular la fecha exacta basándote en lo que pide el usuario (ej: 'mañana a las 10').
2. Si el usuario hace una pregunta general o médica, responde normalmente de forma amable.
3. Si no sabes de qué habla, pide aclaración.

Pregunta del usuario: " . $message;

// 3. Llamada a la API de Gemini
$apiKey = 'AQ.Ab8RN6KayvMR5uPzBKesHE8XoXiXQT_Y8-IUXXkottni2NOI9g'; // Tu clave activa
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

// 4. Detectar si la IA decidió ejecutar una acción
if (strpos($textoIA, '"accion": "agendar_cita"') !== false) {
    // Limpiamos el texto por si Gemini agregó las típicas comillas triples de Markdown ```json
    $jsonLimpio = trim(str_replace(['```json', '```'], '', $textoIA));
    $accionData = json_decode($jsonLimpio, true);

    if ($accionData && isset($accionData['pet_id'])) {
        $pet_id = (int)$accionData['pet_id'];
        $fecha = $accionData['fecha'];
        $motivo = $accionData['motivo'];

        try {
            // Insertar la cita en la base de datos
            $stmtInsert = $db->prepare("INSERT INTO appointments (pet_id, attendant_id, appointment_date, reason, status, created_at) VALUES (:pet_id, :attendant_id, :date, :reason, 'PENDIENTE', NOW())");
            $stmtInsert->execute([
                ':pet_id' => $pet_id,
                ':attendant_id' => $user['id'],
                ':date' => $fecha,
                ':reason' => "Agendada por IA: " . $motivo
            ]);
            
            // Buscar el nombre de la mascota para la respuesta
            $nombreMascota = "tu mascota";
            foreach ($mascotas as $m) { if ($m['id'] == $pet_id) $nombreMascota = $m['name']; }

            $botResponse = "✅ ¡Listo! He agendado la cita para **{$nombreMascota}** el día **{$fecha}** por el motivo: *{$motivo}*. Puedes verificarla en la pestaña 'Mis Citas'.";
        } catch (PDOException $e) {
            $botResponse = "Lo siento, intenté agendar la cita pero hubo un error en el sistema.";
        }
    }
}

jsonSuccess(['reply' => $botResponse], 'Respuesta del bot');
