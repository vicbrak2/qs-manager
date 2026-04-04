<?php

declare(strict_types=1);

namespace QS\Modules\Bitacora\Infrastructure\Persistence;

use DateTimeImmutable;
use QS\Modules\Bitacora\Domain\Entity\Bitacora;
use QS\Modules\Bitacora\Domain\Entity\RoutePlan;
use QS\Modules\Bitacora\Domain\Entity\TravelNote;
use QS\Modules\Bitacora\Domain\ValueObject\PickupPoint;
use QS\Modules\Bitacora\Domain\ValueObject\ServiceAddress;
use QS\Modules\Bitacora\Domain\ValueObject\TravelDuration;

final class MetaFieldMapper
{
    /**
     * @return array<string, int|string|null>
     */
    public function toMetaArray(Bitacora $bitacora): array
    {
        return [
            'fecha_servicio' => $bitacora->fechaServicio(),
            'tipo_servicio' => $bitacora->tipoServicio(),
            'mua_id' => $bitacora->muaId(),
            'estilista_id' => $bitacora->estilistaId(),
            'clienta_nombre' => $bitacora->clientaNombre(),
            'direccion_servicio' => $bitacora->serviceAddress()->value(),
            'hora_llegada' => $bitacora->routePlan()->arrivalTime(),
            'punto_salida' => $bitacora->routePlan()->pickupPoint()->value(),
            'orden_recogida' => $bitacora->routePlan()->pickupOrder(),
            'tiempo_traslado_min' => $bitacora->routePlan()->travelDuration()->minutes(),
            'notas_logisticas' => $bitacora->notasLogisticas(),
            'costo_staff_clp' => $bitacora->costoStaffClp(),
            'precio_cliente_clp' => $bitacora->precioClienteClp(),
        ];
    }

    public function postTitle(Bitacora $bitacora): string
    {
        return sprintf(
            '%s - %s - %s',
            $bitacora->fechaServicio(),
            $bitacora->clientaNombre(),
            $bitacora->tipoServicio()
        );
    }

    public function fromPost(\WP_Post $post): Bitacora
    {
        $meta = function_exists('get_post_meta')
            ? [
                'fecha_servicio' => $this->stringMeta($post->ID, 'fecha_servicio'),
                'tipo_servicio' => $this->stringMeta($post->ID, 'tipo_servicio'),
                'mua_id' => $this->intMeta($post->ID, 'mua_id'),
                'estilista_id' => $this->intMeta($post->ID, 'estilista_id'),
                'clienta_nombre' => $this->stringMeta($post->ID, 'clienta_nombre'),
                'direccion_servicio' => $this->stringMeta($post->ID, 'direccion_servicio'),
                'hora_llegada' => $this->nullableStringMeta($post->ID, 'hora_llegada'),
                'punto_salida' => $this->stringMeta($post->ID, 'punto_salida'),
                'orden_recogida' => $this->nullableStringMeta($post->ID, 'orden_recogida'),
                'tiempo_traslado_min' => $this->intMeta($post->ID, 'tiempo_traslado_min') ?? 0,
                'notas_logisticas' => $this->nullableStringMeta($post->ID, 'notas_logisticas'),
                'costo_staff_clp' => $this->intMeta($post->ID, 'costo_staff_clp') ?? 0,
                'precio_cliente_clp' => $this->intMeta($post->ID, 'precio_cliente_clp') ?? 0,
                'notes' => $this->notes($post->ID),
            ]
            : [];

        return new Bitacora(
            (int) $post->ID,
            (string) ($meta['fecha_servicio'] ?? '1970-01-01'),
            (string) ($meta['tipo_servicio'] ?? $post->post_title),
            $meta['mua_id'] ?? null,
            $meta['estilista_id'] ?? null,
            (string) ($meta['clienta_nombre'] ?? $post->post_title),
            new ServiceAddress((string) ($meta['direccion_servicio'] ?? 'Direccion por definir')),
            new RoutePlan(
                new PickupPoint((string) ($meta['punto_salida'] ?? 'Punto por definir')),
                is_string($meta['orden_recogida'] ?? null) ? $meta['orden_recogida'] : null,
                new TravelDuration((int) ($meta['tiempo_traslado_min'] ?? 0)),
                is_string($meta['hora_llegada'] ?? null) ? $meta['hora_llegada'] : null
            ),
            is_string($meta['notas_logisticas'] ?? null) ? $meta['notas_logisticas'] : null,
            (int) ($meta['costo_staff_clp'] ?? 0),
            (int) ($meta['precio_cliente_clp'] ?? 0),
            is_array($meta['notes'] ?? null) ? $meta['notes'] : [],
            new DateTimeImmutable($post->post_date_gmt !== '' ? $post->post_date_gmt : $post->post_date),
            new DateTimeImmutable($post->post_modified_gmt !== '' ? $post->post_modified_gmt : $post->post_modified)
        );
    }

    /**
     * @param array<int, TravelNote> $notes
     * @return array<int, array<string, int|string|null>>
     */
    public function serializeNotes(array $notes): array
    {
        return array_map(
            static fn (TravelNote $note): array => $note->toArray(),
            $notes
        );
    }

    private function stringMeta(int $postId, string $key): string
    {
        $value = get_post_meta($postId, $key, true);

        return is_scalar($value) ? trim((string) $value) : '';
    }

    private function nullableStringMeta(int $postId, string $key): ?string
    {
        $value = $this->stringMeta($postId, $key);

        return $value === '' ? null : $value;
    }

    private function intMeta(int $postId, string $key): ?int
    {
        $value = get_post_meta($postId, $key, true);

        if ($value === '' || $value === null) {
            return null;
        }

        return is_numeric($value) ? (int) $value : null;
    }

    /**
     * @return array<int, TravelNote>
     */
    private function notes(int $postId): array
    {
        $value = get_post_meta($postId, 'qs_bitacora_notes', true);

        if (! is_array($value)) {
            return [];
        }

        $notes = [];

        foreach ($value as $item) {
            if (! is_array($item) || ! isset($item['message']) || ! is_string($item['message'])) {
                continue;
            }

            $createdAt = isset($item['created_at']) && is_string($item['created_at'])
                ? new DateTimeImmutable($item['created_at'])
                : new DateTimeImmutable('now');
            $authorUserId = isset($item['author_user_id']) && is_numeric($item['author_user_id'])
                ? (int) $item['author_user_id']
                : null;

            $notes[] = new TravelNote($item['message'], $authorUserId, $createdAt);
        }

        return $notes;
    }
}
