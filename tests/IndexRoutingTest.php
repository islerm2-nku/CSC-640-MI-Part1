<?php

namespace Tests;

use PHPUnit\Framework\TestCase;

class IndexRoutingTest extends TestCase
{
    private $dispatcher;

    protected function setUp(): void
    {
        // Create the same dispatcher as in index.php
        $this->dispatcher = \FastRoute\simpleDispatcher(function(\FastRoute\RouteCollector $r) {
            $r->post('/api/telemetry/upload', ['TelemetryController', 'uploadTelemetry']);
            $r->get('/api/sessions', ['SessionController', 'getAllSessions']);
            $r->get('/api/sessions/{id}', ['SessionController', 'getSession']);
            $r->delete('/api/sessions/{id}', ['SessionController', 'deleteSession']);
            $r->get('/api/sessions/{id}/laps', ['SessionController', 'getSessionLapCount']);
            $r->get('/api/sessions/{id}/laps/{lapNumber}', ['SessionController', 'getLapAttributeData']);
            $r->get('/api/sessions/{id}/laps/{lapNumber}/averages', ['SessionController', 'getLapAttributeAverages']);
            $r->delete('/api/sessions/{id}/laps/{lapNumber}', ['SessionController', 'deleteLapAttributeData']);
        });
    }

    public function testUploadTelemetryRoute()
    {
        $routeInfo = $this->dispatcher->dispatch('POST', '/api/telemetry/upload');
        
        $this->assertEquals(\FastRoute\Dispatcher::FOUND, $routeInfo[0]);
        $this->assertEquals(['TelemetryController', 'uploadTelemetry'], $routeInfo[1]);
        $this->assertEmpty($routeInfo[2]); // No route parameters
    }

    public function testGetAllSessionsRoute()
    {
        $routeInfo = $this->dispatcher->dispatch('GET', '/api/sessions');
        
        $this->assertEquals(\FastRoute\Dispatcher::FOUND, $routeInfo[0]);
        $this->assertEquals(['SessionController', 'getAllSessions'], $routeInfo[1]);
        $this->assertEmpty($routeInfo[2]);
    }

    public function testGetSingleSessionRoute()
    {
        $routeInfo = $this->dispatcher->dispatch('GET', '/api/sessions/test-session-id-123');
        
        $this->assertEquals(\FastRoute\Dispatcher::FOUND, $routeInfo[0]);
        $this->assertEquals(['SessionController', 'getSession'], $routeInfo[1]);
        $this->assertArrayHasKey('id', $routeInfo[2]);
        $this->assertEquals('test-session-id-123', $routeInfo[2]['id']);
    }

    public function testDeleteSessionRoute()
    {
        $routeInfo = $this->dispatcher->dispatch('DELETE', '/api/sessions/test-session-id-456');
        
        $this->assertEquals(\FastRoute\Dispatcher::FOUND, $routeInfo[0]);
        $this->assertEquals(['SessionController', 'deleteSession'], $routeInfo[1]);
        $this->assertArrayHasKey('id', $routeInfo[2]);
        $this->assertEquals('test-session-id-456', $routeInfo[2]['id']);
    }

    public function testGetSessionLapsRoute()
    {
        $routeInfo = $this->dispatcher->dispatch('GET', '/api/sessions/abc-123/laps');
        
        $this->assertEquals(\FastRoute\Dispatcher::FOUND, $routeInfo[0]);
        $this->assertEquals(['SessionController', 'getSessionLapCount'], $routeInfo[1]);
        $this->assertArrayHasKey('id', $routeInfo[2]);
        $this->assertEquals('abc-123', $routeInfo[2]['id']);
    }

    public function testGetLapAttributeDataRoute()
    {
        $routeInfo = $this->dispatcher->dispatch('GET', '/api/sessions/abc-123/laps/5');
        
        $this->assertEquals(\FastRoute\Dispatcher::FOUND, $routeInfo[0]);
        $this->assertEquals(['SessionController', 'getLapAttributeData'], $routeInfo[1]);
        $this->assertArrayHasKey('id', $routeInfo[2]);
        $this->assertArrayHasKey('lapNumber', $routeInfo[2]);
        $this->assertEquals('abc-123', $routeInfo[2]['id']);
        $this->assertEquals('5', $routeInfo[2]['lapNumber']);
    }

