<?php

declare(strict_types=1);

namespace QSManager\Domain\Booking;

use DateTimeImmutable;
use QSManager\Domain\ServicesCatalog\ServiceId;
use QSManager\Domain\Team\StaffId;

final class Booking
{
    private function __construct(
        private readonly ?BookingId $id,
        private readonly ?ServiceId $serviceId,
        private readonly ?StaffId $staffId,
        private readonly ?string $customerName,
        private readonly ?string $customerPhone,
        private readonly ?DateTimeImmutable $scheduledFor,
        private readonly BookingStatus $status,
        private readonly ?string $serviceName,
        private readonly ?string $staffName,
        private readonly ?string $address,
        private readonly ?string $comuna,
        private readonly ?float $serviceValue,
        private readonly ?float $transferValue,
        private readonly ?float $depositAmount,
        private readonly ?float $totalService,
        private readonly ?float $balanceDue,
        private readonly ?string $paymentStatus,
        private readonly ?string $serviceStatus,
        private readonly ?string $contractId,
        private readonly ?string $milestone,
        private readonly ?string $cashGroup,
        private readonly ?string $calendarEventId,
        private readonly ?string $agendaReference,
        private readonly ?string $sheetExternalId,
        private readonly ?string $sourceSheet,
        private readonly ?int $sourceRow,
        private readonly ?int $bitacoraId,
        private readonly ?int $estilistaId,
        private readonly ?string $estilistaName,
        private readonly ?string $gasLastSyncStatus,
        private readonly ?string $gasLastSyncMessage,
        private readonly bool $hasTransferReceipt,
        private readonly ?string $transferReceiptMime,
        private readonly ?string $transferReceiptFilename,
        private readonly ?int $transferReceiptSize,
    ) {
    }

    public static function create(
        ?int $serviceId,
        ?int $staffId,
        ?string $customerName,
        ?string $customerPhone,
        ?DateTimeImmutable $scheduledFor,
        string $status,
        ?string $address = null,
        ?string $comuna = null,
        ?float $serviceValue = null,
        ?float $transferValue = null,
        ?float $depositAmount = null,
        ?float $totalService = null,
        ?float $balanceDue = null,
        ?string $paymentStatus = null,
        ?string $serviceStatus = null,
        ?string $contractId = null,
        ?string $milestone = null,
        ?string $cashGroup = null,
    ): self {
        return new self(
            null,
            $serviceId !== null ? ServiceId::fromInt($serviceId) : null,
            $staffId !== null ? StaffId::fromInt($staffId) : null,
            self::normalizeString($customerName),
            self::normalizeString($customerPhone),
            $scheduledFor,
            BookingStatus::fromString($status),
            null,
            null,
            self::normalizeString($address),
            self::normalizeString($comuna),
            $serviceValue,
            $transferValue,
            $depositAmount,
            $totalService,
            $balanceDue,
            self::normalizeString($paymentStatus),
            self::normalizeString($serviceStatus),
            self::normalizeString($contractId),
            self::normalizeString($milestone),
            self::normalizeString($cashGroup),
            null,
            null,
            null,
            null,
            null,
            null,
            null,
            null,
            null,
            null,
            false,
            null,
            null,
            null,
        );
    }

    public static function fromPersistence(
        int $id,
        ?int $serviceId,
        ?int $staffId,
        ?string $customerName,
        ?string $customerPhone,
        ?string $scheduledFor,
        string $status,
        ?string $serviceName = null,
        ?string $staffName = null,
        ?string $address = null,
        ?string $comuna = null,
        ?float $serviceValue = null,
        ?float $transferValue = null,
        ?float $depositAmount = null,
        ?float $totalService = null,
        ?float $balanceDue = null,
        ?string $paymentStatus = null,
        ?string $serviceStatus = null,
        ?string $contractId = null,
        ?string $milestone = null,
        ?string $cashGroup = null,
        ?string $calendarEventId = null,
        ?string $agendaReference = null,
        ?string $sheetExternalId = null,
        ?string $sourceSheet = null,
        ?int $sourceRow = null,
        ?int $bitacoraId = null,
        ?int $estilistaId = null,
        ?string $estilistaName = null,
        ?string $gasLastSyncStatus = null,
        ?string $gasLastSyncMessage = null,
        bool $hasTransferReceipt = false,
        ?string $transferReceiptMime = null,
        ?string $transferReceiptFilename = null,
        ?int $transferReceiptSize = null,
    ): self {
        return new self(
            BookingId::fromInt($id),
            $serviceId !== null ? ServiceId::fromInt($serviceId) : null,
            $staffId !== null ? StaffId::fromInt($staffId) : null,
            self::normalizeString($customerName),
            self::normalizeString($customerPhone),
            $scheduledFor !== null ? new DateTimeImmutable($scheduledFor) : null,
            BookingStatus::fromString($status),
            self::normalizeString($serviceName),
            self::normalizeString($staffName),
            self::normalizeString($address),
            self::normalizeString($comuna),
            $serviceValue,
            $transferValue,
            $depositAmount,
            $totalService,
            $balanceDue,
            self::normalizeString($paymentStatus),
            self::normalizeString($serviceStatus),
            self::normalizeString($contractId),
            self::normalizeString($milestone),
            self::normalizeString($cashGroup),
            self::normalizeString($calendarEventId),
            self::normalizeString($agendaReference),
            self::normalizeString($sheetExternalId),
            self::normalizeString($sourceSheet),
            $sourceRow,
            $bitacoraId,
            $estilistaId,
            self::normalizeString($estilistaName),
            self::normalizeString($gasLastSyncStatus),
            self::normalizeString($gasLastSyncMessage),
            $hasTransferReceipt,
            self::normalizeString($transferReceiptMime),
            self::normalizeString($transferReceiptFilename),
            $transferReceiptSize,
        );
    }

