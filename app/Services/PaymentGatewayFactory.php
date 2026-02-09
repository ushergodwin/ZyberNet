<?php

namespace App\Services;

use App\Contracts\PaymentGatewayInterface;
use App\Models\Transaction;
use App\Services\Gateways\CinemaUGGateway;
use App\Services\Gateways\YoPaymentsGateway;
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
     * Looks at the last N transactions (where N = switch_every). If all N
     * used the same gateway, switches to the next one in the list.
     *
     * @return string Gateway name
     */
    protected static function resolveAutoSwitchGateway(): string
    {
        $switchEvery = (int) config('services.payment_gateway_switch_every', 10);
        $gatewayNames = array_keys(self::$gateways);
        $defaultGateway = config('services.payment_gateway', 'yopayments');

        $recentGateways = Transaction::whereNotNull('gateway')
            ->orderBy('id', 'desc')
            ->take($switchEvery)
            ->pluck('gateway');

        // Not enough transactions yet — use default
        if ($recentGateways->count() < $switchEvery) {
            return $defaultGateway;
        }

        // If the last N transactions all used the same gateway, rotate
        if ($recentGateways->unique()->count() === 1) {
            $currentGateway = $recentGateways->first();
            $currentIndex = array_search($currentGateway, $gatewayNames);

            if ($currentIndex === false) {
                return $defaultGateway;
            }

            $nextIndex = ($currentIndex + 1) % count($gatewayNames);
            return $gatewayNames[$nextIndex];
        }

        // Mixed gateways in the recent batch — continue with the most recent one
        return $recentGateways->first();
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
