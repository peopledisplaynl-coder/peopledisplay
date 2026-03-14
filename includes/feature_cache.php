<?php
// =============================================================================
// START OF FILE: /includes/feature_cache.php
// PURPOSE: Session-cached retrieval and invalidation helpers for per-user feature visibility
// USAGE: require_once __DIR__ . '/feature_cache.php'; then call get_cached_user_features($pdo, $userId)
// =============================================================================

declare(strict_types=1);

if (!function_exists('get_cached_user_features')) {

  /**
   * Haal zichtbaar gemaakte feature key_names voor een gebruiker, met session cache (TTL).
   *
   * @param PDO $pdo
   * @param int|null $userId
   * @param int $ttl seconds cacheduur, standaard 300 (5 minuten)
   * @return array Associatieve array met key_name => true
   */
  function get_cached_user_features(PDO $pdo, ?int $userId, int $ttl = 300): array {
    if (empty($userId) || $userId <= 0) return [];

    if (session_status() !== PHP_SESSION_ACTIVE) session_start();

    $key = "user_features_cache_v1_{$userId}";
    $now = time();

    // Return cached when geldig
    if (!empty($_SESSION[$key]) && !empty($_SESSION[$key]['ts']) && (($_SESSION[$key]['ts'] + $ttl) > $now)) {
      return $_SESSION[$key]['data'];
    }

    // Load from DB
    try {
      $stmt = $pdo->prepare(
        'SELECT fk.key_name
         FROM user_features uf
         JOIN feature_keys fk ON fk.id = uf.feature_key_id
         WHERE uf.user_id = :uid AND uf.visible = 1'
      );
      $stmt->execute([':uid' => $userId]);
      $rows = $stmt->fetchAll(PDO::FETCH_COLUMN) ?: [];
      $out = [];
      foreach ($rows as $r) { $out[$r] = true; }

      // Store in session cache
      $_SESSION[$key] = [
        'ts' => $now,
        'data' => $out
      ];
      return $out;
    } catch (Throwable $e) {
      error_log('feature cache load error: ' . $e->getMessage());
      return [];
    }
  }

  /**
   * Invalideer cache voor één gebruiker (gebruik na succesvolle write).
   *
   * @param int $userId
   * @return void
   */
  function invalidate_user_features_cache(int $userId): void {
    if (session_status() !== PHP_SESSION_ACTIVE) session_start();
    $key = "user_features_cache_v1_{$userId}";
    if (isset($_SESSION[$key])) unset($_SESSION[$key]);
  }

  /**
   * Invalideer alle gebruikerscaches (bij bulk imports of feature_keys mutatie).
   *
   * @return void
   */
  function invalidate_all_user_features_cache(): void {
    if (session_status() !== PHP_SESSION_ACTIVE) session_start();
    foreach (array_keys($_SESSION) as $k) {
      if (strpos($k, 'user_features_cache_v1_') === 0) unset($_SESSION[$k]);
    }
  }
}

# =============================================================================
# END OF FILE: /includes/feature_cache.php
# =============================================================================
