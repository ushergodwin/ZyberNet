<?php

namespace App\Services;

use App\Contracts\PaymentGatewayInterface;
use App\Services\Gateways\CinemaUGGateway;
use App\Services\Gateways\YoPaymentsGateway;
use Illuminate\Support\Facades\Cache;
use InvalidArgumentException;

class PaymentGatewayFactory
{
    /**
     * Supported gateway types.
     */
    protected static array $gateways = [
        'yopayments' => YoPaymentsGateway::class,
        'cinemaug' => CinemaUGGateway::class,
    ];

    /**
     * Create a payment gateway instance.
     *
     * If auto-switch is enabled and no explicit gateway is requested,
     * round-robins between gateways every N transactions.
     *
     * @param string|null $gateway Explicit gateway name, or null for auto-resolve
     * @return PaymentGatewayInterface
     * @throws InvalidArgumentException
     */
    public static function make(?string $gateway = null): PaymentGatewayInterface
    {
        if ($gateway !== null) {
            return self::instantiate($gateway);
        }

        if (config('services.payment_gateway_auto_switch', false)) {
            return self::instantiate(self::resolveAutoSwitchGateway());
        }

        return self::instantiate(config('services.payment_gateway', 'yopayments'));
    }

    /**
     * Get the default gateway instance (respects auto-switch).
     *
     * @return PaymentGatewayInterface
     */
    public static function getDefault(): PaymentGatewayInterface
    {
        return self::make();
    }

    /**
     * Get the list of supported gateways.
     *
     * @return array
     */
    public static function getSupportedGateways(): array
    {
        return array_keys(self::$gateways);
    }

    /**
     * Check if a gateway is supported.
     *
     * @param string $gateway
     * @return bool
     */
    public static function isSupported(string $gateway): bool
    {
        return isset(self::$gateways[strtolower(trim($gateway))]);
    }

    /**
     * Determine which gateway to use based on round-robin auto-switching.
     *
     * Uses a cache-based counter instead of DB queries so that the rotation
     * works correctly even when transactions are deleted (e.g., CinemaUG cleanup).
     *
     * @return string Gateway name
     */
    protected static function resolveAutoSwitchGateway(): string
    {
        $switchEvery = (int) config('services.payment_gateway_switch_every', 10);
        $gatewayNames = array_keys(self::$gateways);
        $defaultGateway = config('services.payment_gateway', 'yopayments');

        $currentGateway = Cache::get('gateway_current', $defaultGateway);
        $counter = (int) Cache::get('gateway_switch_counter', 0);

        // Counter reached the threshold — rotate to next gateway
        if ($counter >= $switchEvery) {
            $currentIndex = array_search($currentGateway, $gatewayNames);

            if ($currentIndex === false) {
                return $defaultGateway;
            }

            $nextGateway = $gatewayNames[($currentIndex + 1) % count($gatewayNames)];
            Cache::put('gateway_current', $nextGateway);
            Cache::put('gateway_switch_counter', 0);

            return $nextGateway;
        }

        return $currentGateway;
    }

    /**
     * Record that a payment was processed through a gateway.
     * Call this after each successful payment initiation to drive the auto-switch counter.
     *
     * @param string $gateway The gateway name that was used
     */
    public static function recordGatewayUsage(string $gateway): void
    {
        $defaultGateway = config('services.payment_gateway', 'yopayments');
        $currentGateway = Cache::get('gateway_current', $defaultGateway);

        if ($gateway === $currentGateway) {
            $counter = (int) Cache::get('gateway_switch_counter', 0);
            Cache::put('gateway_switch_counter', $counter + 1);
            Cache::put('gateway_current', $currentGateway);
        }
    }

    /**
     * Instantiate a gateway by name.
     *
     * @param string $gateway
     * @return PaymentGatewayInterface
     * @throws InvalidArgumentException
     */
    protected static function instantiate(string $gateway): PaymentGatewayInterface
    {
        $gatewayName = strtolower(trim($gateway));

        if (!isset(self::$gateways[$gatewayName])) {
            throw new InvalidArgumentException(
                "Unsupported payment gateway: {$gatewayName}. Supported gateways: " . implode(', ', array_keys(self::$gateways))
            );
        }

        return new self::$gateways[$gatewayName]();
    }
}
