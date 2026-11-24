<?php
/**
 * PeopleDisplay
 * Copyright (c) 2024 Ton Labee — https://peopledisplay.nl
 *
 * Starter versie: GNU AGPL v3 (zie /LICENSE)
 * Commercieel gebruik boven Starter limieten vereist een licentie.
 */
/**
 * infoscreen_ical.php
 * Upload naar: /includes/infoscreen_ical.php
 *
 * Agenda for the infoscreen (2.5.0): reads an iCal (.ics) calendar — the "publish
 * calendar" link of Outlook / Microsoft 365, the secret iCal address of Google Calendar,
 * or any other .ics — and stores the appointments of the coming 60 days, with repeating
 * appointments expanded (RRULE: daily, weekly, monthly, yearly; INTERVAL, COUNT, UNTIL,
 * BYDAY, BYMONTHDAY, BYMONTH), exceptions (EXDATE) and moved or cancelled occurrences
 * (RECURRENCE-ID). Private appointments are left out.
 */

if (defined('PD_INFOSCREEN_ICAL_LOADED')) {
    return;
}
define('PD_INFOSCREEN_ICAL_LOADED', true);

const PD_IS_TZ = 'Europe/Amsterdam';
const PD_IS_AGENDA_DAYS_STORED = 60;

/** Windows time zone names (Outlook) → IANA. */
const PD_IS_WINDOWS_TZ = [
    'W. Europe Standard Time'      => 'Europe/Amsterdam',
    'Romance Standard Time'        => 'Europe/Paris',
    'Central Europe Standard Time' => 'Europe/Budapest',
    'Central European Standard Time' => 'Europe/Warsaw',
    'GMT Standard Time'            => 'Europe/London',
    'Greenwich Standard Time'      => 'Atlantic/Reykjavik',
    'UTC'                          => 'UTC',
    'Coordinated Universal Time'   => 'UTC',
    'E. Europe Standard Time'      => 'Europe/Chisinau',
    'FLE Standard Time'            => 'Europe/Helsinki',
    'GTB Standard Time'            => 'Europe/Bucharest',
    'Eastern Standard Time'        => 'America/New_York',
    'Central Standard Time'        => 'America/Chicago',
    'Mountain Standard Time'       => 'America/Denver',
    'Pacific Standard Time'        => 'America/Los_Angeles',
];

function pd_ical_tz(?string $tzid, ?DateTimeZone $default = null): DateTimeZone
{
    $default = $default ?? new DateTimeZone(PD_IS_TZ);
    if ($tzid === null || $tzid === '') {
        return $default;
    }
    $tzid = trim($tzid, '"');
    if (isset(PD_IS_WINDOWS_TZ[$tzid])) {
        return new DateTimeZone(PD_IS_WINDOWS_TZ[$tzid]);
    }
    // e.g. "/mozilla.org/20050126_1/Europe/Amsterdam"
    if (preg_match('~([A-Za-z]+/[A-Za-z_+-]+(?:/[A-Za-z_+-]+)?)$~', $tzid, $m)) {
        $tzid = $m[1];
    }
    try {
        return new DateTimeZone($tzid);
    } catch (\Throwable $e) {
        return $default;
    }
}

function pd_ical_unescape(string $value): string
{
    return strtr($value, ['\\n' => "\n", '\\N' => "\n", '\\,' => ',', '\\;' => ';', '\\\\' => '\\']);
}

/**
 * A DATE or DATE-TIME value. Returns [DateTimeImmutable, bool allDay] or null.
 */
function pd_ical_date(string $value, array $params, DateTimeZone $calendarTz): ?array
{
    $value = trim($value);
    if (preg_match('/^(\d{8})$/', $value) || ($params['VALUE'] ?? '') === 'DATE') {
        $d = DateTimeImmutable::createFromFormat('!Ymd', substr($value, 0, 8), $calendarTz);
        return $d ? [$d, true] : null;
    }
    if (!preg_match('/^(\d{8}T\d{4,6})(Z?)$/', $value, $m)) {
        return null;
    }
    $fmt = strlen($m[1]) === 13 ? '!Ymd\THi' : '!Ymd\THis';
    $tz = $m[2] === 'Z' ? new DateTimeZone('UTC') : pd_ical_tz($params['TZID'] ?? null, $calendarTz);
    $d = DateTimeImmutable::createFromFormat($fmt, $m[1], $tz);
    return $d ? [$d, false] : null;
}

