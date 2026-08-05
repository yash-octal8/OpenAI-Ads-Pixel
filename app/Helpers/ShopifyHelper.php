<?php

namespace App\Helpers;

class ShopifyHelper
{
    public static string $CUSTOMER = 'Customer';
    public static string $SEGMENT = 'Segment';

    public static function prepareGraphQlQueryString($queries): string
    {
        $queries = array_filter($queries);
        $queryString = "";
        foreach ($queries as $queryKey => $queryValue) {
            if (is_string($queryValue)) {
                $queryString .= "$queryKey: \"$queryValue\"";
            } else {
                $queryString .= "$queryKey: $queryValue";
            }
        }

        return $queryString;
    }

    public static function tags($tags)
    {
        if (is_array($tags)) {
            return $tags;
        }

        return array_values(array_filter(explode(', ', $tags)));
    }

    public static function prepareThemeSchemaFileJson($themeSettingsContentString)
    {
        $jsonBlock = [];
        try {
            if (!$themeSettingsContentString) {
                return $jsonBlock;
            }

            // Try decoding directly first (for pure JSON files)
            $decoded = json_decode($themeSettingsContentString, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                return $decoded;
            }

            $lines = explode("\n", $themeSettingsContentString);

            $capturing = false;
            $jsonLines = [];

            foreach ($lines as $line) {

                if (!$capturing && str($line)->contains('{')) {
                    $capturing = true;
                }

                if ($capturing) {
                    $jsonLines[] = $line;
                }
            }

            $jsonBlock = implode("\n", $jsonLines);
            $jsonBlock = json_decode($jsonBlock, true);
        } catch (\Exception $e) {
            report($e);
        }

        return $jsonBlock ?: [];
    }
}
