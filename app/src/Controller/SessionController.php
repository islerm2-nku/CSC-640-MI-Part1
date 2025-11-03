<?php

namespace App\Controller;

use App\Model\Database;
use App\Service\LapService;

class SessionController extends BaseController
{
    public function getAllSessions()
    {
        try {
            $pdo = Database::getPdo();
            $stmt = $pdo->query("SELECT * FROM session_info ORDER BY session_date DESC, session_time DESC");
            $sessions = $stmt->fetchAll();
            
            return $this->jsonResponse(['sessions' => $sessions]);
        } catch (\Exception $e) {
            return $this->jsonResponse(['error' => $e->getMessage()], 500);
        }
    }

    public function getSession($sessionId)
    {
        try {
            $pdo = Database::getPdo();
            
            // Get session info
            $stmt = $pdo->prepare("SELECT * FROM session_info WHERE session_id = ?");
            $stmt->execute([$sessionId]);
            $session = $stmt->fetch();
            
            if (!$session) {
                return $this->jsonResponse(['error' => 'Session not found'], 404);
            }
            
            // Get weather info
            $stmt = $pdo->prepare("SELECT * FROM weather WHERE session_id = ?");
            $stmt->execute([$sessionId]);
            $weather = $stmt->fetch();
            
            // Get driver info
            $stmt = $pdo->prepare("SELECT * FROM driver WHERE session_id = ?");
            $stmt->execute([$sessionId]);
            $drivers = $stmt->fetchAll();
            
            return $this->jsonResponse([
                'session_info' => $session,
                'weather' => $weather,
                'drivers' => $drivers
            ]);
        } catch (\Exception $e) {
            return $this->jsonResponse(['error' => $e->getMessage()], 500);
        }
    }

    public function getSessionLapCount($sessionId)
    {
        try {
            // Include incident detection in lap data
            $laps = LapService::getLapIndices($sessionId, true);
            
            // Count valid laps (no incidents)
            $validLapCount = count(array_filter($laps, function($lap) {
                return $lap['valid_lap'] === true;
            }));
            
            return $this->jsonResponse([
                'session_id' => $sessionId,
                'lap_count' => count($laps),
                'valid_lap_count' => $validLapCount,
                'invalid_lap_count' => count($laps) - $validLapCount,
                'laps' => $laps
            ]);
        } catch (\Exception $e) {
            $statusCode = $e->getCode() ?: 500;
            return $this->jsonResponse(['error' => $e->getMessage()], $statusCode);
        }
    }

    public function getLapAttributeData($sessionId, $lapNumber)
    {
        try {
            // Get attribute from query parameters
            $attribute = $_GET['attribute'] ?? null;
            
            if (!$attribute) {
                return $this->jsonResponse(['error' => 'Missing required query parameter: attribute'], 400);
            }
            
            $pdo = Database::getPdo();
            
            // Get lap data
            $laps = LapService::getLapIndices($sessionId);
            
            // Find the specific lap
            $lapData = null;
            foreach ($laps as $lap) {
                if ($lap['lap_number'] == $lapNumber) {
                    $lapData = $lap;
                    break;
                }
            }
            
            if (!$lapData) {
                return $this->jsonResponse(['error' => "Lap {$lapNumber} not found in session"], 404);
            }
            
            // Fetch the attribute value
            $stmt = $pdo->prepare("SELECT value FROM attribute_values WHERE session_id = ? AND attribute = ?");
            $stmt->execute([$sessionId, $attribute]);
            $result = $stmt->fetch();
            
            if (!$result) {
                return $this->jsonResponse(['error' => "Attribute '{$attribute}' not found for this session"], 404);
            }
            
            // Parse the attribute data
            $attributeData = json_decode($result['value'], true);
            
            if ($attributeData === null && json_last_error() !== JSON_ERROR_NONE) {
                return $this->jsonResponse(['error' => "Failed to parse attribute data: " . json_last_error_msg()], 500);
            }
            
            // Extract data for the specific lap range
            $startIndex = $lapData['start_index'];
            $endIndex = $lapData['end_index'];
            
            $lapAttributeData = [];
            for ($i = $startIndex; $i <= $endIndex; $i++) {
                $lapAttributeData[$i] = isset($attributeData[$i]) ? $attributeData[$i] : null;
            }
            
            return $this->jsonResponse([
                'session_id' => $sessionId,
                'lap_number' => $lapNumber,
                'attribute' => $attribute,
                'start_index' => $startIndex,
                'end_index' => $endIndex,
                'sample_count' => $lapData['sample_count'],
                'data' => $lapAttributeData
            ]);
        } catch (\Exception $e) {
            $statusCode = $e->getCode() ?: 500;
            return $this->jsonResponse(['error' => $e->getMessage()], $statusCode);
        }
    }