    public function testGetLapAveragesRoute()
    {
        $routeInfo = $this->dispatcher->dispatch('GET', '/api/sessions/xyz-789/laps/3/averages');
        
        $this->assertEquals(\FastRoute\Dispatcher::FOUND, $routeInfo[0]);
        $this->assertEquals(['SessionController', 'getLapAttributeAverages'], $routeInfo[1]);
        $this->assertArrayHasKey('id', $routeInfo[2]);
        $this->assertArrayHasKey('lapNumber', $routeInfo[2]);
        $this->assertEquals('xyz-789', $routeInfo[2]['id']);
        $this->assertEquals('3', $routeInfo[2]['lapNumber']);
    }

    public function testDeleteLapDataRoute()
    {
        $routeInfo = $this->dispatcher->dispatch('DELETE', '/api/sessions/def-456/laps/7');
        
        $this->assertEquals(\FastRoute\Dispatcher::FOUND, $routeInfo[0]);
        $this->assertEquals(['SessionController', 'deleteLapAttributeData'], $routeInfo[1]);
        $this->assertArrayHasKey('id', $routeInfo[2]);
        $this->assertArrayHasKey('lapNumber', $routeInfo[2]);
        $this->assertEquals('def-456', $routeInfo[2]['id']);
        $this->assertEquals('7', $routeInfo[2]['lapNumber']);
    }

    public function testNotFoundRoute()
    {
        $routeInfo = $this->dispatcher->dispatch('GET', '/api/nonexistent');
        
        $this->assertEquals(\FastRoute\Dispatcher::NOT_FOUND, $routeInfo[0]);
    }

    public function testMethodNotAllowedForSessions()
    {
        // POST not allowed on /api/sessions (only GET)
        $routeInfo = $this->dispatcher->dispatch('POST', '/api/sessions');
        
        $this->assertEquals(\FastRoute\Dispatcher::METHOD_NOT_ALLOWED, $routeInfo[0]);
    }

    public function testMethodNotAllowedForSessionDetail()
    {
        // PUT not allowed on /api/sessions/{id}
        $routeInfo = $this->dispatcher->dispatch('PUT', '/api/sessions/test-id');
        
        $this->assertEquals(\FastRoute\Dispatcher::METHOD_NOT_ALLOWED, $routeInfo[0]);
    }

    public function testSessionIdWithUUID()
    {
        $uuid = 'b3e96fd3-10bf-40e0-9cf3-941753d461f5';
        $routeInfo = $this->dispatcher->dispatch('GET', "/api/sessions/{$uuid}");
        
        $this->assertEquals(\FastRoute\Dispatcher::FOUND, $routeInfo[0]);
        $this->assertEquals($uuid, $routeInfo[2]['id']);
    }

    public function testSessionIdWithSpecialCharacters()
    {
        $sessionId = 'session_123-456';
        $routeInfo = $this->dispatcher->dispatch('GET', "/api/sessions/{$sessionId}");
        
        $this->assertEquals(\FastRoute\Dispatcher::FOUND, $routeInfo[0]);
        $this->assertEquals($sessionId, $routeInfo[2]['id']);
    }

    public function testLapNumberVariations()
    {
        // Single digit
        $routeInfo = $this->dispatcher->dispatch('GET', '/api/sessions/test/laps/1');
        $this->assertEquals('1', $routeInfo[2]['lapNumber']);

        // Multiple digits
        $routeInfo = $this->dispatcher->dispatch('GET', '/api/sessions/test/laps/99');
        $this->assertEquals('99', $routeInfo[2]['lapNumber']);
    }

    public function testRouteParameterExtraction()
    {
        $routeInfo = $this->dispatcher->dispatch('GET', '/api/sessions/my-session/laps/42/averages');
        
        $this->assertCount(2, $routeInfo[2]);
        $this->assertEquals('my-session', $routeInfo[2]['id']);
        $this->assertEquals('42', $routeInfo[2]['lapNumber']);
    }
}