    public function id(): ?BookingId
    {
        return $this->id;
    }

    public function serviceId(): ?ServiceId
    {
        return $this->serviceId;
    }

    public function staffId(): ?StaffId
    {
        return $this->staffId;
    }

    public function customerName(): ?string
    {
        return $this->customerName;
    }

    public function customerPhone(): ?string
    {
        return $this->customerPhone;
    }

    public function scheduledFor(): ?DateTimeImmutable
    {
        return $this->scheduledFor;
    }

    public function status(): BookingStatus
    {
        return $this->status;
    }

    public function serviceName(): ?string
    {
        return $this->serviceName;
    }

    public function staffName(): ?string
    {
        return $this->staffName;
    }

    public function address(): ?string
    {
        return $this->address;
    }

    public function comuna(): ?string
    {
        return $this->comuna;
    }

    public function serviceValue(): ?float
    {
        return $this->serviceValue;
    }

    public function transferValue(): ?float
    {
        return $this->transferValue;
    }

    public function depositAmount(): ?float
    {
        return $this->depositAmount;
    }

    public function totalService(): ?float
    {
        return $this->totalService;
    }

    public function balanceDue(): ?float
    {
        return $this->balanceDue;
    }

    public function paymentStatus(): ?string
    {
        return $this->paymentStatus;
    }

    public function serviceStatus(): ?string
    {
        return $this->serviceStatus;
    }

    public function contractId(): ?string
    {
        return $this->contractId;
    }

    public function milestone(): ?string
    {
        return $this->milestone;
    }

    public function cashGroup(): ?string
    {
        return $this->cashGroup;
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id?->value(),
            'service_id' => $this->serviceId?->value(),
            'staff_id' => $this->staffId?->value(),
            'customer_name' => $this->customerName,
            'customer_phone' => $this->customerPhone,
            'scheduled_for' => $this->scheduledFor?->format(DateTimeImmutable::ATOM),
            'status' => $this->status->value(),
            'service_name' => $this->serviceName,
            'staff_name' => $this->staffName,
            'address' => $this->address,
            'comuna' => $this->comuna,
            'service_value' => $this->serviceValue,
            'transfer_value' => $this->transferValue,
            'deposit_amount' => $this->depositAmount,
            'total_service' => $this->totalService,
            'balance_due' => $this->balanceDue,
            'payment_status' => $this->paymentStatus,
            'service_status' => $this->serviceStatus,
            'contract_id' => $this->contractId,
            'milestone' => $this->milestone,
            'cash_group' => $this->cashGroup,
            'calendar_event_id' => $this->calendarEventId,
            'agenda_reference' => $this->agendaReference,
            'sheet_external_id' => $this->sheetExternalId,
            'source_sheet' => $this->sourceSheet,
            'source_row' => $this->sourceRow,
            'bitacora_id' => $this->bitacoraId,
            'estilista_id' => $this->estilistaId,
            'estilista_name' => $this->estilistaName,
            'gas_last_sync_status' => $this->gasLastSyncStatus,
            'gas_last_sync_message' => $this->gasLastSyncMessage,
            'has_transfer_receipt' => $this->hasTransferReceipt,
            'transfer_receipt_mime' => $this->transferReceiptMime,
            'transfer_receipt_filename' => $this->transferReceiptFilename,
            'transfer_receipt_size' => $this->transferReceiptSize,
        ];
    }

    private static function normalizeString(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim($value);

        return $value === '' ? null : $value;
    }
}
