<?php
/**
 * PeopleDisplay
 * Copyright (c) 2024 Ton Labee — https://peopledisplay.nl
 *
 * Starter versie: GNU AGPL v3 (zie /LICENSE)
 * Commercieel gebruik boven Starter limieten vereist een licentie.
 */
// ============================================
// SESSION TRACKER - Helper Class
// ============================================
// File: includes/session_tracker.php
// Install location: /includes/session_tracker.php
// ============================================

class SessionTracker {
    private $db;
    private $session_timeout = 900; // 15 minutes in seconds
    
    public function __construct($database) {
        $this->db = $database;
    }
    
    /**
     * Start tracking session (call on login)
     */
    public function startSession($user_id) {
        $session_id = session_id();
        $ip_address = $this->getIpAddress();
        $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';
        $browser = $this->getBrowser($user_agent);
        $device = $this->getDevice($user_agent);
        
        // Delete old session if exists
        $stmt = $this->db->prepare("DELETE FROM user_sessions WHERE session_id = ?");
        $stmt->execute([$session_id]);
        
        // Insert new session
        $stmt = $this->db->prepare("
            INSERT INTO user_sessions 
            (user_id, session_id, ip_address, user_agent, browser, device, login_time, last_activity, is_active)
            VALUES (?, ?, ?, ?, ?, ?, NOW(), NOW(), 1)
        ");
        
        return $stmt->execute([
            $user_id,
            $session_id,
            $ip_address,
            $user_agent,
            $browser,
            $device
        ]);
    }
    
    /**
     * Update activity (call on every page load)
     */
    public function updateActivity($page_url = null) {
        if (!isset($_SESSION['user_id'])) {
            return false;
        }
        
        $session_id = session_id();
        $page_url = $page_url ?? $_SERVER['REQUEST_URI'] ?? null;
        
        $stmt = $this->db->prepare("
            UPDATE user_sessions 
            SET last_activity = NOW(), 
                page_url = ?,
                is_active = 1
            WHERE session_id = ? AND user_id = ?
        ");
        
        return $stmt->execute([
            $page_url,
            $session_id,
            $_SESSION['user_id']
        ]);
    }
    
    /**
     * End session (call on logout)
     */
    public function endSession() {
        $session_id = session_id();
        
        $stmt = $this->db->prepare("
            UPDATE user_sessions 
            SET is_active = 0
            WHERE session_id = ?
        ");
        
        return $stmt->execute([$session_id]);
    }
    
    /**
     * Cleanup expired sessions (run via cron or on admin page load)
     */
    public function cleanupExpiredSessions() {
        $timeout_minutes = ceil($this->session_timeout / 60);
        
        $stmt = $this->db->prepare("
            UPDATE user_sessions 
            SET is_active = 0
            WHERE last_activity < DATE_SUB(NOW(), INTERVAL ? MINUTE)
            AND is_active = 1
        ");
        
        return $stmt->execute([$timeout_minutes]);
    }
    
    /**
     * Get all active sessions with user details
     */
    public function getActiveSessions() {
        $timeout_minutes = ceil($this->session_timeout / 60);
        
        $stmt = $this->db->query("
            SELECT 
                s.id,
                s.user_id,
                u.username,
                u.display_name,
                u.email,
                u.role,
                s.session_id,
                s.ip_address,
                s.user_agent,
                s.browser,
                s.device,
                s.login_time,
                s.last_activity,
                s.page_url,
                s.is_active,
                TIMESTAMPDIFF(SECOND, s.login_time, NOW()) as session_duration,
                TIMESTAMPDIFF(SECOND, s.last_activity, NOW()) as idle_seconds,
                CASE 
                    WHEN TIMESTAMPDIFF(SECOND, s.last_activity, NOW()) < 60 THEN 'online'
                    WHEN TIMESTAMPDIFF(SECOND, s.last_activity, NOW()) < 300 THEN 'idle'
                    ELSE 'away'
                END as status
            FROM user_sessions s
            INNER JOIN users u ON s.user_id = u.id
            WHERE s.is_active = 1
            AND s.last_activity > DATE_SUB(NOW(), INTERVAL $timeout_minutes MINUTE)
            ORDER BY s.last_activity DESC
        ");
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Get session count
     */
    public function getActiveSessionCount() {
        $timeout_minutes = ceil($this->session_timeout / 60);
        
        $stmt = $this->db->query("
            SELECT COUNT(*) as count
            FROM user_sessions
            WHERE is_active = 1
            AND last_activity > DATE_SUB(NOW(), INTERVAL $timeout_minutes MINUTE)
        ");
        
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result['count'];
    }
    
    /**
     * Force logout user (admin function)
     */
    public function forceLogout($user_id) {
        $stmt = $this->db->prepare("
            UPDATE user_sessions 
            SET is_active = 0
            WHERE user_id = ? AND is_active = 1
        ");
        
        return $stmt->execute([$user_id]);
    }
    
    /**
     * Get user's IP address (proxy-safe)
     */
    private function getIpAddress() {
        if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
            return $_SERVER['HTTP_CLIENT_IP'];
        } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            return $_SERVER['HTTP_X_FORWARDED_FOR'];
        } else {
            return $_SERVER['REMOTE_ADDR'] ?? 'Unknown';
        }
    }
    
    /**
     * Parse browser from user agent
     */
    private function getBrowser($user_agent) {
        if (preg_match('/MSIE/i', $user_agent)) {
            return 'Internet Explorer';
        } elseif (preg_match('/Firefox/i', $user_agent)) {
            return 'Firefox';
        } elseif (preg_match('/Chrome/i', $user_agent)) {
            return 'Chrome';
        } elseif (preg_match('/Safari/i', $user_agent)) {
            return 'Safari';
        } elseif (preg_match('/Opera/i', $user_agent)) {
            return 'Opera';
        } elseif (preg_match('/Edge/i', $user_agent)) {
            return 'Edge';
        } else {
            return 'Unknown';
        }
    }
    
    /**
     * Parse device from user agent
     */
    private function getDevice($user_agent) {
        if (preg_match('/mobile/i', $user_agent)) {
            return 'Mobile';
        } elseif (preg_match('/tablet|ipad/i', $user_agent)) {
            return 'Tablet';
        } else {
            return 'Desktop';
        }
    }
}
