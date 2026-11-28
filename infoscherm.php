<?php
/**
 * PeopleDisplay
 * Copyright (c) 2024 Ton Labee — https://peopledisplay.nl
 *
 * Starter versie: GNU AGPL v3 (zie /LICENSE)
 * Commercieel gebruik boven Starter limieten vereist een licentie.
 */
/**
 * infoscherm.php
 * Upload naar: /infoscherm.php
 *
 * Infoscreen (2.4.0): rotating own messages, news slides and weather, with a weather /
 * clock sidebar and a headline ticker. Combined mode shows the Google Slides presentation
 * in the main area instead of the slides. Opened directly on a display (kiosk_login.php?
 * token=…&page=infoscherm) or inside the idle overlay of index.php (?embed=1).
 * Data comes from api/infoscreen_data.php every 5 minutes; the last answer is kept in
 * localStorage so the screen keeps running when the internet drops for a while.
 */

header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/version.php';
require_once __DIR__ . '/includes/infoscreen.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}
pd_infoscreen_ensure_schema($db);

$embed = !empty($_GET['embed']);
$forceMode = in_array($_GET['mode'] ?? '', ['infoscreen', 'combined'], true) ? $_GET['mode'] : '';
$preview = !empty($_GET['preview']);

// Preview of a specific screen account (Infoscherm › Schermen › Voorbeeld), only for managers
$asUser = null;
if ($preview && isset($_GET['as'])) {
    // Only with the right Schermen, for a screen within the own locations (2.10.0; the API checks again)
    $st = $db->prepare("SELECT id, username, display_name, features FROM users WHERE id = ?");
    $st->execute([(int)$_GET['as']]);
    $asUser = $st->fetch(PDO::FETCH_ASSOC) ?: null;
    $previewScope = pd_infoscreen_scope($db, (int)$_SESSION['user_id']);
    $asFeat = $asUser ? (json_decode((string)$asUser['features'], true) ?: []) : [];
    $asLocsP = is_array($asFeat['locations'] ?? null) ? array_values(array_filter(array_map('strval', $asFeat['locations']))) : [];
    if (!$asUser || !pd_infoscreen_scope_can($previewScope, 'screens') || !pd_infoscreen_scope_sees_screen($previewScope, $asLocsP)) {
        $asUser = null;
    }
}
// Preview of one location (2.9.0, Huisstijl): managers, and editors for their own locations
$asLoc = null;
if ($preview && !$asUser && isset($_GET['loc']) && (string)$_GET['loc'] !== '') {
    $asLoc = (string)$_GET['loc'];
}
$dataUrl = 'api/infoscreen_data.php' . ($asUser ? '?as=' . (int)$asUser['id'] : ($asLoc !== null ? '?loc=' . rawurlencode($asLoc) : ''));
$cacheKey = $preview ? 'pd_infoscreen_preview_' . ($asUser ? (int)$asUser['id'] : ($asLoc !== null ? 'loc_' . md5($asLoc) : 'self')) : 'pd_infoscreen_cache_v1';
$previewLabel = $asUser
    ? 'Voorbeeld — scherm „' . ($asUser['display_name'] ?: $asUser['username']) . '"'
    : ($asLoc !== null ? 'Voorbeeld — scherm op locatie „' . $asLoc . '"' : 'Voorbeeld — zo ziet het infoscherm eruit');
?><!DOCTYPE html>
<html lang="nl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex">
<title>Infoscherm — PeopleDisplay</title>
<link rel="icon" href="favicon.ico">
<style>
:root {
    --bg1: #0b1f3a; --bg2: #143a6b; --panel: rgba(255,255,255,.07); --panel-border: rgba(255,255,255,.12);
    --text: #ffffff; --muted: rgba(255,255,255,.72); --accent: #4ea1ff; --ticker-bg: rgba(0,0,0,.35);
    --top-h: 9vh; --ticker-h: 7vh; --gap: 1.6vmin; --radius: 1.6vmin;
    /* Huisstijl colours for messages (2.9.0), set from the data */
    --primary: #1d4ed8; --primary-dark: #14369a; --on-primary: #ffffff;
    --accent2: #dc2626; --accent2-dark: #9a1b1b; --on-accent2: #ffffff;
}
body.theme-licht {
    --bg1: #eef2f8; --bg2: #dfe7f3; --panel: #ffffff; --panel-border: rgba(16,33,58,.08);
    --text: #10213a; --muted: #4d5d75; --accent: #1f6fd6; --ticker-bg: #10213a;
}
* { box-sizing: border-box; margin: 0; padding: 0; }
html, body { width: 100%; height: 100%; overflow: hidden; }
body {
    font-family: "Segoe UI", system-ui, -apple-system, Roboto, "Helvetica Neue", Arial, sans-serif;
    background: linear-gradient(135deg, var(--bg1), var(--bg2)); color: var(--text);
    -webkit-font-smoothing: antialiased; cursor: default;
}
body.hide-cursor, body.hide-cursor * { cursor: none !important; }

#screen {
    position: absolute; inset: 0; display: grid; gap: var(--gap); padding: var(--gap);
    grid-template-columns: minmax(0, 1fr) 26vw;
    grid-template-rows: var(--top-h) 1fr var(--ticker-h);
    grid-template-areas: "top top" "stage side" "ticker ticker";
}
body.no-sidebar #screen { grid-template-columns: minmax(0, 1fr); grid-template-areas: "top" "stage" "ticker"; }
body.no-ticker #screen { grid-template-rows: var(--top-h) 1fr; }
body.no-ticker.no-sidebar #screen { grid-template-areas: "top" "stage"; }
body.no-ticker:not(.no-sidebar) #screen { grid-template-areas: "top top" "stage side"; }
body.no-sidebar #sidebar, body.no-ticker #ticker { display: none; }

/* Top bar */
#topbar { grid-area: top; display: flex; align-items: center; justify-content: space-between; gap: 2vw; padding: 0 1vw; }
#brand { display: flex; align-items: center; gap: 1.4vw; min-width: 0; height: 100%; }
#logo { display: none; height: calc(var(--top-h) * .78); max-width: 30vw; object-fit: contain; flex: 0 0 auto; }
body.has-logo #logo { display: block; }
#logo.plate { background: #fff; padding: .7vmin 1.2vmin; border-radius: 1vmin; }
body.logo-only #title { display: none; }
body.branded #ticker .label { background: var(--primary); color: var(--on-primary); }
#title { min-width: 0; font-size: calc(var(--top-h) * .42); font-weight: 700; letter-spacing: -.01em; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
#datetime { display: flex; align-items: baseline; gap: 1.4vw; white-space: nowrap; margin-left: auto; }
#date { font-size: calc(var(--top-h) * .3); color: var(--muted); }
#clock { font-size: calc(var(--top-h) * .55); font-weight: 700; font-variant-numeric: tabular-nums; }
body.no-clock #datetime { display: none; }

/* Stage */
#stage { grid-area: stage; position: relative; overflow: hidden; border-radius: var(--radius); background: rgba(0,0,0,.25); }
.slide {
    position: absolute; inset: 0; opacity: 0; transition: opacity .9s ease; display: flex; overflow: hidden;
}
.slide.active { opacity: 1; z-index: 2; }
#progress { position: absolute; left: 0; bottom: 0; height: .6vmin; width: 0; background: var(--accent); z-index: 5; opacity: .85; }
#slides-frame { position: absolute; inset: 0; width: 100%; height: 100%; border: 0; background: #000; z-index: 3; }

