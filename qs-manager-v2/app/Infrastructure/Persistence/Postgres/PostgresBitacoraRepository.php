<?php

declare(strict_types=1);

namespace QSManager\Infrastructure\Persistence\Postgres;

use DateTimeImmutable;
use PDO;
use QSManager\Domain\Bitacora\Bitacora;
use QSManager\Domain\Bitacora\BitacoraRepository;
use QSManager\Domain\Bitacora\PickupPoint;
use QSManager\Domain\Bitacora\RoutePlan;
use QSManager\Domain\Bitacora\ServiceAddress;
use QSManager\Domain\Bitacora\TravelDuration;
use QSManager\Domain\Bitacora\TravelItinerary;
use QSManager\Domain\Bitacora\TravelNote;

final class PostgresBitacoraRepository implements BitacoraRepository
{
    public function __construct(private readonly PDO $connection)
    {
    }

    public function findAll(): array
    {
        $rows = $this->connection
            ->query('select * from qs_bitacoras order by id desc')
            ->fetchAll(PDO::FETCH_ASSOC);

        if ($rows === []) {
            return [];
        }

        $notes = $this->notesFor(array_map(static fn (array $row): int => (int) $row['id'], $rows));

        return array_map(
            fn (array $row): Bitacora => $this->fromRow($row, $notes[(int) $row['id']] ?? []),
            $rows
        );
    }

    public function findById(int $id): ?Bitacora
    {
        $statement = $this->connection->prepare('select * from qs_bitacoras where id = :id');
        $statement->execute(['id' => $id]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);

        if ($row === false) {
            return null;
        }

        return $this->fromRow($row, $this->notesFor([$id])[$id] ?? []);
    }

    public function findByBookingId(int $bookingId): ?Bitacora
    {
        $statement = $this->connection->prepare('select * from qs_bitacoras where booking_id = :booking_id');
        $statement->execute(['booking_id' => $bookingId]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);

        if ($row === false) {
            return null;
        }

        $id = (int) $row['id'];
        return $this->fromRow($row, $this->notesFor([$id])[$id] ?? []);
    }

    public function save(Bitacora $bitacora): Bitacora
    {
        $params = [
            'booking_id' => $bitacora->bookingId(),
            'booking_external_id' => $bitacora->bookingExternalId(),
            'fecha_servicio' => $bitacora->fechaServicio(),
            'tipo_servicio' => $bitacora->tipoServicio(),
            'mua_id' => $bitacora->muaId(),
            'estilista_id' => $bitacora->estilistaId(),
            'professional_ids' => json_encode($bitacora->toArray()['professional_ids'], JSON_THROW_ON_ERROR),
            'clienta_nombre' => $bitacora->clientaNombre(),
            'direccion_servicio' => $bitacora->serviceAddress()->value(),
            'punto_salida' => $bitacora->routePlan()->pickupPoint()->value(),
            'orden_recogida' => $bitacora->routePlan()->pickupOrder(),
            'tiempo_traslado_min' => $bitacora->routePlan()->travelDuration()->minutes(),
            'hora_llegada' => $bitacora->routePlan()->arrivalTime(),
            'hora_inicio_servicio' => $bitacora->horaInicioServicio(),
            'hora_fin_servicio' => $bitacora->horaFinServicio(),
            'tramos' => json_encode($bitacora->itinerario()->toArray(), JSON_THROW_ON_ERROR),
            'objetivo' => $bitacora->objetivo(),
            'consideraciones' => $bitacora->consideraciones(),
            'notas_logisticas' => $bitacora->notasLogisticas(),
            'costo_staff_clp' => $bitacora->costoStaffClp(),
            'precio_cliente_clp' => $bitacora->precioClienteClp(),
        ];

        if ($bitacora->id() === null) {
            $statement = $this->connection->prepare(
                'insert into qs_bitacoras (
                    booking_id, booking_external_id, fecha_servicio, tipo_servicio, mua_id, estilista_id, professional_ids, clienta_nombre,
                    direccion_servicio, punto_salida, orden_recogida, tiempo_traslado_min,
                    hora_llegada, hora_inicio_servicio, hora_fin_servicio, tramos, objetivo,
                    consideraciones, notas_logisticas, costo_staff_clp, precio_cliente_clp
                ) values (
                    :booking_id, :booking_external_id, :fecha_servicio, :tipo_servicio, :mua_id, :estilista_id, cast(:professional_ids as jsonb), :clienta_nombre,
                    :direccion_servicio, :punto_salida, :orden_recogida, :tiempo_traslado_min,
                    :hora_llegada, :hora_inicio_servicio, :hora_fin_servicio, :tramos, :objetivo,
                    :consideraciones, :notas_logisticas, :costo_staff_clp, :precio_cliente_clp
                ) returning id'
            );
            $statement->execute($params);
            $id = (int) $statement->fetchColumn();
        } else {
            $id = $bitacora->id();
            $this->connection->prepare(
                'update qs_bitacoras set
                    booking_id = :booking_id,
                    booking_external_id = :booking_external_id,
                    fecha_servicio = :fecha_servicio,
                    tipo_servicio = :tipo_servicio,
                    mua_id = :mua_id,
                    estilista_id = :estilista_id,
                    professional_ids = cast(:professional_ids as jsonb),
                    clienta_nombre = :clienta_nombre,
                    direccion_servicio = :direccion_servicio,
                    punto_salida = :punto_salida,
                    orden_recogida = :orden_recogida,
                    tiempo_traslado_min = :tiempo_traslado_min,
                    hora_llegada = :hora_llegada,
                    hora_inicio_servicio = :hora_inicio_servicio,
                    hora_fin_servicio = :hora_fin_servicio,
                    tramos = :tramos,
                    objetivo = :objetivo,
                    consideraciones = :consideraciones,
                    notas_logisticas = :notas_logisticas,
                    costo_staff_clp = :costo_staff_clp,
                    precio_cliente_clp = :precio_cliente_clp,
                    updated_at = now()
                 where id = :id'
            )->execute($params + ['id' => $id]);
        }

