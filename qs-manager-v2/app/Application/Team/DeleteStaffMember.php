<?php

declare(strict_types=1);

namespace QSManager\Application\Team;

use DateTimeImmutable;
use QSManager\Domain\Team\StaffRepository;

/**
 * Baja de una profesional. La unica razon para impedirla es que tenga
 * servicios PENDIENTES: alguien la esta esperando en una fecha futura y
 * borrarla dejaria ese servicio sin equipo. Los servicios ya realizados no
 * bloquean nada -- quedan desvinculados (FK on delete set null) y conservan
 * su historial de montos y fechas.
 */
final class DeleteStaffMember
{
    public function __construct(private readonly StaffRepository $staff)
    {
    }

    /**
     * @return array{deleted: bool, found: bool, pending: list<array{scheduled_for: string, customer_name: ?string}>}
     */
    public function execute(int $id): array
    {
        if ($this->staff->findById($id) === null) {
            return ['deleted' => false, 'found' => false, 'pending' => []];
        }

        $pending = $this->staff->pendingServices($id);
        if ($pending !== []) {
            return ['deleted' => false, 'found' => true, 'pending' => $pending];
        }

        return ['deleted' => $this->staff->delete($id), 'found' => true, 'pending' => []];
    }

    /**
     * Mensaje para el usuario: dice cuantos servicios la retienen y cuando es
     * el proximo, para que sepa que tiene que reasignar antes de borrar.
     *
     * @param list<array{scheduled_for: string, customer_name: ?string}> $pending
     */
    public static function pendingMessage(array $pending): string
    {
        $proximo = $pending[0];
        $fecha = (new DateTimeImmutable($proximo['scheduled_for']))->format('d-m-Y');
        $clienta = $proximo['customer_name'] ?? 'sin clienta';
        $total = count($pending);

        return $total === 1
            ? sprintf('No se puede borrar: tiene un servicio pendiente el %s (%s). Reasignalo primero.', $fecha, $clienta)
            : sprintf(
                'No se puede borrar: tiene %d servicios pendientes, el proximo el %s (%s). Reasignalos primero.',
                $total,
                $fecha,
                $clienta
            );
    }
}