    public function getLapAttributeAverages($sessionId, $lapNumber)
    {
        try {
            // Get attributes from query parameters (comma-separated or multiple ?attribute= params)
            $attributeParam = $_GET['attribute'] ?? null;
            
            if (!$attributeParam) {
                return $this->jsonResponse(['error' => 'Missing required query parameter: attribute'], 400);
            }
            
            // Support both comma-separated and multiple query parameters
            $attributes = [];
            if (strpos($attributeParam, ',') !== false) {
                $attributes = array_map('trim', explode(',', $attributeParam));
            } else {
                $attributes = [$attributeParam];
            }
            
            // Also check for multiple ?attribute= parameters
            if (isset($_GET['attribute']) && is_array($_GET['attribute'])) {
                $attributes = array_merge($attributes, $_GET['attribute']);
            }
            
            $attributes = array_unique($attributes);
            
            $pdo = Database::getPdo();
            
            // Get lap data
            $laps = LapService::getLapIndices($sessionId);
            
            // Find the specific lap
            $lapData = null;
            foreach ($laps as $lap) {
                if ($lap['lap_number'] == $lapNumber) {
                    $lapData = $lap;
                    break;
                }
            }
            
            if (!$lapData) {
                return $this->jsonResponse(['error' => "Lap {$lapNumber} not found in session"], 404);
            }
            
            $startIndex = $lapData['start_index'];
            $endIndex = $lapData['end_index'];
            
            // Fetch all requested attributes and calculate averages
            $attributesAverages = [];
            foreach ($attributes as $attribute) {
                $stmt = $pdo->prepare("SELECT value FROM attribute_values WHERE session_id = ? AND attribute = ?");
                $stmt->execute([$sessionId, $attribute]);
                $result = $stmt->fetch();
                
                if (!$result) {
                    $attributesAverages[$attribute] = null;
                    continue;
                }
                
                // Parse the attribute data
                $parsedData = json_decode($result['value'], true);
                
                if ($parsedData === null && json_last_error() !== JSON_ERROR_NONE) {
                    return $this->jsonResponse(['error' => "Failed to parse attribute '{$attribute}': " . json_last_error_msg()], 500);
                }
                
                // Calculate average for this lap's frame range
                $values = [];
                for ($i = $startIndex; $i <= $endIndex; $i++) {
                    if (isset($parsedData[$i]) && is_numeric($parsedData[$i])) {
                        $values[] = $parsedData[$i];
                    }
                }
                
                // Calculate average
                $average = !empty($values) ? array_sum($values) / count($values) : null;
                
                $attributesAverages[$attribute] = [
                    'average' => $average,
                    'min' => !empty($values) ? min($values) : null,
                    'max' => !empty($values) ? max($values) : null,
                    'sample_count' => count($values)
                ];
            }
            
            return $this->jsonResponse([
                'session_id' => $sessionId,
                'lap_number' => $lapNumber,
                'start_index' => $startIndex,
                'end_index' => $endIndex,
                'lap_sample_count' => $lapData['sample_count'],
                'attributes' => $attributesAverages
            ]);
        } catch (\Exception $e) {
            $statusCode = $e->getCode() ?: 500;
            return $this->jsonResponse(['error' => $e->getMessage()], $statusCode);
        }
    }

