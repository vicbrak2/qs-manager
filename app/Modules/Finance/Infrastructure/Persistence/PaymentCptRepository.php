<?php

declare(strict_types=1);

namespace QS\Modules\Finance\Infrastructure\Persistence;

use DateTimeImmutable;
use QS\Modules\Finance\Domain\Entity\Payment;
use QS\Modules\Finance\Domain\Repository\PaymentRepository;
use QS\Modules\Finance\Domain\ValueObject\PaymentMethod;
use QS\Shared\ValueObjects\Money;
use RuntimeException;

final class PaymentCptRepository implements PaymentRepository
{
    public function __construct(private readonly \wpdb $wpdb)
    {
    }

    public function findAll(): array
    {
        if (! function_exists('get_posts')) {
            return [];
        }

        $posts = get_posts([
            'post_type' => 'qs_payment',
            'post_status' => ['publish', 'private', 'draft'],
            'numberposts' => -1,
            'orderby' => 'date',
            'order' => 'DESC',
        ]);

        if (! is_array($posts)) {
            return [];
        }

        return array_map(
            fn (\WP_Post $post): Payment => $this->hydrate($post),
            array_values(array_filter($posts, static fn ($post): bool => $post instanceof \WP_Post))
        );
    }

    public function findByMonth(string $month): array
    {
        return array_values(array_filter(
            $this->findAll(),
            static fn (Payment $payment): bool => $payment->closingMonth() === $month
        ));
    }

    public function save(Payment $payment): Payment
    {
        if (! function_exists('wp_insert_post') || ! function_exists('update_post_meta')) {
            throw new RuntimeException('WordPress runtime is not available.');
        }

        $title = $payment->concept() ?? sprintf('Pago reserva #%d', $payment->reservationId() ?? 0);
        $payload = [
            'post_type' => 'qs_payment',
            'post_status' => 'publish',
            'post_title' => $title,
            'post_content' => $payment->concept() ?? '',
        ];
        $result = $payment->id() === null
            ? wp_insert_post($payload, true)
            : wp_update_post(['ID' => $payment->id()] + $payload, true);

        if ($result instanceof \WP_Error || ! is_int($result)) {
            throw new RuntimeException('Could not persist payment.');
        }

        update_post_meta($result, 'reserva_id', $payment->reservationId());
        update_post_meta($result, 'monto_clp', $payment->amount()->amount());
        update_post_meta($result, 'metodo', $payment->method()->value);
        update_post_meta($result, 'estado', $payment->status());
        update_post_meta($result, 'fecha_pago', $payment->paidAt()->format('Y-m-d H:i:s'));
        update_post_meta($result, 'mes_anio_cierre', $payment->closingMonth());

        $this->syncFinanceEntry($result, $payment, $title);

        $saved = get_post($result);

        if (! $saved instanceof \WP_Post) {
            throw new RuntimeException('Payment was not found after persisting.');
        }

        return $this->hydrate($saved);
    }

    private function hydrate(\WP_Post $post): Payment
    {
        $reservationId = $this->intMeta($post->ID, 'reserva_id');
        $amountClp = $this->intMeta($post->ID, 'monto_clp') ?? 0;
        $method = PaymentMethod::fromNullable($this->stringMeta($post->ID, 'metodo'));
        $status = $this->stringMeta($post->ID, 'estado');
        $paidAt = $this->stringMeta($post->ID, 'fecha_pago');
        $closingMonth = $this->stringMeta($post->ID, 'mes_anio_cierre');

        return new Payment(
            (int) $post->ID,
            $reservationId,
            trim($post->post_title) !== '' ? $post->post_title : null,
            new Money($amountClp),
            $method,
            $status !== '' ? $status : 'registered',
            new DateTimeImmutable($paidAt !== '' ? $paidAt : $post->post_date),
            $closingMonth !== '' ? $closingMonth : (new DateTimeImmutable($post->post_date))->format('Y-m')
        );
    }

    private function syncFinanceEntry(int $postId, Payment $payment, string $title): void
    {
        $table = $this->wpdb->prefix . 'qs_finance_entries';
        $entryId = $this->intMeta($postId, 'qs_finance_entry_id');
        $data = [
            'tipo' => 'ingreso',
            'concepto' => $title,
            'monto_clp' => $payment->amount()->amount(),
            'metodo_pago' => $payment->method()->value,
            'servicio_id' => null,
            'staff_id' => null,
            'fecha' => $payment->paidAt()->format('Y-m-d'),
            'mes_anio' => $payment->closingMonth(),
            'created_at' => gmdate('Y-m-d H:i:s'),
        ];
        $formats = ['%s', '%s', '%d', '%s', '%d', '%d', '%s', '%s', '%s'];

        if ($entryId !== null) {
            $this->wpdb->update($table, $data, ['id' => $entryId], $formats, ['%d']);
            return;
        }

        $inserted = $this->wpdb->insert($table, $data, $formats);

        if ($inserted !== false) {
            update_post_meta($postId, 'qs_finance_entry_id', (int) $this->wpdb->insert_id);
        }
    }

    private function stringMeta(int $postId, string $key): string
    {
        $value = get_post_meta($postId, $key, true);

        return is_scalar($value) ? trim((string) $value) : '';
    }

    private function intMeta(int $postId, string $key): ?int
    {
        $value = get_post_meta($postId, $key, true);

        if ($value === '' || $value === null) {
            return null;
        }

        return is_numeric($value) ? (int) $value : null;
    }
}
