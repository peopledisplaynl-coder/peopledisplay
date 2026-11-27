<?php
/**
 * infoscreen_branding.php
 * Upload naar: /includes/infoscreen_branding.php
 *
 * Huisstijl of the infoscreen (2.9.0): logo, title, colours and font for the whole
 * organisation (location_name '') and per location. Rule: an empty field of a location
 * follows the organisation; the colours go as one group ("Eigen kleuren gebruiken").
 * A screen with one location uses the huisstijl of that location; a screen with more (or all)
 * locations uses the organisation, unless a huisstijl is chosen for it under Schermen
 * (users.features.infoscreenBrand: '' = organisation, or a location name).
 * Only system fonts: nothing is loaded from the internet.
 */

const PD_BRAND_FONTS = [
    'standaard' => ['Standaard (Segoe UI)', '"Segoe UI", system-ui, -apple-system, Roboto, "Helvetica Neue", Arial, sans-serif'],
    'arial'     => ['Arial', 'Arial, "Helvetica Neue", Helvetica, "Liberation Sans", sans-serif'],
    'calibri'   => ['Calibri', 'Calibri, Carlito, "Segoe UI", Arial, sans-serif'],
    'verdana'   => ['Verdana (extra goed leesbaar)', 'Verdana, Geneva, "DejaVu Sans", sans-serif'],
    'tahoma'    => ['Tahoma', 'Tahoma, Verdana, "Segoe UI", sans-serif'],
    'trebuchet' => ['Trebuchet MS', '"Trebuchet MS", "Lucida Grande", "Segoe UI", Arial, sans-serif'],
    'georgia'   => ['Georgia (met schreef)', 'Georgia, Cambria, "Times New Roman", serif'],
];
const PD_BRAND_LOGO_MODES = ['logo_title' => 'Logo en titel', 'logo' => 'Alleen logo', 'title' => 'Alleen titel'];
// Without own colours: the message colours "Huisstijl" and "Accent" fall back to these
const PD_BRAND_DEFAULT_PRIMARY = '#1d4ed8';
const PD_BRAND_DEFAULT_ACCENT = '#dc2626';
const PD_BRAND_DARK_TEXT = '#10213a';

// ── Colours ──────────────────────────────────────────────────────────────────

function pd_color_valid(?string $hex): ?string
{
    $hex = strtolower(trim((string)$hex));
    if (preg_match('/^#?([0-9a-f]{3})$/', $hex, $m)) {
        $hex = '#' . $m[1][0] . $m[1][0] . $m[1][1] . $m[1][1] . $m[1][2] . $m[1][2];
    }
    if (preg_match('/^#?([0-9a-f]{6})$/', $hex, $m)) return '#' . $m[1];
    return null;
}

/** WCAG relative luminance (0 = black, 1 = white). */
function pd_color_luminance(string $hex): float
{
    $hex = pd_color_valid($hex) ?? '#000000';
    $lin = function (int $c): float {
        $c /= 255;
        return $c <= 0.03928 ? $c / 12.92 : (($c + 0.055) / 1.055) ** 2.4;
    };
    return 0.2126 * $lin(hexdec(substr($hex, 1, 2))) + 0.7152 * $lin(hexdec(substr($hex, 3, 2))) + 0.0722 * $lin(hexdec(substr($hex, 5, 2)));
}

/** WCAG contrast ratio, 1 to 21. */
function pd_color_contrast(string $a, string $b): float
{
    $la = pd_color_luminance($a);
    $lb = pd_color_luminance($b);
    return round((max($la, $lb) + 0.05) / (min($la, $lb) + 0.05), 2);
}

/** White or dark text, whichever reads best on $bg. */
function pd_color_text_on(string $bg): string
{
    return pd_color_contrast('#ffffff', $bg) >= pd_color_contrast(PD_BRAND_DARK_TEXT, $bg) ? '#ffffff' : PD_BRAND_DARK_TEXT;
}

