<?php

declare(strict_types=1);

namespace QS\Modules\Agents\Interfaces\Rest;

use QS\Modules\Agents\Infrastructure\N8n\ChatbotGateway;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

final class ChatbotController
{
    public function __construct(
        private readonly ChatbotGateway $gateway
    ) {
    }

    public function chat(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        $message = trim((string) $request->get_param('message'));

        if ($message === '') {
            return new WP_Error('missing_message', 'El mensaje no puede estar vacío.', ['status' => 400]);
        }

        $sessionId = is_user_logged_in()
            ? 'wp_user_' . get_current_user_id()
            : 'anon_' . md5($_SERVER['REMOTE_ADDR'] ?? 'unknown');

        $reply = $this->gateway->ask($message, $sessionId);

        if (is_wp_error($reply)) {
            return $reply;
        }

        return new WP_REST_Response([
            'success'  => true,
            'response' => $reply,
        ], 200);
    }
}
