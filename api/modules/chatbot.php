<?php
// api/modules/chatbot.php
// Módulo de Chatbot con IA y capacidad de consultar la BD

require_once __DIR__ . '/../core/db.php';
require_once __DIR__ . '/../core/response.php';
require_once __DIR__ . '/../core/auth.php';

$method = getMethod();
$segments = getUriSegments();

if ($method !== 'POST') {
    jsonError('Método no permitido', 405);
}

$user = requireAuth();
$db = getDB();
$body = getJsonBody();

$message = trim($body['message'] ?? '');
if (empty($message)) jsonError('El mensaje no puede estar vacío', 400);

// 1. Obtener datos reales del usuario para darle contexto a la IA
// Consultamos cuántas mascotas tiene
$stmtPets = $db->prepare("SELECT name, species_name FROM pets p LEFT JOIN pet_types pt ON p.type_id = pt.id WHERE owner_id = :id");
$stmtPets->execute([':id' => $user['id']]);
$mascotas = $stmtPets->fetchAll();

$mascotasTexto = empty($mascotas) ? "Ninguna" : "";
foreach ($mascotas as $m) {
    $mascotasTexto .= "- " . $m['name'] . " (" . $m['species_name'] . ")\n";
}

// Consultamos vacunas pendientes
$stmtVacunas = $db->prepare("
    SELECT p.name as pet_name, v.next_due_date, vt.name as vaccine_name 
    FROM vaccines v
    JOIN pets p ON v.pet_id = p.id
    LEFT JOIN vaccine_types vt ON v.vaccine_type_id = vt.id
    WHERE p.owner_id = :id AND v.next_due_date <= DATE_ADD(CURDATE(), INTERVAL 60 DAY)
");
$stmtVacunas->execute([':id' => $user['id']]);
$vacunas = $stmtVacunas->fetchAll();

$vacunasTexto = empty($vacunas) ? "Ninguna pendiente" : "";
foreach ($vacunas as $v) {
    $vacunasTexto .= "- " . $v['pet_name'] . ": " . $v['vaccine_name'] . " (Vence: " . $v['next_due_date'] . ")\n";
}

// 2. Construir el Prompt del Sistema (las instrucciones estrictas para la IA)
$prompt = "Eres VetBot, el asistente virtual de la clínica veterinaria VetCtrl.
El usuario que te habla se llama '{$user['username']}' y tiene el rol de '{$user['role_name']}'.
Aquí tienes su información real actual de la base de datos (ÚSALA para responder):
MASCOTAS DEL USUARIO:
$mascotasTexto
VACUNAS PRÓXIMAS A VENCER:
$vacunasTexto

REGLAS ESTRICTAS:
1. Responde de forma amable, breve y profesional.
2. Si el usuario pregunta por sus mascotas o vacunas, usa la información de arriba.
3. Si el usuario quiere agendar una cita, indícale que puede ir a la pestaña 'Nueva Cita' o al Dashboard.
4. NO inventes datos que no estén en la lista. Si no sabes algo de la clínica, di que no tienes esa información.
5. Si te preguntan cosas médicas (síntomas, enfermedades), actúa como un veterinario dando consejos generales, pero aclara que deben traer a la mascota a la clínica para un diagnóstico real.

Pregunta del usuario: " . $message;

// 3. Llamada a la API de Gemini
$apiKey = 'AQ.Ab8RN6LBY5R9RZm4lzufOX_DGqjs8fXHR5Zj7T7aYsFt4yZJEQ'; 
$url = "https://generativelanguage.googleapis.com/v1beta/models/gemini-1.5-flash:generateContent?key=" . $apiKey;

$data = [
    "contents" => [
        ["parts" => [["text" => $prompt]]]
    ]
];

$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
curl_setopt($ch, CURLOPT_TIMEOUT, 15);

$response = curl_exec($ch);
$err = curl_error($ch);
curl_close($ch);

if ($err) {
    $botResponse = "Lo siento, mi sistema de IA está desconectado. 😔 Intenta de nuevo en un momento.";
} else {
    $json = json_decode($response, true);
    if (isset($json['candidates'][0]['content']['parts'][0]['text'])) {
        $botResponse = $json['candidates'][0]['content']['parts'][0]['text'];
    } else {
        $botResponse = "No pude procesar tu solicitud. Intenta preguntar por tus mascotas o vacunas.";
    }
}

jsonSuccess(['reply' => $botResponse], 'Respuesta del bot');
