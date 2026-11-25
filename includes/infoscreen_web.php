<?php
/**
 * PeopleDisplay
 * Copyright (c) 2024 Ton Labee — https://peopledisplay.nl
 *
 * Starter versie: GNU AGPL v3 (zie /LICENSE)
 * Commercieel gebruik boven Starter limieten vereist een licentie.
 */
/**
 * infoscreen_web.php
 * Upload naar: /includes/infoscreen_web.php
 *
 * Website source (2.7.0): news from a web page that has no RSS feed. Stored as an
 * infoscreen_sources row with type 'rss' and provider 'web'; the settings live in data_json:
 *   {"mode":"auto"|"feed"|"list"|"css", "feed":"<url>", "xpath":"<container xpath>",
 *    "css":{"item":"…","title":"…","link":"…","image":"…","date":"…"}}
 *
 * - feed: the page links to a feed (or is a WordPress site with /feed/) → that feed is used.
 * - list: a list of items recognised on the page, chosen by the admin in the preview.
 * - css:  CSS selectors filled in by hand (simple selectors: tag, .class, #id, [attr], > and space).
 * - auto: the best list found on the page at each fetch.
 * Only the title, a short text, an image link and the link are taken, never whole articles.
 * Pages that build their content with JavaScript give no items (no browser on the server).
 */

require_once __DIR__ . '/infoscreen.php';

const PD_IS_WEB_MAX_ITEMS = 30;
const PD_IS_WEB_SKIP = '/(^|[\s_-])(nav|navbar|menu|menubar|header|footer|cookie|cookies|breadcrumbs?|social|share|sharing|sidebar|skip|topbar|submenu|dropdown|mega-?menu|pagination|pager|language|lang-switch)([\s_-]|$)/i';

/** Load HTML into a DOMXPath, as UTF-8. */
function pd_is_web_dom(string $html): ?DOMXPath
{
    if (trim($html) === '') return null;
    // Charset from <meta>, convert to UTF-8 when needed
    if (preg_match('/<meta[^>]+charset=["\']?\s*([\w-]+)/i', substr($html, 0, 4096), $m)) {
        $cs = strtoupper($m[1]);
        if ($cs !== 'UTF-8' && $cs !== 'UTF8') {
            $conv = @mb_convert_encoding($html, 'UTF-8', $cs);
            if (is_string($conv)) $html = $conv;
        }
    }
    $doc = new DOMDocument();
    $prev = libxml_use_internal_errors(true);
    $ok = $doc->loadHTML('<?xml encoding="UTF-8">' . $html, LIBXML_NONET | LIBXML_NOWARNING | LIBXML_NOERROR | LIBXML_COMPACT);
    libxml_clear_errors();
    libxml_use_internal_errors($prev);
    if (!$ok) return null;
    $xp = new DOMXPath($doc);
    foreach (iterator_to_array($xp->query('//script|//style|//noscript|//template|//svg|//iframe')) as $n) {
        $n->parentNode->removeChild($n);
    }
    return $xp;
}

