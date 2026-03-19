<?php
/**
 * Deployment helper (local/internal use only)
 *
 * This tool shows recent commits and lists all files changed since a given Git tag
 * or commit, so you can upload only those files to /install/ on admin.peopledisplay.nl.
 *
 * WARNING: This file is NOT intended to be included in the distribution ZIP.
 *          Ensure your build pipeline/exclude list keeps tools/ out of the release.
 *
 * Usage:
 *   - Open in a browser: tools/deploy_changes.php
 *   - Optionally provide query params:
 *       ?from=v2.0.9&to=HEAD
 */

function run_cmd(string $cmd): string {
    $output = @shell_exec($cmd . ' 2>&1');
    return $output === null ? '' : trim($output);
}

// Build defaults
$defaultFrom = run_cmd('git describe --tags --abbrev=0 2>/dev/null');
if ($defaultFrom === '') {
    $defaultFrom = 'HEAD~1';
}

$from = trim($_GET['from'] ?? $defaultFrom);
$to   = trim($_GET['to']   ?? 'HEAD');

// Sanitize (basic) - allow typical git ref chars
$fromSafe = preg_replace('/[^A-Za-z0-9_\-\.\/~]/', '', $from);
$toSafe   = preg_replace('/[^A-Za-z0-9_\-\.\/~]/', '', $to);

$commits = run_cmd("git log --oneline -20 " . escapeshellarg($toSafe));
$changed = run_cmd("git diff --name-only " . escapeshellarg($fromSafe) . " " . escapeshellarg($toSafe));

$files = array_filter(array_map('trim', explode("\n", $changed)));

$groups = [
    'includes/' => [],
    'admin/api/' => [],
    'admin/' => [],
    'root' => [],
    'other' => [],
];

foreach ($files as $file) {
    if ($file === '') {
        continue;
    }

    if (strpos($file, 'includes/') === 0) {
        $groups['includes/'][] = $file;
    } elseif (strpos($file, 'admin/api/') === 0) {
        $groups['admin/api/'][] = $file;
    } elseif (strpos($file, 'admin/') === 0) {
        $groups['admin/'][] = $file;
    } elseif (strpos($file, '/') === false) {
        $groups['root'][] = $file;
    } else {
        $groups['other'][] = $file;
    }
}

function h(string $s): string {
    return htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

?>
<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Deploy Changes Helper</title>
    <style>
        body { font-family: system-ui, -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; background: #f7fafc; color: #1f2937; padding: 24px; }
        .container { max-width: 980px; margin: 0 auto; }
        h1 { margin-bottom: 12px; }
        .note { background: rgba(245, 158, 11, 0.12); border: 1px solid rgba(245, 158, 11, 0.35); padding: 16px; border-radius: 8px; margin-bottom: 20px; }
        .panel { background: white; border-radius: 10px; padding: 18px 20px; box-shadow: 0 2px 12px rgba(0,0,0,0.08); margin-bottom: 20px; }
        .panel h2 { margin-top: 0; }
        pre { background: #111827; color: #f8fafc; padding: 12px; border-radius: 8px; overflow-x: auto; }
        ul { list-style: none; padding-left: 0; }
        li { margin-bottom: 6px; }
        label { display: flex; align-items: center; gap: 8px; }
        .mono { font-family: SFMono-Regular, Menlo, Monaco, Consolas, 'Liberation Mono', 'Courier New', monospace; }
        .group { margin-bottom: 16px; }
        .group h3 { margin-bottom: 8px; }
        .small { font-size: 0.9rem; color: #4b5563; }
        .form-row { display: flex; flex-wrap: wrap; gap: 12px; align-items: center; }
        .form-row input { padding: 8px 10px; border-radius: 6px; border: 1px solid #cbd5e1; width: 220px; }
        .form-row button { padding: 9px 14px; border-radius: 6px; border: none; background: #4f46e5; color: white; cursor: pointer; }
        .form-row button:hover { background: #4338ca; }
    </style>
</head>
<body>
    <div class="container">
        <h1>Deploy changes helper</h1>
        <div class="note">
            <strong>Note:</strong> Upload the changed files listed below to the <code>/install/</code> directory on <strong>admin.peopledisplay.nl</strong>.
            <br>
            <strong>Important:</strong> This tool lives in <code>tools/</code> and is not intended to be included in the distribution ZIP. Make sure the build process keeps <code>tools/</code> excluded.
        </div>

        <div class="panel">
            <h2>Options</h2>
            <form method="get">
                <div class="form-row">
                    <label>
                        From (tag/commit):
                        <input name="from" value="<?= h($from) ?>" />
                    </label>
                    <label>
                        To (tag/commit):
                        <input name="to" value="<?= h($to) ?>" />
                    </label>
                    <button type="submit">Refresh</button>
                </div>
                <div class="small">Defaults: from latest tag (if any), to HEAD.</div>
            </form>
        </div>

        <div class="panel">
            <h2>Recent commits (<?= h($toSafe) ?>)</h2>
            <pre><?= h($commits ?: "(no output)") ?></pre>
        </div>

        <div class="panel">
            <h2>Files changed since <?= h($fromSafe) ?> → <?= h($toSafe) ?></h2>

            <?php if (empty($files)): ?>
                <p>No changed files detected.</p>
            <?php else: ?>
                <form>
                    <?php foreach ($groups as $group => $items): ?>
                        <?php if (empty($items)) continue; ?>
                        <div class="group">
                            <h3><?= h($group) ?></h3>
                            <ul>
                                <?php foreach ($items as $file): ?>
                                    <li>
                                        <label>
                                            <input type="checkbox" checked disabled />
                                            <span class="mono"><?= h($file) ?></span>
                                        </label>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php endforeach; ?>
                </form>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
