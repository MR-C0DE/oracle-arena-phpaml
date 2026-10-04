<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$socket = stream_socket_server('tcp://127.0.0.1:0', $errorCode, $errorMessage);
if ($socket === false) {
    throw new RuntimeException("Impossible de réserver un port de test : {$errorMessage}");
}
$address = (string) stream_socket_get_name($socket, false);
fclose($socket);
$port = (int) substr($address, strrpos($address, ':') + 1);

$pipes = [];
$process = proc_open(
    [PHP_BINARY, '-S', "127.0.0.1:{$port}", '-t', $root . '/public', $root . '/public/index.php'],
    [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
    $pipes,
    $root,
);
if (!is_resource($process)) {
    throw new RuntimeException('Impossible de démarrer le serveur HTTP de test.');
}

/** @return int HTTP status */
function rawStatus(int $port, string $path): int
{
    $connection = @fsockopen('127.0.0.1', $port, $errorCode, $errorMessage, 2.0);
    if ($connection === false) { return 0; }
    fwrite($connection, "GET {$path} HTTP/1.1\r\nHost: 127.0.0.1\r\nConnection: close\r\n\r\n");
    $statusLine = (string) fgets($connection);
    fclose($connection);
    return preg_match('#^HTTP/\d(?:\.\d)?\s+(\d{3})#', $statusLine, $matches) ? (int) $matches[1] : 0;
}

try {
    $ready = false;
    for ($attempt = 0; $attempt < 40; $attempt++) {
        if (rawStatus($port, '/') !== 0) { $ready = true; break; }
        usleep(50_000);
    }
    if (!$ready) { throw new RuntimeException('Le serveur HTTP de test ne répond pas.'); }
    if (rawStatus($port, '/') !== 200) {
        throw new RuntimeException("La page d'accueil du projet neuf ne retourne pas 200.");
    }

    $cases = [
        '/../.env' => 404,
        '/%2e%2e/.env' => 404,
        '/%252e%252e/.env' => 404,
        '/..%2f.env' => 404,
        '/%2e%2e%5c.env' => 404,
    ];
    foreach ($cases as $path => $expected) {
        $actual = rawStatus($port, $path);
        if ($actual !== $expected) {
            throw new RuntimeException("{$path} retourne {$actual} au lieu de {$expected}.");
        }
    }
    fwrite(STDOUT, "✓ Les traversées de chemin HTTP sont refusées.\n");
} finally {
    proc_terminate($process);
    foreach ($pipes as $pipe) { if (is_resource($pipe)) { fclose($pipe); } }
    proc_close($process);
}
