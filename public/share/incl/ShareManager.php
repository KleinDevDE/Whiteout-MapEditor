<?php

namespace incl;
if (!defined("INITIALIZED")) {
    http_response_code(403);
    die();
}

class ShareManager
{
    public function storeToFile(SaveData $saveData)
    {
        $filePath = __DIR__ . "/../saves/{$saveData->getShareID()}.json";
        if (file_exists($filePath)) {
            http_response_code(409);
            die(json_encode(['status' => false, 'error' => 'Cannot overwrite existing shareID']));
        }

        try {
            //First, make sure we have at least 30GB free disk space
            $freeSpace = disk_free_space(__DIR__ . "/../saves/");
            if ($freeSpace === false || $freeSpace < 30 * 1024 * 1024 * 1024) {
                http_response_code(507); //Insufficient Storage
                die(json_encode(['status' => false, 'error' => 'Cannot accept new shares due to low disk space']));
            }

            file_put_contents($filePath, $saveData->toJSON());
        } catch (\Throwable $throwable) {
            http_response_code(500);
            error_log("JSON encoding error: " . $throwable->getMessage());
            die(json_encode(['status' => false, 'error' => 'Failed to encode data to JSON']));
        }
    }

    public static function fromFile(string $shareID): SaveData
    {
        $filePath = __DIR__ . "/../saves/$shareID.json";
        if (!file_exists($filePath)) {
            http_response_code(404);
            die(json_encode(['status' => false, 'error' => 'ShareID not found']));
        }

        try {
            $json = file_get_contents($filePath);
            if ($json === false) {
                throw new \RuntimeException("Failed to read file");
            }

            return SaveData::fromJSON($json);
        } catch (\Throwable $throwable) {
            http_response_code(500);
            error_log("Error reading share file: " . $throwable->getMessage());
            die(json_encode(['status' => false, 'error' => 'Failed to read share data']));
        }
    }
}