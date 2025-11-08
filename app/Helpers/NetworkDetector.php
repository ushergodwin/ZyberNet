<?php

namespace App\Helpers;

class NetworkDetector
{
    /**
     * Ugandan MTN prefixes
     * Format: 256 77X, 256 78X, 256 76X (some)
     */
    private static array $mtnPrefixes = [
        // MTN Uganda prefixes (without country code)
        '077',
        '078',
        '076',

        // With country code +256
        '25677',
        '25678',
        '25676',

        // With country code 256 (no plus)
        '25677',
        '25678',
        '25676',
    ];

    /**
     * Ugandan Airtel prefixes
     * Format: 256 75X, 256 70X
     */
    private static array $airtelPrefixes = [
        // Airtel Uganda prefixes (without country code)
        '075',
        '070',
        '074',

        // With country code +256
        '25675',
        '25670',
        '25674',

        // With country code 256 (no plus)
        '25675',
        '25670',
        '25674',
    ];

    /**
     * Detect network from phone number
     * 
     * @param string $phoneNumber The phone number to check
     * @return string|null Returns 'MTN', 'AIRTEL', or null if unknown
     */
    public static function detectNetwork(string $phoneNumber): ?string
    {
        // Remove all non-numeric characters
        $cleaned = preg_replace('/[^0-9]/', '', $phoneNumber);

        // Remove leading zeros
        $cleaned = ltrim($cleaned, '0');

        // Check MTN prefixes
        foreach (self::$mtnPrefixes as $prefix) {
            if (str_starts_with($cleaned, $prefix)) {
                return 'MTN';
            }
        }

        // Check Airtel prefixes
        foreach (self::$airtelPrefixes as $prefix) {
            if (str_starts_with($cleaned, $prefix)) {
                return 'AIRTEL';
            }
        }

        return null;
    }

    /**
     * Normalize phone number to E.164 format (256XXXXXXXXX)
     * 
     * @param string $phoneNumber
     * @return string|null
     */
    public static function normalizePhoneNumber(string $phoneNumber): ?string
    {
        // Remove all non-numeric characters
        $cleaned = preg_replace('/[^0-9]/', '', $phoneNumber);

        // Remove leading zeros
        $cleaned = ltrim($cleaned, '0');

        // If it starts with 256, it's already normalized
        if (str_starts_with($cleaned, '256')) {
            return $cleaned;
        }

        // If it's a 9-digit number (local format), add country code
        if (strlen($cleaned) === 9) {
            return '256' . $cleaned;
        }

        // If it's a 10-digit number starting with 0, remove the 0 and add country code
        if (strlen($cleaned) === 10 && str_starts_with($cleaned, '0')) {
            return '256' . substr($cleaned, 1);
        }

        return null;
    }

    /**
     * Validate if phone number is a valid Ugandan number
     * 
     * @param string $phoneNumber
     * @return bool
     */
    public static function isValidUgandanNumber(string $phoneNumber): bool
    {
        $normalized = self::normalizePhoneNumber($phoneNumber);

        if (!$normalized) {
            return false;
        }

        // Check if it matches any known prefix
        return self::detectNetwork($phoneNumber) !== null;
    }

    /**
     * Get formatted phone number for display
     * 
     * @param string $phoneNumber
     * @param string $format 'international' or 'local'
     * @return string|null
     */
    public static function formatPhoneNumber(string $phoneNumber, string $format = 'international'): ?string
    {
        $normalized = self::normalizePhoneNumber($phoneNumber);

        if (!$normalized) {
            return null;
        }

        if ($format === 'local') {
            // Format: 0777 123 456
            $localNumber = '0' . substr($normalized, 3);
            return substr($localNumber, 0, 4) . ' ' .
                substr($localNumber, 4, 3) . ' ' .
                substr($localNumber, 7);
        }

        // Format: +256 777 123 456 (international)
        return '+' . substr($normalized, 0, 3) . ' ' .
            substr($normalized, 3, 3) . ' ' .
            substr($normalized, 6, 3) . ' ' .
            substr($normalized, 9);
    }

    /**
     * Get network info including detection and formatting
     * 
     * @param string $phoneNumber
     * @return array
     */
    public static function getPhoneNumberInfo(string $phoneNumber): array
    {
        $network = self::detectNetwork($phoneNumber);
        $normalized = self::normalizePhoneNumber($phoneNumber);
        $isValid = self::isValidUgandanNumber($phoneNumber);

        return [
            'original' => $phoneNumber,
            'normalized' => $normalized,
            'network' => $network,
            'is_valid' => $isValid,
            'formatted_local' => $isValid ? self::formatPhoneNumber($phoneNumber, 'local') : null,
            'formatted_international' => $isValid ? self::formatPhoneNumber($phoneNumber, 'international') : null,
        ];
    }
}
