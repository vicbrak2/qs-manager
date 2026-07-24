<?php

declare(strict_types=1);

namespace QSManager\Infrastructure\Gas;

use RuntimeException;
use QSManager\Application\ServicesCatalog\ServiceCatalogGateway;

final class HttpGasServiceCatalogGateway implements ServiceCatalogGateway
{
    public function __construct(
        private readonly string $webAppUrl,
        private readonly string $sharedSecret,
    ) {
    }

    public function create(array $service, string $idempotencyKey): array
    {
        $payload = [
            'action' => 'create_service',
            'api_key' => $this->sharedSecret,
            'idempotency_key' => $idempotencyKey,
            'service' => $service,
        ];

        $context = stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => "Content-Type: application/json\r\n",
                'content' => json_encode($payload, JSON_THROW_ON_ERROR),
                'ignore_errors' => true,
                'timeout' => 15,
            ],
        ]);

        try {
            $rawResponse = @file_get_contents($this->webAppUrl, false, $context);
        } catch (\Throwable) {
            $rawResponse = false;
        }

        if ($rawResponse === false) {
            throw new RuntimeException('No fue posible publicar el servicio en Google Sheets.');
        }

        $decoded = json_decode($rawResponse, true);
        if (!is_array($decoded)) {
            throw new RuntimeException('GAS devolvio una respuesta no valida.');
        }

        if (($decoded['ok'] ?? false) !== true || !is_array($decoded['result'] ?? null)) {
            throw new RuntimeException((string) ($decoded['error'] ?? 'GAS rechazo la publicacion del servicio.'));
        }

        return $decoded['result'];
    }
}