    public function deleteLapAttributeData($sessionId, $lapNumber)
    {
        try {
            $pdo = Database::getPdo();
            
            // Get lap data
            $laps = LapService::getLapIndices($sessionId);
            
            // Find the specific lap
            $lapData = null;
            foreach ($laps as $lap) {
                if ($lap['lap_number'] == $lapNumber) {
                    $lapData = $lap;
                    break;
                }
            }
            
            if (!$lapData) {
                return $this->jsonResponse(['error' => "Lap {$lapNumber} not found in session"], 404);
            }
            
            $startIndex = $lapData['start_index'];
            $endIndex = $lapData['end_index'];
            
            // Check if specific attributes are requested
            $attributeParam = $_GET['attribute'] ?? null;
            
            if ($attributeParam) {
                // Delete specific attributes
                $attributes = [];
                if (strpos($attributeParam, ',') !== false) {
                    $attributes = array_map('trim', explode(',', $attributeParam));
                } else {
                    $attributes = [$attributeParam];
                }
                
                // Also check for multiple ?attribute= parameters
                if (isset($_GET['attribute']) && is_array($_GET['attribute'])) {
                    $attributes = array_merge($attributes, $_GET['attribute']);
                }
                
                $attributes = array_unique($attributes);
            } else {
                // Get all attributes for this session
                $stmt = $pdo->prepare("SELECT DISTINCT attribute FROM attribute_values WHERE session_id = ?");
                $stmt->execute([$sessionId]);
                $results = $stmt->fetchAll();
                $attributes = array_column($results, 'attribute');
            }
            
            // Delete data for each attribute in the lap range
            $deletedCount = 0;
            foreach ($attributes as $attribute) {
                $stmt = $pdo->prepare("SELECT value FROM attribute_values WHERE session_id = ? AND attribute = ?");
                $stmt->execute([$sessionId, $attribute]);
                $result = $stmt->fetch();
                
                if (!$result) {
                    continue;
                }
                
                // Parse the attribute data
                $attributeData = json_decode($result['value'], true);
                
                if ($attributeData === null && json_last_error() !== JSON_ERROR_NONE) {
                    return $this->jsonResponse(['error' => "Failed to parse attribute '{$attribute}': " . json_last_error_msg()], 500);
                }
                
                // Remove data for indices in the lap range
                for ($i = $startIndex; $i <= $endIndex; $i++) {
                    if (isset($attributeData[$i])) {
                        unset($attributeData[$i]);
                        $deletedCount++;
                    }
                }
                
                // Re-index array to maintain integrity
                $attributeData = array_values($attributeData);
                $newValue = json_encode($attributeData);
                $newLength = strlen($newValue);
                
                // Update the attribute with modified data
                $updateStmt = $pdo->prepare("UPDATE attribute_values SET value = ?, value_len = ? WHERE session_id = ? AND attribute = ?");
                $updateStmt->execute([$newValue, $newLength, $sessionId, $attribute]);
            }
            
            return $this->jsonResponse([
                'session_id' => $sessionId,
                'lap_number' => $lapNumber,
                'attributes_deleted' => $attributes,
                'start_index' => $startIndex,
                'end_index' => $endIndex,
                'data_points_deleted' => $deletedCount,
                'message' => "Successfully deleted attribute data for lap {$lapNumber}"
            ]);
        } catch (\Exception $e) {
            $statusCode = $e->getCode() ?: 500;
            return $this->jsonResponse(['error' => $e->getMessage()], $statusCode);
        }
    }

    public function deleteSession($sessionId)
    {
        try {
            $pdo = Database::getPdo();
            
            // Verify session exists
            $stmt = $pdo->prepare("SELECT session_id FROM session_info WHERE session_id = ?");
            $stmt->execute([$sessionId]);
            $session = $stmt->fetch();
            
            if (!$session) {
                return $this->jsonResponse(['error' => 'Session not found'], 404);
            }
            
            // Delete will cascade to related tables due to foreign key constraints
            // Delete weather (explicitly, though cascade should handle it)
            $stmt = $pdo->prepare("DELETE FROM weather WHERE session_id = ?");
            $stmt->execute([$sessionId]);
            $weatherDeleted = $stmt->rowCount();
            
            // Delete drivers (explicitly, though cascade should handle it)
            $stmt = $pdo->prepare("DELETE FROM driver WHERE session_id = ?");
            $stmt->execute([$sessionId]);
            $driversDeleted = $stmt->rowCount();
            
            // Delete attribute values (explicitly, though cascade should handle it)
            $stmt = $pdo->prepare("DELETE FROM attribute_values WHERE session_id = ?");
            $stmt->execute([$sessionId]);
            $attributesDeleted = $stmt->rowCount();
            
            // Delete session info (this should cascade delete everything due to foreign keys)
            $stmt = $pdo->prepare("DELETE FROM session_info WHERE session_id = ?");
            $stmt->execute([$sessionId]);
            $sessionDeleted = $stmt->rowCount();
            
            return $this->jsonResponse([
                'session_id' => $sessionId,
                'message' => 'Session and all associated data deleted successfully',
                'deleted_records' => [
                    'session_info' => $sessionDeleted,
                    'weather' => $weatherDeleted,
                    'drivers' => $driversDeleted,
                    'attribute_values' => $attributesDeleted
                ]
            ]);
        } catch (\Exception $e) {
            $statusCode = $e->getCode() ?: 500;
            return $this->jsonResponse(['error' => $e->getMessage()], $statusCode);
        }
    }
}
