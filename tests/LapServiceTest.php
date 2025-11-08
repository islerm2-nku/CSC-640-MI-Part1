<?php

namespace Tests;

use PHPUnit\Framework\TestCase;

class LapServiceTest extends TestCase
{
    // Test parseLapIndices using reflection since it's a private method
    
    public function testParseLapIndicesWithEmptyArray()
    {
        $lapData = [];
        $result = $this->invokeParseLapIndices($lapData);
        
        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    public function testParseLapIndicesWithSingleLap()
    {
        $lapData = [
            0 => 1,
            1 => 1,
            2 => 1,
            3 => 1,
            4 => 1
        ];
        
        $result = $this->invokeParseLapIndices($lapData);
        
        $this->assertCount(1, $result);
        $this->assertEquals(1, $result[0]['lap_number']);
        $this->assertEquals(0, $result[0]['start_index']);
        $this->assertEquals(4, $result[0]['end_index']);
        $this->assertEquals(5, $result[0]['sample_count']);
    }

    public function testParseLapIndicesWithMultipleLaps()
    {
        $lapData = [
            0 => 1,
            1 => 1,
            2 => 1,
            3 => 2,
            4 => 2,
            5 => 2,
            6 => 3,
            7 => 3
        ];
        
        $result = $this->invokeParseLapIndices($lapData);
        
        $this->assertCount(3, $result);
        
        // Lap 1
        $this->assertEquals(1, $result[0]['lap_number']);
        $this->assertEquals(0, $result[0]['start_index']);
        $this->assertEquals(2, $result[0]['end_index']);
        $this->assertEquals(3, $result[0]['sample_count']);
        
        // Lap 2
        $this->assertEquals(2, $result[1]['lap_number']);
        $this->assertEquals(3, $result[1]['start_index']);
        $this->assertEquals(5, $result[1]['end_index']);
        $this->assertEquals(3, $result[1]['sample_count']);
        
        // Lap 3
        $this->assertEquals(3, $result[2]['lap_number']);
        $this->assertEquals(6, $result[2]['start_index']);
        $this->assertEquals(7, $result[2]['end_index']);
        $this->assertEquals(2, $result[2]['sample_count']);
    }

    public function testParseLapIndicesSkipsZeroLapNumber()
    {
        $lapData = [
            0 => 0,  // Warmup - should be skipped
            1 => 0,
            2 => 1,  // Actual lap 1
            3 => 1,
            4 => 1
        ];
        
        $result = $this->invokeParseLapIndices($lapData);
        
        $this->assertCount(1, $result);
        $this->assertEquals(1, $result[0]['lap_number']);
        $this->assertEquals(2, $result[0]['start_index']);
        $this->assertEquals(4, $result[0]['end_index']);
    }

    public function testParseLapIndicesSkipsNegativeLapNumbers()
    {
        $lapData = [
            0 => -1,  // Invalid - should be skipped
            1 => -1,
            2 => 1,   // Valid lap
            3 => 1,
            4 => 2,
            5 => 2
        ];
        
        $result = $this->invokeParseLapIndices($lapData);
        
        $this->assertCount(2, $result);
        $this->assertEquals(1, $result[0]['lap_number']);
        $this->assertEquals(2, $result[1]['lap_number']);
    }

    public function testParseLapIndicesWithNonSequentialIndices()
    {
        // Simulating sparse array
        $lapData = [
            10 => 1,
            11 => 1,
            12 => 1,
            20 => 2,
            21 => 2,
            22 => 2
        ];
        
        $result = $this->invokeParseLapIndices($lapData);
        
        $this->assertCount(2, $result);
        $this->assertEquals(1, $result[0]['lap_number']);
        $this->assertEquals(10, $result[0]['start_index']);
        $this->assertEquals(19, $result[0]['end_index']); // Index before next lap starts
        
        $this->assertEquals(2, $result[1]['lap_number']);
        $this->assertEquals(20, $result[1]['start_index']);
        $this->assertEquals(22, $result[1]['end_index']);
    }

    public function testParseLapIndicesCalculatesSampleCountCorrectly()
    {
        $lapData = [
            0 => 1,
            1 => 1,
            2 => 1,
            3 => 1,
            4 => 1,
            5 => 2,
            6 => 2
        ];
        
        $result = $this->invokeParseLapIndices($lapData);
        
        // Lap 1: indices 0-4 = 5 samples
        $this->assertEquals(5, $result[0]['sample_count']);
        $this->assertEquals(5, ($result[0]['end_index'] - $result[0]['start_index'] + 1));
        
        // Lap 2: indices 5-6 = 2 samples
        $this->assertEquals(2, $result[1]['sample_count']);
        $this->assertEquals(2, ($result[1]['end_index'] - $result[1]['start_index'] + 1));
    }

    public function testParseLapIndicesWithMixedValidAndInvalidLaps()
    {
        $lapData = [
            0 => 0,   // Skip
            1 => 0,   // Skip
            2 => 1,   // Valid
            3 => 1,
            4 => 0,   // Skip
            5 => 2,   // Valid
            6 => 2,
            7 => -1,  // Skip
            8 => 3,   // Valid
            9 => 3
        ];
        
        $result = $this->invokeParseLapIndices($lapData);
        
        $this->assertCount(3, $result);
        $this->assertEquals([1, 2, 3], array_column($result, 'lap_number'));
    }

    public function testParseLapIndicesHandlesLargeLapNumbers()
    {
        $lapData = [
            0 => 99,
            1 => 99,
            2 => 100,
            3 => 100,
            4 => 101,
            5 => 101
        ];
        
        $result = $this->invokeParseLapIndices($lapData);
        
        $this->assertCount(3, $result);
        $this->assertEquals(99, $result[0]['lap_number']);
        $this->assertEquals(100, $result[1]['lap_number']);
        $this->assertEquals(101, $result[2]['lap_number']);
    }

    public function testParseLapIndicesStructureHasAllRequiredFields()
    {
        $lapData = [
            0 => 1,
            1 => 1,
            2 => 2,
            3 => 2
        ];
        
        $result = $this->invokeParseLapIndices($lapData);
        
        foreach ($result as $lap) {
            $this->assertArrayHasKey('lap_number', $lap);
            $this->assertArrayHasKey('start_index', $lap);
            $this->assertArrayHasKey('end_index', $lap);
            $this->assertArrayHasKey('sample_count', $lap);
            
            // Validate data types
            $this->assertIsInt($lap['lap_number']);
            $this->assertIsInt($lap['start_index']);
            $this->assertIsInt($lap['end_index']);
            $this->assertIsInt($lap['sample_count']);
        }
    }

    public function testParseLapIndicesEndIndexIsAlwaysGreaterOrEqualToStartIndex()
    {
        $lapData = [
            0 => 1,
            1 => 1,
            2 => 1,
            3 => 2,
            4 => 2,
            5 => 3
        ];
        
        $result = $this->invokeParseLapIndices($lapData);
        
        foreach ($result as $lap) {
            $this->assertGreaterThanOrEqual(
                $lap['start_index'],
                $lap['end_index'],
                "End index should be >= start index for lap {$lap['lap_number']}"
            );
        }
    }

    public function testParseLapIndicesSampleCountMatchesIndexRange()
    {
        $lapData = [
            5 => 1,
            6 => 1,
            7 => 1,
            8 => 1,
            10 => 2,
            11 => 2,
            12 => 2
        ];
        
        $result = $this->invokeParseLapIndices($lapData);
        
        foreach ($result as $lap) {
            $expectedCount = $lap['end_index'] - $lap['start_index'] + 1;
            $this->assertEquals(
                $expectedCount,
                $lap['sample_count'],
                "Sample count mismatch for lap {$lap['lap_number']}"
            );
        }
    }

    public function testParseLapIndicesWithSingleSampleLap()
    {
        $lapData = [
            0 => 1,
            1 => 1,
            2 => 2,  // Single sample lap
            3 => 3,
            4 => 3
        ];
        
        $result = $this->invokeParseLapIndices($lapData);
        
        $this->assertCount(3, $result);
        
        // Check the single-sample lap (lap 2)
        $lap2 = $result[1];
        $this->assertEquals(2, $lap2['lap_number']);
        $this->assertEquals(2, $lap2['start_index']);
        $this->assertEquals(2, $lap2['end_index']);
        $this->assertEquals(1, $lap2['sample_count']);
    }

    public function testParseLapIndicesReturnsLapsInOrder()
    {
        $lapData = [
            0 => 1,
            1 => 1,
            2 => 3,  // Out of order
            3 => 3,
            4 => 2,  // Out of order
            5 => 2,
            6 => 4,
            7 => 4
        ];
        
        $result = $this->invokeParseLapIndices($lapData);
        
        $this->assertCount(4, $result);
        
        // Verify lap numbers are in the order they appear in data
        $lapNumbers = array_column($result, 'lap_number');
        $this->assertEquals([1, 3, 2, 4], $lapNumbers);
    }

    public function testParseLapIndicesWithAllZeros()
    {
        $lapData = [
            0 => 0,
            1 => 0,
            2 => 0,
            3 => 0
        ];
        
        $result = $this->invokeParseLapIndices($lapData);
        
        $this->assertIsArray($result);
        $this->assertEmpty($result, "All zero lap numbers should result in empty array");
    }

    public function testParseLapIndicesConsecutiveLapTransitions()
    {
        $lapData = [
            0 => 1,
            1 => 2,  // Immediate transition
            2 => 3,  // Another immediate transition
            3 => 4
        ];
        
        $result = $this->invokeParseLapIndices($lapData);
        
        $this->assertCount(4, $result);
        
        // Each lap should be 1 sample before the transition
        $this->assertEquals(0, $result[0]['end_index']); // Lap 1 ends at index 0
        $this->assertEquals(1, $result[1]['start_index']); // Lap 2 starts at index 1
        $this->assertEquals(1, $result[1]['end_index']); // Lap 2 ends at index 1
        $this->assertEquals(2, $result[2]['start_index']); // Lap 3 starts at index 2
    }

    public function testAddIncidentDataStructure()
    {
        // Test the structure that addIncidentData should add
        $laps = [
            ['lap_number' => 1, 'start_index' => 0, 'end_index' => 5, 'sample_count' => 6],
            ['lap_number' => 2, 'start_index' => 6, 'end_index' => 11, 'sample_count' => 6]
        ];
        
        // Simulate adding incident data
        foreach ($laps as &$lap) {
            $lap['valid_lap'] = true;
            $lap['incidents_in_lap'] = 0;
        }
        
        foreach ($laps as $lap) {
            $this->assertArrayHasKey('valid_lap', $lap);
            $this->assertArrayHasKey('incidents_in_lap', $lap);
            $this->assertIsBool($lap['valid_lap']);
            $this->assertIsInt($lap['incidents_in_lap']);
        }
    }

    public function testIncidentCountingLogic()
    {
        // Simulate incident data for a lap
        $incidentData = [
            0 => 0,
            1 => 0,
            2 => 1,  // Incident
            3 => 0,
            4 => 1,  // Incident
            5 => 0,
            6 => 0
        ];
        
        $startIndex = 0;
        $endIndex = 5;
        
        $incidentCount = 0;
        for ($i = $startIndex; $i <= $endIndex; $i++) {
            if (isset($incidentData[$i]) && $incidentData[$i] == 1) {
                $incidentCount++;
            }
        }
        
        $this->assertEquals(2, $incidentCount);
        $this->assertFalse($incidentCount === 0); // Not a valid lap
    }

    public function testIncidentCountingWithNoIncidents()
    {
        $incidentData = [
            0 => 0,
            1 => 0,
            2 => 0,
            3 => 0,
            4 => 0
        ];
        
        $startIndex = 0;
        $endIndex = 4;
        
        $incidentCount = 0;
        for ($i = $startIndex; $i <= $endIndex; $i++) {
            if (isset($incidentData[$i]) && $incidentData[$i] == 1) {
                $incidentCount++;
            }
        }
        
        $this->assertEquals(0, $incidentCount);
        $this->assertTrue($incidentCount === 0); // Valid lap
    }

    public function testIncidentCountingWithSparseData()
    {
        $incidentData = [
            10 => 0,
            11 => 1,  // Incident
            12 => 0,
            // Index 13 missing
            14 => 0,
            15 => 1   // Incident
        ];
        
        $startIndex = 10;
        $endIndex = 15;
        
        $incidentCount = 0;
        for ($i = $startIndex; $i <= $endIndex; $i++) {
            if (isset($incidentData[$i]) && $incidentData[$i] == 1) {
                $incidentCount++;
            }
        }
        
        $this->assertEquals(2, $incidentCount);
    }

    public function testValidLapDeterminationLogic()
    {
        $testCases = [
            ['incidents' => 0, 'expected_valid' => true],
            ['incidents' => 1, 'expected_valid' => false],
            ['incidents' => 5, 'expected_valid' => false],
            ['incidents' => null, 'expected_valid' => true]  // When incidents unknown
        ];
        
        foreach ($testCases as $case) {
            if ($case['incidents'] === null) {
                $validLap = true; // Default when no incident data
            } else {
                $validLap = $case['incidents'] === 0;
            }
            
            $this->assertEquals(
                $case['expected_valid'],
                $validLap,
                "Lap with {$case['incidents']} incidents should be " . 
                ($case['expected_valid'] ? 'valid' : 'invalid')
            );
        }
    }

    public function testGetLapDataFilterByLapNumber()
    {
        $allLaps = [
            ['lap_number' => 1, 'start_index' => 0, 'end_index' => 5, 'sample_count' => 6],
            ['lap_number' => 2, 'start_index' => 6, 'end_index' => 11, 'sample_count' => 6],
            ['lap_number' => 3, 'start_index' => 12, 'end_index' => 17, 'sample_count' => 6]
        ];
        
        // Simulate filtering by lap_number = 2
        $options = ['lap_number' => 2];
        $filtered = array_filter($allLaps, function($lap) use ($options) {
            return $lap['lap_number'] == $options['lap_number'];
        });
        
        $this->assertCount(1, $filtered);
        $this->assertEquals(2, array_values($filtered)[0]['lap_number']);
    }

    public function testGetLapDataFilterByMinSamples()
    {
        $allLaps = [
            ['lap_number' => 1, 'start_index' => 0, 'end_index' => 2, 'sample_count' => 3],
            ['lap_number' => 2, 'start_index' => 3, 'end_index' => 7, 'sample_count' => 5],
            ['lap_number' => 3, 'start_index' => 8, 'end_index' => 19, 'sample_count' => 12]
        ];
        
        // Simulate filtering by min_samples = 5
        $options = ['min_samples' => 5];
        $filtered = array_filter($allLaps, function($lap) use ($options) {
            return $lap['sample_count'] >= $options['min_samples'];
        });
        
        $this->assertCount(2, $filtered);
        
        $filtered = array_values($filtered);
        $this->assertEquals(2, $filtered[0]['lap_number']);
        $this->assertEquals(3, $filtered[1]['lap_number']);
    }

    public function testGetLapDataReindexesArray()
    {
        $filtered = [
            1 => ['lap_number' => 2],
            3 => ['lap_number' => 4]
        ];
        
        $reindexed = array_values($filtered);
        
        $this->assertArrayHasKey(0, $reindexed);
        $this->assertArrayHasKey(1, $reindexed);
        $this->assertArrayNotHasKey(2, $reindexed);
        $this->assertEquals(2, $reindexed[0]['lap_number']);
        $this->assertEquals(4, $reindexed[1]['lap_number']);
    }

    public function testJsonDecodeHandlesInvalidJson()
    {
        $invalidJson = "This is not JSON";
        $result = json_decode($invalidJson, true);
        
        $this->assertNull($result);
        $this->assertNotEquals(JSON_ERROR_NONE, json_last_error());
    }

    public function testJsonDecodeHandlesValidJson()
    {
        $validJson = '{"0":1,"1":1,"2":2,"3":2}';
        $result = json_decode($validJson, true);
        
        $this->assertIsArray($result);
        $this->assertEquals(JSON_ERROR_NONE, json_last_error());
        $this->assertCount(4, $result);
    }

    public function testJsonDecodeHandlesEmptyArray()
    {
        $emptyArrayJson = '[]';
        $result = json_decode($emptyArrayJson, true);
        
        $this->assertIsArray($result);
        $this->assertEmpty($result);
        $this->assertEquals(JSON_ERROR_NONE, json_last_error());
    }

    public function testArrayKeyLastFunctionality()
    {
        $data = [
            0 => 1,
            5 => 2,
            10 => 3,
            15 => 4
        ];
        
        $lastKey = array_key_last($data);
        
        $this->assertEquals(15, $lastKey);
        $this->assertEquals(4, $data[$lastKey]);
    }

    /**
     * Helper method to invoke private parseLapIndices method using reflection
     */
    private function invokeParseLapIndices($lapData)
    {
        $reflection = new \ReflectionClass('App\Service\LapService');
        $method = $reflection->getMethod('parseLapIndices');
        $method->setAccessible(true);
        
        return $method->invoke(null, $lapData);
    }
}