/** Darker (negative) or lighter (positive) shade, -1 … 1. */
function pd_color_shade(string $hex, float $amount): string
{
    $hex = pd_color_valid($hex) ?? '#000000';
    $out = '#';
    foreach ([1, 3, 5] as $i) {
        $c = hexdec(substr($hex, $i, 2));
        $c = $amount < 0 ? $c * (1 + $amount) : $c + (255 - $c) * $amount;
        $out .= str_pad(dechex((int)round(max(0, min(255, $c)))), 2, '0', STR_PAD_LEFT);
    }
    return $out;
}

/**
 * Readability checks for a set of colours (also used by the live check in the form, which
 * has the same rules in JavaScript). Returns a list of [label, ratio, level ok|warn|bad].
 */
function pd_brand_contrast_report(string $primary, string $accent, string $bg1, string $bg2): array
{
    $level = fn(float $r) => $r >= 4.5 ? 'ok' : ($r >= 3 ? 'warn' : 'bad');
    $out = [];
    foreach (['Tekst op de achtergrond (begin)' => $bg1, 'Tekst op de achtergrond (eind)' => $bg2,
              'Tekst op de hoofdkleur' => $primary, 'Tekst op de accentkleur' => $accent] as $label => $bg) {
        $r = pd_color_contrast(pd_color_text_on($bg), $bg);
        $out[] = [$label, $r, $level($r)];
    }
    return $out;
}

// ── Storage ──────────────────────────────────────────────────────────────────

