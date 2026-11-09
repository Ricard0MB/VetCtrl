<?php
// api/modules/chatbot.php
// Módulo de Chatbot (Reglas + IA Real con Gemini)

require_once __DIR__ . '/../core/db.php';
require_once __DIR__ . '/../core/response.php';
require_once __DIR__ . '/../core/auth.php';

$method = getMethod();
$segments = getUriSegments();
$action = $segments[1] ?? null;

if ($method !== 'POST') {
    jsonError('Método no permitido', 405);
}

$user = requireAuth();
$db = getDB();
$body = getJsonBody();

$message = trim($body['message'] ?? '');
$message_lower = mb_strtolower($message, 'UTF-8');

if (empty($message)) {
    jsonError('El mensaje no puede estar vacío', 400);
}

// Función auxiliar para buscar palabras clave
function containsAny($text, $keywords) {
    foreach ($keywords as $word) {
        if (strpos($text, $word) !== false) return true;
    }
    return false;
}

$botResponse = "";

// ==========================================
// 1. LÓGICA DE REGLAS (Rápida y usa la Base de Datos)
// ==========================================

// SALUDOS
if (containsAny($message_lower, ['hola', 'buenos dias', 'buenas tardes', 'buenas noches', 'saludos'])) {
    $botResponse = "¡Hola " . $user['username'] . "! 👋 Soy VetBot. Puedo consultar tus mascotas, ver vacunas pendientes o responder tus dudas sobre veterinaria. ¿En qué te ayudo?";
}
// CONSULTAR MASCOTAS
elseif (containsAny($message_lower, ['mis mascotas', 'mascotas tengo', 'cuantas mascotas', 'mis perros', 'mis gatos'])) {
    $stmt = $db->prepare("SELECT name FROM pets WHERE owner_id = :id");
    $stmt->execute([':id' => $user['id']]);
    $pets = $stmt->fetchAll();
    
    if (count($pets) > 0) {
        $names = implode(", ", array_column($pets, 'name'));
        $botResponse = "Actualmente tienes " . count($pets) . " mascota(s) registrada(s) a tu nombre: " . $names . ". 🐾";
    } else {
        $botResponse = "No tienes mascotas registradas todavía. Puedes agregar una desde la pestaña 'Mascotas'.";
    }
}
// VACUNAS PENDIENTES
elseif (containsAny($message_lower, ['vacuna', 'vacunas', 'vence', 'proxima dosis'])) {
    $stmt = $db->prepare("
        SELECT p.name as pet_name, v.next_due_date, vt.name as vaccine_name 
        FROM vaccines v
        JOIN pets p ON v.pet_id = p.id
        LEFT JOIN vaccine_types vt ON v.vaccine_type_id = vt.id
        WHERE p.owner_id = :id AND v.next_due_date <= DATE_ADD(CURDATE(), INTERVAL 60 DAY)
        ORDER BY v.next_due_date ASC
    ");
    $stmt->execute([':id' => $user['id']]);
    $alerts = $stmt->fetchAll();
    
    if (count($alerts) > 0) {
        $botResponse = "⚠️ Tienes vacunas próximas a vencer:\n";
        foreach ($alerts as $a) {
            $botResponse .= "- " . $a['pet_name'] . ": " . $a['vaccine_name'] . " (Vence: " . $a['next_due_date'] . ")\n";
        }
        $botResponse .= "Te recomiendo agendar una cita pronto.";
    } else {
        $botResponse = "¡Buenas noticias! Todas las vacunas de tus mascotas están al día por los próximos 60 días. 💉";
    }
}
// CITAS
elseif (containsAny($message_lower, ['cita', 'agendar', 'reservar', 'turno'])) {
    $botResponse = "Para agendar una cita, puedes ir a la pestaña 'Asistente' y usar el formulario de 'Nueva Cita'. Allí solo debes seleccionar tu mascota, el motivo y la fecha. 📅";
}
// HORARIOS
elseif (containsAny($message_lower, ['horario', 'abren', 'cierran', 'atienden'])) {
    $stmt = $db->query("SELECT config_key, config_value FROM system_config WHERE config_key IN ('horario_apertura', 'horario_cierre', 'dias_trabajo')");
    $config = [];
    while ($row = $stmt->fetch()) { $config[$row['config_key']] = $row['config_value']; }
    
    $botResponse = "Nuestro horario de atención es de " . ($config['horario_apertura'] ?? '08:00') . " a " . ($config['horario_cierre'] ?? '18:00') . " hrs, los días " . ($config['dias_trabajo'] ?? 'Lunes a Viernes') . ".";
}
// DESPEDIDA
elseif (containsAny($message_lower, ['adios', 'gracias', 'hasta luego', 'chao'])) {
    $botResponse = "¡De nada! Si necesitas algo más, aquí estaré. ¡Que tengas un excelente día! 🐶🐱";
}
// ==========================================
// 2. SI NO ENTENDIÓ, USAR INTELIGENCIA ARTIFICIAL (GEMINI)
// ==========================================
else {
    // Tu API Key de Gemini
    $apiKey = 'AQ.Ab8RN6LBY5R9RZm4lzufOX_DGqjs8fXHR5Zj7T7aYsFt4yZJEQ'; 
    
    // URL de la API de Gemini (Modelo 1.5 Flash - es rápido y gratuito)
    $url = "https://generativelanguage.googleapis.com/v1beta/models/gemini-1.5-flash:generateContent?key=" . $apiKey;
    
    // Contexto para que la IA sepa que es un veterinario
    $prompt = "Eres VetBot, un asistente virtual de una clínica veterinaria. Responde de forma amable, breve y profesional a la siguiente consulta del usuario: " . $message;

    $data = [
        "contents" => [
            ["parts" => [["text" => $prompt]]]
        ]
    ];
    
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    
    // Evitar que la petición se quede colgada
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    
    $response = curl_exec($ch);
    $err = curl_error($ch);
    curl_close($ch);
    
    if ($err) {
        $botResponse = "Lo siento, en este momento no puedo conectarme a mi cerebro de IA. 😔 Por favor, intenta de nuevo más tarde.";
    } else {
        $json = json_decode($response, true);
        // Extraer el texto de la respuesta de Gemini
        if (isset($json['candidates'][0]['content']['parts'][0]['text'])) {
            $botResponse = $json['candidates'][0]['content']['parts'][0]['text'];
        } else {
            // Si falla la IA, fallback por defecto
            $botResponse = "No estoy seguro de cómo responder a eso. Intenta preguntarme por 'mis mascotas', 'vacunas' o 'citas'.";
        }
    }
}

// Un pequeño retraso para que parezca que el bot está "pensando"
sleep(1);

jsonSuccess([
    'reply' => $botResponse
], 'Respuesta del bot');
