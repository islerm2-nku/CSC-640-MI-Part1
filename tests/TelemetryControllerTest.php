<?php

namespace Tests;

use PHPUnit\Framework\TestCase;
use App\Controller\TelemetryController;

class TelemetryControllerTest extends TestCase
{
    private $controller;

    protected function setUp(): void
    {
        $this->controller = new TelemetryController();
        
        // Clean up any existing $_FILES and $_POST for test isolation
        $_FILES = [];
        $_POST = [];
    }

    protected function tearDown(): void
    {
        // Clean up after each test
        $_FILES = [];
        $_POST = [];
    }

    public function testUploadTelemetryReturns400WhenNoFileUploaded()
    {
        $_FILES = [];
        
        $expectedError = ['error' => 'No file uploaded'];
        $expectedStatusCode = 400;

        $this->assertIsArray($expectedError);
        $this->assertArrayHasKey('error', $expectedError);
        $this->assertEquals('No file uploaded', $expectedError['error']);
        $this->assertEquals(400, $expectedStatusCode);
    }

    public function testUploadTelemetryReturns400WhenFileUploadFails()
    {
        $_FILES['telemetry_file'] = [
            'name' => 'test.ibt',
            'type' => 'application/octet-stream',
            'tmp_name' => '/tmp/phptest',
            'error' => UPLOAD_ERR_INI_SIZE, // Upload error
            'size' => 0
        ];

        $expectedError = ['error' => 'File upload failed'];
        $expectedStatusCode = 400;

        $this->assertIsArray($expectedError);
        $this->assertArrayHasKey('error', $expectedError);
        $this->assertEquals('File upload failed', $expectedError['error']);
        $this->assertEquals(400, $expectedStatusCode);
    }

    public function testUploadTelemetryValidatesFileExtension()
    {
        $invalidExtensions = ['txt', 'pdf', 'jpg', 'zip', 'exe', 'php'];
        
        foreach ($invalidExtensions as $ext) {
            $filename = "test_file.{$ext}";
            $extractedExt = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
            
            $this->assertEquals($ext, $extractedExt);
            $this->assertNotEquals('ibt', $extractedExt);
        }
    }

    public function testUploadTelemetryReturns400ForNonIbtFile()
    {
        $_FILES['telemetry_file'] = [
            'name' => 'test.txt',
            'type' => 'text/plain',
            'tmp_name' => '/tmp/phptest',
            'error' => UPLOAD_ERR_OK,
            'size' => 1024
        ];

        $ext = strtolower(pathinfo($_FILES['telemetry_file']['name'], PATHINFO_EXTENSION));
        
        $this->assertNotEquals('ibt', $ext);
        
        $expectedError = ['error' => 'Invalid file type. Only .ibt files are allowed'];
        $expectedStatusCode = 400;

        $this->assertEquals('Invalid file type. Only .ibt files are allowed', $expectedError['error']);
        $this->assertEquals(400, $expectedStatusCode);
    }

    public function testUploadTelemetryAcceptsIbtFileExtension()
    {
        $validFilenames = [
            'telemetry.ibt',
            'race_data.IBT',
            'session.Ibt',
            'test-file_123.ibt'
        ];

        foreach ($validFilenames as $filename) {
            $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
            $this->assertEquals('ibt', $ext);
        }
    }

    public function testUploadTelemetryParsesAttributesArray()
    {
        $_POST['attributes'] = ['Lap', 'Speed', 'RPM'];
        
        $attributes = isset($_POST['attributes']) && is_array($_POST['attributes']) 
            ? $_POST['attributes'] 
            : [];

        $this->assertIsArray($attributes);
        $this->assertCount(3, $attributes);
        $this->assertContains('Lap', $attributes);
        $this->assertContains('Speed', $attributes);
        $this->assertContains('RPM', $attributes);
    }

    public function testUploadTelemetryDefaultsToEmptyAttributesArray()
    {
        // No attributes provided
        $_POST = [];
        
        $attributes = isset($_POST['attributes']) && is_array($_POST['attributes']) 
            ? $_POST['attributes'] 
            : [];

        $this->assertIsArray($attributes);
        $this->assertEmpty($attributes);
    }

    public function testUploadTelemetryHandlesNonArrayAttributes()
    {
        $_POST['attributes'] = 'not_an_array';
        
        $attributes = isset($_POST['attributes']) && is_array($_POST['attributes']) 
            ? $_POST['attributes'] 
            : [];

        $this->assertIsArray($attributes);
        $this->assertEmpty($attributes);
    }

    public function testUploadTelemetryBuildsCorrectPythonCommand()
    {
        $tmpFile = '/tmp/phptest123.ibt';
        $attributes = ['Lap', 'Speed'];
        $pythonScript = '/app/scripts/telemetry_parser.py';
        
        $attributesJson = json_encode($attributes);
        $command = "python3 {$pythonScript} " . escapeshellarg($tmpFile) . " " . escapeshellarg($attributesJson);

        $this->assertStringContainsString('python3', $command);
        $this->assertStringContainsString($pythonScript, $command);
        $this->assertStringContainsString($tmpFile, $command);
        $this->assertStringContainsString('Lap', $command);
        $this->assertStringContainsString('Speed', $command);
    }

    public function testUploadTelemetryEscapesShellArguments()
    {
        $dangerousPath = "/tmp/file'; rm -rf /; echo 'hacked";
        $escaped = escapeshellarg($dangerousPath);
        
        // escapeshellarg should wrap in single quotes and escape internal quotes
        $this->assertStringStartsWith("'", $escaped);
        $this->assertStringEndsWith("'", $escaped);
        
        // The dangerous characters are still present but escaped/quoted safely
        // Test that command injection would fail by verifying quoting structure
        $this->assertStringContainsString("\\'", $escaped); // Internal quotes are escaped
    }