function pd_branding_schema(PDO $db): void
{
    $db->exec("CREATE TABLE IF NOT EXISTS `infoscreen_branding` (
        `id`            int(11)      NOT NULL AUTO_INCREMENT,
        `location_name` varchar(100) NOT NULL DEFAULT '' COMMENT 'Leeg = hele organisatie',
        `title`         varchar(80)  DEFAULT NULL COMMENT 'Leeg = titel van de organisatie',
        `logo_path`     varchar(255) DEFAULT NULL,
        `logo_mode`     varchar(12)  NOT NULL DEFAULT 'logo_title' COMMENT 'logo_title, logo of title',
        `logo_plate`    tinyint(1)   NOT NULL DEFAULT 0 COMMENT '1 = logo op een witte achtergrond',
        `use_colors`    tinyint(1)   NOT NULL DEFAULT 0 COMMENT '0 = kleuren van de organisatie of het thema',
        `color_primary` char(7)      DEFAULT NULL,
        `color_accent`  char(7)      DEFAULT NULL,
        `color_bg1`     char(7)      DEFAULT NULL,
        `color_bg2`     char(7)      DEFAULT NULL,
        `font`          varchar(20)  DEFAULT NULL COMMENT 'Leeg = lettertype van de organisatie',
        `updated_by`    int(11)      DEFAULT NULL,
        `created_at`    datetime     NOT NULL DEFAULT current_timestamp(),
        `updated_at`    datetime     DEFAULT NULL ON UPDATE current_timestamp(),
        PRIMARY KEY (`id`),
        UNIQUE KEY `uq_location` (`location_name`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    // 2.9.0: the title moved from Weergave to the huisstijl of the organisation
    $has = $db->query("SELECT COUNT(*) FROM infoscreen_branding WHERE location_name = ''")->fetchColumn();
    if (!(int)$has) {
        $title = $db->query("SELECT setting_value FROM infoscreen_settings WHERE setting_key = 'title'")->fetchColumn();
        if (is_string($title) && trim($title) !== '') {
            $db->prepare("INSERT INTO infoscreen_branding (location_name, title) VALUES ('', ?)")->execute([mb_substr(trim($title), 0, 80)]);
        }
    }
}

/** All huisstijl rows keyed by location name ('' = organisation). */
function pd_branding_rows(PDO $db): array
{
    try {
        $rows = [];
        foreach ($db->query("SELECT * FROM infoscreen_branding")->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $rows[(string)$r['location_name']] = $r;
        }
        return $rows;
    } catch (\Throwable $e) {
        return [];
    }
}

/** An empty row (the form for a location or organisation without a huisstijl yet). */
function pd_branding_blank(string $location): array
{
    return ['id' => 0, 'location_name' => $location, 'title' => null, 'logo_path' => null, 'logo_mode' => 'logo_title',
            'logo_plate' => 0, 'use_colors' => 0, 'color_primary' => null, 'color_accent' => null,
            'color_bg1' => null, 'color_bg2' => null, 'font' => null];
}

/**
 * The huisstijl a screen uses, filled up with the organisation. $choice: the choice made for
 * the screen (null = automatic: its only location, otherwise the organisation; '' = the
 * organisation; a name = that location). $settingsTitle is the old title (before 2.9.0).
 */
function pd_branding_resolve(array $rows, array $screenLocs, string $theme = 'donker', string $baseUrl = '', string $settingsTitle = '', ?string $choice = null): array
{
    $org = $rows[''] ?? null;
    $name = $choice ?? (count($screenLocs) === 1 ? (string)reset($screenLocs) : '');
    $loc = $name !== '' ? ($rows[$name] ?? null) : null;
    $pick = function (string $field) use ($loc, $org) {
        foreach ([$loc, $org] as $r) {
            if ($r && trim((string)($r[$field] ?? '')) !== '') return $r;
        }
        return null;
    };

    $titleRow = $pick('title');
    $logoRow = $pick('logo_path');
    $fontRow = $pick('font');
    $colorRow = ($loc && (int)$loc['use_colors']) ? $loc : (($org && (int)$org['use_colors']) ? $org : null);
    $font = $fontRow && isset(PD_BRAND_FONTS[$fontRow['font']]) ? $fontRow['font'] : 'standaard';

    $primary = PD_BRAND_DEFAULT_PRIMARY;
    $accent = PD_BRAND_DEFAULT_ACCENT;
    $colors = null;
    if ($colorRow) {
        $primary = pd_color_valid($colorRow['color_primary']) ?? PD_BRAND_DEFAULT_PRIMARY;
        $accent = pd_color_valid($colorRow['color_accent']) ?? PD_BRAND_DEFAULT_ACCENT;
        $bg1 = pd_color_valid($colorRow['color_bg1']) ?? '#0b1f3a';
        $bg2 = pd_color_valid($colorRow['color_bg2']) ?? $bg1;
        $light = (pd_color_luminance($bg1) + pd_color_luminance($bg2)) / 2 > 0.4;
        $text = $light ? PD_BRAND_DARK_TEXT : '#ffffff';
        // Headings in the sidebar use the main colour, unless that is hard to read on the background
        $link = min(pd_color_contrast($primary, $bg1), pd_color_contrast($primary, $bg2)) >= 3 ? $primary : $text;
        $colors = ['bg1' => $bg1, 'bg2' => $bg2, 'light' => $light, 'link' => $link];
    }

    return [
        'title'      => $titleRow ? (string)$titleRow['title'] : $settingsTitle,
        'logo'       => $logoRow ? rtrim($baseUrl, '/') . '/' . ltrim((string)$logoRow['logo_path'], '/') : null,
        'logo_mode'  => $logoRow && isset(PD_BRAND_LOGO_MODES[$logoRow['logo_mode']]) ? $logoRow['logo_mode'] : 'logo_title',
        'logo_plate' => $logoRow ? (int)$logoRow['logo_plate'] === 1 : false,
        'font'       => PD_BRAND_FONTS[$font][1],
        'colors'     => $colors,
        // Message colours "Huisstijl" and "Accent" (always present, also without own colours)
        'primary'    => ['bg' => $primary, 'dark' => pd_color_shade($primary, -0.3), 'text' => pd_color_text_on($primary)],
        'accent'     => ['bg' => $accent, 'dark' => pd_color_shade($accent, -0.3), 'text' => pd_color_text_on($accent)],
        'location'   => $loc ? (string)$loc['location_name'] : null,
    ];
}

/** Store an uploaded logo (PNG, JPG, GIF or WEBP). Returns the relative path. Throws RuntimeException. */
function pd_branding_store_logo(array $file, string $appRoot): string
{
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        throw new RuntimeException('Uploaden van het logo mislukt');
    }
    if ($file['size'] > 2 * 1024 * 1024) {
        throw new RuntimeException('Het logo is te groot (maximaal 2 MB)');
    }
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
    $ext = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/gif' => 'gif', 'image/webp' => 'webp'][$mime] ?? null;
    if ($ext === null || @getimagesize($file['tmp_name']) === false) {
        throw new RuntimeException('Het logo moet een PNG, JPG, GIF of WEBP zijn (SVG: sla het eerst op als PNG)');
    }
    $dir = rtrim($appRoot, '/') . '/' . PD_IS_UPLOAD_DIR;
    if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
        throw new RuntimeException('Map voor afbeeldingen kon niet worden aangemaakt');
    }
    $name = 'logo_' . date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
    if (!move_uploaded_file($file['tmp_name'], $dir . '/' . $name) && !rename($file['tmp_name'], $dir . '/' . $name)) {
        throw new RuntimeException('Het logo kon niet worden opgeslagen');
    }
    return PD_IS_UPLOAD_DIR . '/' . $name;
}

