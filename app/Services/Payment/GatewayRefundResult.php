<?php

namespace App\Services\Payment;

class GatewayRefundResult
{
    public function __construct(
        public bool $success,
        public string $gateway,
        public ?string $gatewayRefundId = null,
        public ?array $response = null,
        public ?string $errorMessage = null,
    ) {}

    public static function failed(string $gateway, string $message, ?array $response = null): self
    {
        return new self(
            success: false,
            gateway: $gateway,
            response: $response,
            errorMessage: $message,
        );
    }
}
