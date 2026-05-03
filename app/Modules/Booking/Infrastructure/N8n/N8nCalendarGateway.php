<?php

declare(strict_types=1);

namespace QS\Modules\Booking\Infrastructure\N8n;

use DateTimeImmutable;
use QS\Core\Logging\Logger;
use QS\Modules\Booking\Domain\Service\CalendarGateway;
use RuntimeException;

final class N8nCalendarGateway implements CalendarGateway
{
    private string $checkAvailabilityWebhookUrl;
    private string $createEventWebhookUrl;

    public function __construct(private readonly Logger $logger)
    {
        // En un caso real, estas URLs vendrían de get_option() o del .env
        $this->checkAvailabilityWebhookUrl = get_option('qs_n8n_calendar_availability_url', 'https://n8n.qamilunastudio.com/webhook/calendar/availability');
        $this->createEventWebhookUrl = get_option('qs_n8n_calendar_create_url', 'https://n8n.qamilunastudio.com/webhook/calendar/create');
    }

    /**
     * @return array<int|string, mixed>
     */
    public function getAvailabilityForDate(DateTimeImmutable $date): array
    {
        $payload = [
            'date' => $date->format('Y-m-d'),
        ];

        $this->logger->info('N8nCalendarGateway: Checking availability. URL: ' . $this->checkAvailabilityWebhookUrl . ' Payload: ' . (string) wp_json_encode($payload));

        $response = wp_remote_post($this->checkAvailabilityWebhookUrl, [
            'headers' => ['Content-Type' => 'application/json'],
            'body' => (string) wp_json_encode($payload),
            'timeout' => 10,
        ]);

        if (is_wp_error($response)) {
            $errorMsg = $response->get_error_message();
            $this->logger->error('N8nCalendarGateway: Availability check failed. Error: ' . $errorMsg);
            throw new RuntimeException('Error al contactar n8n para disponibilidad: ' . $errorMsg);
        }

        $body = wp_remote_retrieve_body($response);
        $statusCode = wp_remote_retrieve_response_code($response);
        
        $this->logger->info("N8nCalendarGateway: Availability response (Code $statusCode): " . $body);

        $data = json_decode($body, true);

        return $data['availability'] ?? [];
    }

    public function createEvent(
        string $title,
        string $description,
        DateTimeImmutable $startTime,
        DateTimeImmutable $endTime
    ): string {
        $payload = [
            'title' => $title,
            'description' => $description,
            'start_time' => $startTime->format('c'),
            'end_time' => $endTime->format('c'),
        ];

        $this->logger->info('N8nCalendarGateway: Creating event. URL: ' . $this->createEventWebhookUrl . ' Payload: ' . (string) wp_json_encode($payload));

        $response = wp_remote_post($this->createEventWebhookUrl, [
            'headers' => ['Content-Type' => 'application/json'],
            'body' => (string) wp_json_encode($payload),
            'timeout' => 15,
        ]);

        if (is_wp_error($response)) {
            $errorMsg = $response->get_error_message();
            $this->logger->error('N8nCalendarGateway: Create event failed. Error: ' . $errorMsg);
            throw new RuntimeException('Error al crear evento en GCal vía n8n: ' . $errorMsg);
        }

        $body = wp_remote_retrieve_body($response);
        $statusCode = wp_remote_retrieve_response_code($response);

        $this->logger->info("N8nCalendarGateway: Create event response (Code $statusCode): " . $body);

        $data = json_decode($body, true);

        return (string) ($data['google_event_id'] ?? '');
    }
}
