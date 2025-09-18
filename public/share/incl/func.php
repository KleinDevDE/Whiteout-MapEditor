<?php
if (!defined("INITIALIZED")) {
    http_response_code(403);
    die();
}

require_once "SaveData.php";
use incl\SaveData;

function getBaseURL(): string {
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443) ? "https://" : "http://";
    $host = $_SERVER['HTTP_HOST'];
    return $protocol . $host;
}

function getShareID(): string {
    $shareID = $_GET["id"] ?? null;
    if ($shareID === null) {
        http_response_code(400);
        die(json_encode(['status' => false, 'error' => '$.shareID missing!']));
    }

    if (strlen($shareID) !== 18) {
        http_response_code(400);
        die(json_encode(['status' => false, 'error' => '$.shareID must be exactly 18 characters long!']));
    }

    if (!SaveData::verifyCheckSum($shareID)) {
        http_response_code(400);
        die(json_encode(['status' => false, 'error' => '$.shareID checksum is invalid!']));
    }

    return $shareID;
}