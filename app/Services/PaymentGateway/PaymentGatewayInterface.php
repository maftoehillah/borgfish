<?php

namespace App\Services\PaymentGateway;

interface PaymentGatewayInterface
{
    public function name(): string;

    public function availableMethods(): array;

    public function createPayment(array $payload): array;

    public function verifyCallback(string $rawBody, array $headers): bool;

    public function parseCallback(string $rawBody, array $headers): array;

    public function fetchPayment(string $providerTransactionId): array;
}
