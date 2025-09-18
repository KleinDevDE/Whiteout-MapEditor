<?php

namespace incl;
if (!defined("INITIALIZED")) {
    http_response_code(403);
    die();
}

class SaveData
{
    private string $shareID;

    /**
     * @param array{
     *      "placedTiles": array{
     *          array{
     *              "id": string,
     *              "stampId": string,
     *              "origin": array{ "x": int, "y": int },
     *              "color": string,
     *              "bbox"?: array{ "w": int, "h": int },
     *              "bannerRange"?: int
     *          }
     *      },
     *      "view": array{
     *          "x": int,
     *          "y": int,
     *          "zoom": int
     *      }
     * } $data
     */
    private array $data;

    private float $createdAt;

    public static function fromJSON(string $json): SaveData
    {
        $data = json_decode($json, true);
        if ($data === null) {
            http_response_code(400);
            die(json_encode(['status' => false, 'error' => 'Invalid JSON']));
        }

        return self::fromArray($data);
    }

    public static function fromArray(array $saveData): SaveData
    {
        $saveData = self::validate($saveData);

        $instance = new self();
        $instance->shareID = $saveData["shareID"];
        $instance->data = $saveData["data"];
        $instance->createdAt = microtime(true);
        return $instance;
    }

    public static function validate(array $saveData): array
    {
        if (isset($saveData["placedTiles"])) {
            $saveData = ['shareID' => self::generateShareID(), 'data' => $saveData];
        }

        if (!isset($saveData["shareID"])) {
            $saveData["shareID"] = self::generateShareID();
        }

        //Input validation
        if (!is_string($saveData["shareID"]) || !self::verifyChecksum($saveData["shareID"])) {
            http_response_code(400);
            die(json_encode(['status' => false, 'error' => '$.shareID is invalid!']));
        }

        if (!isset($saveData["data"]) || !is_array($saveData["data"])) {
            http_response_code(400);
            die(json_encode(['status' => false, 'error' => '$.data must be an array and not empty']));
        }

        if (!isset($saveData["data"]["view"]) || !is_array($saveData["data"]["view"])) {
            http_response_code(400);
            die(json_encode(['status' => false, 'error' => '$.data.view must be an array and not empty']));
        }

        if (!isset($saveData["data"]["view"]["x"]) || !is_numeric($saveData["data"]["view"]["x"])) {
            http_response_code(400);
            die(json_encode(['status' => false, 'error' => '$.data.view.x is missing or not numeric!']));
        }

        if (!isset($saveData["data"]["view"]["y"]) || !is_numeric($saveData["data"]["view"]["y"])) {
            http_response_code(400);
            die(json_encode(['status' => false, 'error' => '$.data.view.y is missing or not numeric!']));
        }

        if (!isset($saveData["data"]["view"]["zoom"]) || !is_numeric($saveData["data"]["view"]["zoom"])) {
            http_response_code(400);
            die(json_encode(['status' => false, 'error' => '$.data.view.zoom is missing or not numeric!']));
        }

        if (!isset($saveData["data"]["placedTiles"]) || !is_array($saveData["data"]["placedTiles"])) {
            http_response_code(400);
            die(json_encode(['status' => false, 'error' => '$.data.placedTiles must be an array and not empty']));
        }

        $counter = 0;
        foreach ($saveData["data"]["placedTiles"] as $key => $value) {
            if (!is_array($value)) {
                http_response_code(400);
                die(json_encode(['status' => false, 'error' => "$.data.placedTiles.$counter is not an object!"]));
            }

            if (!isset($value["id"]) || !is_string($value["id"])) {
                http_response_code(400);
                die(json_encode(['status' => false, 'error' => "$.data.placedTiles.$counter.id is missing or not a string!"]));
            }

            if (strlen($value["id"]) > 120) {
                http_response_code(400);
                die(json_encode(['status' => false, 'error' => "$.data.placedTiles.$counter.id cannot be longer then 120 chars!"]));
            }

            if (!isset($value["stampId"]) || !is_string($value["stampId"])) {
                http_response_code(400);
                die(json_encode(['status' => false, 'error' => "$.data.placedTiles.$counter.stampId is missing or not a string!"]));
            }

            if (strlen($value["stampId"]) > 120) {
                http_response_code(400);
                die(json_encode(['status' => false, 'error' => "$.data.placedTiles.$counter.stampId cannot be longer then 120 chars!"]));
            }

            if (!isset($value["origin"]) || !is_array($value["origin"])) {
                http_response_code(400);
                die(json_encode(['status' => false, 'error' => "$.data.placedTiles.$counter.origin is missing or not an object!"]));
            }

            if (!isset($value["origin"]["x"]) || !is_numeric($value["origin"]["x"])) {
                http_response_code(400);
                die(json_encode(['status' => false, 'error' => "$.data.placedTiles.$counter.origin.x is missing or not numeric!"]));
            }

            if (!isset($value["origin"]["y"]) || !is_numeric($value["origin"]["y"])) {
                http_response_code(400);
                die(json_encode(['status' => false, 'error' => "$.data.placedTiles.$counter.origin.y is missing or not numeric!"]));
            }

            if (!isset($value["color"]) || !is_string($value["color"])) {
                http_response_code(400);
                die(json_encode(['status' => false, 'error' => "$.data.placedTiles.$counter.color is missing or not a string!"]));
            }

            if (strlen($value["color"]) > 30) {
                http_response_code(400);
                die(json_encode(['status' => false, 'error' => "$.data.placedTiles.$counter.color cannot be longer then 30 chars!"]));
            }

            if (isset($value["bbox"])) {
                if (!is_array($value["bbox"])) {
                    http_response_code(400);
                    die(json_encode(['status' => false, 'error' => "$.data.placedTiles.$counter.bbox is not an object!"]));
                }

                if (!isset($value["bbox"]["w"]) || !is_numeric($value["bbox"]["w"])) {
                    http_response_code(400);
                    die(json_encode(['status' => false, 'error' => "$.data.placedTiles.$counter.bbox.w is missing or not numeric!"]));
                }

                if (!isset($value["bbox"]["h"]) || !is_numeric($value["bbox"]["h"])) {
                    http_response_code(400);
                    die(json_encode(['status' => false, 'error' => "$.data.placedTiles.$counter.bbox.h is missing or not numeric!"]));
                }
            }

            if (isset($value["bannerRange"]) && !is_numeric($value["bannerRange"])) {
                http_response_code(400);
                die(json_encode(['status' => false, 'error' => "$.data.placedTiles.$counter.bannerRange is not numeric!"]));
            }

            $counter++;
        }

        return $saveData;
    }

