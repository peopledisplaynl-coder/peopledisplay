<?php
/**
 * inuitbord.php
 * Upload naar: /inuitbord.php (root)
 *
 * IN/UIT bord weergave voor PeopleDisplay v2.0
 * Minimale kaartweergave met 2 kolommen per vak + BHV indicatie
 * Wordt geactiveerd via feature flag 'inuitBord' in users.features JSON
 */

// Zelfde patroon als index.php
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

session_start();
require_once __DIR__ . '/includes/db.php';

// Sessie check (frontend pagina - geen requireAdmin!)
if (!isset($_SESSION['user_id'])) {
    header('Location: /login.php');
    exit;
}

$userId = (int)$_SESSION['user_id'];

// Haal locaties op uit users.features JSON (locaties zijn namen, geen IDs!)
$stmt = $db->prepare("SELECT features FROM users WHERE id = ? LIMIT 1");
$stmt->execute([$userId]);
$userRow  = $stmt->fetch(PDO::FETCH_ASSOC);
$userFeat = json_decode($userRow['features'] ?? '{}', true);
$locatieNamen  = $userFeat['locations'] ?? [];

// Afdelingen zitten in user_afdelingen tabel (niet in features JSON)
$afdStmt = $db->prepare("
    SELECT a.afdeling_name
    FROM user_afdelingen ua
    JOIN afdelingen a ON ua.afdeling_id = a.id
    WHERE ua.user_id = ? AND a.active = 1
    ORDER BY a.sort_order, a.afdeling_name
");
$afdStmt->execute([$userId]);
$afdelingNamen = $afdStmt->fetchAll(PDO::FETCH_COLUMN);

$displayLocatie    = !empty($locatieNamen) ? implode(', ', $locatieNamen) : 'Alle locaties';
$displayNaam       = htmlspecialchars($_SESSION['display_name'] ?? 'Gebruiker');
$locatieNamenJson  = json_encode(array_values($locatieNamen));
$afdelingNamenJson = json_encode(array_values($afdelingNamen));
$heeftAfdelingen   = !empty($afdelingNamen);
?>
<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>IN/UIT Bord — PeopleDisplay</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@tabler/icons-webfont@latest/tabler-icons.min.css">
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        :root {
            --rood-bg:      #FFF5F5;
            --rood-kolom:   #FEE2E2;
            --rood-kaart:   #FECACA;
            --rood-rand:    #FCA5A5;
            --rood-hover:   #F87171;
            --rood-tekst:   #991B1B;
            --rood-label:   #DC2626;
            --groen-bg:     #F0FFF4;
            --groen-kolom:  #DCFCE7;
            --groen-kaart:  #BBF7D0;
            --groen-rand:   #86EFAC;
            --groen-hover:  #4ADE80;
            --groen-tekst:  #14532D;
            --groen-label:  #16A34A;
            --bhv-kleur:    #D97706;
            --border:       rgba(0,0,0,0.08);
            --tekst:        #111827;
            --tekst-sub:    #6B7280;
            --header-h:     52px;
            --footer-h:     32px;
            --shadow:       0 1px 3px rgba(0,0,0,0.08);
        }

        html, body {
            height: 100%;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: #E2E8F0;
            color: var(--tekst);
            overflow: hidden;
        }

        /* ─── HEADER ─── */
        .header {
            height: var(--header-h);
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0 18px;
            background: #1E293B;
            box-shadow: 0 2px 8px rgba(0,0,0,0.2);
            position: fixed;
            top: 0; left: 0; right: 0;
            z-index: 100;
        }
        .header-links {
            display: flex;
            align-items: center;
            gap: 12px;
        }
        .header-title {
            font-size: 15px;
            font-weight: 600;
            color: #fff;
            display: flex;
            align-items: center;
            gap: 8px;
            letter-spacing: -0.01em;
        }
        .header-title i { font-size: 17px; color: #94A3B8; }
        .badge-locatie {
            font-size: 11px;
            color: #CBD5E1;
            background: rgba(255,255,255,0.1);
            border: 1px solid rgba(255,255,255,0.15);
            border-radius: 20px;
            padding: 3px 10px;
            display: flex;
            align-items: center;
            gap: 4px;
        }
        .badge-locatie i { font-size: 10px; }
        .header-right {
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .sort-btn {
            font-size: 11px;
            color: #CBD5E1;
            background: rgba(255,255,255,0.1);
            border: 1px solid rgba(255,255,255,0.15);
            border-radius: 20px;
            padding: 4px 11px;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 5px;
            transition: background 0.15s;
            white-space: nowrap;
        }
        .sort-btn:hover { background: rgba(255,255,255,0.2); }
        .sort-btn i { font-size: 12px; }
        .sync-indicator {
            display: flex;
            align-items: center;
            gap: 5px;
            font-size: 11px;
            color: #94A3B8;
        }
        .sync-dot {
            width: 7px; height: 7px;
            border-radius: 50%;
            background: #4ADE80;
        }
        .sync-dot.pulsing { animation: pulse 2.5s ease-in-out infinite; }
        .sync-dot.updating { background: #FCD34D; animation: none; }
        @keyframes pulse { 0%,100%{opacity:1} 50%{opacity:0.3} }
        .overzicht-link {
            font-size: 11px;
            color: #CBD5E1;
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 4px;
            padding: 4px 9px;
            border-radius: 6px;
            border: 1px solid rgba(255,255,255,0.15);
            background: rgba(255,255,255,0.1);
            transition: background 0.15s;
        }
        .overzicht-link:hover { background: rgba(255,255,255,0.2); }

        /* ─── LAYOUT ─── */
        .bord-wrap {
            position: fixed;
            top: var(--header-h); /* wordt door JS overschreven */
            bottom: var(--footer-h);
            left: 0; right: 0;
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 0;
            transition: top 0.25s ease;
        }

        /* ─── KOLOM ─── */
        .kolom {
            display: flex;
            flex-direction: column;
            overflow: hidden;
        }
        .kolom.kolom-uit { background: var(--rood-bg); border-right: 3px solid #CBD5E0; }
        .kolom.kolom-in  { background: var(--groen-bg); }

        .kolom-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 10px 14px;
            flex-shrink: 0;
            border-bottom: 2px solid rgba(0,0,0,0.06);
        }
        .kolom-uit .kolom-header { background: var(--rood-kolom); }
        .kolom-in  .kolom-header { background: var(--groen-kolom); }

        .kolom-label {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 15px;
            font-weight: 700;
            letter-spacing: 0.04em;
            text-transform: uppercase;
        }
        .kolom-label.uit { color: var(--rood-label); }
        .kolom-label.in  { color: var(--groen-label); }
        .kdot { width: 10px; height: 10px; border-radius: 50%; }
        .kdot.uit { background: var(--rood-label); }
        .kdot.in  { background: var(--groen-label); }

        .kolom-count {
            font-size: 13px;
            font-weight: 600;
            padding: 3px 10px;
            border-radius: 20px;
        }
        .kolom-uit .kolom-count { background: var(--rood-kaart); color: var(--rood-tekst); border: 1px solid var(--rood-rand); }
        .kolom-in  .kolom-count { background: var(--groen-kaart); color: var(--groen-tekst); border: 1px solid var(--groen-rand); }

        /* ─── NAMEN GRID ─── */
        .kolom-body {
            flex: 1;
            overflow-y: auto;
            padding: 8px;
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 4px;
            align-content: start;
        }
        .kolom-body::-webkit-scrollbar { width: 4px; }
        .kolom-body::-webkit-scrollbar-track { background: transparent; }
        .kolom-body::-webkit-scrollbar-thumb { background: rgba(0,0,0,0.15); border-radius: 2px; }

        /* ─── KAARTJE ─── */
        .kaart {
            display: flex;
            align-items: center;
            gap: 6px;
            padding: 6px 9px;
            border-radius: 7px;
            cursor: pointer;
            border: 1.5px solid transparent;
            transition: background 0.12s, border-color 0.12s, transform 0.1s, box-shadow 0.12s;
            user-select: none;
            min-width: 0;
        }
        /* UIT kaartjes: altijd rood getint */
        .kaart {
            background: var(--rood-kaart);
            border-color: var(--rood-rand);
        }
        .kaart:hover {
            background: #FCA5A5;
            border-color: var(--rood-hover);
            box-shadow: 0 2px 6px rgba(239,68,68,0.2);
            transform: translateY(-1px);
        }
        /* IN kaartjes: altijd groen getint */
        .kaart.in-kolom {
            background: var(--groen-kaart);
            border-color: var(--groen-rand);
        }
        .kaart.in-kolom:hover {
            background: #6EE7B7;
            border-color: var(--groen-hover);
            box-shadow: 0 2px 6px rgba(34,197,94,0.2);
            transform: translateY(-1px);
        }
        .kaart:active { transform: scale(0.97) !important; box-shadow: none !important; }
        .kaart.vervaagd { opacity: 0; pointer-events: none; }

        .bhv-icon {
            font-size: 12px;
            color: var(--bhv-kleur);
            flex-shrink: 0;
            line-height: 1;
        }
        .kaart-naam {
            font-size: 14px;
            font-weight: 500;
            color: var(--tekst);
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            flex: 1;
            line-height: 1.3;
        }

        /* ─── VLIEGEND ELEMENT (animatie) ─── */
        #vliegend {
            position: fixed;
            pointer-events: none;
            z-index: 9999;
            display: none;
            align-items: center;
            gap: 6px;
            padding: 6px 9px;
            border-radius: 7px;
            border: 1.5px solid var(--rood-rand);
            background: var(--rood-kaart);
            font-size: 14px;
            font-weight: 500;
            color: var(--tekst);
            white-space: nowrap;
            box-shadow: 0 4px 16px rgba(0,0,0,0.15);
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
        }

        /* ─── FOOTER ─── */
        .footer {
            height: var(--footer-h);
            position: fixed;
            bottom: 0; left: 0; right: 0;
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0 18px;
            background: #1E293B;
            font-size: 11px;
            color: #64748B;
        }
        .footer-hint { display: flex; align-items: center; gap: 5px; }
        .footer-hint i { font-size: 12px; color: var(--bhv-kleur); }

        /* ─── LEGE STAAT ─── */
        .leeg {
            grid-column: 1 / -1;
            text-align: center;
            padding: 30px 10px;
            font-size: 13px;
            color: var(--tekst-sub);
            opacity: 0.7;
        }

        /* ─── HEADER BADGES ─── */
        .header-badges {
            display: flex;
            align-items: center;
            gap: 6px;
        }
        .hdr-badge {
            font-size: 12px;
            font-weight: 600;
            padding: 4px 10px;
            border-radius: 20px;
            display: flex;
            align-items: center;
            gap: 5px;
            white-space: nowrap;
        }
        .hdr-badge-in  { background: #DCFCE7; color: #15803D; border: 1px solid #86EFAC; }
        .hdr-badge-uit { background: #FEE2E2; color: #B91C1C; border: 1px solid #FCA5A5; }
        .hdr-badge-bhv {
            background: #FEF3C7;
            color: #92400E;
            border: 1px solid #FCD34D;
            transition: opacity 0.3s;
        }
        .hdr-badge-bhv.geen-bhv { opacity: 0.35; }

        /* ─── BHV PILL OP KAART ─── */
        .bhv-badge {
            font-size: 10px;
            font-weight: 700;
            background: #FEF3C7;
            color: #92400E;
            border: 1px solid #FCD34D;
            border-radius: 4px;
            padding: 1px 5px;
            flex-shrink: 0;
            letter-spacing: 0.02em;
            white-space: nowrap;
        }

        /* ─── LOCATIE MENU ─── */
        .loc-bar {
            position: fixed;
            top: var(--header-h);
            left: 0; right: 0;
            background: #1E293B;
            border-bottom: 2px solid #0F172A;
            display: flex;
            align-items: center;
            gap: 6px;
            padding: 6px 14px;
            overflow-x: auto;
            z-index: 90;
            transition: max-height 0.25s ease, padding 0.25s ease;
            max-height: 48px;
        }
        .loc-bar.verborgen {
            max-height: 0;
            padding-top: 0;
            padding-bottom: 0;
            overflow: hidden;
            border-bottom: none;
        }
        .loc-bar::-webkit-scrollbar { height: 3px; }
        .loc-bar::-webkit-scrollbar-thumb { background: #475569; border-radius: 2px; }

        .loc-pill {
            font-size: 11px;
            font-weight: 500;
            padding: 4px 11px;
            border-radius: 20px;
            border: 1px solid #475569;
            background: #334155;
            color: #94A3B8;
            cursor: pointer;
            white-space: nowrap;
            transition: background 0.15s, color 0.15s, border-color 0.15s;
            user-select: none;
            flex-shrink: 0;
        }
        .loc-pill.actief {
            background: #3B82F6;
            border-color: #60A5FA;
            color: #fff;
        }
        .loc-pill.alle {
            background: #475569;
            border-color: #64748B;
            color: #CBD5E1;
        }
        .loc-pill.alle.actief {
            background: #6366F1;
            border-color: #818CF8;
            color: #fff;
        }
        .loc-toggle-btn {
            font-size: 11px;
            color: #CBD5E1;
            background: rgba(255,255,255,0.08);
            border: 1px solid rgba(255,255,255,0.15);
            border-radius: 20px;
            padding: 4px 10px;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 4px;
            transition: background 0.15s;
            white-space: nowrap;
        }
        .loc-toggle-btn:hover { background: rgba(255,255,255,0.15); }
        .loc-toggle-btn i { font-size: 11px; }

        /* ─── AFDELINGEN BALK ─── */
        .afd-bar {
            position: fixed;
            left: 0; right: 0;
            background: #0F172A;
            border-bottom: 2px solid #020617;
            display: flex;
            align-items: center;
            gap: 6px;
            padding: 6px 14px;
            overflow-x: auto;
            z-index: 89;
            transition: max-height 0.25s ease, padding 0.25s ease;
            max-height: 48px;
        }
        .afd-bar.verborgen {
            max-height: 0;
            padding-top: 0;
            padding-bottom: 0;
            overflow: hidden;
            border-bottom: none;
        }
        .afd-bar::-webkit-scrollbar { height: 3px; }
        .afd-bar::-webkit-scrollbar-thumb { background: #334155; border-radius: 2px; }

        .afd-pill {
            font-size: 11px;
            font-weight: 500;
            padding: 4px 11px;
            border-radius: 20px;
            border: 1px solid #374151;
            background: #1F2937;
            color: #9CA3AF;
            cursor: pointer;
            white-space: nowrap;
            transition: background 0.15s, color 0.15s, border-color 0.15s;
            user-select: none;
            flex-shrink: 0;
        }
        .afd-pill.actief {
            background: #0E7490;
            border-color: #22D3EE;
            color: #fff;
        }
        .afd-pill.alle {
            background: #1F2937;
            border-color: #4B5563;
            color: #D1D5DB;
        }
        .afd-pill.alle.actief {
            background: #0F766E;
            border-color: #2DD4BF;
            color: #fff;
        }
        .afd-label {
            font-size: 10px;
            color: #4B5563;
            font-weight: 600;
            letter-spacing: 0.08em;
            text-transform: uppercase;
            white-space: nowrap;
            margin-right: 2px;
            flex-shrink: 0;
        }

        /* ─── RESPONSIVE ─── */
        @media (max-width: 600px) {
            .kolom-body { grid-template-columns: 1fr; }
            .kaart-naam { font-size: 13px; }
        }
    </style>
</head>
<body>

<!-- Vliegend element voor animatie -->
<div id="vliegend"></div>

<!-- Header -->
<header class="header">
    <div class="header-links">
        <span class="header-title">
            <i class="ti ti-layout-board-split"></i>
            IN / UIT bord
        </span>
        <span class="badge-locatie">
            <i class="ti ti-map-pin"></i>
            <?= htmlspecialchars($displayLocatie) ?>
        </span>
    </div>

    <!-- Status badges -->
    <div class="header-badges">
        <div class="hdr-badge hdr-badge-in">✓ <span id="bdg-in">—</span></div>
        <div class="hdr-badge hdr-badge-uit">✗ <span id="bdg-uit">—</span></div>
        <div class="hdr-badge hdr-badge-bhv geen-bhv" id="bdg-bhv-wrap">🚨 <span id="bdg-bhv">0</span></div>
    </div>
    <div class="header-right">
        <button class="sort-btn" onclick="toggleSort()" id="sort-btn" title="Sorteervolgorde wijzigen">
            <i class="ti ti-arrows-sort"></i>
            <span id="sort-label">Voornaam</span>
        </button>
        <button class="loc-toggle-btn" onclick="toggleFilters()" id="loc-toggle-btn" title="Locatie- en afdelingsfilters">
            <i class="ti ti-adjustments-horizontal"></i>
            <span>Filters</span>
        </button>
        <button class="loc-toggle-btn" onclick="window.open('/bhv-print/bhv-print.html','_blank')" title="BHV overzicht openen" style="border-color:rgba(251,191,36,0.4);color:#FCD34D;">
            <i class="ti ti-alarm"></i>
            <span>BHV</span>
        </button>
        <a href="/overzicht.php" class="overzicht-link" title="Bekijk volledig overzicht">
            <i class="ti ti-layout-list"></i> Overzicht
        </a>
        <a href="/logout.php" class="overzicht-link" title="Uitloggen" style="color:#ef4444;border-color:#fca5a5;">
            <i class="ti ti-logout"></i>
        </a>
        <button id="fs-btn" class="overzicht-link" title="Volledig scherm" onclick="toggleFullscreen()" style="cursor:pointer;background:rgba(255,255,255,0.15);border-color:rgba(255,255,255,0.25);">
            <i class="ti ti-maximize" id="fs-icon"></i>
        </button>
        <div class="sync-indicator">
            <div class="sync-dot pulsing" id="sync-dot"></div>
            <span id="sync-tekst">Live</span>
        </div>
    </div>
</header>

<!-- Locatie filter balk -->
<div class="loc-bar" id="loc-bar">
    <span class="afd-label">Locatie</span>
    <span class="loc-pill alle actief" id="loc-alle" onclick="selectAlleLocaties()">Alle</span>
    <?php foreach ($locatieNamen as $loc): ?>
    <span class="loc-pill actief" data-locatie="<?= htmlspecialchars($loc) ?>" onclick="toggleLocatie(this)">
        <?= htmlspecialchars($loc) ?>
    </span>
    <?php endforeach; ?>
</div>

<?php if ($heeftAfdelingen): ?>
<!-- Afdeling filter balk -->
<div class="afd-bar" id="afd-bar">
    <span class="afd-label">Afdeling</span>
    <span class="afd-pill alle actief" id="afd-alle" onclick="selectAlleAfdelingen()">Alle</span>
    <?php foreach ($afdelingNamen as $afd): ?>
    <span class="afd-pill actief" data-afdeling="<?= htmlspecialchars($afd) ?>" onclick="toggleAfdeling(this)">
        <?= htmlspecialchars($afd) ?>
    </span>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<!-- Bord -->
<div class="bord-wrap" id="bord-wrap">
    <div class="kolom kolom-uit">
        <div class="kolom-header">
            <span class="kolom-label uit">
                <span class="kdot uit"></span>
                UIT
            </span>
            <span class="kolom-count" id="cnt-uit">—</span>
        </div>
        <div class="kolom-body" id="lijst-uit"></div>
    </div>

    <!-- IN kolom -->
    <div class="kolom kolom-in">
        <div class="kolom-header">
            <span class="kolom-label in">
                <span class="kdot in"></span>
                IN
            </span>
            <span class="kolom-count" id="cnt-in">—</span>
        </div>
        <div class="kolom-body" id="lijst-in"></div>
    </div>
</div>

<!-- Footer -->
<footer class="footer">
    <span class="footer-hint">
        Klik op een naam om in/uit te checken
        &nbsp;·&nbsp;
        <span style="font-size:11px;">🚨 BHV</span> = BHV medewerker
    </span>
    <span id="laatste-update">Laden…</span>
</footer>

<script>
"use strict";

// ─── Configuratie ───────────────────────────────────────────────────
const API_URL        = '/admin/api/inuitbord-api.php';
const REFRESH_MS     = 30000;
const ALLE_LOCATIES  = <?= $locatieNamenJson ?>;
const ALLE_AFDELING  = <?= $afdelingNamenJson ?>;
const HEEFT_AFD      = <?= $heeftAfdelingen ? 'true' : 'false' ?>;

let medewerkers           = [];
let sortModus             = localStorage.getItem('ib_sort') || 'voornaam';
let bezig                 = false;
let refreshTimer          = null;
let filtersZichtbaar      = true;
let geselecteerdeLocaties = new Set(ALLE_LOCATIES);
let geselecteerdeAfd      = new Set(ALLE_AFDELING);

// ─── DOM shortcuts ──────────────────────────────────────────────────
const lijstUit  = document.getElementById('lijst-uit');
const lijstIn   = document.getElementById('lijst-in');
const cntUit    = document.getElementById('cnt-uit');
const cntIn     = document.getElementById('cnt-in');
const syncDot   = document.getElementById('sync-dot');
const syncTekst = document.getElementById('sync-tekst');
const sortLabel = document.getElementById('sort-label');
const vliegend  = document.getElementById('vliegend');
const updateEl  = document.getElementById('laatste-update');

// ─── Sortering ──────────────────────────────────────────────────────
function sortSleutel(m) {
    if (sortModus === 'voornaam') {
        return voornaam(m).toLowerCase();
    }
    return achternaam(m).toLowerCase();
}

function voornaam(m) {
    const n = m.naam || '';
    if (n.includes(',')) return n.split(',')[1]?.trim() || n;
    return n.split(' ')[0] || n;
}

function achternaam(m) {
    const n = m.naam || '';
    if (n.includes(',')) return n.split(',')[0]?.trim() || n;
    const parts = n.split(' ');
    return parts.length > 1 ? parts.slice(1).join(' ') : n;
}

function weergaveNaam(m) {
    if (sortModus === 'voornaam') {
        const vn = voornaam(m);
        const an = achternaam(m);
        return vn + ' ' + an;
    }
    const vn = voornaam(m);
    const an = achternaam(m);
    return an + ', ' + vn;
}

function toggleSort() {
    sortModus = sortModus === 'voornaam' ? 'achternaam' : 'voornaam';
    localStorage.setItem('ib_sort', sortModus);
    sortLabel.textContent = sortModus === 'voornaam' ? 'Voornaam' : 'Achternaam';
    render();
}

// Init sorteerlabel
sortLabel.textContent = sortModus === 'voornaam' ? 'Voornaam' : 'Achternaam';

// ─── Data ophalen ───────────────────────────────────────────────────
async function fetchData() {
    syncDot.className = 'sync-dot updating';
    syncTekst.textContent = '…';
    try {
        const resp = await fetch(API_URL, { cache: 'no-store' });
        if (!resp.ok) throw new Error('HTTP ' + resp.status);
        const data = await resp.json();
        if (data.error) throw new Error(data.error);
        medewerkers = data;
        render();
        zetTijd();
        syncDot.className = 'sync-dot pulsing';
        syncTekst.textContent = 'Live';
    } catch (e) {
        console.error('Fetch fout:', e);
        syncDot.className = 'sync-dot';
        syncDot.style.background = '#ef4444';
        syncTekst.textContent = 'Fout';
    }
}

// ─── Filter balken ───────────────────────────────────────────────────
function updateBordTop() {
    const locBar = document.getElementById('loc-bar');
    const afdBar = document.getElementById('afd-bar');
    const bord   = document.getElementById('bord-wrap');
    const headerH = 52;
    let extra = 0;
    if (locBar && !locBar.classList.contains('verborgen')) extra += locBar.offsetHeight;
    if (afdBar && !afdBar.classList.contains('verborgen')) extra += afdBar.offsetHeight;
    bord.style.top = (headerH + extra) + 'px';

    // Positioneer afd-bar direct onder loc-bar
    if (afdBar) {
        const locH = (locBar && !locBar.classList.contains('verborgen')) ? locBar.offsetHeight : 0;
        afdBar.style.top = (headerH + locH) + 'px';
    }
}

function toggleFilters() {
    filtersZichtbaar = !filtersZichtbaar;
    const locBar = document.getElementById('loc-bar');
    const afdBar = document.getElementById('afd-bar');
    if (locBar) locBar.classList.toggle('verborgen', !filtersZichtbaar);
    if (afdBar) afdBar.classList.toggle('verborgen', !filtersZichtbaar);
    setTimeout(updateBordTop, 260);
}

// Afdeling toggle functies
function toggleAfdeling(pill) {
    const afd = pill.dataset.afdeling;
    if (geselecteerdeAfd.has(afd)) {
        geselecteerdeAfd.delete(afd);
        pill.classList.remove('actief');
    } else {
        geselecteerdeAfd.add(afd);
        pill.classList.add('actief');
    }
    updateAlleAfdPill();
    render();
}

function selectAlleAfdelingen() {
    const alleActief = geselecteerdeAfd.size === ALLE_AFDELING.length;
    if (alleActief) {
        geselecteerdeAfd.clear();
        document.querySelectorAll('.afd-pill:not(.alle)').forEach(p => p.classList.remove('actief'));
    } else {
        ALLE_AFDELING.forEach(a => geselecteerdeAfd.add(a));
        document.querySelectorAll('.afd-pill:not(.alle)').forEach(p => p.classList.add('actief'));
    }
    updateAlleAfdPill();
    render();
}

function updateAlleAfdPill() {
    const btn = document.getElementById('afd-alle');
    if (!btn) return;
    const alleActief = geselecteerdeAfd.size === ALLE_AFDELING.length;
    btn.classList.toggle('actief', alleActief);
    btn.textContent = alleActief ? 'Alle' : `${geselecteerdeAfd.size}/${ALLE_AFDELING.length}`;
}

// ─── Locatie menu ───────────────────────────────────────────────────

function toggleLocatie(pill) {
    const loc = pill.dataset.locatie;
    if (geselecteerdeLocaties.has(loc)) {
        geselecteerdeLocaties.delete(loc);
        pill.classList.remove('actief');
    } else {
        geselecteerdeLocaties.add(loc);
        pill.classList.add('actief');
    }
    updateAllePill();
    render();
}

function selectAlleLocaties() {
    const alleActief = geselecteerdeLocaties.size === ALLE_LOCATIES.length;
    if (alleActief) {
        geselecteerdeLocaties.clear();
        document.querySelectorAll('.loc-pill:not(.alle)').forEach(p => p.classList.remove('actief'));
    } else {
        ALLE_LOCATIES.forEach(l => geselecteerdeLocaties.add(l));
        document.querySelectorAll('.loc-pill:not(.alle)').forEach(p => p.classList.add('actief'));
    }
    updateAllePill();
    render();
}

function updateAllePill() {
    const alleBtn = document.getElementById('loc-alle');
    if (!alleBtn) return;
    const alleActief = geselecteerdeLocaties.size === ALLE_LOCATIES.length;
    alleBtn.classList.toggle('actief', alleActief);
    alleBtn.textContent = alleActief ? 'Alle' : `${geselecteerdeLocaties.size}/${ALLE_LOCATIES.length}`;
}

// ─── Render ─────────────────────────────────────────────────────────
function render() {
    // Locatie- én afdelingsfilter toepassen
    const gefilterd = medewerkers.filter(m => {
        const locOk = geselecteerdeLocaties.size === 0 || geselecteerdeLocaties.size === ALLE_LOCATIES.length
            || geselecteerdeLocaties.has(m.locatie);
        const afdOk = !HEEFT_AFD || geselecteerdeAfd.size === 0 || geselecteerdeAfd.size === ALLE_AFDELING.length
            || geselecteerdeAfd.has(m.afdeling);
        return locOk && afdOk;
    });

    const uit = gefilterd
        .filter(m => m.status !== 'IN')
        .sort((a, b) => sortSleutel(a).localeCompare(sortSleutel(b), 'nl'));
    const inn = gefilterd
        .filter(m => m.status === 'IN')
        .sort((a, b) => sortSleutel(a).localeCompare(sortSleutel(b), 'nl'));

    cntUit.textContent = uit.length;
    cntIn.textContent  = inn.length;

    // Header badges bijwerken
    document.getElementById('bdg-in').textContent  = inn.length;
    document.getElementById('bdg-uit').textContent = uit.length;
    const bhvAanwezig = inn.filter(m => m.bhv === 'Ja').length;
    document.getElementById('bdg-bhv').textContent = bhvAanwezig;
    const bhvWrap = document.getElementById('bdg-bhv-wrap');
    if (bhvAanwezig > 0) {
        bhvWrap.classList.remove('geen-bhv');
        bhvWrap.title = bhvAanwezig + ' BHV medewerker(s) aanwezig';
    } else {
        bhvWrap.classList.add('geen-bhv');
        bhvWrap.title = 'Geen BHV aanwezig';
    }

    vul(lijstUit, uit, false);
    vul(lijstIn,  inn, true);
}

function vul(container, lijst, isIn) {
    container.innerHTML = '';
    if (lijst.length === 0) {
        const leeg = document.createElement('div');
        leeg.className = 'leeg';
        leeg.textContent = isIn ? 'Niemand ingecheckt' : 'Iedereen aanwezig';
        container.appendChild(leeg);
        return;
    }
    lijst.forEach(m => {
        const el = document.createElement('div');
        el.className = 'kaart' + (isIn ? ' in-kolom' : '');
        el.dataset.id = m.employee_id;
        el.title = isIn ? 'Klik om uit te checken' : 'Klik om in te checken';

        const bhvHtml = m.bhv === 'Ja'
            ? '<span class="bhv-badge" title="BHV medewerker">🚨 BHV</span>'
            : '';
        el.innerHTML = bhvHtml +
            '<span class="kaart-naam">' + escHtml(weergaveNaam(m)) + '</span>';

        el.addEventListener('click', () => wissel(m, el, isIn));
        container.appendChild(el);
    });
}

// ─── Status wisselen ────────────────────────────────────────────────
async function wissel(m, el, wasIn) {
    if (bezig) return;
    bezig = true;

    const nieuweStatus = wasIn ? 'OUT' : 'IN';
    const doelContainer = wasIn ? lijstUit : lijstIn;

    // Vlieg-animatie
    vliegAnimatie(el, doelContainer, m, wasIn);

    // Optimistic update
    m.status = nieuweStatus;
    el.classList.add('vervaagd');

    // API call
    try {
        const body = new FormData();
        body.append('action', 'updatestatus');
        body.append('employee_id', m.employee_id);
        body.append('status', nieuweStatus);

        const resp = await fetch(API_URL, { method: 'POST', body });
        const data = await resp.json();
        if (!data.success) throw new Error(data.error || 'Onbekende fout');
    } catch (e) {
        console.error('Status update fout:', e);
        // Rollback
        m.status = wasIn ? 'IN' : 'OUT';
    }

    // Her-render na animatie
    setTimeout(() => {
        render();
        zetTijd();
        bezig = false;
    }, 380);
}

// ─── Vlieg-animatie ─────────────────────────────────────────────────
function vliegAnimatie(bronEl, doelContainer, m, wasIn) {
    const bronRect = bronEl.getBoundingClientRect();

    // Vliegend element vullen
    const bhvHtml = m.bhv === 'Ja'
        ? '<span class="bhv-badge" style="font-size:10px;font-weight:700;background:#FEF3C7;color:#92400E;border:1px solid #FCD34D;border-radius:4px;padding:1px 5px;white-space:nowrap;">🚨 BHV</span>'
        : '';
    vliegend.innerHTML = bhvHtml +
        '<span style="font-size:12px;color:var(--tekst)">' + escHtml(weergaveNaam(m)) + '</span>';

    vliegend.style.cssText = [
        'display:flex',
        'left:' + bronRect.left + 'px',
        'top:' + bronRect.top + 'px',
        'width:' + bronRect.width + 'px',
        'transition:none',
        'opacity:1',
        'transform:scale(1)',
        wasIn
            ? 'border-color:#C0DD97;background:#EAF3DE'
            : 'border-color:#D3D1C7;background:#fff'
    ].join(';');

    // Target: bovenaan de doelcontainer
    const doelRect = doelContainer.getBoundingClientRect();
    const doelX = doelRect.left + 8;
    const doelY = doelRect.top  + 8;

    // Start animatie na één frame
    requestAnimationFrame(() => {
        requestAnimationFrame(() => {
            vliegend.style.transition = [
                'left 0.35s cubic-bezier(0.4,0,0.2,1)',
                'top 0.35s cubic-bezier(0.4,0,0.2,1)',
                'opacity 0.35s ease',
                'transform 0.35s ease'
            ].join(',');
            vliegend.style.left      = doelX + 'px';
            vliegend.style.top       = doelY + 'px';
            vliegend.style.opacity   = '0';
            vliegend.style.transform = 'scale(0.85)';
        });
    });

    // Verberg na animatie
    setTimeout(() => {
        vliegend.style.display = 'none';
        vliegend.style.transform = '';
    }, 400);
}

// ─── Helpers ────────────────────────────────────────────────────────
function zetTijd() {
    const nu = new Date();
    const hh = nu.getHours().toString().padStart(2, '0');
    const mm = nu.getMinutes().toString().padStart(2, '0');
    const ss = nu.getSeconds().toString().padStart(2, '0');
    updateEl.textContent = 'Bijgewerkt om ' + hh + ':' + mm + ':' + ss;
}

function escHtml(str) {
    return String(str)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;');
}

// ─── Auto-refresh ───────────────────────────────────────────────────
function startRefresh() {
    clearInterval(refreshTimer);
    refreshTimer = setInterval(fetchData, REFRESH_MS);
}

// ─── Fullscreen ─────────────────────────────────────────────────────
function toggleFullscreen() {
    if (!document.fullscreenElement) {
        document.documentElement.requestFullscreen().catch(() => {});
    } else {
        document.exitFullscreen();
    }
}

// Icoon wisselen bij fullscreen change
document.addEventListener('fullscreenchange', () => {
    const icon = document.getElementById('fs-icon');
    if (!icon) return;
    icon.className = document.fullscreenElement
        ? 'ti ti-minimize'
        : 'ti ti-maximize';
});

// Auto-fullscreen bij eerste klik op de pagina (zelfde als index.php)
let fullscreenEnabled = false;
document.addEventListener('click', function enableFullscreen() {
    if (!fullscreenEnabled && !document.fullscreenElement) {
        document.documentElement.requestFullscreen().catch(() => {
            console.log('Fullscreen niet toegestaan');
        });
        fullscreenEnabled = true;
    }
}, { once: true });

// ─── Start ──────────────────────────────────────────────────────────
fetchData();
startRefresh();
updateBordTop();
window.addEventListener('resize', updateBordTop);
</script>
</body>
</html>
