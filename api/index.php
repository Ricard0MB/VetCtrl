<?php
// api/index.php
// Front controller de la API

// 🔥 CORS PRIMERO — antes de cualquier require
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
header('Access-Control-Max-Age: 86400');

// Responder al preflight OPTIONS inmediatamente
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

// Ahora sí los require
require_once __DIR__ . '/core/response.php';
require_once __DIR__ . '/core/router.php';
require_once __DIR__ . '/core/auth.php';

$segments = getUriSegments();
$method   = getMethod();
$resource = $segments[0] ?? null;

if ($resource === null) {
    jsonSuccess([
        'name'    => 'VetCtrl API',
        'version' => '1.0',
    ], 'API funcionando');
}

$routes = [
    'auth'           => __DIR__ . '/modules/auth.php',
    'pets'           => __DIR__ . '/modules/pets.php',
    'owners'         => __DIR__ . '/modules/owners.php',
    'pet-types'      => __DIR__ . '/modules/pet_types.php',
    'breeds'         => __DIR__ . '/modules/breeds.php',
    'appointments'   => __DIR__ . '/modules/appointments.php',
    'consultations'  => __DIR__ . '/modules/consultations.php',
    'treatments'     => __DIR__ . '/modules/treatments.php',
    'vaccines'       => __DIR__ . '/modules/vaccines.php',
    'vaccine-types'  => __DIR__ . '/modules/vaccine_types.php',
    'users'          => __DIR__ . '/modules/users.php',
    'reports'        => __DIR__ . '/modules/reports.php',
    'admin'          => __DIR__ . '/modules/admin.php',
];

if (!isset($routes[$resource])) {
    jsonError("Recurso '$resource' no encontrado", 404);
}

require $routes[$resource];
