<?php
// api/core/router.php

function getUriSegments(): array {
    $uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
    $scriptDir = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\');
    if ($scriptDir !== '' && strpos($uri, $scriptDir) === 0) {
        $uri = substr($uri, strlen($scriptDir));
    }
    $uri = trim($uri, '/');
    return $uri === '' ? [] : explode('/', $uri);
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