/** Content lines of an .ics text: unfolded, as [name, params, value]. */
function pd_ical_lines(string $ics): array
{
    $ics = str_replace(["\r\n", "\r"], "\n", $ics);
    $ics = preg_replace("/\n[ \t]/", '', $ics); // unfold
    $lines = [];
    foreach (explode("\n", $ics) as $line) {
        if ($line === '' || !str_contains($line, ':')) continue;
        // name;param=value;param="a:b":value — the first ':' outside quotes ends the parameters
        $inQuote = false;
        $split = null;
        for ($i = 0, $n = strlen($line); $i < $n; $i++) {
            if ($line[$i] === '"') $inQuote = !$inQuote;
            elseif ($line[$i] === ':' && !$inQuote) { $split = $i; break; }
        }
        if ($split === null) continue;
        $head = substr($line, 0, $split);
        $value = substr($line, $split + 1);
        $parts = explode(';', $head);
        $name = strtoupper(array_shift($parts));
        $params = [];
        foreach ($parts as $p) {
            if (str_contains($p, '=')) {
                [$k, $v] = explode('=', $p, 2);
                $params[strtoupper($k)] = trim($v, '"');
            }
        }
        $lines[] = [$name, $params, $value];
    }
    return $lines;
}

/**
 * Parse VEVENTs. Throws RuntimeException when the text is not a calendar.
 * Each event: uid, title, place, start, end, all_day, rrule (array|null), exdates (string keys),
 * recurrence_id (DateTimeImmutable|null), cancelled, private.
 */
function pd_ical_parse(string $ics): array
{
    $ics = ltrim($ics, "\xEF\xBB\xBF \t\r\n");
    if (stripos($ics, 'BEGIN:VCALENDAR') !== 0) {
        throw new RuntimeException('Dit is geen agenda in iCal-formaat (.ics)');
    }
    $lines = pd_ical_lines($ics);
    $calendarTz = new DateTimeZone(PD_IS_TZ);
    foreach ($lines as [$name, , $value]) {
        if ($name === 'X-WR-TIMEZONE') { $calendarTz = pd_ical_tz($value); break; }
    }

    $events = [];
    $cur = null;
    $depth = 0; // nested components inside a VEVENT (VALARM)
    foreach ($lines as [$name, $params, $value]) {
        if ($name === 'BEGIN' && strtoupper($value) === 'VEVENT') { $cur = ['exdates' => [], 'rrule' => null, 'recurrence_id' => null, 'start' => null, 'end' => null, 'duration' => null, 'all_day' => false, 'uid' => '', 'title' => '', 'place' => '', 'cancelled' => false, 'private' => false]; $depth = 0; continue; }
        if ($cur === null) continue;
        if ($name === 'BEGIN') { $depth++; continue; }
        if ($name === 'END' && strtoupper($value) !== 'VEVENT') { $depth--; continue; }
        if ($name === 'END') {
            if ($cur['start']) {
                if (!$cur['end']) {
                    if ($cur['duration']) {
                        try { $cur['end'] = $cur['start']->add(new DateInterval(ltrim($cur['duration'], '+'))); } catch (\Throwable $e) { /* ignore */ }
                    }
                    if (!$cur['end']) $cur['end'] = $cur['all_day'] ? $cur['start']->modify('+1 day') : $cur['start'];
                }
                unset($cur['duration']);
                $events[] = $cur;
            }
            $cur = null;
            continue;
        }
        if ($depth > 0) continue;
        switch ($name) {
            case 'UID': $cur['uid'] = $value; break;
            case 'SUMMARY': $cur['title'] = trim(pd_ical_unescape($value)); break;
            case 'LOCATION': $cur['place'] = trim(pd_ical_unescape($value)); break;
            case 'STATUS': $cur['cancelled'] = strtoupper(trim($value)) === 'CANCELLED'; break;
            case 'CLASS': $cur['private'] = in_array(strtoupper(trim($value)), ['PRIVATE', 'CONFIDENTIAL'], true); break;
            case 'DURATION': $cur['duration'] = trim($value); break;
            case 'DTSTART':
                if ($d = pd_ical_date($value, $params, $calendarTz)) { [$cur['start'], $cur['all_day']] = $d; }
                break;
            case 'DTEND':
                if ($d = pd_ical_date($value, $params, $calendarTz)) { $cur['end'] = $d[0]; }
                break;
            case 'RECURRENCE-ID':
                if ($d = pd_ical_date($value, $params, $calendarTz)) { $cur['recurrence_id'] = $d[0]; }
                break;
            case 'EXDATE':
                foreach (explode(',', $value) as $v) {
                    if ($d = pd_ical_date($v, $params, $calendarTz)) $cur['exdates'][] = $d[0]->getTimestamp();
                }
                break;
            case 'RRULE':
                $rule = [];
                foreach (explode(';', $value) as $part) {
                    if (str_contains($part, '=')) { [$k, $v] = explode('=', $part, 2); $rule[strtoupper($k)] = strtoupper($v); }
                }
                $cur['rrule'] = $rule;
                break;
        }
    }
    return $events;
}

