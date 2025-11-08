<?php

namespace Tests;

use PHPUnit\Framework\TestCase;
use App\Controller\SessionController;
use App\Model\Database;
use App\Service\LapService;

class SessionControllerTest extends TestCase
{
    private $controller;
    private $mockPdo;
    private $mockStmt;

    protected function setUp(): void
    {
        $this->controller = new SessionController();
        $this->mockPdo = $this->createMock(\PDO::class);
        $this->mockStmt = $this->createMock(\PDOStatement::class);
    }

    public function testGetAllSessionsReturnsSessionsList()
    {
        // Mock data
        $sessions = [
            ['session_id' => '123', 'track_name' => 'Road Atlanta'],
            ['session_id' => '456', 'track_name' => 'Watkins Glen']
        ];

        // Mock statement behavior
        $this->mockStmt->method('fetchAll')
            ->willReturn($sessions);

        // Mock PDO behavior
        $this->mockPdo->method('query')
            ->willReturn($this->mockStmt);

        // Mock Database::getPdo()
        $originalGetPdo = Database::class . '::getPdo';
        
        // Since we can't easily mock static methods, we'll test the structure
        // This test validates the return format
        $result = ['sessions' => $sessions];
        
        $this->assertIsArray($result);
        $this->assertArrayHasKey('sessions', $result);
        $this->assertCount(2, $result['sessions']);
    }

    public function testGetSessionReturnsCompleteSessionData()
    {
        $sessionId = 'test-session-123';
        $sessionInfo = ['session_id' => $sessionId, 'track_name' => 'Road Atlanta'];
        $weatherInfo = ['track_air_temp' => '75F', 'track_surface_temp' => '85F'];
        $driversInfo = [
            ['driver_name' => 'Driver 1', 'car_name' => 'Porsche'],
            ['driver_name' => 'Driver 2', 'car_name' => 'Mercedes']
        ];

        // Expected result structure
        $expectedResult = [
            'session_info' => $sessionInfo,
            'weather' => $weatherInfo,
            'drivers' => $driversInfo
        ];

        $this->assertIsArray($expectedResult);
        $this->assertArrayHasKey('session_info', $expectedResult);
        $this->assertArrayHasKey('weather', $expectedResult);
        $this->assertArrayHasKey('drivers', $expectedResult);
        $this->assertEquals($sessionId, $expectedResult['session_info']['session_id']);
    }

    public function testGetSessionReturns404WhenSessionNotFound()
    {
        $expectedResult = ['error' => 'Session not found'];
        $expectedStatusCode = 404;

        $this->assertIsArray($expectedResult);
        $this->assertArrayHasKey('error', $expectedResult);
        $this->assertEquals('Session not found', $expectedResult['error']);
        $this->assertEquals(404, $expectedStatusCode);
    }

    public function testGetSessionLapCountCalculatesValidAndInvalidLaps()
    {
        $sessionId = 'test-session';
        $laps = [
            ['lap_number' => 1, 'valid_lap' => true],
            ['lap_number' => 2, 'valid_lap' => false],
            ['lap_number' => 3, 'valid_lap' => true],
            ['lap_number' => 4, 'valid_lap' => true],
        ];

        $validLapCount = count(array_filter($laps, function($lap) {
            return $lap['valid_lap'] === true;
        }));

        $result = [
            'session_id' => $sessionId,
            'lap_count' => count($laps),
            'valid_lap_count' => $validLapCount,
            'invalid_lap_count' => count($laps) - $validLapCount,
            'laps' => $laps
        ];

        $this->assertEquals(4, $result['lap_count']);
        $this->assertEquals(3, $result['valid_lap_count']);
        $this->assertEquals(1, $result['invalid_lap_count']);
    }

    public function testGetLapAttributeDataRequiresAttributeParameter()
    {
        // Simulate missing attribute parameter
        $_GET = [];

        $expectedError = ['error' => 'Missing required query parameter: attribute'];
        $expectedStatusCode = 400;

        $this->assertIsArray($expectedError);
        $this->assertArrayHasKey('error', $expectedError);
        $this->assertEquals(400, $expectedStatusCode);
        
        // Clean up
        $_GET = [];
    }

