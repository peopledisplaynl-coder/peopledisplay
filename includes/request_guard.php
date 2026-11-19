<?php
/**
 * PeopleDisplay
 * Copyright (c) 2024 Ton Labee — https://peopledisplay.nl
 *
 * Starter versie: GNU AGPL v3 (zie /LICENSE)
 * Commercieel gebruik boven Starter limieten vereist een licentie.
 */
/**
 * Hulpfuncties voor API's die gegevens wijzigen.
 *
 * Een wijziging mag alleen via POST en alleen vanaf de eigen website. Zo kan een
 * andere website (of een plaatje in een e-mail) geen status van een medewerker
 * wijzigen met de sessie van een ingelogde gebruiker (CSRF).
 */

if (!function_exists('pd_require_post_same_origin')) {
    /**
     * Stop met een JSON-fout als het verzoek geen POST is of niet van deze website komt.
     */
    function pd_require_post_same_origin(): void
    {
        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
            header('Allow: POST');
            pd_request_guard_fail(405, 'Alleen POST is toegestaan');
        }

        $ownHost = preg_replace('/:\d+$/', '', $_SERVER['HTTP_HOST'] ?? '');
        foreach (['HTTP_ORIGIN', 'HTTP_REFERER'] as $key) {
            $value = $_SERVER[$key] ?? '';
            if ($value === '') {
                continue;
            }
            $host = parse_url($value, PHP_URL_HOST);
            if (!$host || strcasecmp($host, $ownHost) !== 0) {
                pd_request_guard_fail(403, 'Verzoek niet toegestaan');
            }
            break; // Origin gaat voor Referer
        }
    }
}

if (!function_exists('pd_request_guard_fail')) {
    function pd_request_guard_fail(int $status, string $message): void
    {
        http_response_code($status);
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => $message]);
        exit;
    }
}

if (!function_exists('pd_read_json_or_post')) {
    /**
     * Gegevens uit een JSON-body, of anders uit $_POST.
     */
    function pd_read_json_or_post(): array
    {
        $json = json_decode((string)file_get_contents('php://input'), true);
        return is_array($json) ? $json : $_POST;
    }
}