function pd_branding_delete_logo(PDO $db, ?string $path, string $appRoot): void
{
    if (!$path || !str_starts_with($path, PD_IS_UPLOAD_DIR . '/logo_') || str_contains($path, '..')) return;
    $stmt = $db->prepare("SELECT COUNT(*) FROM infoscreen_branding WHERE logo_path = ?");
    $stmt->execute([$path]);
    if ((int)$stmt->fetchColumn() === 0) {
        @unlink(rtrim($appRoot, '/') . '/' . $path);
    }
}

/** Save the huisstijl of one location ('' = organisation) from the form. Throws RuntimeException. */
function pd_branding_save(PDO $db, string $location, array $post, array $files, string $appRoot, int $userId): void
{
    $rows = pd_branding_rows($db);
    $old = $rows[$location] ?? null;

    $logo = $old['logo_path'] ?? null;
    if (!empty($post['remove_logo'])) $logo = null;
    if (!empty($files['logo']['name'])) $logo = pd_branding_store_logo($files['logo'], $appRoot);

    $useColors = !empty($post['use_colors']) ? 1 : 0;
    $color = function (string $key, string $fallback) use ($post): string {
        return pd_color_valid($post[$key] ?? '') ?? $fallback;
    };
    $font = (string)($post['font'] ?? '');
    $values = [
        'title'         => ($t = mb_substr(trim((string)($post['title'] ?? '')), 0, 80)) !== '' ? $t : null,
        'logo_path'     => $logo,
        'logo_mode'     => isset(PD_BRAND_LOGO_MODES[$post['logo_mode'] ?? '']) ? $post['logo_mode'] : 'logo_title',
        'logo_plate'    => !empty($post['logo_plate']) ? 1 : 0,
        'use_colors'    => $useColors,
        'color_primary' => $color('color_primary', PD_BRAND_DEFAULT_PRIMARY),
        'color_accent'  => $color('color_accent', PD_BRAND_DEFAULT_ACCENT),
        'color_bg1'     => $color('color_bg1', '#0b1f3a'),
        'color_bg2'     => $color('color_bg2', '#143a6b'),
        'font'          => isset(PD_BRAND_FONTS[$font]) ? $font : null,
        'updated_by'    => $userId ?: null,
    ];
    if ($old) {
        $set = implode(', ', array_map(fn($k) => "`$k` = ?", array_keys($values)));
        $db->prepare("UPDATE infoscreen_branding SET $set WHERE id = ?")->execute([...array_values($values), $old['id']]);
    } else {
        $cols = implode(', ', array_map(fn($k) => "`$k`", array_keys($values)));
        $db->prepare("INSERT INTO infoscreen_branding (location_name, $cols) VALUES (?" . str_repeat(', ?', count($values)) . ")")
           ->execute([$location, ...array_values($values)]);
    }
    if ($old && $old['logo_path'] && $old['logo_path'] !== $logo) pd_branding_delete_logo($db, $old['logo_path'], $appRoot);

    // Keep the old setting in step, for screens that still run an older page
    if ($location === '') {
        $db->prepare("INSERT INTO infoscreen_settings (setting_key, setting_value) VALUES ('title', ?)
                      ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)")->execute([(string)$values['title']]);
    }
}

/** Remove the own huisstijl of a location: it follows the organisation again. */
function pd_branding_reset(PDO $db, string $location, string $appRoot): void
{
    $rows = pd_branding_rows($db);
    if (!isset($rows[$location])) return;
    $db->prepare("DELETE FROM infoscreen_branding WHERE id = ?")->execute([$rows[$location]['id']]);
    pd_branding_delete_logo($db, $rows[$location]['logo_path'], $appRoot);
}