    public function toJSON(): string
    {
        try {
            return json_encode([
                'shareID' => $this->shareID,
                'data' => $this->data
            ], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            http_response_code(500);
            error_log("JSON encoding error: " . $e->getMessage());
            die(json_encode(['status' => false, 'error' => 'Failed to encode data to JSON']));
        }
    }

    /**
     * @return string
     */
    public function getShareID(): string
    {
        return $this->shareID;
    }

    /**
     * @return array
     */
    public function getData(): array
    {
        return $this->data;
    }

    private static function generateShareID(): string {
        $env = parse_ini_file(__DIR__.DIRECTORY_SEPARATOR."..".DIRECTORY_SEPARATOR.".env");
        $env["SHAREID_RANDOM"] = $env["SHAREID_RANDOM"] ?? "NoRandomFound43908";

        $base = bin2hex(random_bytes(8)); // 16 hex characters
        $checksum = substr(hash('sha256', $base . $env["SHAREID_RANDOM"]), 0, 2); // 2 hex characters
        return $base . $checksum; // Total length: 18 characters
    }

    public static function verifyChecksum(string $shareID): bool {
        $env = parse_ini_file(__DIR__."/../.env");
        $env["SHAREID_RANDOM"] = $env["SHAREID_RANDOM"] ?? "NoRandomFound43908";

        if (strlen($shareID) !== 18 || !ctype_xdigit($shareID)) {
            return false; // Invalid length or non-hex characters
        }

        $base = substr($shareID, 0, 16);
        $providedChecksum = substr($shareID, 16, 2);
        $calculatedChecksum = substr(hash('sha256', $base . $env["SHAREID_RANDOM"]), 0, 2);
        return hash_equals($providedChecksum, $calculatedChecksum);
    }
}