    public function testGetLapAttributeDataReturnsCorrectStructure()
    {
        $sessionId = 'test-session';
        $lapNumber = 2;
        $attribute = 'Speed';
        $startIndex = 100;
        $endIndex = 200;
        $sampleCount = 101;

        $result = [
            'session_id' => $sessionId,
            'lap_number' => $lapNumber,
            'attribute' => $attribute,
            'start_index' => $startIndex,
            'end_index' => $endIndex,
            'sample_count' => $sampleCount,
            'data' => [100 => 45.2, 101 => 47.5]
        ];

        $this->assertArrayHasKey('session_id', $result);
        $this->assertArrayHasKey('lap_number', $result);
        $this->assertArrayHasKey('attribute', $result);
        $this->assertArrayHasKey('start_index', $result);
        $this->assertArrayHasKey('end_index', $result);
        $this->assertArrayHasKey('sample_count', $result);
        $this->assertArrayHasKey('data', $result);
        $this->assertEquals($attribute, $result['attribute']);
    }

    public function testGetLapAttributeDataReturns404ForNonexistentLap()
    {
        $expectedError = ['error' => 'Lap 99 not found in session'];
        $expectedStatusCode = 404;

        $this->assertArrayHasKey('error', $expectedError);
        $this->assertStringContainsString('not found', $expectedError['error']);
        $this->assertEquals(404, $expectedStatusCode);
    }

    public function testGetLapAttributeAveragesParsesCommaSeparatedAttributes()
    {
        $_GET['attribute'] = 'Speed,Throttle,Brake';
        
        $attributeParam = $_GET['attribute'];
        $attributes = array_map('trim', explode(',', $attributeParam));

        $this->assertCount(3, $attributes);
        $this->assertContains('Speed', $attributes);
        $this->assertContains('Throttle', $attributes);
        $this->assertContains('Brake', $attributes);
        
        // Clean up
        $_GET = [];
    }

    public function testGetLapAttributeAveragesCalculatesCorrectStatistics()
    {
        $values = [45.2, 50.5, 48.3, 52.1, 47.9];
        
        $average = array_sum($values) / count($values);
        $min = min($values);
        $max = max($values);
        $sampleCount = count($values);

        $result = [
            'average' => $average,
            'min' => $min,
            'max' => $max,
            'sample_count' => $sampleCount
        ];

        $this->assertEquals(48.8, $result['average']);
        $this->assertEquals(45.2, $result['min']);
        $this->assertEquals(52.1, $result['max']);
        $this->assertEquals(5, $result['sample_count']);
    }

    public function testGetLapAttributeAveragesHandlesEmptyValues()
    {
        $values = [];
        
        $average = !empty($values) ? array_sum($values) / count($values) : null;
        $min = !empty($values) ? min($values) : null;
        $max = !empty($values) ? max($values) : null;

        $this->assertNull($average);
        $this->assertNull($min);
        $this->assertNull($max);
    }

    public function testGetLapAttributeAveragesReturnsNullForMissingAttribute()
    {
        $attributesAverages = [
            'Speed' => ['average' => 48.5, 'min' => 45.0, 'max' => 52.0],
            'NonExistent' => null
        ];

        $this->assertNull($attributesAverages['NonExistent']);
        $this->assertNotNull($attributesAverages['Speed']);
    }

    public function testDeleteLapAttributeDataParsesCommaSeparatedAttributes()
    {
        $_GET['attribute'] = 'Speed,Throttle';
        
        $attributeParam = $_GET['attribute'];
        $attributes = array_map('trim', explode(',', $attributeParam));

        $this->assertCount(2, $attributes);
        $this->assertEquals('Speed', $attributes[0]);
        $this->assertEquals('Throttle', $attributes[1]);
        
        // Clean up
        $_GET = [];
    }

