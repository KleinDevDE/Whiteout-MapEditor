<?php
define("INITIALIZED", time());
include_once "incl/func.php";
require_once "incl/SaveData.php";
require_once "incl/ShareManager.php";

use incl\ShareManager;

$shareID = getShareID();
$saveData = ShareManager::fromFile($shareID);

if ($saveData->getShareID() !== $shareID) {
    http_response_code(400);
    die(json_encode(['status' => false, 'error' => 'shareID in URL does not match shareID in data']));
}

header('Content-Type: application/json');
echo json_encode(['status' => true, 'data' => $saveData->getData()]);