/** Ordinal weekday in a month: nth (1..5, or -1..-5) $weekday (1=Mon … 7=Sun). */
function pd_ical_nth_weekday(int $year, int $month, int $weekday, int $nth): ?int
{
    $days = pd_ical_days_in_month($year, $month);
    $all = [];
    for ($d = 1; $d <= $days; $d++) {
        if ((int)date('N', gmmktime(12, 0, 0, $month, $d, $year)) === $weekday) $all[] = $d;
    }
    $idx = $nth > 0 ? $nth - 1 : count($all) + $nth;
    return $all[$idx] ?? null;
}

function pd_ical_days_in_month(int $year, int $month): int
{
    return (int)date('t', gmmktime(12, 0, 0, $month, 1, $year));
}

/**
 * Start times of one repeating event between $from and $to (inclusive), as DateTimeImmutable
 * in the event's own time zone (so a 9:00 appointment stays at 9:00 across summer time).
 */
function pd_ical_expand(array $ev, DateTimeImmutable $from, DateTimeImmutable $to, int $max = 400): array
{
    $start = $ev['start'];
    $rule = $ev['rrule'];
    $freq = $rule['FREQ'] ?? '';
    if (!in_array($freq, ['DAILY', 'WEEKLY', 'MONTHLY', 'YEARLY'], true)) {
        return [$start];
    }
    $interval = max(1, (int)($rule['INTERVAL'] ?? 1));
    $count = isset($rule['COUNT']) ? max(1, (int)$rule['COUNT']) : null;
    $until = null;
    if (!empty($rule['UNTIL'])) {
        $u = pd_ical_date($rule['UNTIL'], [], $start->getTimezone());
        if ($u) $until = $u[1] ? $u[0]->setTime(23, 59, 59) : $u[0];
    }
    $codes = ['MO' => 1, 'TU' => 2, 'WE' => 3, 'TH' => 4, 'FR' => 5, 'SA' => 6, 'SU' => 7];
    $byDay = [];
    foreach (array_filter(explode(',', $rule['BYDAY'] ?? '')) as $bd) {
        if (preg_match('/^([+-]?\d{1,2})?(MO|TU|WE|TH|FR|SA|SU)$/', $bd, $m)) $byDay[] = [(int)($m[1] ?? 0), $codes[$m[2]]];
    }
    $byMonthDay = array_map('intval', array_filter(explode(',', $rule['BYMONTHDAY'] ?? ''), 'strlen'));
    $byMonth = array_map('intval', array_filter(explode(',', $rule['BYMONTH'] ?? ''), 'strlen'));

    $h = (int)$start->format('G'); $i = (int)$start->format('i'); $s = (int)$start->format('s');
    $at = fn(int $y, int $mo, int $d) => $start->setDate($y, $mo, $d)->setTime($h, $i, $s);

    $out = [];
    $seen = 0;
    $period = $start;
    for ($guard = 0; $guard < 3000; $guard++) {
        $y = (int)$period->format('Y'); $mo = (int)$period->format('n');
        $cands = [];
        if ($freq === 'DAILY') {
            $ok = (!$byMonth || in_array((int)$period->format('n'), $byMonth, true))
                && (!$byDay || in_array((int)$period->format('N'), array_column($byDay, 1), true))
                && (!$byMonthDay || in_array((int)$period->format('j'), $byMonthDay, true));
            if ($ok) $cands[] = $period;
        } elseif ($freq === 'WEEKLY') {
            $monday = $period->modify('-' . ((int)$period->format('N') - 1) . ' days');
            $days = $byDay ? array_column($byDay, 1) : [(int)$start->format('N')];
            sort($days);
            foreach ($days as $wd) {
                $c = $monday->modify('+' . ($wd - 1) . ' days');
                $c = $at((int)$c->format('Y'), (int)$c->format('n'), (int)$c->format('j'));
                if (!$byMonth || in_array((int)$c->format('n'), $byMonth, true)) $cands[] = $c;
            }
        } else {
            $months = $freq === 'MONTHLY' ? [$mo] : ($byMonth ?: [(int)$start->format('n')]);
            foreach ($months as $m) {
                if ($freq === 'MONTHLY' && $byMonth && !in_array($m, $byMonth, true)) continue;
                $dim = pd_ical_days_in_month($y, $m);
                $dayList = [];
                if ($byDay) {
                    foreach ($byDay as [$nth, $wd]) {
                        if ($nth !== 0) {
                            if (($d = pd_ical_nth_weekday($y, $m, $wd, $nth)) !== null) $dayList[] = $d;
                        } else {
                            for ($d = 1; $d <= $dim; $d++) {
                                if ((int)date('N', gmmktime(12, 0, 0, $m, $d, $y)) === $wd) $dayList[] = $d;
                            }
                        }
                    }
                    if ($byMonthDay) $dayList = array_values(array_intersect($dayList, array_map(fn($d) => $d < 0 ? $dim + $d + 1 : $d, $byMonthDay)));
                } elseif ($byMonthDay) {
                    foreach ($byMonthDay as $d) { $d = $d < 0 ? $dim + $d + 1 : $d; if ($d >= 1 && $d <= $dim) $dayList[] = $d; }
                } else {
                    $d = (int)$start->format('j');
                    if ($d <= $dim) $dayList[] = $d; // e.g. the 31st is skipped in short months
                }
                sort($dayList);
                foreach (array_unique($dayList) as $d) $cands[] = $at($y, $m, $d);
            }
        }
        foreach ($cands as $c) {
            if ($c < $start) continue;
            if ($until && $c > $until) return $out;
            if ($count !== null && $seen >= $count) return $out;
            $seen++;
            if ($c > $to) return $out;
            if ($c >= $from) {
                $out[] = $c;
                if (count($out) >= $max) return $out;
            }
        }
        // next period
        $period = match ($freq) {
            'DAILY'   => $period->modify("+$interval days"),
            'WEEKLY'  => $period->modify("+$interval weeks"),
            'MONTHLY' => $period->setDate($y, $mo, 1)->modify("+$interval months"),
            'YEARLY'  => $period->setDate($y, 1, 1)->modify("+$interval years"),
        };
        if ($period > $to && $count === null) return $out;
    }
    return $out;
}

