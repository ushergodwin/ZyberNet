<?php

namespace App\Traits;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use InvalidArgumentException;

trait RouterTrait
{
    function encryptRouterPassword($password)
    {
        $encrypt_method = 'AES-256-CBC';
        $secret_key = env('SECRET_HASHING_KEY'); // user define private key
        $secret_iv = env('SECRET_HASHING_IV'); // user define secret key
        $key = hash('sha256', $secret_key);
        $iv = substr(hash('sha256', $secret_iv), 0, 16); // sha256 is hash_hmac_algo
        $output = openssl_encrypt($password, $encrypt_method, $key, 0, $iv);

        return base64_encode($output);
    }

    function decryptPassword($hash)
    {
        $encrypt_method = 'AES-256-CBC';
        $secret_key = env('SECRET_HASHING_KEY'); // user define private key
        $secret_iv = env('SECRET_HASHING_IV'); // user define secret key
        $key = hash('sha256', $secret_key);
        $iv = substr(hash('sha256', $secret_iv), 0, 16); // sha256 is hash_hmac_algo

        return openssl_decrypt(base64_decode($hash), $encrypt_method, $key, 0, $iv);
    }

    private function parseSessionTimeout(string $timeout): int
    {
        // Converts values like "3h", "1d", "30m" to seconds
        if (preg_match('/(\d+)([dhm])/', $timeout, $matches)) {
            $value = (int)$matches[1];
            switch ($matches[2]) {
                case 'd':
                    return $value * 86400;
                case 'h':
                    return $value * 3600;
                case 'm':
                    return $value * 60;
            }
        }
        return 0; // fallback
    }


    static  function convertToBytes(int|float $value, string $unit = 'MB'): int
    {
        $unit = strtoupper(trim($unit));

        return match ($unit) {
            'GB' => (int)($value * 1024 * 1024 * 1024),
            'MB' => (int)($value * 1024 * 1024),
            'KB' => (int)($value * 1024),
            default => throw new InvalidArgumentException("Unsupported unit: $unit"),
        };
    }

    public function sessionTimeoutToLimitUptime(string $sessionTimeout): string
    {
        // Match a number followed by 'd' (days) or 'h' (hours)
        if (!preg_match('/^(\d+)([dh])$/', strtolower($sessionTimeout), $matches)) {
            throw new InvalidArgumentException("Invalid session timeout format: $sessionTimeout");
        }

        $value = (int) $matches[1];
        $unit = $matches[2];

        if ($unit === 'd') {
            $hours = $value * 24;
        } elseif ($unit === 'h') {
            $hours = $value;
        } else {
            throw new InvalidArgumentException("Unsupported time unit: $unit");
        }

        return sprintf('%02d:00:00', $hours);
    }
}
