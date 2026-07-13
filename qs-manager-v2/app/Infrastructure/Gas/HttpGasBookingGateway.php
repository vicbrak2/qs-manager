<?php

declare(strict_types=1);

namespace QSManager\Infrastructure\Gas;

use QSManager\Application\Booking\BookingGasGateway;
use QSManager\Application\Booking\GasSyncResult;
use QSManager\Domain\Booking\Booking;

final class HttpGasBookingGateway implements BookingGasGateway
{
    public function __construct(
        private readonly ?string $webAppUrl,
        private readonly GasBookingPayloadMapper $payloadMapper,
    ) {
    }

    public function sync(Booking $booking): GasSyncResult
    {
        $payload = $this->payloadMapper->toPayload($booking);

        if ($this->webAppUrl === null || trim($this->webAppUrl) === '') {
            return GasSyncResult::skipped(
                $payload,
                'GAS_WEBAPP_URL is not configured. Local zero-cost mode skipped the external sync.'
            );
        }

        $context = stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => "Content-Type: application/json\r\n",
                'content' => json_encode($payload, JSON_THROW_ON_ERROR),
                'ignore_errors' => true,
                'timeout' => 8,
            ],
        ]);

        try {
            $rawResponse = @file_get_contents($this->webAppUrl, false, $context);
        } catch (\Throwable $exception) {
            $rawResponse = false;
        }
        
        if ($rawResponse === false) {
            return GasSyncResult::failed($payload, 'Failed to call GAS web app.');
        }

        $decoded = json_decode($rawResponse, true);
        if (!is_array($decoded)) {
            return GasSyncResult::failed($payload, 'GAS web app returned a non-JSON response.', [
                'raw' => $rawResponse,
            ]);
        }

        if (($decoded['ok'] ?? false) !== true) {
            return GasSyncResult::failed($payload, (string) ($decoded['error'] ?? 'GAS web app returned an error.'), $decoded);
        }

        return GasSyncResult::synced($payload, $decoded);
    }
}
