<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SmsService
{
    /**
     * Send SMS to a recipient.
     *
     * @param string $recipients (comma-separated for multiple numbers)
     * @param string $message
     * @return bool
     */
    public static function send(string $recipients, string $message): bool
    {
        $params = [
            'number'   => $recipients,
            'message'  => $message,
            'username' => config('services.egosms.username'),
            'password' => config('services.egosms.password'),
            'sender'   => config('services.egosms.sender', ''),
            'priority' => 0,
        ];

        try {
            $url = config('services.egosms.api_url');
            $response = Http::get($url, $params);

            if ($response->successful() && strlen($response->body()) === 2) {
                return true;
            }

            Log::warning('SMS sending failed', ['response' => $response->body()]);
            return false;
        } catch (\Throwable $e) {
            Log::error('Error sending SMS: ' . $e->getMessage());
            return false;
        }
    }
}
