<?php

declare(strict_types=1);

namespace QS\Modules\Bitacora\Infrastructure\Persistence;

use DateTimeImmutable;
use QS\Modules\Bitacora\Domain\Entity\Bitacora;
use QS\Modules\Bitacora\Domain\Entity\RoutePlan;
use QS\Modules\Bitacora\Domain\Entity\TravelNote;
use QS\Modules\Bitacora\Domain\Repository\BitacoraRepository;
use QS\Modules\Bitacora\Domain\ValueObject\PickupPoint;
use QS\Modules\Bitacora\Domain\ValueObject\ServiceAddress;
use QS\Modules\Bitacora\Domain\ValueObject\TravelDuration;
use RuntimeException;

final class CptBitacoraRepository implements BitacoraRepository
{
    public function __construct(private readonly MetaFieldMapper $metaFieldMapper)
    {
    }

    public function findAll(): array
    {
        if (! function_exists('get_posts')) {
            return [];
        }

        $posts = get_posts([
            'post_type' => 'qs_bitacora',
            'post_status' => ['publish', 'private', 'draft'],
            'numberposts' => -1,
            'orderby' => 'date',
            'order' => 'DESC',
        ]);

        if (! is_array($posts)) {
            return [];
        }

        return array_map(
            fn (\WP_Post $post): Bitacora => $this->metaFieldMapper->fromPost($post),
            array_values(array_filter($posts, static fn ($post): bool => $post instanceof \WP_Post))
        );
    }

    public function findById(int $id): ?Bitacora
    {
        if (! function_exists('get_post')) {
            return null;
        }

        $post = get_post($id);

        if (! $post instanceof \WP_Post || $post->post_type !== 'qs_bitacora') {
            return null;
        }

        return $this->metaFieldMapper->fromPost($post);
    }

    public function save(Bitacora $bitacora): Bitacora
    {
        if (! function_exists('wp_insert_post') || ! function_exists('update_post_meta')) {
            throw new RuntimeException('WordPress runtime is not available.');
        }

        $payload = [
            'post_type' => 'qs_bitacora',
            'post_status' => 'publish',
            'post_title' => $this->metaFieldMapper->postTitle($bitacora),
            'post_content' => $bitacora->notasLogisticas() ?? '',
        ];

        $result = $bitacora->id() === null
            ? wp_insert_post($payload, true)
            : wp_update_post(['ID' => $bitacora->id()] + $payload, true);

        if ($result instanceof \WP_Error || ! is_int($result)) {
            throw new RuntimeException('Could not persist bitacora.');
        }

        foreach ($this->metaFieldMapper->toMetaArray($bitacora) as $key => $value) {
            update_post_meta($result, $key, $value);
        }

        update_post_meta($result, 'qs_bitacora_notes', $this->metaFieldMapper->serializeNotes($bitacora->notes()));

        $saved = $this->findById($result);

        if ($saved === null) {
            throw new RuntimeException('Bitacora was not found after persisting.');
        }

        return $saved;
    }

    public function addNote(int $bitacoraId, TravelNote $note): ?Bitacora
    {
        $existing = $this->findById($bitacoraId);

        if ($existing === null) {
            return null;
        }

        $notes = $existing->notes();
        $notes[] = $note;

        return $this->save(
            new Bitacora(
                $existing->id(),
                $existing->fechaServicio(),
                $existing->tipoServicio(),
                $existing->muaId(),
                $existing->estilistaId(),
                $existing->clientaNombre(),
                new ServiceAddress($existing->serviceAddress()->value()),
                new RoutePlan(
                    new PickupPoint($existing->routePlan()->pickupPoint()->value()),
                    $existing->routePlan()->pickupOrder(),
                    new TravelDuration($existing->routePlan()->travelDuration()->minutes()),
                    $existing->routePlan()->arrivalTime()
                ),
                $existing->notasLogisticas(),
                $existing->costoStaffClp(),
                $existing->precioClienteClp(),
                $notes,
                $existing->createdAt(),
                new DateTimeImmutable('now')
            )
        );
    }
}
