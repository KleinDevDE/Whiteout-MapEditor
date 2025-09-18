<?php
define("INITIALIZED", time());
include_once "incl/func.php";
require_once "incl/SaveData.php";
require_once "incl/ShareManager.php";

use incl\SaveData;



$input = file_get_contents('php://input');
if ($input === false) {
    http_response_code(400);
    die(json_encode(['status' => false, 'error' => 'Failed to read input']));
}

try {
    $data = json_decode($input, true, 512, JSON_THROW_ON_ERROR);
} catch (Throwable $e) {
    http_response_code(400);
    die(json_encode(['status' => false, 'error' => 'Invalid JSON input']));
}

$saveData = SaveData::fromJSON($input);
$shareID = $saveData->getShareID();

if ($saveData->getShareID() !== $shareID) {
    http_response_code(400);
    die(json_encode(['status' => false, 'error' => 'shareID in URL does not match shareID in data']));
}

$shareManager = new incl\ShareManager();
$shareManager->storeToFile($saveData);

header('Content-Type: application/json');
echo json_encode([
    'status' => true,
    'shareID' => $shareID,
    'url' => getBaseURL() . "/view.php?id=" . $shareID
]);