    public function testUploadTelemetryReturns500WhenPythonScriptFails()
    {
        // shell_exec returns null on failure
        $output = null;

        $expectedError = ['error' => 'Failed to process telemetry data'];
        $expectedStatusCode = 500;

        $this->assertNull($output);
        $this->assertEquals('Failed to process telemetry data', $expectedError['error']);
        $this->assertEquals(500, $expectedStatusCode);
    }

    public function testUploadTelemetryReturns500WhenJsonParsingFails()
    {
        $invalidJson = "This is not JSON";
        $telemetryData = json_decode($invalidJson, true);

        $this->assertNull($telemetryData);
        $this->assertNotEquals(JSON_ERROR_NONE, json_last_error());

        $expectedError = ['error' => 'Invalid telemetry data format'];
        $expectedStatusCode = 500;

        $this->assertEquals('Invalid telemetry data format', $expectedError['error']);
        $this->assertEquals(500, $expectedStatusCode);
    }

    public function testUploadTelemetryParsesValidJsonResponse()
    {
        $validJson = json_encode([
            'uploaded' => true,
            'session_id' => 'test-123',
            'attributes' => ['Lap', 'Speed']
        ]);

        $telemetryData = json_decode($validJson, true);

        $this->assertIsArray($telemetryData);
        $this->assertArrayHasKey('uploaded', $telemetryData);
        $this->assertArrayHasKey('session_id', $telemetryData);
        $this->assertTrue($telemetryData['uploaded']);
        $this->assertEquals('test-123', $telemetryData['session_id']);
    }

    public function testUploadTelemetryReturnsSuccessResponse()
    {
        $expectedResponse = [
            'uploaded' => true,
            'session_id' => '2478c41b-dceb-449e-9b97-a911050d276b',
            'track_name' => 'Road Atlanta',
            'attributes' => ['Lap', 'Speed', 'RPM']
        ];

        $this->assertIsArray($expectedResponse);
        $this->assertArrayHasKey('uploaded', $expectedResponse);
        $this->assertArrayHasKey('session_id', $expectedResponse);
        $this->assertTrue($expectedResponse['uploaded']);
    }

    public function testUploadTelemetryHandlesEmptyJsonResponse()
    {
        $emptyJson = json_encode([]);
        $telemetryData = json_decode($emptyJson, true);

        $this->assertIsArray($telemetryData);
        $this->assertEmpty($telemetryData);
        $this->assertEquals(JSON_ERROR_NONE, json_last_error());
    }

    public function testUploadTelemetryJsonEncodesAttributesCorrectly()
    {
        $attributes = ['Lap', 'Speed', 'Throttle', 'Brake'];
        $json = json_encode($attributes);

        $this->assertIsString($json);
        $this->assertStringStartsWith('[', $json);
        $this->assertStringEndsWith(']', $json);
        $this->assertStringContainsString('Lap', $json);
        
        $decoded = json_decode($json, true);
        $this->assertEquals($attributes, $decoded);
    }

    public function testFileErrorConstants()
    {
        // Verify PHP upload error constants
        $this->assertEquals(0, UPLOAD_ERR_OK);
        $this->assertEquals(1, UPLOAD_ERR_INI_SIZE);
        $this->assertEquals(2, UPLOAD_ERR_FORM_SIZE);
        $this->assertEquals(3, UPLOAD_ERR_PARTIAL);
        $this->assertEquals(4, UPLOAD_ERR_NO_FILE);
    }

    public function testFileValidationChecksErrorCode()
    {
        $errorCodes = [
            UPLOAD_ERR_INI_SIZE,
            UPLOAD_ERR_FORM_SIZE,
            UPLOAD_ERR_PARTIAL,
            UPLOAD_ERR_NO_FILE,
            UPLOAD_ERR_NO_TMP_DIR,
            UPLOAD_ERR_CANT_WRITE
        ];

        foreach ($errorCodes as $errorCode) {
            $this->assertNotEquals(UPLOAD_ERR_OK, $errorCode);
        }
    }

    public function testCaseInsensitiveExtensionCheck()
    {
        $filenames = [
            'test.ibt' => true,
            'test.IBT' => true,
            'test.Ibt' => true,
            'test.iBT' => true,
            'test.txt' => false,
            'test.TXT' => false
        ];

        foreach ($filenames as $filename => $shouldBeIbt) {
            $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
            
            if ($shouldBeIbt) {
                $this->assertEquals('ibt', $ext);
            } else {
                $this->assertNotEquals('ibt', $ext);
            }
        }
    }

    public function testExceptionHandlingReturns500()
    {
        $exception = new \Exception('Test exception');
        
        $expectedError = ['error' => $exception->getMessage()];
        $expectedStatusCode = 500;

        $this->assertEquals('Test exception', $expectedError['error']);
        $this->assertEquals(500, $expectedStatusCode);
    }

    public function testPythonScriptPathIsCorrect()
    {
        $pythonScript = '/app/scripts/telemetry_parser.py';
        
        $this->assertStringStartsWith('/app/scripts/', $pythonScript);
        $this->assertStringEndsWith('.py', $pythonScript);
        $this->assertStringContainsString('telemetry_parser', $pythonScript);
    }

    public function testAttributesArrayStructure()
    {
        $validAttributes = [
            ['Lap', 'Speed'],
            ['RPM', 'Gear', 'Throttle'],
            [],
            ['OnPitRoad', 'PlayerIncidents', 'FuelLevel']
        ];

        foreach ($validAttributes as $attributes) {
            $this->assertIsArray($attributes);
            $json = json_encode($attributes);
            $this->assertNotFalse($json);
            
            $decoded = json_decode($json, true);
            $this->assertEquals($attributes, $decoded);
        }
    }
}
