<?php

use App\Helpers\ShopifyHelper;
use Illuminate\Support\Facades\DB;

function isJSON($string)
{
    return is_string($string) && is_array(json_decode($string, true)) && (json_last_error() == JSON_ERROR_NONE);
}

function AuthId()
{
    return Auth::id();
}

function bulkUpdate($table, $values, $index = 'id')
{
    $final = [];
    $ids = [];

    if (!count($values)) return false;

    if (empty($index)) return false;

    $slots = [];
    foreach ($values as $key => $val) {
        $ids[] = $val[$index];
        foreach (array_keys($val) as $field) {
            if ($field !== $index) {
                $value = (is_null($val[$field]) ? NULL : $val[$field]);
                $slotKey = ':' . $field . '_' . $key;

                $slots[$slotKey] = $value;
                $final[$field][] = "WHEN `$index` = " . $val[$index] . " THEN " . $slotKey . " ";
            }
        }
    }

    $cases = '';
    foreach ($final as $k => $v) {
        $cases .= '`' . $k . '` = (CASE ' . implode("\n", $v) . "\n"
            . 'ELSE `' . $k . '` END), ';
    }

    $query = "UPDATE `$table` SET " . substr($cases, 0, -2) . " WHERE `$index` IN(" . implode(',', $ids) . ");";

    return DB::statement($query, $slots);
}

function getResourceId($gid): int
{
    return (int) substr(strrchr($gid, '/'), 1);
}

function getGraphqlId($id, $type)
{
    $id = is_string($id) ? trim($id) : $id;

    if (str($id)->startsWith('gid://')) {
        return $id;
    }

    $prefix = 'gid://shopify';

    return match($type) {
        ShopifyHelper::$CUSTOMER => join('/', [$prefix, ShopifyHelper::$CUSTOMER, $id]),
        ShopifyHelper::$SEGMENT => join('/', [$prefix, ShopifyHelper::$SEGMENT, $id]),
        default => $id
    };
}

function preparedResponse($data, $statusCode = 200, $errors = false)
{
    $preparedData = [];
    if (!str($statusCode)->startsWith('20') || $errors) {
        $preparedData['message'] = $data;
        $preparedData['errors'] = true;
    } else {
        $preparedData = $data;
    }

    if ($errors) {
        $preparedData['errors'] = $errors;
    }

    return response()->json(
        $preparedData,
        $statusCode ?: 400
    );
}

if (!function_exists('calculateTotalCharge')) {
    function calculateTotalCharge(int $customerNumbers, $shop, $startFromZero = true)
    {
        // Simplified for skeleton app: no invite cost calculation needed.
        return $startFromZero ? 0 : ['amount' => 0, 'free_invites' => $customerNumbers, 'charged' => 0];
    }
}

if (!function_exists('useMoreMemory')) {
    function useMoreMemory()
    {
        ini_set('memory_limit', '1024M');
    }
}
