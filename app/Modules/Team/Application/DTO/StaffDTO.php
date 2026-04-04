<?php

declare(strict_types=1);

namespace QS\Modules\Team\Application\DTO;

use QS\Modules\Team\Domain\Entity\StaffMember;

final class StaffDTO
{
    public function __construct(private readonly StaffMember $staffMember)
    {
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->staffMember->id()->value(),
            'nombre' => $this->staffMember->nombre(),
            'apellido' => $this->staffMember->apellido(),
            'full_name' => $this->staffMember->fullName(),
            'especialidad' => $this->staffMember->specialty()->value,
            'costo_hora_clp' => $this->staffMember->costoHoraClp(),
            'contacto_whatsapp' => $this->staffMember->contactoWhatsapp(),
            'estado' => $this->staffMember->estado(),
            'created_at' => $this->staffMember->createdAt()->format(DATE_ATOM),
            'updated_at' => $this->staffMember->updatedAt()->format(DATE_ATOM),
        ];
    }
}
