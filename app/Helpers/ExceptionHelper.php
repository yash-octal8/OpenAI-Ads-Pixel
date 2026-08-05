<?php

namespace App\Helpers;

use Exception;
use Illuminate\Support\Facades\Log;

class ExceptionHelper
{
    /**
     * Handle and log exceptions with detailed context.
     *
     * @param Exception $exception
     * @param string|null $contextMessage
     * @param array $extraData
     * @return array
     */
    public static function handle(Exception $exception, ?string $contextMessage = null, array $extraData = []): array
    {
        $errorMessage = $exception->getMessage();
        $statusCode = method_exists($exception, 'getCode') && $exception->getCode() ? $exception->getCode() : 500;

        // Prepare log data
        $logData = [
            'error' => $errorMessage,
            'exception' => get_class($exception),
            'file' => $exception->getFile(),
            'line' => $exception->getLine(),
            'status_code' => $statusCode,
            'stack_trace' => $exception->getTraceAsString(),
            'extra_data' => $extraData,
        ];

        if ($contextMessage) {
            $logData['context'] = $contextMessage;
        }

        // Log the error
        Log::error("ExceptionHelper: {$errorMessage}", $logData);

        return [
            'is_error' => true,
            'error' => $errorMessage,
            'status' => $statusCode,
        ];
    }
}