/**
 * Occurrences of all events overlapping [$from, $to], in Amsterdam time:
 * [['key','title','place','start' => 'Y-m-d H:i:s','end' => …,'all_day' => bool], …], sorted by start.
 */
function pd_ical_occurrences(array $events, DateTimeImmutable $from, DateTimeImmutable $to): array
{
    $local = new DateTimeZone(PD_IS_TZ);
    // Moved / cancelled occurrences of repeating events
    $overrides = [];
    foreach ($events as $ev) {
        if ($ev['recurrence_id']) $overrides[$ev['uid'] . '|' . $ev['recurrence_id']->getTimestamp()] = true;
    }
    $out = [];
    foreach ($events as $ev) {
        if ($ev['private'] || $ev['cancelled'] || $ev['title'] === '') continue;
        $length = $ev['end']->getTimestamp() - $ev['start']->getTimestamp();
        // An occurrence that started before $from but is still going on counts too
        $starts = $ev['rrule'] && !$ev['recurrence_id']
            ? pd_ical_expand($ev, $from->modify('-' . max(0, $length) . ' seconds'), $to)
            : [$ev['start']];
        foreach ($starts as $st) {
            $ts = $st->getTimestamp();
            if ($ev['rrule'] && !$ev['recurrence_id']) {
                if (in_array($ts, $ev['exdates'], true)) continue;
                if (isset($overrides[$ev['uid'] . '|' . $ts])) continue;
            }
            $en = $st->modify(($length >= 0 ? '+' : '') . $length . ' seconds');
            if ($en <= $from && $length > 0) continue;
            if ($st > $to) continue;
            if ($length === 0 && $st < $from) continue;
            $out[] = [
                'key'     => sha1($ev['uid'] . '|' . $ts),
                'title'   => mb_substr($ev['title'], 0, 300),
                'place'   => $ev['place'] !== '' ? mb_substr($ev['place'], 0, 200) : null,
                'start'   => $ev['all_day'] ? $st->format('Y-m-d 00:00:00') : $st->setTimezone($local)->format('Y-m-d H:i:s'),
                'end'     => $ev['all_day'] ? $en->format('Y-m-d 00:00:00') : $en->setTimezone($local)->format('Y-m-d H:i:s'),
                'all_day' => $ev['all_day'],
            ];
        }
    }
    usort($out, fn($a, $b) => [$a['start'], $a['title']] <=> [$b['start'], $b['title']]);
    return $out;
}

