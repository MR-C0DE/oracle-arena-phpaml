<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$requestPath = (string) (parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/');

$decodedPath = $requestPath;
for ($pass = 0; $pass < 3; $pass++) {
    $nextPath = rawurldecode($decodedPath);
    if ($nextPath === $decodedPath) { break; }
    $decodedPath = $nextPath;
}
$decodedPath = str_replace('\\', '/', $decodedPath);
if (str_contains($decodedPath, "\0") || preg_match('#(?:^|/)\.\.(?:/|$)#', $decodedPath)) {
    http_response_code(404);
    header('Content-Type: text/plain; charset=UTF-8');
    exit('Not Found');
}

if (PHP_SAPI === 'cli-server' && $decodedPath !== '/') {
    $publicRoot = realpath(__DIR__);
    $requestedFile = realpath(__DIR__ . '/' . ltrim($decodedPath, '/'));
    if ($publicRoot !== false && $requestedFile !== false
        && str_starts_with($requestedFile, $publicRoot . DIRECTORY_SEPARATOR)
        && is_file($requestedFile)) {
        return false;
    }
}

if (PHP_SAPI === 'cli-server' && $requestPath === '/_aml/live-reload') {
    $fingerprint = [];
    foreach ([$root . '/src', $root . '/database', __DIR__] as $watchedRoot) {
        if (!is_dir($watchedRoot)) { continue; }
        $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($watchedRoot, FilesystemIterator::SKIP_DOTS));
        foreach ($files as $file) {
            if ($file->isFile() && in_array(strtolower($file->getExtension()), ['php', 'css', 'js', 'html', 'json', 'svg'], true)) {
                $fingerprint[] = $file->getPathname() . ':' . $file->getMTime() . ':' . $file->getSize();
            }
        }
    }
    sort($fingerprint);
    header('Content-Type: application/json; charset=UTF-8');
    header('Cache-Control: no-store');
    echo json_encode(['version' => sha1(implode('|', $fingerprint))], JSON_THROW_ON_ERROR);
    return;
}

$moduleAutoloader = $root . '/runtime/autoload.php';
if (is_file($moduleAutoloader)) {
    require_once $moduleAutoloader;
} else {
    $frameworkAutoloader = $root . '/runtime/framework/Autoloader.php';
    if (!is_file($frameworkAutoloader)) {
        http_response_code(500);
        exit('Application indisponible.');
    }
    require_once $frameworkAutoloader;
    \PHPAML\Autoloader::register([
        'PHPAML\\' => $root . '/runtime/framework',
        'App\\Controllers\\' => $root . '/src/controllers',
        'App\\Models\\' => $root . '/src/models',
        'App\\Routes\\' => $root . '/src/routes',
        'App\\' => $root . '/src',
    ]);
}

/**
 * @param array<string, mixed> $config
 * @return array<string, mixed>
 */
function phpamlComposeApplication(array $config, string $root): array
{
    $config['legacy_data_bootstrap'] = false;
    $config['bootstrappers'][] = static function (\PHPAML\Container $container, array $applicationConfig) use ($root): void {
        if (!class_exists(\AML\Data\Connections\ConnectionManager::class)) {
            return;
        }

        $dataConfig = $applicationConfig['data'] ?? null;
        if (!is_array($dataConfig)) {
            return;
        }

        $manager = new \AML\Data\Connections\ConnectionManager($root, $dataConfig);
        $container->set('AML\\Data\\Connections\\ConnectionManager', $manager);
        $container->set('AML\\Data\\Connection', $manager->sql());
    };

    return $config;
}

// AML View integration
$viewApp = new \AML\View\FileApplication($root . '/src/views');
$config = \PHPAML\Config\ApplicationConfig::load($root);
if (function_exists('phpamlComposeApplication')) {
    $config = phpamlComposeApplication($config, $root);
}
$application = new \PHPAML\WebApplication($config);
if (!preg_match('#^/api(?:/|$)#', $requestPath)) {
    $request = \PHPAML\Http\Request::capture();
    $response = $application->handle($request, static function (\PHPAML\Http\Request $viewRequest) use ($application, $viewApp, $requestPath): \PHPAML\Http\Response {
        if ($requestPath === '/_aml/' . \AML\Engine\EngineRuntime::assetFilename(true)) {
            $runtime = file_get_contents(\AML\Engine\EngineRuntime::assetPath(true));
            if ($runtime === false) {
                return new \PHPAML\Http\Response('AML Engine asset unavailable.', 500);
            }
            return new \PHPAML\Http\Response($runtime, 200, [
                'Content-Type' => 'text/javascript; charset=utf-8',
                'Cache-Control' => 'public, max-age=31536000, immutable',
            ]);
        }
        if ($requestPath === '/_aml/' . \AML\Engine\EngineRuntime::assetFilename(true) . '.map') {
            $sourceMap = \AML\Engine\EngineRuntime::assetPath(true) . '.map';
            $runtimeMap = file_get_contents($sourceMap);
            if ($runtimeMap === false) {
                return new \PHPAML\Http\Response('AML Engine source map unavailable.', 404);
            }
            return new \PHPAML\Http\Response($runtimeMap, 200, [
                'Content-Type' => 'application/json; charset=utf-8',
                'Cache-Control' => 'public, max-age=31536000, immutable',
            ]);
        }
        if ($requestPath === '/_aml/styles.css') {
            return new \PHPAML\Http\Response($viewApp->styles(), 200, [
                'Content-Type' => 'text/css; charset=utf-8',
                'Cache-Control' => 'no-cache',
            ]);
        }
        $status = 200;
        try {
            $result = $viewApp->mount($requestPath);
        } catch (OutOfBoundsException) {
            $status = 404;
            $result = $viewApp->notFound($requestPath);
        } catch (Throwable $error) {
            $status = 500;
            $result = $viewApp->error($requestPath, $error);
        }
        $session = $application->container()->get(\PHPAML\Session\Session::class);
        $body = $result instanceof \AML\View\PageResult ? $result->rootHtml() : (string) $result;
        $liveReloadMeta = PHP_SAPI === 'cli-server' ? '<meta name="aml-live-reload" content="/_aml/live-reload">' : '';
        $cspNonce = \PHPAML\Security\CspNonce::from($viewRequest);
        $metadata = $result instanceof \AML\View\PageResult ? $viewApp->metadata($requestPath) : new \AML\View\PageMetadata();
        $head = $metadata->render($cspNonce);
        $htmlAttributes = $metadata->htmlAttributes();
        $engineScript = \AML\Engine\EngineRuntime::externalScript();
        $html = '<!doctype html><html ' . $htmlAttributes . '><head><meta charset="utf-8">'
            . '<meta name="viewport" content="width=device-width, initial-scale=1">'
            . $session->csrfMeta() . $liveReloadMeta . $head
            . '<link rel="stylesheet" href="/_aml/styles.css">'
            . '</head><body>' . $body . $engineScript . '</body></html>';
        return \PHPAML\Http\Response::html($html, $status);
    });
    $response->send();
    return;
}
$config = phpamlComposeApplication(\PHPAML\Config\ApplicationConfig::load($root), $root);
(new \PHPAML\WebApplication($config))->run();