/** Make a link on the page absolute (http/https only). */
function pd_is_web_abs(?string $href, string $base): ?string
{
    $href = trim(html_entity_decode((string)$href, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    if ($href === '' || $href[0] === '#' || preg_match('/^(javascript|mailto|tel|data):/i', $href)) return null;
    if (preg_match('#^https?://#i', $href)) return pd_infoscreen_clean_url($href);
    if (str_starts_with($href, '//')) return pd_infoscreen_clean_url('https:' . $href);
    $p = parse_url($base);
    if (empty($p['scheme']) || empty($p['host'])) return null;
    $origin = $p['scheme'] . '://' . $p['host'] . (isset($p['port']) ? ':' . $p['port'] : '');
    if ($href[0] === '/') return pd_infoscreen_clean_url($origin . $href);
    if ($href[0] === '?') return pd_infoscreen_clean_url($origin . ($p['path'] ?? '/') . $href);
    $dir = preg_replace('#/[^/]*$#', '/', $p['path'] ?? '/');
    $path = $dir . $href;
    // Resolve ./ and ../
    $parts = [];
    foreach (explode('/', $path) as $seg) {
        if ($seg === '..') array_pop($parts);
        elseif ($seg !== '.') $parts[] = $seg;
    }
    return pd_infoscreen_clean_url($origin . implode('/', $parts));
}

/** The base URL of the page (<base href> wins). */
function pd_is_web_base(DOMXPath $xp, string $url): string
{
    $b = $xp->query('//base[@href]')->item(0);
    if ($b instanceof DOMElement) {
        $abs = pd_is_web_abs($b->getAttribute('href'), $url);
        if ($abs) return $abs;
    }
    return $url;
}

/** Feeds the page links to (<link rel="alternate" type="application/rss+xml">). */
function pd_is_web_feed_links(DOMXPath $xp, string $base): array
{
    $feeds = [];
    foreach ($xp->query('//link[@href]') as $l) {
        $rel = strtolower($l->getAttribute('rel'));
        $type = strtolower($l->getAttribute('type'));
        if (!str_contains($rel, 'alternate') || !preg_match('#(rss|atom|feed)\+?xml|application/xml#', $type)) continue;
        $href = pd_is_web_abs($l->getAttribute('href'), $base);
        if (!$href || preg_match('#/comments/feed/?$|/wp-json/#', $href)) continue;
        $feeds[$href] = trim($l->getAttribute('title')) ?: $href;
    }
    return $feeds;
}

function pd_is_web_text(?DOMNode $n): string
{
    if (!$n) return '';
    return trim(preg_replace('/\s+/u', ' ', $n->textContent));
}

/** Is this element (or an ancestor) a menu, header, footer, cookie bar …? */
function pd_is_web_skipped(DOMNode $n): bool
{
    for ($e = $n; $e instanceof DOMElement; $e = $e->parentNode) {
        $tag = strtolower($e->nodeName);
        if (in_array($tag, ['nav', 'header', 'footer', 'form', 'aside', 'button', 'select'], true)) return true;
        $role = strtolower($e->getAttribute('role'));
        if (in_array($role, ['navigation', 'banner', 'contentinfo', 'menu', 'menubar', 'search'], true)) return true;
        if (preg_match(PD_IS_WEB_SKIP, $e->getAttribute('class') . ' ' . $e->getAttribute('id'))) return true;
    }
    return false;
}

/** Signature of an element: tag + sorted classes (state classes left out). */
function pd_is_web_sig(DOMElement $e): string
{
    $classes = preg_split('/\s+/', trim($e->getAttribute('class'))) ?: [];
    $classes = array_values(array_filter($classes, fn($c) => $c !== '' && !preg_match('/^(active|current|first|last|odd|even|selected|is-|has-|js-|w--)|[0-9]{3,}/', $c)));
    sort($classes);
    return strtolower($e->nodeName) . ($classes ? '.' . implode('.', array_slice($classes, 0, 4)) : '');
}

function pd_is_web_class_xpath(array $classes): string
{
    return implode('', array_map(fn($c) => "[contains(concat(' ',normalize-space(@class),' '),' " . str_replace("'", '', $c) . " ')]", $classes));
}

/** XPath that finds all items of a list: the parent (by id, or tag + classes) and the item signature. */
function pd_is_web_group_xpath(DOMElement $parent, DOMElement $item): string
{
    $sigParts = explode('.', pd_is_web_sig($item));
    $itemXp = array_shift($sigParts) . pd_is_web_class_xpath($sigParts);
    if ($parent->getAttribute('id') !== '' && preg_match('/^[\w-]+$/', $parent->getAttribute('id')) && !preg_match('/[0-9]{4,}/', $parent->getAttribute('id'))) {
        return "//*[@id='" . $parent->getAttribute('id') . "']/" . $itemXp;
    }
    $pParts = explode('.', pd_is_web_sig($parent));
    $pTag = array_shift($pParts);
    return '//' . $pTag . pd_is_web_class_xpath($pParts) . '/' . $itemXp;
}

/** Date in a text: "12 november 2026", "12 nov. 2026", "12-11-2026", "2026-11-12". */
function pd_is_web_date(string $text): ?string
{
    $months = ['jan' => 1, 'feb' => 2, 'mrt' => 3, 'maa' => 3, 'mar' => 3, 'apr' => 4, 'mei' => 5, 'may' => 5, 'jun' => 6, 'jul' => 7,
               'aug' => 8, 'sep' => 9, 'okt' => 10, 'oct' => 10, 'nov' => 11, 'dec' => 12];
    if (preg_match('/\b(\d{1,2})\s+(jan|feb|mrt|maa|mar|apr|mei|may|jun|jul|aug|sep|okt|oct|nov|dec)[a-z]*\.?\s+(\d{4})\b/iu', $text, $m)) {
        [$d, $mo, $y] = [(int)$m[1], $months[strtolower(substr($m[2], 0, 3))], (int)$m[3]];
    } elseif (preg_match('/\b(\d{4})-(\d{2})-(\d{2})\b/', $text, $m)) {
        [$y, $mo, $d] = [(int)$m[1], (int)$m[2], (int)$m[3]];
    } elseif (preg_match('/\b(\d{1,2})[-\/.](\d{1,2})[-\/.](\d{4})\b/', $text, $m)) {
        [$d, $mo, $y] = [(int)$m[1], (int)$m[2], (int)$m[3]];
    } else {
        return null;
    }
    if (!checkdate($mo, $d, $y) || $y < 2000 || $y > (int)date('Y') + 1) return null;
    return sprintf('%04d-%02d-%02d 08:00:00', $y, $mo, $d);
}

/** The image of an item: lazy-load attributes, srcset, or a CSS background. */
function pd_is_web_image(DOMXPath $xp, DOMElement $el, string $base): ?string
{
    foreach ($xp->query('.//img|.//source[@srcset]', $el) as $img) {
        foreach (['data-src', 'data-lazy-src', 'data-original', 'data-srcset', 'srcset', 'src'] as $attr) {
            $v = trim($img->getAttribute($attr));
            if ($v === '' || str_starts_with($v, 'data:')) continue;
            if (str_contains($attr, 'srcset')) {
                // Widest candidate
                $best = null; $bestW = -1;
                foreach (explode(',', $v) as $cand) {
                    $bits = preg_split('/\s+/', trim($cand));
                    $w = isset($bits[1]) ? (int)$bits[1] : 0;
                    if ($bits[0] !== '' && $w > $bestW) { $best = $bits[0]; $bestW = $w; }
                }
                $v = (string)$best;
            }
            if (preg_match('/\.svg(\?|$)/i', $v)) continue;
            $w = (int)$img->getAttribute('width');
            if ($w > 0 && $w < 60) continue; // icons
            $abs = pd_is_web_abs($v, $base);
            if ($abs) return $abs;
        }
    }
    foreach ($xp->query('descendant-or-self::*[contains(@style,"background")]', $el) as $n) {
        if (preg_match('/url\(\s*[\'"]?([^\'")]+)/i', $n->getAttribute('style'), $m) && !preg_match('/\.svg(\?|$)/i', $m[1])) {
            $abs = pd_is_web_abs($m[1], $base);
            if ($abs) return $abs;
        }
    }
    return null;
}

/**
 * One item from a list element: title, link, summary, image, date.
 * $sel (CSS mode) may name relative XPaths for title/link/image/date.
 */
function pd_is_web_item(DOMXPath $xp, DOMElement $el, string $base, array $sel = []): ?array
{
    $title = '';
    $link = null;
    if (!empty($sel['title'])) {
        $t = $xp->query($sel['title'], $el)->item(0);
        $title = pd_is_web_text($t);
    }
    if ($title === '') {
        $h = $xp->query('.//h1|.//h2|.//h3|.//h4|.//h5|.//h6|.//*[contains(@class,"title") or contains(@class,"titel") or contains(@class,"heading")]', $el)->item(0);
        $title = pd_is_web_text($h);
    }
    // Links: the one around/inside the title, else the one with the longest text
    $anchors = [];
    if (strtolower($el->nodeName) === 'a') $anchors[] = $el;
    foreach ($xp->query('.//a[@href]', $el) as $a) $anchors[] = $a;
    if (!empty($sel['link'])) {
        $l = $xp->query($sel['link'], $el)->item(0);
        if ($l instanceof DOMElement) $link = pd_is_web_abs($l->getAttribute('href') ?: $l->getAttribute('data-href'), $base);
    }
    $bestA = null; $bestLen = -1;
    foreach ($anchors as $a) {
        $abs = pd_is_web_abs($a->getAttribute('href'), $base);
        if (!$abs) continue;
        $len = mb_strlen(pd_is_web_text($a));
        if ($title !== '' && str_contains(pd_is_web_text($a), $title)) { $bestA = $a; break; }
        if ($len > $bestLen) { $bestA = $a; $bestLen = $len; }
    }
    if (!$link && $bestA) $link = pd_is_web_abs($bestA->getAttribute('href'), $base);
    if ($title === '' && $bestA) $title = pd_is_web_text($bestA) ?: trim($bestA->getAttribute('title') ?: $bestA->getAttribute('aria-label'));
    $title = pd_infoscreen_plain($title, 200);
    if (mb_strlen($title) < 3 || preg_match('/^(lees meer|meer lezen|read more|bekijk|meer info|klik hier|verder lezen)\b/iu', $title)) return null;

    // Summary: first paragraph that is not the title
    $summary = '';
    foreach ($xp->query('.//p|.//*[contains(@class,"excerpt") or contains(@class,"intro") or contains(@class,"summary") or contains(@class,"description")]', $el) as $p) {
        $t = pd_is_web_text($p);
        if (mb_strlen($t) >= 20 && !str_contains($t, $title) && !preg_match('/^(lees meer|meer lezen|read more)/iu', $t)) { $summary = $t; break; }
    }
    $summary = pd_infoscreen_plain($summary, 300);

    $image = null;
    if (!empty($sel['image'])) {
        $i = $xp->query($sel['image'], $el)->item(0);
        if ($i instanceof DOMElement) $image = pd_is_web_image($xp, $i, $base);
    }
    $image = $image ?: pd_is_web_image($xp, $el, $base);

    $published = null;
    $dateNode = !empty($sel['date']) ? $xp->query($sel['date'], $el)->item(0) : $xp->query('.//time', $el)->item(0);
    if ($dateNode instanceof DOMElement && $dateNode->getAttribute('datetime') !== '') {
        $ts = strtotime($dateNode->getAttribute('datetime'));
        if ($ts) $published = date('Y-m-d H:i:s', $ts);
    }
    if (!$published) $published = pd_is_web_date($dateNode ? pd_is_web_text($dateNode) : pd_is_web_text($el));

    return [
        'key' => sha1($link ?: $title),
        'title' => $title,
        'summary' => $summary,
        'image' => $image,
        'link' => $link,
        'published' => $published,
    ];
}

/** Items of the elements an XPath finds. */
function pd_is_web_items(DOMXPath $xp, string $xpath, string $base, array $sel = []): array
{
    $nodes = @$xp->query($xpath);
    if ($nodes === false) throw new RuntimeException('De keuze voor de berichten op deze pagina klopt niet (meer). Kies opnieuw via Voorbeeld.');
    $items = [];
    foreach ($nodes as $n) {
        if (!$n instanceof DOMElement) continue;
        $it = pd_is_web_item($xp, $n, $base, $sel);
        if ($it && !isset($items[$it['key']])) $items[$it['key']] = $it;
        if (count($items) >= PD_IS_WEB_MAX_ITEMS) break;
    }
    return array_values($items);
}

/**
 * Find the lists on a page that look like news: repeated elements that each hold a link.
 * Returns [['xpath' => …, 'count' => n, 'score' => s, 'items' => [...]], …], best first.
 */
function pd_is_web_detect(DOMXPath $xp, string $base, int $maxGroups = 5): array
{
    $groups = [];
    $add = function (DOMElement $parent, DOMElement $item) use (&$groups) {
        $key = spl_object_id($parent) . '|' . pd_is_web_sig($item);
        if (!isset($groups[$key])) $groups[$key] = ['parent' => $parent, 'item' => $item];
    };
    // 1. Repeated siblings around links
    foreach ($xp->query('//body//a[@href]') as $a) {
        if (mb_strlen(pd_is_web_text($a)) < 3 && !$xp->query('.//img', $a)->length) continue;
        $cand = $a;
        for ($depth = 0; $depth < 7 && $cand->parentNode instanceof DOMElement; $depth++) {
            $parent = $cand->parentNode;
            if (in_array(strtolower($parent->nodeName), ['body', 'html'], true)) break;
            $sig = pd_is_web_sig($cand);
            $same = 0;
            foreach ($parent->childNodes as $c) {
                if ($c instanceof DOMElement && pd_is_web_sig($c) === $sig) $same++;
            }
            if ($same >= 2) { $add($parent, $cand); break; }
            $cand = $parent;
        }
    }
    // 2. <article> elements
    foreach ($xp->query('//article') as $art) {
        if ($art->parentNode instanceof DOMElement) $add($art->parentNode, $art);
    }

    $result = [];
    $seen = [];
    foreach ($groups as $g) {
        if (pd_is_web_skipped($g['parent'])) continue;
        $xpath = pd_is_web_group_xpath($g['parent'], $g['item']);
        if (isset($seen[$xpath])) continue;
        $seen[$xpath] = true;
        $items = pd_is_web_items($xp, $xpath, $base);
        $items = array_values(array_filter($items, fn($i) => $i['link']));
        $n = count($items);
        if ($n < 2) continue;
        $avgTitle = array_sum(array_map(fn($i) => mb_strlen($i['title']), $items)) / $n;
        if ($avgTitle < 8) continue; // tags, page numbers, icons
        $withImg = count(array_filter($items, fn($i) => $i['image']));
        $withDate = count(array_filter($items, fn($i) => $i['published']));
        $withText = count(array_filter($items, fn($i) => $i['summary'] !== ''));
        $hosts = array_unique(array_map(fn($i) => parse_url((string)$i['link'], PHP_URL_HOST), $items));
        $score = min($n, 12) * min($avgTitle, 60) / 10 + $withImg * 2 + $withDate * 3 + $withText * 2
               - (count($hosts) > 2 ? 15 : 0);                     // social-media icon rows, partner logos
        if ($score <= 0) continue;
        $result[] = ['xpath' => $xpath, 'count' => $n, 'score' => round($score, 1), 'items' => $items];
    }
    usort($result, fn($a, $b) => $b['score'] <=> $a['score']);
    // Drop lists that are part of a better list (same items)
    $out = [];
    foreach ($result as $r) {
        $links = array_column($r['items'], 'link');
        foreach ($out as $o) {
            if (count(array_intersect($links, array_column($o['items'], 'link'))) >= count($links) * 0.8) continue 2;
        }
        $out[] = $r;
        if (count($out) >= $maxGroups) break;
    }
    return $out;
}

/**
 * Simple CSS selector → XPath. Supports: tag, .class, #id, [attr], [attr=value], *, descendant (space)
 * and child (>), and a comma list. Relative ($relative) for title/link/image/date inside an item.
 */
function pd_is_web_css_to_xpath(string $css, bool $relative = false): ?string
{
    $css = trim($css);
    if ($css === '') return null;
    $out = [];
    foreach (explode(',', $css) as $sel) {
        $sel = trim(preg_replace('/\s*>\s*/', ' > ', trim($sel)));
        if ($sel === '') continue;
        $xp = $relative ? '.' : '';
        $axis = '//';
        foreach (preg_split('/\s+/', $sel) as $tok) {
            if ($tok === '>') { $axis = '/'; continue; }
            if (!preg_match('/^([a-z][a-z0-9-]*|\*)?((?:[.#][\w-]+|\[[\w-]+(?:[~^$*|]?=["\']?[^"\'\]]*["\']?)?\])*)$/i', $tok, $m)) return null;
            $step = ($m[1] ?? '') !== '' ? strtolower($m[1]) : '*';
            preg_match_all('/([.#])([\w-]+)|\[([\w-]+)(?:([~^$*|]?=)["\']?([^"\'\]]*)["\']?)?\]/', $m[2] ?? '', $parts, PREG_SET_ORDER);
            foreach ($parts as $p) {
                if ($p[1] === '.') $step .= "[contains(concat(' ',normalize-space(@class),' '),' {$p[2]} ')]";
                elseif ($p[1] === '#') $step .= "[@id='{$p[2]}']";
                else {
                    $attr = $p[3]; $op = $p[4] ?? ''; $val = str_replace("'", '', $p[5] ?? '');
                    if ($op === '') $step .= "[@$attr]";
                    elseif ($op === '=') $step .= "[@$attr='$val']";
                    elseif ($op === '^=') $step .= "[starts-with(@$attr,'$val')]";
                    else $step .= "[contains(@$attr,'$val')]";
                }
            }
            $xp .= $axis . $step;
            $axis = '//';
        }
        $out[] = $xp;
    }
    return $out ? implode(' | ', $out) : null;
}

/** Read the website settings of a source (data_json). */
function pd_is_web_config(array $source): array
{
    $cfg = json_decode((string)($source['data_json'] ?? ''), true);
    if (!is_array($cfg)) $cfg = [];
    $cfg['mode'] = in_array($cfg['mode'] ?? '', ['auto', 'feed', 'list', 'css'], true) ? $cfg['mode'] : 'auto';
    $cfg['css'] = is_array($cfg['css'] ?? null) ? $cfg['css'] : [];
    return $cfg;
}

/** Relative XPaths for the CSS fields of a configuration. */
function pd_is_web_css_sel(array $css): array
{
    $sel = [];
    foreach (['title', 'link', 'image', 'date'] as $f) {
        if (!empty($css[$f])) {
            $x = pd_is_web_css_to_xpath((string)$css[$f], true);
            if ($x === null) throw new RuntimeException('Deze CSS-selector wordt niet ondersteund: ' . $css[$f]);
            $sel[$f] = $x;
        }
    }
    return $sel;
}

/** Items of a page for a configuration (no network). */
function pd_is_web_extract(string $html, string $url, array $cfg): array
{
    $xp = pd_is_web_dom($html);
    if (!$xp) throw new RuntimeException('De pagina kon niet gelezen worden');
    $base = pd_is_web_base($xp, $url);
    if ($cfg['mode'] === 'css') {
        $itemXp = pd_is_web_css_to_xpath((string)($cfg['css']['item'] ?? ''));
        if (!$itemXp) throw new RuntimeException('Vul een geldige CSS-selector voor een bericht in');
        return pd_is_web_items($xp, $itemXp, $base, pd_is_web_css_sel($cfg['css']));
    }
    if ($cfg['mode'] === 'list' && !empty($cfg['xpath'])) {
        $items = pd_is_web_items($xp, (string)$cfg['xpath'], $base);
        if ($items) return $items;
        // The page changed: fall back to the best list
    }
    $groups = pd_is_web_detect($xp, $base, 1);
    return $groups ? $groups[0]['items'] : [];
}

/**
 * Look at a page for the admin preview: feeds it links to and the lists on it.
 * Returns ['feeds' => [['url','title','items'=>[...]]], 'groups' => [...], 'js_only' => bool].
 */
function pd_is_web_preview(string $url, array $cfg = []): array
{
    $url = pd_infoscreen_clean_url($url);
    if (!$url) throw new RuntimeException('Vul een geldig webadres in (begint met https://)');
    $res = pd_infoscreen_http_get($url);
    if (!$res['ok']) throw new RuntimeException($res['error']);
    $body = $res['body'];

    // The address is itself a feed
    if (preg_match('/^\s*(<\?xml[^>]*>\s*)?<(rss|feed|rdf:RDF)\b/i', $body)) {
        $items = pd_infoscreen_parse_feed($body);
        return ['feeds' => [['url' => $url, 'title' => 'Dit adres is zelf een feed', 'items' => array_slice($items, 0, 5)]], 'groups' => [], 'js_only' => false];
    }
    $xp = pd_is_web_dom($body);
    if (!$xp) throw new RuntimeException('De pagina kon niet gelezen worden');
    $base = pd_is_web_base($xp, $url);

    $feedLinks = pd_is_web_feed_links($xp, $base);
    // WordPress without a feed link still has /feed/
    if (!$feedLinks && preg_match('#/wp-content/|/wp-includes/#', $body)) {
        $p = parse_url($url);
        $feedLinks[$p['scheme'] . '://' . $p['host'] . '/feed/'] = 'WordPress-feed';
    }
    $feeds = [];
    foreach (array_slice($feedLinks, 0, 3, true) as $fu => $title) {
        $fr = pd_infoscreen_http_get($fu);
        if (!$fr['ok']) continue;
        try {
            $items = pd_infoscreen_parse_feed($fr['body']);
        } catch (\Throwable $e) {
            continue;
        }
        if ($items) $feeds[] = ['url' => $fu, 'title' => $title, 'items' => array_slice($items, 0, 5)];
    }

    $groups = [];
    $cssItems = null;
    if (($cfg['mode'] ?? '') === 'css' && !empty($cfg['css']['item'])) {
        $cssItems = pd_is_web_extract($body, $url, ['mode' => 'css', 'css' => $cfg['css']]);
    } else {
        $groups = pd_is_web_detect($xp, $base, 5);
    }
    $bodyText = pd_is_web_text($xp->query('//body')->item(0));
    return [
        'feeds' => $feeds,
        'groups' => array_map(fn($g) => ['xpath' => $g['xpath'], 'count' => $g['count'], 'items' => array_slice($g['items'], 0, 5)], $groups),
        'css_items' => $cssItems === null ? null : array_slice($cssItems, 0, 8),
        'js_only' => !$feeds && !$groups && mb_strlen($bodyText) < 300,
    ];
}

/** Fetch a website source and store its items (called from pd_infoscreen_fetch_source). */
function pd_infoscreen_fetch_web(PDO $db, array $source): array
{
    $cfg = pd_is_web_config($source);
    if ($cfg['mode'] === 'feed' && !empty($cfg['feed'])) {
        $res = pd_infoscreen_http_get((string)$cfg['feed']);
        if (!$res['ok']) throw new RuntimeException($res['error']);
        $items = pd_infoscreen_parse_feed($res['body']);
    } else {
        $res = pd_infoscreen_http_get((string)$source['url']);
        if (!$res['ok']) throw new RuntimeException($res['error']);
        $items = pd_is_web_extract($res['body'], (string)$source['url'], $cfg);
    }
    if (!$items) {
        throw new RuntimeException('Geen berichten gevonden op de pagina. Kies opnieuw via Voorbeeld, of de site bouwt de pagina met JavaScript op.');
    }
    return pd_infoscreen_store_items($db, $source, $items);
}
