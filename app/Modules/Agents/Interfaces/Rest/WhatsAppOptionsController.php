<?php

declare(strict_types=1);

namespace QS\Modules\Agents\Interfaces\Rest;

use QS\Modules\Agents\Infrastructure\N8n\WhatsAppGateway;
use WP_REST_Request;
use WP_REST_Response;

/**
 * Endpoint para leer y escribir las opciones del WhatsApp Gateway desde la REST API.
 *
 * GET  /wp-json/qs/v1/agents/whatsapp-options  -> devuelve estado actual
 * POST /wp-json/qs/v1/agents/whatsapp-options  -> actualiza las opciones enviadas
 *
 * Opciones soportadas en el body POST:
 *   webhook_url      string  URL del webhook híbrido de n8n
 *   phone            string  Número destino por defecto (operadora)
 *   instance         string  Nombre de instancia Evolution
 *   actions_enabled  bool    Activa o desactiva envíos
 *   allowed_phones   string  Lista separada por comas de números permitidos
 */
final class WhatsAppOptionsController
{
    public function __construct(
        private readonly WhatsAppGateway $whatsAppGateway
    ) {
    }

    public function show(WP_REST_Request $request): WP_REST_Response
    {
        return new WP_REST_Response([
            'success' => true,
            'options' => [
                'webhook_url'     => $this->whatsAppGateway->webhookUrl(),
                'phone'           => $this->whatsAppGateway->defaultPhone(),
                'instance'        => $this->whatsAppGateway->instanceName(),
                'actions_enabled' => $this->whatsAppGateway->actionsEnabled(),
                'allowed_phones'  => $this->whatsAppGateway->allowedPhones(),
            ],
        ], 200);
    }

    public function update(WP_REST_Request $request): WP_REST_Response
    {
        $updated = [];

        $webhookUrl = trim((string) ($request->get_param('webhook_url') ?? ''));
        if ($webhookUrl !== '') {
            update_option(WhatsAppGateway::OPTION_NAME, $webhookUrl, false);
            $updated[] = 'webhook_url';
        }

        $phone = trim((string) ($request->get_param('phone') ?? ''));
        if ($phone !== '') {
            update_option(WhatsAppGateway::PHONE_OPTION_NAME, $phone, false);
            $updated[] = 'phone';
        }

        $instance = trim((string) ($request->get_param('instance') ?? ''));
        if ($instance !== '') {
            update_option(WhatsAppGateway::INSTANCE_OPTION_NAME, $instance, false);
            $updated[] = 'instance';
        }

        $actionsEnabledRaw = $request->get_param('actions_enabled');
        if ($actionsEnabledRaw !== null) {
            $value = filter_var($actionsEnabledRaw, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
            if ($value !== null) {
                update_option(WhatsAppGateway::ACTIONS_ENABLED_OPTION_NAME, $value ? '1' : '0', false);
                $updated[] = 'actions_enabled';
            }
        }

        $allowedPhones = trim((string) ($request->get_param('allowed_phones') ?? ''));
        if ($allowedPhones !== '') {
            update_option(WhatsAppGateway::ALLOWED_PHONES_OPTION_NAME, $allowedPhones, false);
            $updated[] = 'allowed_phones';
        }

        return new WP_REST_Response([
            'success' => true,
            'updated' => $updated,
        ], 200);
    }

    public function canManage(WP_REST_Request $request): bool
    {
        return function_exists('current_user_can') && (bool) current_user_can('manage_options');
    }
}
