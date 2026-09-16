<?php
// api/core/db.php
// Reutiliza la conexión PDO de includes/config.php

function getDB(): PDO {
    static $conn = null;
    if ($conn !== null) return $conn;

    // Buscar config.php subiendo desde /api/core/ hasta la raíz
    $configPath = __DIR__ . '/../../includes/config.php';
    if (!file_exists($configPath)) {
        http_response_code(500);
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'config.php no encontrado en ' . $configPath]);
        exit;
    }
    require_once $configPath;

    if (!isset($conn) || !($conn instanceof PDO)) {
        http_response_code(500);
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'La conexión PDO no está disponible']);
        exit;
    }

    return $conn;
}