// ── Form ─────────────────────────────────────────────────────────────────────

/**
 * The huisstijl form with live preview and readability check. $hidden: extra hidden inputs
 * (action, csrf, location). $assetBase: prefix for the logo URL ('../' from /admin/).
 * $org: the resolved huisstijl of the organisation, to show what an empty field follows.
 */
function pd_branding_form(array $row, string $hidden, string $assetBase, array $org, string $previewUrl, string $resetHtml = ''): string
{
    $h = fn($v) => htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
    $isOrg = $row['location_name'] === '';
    $c = fn(string $k, string $d) => pd_color_valid($row[$k] ?? '') ?? $d;
    $orgColors = $org['colors'];
    $p = $c('color_primary', $org['primary']['bg']);
    $a = $c('color_accent', $org['accent']['bg']);
    $b1 = $c('color_bg1', $orgColors['bg1'] ?? '#0b1f3a');
    $b2 = $c('color_bg2', $orgColors['bg2'] ?? '#143a6b');
    $fonts = '';
    if (!$isOrg) {
        $fonts .= '<option value="">Zoals de organisatie</option>';
    }
    foreach (PD_BRAND_FONTS as $key => [$label, $stack]) {
        $sel = ($row['font'] ?? '') === $key || ($isOrg && !$row['font'] && $key === 'standaard') ? ' selected' : '';
        $fonts .= '<option value="' . $key . '" data-stack="' . $h($stack) . '"' . $sel . '>' . $h($label) . '</option>';
    }
    $modes = '';
    foreach (PD_BRAND_LOGO_MODES as $key => $label) {
        $modes .= '<option value="' . $key . '"' . (($row['logo_mode'] ?? 'logo_title') === $key ? ' selected' : '') . '>' . $h($label) . '</option>';
    }
    $orgLogo = !$isOrg && $org['logo'] ? $org['logo'] : '';
    $logo = $row['logo_path'] ? $assetBase . $row['logo_path'] : '';
    $titlePh = $isOrg ? 'Bijv. Welkom bij Stichting De Linde' : ($org['title'] !== '' ? 'Leeg = ' . $org['title'] : 'Bijv. Welkom op OBS De Linde');
    $useColorsLabel = $isOrg ? 'Eigen kleuren gebruiken (anders de kleurstelling bij Weergave)' : 'Eigen kleuren gebruiken (anders de kleuren van de organisatie)';
    $checked = fn($v) => (int)$v ? ' checked' : '';

    return '<form method="post" enctype="multipart/form-data" class="brand-form" data-brand-form
        data-org-primary="' . $h($org['primary']['bg']) . '" data-org-accent="' . $h($org['accent']['bg']) . '"
        data-org-bg1="' . $h($orgColors['bg1'] ?? '') . '" data-org-bg2="' . $h($orgColors['bg2'] ?? '') . '"
        data-org-title="' . $h($org['title']) . '" data-org-logo="' . $h($orgLogo) . '" data-org-font="' . $h($org['font']) . '">
    ' . $hidden . '
    <div class="brand-grid">
      <div class="brand-fields">
        <label class="f" for="b-title">Titel bovenaan</label>
        <input type="text" id="b-title" name="title" maxlength="80" value="' . $h($row['title']) . '" placeholder="' . $h($titlePh) . '">
        <div class="help">' . ($isOrg ? 'Leeg = geen titel, alleen datum en tijd.' : 'Leeg = de titel van de organisatie.') . '</div>

        <label class="f" for="b-logo" style="margin-top:14px">Logo</label>
        ' . ($logo ? '<div class="current-img"><img src="' . $h($logo) . '" alt="" data-current-logo><label class="check"><input type="checkbox" name="remove_logo" value="1"> Logo verwijderen</label></div>' : '') . '
        <input type="file" id="b-logo" name="logo" accept="image/png,image/jpeg,image/gif,image/webp">
        <div class="help">PNG (met doorzichtige achtergrond) werkt het best, maximaal 2 MB. Een SVG-logo eerst opslaan als PNG.' . (!$isOrg ? ' Geen logo = het logo van de organisatie.' : '') . '</div>
        <div class="row2">
          <div><label class="f" for="b-mode">Bovenbalk</label><select id="b-mode" name="logo_mode">' . $modes . '</select></div>
          <div><label class="check" style="margin-top:28px"><input type="checkbox" name="logo_plate" value="1"' . $checked($row['logo_plate'] ?? 0) . '> Logo op een wit vlak</label></div>
        </div>
        <div class="help">Een wit vlak houdt een donker logo zichtbaar op een donkere achtergrond.</div>

        <label class="f" for="b-font" style="margin-top:14px">Lettertype</label>
        <select id="b-font" name="font">' . $fonts . '</select>
        <div class="help">Lettertypes die op (vrijwel) elke computer staan; er wordt niets van internet geladen. Op een Chromebook of Android-scherm verschijnt een gelijkend lettertype.</div>

        <label class="check" style="margin-top:16px"><input type="checkbox" name="use_colors" value="1" data-use-colors' . $checked($row['use_colors'] ?? 0) . '> ' . $h($useColorsLabel) . '</label>
        <div class="brand-colors" data-colors>
          <label><input type="color" name="color_primary" value="' . $h($p) . '"><span><b>Hoofdkleur</b><small>Berichten in de huisstijl, nieuwsbalk, kopjes</small></span></label>
          <label><input type="color" name="color_accent" value="' . $h($a) . '"><span><b>Accentkleur</b><small>Tweede berichtkleur, bijvoorbeeld voor belangrijke berichten</small></span></label>
          <label><input type="color" name="color_bg1" value="' . $h($b1) . '"><span><b>Achtergrond</b><small>Begin van het kleurverloop</small></span></label>
          <label><input type="color" name="color_bg2" value="' . $h($b2) . '"><span><b>Achtergrond 2</b><small>Eind van het kleurverloop (zelfde kleur = effen)</small></span></label>
        </div>
        <div class="contrast" data-contrast></div>
      </div>
      <div class="brand-preview-wrap">
        <div class="f">Voorbeeld</div>
        <div class="brand-preview" data-preview>
          <div class="bp-top"><span class="bp-logo"><img alt=""></span><span class="bp-title"></span><span class="bp-clock">10:24</span></div>
          <div class="bp-main">
            <div class="bp-msg bp-primary"><span class="bp-badge">Huisstijl</span><b>Studiedag vrijdag</b><small>Alle groepen zijn vrij.</small></div>
            <div class="bp-side"><div class="bp-card"><i>Agenda</i><span>Teamoverleg 15:15</span></div><div class="bp-msg bp-accent"><b>Accent</b><small>Belangrijk bericht</small></div></div>
          </div>
          <div class="bp-ticker"><span class="bp-label">Nieuws</span><span>Nieuw speelplein geopend •</span></div>
        </div>
        <div class="help">Een schets: het echte scherm ziet u met de knop hieronder (na opslaan).</div>
      </div>
    </div>
    <div class="form-actions">
      <button class="btn btn-primary" type="submit">💾 Opslaan</button>
      <a class="btn btn-light" href="' . $h($previewUrl) . '" target="_blank">👁️ Bekijk op het scherm</a>
      ' . $resetHtml . '
    </div>
</form>';
}

