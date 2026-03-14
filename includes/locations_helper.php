<?php
/**
 * Locations Helper Functions
 * 
 * Provides utility functions for location management
 */

/**
 * Get locations in configured order from database
 * Falls back to alphabetical if no order is set
 * 
 * @param PDO $db Database connection
 * @return array Ordered array of location names
 */
function getOrderedLocations($db) {
    try {
        $stmt = $db->query("SELECT locations_order, locations FROM config WHERE id = 1");
        $config = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$config) {
            return [];
        }
        
        // Try to get ordered locations first
        $orderedLocations = json_decode($config['locations_order'] ?? '[]', true);
        
        // If we have a custom order, use it
        if (!empty($orderedLocations) && is_array($orderedLocations)) {
            return $orderedLocations;
        }
        
        // Fallback: get all locations and sort alphabetically
        $allLocations = json_decode($config['locations'] ?? '[]', true);
        
        if (!empty($allLocations) && is_array($allLocations)) {
            sort($allLocations);
            return $allLocations;
        }
        
        return [];
        
    } catch (Exception $e) {
        error_log('Error getting ordered locations: ' . $e->getMessage());
        return [];
    }
}

/**
 * Extract location ID from location string
 * Handles formats like "05: Location Name" or "Location Name"
 * 
 * @param string $locationString The location string
 * @return string Location ID or normalized name
 */
function getLocationID($locationString) {
    if (empty($locationString)) {
        return '';
    }
    
    // Try to extract number prefix (e.g., "05:" from "05: Amsterdam")
    if (preg_match('/^(\d+)/', $locationString, $matches)) {
        return $matches[1];
    }
    
    // No number prefix, return normalized string
    return strtolower(trim($locationString));
}

/**
 * Update locations order in database
 * 
 * @param PDO $db Database connection
 * @param array $orderedLocations Ordered array of location names
 * @return bool Success
 */
function updateLocationsOrder($db, array $orderedLocations) {
    try {
        $stmt = $db->prepare("
            UPDATE config 
            SET locations_order = ?, updated_at = NOW()
            WHERE id = 1
        ");
        
        return $stmt->execute([json_encode($orderedLocations)]);
        
    } catch (Exception $e) {
        error_log('Error updating locations order: ' . $e->getMessage());
        return false;
    }
}

/**
 * Sync locations from Google Sheets while preserving order
 * 
 * @param PDO $db Database connection
 * @param array $newLocations Locations from Google Sheets
 * @return array Statistics about the sync
 */
function syncLocationsFromSheet($db, array $newLocations) {
    try {
        // Get current config
        $stmt = $db->query("SELECT locations, locations_order FROM config WHERE id = 1");
        $config = $stmt->fetch(PDO::FETCH_ASSOC);
        
        $oldLocations = json_decode($config['locations'] ?? '[]', true) ?: [];
        $currentOrder = json_decode($config['locations_order'] ?? '[]', true) ?: [];
        
        // Determine added and removed locations
        $added = array_diff($newLocations, $oldLocations);
        $removed = array_diff($oldLocations, $newLocations);
        
        // Build new order: keep existing order, append new locations
        $newOrder = [];
        
        // First, add locations that are still in the new list, in their current order
        foreach ($currentOrder as $loc) {
            if (in_array($loc, $newLocations, true)) {
                $newOrder[] = $loc;
            }
        }
        
        // Then, add any new locations that weren't in the order
        foreach ($newLocations as $loc) {
            if (!in_array($loc, $newOrder, true)) {
                $newOrder[] = $loc;
            }
        }
        
        // Update database
        $stmt = $db->prepare("
            UPDATE config 
            SET locations = ?, locations_order = ?, updated_at = NOW()
            WHERE id = 1
        ");
        
        $stmt->execute([
            json_encode($newLocations),
            json_encode($newOrder)
        ]);
        
        return [
            'success' => true,
            'total' => count($newLocations),
            'added' => count($added),
            'removed' => count($removed),
            'added_locations' => array_values($added),
            'removed_locations' => array_values($removed)
        ];
        
    } catch (Exception $e) {
        error_log('Error syncing locations: ' . $e->getMessage());
        return [
            'success' => false,
            'error' => $e->getMessage()
        ];
    }
}
