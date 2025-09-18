<?php
define("INITIALIZED", time());
include_once "share/incl/func.php";
require_once "share/incl/SaveData.php";
require_once "share/incl/ShareManager.php";

use incl\ShareManager;

$shareID = getShareID();
$saveData = ShareManager::fromFile($shareID);

if ($saveData->getShareID() !== $shareID) {
    http_response_code(400);
    die(json_encode(['status' => false, 'error' => 'shareID in URL does not match shareID in data']));
}

echo str_replace("window.DRAFT_ID = undefined;", "window.DRAFT_ID = '{$shareID}';", file_get_contents(__DIR__ . "/index.html"));