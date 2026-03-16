<?php
header('Access-Control-Allow-Origin: *');
header('Content-Type: application/json');

echo file_get_contents(__DIR__ . '/../../version.json');