/** webcal:// → https:// */
function pd_ical_url(string $url): string
{
    return preg_replace('~^webcals?://~i', 'https://', trim($url));
}

function pd_infoscreen_fetch_ical(PDO $db, array $source): array
{
    $res = pd_infoscreen_http_get(pd_ical_url((string)$source['url']));
    if (!$res['ok']) {
        throw new RuntimeException($res['error']);
    }
    $events = pd_ical_parse($res['body']);
    $now = new DateTimeImmutable('now', new DateTimeZone(PD_IS_TZ));
    $from = $now->setTime(0, 0);
    $to = $from->modify('+' . PD_IS_AGENDA_DAYS_STORED . ' days');
    $occ = pd_ical_occurrences($events, $from, $to);

    $db->beginTransaction();
    try {
        $db->prepare("DELETE FROM infoscreen_events WHERE source_id = ?")->execute([$source['id']]);
        $ins = $db->prepare("INSERT IGNORE INTO infoscreen_events (source_id, event_key, title, place, starts_at, ends_at, all_day) VALUES (?, ?, ?, ?, ?, ?, ?)");
        foreach (array_slice($occ, 0, 1000) as $o) {
            $ins->execute([$source['id'], $o['key'], $o['title'], $o['place'], $o['start'], $o['end'], $o['all_day'] ? 1 : 0]);
        }
        $db->commit();
    } catch (\Throwable $e) {
        $db->rollBack();
        throw $e;
    }
    $week = count(array_filter($occ, fn($o) => $o['start'] < $from->modify('+7 days')->format('Y-m-d H:i:s')));
    return ['message' => count($occ) . ' afspraken in de komende ' . PD_IS_AGENDA_DAYS_STORED . ' dagen, waarvan ' . $week . ' deze week', 'count' => count($occ)];
}
