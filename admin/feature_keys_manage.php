<?php
/**
 * PeopleDisplay
 * Copyright (c) 2024 Ton Labee — https://peopledisplay.nl
 *
 * Starter versie: GNU AGPL v3 (zie /LICENSE)
 * Commercieel gebruik boven Starter limieten vereist een licentie.
 */
declare(strict_types=1);
session_start();
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'superadmin') {
  header('Location: /login.php');
  exit;
}
require_once __DIR__ . '/../includes/db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $action = $_POST['action'] ?? '';
  $name = trim($_POST['key_name'] ?? '');
  $category = trim($_POST['category'] ?? '');

  if ($action === 'add' && $name !== '') {
    $stmt = $db->prepare("INSERT INTO feature_keys (key_name, category) VALUES (?, ?)");
    $stmt->execute([$name, $category]);
  }

  if ($action === 'delete' && isset($_POST['id'])) {
    $stmt = $db->prepare("DELETE FROM feature_keys WHERE id = ?");
    $stmt->execute([intval($_POST['id'])]);
  }

  header('Location: feature_keys_manage.php');
  exit;
}

$stmt = $db->query("SELECT id, key_name, category FROM feature_keys ORDER BY category, key_name");
$features = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!doctype html>
<html lang="nl">
<head>
  <meta charset="utf-8">
  <title>Featurebeheer</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="p-4">
  <h2>🔧 Featurebeheer</h2>
  <form method="post" class="row g-2 mb-4">
    <input type="hidden" name="action" value="add">
    <div class="col-md-4">
      <input type="text" name="key_name" class="form-control" placeholder="Feature naam" required>
    </div>
    <div class="col-md-4">
      <input type="text" name="category" class="form-control" placeholder="Categorie (optioneel)">
    </div>
    <div class="col-md-4">
      <button type="submit" class="btn btn-primary">Toevoegen</button>
    </div>
  </form>

  <table class="table table-bordered">
    <thead><tr><th>Naam</th><th>Categorie</th><th>Actie</th></tr></thead>
    <tbody>
      <?php foreach ($features as $f): ?>
      <tr>
        <td><?= htmlspecialchars($f['key_name']) ?></td>
        <td><?= htmlspecialchars($f['category']) ?></td>
        <td>
          <form method="post" style="display:inline;">
            <input type="hidden" name="action" value="delete">
            <input type="hidden" name="id" value="<?= $f['id'] ?>">
            <button type="submit" class="btn btn-sm btn-danger">Verwijder</button>
          </form>
        </td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</body>
</html>