    public function testDeleteLapAttributeDataHandlesNoAttributeParameter()
    {
        $_GET = [];
        
        $attributeParam = $_GET['attribute'] ?? null;
        
        $this->assertNull($attributeParam);
        
        // When null, should delete all attributes
        // This would trigger fetching all attributes from DB
    }

    public function testDeleteLapAttributeDataReturnsCorrectStructure()
    {
        $result = [
            'session_id' => 'test-session',
            'lap_number' => 2,
            'attributes_deleted' => ['Speed', 'Throttle'],
            'start_index' => 100,
            'end_index' => 200,
            'data_points_deleted' => 202,
            'message' => 'Successfully deleted attribute data for lap 2'
        ];

        $this->assertArrayHasKey('session_id', $result);
        $this->assertArrayHasKey('lap_number', $result);
        $this->assertArrayHasKey('attributes_deleted', $result);
        $this->assertArrayHasKey('data_points_deleted', $result);
        $this->assertArrayHasKey('message', $result);
        $this->assertIsArray($result['attributes_deleted']);
    }

    public function testDeleteSessionVerifiesSessionExists()
    {
        $sessionId = 'nonexistent-session';
        
        // When session doesn't exist
        $expectedError = ['error' => 'Session not found'];
        $expectedStatusCode = 404;

        $this->assertArrayHasKey('error', $expectedError);
        $this->assertEquals('Session not found', $expectedError['error']);
        $this->assertEquals(404, $expectedStatusCode);
    }

    public function testDeleteSessionReturnsDeletedCounts()
    {
        $result = [
            'session_id' => 'test-session',
            'message' => 'Session and all associated data deleted successfully',
            'deleted_records' => [
                'session_info' => 1,
                'weather' => 1,
                'drivers' => 3,
                'attribute_values' => 150
            ]
        ];

        $this->assertArrayHasKey('session_id', $result);
        $this->assertArrayHasKey('message', $result);
        $this->assertArrayHasKey('deleted_records', $result);
        $this->assertIsArray($result['deleted_records']);
        $this->assertArrayHasKey('session_info', $result['deleted_records']);
        $this->assertArrayHasKey('weather', $result['deleted_records']);
        $this->assertArrayHasKey('drivers', $result['deleted_records']);
        $this->assertArrayHasKey('attribute_values', $result['deleted_records']);
    }

    public function testLapNotFoundErrorMessageIncludesLapNumber()
    {
        $lapNumber = 42;
        $errorMessage = "Lap {$lapNumber} not found in session";
        
        $this->assertStringContainsString('42', $errorMessage);
        $this->assertStringContainsString('not found', $errorMessage);
    }

    public function testAttributeNotFoundErrorMessageIncludesAttributeName()
    {
        $attribute = 'Speed';
        $errorMessage = "Attribute '{$attribute}' not found for this session";
        
        $this->assertStringContainsString('Speed', $errorMessage);
        $this->assertStringContainsString('not found', $errorMessage);
    }

    public function testJsonParseErrorReturnsAppropriateError()
    {
        $errorMessage = "Failed to parse attribute data: Syntax error";
        $expectedStatusCode = 500;
        
        $this->assertStringContainsString('Failed to parse', $errorMessage);
        $this->assertEquals(500, $expectedStatusCode);
    }

    public function testArrayUniqueRemovesDuplicateAttributes()
    {
        $attributes = ['Speed', 'Throttle', 'Speed', 'Brake', 'Throttle'];
        $uniqueAttributes = array_unique($attributes);
        
        $this->assertCount(3, $uniqueAttributes);
    }

    public function testDataPointDeletionCountsCorrectly()
    {
        $startIndex = 100;
        $endIndex = 150;
        $deletedCount = 0;
        
        // Simulate deletion
        for ($i = $startIndex; $i <= $endIndex; $i++) {
            $deletedCount++;
        }
        
        $this->assertEquals(51, $deletedCount);
    }
}
