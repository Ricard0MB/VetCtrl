<?php
// api/core/router.php
// Router compatible con Render (sin depender de .htaccess)

/**
 * Devuelve los segmentos de la URL después de /api/
 * Ignora 'index.php' si aparece.
 * Soporta:
 *   /api/pets/123        → ['pets', '123']
 *   /api/index.php?resource=pets&id=123 → ['pets'] (id va por query)
 *   /api/                → []
 */
function getUriSegments(): array {
    $uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

    // Quitar el prefijo del directorio del script (/api)
    $scriptDir = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\');
    if ($scriptDir !== '' && strpos($uri, $scriptDir) === 0) {
        $uri = substr($uri, strlen($scriptDir));
    }

    $uri = trim($uri, '/');
    if ($uri === '') return [];

    $segments = explode('/', $uri);

    // 🔥 Ignorar 'index.php' si aparece como primer segmento
    if (isset($segments[0]) && strtolower($segments[0]) === 'index.php') {
        array_shift($segments);
    }

    // Si después de quitar index.php no queda nada,
    // pero hay ?resource=... en la query, usarlo
    if (empty($segments) && isset($_GET['resource'])) {
        $segments[] = $_GET['resource'];
    }

    return array_values(array_filter($segments, fn($s) => $s !== ''));
}

function getMethod(): string {
    return strtoupper($_SERVER['REQUEST_METHOD']);
}

function getQuery(string $key, $default = null) {
    return $_GET[$key] ?? $default;
}

function getPagination(): array {
    $page  = max(1, (int)($_GET['page'] ?? 1));
    $limit = min(100, max(1, (int)($_GET['limit'] ?? 20)));
    $offset = ($page - 1) * $limit;
    return ['page' => $page, 'limit' => $limit, 'offset' => $offset];
}