/* Message slide */
.msg { color: #fff; }
.msg.c-blauw  { background: linear-gradient(135deg, #1d4ed8, #0b2a6b); }
.msg.c-groen  { background: linear-gradient(135deg, #15803d, #064e2b); }
.msg.c-oranje { background: linear-gradient(135deg, #ea7a12, #9a3b06); }
.msg.c-rood   { background: linear-gradient(135deg, #dc2626, #7f1010); }
.msg.c-paars  { background: linear-gradient(135deg, #7c3aed, #3b1475); }
.msg.c-grijs  { background: linear-gradient(135deg, #475569, #1e293b); }
.msg.c-huisstijl { background: linear-gradient(135deg, var(--primary), var(--primary-dark)); color: var(--on-primary); }
.msg.c-accent    { background: linear-gradient(135deg, var(--accent2), var(--accent2-dark)); color: var(--on-accent2); }
.msg .msg-img { flex: 0 0 var(--img, 52%); background-size: cover; background-position: center; }
/* Image placement (2.9.0) */
.msg.lay-right { flex-direction: row-reverse; }
.msg.lay-top { flex-direction: column; }
.msg.lay-top .msg-text { padding: 3.5% 6%; }
.msg.lay-full .msg-img { position: absolute; inset: 0; }
.msg.lay-full .msg-text { position: absolute; left: 0; right: 0; bottom: 0; max-height: 62%; padding: 9% 5% 4%;
    background: linear-gradient(transparent, rgba(0,0,0,.82) 35%); color: #fff; }
.msg.fit-contain .msg-img { background-size: contain; background-repeat: no-repeat; background-color: rgba(0,0,0,.25); }
.msg .msg-img.contain { background-size: contain; background-repeat: no-repeat; background-color: rgba(0,0,0,.25); }
.msg .msg-text { flex: 1; display: flex; flex-direction: column; justify-content: center; justify-content: safe center; padding: 5% 5.5%;
    min-width: 0; min-height: 0; overflow: hidden; --fit: 1; }
.msg h1 { font-size: calc(clamp(24px, 5.2vmin, 90px) * var(--fit)); line-height: 1.12; font-weight: 800; letter-spacing: -.015em; margin-bottom: .45em; overflow-wrap: anywhere; }
.msg p { font-size: calc(clamp(16px, 3vmin, 48px) * var(--fit)); line-height: 1.42; opacity: .94; white-space: pre-line; overflow-wrap: anywhere; }
.msg.no-img .msg-text { align-items: center; text-align: center; padding: 6% 10%; }
.msg.no-img h1 { font-size: calc(clamp(28px, 7.5vmin, 120px) * var(--fit)); }
.msg.only-img .msg-img { flex: 1; }
.msg.only-img .msg-text { position: absolute; left: 0; right: 0; bottom: 0; padding: 3% 4%; background: linear-gradient(transparent, rgba(0,0,0,.75)); }
.msg.only-img h1 { margin: 0; }
.badge { display: inline-flex; align-items: center; gap: .4em; align-self: flex-start; background: #fff; color: #b91c1c; font-weight: 800;
    text-transform: uppercase; letter-spacing: .06em; font-size: clamp(12px, 1.9vmin, 30px); padding: .35em .8em; border-radius: 99px; margin-bottom: 1em; flex: 0 0 auto; }
.msg.no-img .badge { align-self: center; }

/* News slide */
.news { background: #000 center / cover no-repeat; color: #fff; }
.news::after { content: ""; position: absolute; inset: 0; background: linear-gradient(180deg, rgba(0,0,0,0) 35%, rgba(0,0,0,.88) 100%); }
.news .news-text { position: absolute; left: 0; right: 0; bottom: 0; padding: 4% 5%; z-index: 1; }
.news .news-meta { font-size: clamp(12px, 2vmin, 32px); font-weight: 700; text-transform: uppercase; letter-spacing: .08em; color: #ffd166; margin-bottom: .6em; }
.news h2 { font-size: clamp(22px, 5vmin, 84px); line-height: 1.12; font-weight: 800; letter-spacing: -.01em; text-shadow: 0 2px 12px rgba(0,0,0,.5); }
.news p { font-size: clamp(14px, 2.5vmin, 40px); line-height: 1.4; margin-top: .6em; opacity: .9; max-width: 85%;
    display: -webkit-box; -webkit-line-clamp: 3; -webkit-box-orient: vertical; overflow: hidden; }

/* Weather slide */
.wx { color: #fff; flex-direction: column; padding: 4% 5%; }
.wx-head { display: flex; align-items: center; gap: 4%; flex: 1; min-height: 0; }
.wx-head svg { height: 100%; max-height: 32vmin; width: auto; flex: 0 0 auto; filter: drop-shadow(0 .8vmin 1.5vmin rgba(0,0,0,.25)); }
.wx-now .place { font-size: clamp(16px, 3vmin, 48px); font-weight: 600; opacity: .85; }
.wx-now .temp { font-size: clamp(48px, 15vmin, 240px); font-weight: 800; line-height: 1; letter-spacing: -.03em; }
.wx-now .desc { font-size: clamp(18px, 3.6vmin, 58px); font-weight: 600; }
.wx-now .extra { font-size: clamp(13px, 2.2vmin, 34px); opacity: .85; margin-top: .5em; }
.wx-report { font-size: clamp(13px, 2.2vmin, 34px); line-height: 1.4; opacity: .92; margin: 2% 0; max-width: 95%;
    display: -webkit-box; -webkit-line-clamp: 3; -webkit-box-orient: vertical; overflow: hidden; }
.wx-days { display: grid; grid-template-columns: repeat(5, 1fr); gap: 1.5%; }
.wx-day { background: rgba(255,255,255,.14); border-radius: var(--radius); padding: 3% 2%; text-align: center; }
.wx-day .d { font-size: clamp(12px, 2.2vmin, 34px); font-weight: 700; }
.wx-day svg { width: 58%; height: auto; margin: 4% 0; }
.wx-day .t { font-size: clamp(13px, 2.4vmin, 38px); font-weight: 700; }
.wx-day .t span { opacity: .7; font-weight: 500; }
.wx-day .r { font-size: clamp(11px, 1.7vmin, 26px); opacity: .85; margin-top: .2em; }
.wx-src { position: absolute; right: 2.5%; bottom: 2%; font-size: clamp(10px, 1.4vmin, 20px); opacity: .7; }
.bg-clear   { background: linear-gradient(160deg, #2f8fe6, #8cc8ff); }
.bg-partly  { background: linear-gradient(160deg, #3b7cc4, #9cb9d9); }
.bg-cloudy, .bg-fog { background: linear-gradient(160deg, #5f7287, #a7b4c2); }
.bg-drizzle, .bg-rain, .bg-showers { background: linear-gradient(160deg, #34495e, #6b8299); }
.bg-thunder { background: linear-gradient(160deg, #262b45, #59607d); }
.bg-snow, .bg-sleet { background: linear-gradient(160deg, #7c93ad, #cfdbe8); }
.bg-night   { background: linear-gradient(160deg, #0c1530, #2a3a66); }

/* Welcome slide (nothing to show) */
.welcome { flex-direction: column; align-items: center; justify-content: center; text-align: center; gap: 2vmin; }
.welcome .big { font-size: clamp(48px, 18vmin, 280px); font-weight: 800; font-variant-numeric: tabular-nums; letter-spacing: -.03em; }
.welcome .sub { font-size: clamp(18px, 4vmin, 60px); color: var(--muted); }
.welcome .hint { font-size: clamp(12px, 2vmin, 28px); color: var(--muted); margin-top: 3vmin; }

/* Sidebar */
#sidebar { grid-area: side; display: flex; flex-direction: column; gap: var(--gap); min-height: 0; }
.card { background: var(--panel); border: 1px solid var(--panel-border); border-radius: var(--radius); padding: 1.8vmin 1.6vw; }
body.theme-licht .card { box-shadow: 0 .4vmin 1.6vmin rgba(16,33,58,.06); }
.card-title { font-size: clamp(11px, 1.7vmin, 26px); text-transform: uppercase; letter-spacing: .1em; color: var(--muted); font-weight: 700; margin-bottom: 1vmin; }
#wx-card .now { display: flex; align-items: center; gap: 1vw; }
#wx-card .now svg { width: 42%; height: auto; flex: 0 0 auto; }
#wx-card .temp { font-size: clamp(30px, 8.5vmin, 140px); font-weight: 800; line-height: 1; letter-spacing: -.03em; }
#wx-card .desc { font-size: clamp(13px, 2.3vmin, 36px); font-weight: 600; margin-top: .3em; }
#wx-card .extra { font-size: clamp(11px, 1.8vmin, 28px); color: var(--muted); margin-top: .8vmin; line-height: 1.5; }
#wx-card .days { margin-top: 1.4vmin; display: flex; flex-direction: column; gap: .5vmin; }
#wx-card .day { display: grid; grid-template-columns: 1fr auto auto; align-items: center; gap: .8vw; font-size: clamp(12px, 2.1vmin, 32px); }
#wx-card .day svg { width: 4.6vmin; height: 4.6vmin; }
#wx-card .day .dn { font-weight: 600; }
#wx-card .day .dt { font-weight: 700; font-variant-numeric: tabular-nums; }
#wx-card .day .dt span { color: var(--muted); font-weight: 500; }
#wx-card .src { font-size: clamp(9px, 1.3vmin, 18px); color: var(--muted); margin-top: 1.2vmin; }
/* Without the notices card (infoscreen mode) the weather card fills the sidebar, with five days */
body:not(.mode-combined) #wx-card { flex: 1; display: flex; flex-direction: column; min-height: 0; }
body:not(.mode-combined) #wx-card .days { flex: 1; justify-content: space-evenly; }
body:not(.mode-combined) #wx-card .day { font-size: clamp(12px, 2.5vmin, 38px); }
body:not(.mode-combined) #wx-card .day svg { width: 5.6vmin; height: 5.6vmin; }
#notes-card { flex: 1; min-height: 0; overflow: hidden; display: none; flex-direction: column; }
/* Agenda (2.5.0) */
#agenda-card { display: none; flex-direction: column; min-height: 0; overflow: hidden; }
body.has-agenda #agenda-card { display: flex; flex: 1; }
body.has-agenda:not(.mode-combined) #wx-card { flex: 0 0 auto; }
body.has-agenda #wx-card .days { display: none; }
body.mode-combined.has-agenda #agenda-card { flex: 0 0 auto; }
.ag-list { display: flex; flex-direction: column; gap: .4vmin; min-height: 0; overflow: hidden; }
.ag-day { font-size: clamp(10px, 1.6vmin, 24px); font-weight: 800; text-transform: uppercase; letter-spacing: .08em; color: var(--accent); margin-top: .8vmin; }
.ag-day:first-child { margin-top: 0; }
.ag-ev { display: grid; grid-template-columns: minmax(7.5vmin, auto) 1fr; gap: .8vw; align-items: baseline; font-size: clamp(12px, 2.1vmin, 32px); line-height: 1.25; }
.ag-ev .t { font-weight: 700; font-variant-numeric: tabular-nums; color: var(--muted); white-space: nowrap; }
.ag-ev .w { min-width: 0; }
.ag-ev .n { font-weight: 600; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; display: block; }
.ag-ev .p { font-size: .8em; color: var(--muted); overflow: hidden; text-overflow: ellipsis; white-space: nowrap; display: block; }
.ag-ev.now .t { color: #22c55e; }
.ag-empty { color: var(--muted); font-size: clamp(12px, 2vmin, 30px); }
.agenda-slide { flex-direction: column; padding: 4% 5%; color: #fff; background: linear-gradient(150deg, #1e3a8a, #0f2350); }
.agenda-slide h1 { font-size: clamp(26px, 6vmin, 96px); font-weight: 800; margin-bottom: 3%; letter-spacing: -.01em; }
.agenda-slide .cols { display: flex; flex-direction: column; gap: 2.2vmin; flex: 1; min-height: 0; overflow: hidden; }
.agenda-slide .col { display: grid; grid-template-columns: 30% 1fr; gap: 3%; padding-top: 2vmin; border-top: 2px solid rgba(255,255,255,.15); }
.agenda-slide .col h2 { font-size: clamp(15px, 3vmin, 48px); font-weight: 800; color: #93c5fd; line-height: 1.2; }
.agenda-slide .ev { display: grid; grid-template-columns: minmax(4.2em, auto) 1fr; gap: 1em; font-size: clamp(14px, 2.7vmin, 44px); margin-bottom: .55em; line-height: 1.25; }
.agenda-slide .ev .t { font-weight: 700; opacity: .8; font-variant-numeric: tabular-nums; }
.agenda-slide .ev .p { display: block; font-size: .78em; opacity: .75; }
.agenda-slide .ev .c { display: inline-block; font-size: .7em; opacity: .7; margin-left: .4em; }
body.mode-combined #notes-card.has-notes { display: flex; }
#notes-card .note { flex: 1; display: flex; flex-direction: column; justify-content: center; animation: fadein .8s ease; min-height: 0; }
#notes-card .note img { width: 100%; max-height: 40%; object-fit: cover; border-radius: calc(var(--radius) * .7); margin-bottom: 1.2vmin; }
#notes-card .note h3 { font-size: clamp(16px, 3vmin, 46px); line-height: 1.2; margin-bottom: .5em; }
#notes-card .note p { font-size: clamp(12px, 2vmin, 30px); line-height: 1.45; color: var(--muted); white-space: pre-line;
    display: -webkit-box; -webkit-line-clamp: 7; -webkit-box-orient: vertical; overflow: hidden; }
#notes-card .note .badge { font-size: clamp(10px, 1.5vmin, 22px); }
/* BHV aanwezig (2.8.0) */
#bhv-card { display: none; flex: 0 0 auto; }
body.has-bhv #bhv-card { display: block; }
#bhv-card .bhv-row { display: flex; align-items: center; gap: 1.2vw; }
#bhv-card .bhv-count { font-size: clamp(26px, 6.5vmin, 110px); font-weight: 800; line-height: 1; color: #22c55e; font-variant-numeric: tabular-nums; }
#bhv-card .bhv-names { font-size: clamp(12px, 2vmin, 30px); line-height: 1.35; font-weight: 600; min-width: 0;
    display: -webkit-box; -webkit-line-clamp: 3; -webkit-box-orient: vertical; overflow: hidden; }
#bhv-card .nw { white-space: nowrap; }
#bhv-card.none { background: rgba(220,38,38,.18); border-color: rgba(220,38,38,.6); }
#bhv-card.none .bhv-count { color: #ef4444; }
body.theme-licht #bhv-card.none { background: #fdecec; }
body.mode-combined #bhv-card .bhv-count { font-size: clamp(22px, 5vmin, 80px); }
body.mode-combined #bhv-card .bhv-names { -webkit-line-clamp: 2; }
/* Vandaag jarig (2.8.0) */
.bday { flex-direction: column; align-items: center; justify-content: center; text-align: center; padding: 4% 6%; color: #fff;
    background: radial-gradient(circle at 20% 15%, rgba(255,255,255,.18), transparent 40%), linear-gradient(150deg, #db2777, #7c3aed); }
.bday .conf { font-size: clamp(40px, 12vmin, 200px); line-height: 1; margin-bottom: 2vmin; }
.bday h1 { font-size: clamp(28px, 7vmin, 120px); font-weight: 800; letter-spacing: -.01em; margin-bottom: 3vmin; }
.bday .names { display: flex; flex-wrap: wrap; justify-content: center; gap: 1.6vmin 3vw; max-width: 90%;
    font-size: calc(clamp(22px, 6vmin, 100px) * var(--n, 1)); font-weight: 700; }
.bday .names span { white-space: nowrap; }
.bday .sub { margin-top: 3.5vmin; font-size: clamp(14px, 3vmin, 48px); opacity: .9; }
#ticker .item.bday-item .src { color: #f9a8d4; }
#ticker .item.bhv-item .src { color: #86efac; }
#ticker .item.bhv-item.none .src { color: #ff8a8a; }
#side-clock { display: none; text-align: center; }
body.no-weather #wx-card { display: none; }
body.no-weather #side-clock { display: block; }
#side-clock .big { font-size: clamp(30px, 9vmin, 150px); font-weight: 800; font-variant-numeric: tabular-nums; }
#side-clock .sub { color: var(--muted); font-size: clamp(12px, 2.2vmin, 34px); }
@keyframes fadein { from { opacity: 0; transform: translateY(1vmin); } to { opacity: 1; transform: none; } }

/* Ticker */
#ticker { grid-area: ticker; display: flex; align-items: stretch; overflow: hidden; border-radius: var(--radius); background: var(--ticker-bg); color: #fff; }
#ticker .label { flex: 0 0 auto; display: flex; align-items: center; padding: 0 1.4vw; background: var(--accent); font-weight: 800;
    font-size: calc(var(--ticker-h) * .34); text-transform: uppercase; letter-spacing: .06em; }
#ticker .viewport { flex: 1; position: relative; overflow: hidden; }
#ticker .track { position: absolute; top: 0; bottom: 0; left: 0; display: flex; align-items: center; white-space: nowrap; will-change: transform;
    font-size: calc(var(--ticker-h) * .4); font-weight: 600; }
#ticker .item { padding: 0 1.6vw; }
#ticker .item .src { color: #ffd166; font-weight: 700; margin-right: .5em; }
#ticker .item.important .src { color: #ff8a8a; }
#ticker .sep { opacity: .45; }

/* Status */
#status { position: fixed; left: 50%; transform: translateX(-50%); bottom: calc(var(--ticker-h) + var(--gap) * 2); z-index: 20;
    background: rgba(0,0,0,.7); color: #fff; font-size: clamp(11px, 1.6vmin, 22px); padding: .5em 1.1em; border-radius: 99px; display: none; }
#status.show { display: block; }
#preview-bar { position: fixed; top: 0; left: 50%; transform: translateX(-50%); z-index: 30; background: #fbbf24; color: #3b2a00;
    font: 600 13px/1.2 system-ui, sans-serif; padding: 6px 14px; border-radius: 0 0 8px 8px; }

/* Portrait screens */
@media (orientation: portrait) {
    #screen { grid-template-columns: minmax(0, 1fr); grid-template-rows: 7vh 1fr auto 6vh; grid-template-areas: "top" "stage" "side" "ticker"; }
    body.no-ticker #screen { grid-template-rows: 7vh 1fr auto; grid-template-areas: "top" "stage" "side"; }
    #sidebar { flex-direction: row; }
    #sidebar .card { flex: 1; }
    body #wx-card .days { display: none; }
    body:not(.mode-combined) #wx-card { flex: 1; }
    #wx-card .now svg { width: 24%; }
    #date { display: none; }
    #title { font-size: calc(var(--top-h) * .36); }
    :root { --top-h: 7vh; --ticker-h: 6vh; }
    .msg { flex-direction: column; }
    .msg .msg-img { flex: 0 0 var(--img, 45%); }
    .msg.lay-right { flex-direction: column; }
}
</style>
</head>
<body class="theme-donker<?= $embed ? ' embed' : '' ?>">
<?php if ($preview): ?><div id="preview-bar"><?= htmlspecialchars($previewLabel) ?></div><?php endif; ?>
<div id="screen">
    <header id="topbar">
        <div id="brand"><img id="logo" alt=""><div id="title"></div></div>
        <div id="datetime"><span id="date"></span><span id="clock"></span></div>
    </header>
    <main id="stage"><div id="progress"></div></main>
    <aside id="sidebar">
        <div class="card" id="wx-card"></div>
        <div class="card" id="side-clock"><div class="big" id="side-time"></div><div class="sub" id="side-date"></div></div>
        <div class="card" id="bhv-card"></div>
        <div class="card" id="agenda-card"><div class="card-title">Agenda</div><div class="ag-list" id="agenda-list"></div></div>
        <div class="card" id="notes-card"><div class="card-title">Mededelingen</div><div id="note-holder" style="flex:1;display:flex;min-height:0"></div></div>
    </aside>
    <footer id="ticker"><div class="label">Nieuws</div><div class="viewport"><div class="track" id="ticker-track"></div></div></footer>
</div>
<div id="status"></div>

<script>
(function () {
    'use strict';

    const VERSION = <?= json_encode(PD_CURRENT_VERSION) ?>;
    const EMBED = <?= json_encode($embed) ?>;
    const FORCE_MODE = <?= json_encode($forceMode) ?>;
    const DATA_URL = <?= json_encode($dataUrl) ?>;
    const CACHE_KEY = <?= json_encode($cacheKey) ?>;
    const REFRESH_MS = 5 * 60 * 1000;
    const REFRESH_BHV_MS = 2 * 60 * 1000;   // BHV presence changes more often
    const PAGE_STARTED = Date.now();

    let data = null;            // last good answer
    let playlist = [];
    let index = -1;
    let slideTimer = null;
    let currentMode = null;
    let pendingReload = false;
    let noteIndex = -1;
    let newsOffset = 0;         // which news slides this round starts with (moves on every round)
    const brokenImages = new Set();

    const $ = (id) => document.getElementById(id);
    const esc = (s) => String(s ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
    const cssUrl = (u) => 'url("' + String(u).replace(/["\\\n\r]/g, encodeURIComponent) + '")';

    // ── Weather icons (inline SVG, no external images) ───────────────────
    const SUN = '<g><circle cx="32" cy="32" r="11" fill="#FDB813"/><g stroke="#FDB813" stroke-width="3.5" stroke-linecap="round">'
        + [0, 45, 90, 135, 180, 225, 270, 315].map((a) => { const r = a * Math.PI / 180;
            return `<line x1="${(32 + Math.cos(r) * 16).toFixed(1)}" y1="${(32 + Math.sin(r) * 16).toFixed(1)}" x2="${(32 + Math.cos(r) * 22).toFixed(1)}" y2="${(32 + Math.sin(r) * 22).toFixed(1)}"/>`; }).join('')
        + '</g></g>';
    const MOON = '<path d="M38 12a20 20 0 1 0 14 30A16 16 0 0 1 38 12z" fill="#F2E9C9"/>';
    const CLOUD = (fill = '#F4F7FB', dx = 0, dy = 0, s = 1) =>
        `<path transform="translate(${dx} ${dy}) scale(${s})" d="M18 50a11 11 0 0 1-1.5-21.9A15.5 15.5 0 0 1 46.5 25 12.5 12.5 0 0 1 47 50z" fill="${fill}" stroke="rgba(0,0,0,.08)" stroke-width="1"/>`;
    const DROPS = (n = 3, color = '#4FA3F7') => '<g stroke="' + color + '" stroke-width="3.2" stroke-linecap="round">'
        + [20, 31, 42].slice(0, n).map((x) => `<line x1="${x}" y1="54" x2="${x - 3}" y2="61"/>`).join('') + '</g>';
    const FLAKES = '<g fill="#FFFFFF" stroke="#9FC3E6" stroke-width="1">' + [20, 32, 44].map((x) => `<circle cx="${x}" cy="57" r="3"/>`).join('') + '</g>';
    const BOLT = '<path d="M33 46l-7 12h6l-3 9 11-14h-6l4-7z" fill="#FFD23F" stroke="#E0A800" stroke-width="1"/>';
    const FOG = '<g stroke="#E3E8EF" stroke-width="3.2" stroke-linecap="round"><line x1="10" y1="54" x2="54" y2="54"/><line x1="16" y1="60" x2="48" y2="60"/></g>';

    function weatherIcon(cat, isDay = true) {
        const sky = isDay ? '<g transform="translate(-8 -9) scale(.85)">' + SUN + '</g>' : '<g transform="translate(-6 -8) scale(.8)">' + MOON + '</g>';
        let body;
        switch (cat) {
            case 'clear':   body = isDay ? SUN : '<g transform="translate(2 0)">' + MOON + '</g>'; break;
            case 'partly':  body = sky + CLOUD('#F4F7FB', 6, 4, .9); break;
            case 'cloudy':  body = CLOUD('#C9D3DF', -6, -6, .8) + CLOUD('#F4F7FB', 2, 2, .95); break;
            case 'fog':     body = CLOUD('#DCE3EB', 0, -6, .95) + FOG; break;
            case 'drizzle': body = CLOUD('#E6ECF3', 0, -6, .95) + DROPS(2, '#7CB9F5'); break;
            case 'rain':    body = CLOUD('#C9D3DF', 0, -6, .95) + DROPS(3); break;
            case 'showers': body = sky + CLOUD('#E6ECF3', 4, -4, .9) + DROPS(3); break;
            case 'thunder': body = CLOUD('#9AA8BA', 0, -8, .95) + BOLT; break;
            case 'snow':    body = CLOUD('#E6ECF3', 0, -6, .95) + FLAKES; break;
            case 'sleet':   body = CLOUD('#D3DCE6', 0, -6, .95) + '<g transform="translate(0 0)">' + DROPS(2) + '</g><circle cx="44" cy="57" r="3" fill="#fff" stroke="#9FC3E6"/>'; break;
            default:        body = CLOUD('#F4F7FB', 0, 0, 1);
        }
        return '<svg viewBox="0 0 64 64" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">' + body + '</svg>';
    }

    // ── Date and time ────────────────────────────────────────────────────
    const fmtTime = new Intl.DateTimeFormat('nl-NL', { hour: '2-digit', minute: '2-digit' });
    const fmtDate = new Intl.DateTimeFormat('nl-NL', { weekday: 'long', day: 'numeric', month: 'long' });
    const fmtDay = new Intl.DateTimeFormat('nl-NL', { weekday: 'short' });

    const upperFirst = (s) => s.charAt(0).toUpperCase() + s.slice(1);
    function tick() {
        const now = new Date();
        const t = fmtTime.format(now), d = upperFirst(fmtDate.format(now)); // "Maandag 23 november"
        $('clock').textContent = t; $('date').textContent = d;
        $('side-time').textContent = t; $('side-date').textContent = d;
        document.querySelectorAll('.welcome .big').forEach((el) => { el.textContent = t; });
        document.querySelectorAll('.welcome .sub').forEach((el) => { el.textContent = d; });
    }

    function ymd(d) {
        return d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0') + '-' + String(d.getDate()).padStart(2, '0');
    }
    function dayName(dateStr) {
        const today = new Date();
        const tomorrow = new Date(today.getFullYear(), today.getMonth(), today.getDate() + 1);
        if (dateStr === ymd(today)) return 'Vandaag';
        if (dateStr === ymd(tomorrow)) return 'Morgen';
        const d = new Date(dateStr + 'T12:00:00');
        return isNaN(d) ? '' : upperFirst(fmtDay.format(d).replace('.', ''));
    }

    function timeAgo(str) {
        if (!str) return '';
        const d = new Date(str.replace(' ', 'T'));
        const min = Math.round((Date.now() - d.getTime()) / 60000);
        if (isNaN(min) || min < 0) return '';
        if (min < 2) return 'zojuist';
        if (min < 60) return min + ' min geleden';
        const h = Math.round(min / 60);
        if (h < 24) return h + ' uur geleden';
        const days = Math.round(h / 24);
        return days === 1 ? 'gisteren' : days + ' dagen geleden';
    }

    // ── Slides ───────────────────────────────────────────────────────────
    function messageSlide(m) {
        const hasText = (m.body || '').trim() !== '';
        const lay = m.image && hasText ? ['lay-' + (m.layout || 'left'), 'fit-' + (m.fit || 'cover')] : [];
        const cls = ['slide', 'msg', 'c-' + (m.color || 'blauw'), m.image ? (hasText ? '' : 'only-img') : 'no-img', ...lay].join(' ');
        const badge = m.priority ? '<span class="badge">⚠ Belangrijk</span>' : '';
        const img = m.image ? `<div class="msg-img${hasText ? '' : ' contain'}" style="background-image:${esc(cssUrl(m.image))}"></div>` : '';
        const size = m.image && hasText && m.layout !== 'full' ? ` style="--img:${Math.max(30, Math.min(70, +m.size || 50))}%"` : '';
        return `<div class="${cls}"${size}>${img}<div class="msg-text">${badge}<h1>${esc(m.title)}</h1>${hasText ? `<p>${esc(m.body)}</p>` : ''}</div></div>`;
    }

    function newsSlide(n) {
        const meta = [n.source, timeAgo(n.published)].filter(Boolean).map(esc).join(' · ');
        return `<div class="slide news" style="background-image:${esc(cssUrl(n.image))}"><div class="news-text">`
            + `<div class="news-meta">${meta}</div><h2>${esc(n.title)}</h2>${n.summary ? `<p>${esc(n.summary)}</p>` : ''}</div></div>`;
    }

    function weatherExtra(c) {
        const parts = [];
        if (c.feels !== null && c.feels !== undefined) parts.push('Gevoel ' + Math.round(c.feels) + '°');
        if (c.wind_bft !== null && c.wind_bft !== undefined) parts.push('Wind ' + (c.wind_dir ? c.wind_dir + ' ' : '') + c.wind_bft + ' Bft');
        if (c.humidity !== null && c.humidity !== undefined) parts.push('Vocht ' + c.humidity + '%');
        return parts;
    }

    function weatherSlide(w) {
        const c = w.current;
        const bg = c.is_day ? 'bg-' + c.cat : 'bg-night';
        const days = (w.days || []).slice(0, 5).map((d) => `<div class="wx-day"><div class="d">${esc(dayName(d.date))}</div>${weatherIcon(d.cat, true)}`
            + `<div class="t">${d.max ?? '–'}° <span>${d.min ?? '–'}°</span></div>`
            + (d.rain_chance !== null && d.rain_chance !== undefined ? `<div class="r">💧 ${d.rain_chance}%</div>` : '') + '</div>').join('');
        const report = w.report && (w.report.summary || w.report.title) ? `<div class="wx-report">${esc(w.report.summary || w.report.title)}</div>` : '';
        return `<div class="slide wx ${bg}"><div class="wx-head">${weatherIcon(c.cat, c.is_day)}<div class="wx-now">`
            + `<div class="place">Het weer in ${esc(w.place)}</div><div class="temp">${Math.round(c.temp)}°</div><div class="desc">${esc(c.desc)}</div>`
            + `<div class="extra">${esc(weatherExtra(c).join('  ·  '))}</div></div></div>${report}<div class="wx-days">${days}</div>`
            + `<div class="wx-src">${esc(w.attribution)}</div></div>`;
    }

    function nameList(names) {
        return names.length < 2 ? (names[0] || '') : names.slice(0, -1).join(', ') + ' en ' + names[names.length - 1];
    }

    function birthdaySlide(names) {
        const scale = names.length <= 2 ? 1.25 : names.length <= 4 ? 1 : names.length <= 8 ? .75 : .55;
        return `<div class="slide bday" style="--n:${scale}"><div class="conf">🎉</div><h1>Vandaag jarig</h1>`
            + `<div class="names">${names.map((n) => `<span>🎂 ${esc(n)}</span>`).join('')}</div>`
            + `<div class="sub">Van harte gefeliciteerd!</div></div>`;
    }

    function welcomeSlide() {
        return '<div class="slide welcome"><div class="big"></div><div class="sub"></div>'
            + '<div class="hint">Er zijn op dit moment geen berichten.</div></div>';
    }

    function buildPlaylist(d) {
        const s = d.settings;
        const list = [];
        const messages = d.messages || [];
        // News slides: the feeds take turns, and every round continues where the previous one stopped
        const pool = mixSources((d.news || []).filter((n) => n.image && !brokenImages.has(n.image)));
        let newsWithImage = [];
        if (s.news_slides && s.news_slide_max > 0 && pool.length) {
            const count = Math.min(s.news_slide_max, pool.length);
            const start = newsOffset % pool.length;
            for (let i = 0; i < count; i++) newsWithImage.push(pool[(start + i) % pool.length]);
            newsOffset = start + count;
        }
        const important = messages.filter((m) => m.priority);

        // Messages, with a news slide after every message (news fills the gaps)
        const queueNews = newsWithImage.slice();
        messages.forEach((m) => {
            list.push({ type: 'msg', html: messageSlide(m), seconds: m.duration || s.slide_seconds });
            if (queueNews.length) list.push(newsItem(queueNews.shift(), s));
        });
        while (queueNews.length) list.push(newsItem(queueNews.shift(), s));

        // Weather once per round, in the middle
        if (s.weather_slide && d.weather && d.weather.current) {
            list.splice(Math.ceil(list.length / 2), 0, { type: 'wx', html: weatherSlide(d.weather), seconds: s.slide_seconds });
        }
        // Agenda once per round, after the first quarter
        if (s.agenda_slide && (d.agenda || []).length) {
            list.splice(Math.ceil(list.length / 4), 0, { type: 'agenda', html: agendaSlide(d.agenda), seconds: Math.max(s.slide_seconds, 15) });
        }
        // Birthdays: right at the start of every round
        if (s.birthday_slide && (d.birthdays || []).length) {
            list.splice(Math.min(1, list.length), 0, { type: 'bday', html: birthdaySlide(d.birthdays), seconds: Math.max(s.slide_seconds, 12) });
        }
        // Important messages come back halfway through a long round
        if (important.length && list.length > 5) {
            list.splice(Math.floor(list.length * .75), 0, ...important.map((m) => ({ type: 'msg', html: messageSlide(m), seconds: m.duration || s.slide_seconds })));
        }
        if (!list.length) list.push({ type: 'welcome', html: welcomeSlide(), seconds: 30 });
        return list;
    }

    // Round-robin over the sources: newest of feed A, newest of feed B, second of feed A, …
    function mixSources(items) {
        const bySource = new Map();
        items.forEach((n) => { if (!bySource.has(n.source)) bySource.set(n.source, []); bySource.get(n.source).push(n); });
        const lists = [...bySource.values()];
        const mixed = [];
        for (let i = 0; lists.some((l) => i < l.length); i++) lists.forEach((l) => { if (i < l.length) mixed.push(l[i]); });
        return mixed;
    }

    function newsItem(n, s) {
        return { type: 'news', html: newsSlide(n), seconds: s.slide_seconds, image: n.image };
    }

    function preload(url) {
        return new Promise((resolve) => {
            if (!url) return resolve(true);
            const img = new Image();
            const t = setTimeout(() => resolve(false), 8000);
            img.onload = () => { clearTimeout(t); resolve(true); };
            img.onerror = () => { clearTimeout(t); resolve(false); };
            img.src = url;
        });
    }

    async function nextSlide() {
        clearTimeout(slideTimer);
        if (pendingReload) { location.reload(); return; }
        if (currentMode === 'combined') return;
        if (!playlist.length) return;

        let item = null;
        for (let tries = 0; tries < playlist.length; tries++) {
            index = index + 1;
            if (index >= playlist.length && data) {
                // Round finished: build the next round, with the next news items
                playlist = buildPlaylist(data);
                index = 0;
            }
            index = index % playlist.length;
            const candidate = playlist[index];
            if (candidate.broken) continue;
            if (candidate.image && !(await preload(candidate.image))) { candidate.broken = true; brokenImages.add(candidate.image); continue; }
            item = candidate;
            break;
        }
        if (!item) item = { html: welcomeSlide(), seconds: 30 };

        const stage = $('stage');
        const wrap = document.createElement('div');
        wrap.innerHTML = item.html;
        const el = wrap.firstElementChild;
        stage.insertBefore(el, $('progress'));
        fitText(el);
        requestAnimationFrame(() => requestAnimationFrame(() => el.classList.add('active')));
        tick();
        // Remove the previous slides after the fade
        const old = [...stage.querySelectorAll('.slide')].filter((s) => s !== el);
        setTimeout(() => old.forEach((s) => s.remove()), 1000);

        const ms = Math.max(5, item.seconds || 12) * 1000;
        const bar = $('progress');
        bar.style.transition = 'none'; bar.style.width = '0';
        requestAnimationFrame(() => requestAnimationFrame(() => { bar.style.transition = `width ${ms}ms linear`; bar.style.width = '100%'; }));
        bar.style.display = playlist.length > 1 ? '' : 'none';
        slideTimer = setTimeout(nextSlide, ms);
    }

    // ── Combined mode: Google Slides in the main area, messages in the sidebar ──
    // Long messages: make the text smaller until title, badge and text fit the slide (2.6.1)
    function fitText(slide) {
        slide.querySelectorAll('.msg-text').forEach((box) => {
            if (box.closest('.only-img')) return;
            let fit = 1;
            box.style.setProperty('--fit', '1');
            while (box.scrollHeight > box.clientHeight + 1 && fit > 0.4) {
                fit = Math.round((fit - 0.05) * 100) / 100;
                box.style.setProperty('--fit', String(fit));
            }
        });
    }
    let fitTimer = null;
    window.addEventListener('resize', () => {
        clearTimeout(fitTimer);
        fitTimer = setTimeout(() => document.querySelectorAll('#stage .slide').forEach(fitText), 200);
    });
    if (document.fonts && document.fonts.ready) document.fonts.ready.then(() => document.querySelectorAll('#stage .slide').forEach(fitText));

    function showSlidesFrame(presentationId, seconds) {
        let frame = $('slides-frame');
        const src = `https://docs.google.com/presentation/d/${encodeURIComponent(presentationId)}/embed?start=true&loop=true&delayms=${Math.max(3, seconds) * 1000}&rm=minimal`;
        if (!frame) {
            frame = document.createElement('iframe');
            frame.id = 'slides-frame';
            frame.setAttribute('allowfullscreen', '');
            $('stage').appendChild(frame);
        }
        if (frame.dataset.src !== src) { frame.src = src; frame.dataset.src = src; }
        $('stage').querySelectorAll('.slide').forEach((s) => s.remove());
        $('progress').style.display = 'none';
    }

    function rotateNote() {
        const messages = (data && data.messages) || [];
        const card = $('notes-card');
        card.classList.toggle('has-notes', messages.length > 0);
        if (!messages.length) return;
        noteIndex = (noteIndex + 1) % messages.length;
        const m = messages[noteIndex];
        $('note-holder').innerHTML = `<div class="note">${m.priority ? '<span class="badge">⚠ Belangrijk</span>' : ''}`
            + (m.image ? `<img src="${esc(m.image)}" alt="">` : '') + `<h3>${esc(m.title)}</h3>${m.body ? `<p>${esc(m.body)}</p>` : ''}</div>`;
    }

    // ── Agenda ───────────────────────────────────────────────────────────
    const fmtDayLong = new Intl.DateTimeFormat('nl-NL', { weekday: 'long', day: 'numeric', month: 'long' });
    const parseLocal = (str) => new Date(String(str).replace(' ', 'T'));
    function agendaDayLabel(ymdStr) {
        const name = dayName(ymdStr);
        if (name === 'Vandaag' || name === 'Morgen') return name;
        return upperFirst(fmtDayLong.format(new Date(ymdStr + 'T12:00:00')));
    }
    function agendaTime(e) {
        if (e.all_day) return 'Hele dag';
        return fmtTime.format(parseLocal(e.start));
    }
    // Group by day; an event that started on an earlier day but is still going on shows under today
    function agendaGroups(agenda, max) {
        const today = ymd(new Date());
        const groups = [];
        agenda.slice(0, max).forEach((e) => {
            let day = String(e.start).slice(0, 10);
            if (day < today) day = today;
            let g = groups.find((x) => x.day === day);
            if (!g) { g = { day, events: [] }; groups.push(g); }
            g.events.push(e);
        });
        return groups;
    }
    function isNow(e) {
        const now = Date.now();
        return !e.all_day && parseLocal(e.start).getTime() <= now && (!e.end || parseLocal(e.end).getTime() > now);
    }
    function renderAgendaCard(d) {
        const agenda = d.agenda || [];
        const show = d.settings.agenda_sidebar && agenda.length > 0;
        document.body.classList.toggle('has-agenda', show);
        if (!show) return;
        const max = currentMode === 'combined' ? 2 : 8;
        $('agenda-list').innerHTML = agendaGroups(agenda, max).map((g) => `<div class="ag-day">${esc(agendaDayLabel(g.day))}</div>`
            + g.events.map((e) => `<div class="ag-ev${isNow(e) ? ' now' : ''}"><span class="t">${isNow(e) ? 'Nu' : esc(agendaTime(e))}</span>`
                + `<span class="w"><span class="n">${esc(e.title)}</span>${e.place ? `<span class="p">${esc(e.place)}</span>` : ''}</span></div>`).join('')).join('');
    }
    function agendaSlide(agenda) {
        const groups = agendaGroups(agenda, 9).slice(0, 3);
        return `<div class="slide agenda-slide"><h1>📅 Agenda</h1><div class="cols">`
            + groups.map((g) => `<div class="col"><h2>${esc(agendaDayLabel(g.day))}</h2>`
                + '<div>' + g.events.slice(0, 4).map((e) => `<div class="ev"><span class="t">${isNow(e) ? 'Nu' : esc(agendaTime(e))}</span><span>${esc(e.title)}`
                    + (e.calendar ? `<span class="c">· ${esc(e.calendar)}</span>` : '')
                    + (e.place ? `<span class="p">${esc(e.place)}</span>` : '') + '</span></div>').join('') + '</div></div>').join('')
            + '</div></div>';
    }

    // ── Sidebar and ticker ───────────────────────────────────────────────
    function renderWeatherCard(w) {
        document.body.classList.toggle('no-weather', !(w && w.current));
        if (!(w && w.current)) return;
        const c = w.current;
        const days = (w.days || []).slice(0, currentMode === 'combined' ? 3 : 5).map((d) => `<div class="day"><span class="dn">${esc(dayName(d.date))}</span>`
            + `${weatherIcon(d.cat, true)}<span class="dt">${d.max ?? '–'}° <span>${d.min ?? '–'}°</span></span></div>`).join('');
        $('wx-card').innerHTML = `<div class="card-title">Weer · ${esc(w.place)}</div>`
            + `<div class="now">${weatherIcon(c.cat, c.is_day)}<div><div class="temp">${Math.round(c.temp)}°</div><div class="desc">${esc(c.desc)}</div></div></div>`
            + `<div class="extra">${esc(weatherExtra(c).join(' · '))}</div><div class="days">${days}</div><div class="src">${esc(w.attribution)}</div>`;
    }

    function bhvText(b) {
        if (!b.count) return 'Geen BHV\'er ingecheckt';
        return b.names && b.names.length ? b.names.join(', ') + (b.count > b.names.length ? ' …' : '') : (b.count === 1 ? 'BHV\'er ingecheckt' : 'BHV\'ers ingecheckt');
    }
    function bhvHtml(b) {
        if (!b.count || !(b.names && b.names.length)) return esc(bhvText(b));
        return b.names.map((n) => `<span class="nw">${esc(n)}</span>`).join(', ') + (b.count > b.names.length ? ' …' : '');
    }
    function renderBhvCard(d) {
        const b = d.bhv;
        const show = !!(d.settings.bhv_card && b);
        document.body.classList.toggle('has-bhv', show);
        if (!show) return;
        const card = $('bhv-card');
        card.classList.toggle('none', !b.count);
        card.innerHTML = '<div class="card-title">BHV aanwezig</div>'
            + `<div class="bhv-row"><div class="bhv-count">${b.count}</div><div class="bhv-names">${bhvHtml(b)}</div></div>`;
    }

    let tickerAnim = null;
    function renderTicker(d) {
        const items = [];
        (d.messages || []).filter((m) => m.priority).forEach((m) => items.push(`<span class="item important"><span class="src">Belangrijk</span>${esc(m.title)}</span>`));
        if (d.settings.birthday_slide && (d.birthdays || []).length) {
            items.push(`<span class="item bday-item"><span class="src">🎂 Vandaag jarig</span>${esc(nameList(d.birthdays))}</span>`);
        }
        // Without a sidebar the BHV block is not visible: show it in the ticker instead
        const sidebarHidden = currentMode !== 'combined' && !d.settings.sidebar;
        if (d.settings.bhv_card && d.bhv && sidebarHidden) {
            items.push(`<span class="item bhv-item${d.bhv.count ? '' : ' none'}"><span class="src">BHV aanwezig: ${d.bhv.count}</span>${esc(bhvText(d.bhv))}</span>`);
        }
        mixSources(d.news || []).slice(0, 30).forEach((n) => items.push(`<span class="item"><span class="src">${esc(n.source)}</span>${esc(n.title)}</span>`));
        const show = d.settings.ticker && items.length > 0;
        document.body.classList.toggle('no-ticker', !show);
        if (!show) return;
        const html = items.join('<span class="sep">•</span>') + '<span class="sep">•</span>';
        const track = $('ticker-track');
        if (track.dataset.html === html) return;
        track.dataset.html = html;
        track.innerHTML = html + html; // twice, for a seamless loop
        requestAnimationFrame(() => {
            const half = track.scrollWidth / 2;
            const speed = Math.max(20, d.settings.ticker_speed || 60) * (window.innerWidth / 1920);
            if (tickerAnim) tickerAnim.cancel();
            tickerAnim = track.animate([{ transform: 'translateX(0)' }, { transform: `translateX(-${half}px)` }],
                { duration: (half / speed) * 1000, iterations: Infinity });
        });
    }

    // ── Applying data ────────────────────────────────────────────────────
    function apply(d, fromCache) {
        data = d;
        const s = d.settings;
        const mode = FORCE_MODE || (d.screen_mode === 'combined' && d.presentation_id ? 'combined' : 'infoscreen');
        const b = d.branding || null;
        const brandColors = b && b.colors;
        const theme = brandColors ? (brandColors.light ? 'licht' : 'donker') : (s.theme || 'donker');
        const hasLogo = !!(b && b.logo && b.logo_mode !== 'title');
        document.body.className = [
            'theme-' + theme, EMBED ? 'embed' : '', 'mode-' + mode, brandColors ? 'branded' : '',
            hasLogo ? 'has-logo' : '', hasLogo && b.logo_mode === 'logo' ? 'logo-only' : '',
            s.sidebar || mode === 'combined' ? '' : 'no-sidebar', s.show_clock ? '' : 'no-clock',
            document.body.classList.contains('hide-cursor') ? 'hide-cursor' : '',
        ].filter(Boolean).join(' ');
        const modeChanged = mode !== currentMode;
        currentMode = mode;
        $('title').textContent = s.title || '';
        applyBranding(b, hasLogo);
        renderWeatherCard(d.weather);
        renderAgendaCard(d);
        renderBhvCard(d);
        renderTicker(d);

        if (mode === 'combined') {
            clearTimeout(slideTimer);
            showSlidesFrame(d.presentation_id, s.slide_seconds);
            if (modeChanged || noteIndex === -1) rotateNote();
        } else {
            const frame = $('slides-frame');
            if (frame) frame.remove();
            playlist = buildPlaylist(d);
            if (modeChanged || index === -1) { index = -1; nextSlide(); }
            else if (index >= playlist.length) index = -1;
        }
        // A new PeopleDisplay version or a page open for more than a day: reload at the next slide
        if (!fromCache && (d.version !== VERSION || Date.now() - PAGE_STARTED > 24 * 3600 * 1000)) {
            pendingReload = true;
            if (mode === 'combined') location.reload();
        }
    }

    // Huisstijl (2.9.0): colours, font and logo
    function applyBranding(b, hasLogo) {
        const st = document.body.style;
        const vars = {};
        if (b) {
            vars['--primary'] = b.primary.bg; vars['--primary-dark'] = b.primary.dark; vars['--on-primary'] = b.primary.text;
            vars['--accent2'] = b.accent.bg; vars['--accent2-dark'] = b.accent.dark; vars['--on-accent2'] = b.accent.text;
            if (b.colors) { vars['--bg1'] = b.colors.bg1; vars['--bg2'] = b.colors.bg2; vars['--accent'] = b.colors.link; }
        }
        ['--primary', '--primary-dark', '--on-primary', '--accent2', '--accent2-dark', '--on-accent2', '--bg1', '--bg2', '--accent']
            .forEach((k) => { if (vars[k]) st.setProperty(k, vars[k]); else st.removeProperty(k); });
        st.fontFamily = b && b.font ? b.font : '';
        const logo = $('logo');
        if (hasLogo) {
            if (logo.getAttribute('src') !== b.logo) logo.src = b.logo;
            logo.classList.toggle('plate', !!b.logo_plate);
        } else {
            logo.removeAttribute('src');
        }
    }

    function setStatus(text) {
        const el = $('status');
        el.textContent = text || '';
        el.classList.toggle('show', !!text);
    }

    async function load() {
        try {
            const r = await fetch(DATA_URL, { credentials: 'same-origin', cache: 'no-store' });
            if (r.status === 401) {
                (EMBED ? window.top : window).location.href = 'login.php';
                return;
            }
            const d = await r.json();
            if (!r.ok || !d.success) throw new Error(d.error || 'HTTP ' + r.status);
            try { localStorage.setItem(CACHE_KEY, JSON.stringify({ saved: Date.now(), data: d })); } catch (e) { /* storage full or blocked */ }
            setStatus('');
            apply(d, false);
        } catch (e) {
            let cached = null;
            try { cached = JSON.parse(localStorage.getItem(CACHE_KEY) || 'null'); } catch (err) { cached = null; }
            if (!data && cached && cached.data) apply(cached.data, true);
            const since = cached && cached.saved ? ' — laatst bijgewerkt ' + fmtTime.format(new Date(cached.saved)) : '';
            setStatus(data ? 'Geen verbinding' + since : 'Geen verbinding met de server');
            if (!data) { playlist = [{ html: welcomeSlide(), seconds: 30 }]; currentMode = 'infoscreen'; nextSlide(); }
        }
    }

    // ── Screen behaviour ─────────────────────────────────────────────────
    // In the idle overlay of index.php: report touches to the page underneath, which closes the overlay.
    if (EMBED && window.parent !== window) {
        ['pointerdown', 'touchstart', 'keydown', 'wheel'].forEach((ev) =>
            document.addEventListener(ev, () => window.parent.postMessage({ type: 'pd-infoscreen-activity' }, location.origin), { passive: true }));
    }
    // Hide the mouse pointer when it doesn't move
    let cursorTimer = null;
    document.addEventListener('mousemove', () => {
        document.body.classList.remove('hide-cursor');
        clearTimeout(cursorTimer);
        cursorTimer = setTimeout(() => document.body.classList.add('hide-cursor'), 3000);
    });
    // Keep the display awake where the browser allows it
    async function wakeLock() {
        try { if ('wakeLock' in navigator && document.visibilityState === 'visible') await navigator.wakeLock.request('screen'); } catch (e) { /* not allowed */ }
    }
    document.addEventListener('visibilitychange', wakeLock);
    if (!EMBED) document.addEventListener('dblclick', () => {
        if (document.fullscreenElement) document.exitFullscreen().catch(() => {});
        else document.documentElement.requestFullscreen().catch(() => {});
    });

    tick();
    setInterval(tick, 1000);
    setInterval(() => { if (currentMode === 'combined') rotateNote(); }, 15000);
    setInterval(() => { if (data) renderAgendaCard(data); }, 60000); // "Nu" and passing appointments
    // Refresh: every 5 minutes, every 2 minutes when the BHV block is on
    function scheduleLoad() {
        setTimeout(async () => { await load(); scheduleLoad(); }, data && data.bhv ? REFRESH_BHV_MS : REFRESH_MS);
    }
    wakeLock();
    load().then(scheduleLoad);
})();
</script>
</body>
</html>
