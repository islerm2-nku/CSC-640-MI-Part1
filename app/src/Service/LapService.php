<?php

namespace App\Service;

use App\Model\Database;

class LapService
{
    /**
     * Get lap data for a specific session and parse lap start/end indices
     * 
     * @param string $sessionId The session ID to fetch lap data for
     * @param bool $includeIncidents Whether to check for incidents in each lap
     * @return array Array containing lap information with start and end indices for each lap
     * @throws \Exception If session not found or data cannot be parsed
     */
    public static function getLapIndices($sessionId, $includeIncidents = false)
    {
        $pdo = Database::getPdo();
        
        // Fetch the Lap attribute value for the session
        $stmt = $pdo->prepare("SELECT value FROM attribute_values WHERE session_id = ? AND attribute = 'Lap'");
        $stmt->execute([$sessionId]);
        $result = $stmt->fetch();
        
        if (!$result) {
            throw new \Exception("No lap data found for session: {$sessionId}", 404);
        }
        
        // Parse the stringified value (assuming it's JSON)
        $lapData = json_decode($result['value'], true);
        
        if ($lapData === null && json_last_error() !== JSON_ERROR_NONE) {
            throw new \Exception("Failed to parse lap data: " . json_last_error_msg(), 500);
        }
        
        // Process lap data to find start and end indices for each lap
        $laps = self::parseLapIndices($lapData);
        
        // Add incident detection if requested
        if ($includeIncidents) {
            $laps = self::addIncidentData($sessionId, $laps);
        }
        
        return $laps;
    }
    
    /**
     * Parse lap data array to find start and end indices for each lap
     * 
     * @param array $lapData Array of lap numbers indexed by sample position
     * @return array Array of laps with their start and end indices
     */
    private static function parseLapIndices($lapData)
    {
        if (empty($lapData)) {
            return [];
        }
        
        $laps = [];
        $currentLap = null;
        $startIndex = null;
        
        foreach ($lapData as $index => $lapNumber) {
            // Skip if lap number is 0 or negative (usually warmup/cooldown)
            if ($lapNumber <= 0) {
                continue;
            }
            
            // New lap detected
            if ($currentLap !== $lapNumber) {
                // Save previous lap if it exists
                if ($currentLap !== null && $startIndex !== null) {
                    $laps[] = [
                        'lap_number' => $currentLap,
                        'start_index' => $startIndex,
                        'end_index' => $index - 1,
                        'sample_count' => ($index - 1) - $startIndex + 1
                    ];
                }
                
                // Start new lap
                $currentLap = $lapNumber;
                $startIndex = $index;
            }
        }
        
        // Add the last lap
        if ($currentLap !== null && $startIndex !== null) {
            $lastIndex = array_key_last($lapData);
            $laps[] = [
                'lap_number' => $currentLap,
                'start_index' => $startIndex,
                'end_index' => $lastIndex,
                'sample_count' => $lastIndex - $startIndex + 1
            ];
        }
        
        return $laps;
    }
    
    /**
     * Get lap data with additional filtering options
     * 
     * @param string $sessionId The session ID to fetch lap data for
     * @param array $options Optional parameters (lap_number, min_samples, etc.)
     * @return array Filtered lap data
     */
    public static function getLapData($sessionId, $options = [])
    {
        $laps = self::getLapIndices($sessionId);
        
        // Filter by specific lap number if provided
        if (isset($options['lap_number'])) {
            $laps = array_filter($laps, function($lap) use ($options) {
                return $lap['lap_number'] == $options['lap_number'];
            });
        }
        
        // Filter by minimum sample count if provided
        if (isset($options['min_samples'])) {
            $laps = array_filter($laps, function($lap) use ($options) {
                return $lap['sample_count'] >= $options['min_samples'];
            });
        }
        
        return array_values($laps); // Re-index array
    }
    
    /**
     * Add incident data to laps by checking PlayerIncidents attribute
     * 
     * @param string $sessionId The session ID
     * @param array $laps Array of lap data with start/end indices
     * @return array Laps with added incident information
     */
    private static function addIncidentData($sessionId, $laps)
    {
        $pdo = Database::getPdo();
        
        // Try to fetch PlayerIncidents attribute
        $stmt = $pdo->prepare("SELECT value FROM attribute_values WHERE session_id = ? AND attribute = 'PlayerIncidents'");
        $stmt->execute([$sessionId]);
        $result = $stmt->fetch();
        
        // If PlayerIncidents doesn't exist, mark all laps as valid
        if (!$result) {
            foreach ($laps as &$lap) {
                $lap['valid_lap'] = true;
                $lap['incidents_in_lap'] = null;
            }
            return $laps;
        }
        
        // Parse the PlayerIncidents data
        $incidentData = json_decode($result['value'], true);
        
        if ($incidentData === null && json_last_error() !== JSON_ERROR_NONE) {
            // If we can't parse incidents, assume all laps are valid
            foreach ($laps as &$lap) {
                $lap['valid_lap'] = true;
                $lap['incidents_in_lap'] = null;
            }
            return $laps;
        }
        
        // Check each lap for incidents
        foreach ($laps as &$lap) {
            $startIndex = $lap['start_index'];
            $endIndex = $lap['end_index'];
            
            // Count all incidents (1 values) within the lap's frame range
            $incidentCount = 0;
            for ($i = $startIndex; $i <= $endIndex; $i++) {
                if (isset($incidentData[$i]) && $incidentData[$i] == 1) {
                    $incidentCount++;
                }
            }
            
            $lap['incidents_in_lap'] = $incidentCount;
            $lap['valid_lap'] = $incidentCount === 0;
        }
        
        return $laps;
    }
}
