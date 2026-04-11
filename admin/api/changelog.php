<?php
/**
 * PeopleDisplay
 * Copyright (c) 2024 Ton Labee — https://peopledisplay.nl
 *
 * Starter versie: GNU AGPL v3 (zie /LICENSE)
 * Commercieel gebruik boven Starter limieten vereist een licentie.
 */
header('Access-Control-Allow-Origin: *');
header('Content-Type: application/json');

echo file_get_contents(__DIR__ . '/../../version.json');
