<?php

declare(strict_types=1);

namespace QS\Modules\Booking\Infrastructure\N8n;

use DateTimeImmutable;
use QS\Modules\Booking\Domain\Service\CalendarGateway;
use RuntimeException;

final class N8nCalendarGateway implements CalendarGateway
{
    private string $checkAvailabilityWebhookUrl;
    private string $createEventWebhookUrl;

    public function __construct()
    {
        // En un caso real, estas URLs vendrían de get_option() o del .env
        $this->checkAvailabilityWebhookUrl = get_option('qs_n8n_calendar_availability_url', 'https://n8n.qamilunastudio.com/webhook/calendar/availability');
        $this->createEventWebhookUrl = get_option('qs_n8n_calendar_create_url', 'https://n8n.qamilunastudio.com/webhook/calendar/create');
    }

    public function getAvailabilityForDate(DateTimeImmutable $date): array
    {
        $response = wp_remote_post($this->checkAvailabilityWebhookUrl, [
            'headers' => ['Content-Type' => 'application/json'],
            'body' => wp_json_encode([
                'date' => $date->format('Y-m-d')
            ]),
            'timeout' => 10
        ]);

        if (is_wp_error($response)) {
            throw new RuntimeException('Error al contactar n8n para disponibilidad: ' . $response->get_error_message());
        }

        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        return $data['availability'] ?? [];
    }

    public function createEvent(
        string $title,
        string $description,
        DateTimeImmutable $startTime,
        DateTimeImmutable $endTime
    ): string {
        $response = wp_remote_post($this->createEventWebhookUrl, [
            'headers' => ['Content-Type' => 'application/json'],
            'body' => wp_json_encode([
                'title' => $title,
                'description' => $description,
                'start_time' => $startTime->format('c'),
                'end_time' => $endTime->format('c')
            ]),
            'timeout' => 15
        ]);

        if (is_wp_error($response)) {
            throw new RuntimeException('Error al crear evento en GCal vía n8n: ' . $response->get_error_message());
        }

        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        return $data['google_event_id'] ?? '';
    }
}