/** Styles for the form (both the admin page and the redacteur page). */
function pd_branding_form_css(): string
{
    return '<style>
.brand-grid { display: grid; grid-template-columns: minmax(0, 1fr) minmax(0, 1.1fr); gap: 26px; align-items: start; }
@media (max-width: 900px) { .brand-grid { grid-template-columns: 1fr; } }
.brand-fields .row2 { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-top: 8px; }
.brand-colors { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; margin-top: 10px; }
.brand-colors label { display: flex; gap: 10px; align-items: center; border: 1.5px solid #e5e7eb; border-radius: 10px; padding: 8px 10px; cursor: pointer; }
.brand-colors input[type=color] { width: 44px; height: 36px; border: 0; padding: 0; background: none; cursor: pointer; flex: 0 0 auto; }
.brand-colors span { display: flex; flex-direction: column; font-size: 13px; line-height: 1.3; }
.brand-colors small { color: #6b7280; font-size: 11.5px; }
.brand-colors.off { opacity: .45; pointer-events: none; }
.contrast { margin-top: 10px; font-size: 13px; display: grid; gap: 4px; }
.contrast div { display: flex; justify-content: space-between; gap: 10px; padding: 5px 10px; border-radius: 8px; background: #f9fafb; }
.contrast .ok b { color: #047857; } .contrast .warn { background: #fffbeb; } .contrast .warn b { color: #b45309; } .contrast .bad { background: #fef2f2; } .contrast .bad b { color: #b91c1c; }
.brand-preview { aspect-ratio: 16 / 9; border-radius: 10px; overflow: hidden; display: grid; grid-template-rows: 13% 1fr 10%; gap: 2.5%; padding: 2.5%;
    font-size: clamp(9px, 1.25vw, 15px); box-shadow: 0 6px 20px rgba(0,0,0,.18); }
.bp-top { display: flex; align-items: center; gap: 3%; font-weight: 700; min-width: 0; }
.bp-logo { height: 100%; display: none; align-items: center; } .bp-logo img { max-height: 100%; max-width: 9em; object-fit: contain; }
.bp-logo.plate { background: #fff; border-radius: 6px; padding: 2px 6px; }
.bp-title { font-size: 1.15em; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.bp-clock { margin-left: auto; font-size: 1.3em; font-variant-numeric: tabular-nums; }
.bp-main { display: grid; grid-template-columns: 1.8fr 1fr; gap: 3%; min-height: 0; }
.bp-msg { border-radius: 8px; padding: 6%; display: flex; flex-direction: column; justify-content: center; gap: .3em; min-height: 0; }
.bp-msg b { font-size: 1.4em; line-height: 1.1; } .bp-side .bp-msg b { font-size: 1em; }
.bp-badge { align-self: flex-start; background: #fff; color: #b91c1c; font-size: .7em; font-weight: 800; padding: .15em .6em; border-radius: 99px; text-transform: uppercase; }
.bp-side { display: grid; grid-template-rows: 1fr 1fr; gap: 6%; min-height: 0; }
.bp-card { border-radius: 8px; padding: 6%; display: flex; flex-direction: column; gap: .3em; font-size: .9em; }
.bp-card i { font-style: normal; font-weight: 800; text-transform: uppercase; font-size: .75em; letter-spacing: .06em; }
.bp-ticker { border-radius: 8px; display: flex; align-items: center; gap: 3%; overflow: hidden; white-space: nowrap; font-weight: 600; background: rgba(0,0,0,.4); color: #fff; }
.bp-label { align-self: stretch; display: flex; align-items: center; padding: 0 3%; font-weight: 800; text-transform: uppercase; font-size: .8em; }
</style>';
}

/** Live preview and readability check (same rules as pd_color_* in PHP). */
function pd_branding_form_js(): string
{
    return <<<'JS'
<script>
(function () {
    const DARK = '#10213a';
    function lum(hex) {
        const n = parseInt(String(hex).replace('#', ''), 16);
        return [n >> 16 & 255, n >> 8 & 255, n & 255].map((c) => { c /= 255; return c <= 0.03928 ? c / 12.92 : Math.pow((c + 0.055) / 1.055, 2.4); })
            .reduce((s, c, i) => s + c * [0.2126, 0.7152, 0.0722][i], 0);
    }
    function contrast(a, b) { const x = lum(a), y = lum(b); return (Math.max(x, y) + 0.05) / (Math.min(x, y) + 0.05); }
    function textOn(bg) { return contrast('#ffffff', bg) >= contrast(DARK, bg) ? '#ffffff' : DARK; }
    function shade(hex, amt) {
        const n = parseInt(hex.replace('#', ''), 16);
        return '#' + [n >> 16 & 255, n >> 8 & 255, n & 255].map((c) => Math.round(Math.max(0, Math.min(255, c * (1 + amt))))
            .toString(16).padStart(2, '0')).join('');
    }
    document.querySelectorAll('[data-brand-form]').forEach((form) => {
        const pv = form.querySelector('[data-preview]');
        const val = (n) => (form.elements[n] ? form.elements[n].value : '');
        const fileInput = form.elements.logo;
        let uploaded = null;
        if (fileInput) fileInput.addEventListener('change', () => {
            uploaded = fileInput.files && fileInput.files[0] ? URL.createObjectURL(fileInput.files[0]) : null;
            update();
        });
        function update() {
            const own = form.querySelector('[data-use-colors]').checked;
            form.querySelector('[data-colors]').classList.toggle('off', !own);
            const d = form.dataset;
            const p = own ? val('color_primary') : d.orgPrimary;
            const a = own ? val('color_accent') : d.orgAccent;
            let b1 = own ? val('color_bg1') : (d.orgBg1 || '#0b1f3a');
            let b2 = own ? val('color_bg2') : (d.orgBg2 || '#143a6b');
            const light = (lum(b1) + lum(b2)) / 2 > 0.4;
            const text = light ? DARK : '#ffffff';
            const link = Math.min(contrast(p, b1), contrast(p, b2)) >= 3 ? p : text;
            const fontSel = form.elements.font;
            const opt = fontSel.options[fontSel.selectedIndex];
            pv.style.fontFamily = opt && opt.dataset.stack ? opt.dataset.stack : d.orgFont;
            pv.style.background = `linear-gradient(135deg, ${b1}, ${b2})`;
            pv.style.color = text;
            const panel = light ? '#ffffff' : 'rgba(255,255,255,.09)';
            pv.querySelectorAll('.bp-card').forEach((c) => { c.style.background = panel; c.querySelector('i').style.color = link; });
            const pm = pv.querySelector('.bp-primary');
            pm.style.background = `linear-gradient(135deg, ${p}, ${shade(p, -0.3)})`; pm.style.color = textOn(p);
            const am = pv.querySelector('.bp-accent');
            am.style.background = `linear-gradient(135deg, ${a}, ${shade(a, -0.3)})`; am.style.color = textOn(a);
            const lab = pv.querySelector('.bp-label');
            lab.style.background = p; lab.style.color = textOn(p);
            pv.querySelector('.bp-ticker').style.background = light ? DARK : 'rgba(0,0,0,.35)';
            // Title and logo
            const mode = val('logo_mode');
            const removeLogo = form.elements.remove_logo && form.elements.remove_logo.checked;
            const cur = form.querySelector('[data-current-logo]');
            const logoSrc = uploaded || (!removeLogo && cur ? cur.src : '') || d.orgLogo;
            const logoBox = pv.querySelector('.bp-logo');
            logoBox.style.display = logoSrc && mode !== 'title' ? 'flex' : 'none';
            logoBox.classList.toggle('plate', !!(form.elements.logo_plate && form.elements.logo_plate.checked));
            if (logoSrc) logoBox.querySelector('img').src = logoSrc;
            const title = val('title').trim() || d.orgTitle || '';
            pv.querySelector('.bp-title').textContent = mode === 'logo' && logoSrc ? '' : title;
            // Readability
            const rows = [['Tekst op de achtergrond (begin)', b1], ['Tekst op de achtergrond (eind)', b2], ['Tekst op de hoofdkleur', p], ['Tekst op de accentkleur', a]];
            let html = '';
            rows.forEach(([label, bg]) => {
                const r = contrast(textOn(bg), bg);
                const lvl = r >= 4.5 ? 'ok' : (r >= 3 ? 'warn' : 'bad');
                const word = lvl === 'ok' ? '✓ goed leesbaar' : (lvl === 'warn' ? '⚠ matig, kies donkerder of lichter' : '✗ slecht leesbaar');
                html += `<div class="${lvl}"><span>${label}</span><b>${r.toFixed(1)} : 1 ${word}</b></div>`;
            });
            if (Math.abs(lum(b1) - lum(b2)) > 0.45) html += '<div class="warn"><span>Kleurverloop van licht naar donker</span><b>⚠ tekst is op één kant slecht leesbaar</b></div>';
            form.querySelector('[data-contrast]').innerHTML = html;
        }
        form.addEventListener('input', update);
        form.addEventListener('change', update);
        update();
    });
})();
</script>
JS;
}