        $saved = $this->findById($id);
        if ($saved === null) {
            throw new \RuntimeException('Failed to retrieve saved bitacora.');
        }

        return $saved;
    }

    public function addNote(int $bitacoraId, TravelNote $note): ?Bitacora
    {
        if ($this->findById($bitacoraId) === null) {
            return null;
        }

        $this->connection->prepare(
            'insert into qs_bitacora_notes (bitacora_id, message, author_user_id, created_at)
             values (:bitacora_id, :message, :author_user_id, :created_at)'
        )->execute([
            'bitacora_id' => $bitacoraId,
            'message' => $note->message(),
            'author_user_id' => $note->authorUserId(),
            'created_at' => $note->createdAt()->format(DateTimeImmutable::ATOM),
        ]);

        $this->connection->prepare('update qs_bitacoras set updated_at = now() where id = :id')
            ->execute(['id' => $bitacoraId]);

        return $this->findById($bitacoraId);
    }

    /**
     * @param list<int> $bitacoraIds
     * @return array<int, list<TravelNote>>
     */
    private function notesFor(array $bitacoraIds): array
    {
        $placeholders = implode(',', array_fill(0, count($bitacoraIds), '?'));
        $statement = $this->connection->prepare(
            "select bitacora_id, message, author_user_id, created_at
             from qs_bitacora_notes
             where bitacora_id in ($placeholders)
             order by id asc"
        );
        $statement->execute($bitacoraIds);

        $notes = [];
        foreach ($statement->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $notes[(int) $row['bitacora_id']][] = new TravelNote(
                (string) $row['message'],
                $row['author_user_id'] === null ? null : (int) $row['author_user_id'],
                new DateTimeImmutable((string) $row['created_at']),
            );
        }

        return $notes;
    }

    /**
     * @param array<string, mixed> $row
     * @param list<TravelNote> $notes
     */
    private function fromRow(array $row, array $notes): Bitacora
    {
        return new Bitacora(
            (int) $row['id'],
            $row['booking_id'] === null ? null : (int) $row['booking_id'],
            $row['booking_external_id'] === null ? null : (string) $row['booking_external_id'],
            (string) $row['fecha_servicio'],
            (string) $row['tipo_servicio'],
            $row['mua_id'] === null ? null : (int) $row['mua_id'],
            $row['estilista_id'] === null ? null : (int) $row['estilista_id'],
            (string) $row['clienta_nombre'],
            new ServiceAddress((string) $row['direccion_servicio']),
            new RoutePlan(
                new PickupPoint((string) $row['punto_salida']),
                $row['orden_recogida'] === null ? null : (string) $row['orden_recogida'],
                new TravelDuration((int) $row['tiempo_traslado_min']),
                $row['hora_llegada'] === null ? null : (string) $row['hora_llegada'],
            ),
            $row['hora_inicio_servicio'] === null ? null : (string) $row['hora_inicio_servicio'],
            $row['hora_fin_servicio'] === null ? null : (string) $row['hora_fin_servicio'],
            TravelItinerary::fromArray(json_decode((string) ($row['tramos'] ?? '[]'), true) ?: []),
            $row['objetivo'] === null ? null : (string) $row['objetivo'],
            $row['consideraciones'] === null ? null : (string) $row['consideraciones'],
            $row['notas_logisticas'] === null ? null : (string) $row['notas_logisticas'],
            (int) $row['costo_staff_clp'],
            (int) $row['precio_cliente_clp'],
            $notes,
            new DateTimeImmutable((string) $row['created_at']),
            new DateTimeImmutable((string) $row['updated_at']),
            $this->professionalIdsFromRow($row),
        );
    }

    /**
     * @param array<string, mixed> $row
     * @return list<int>
     */
    private function professionalIdsFromRow(array $row): array
    {
        $decoded = json_decode((string) ($row['professional_ids'] ?? '[]'), true);
        if (!is_array($decoded)) {
            return [];
        }

        $ids = [];
        foreach ($decoded as $id) {
            if (filter_var($id, FILTER_VALIDATE_INT) !== false && (int) $id > 0) {
                $ids[] = (int) $id;
            }
        }

        return array_values(array_unique($ids));
    }
}
