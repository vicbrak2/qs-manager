<?php

declare(strict_types=1);

namespace QSManager\Interfaces\Http\Validation;

use DateTimeImmutable;
use QSManager\Domain\ServicesCatalog\ServiceRepository;
use QSManager\Domain\Team\StaffRepository;

final class BookingRequestValidator
{
    private const STATUSES = ['draft', 'confirmed', 'cancelled', 'completed'];
    private const RECEIPT_MIME_TYPES = ['image/jpeg', 'image/png', 'image/webp'];
    private const MAX_RECEIPT_BYTES = 450000;

    public function __construct(
        private readonly ServiceRepository $services,
        private readonly StaffRepository $staff,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function validate(array $body): array
    {
        $errors = [];

        $serviceId = $this->optionalPositiveInt($body, 'service_id', 'Service id', $errors);
        if ($serviceId !== null && !$this->services->exists($serviceId)) {
            $errors['service_id'][] = 'Selected service does not exist.';
        }

        $staffId = $this->optionalPositiveInt($body, 'staff_id', 'Staff id', $errors);
        if ($staffId !== null && !$this->staff->exists($staffId)) {
            $errors['staff_id'][] = 'Selected staff member does not exist.';
        }

        $customerName = $this->stringField($body, 'customer_name', false, $errors);
        if ($customerName !== null && mb_strlen($customerName) > 160) {
            $errors['customer_name'][] = 'Customer name cannot exceed 160 characters.';
        }

        $customerPhone = $this->stringField($body, 'customer_phone', false, $errors);
        if ($customerPhone !== null && !preg_match('/^\+?[0-9\s\-]+$/', $customerPhone)) {
            $errors['customer_phone'][] = 'Customer phone must match /^\\+?[0-9\\s\\-]+$/.';
        }
        $this->maxLength($customerPhone, 'customer_phone', 40, $errors);

        $scheduledFor = $this->stringField($body, 'scheduled_for', false, $errors);
        if ($scheduledFor !== null) {
            try {
                new DateTimeImmutable($scheduledFor);
            } catch (\Exception) {
                $errors['scheduled_for'][] = 'Scheduled for must be a parseable date and time.';
            }
        }

        $status = $this->stringField($body, 'status', true, $errors);
        if ($status !== null && !in_array($status, self::STATUSES, true)) {
            $errors['status'][] = 'Status must be one of: draft, confirmed, cancelled, completed.';
        }

        $address = $this->stringField($body, 'address', false, $errors);
        $this->maxLength($address, 'address', 240, $errors);

        $comuna = $this->stringField($body, 'comuna', false, $errors);
        $this->maxLength($comuna, 'comuna', 120, $errors);

        $serviceValue = $this->optionalNonNegativeMoney($body, 'service_value', 'Service value', $errors);
        $transferValue = $this->optionalNonNegativeMoney($body, 'transfer_value', 'Transfer value', $errors);
        $depositAmount = $this->optionalNonNegativeMoney($body, 'deposit_amount', 'Deposit amount', $errors);
        $totalService = $this->optionalNonNegativeMoney($body, 'total_service', 'Total service', $errors);
        $balanceDue = $this->optionalNonNegativeMoney($body, 'balance_due', 'Balance due', $errors);

        $paymentStatus = $this->stringField($body, 'payment_status', false, $errors);
        $this->maxLength($paymentStatus, 'payment_status', 40, $errors);

        $serviceStatus = $this->stringField($body, 'service_status', false, $errors);
        $this->maxLength($serviceStatus, 'service_status', 40, $errors);

        $contractId = $this->stringField($body, 'contract_id', false, $errors);
        $this->maxLength($contractId, 'contract_id', 80, $errors);

        $milestone = $this->stringField($body, 'milestone', false, $errors);
        $this->maxLength($milestone, 'milestone', 80, $errors);

        $cashGroup = $this->stringField($body, 'cash_group', false, $errors);
        $this->maxLength($cashGroup, 'cash_group', 80, $errors);

        $transferReceipt = $this->transferReceipt($body, $errors);

        if ($errors !== []) {
            throw new ValidationException($errors);
        }

        return [
            'service_id' => $serviceId,
            'staff_id' => $staffId,
            'customer_name' => $customerName,
            'customer_phone' => $customerPhone,
            'scheduled_for' => $scheduledFor,
            'status' => $status ?? '',
            'address' => $address,
            'comuna' => $comuna,
            'service_value' => $serviceValue,
            'transfer_value' => $transferValue,
            'deposit_amount' => $depositAmount,
            'total_service' => $totalService,
            'balance_due' => $balanceDue,
            'payment_status' => $paymentStatus,
            'service_status' => $serviceStatus,
            'contract_id' => $contractId,
            'milestone' => $milestone,
            'cash_group' => $cashGroup,
            'transfer_receipt' => $transferReceipt,
        ];
    }

    /**
     * @return null|array{image_base64: string, mime: string, filename: string, size: int}
     */
    private function transferReceipt(array $body, array &$errors): ?array
    {
        if (!array_key_exists('transfer_receipt', $body) || $body['transfer_receipt'] === null || $body['transfer_receipt'] === '') {
            return null;
        }

        if (!is_array($body['transfer_receipt'])) {
            $errors['transfer_receipt'][] = 'Transfer receipt must be an object.';
            return null;
        }

        $receipt = $body['transfer_receipt'];
        $dataUrl = $receipt['data_url'] ?? null;
        $filename = $receipt['filename'] ?? 'comprobante-transferencia.webp';

        if (!is_string($dataUrl) || !preg_match('/^data:(image\\/(?:jpeg|png|webp));base64,([A-Za-z0-9+\\/=]+)$/', $dataUrl, $matches)) {
            $errors['transfer_receipt'][] = 'Transfer receipt must be a valid JPEG, PNG or WebP data URL.';
            return null;
        }

        $mime = $matches[1];
        if (!in_array($mime, self::RECEIPT_MIME_TYPES, true)) {
            $errors['transfer_receipt'][] = 'Transfer receipt image type is not allowed.';
            return null;
        }

        $imageBase64 = $matches[2];
        $decoded = base64_decode($imageBase64, true);
        if ($decoded === false) {
            $errors['transfer_receipt'][] = 'Transfer receipt image could not be decoded.';
            return null;
        }

        $size = strlen($decoded);
        if ($size > self::MAX_RECEIPT_BYTES) {
            $errors['transfer_receipt'][] = 'Transfer receipt image cannot exceed 450 KB after compression.';
            return null;
        }

        if (!is_string($filename) || trim($filename) === '') {
            $filename = 'comprobante-transferencia.webp';
        }
        $filename = mb_substr(trim($filename), 0, 180);

        return [
            'image_base64' => $imageBase64,
            'mime' => $mime,
            'filename' => $filename,
            'size' => $size,
        ];
    }

    private function stringField(array $body, string $field, bool $required, array &$errors): ?string
    {
        if (!array_key_exists($field, $body) || $body[$field] === null || $body[$field] === '') {
            if ($required) {
                $errors[$field][] = ucfirst(str_replace('_', ' ', $field)) . ' is required.';
            }
            return null;
        }

        if (!is_string($body[$field])) {
            $errors[$field][] = ucfirst(str_replace('_', ' ', $field)) . ' must be a string.';
            return null;
        }

        $value = trim($body[$field]);
        if ($required && $value === '') {
            $errors[$field][] = ucfirst(str_replace('_', ' ', $field)) . ' is required.';
            return null;
        }

        return $value === '' ? null : $value;
    }

    private function optionalPositiveInt(array $body, string $field, string $label, array &$errors): ?int
    {
        if (!array_key_exists($field, $body) || $body[$field] === null || $body[$field] === '') {
            return null;
        }

        if (filter_var($body[$field], FILTER_VALIDATE_INT) === false) {
            $errors[$field][] = $label . ' must be a positive integer.';
            return null;
        }

        $value = (int) $body[$field];
        if ($value <= 0) {
            $errors[$field][] = $label . ' must be a positive integer.';
            return null;
        }

        return $value;
    }

    private function optionalNonNegativeMoney(array $body, string $field, string $label, array &$errors): ?float
    {
        if (!array_key_exists($field, $body) || $body[$field] === null || $body[$field] === '') {
            return null;
        }

        if (!is_int($body[$field]) && !is_float($body[$field]) && !is_string($body[$field])) {
            $errors[$field][] = $label . ' must be a non-negative number.';
            return null;
        }

        if (!is_numeric($body[$field])) {
            $errors[$field][] = $label . ' must be a non-negative number.';
            return null;
        }

        $value = (float) $body[$field];
        if ($value < 0) {
            $errors[$field][] = $label . ' must be a non-negative number.';
            return null;
        }

        return $value;
    }

    private function maxLength(?string $value, string $field, int $max, array &$errors): void
    {
        if ($value !== null && mb_strlen($value) > $max) {
            $errors[$field][] = ucfirst(str_replace('_', ' ', $field)) . ' cannot exceed ' . $max . ' characters.';
        }
    }
}
