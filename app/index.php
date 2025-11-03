<?php
require __DIR__ . '/../vendor/autoload.php';

use App\Controller\TelemetryController;
use App\Controller\SessionController;
use FastRoute\RouteCollector;

header('Content-Type: application/json; charset=utf-8');

$dispatcher = FastRoute\simpleDispatcher(function(RouteCollector $r) {
    $r->post('/api/telemetry/upload', [new TelemetryController(), 'uploadTelemetry']);
    $r->get('/api/sessions', [new SessionController(), 'getAllSessions']);
    $r->get('/api/sessions/{id}', [new SessionController(), 'getSession']);
    $r->delete('/api/sessions/{id}', [new SessionController(), 'deleteSession']);
    $r->get('/api/sessions/{id}/laps', [new SessionController(), 'getSessionLapCount']);
    $r->get('/api/sessions/{id}/laps/{lapNumber}', [new SessionController(), 'getLapAttributeData']);
    $r->get('/api/sessions/{id}/laps/{lapNumber}/averages', [new SessionController(), 'getLapAttributeAverages']);
    $r->delete('/api/sessions/{id}/laps/{lapNumber}', [new SessionController(), 'deleteLapAttributeData']);
});

try {
    $httpMethod = $_SERVER['REQUEST_METHOD'];
    $uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
    
    $routeInfo = $dispatcher->dispatch($httpMethod, $uri);
    
    switch ($routeInfo[0]) {
        case FastRoute\Dispatcher::NOT_FOUND:
            throw new Exception('Not Found', 404);
        case FastRoute\Dispatcher::METHOD_NOT_ALLOWED:
            throw new Exception('Method Not Allowed', 405);
        case FastRoute\Dispatcher::FOUND:
            $handler = $routeInfo[1];
            $vars = $routeInfo[2];
            $response = call_user_func_array($handler, array_values($vars));
            echo json_encode($response);
            break;
    }
} catch (Exception $e) {
    http_response_code($e->getCode() ?: 500);
    echo json_encode(['error' => $e->getMessage()]);
}