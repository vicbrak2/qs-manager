<?php

declare(strict_types=1);

namespace QS\Modules\Agents\Interfaces\Rest;

use QS\Modules\Agents\Infrastructure\N8n\ChatbotGateway;
use QS\Modules\Agents\Infrastructure\Wordpress\ChatbotFallbackResponder;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

final class ChatbotController
{
    public function __construct(
        private readonly ChatbotGateway $gateway,
        private readonly ChatbotFallbackResponder $fallbackResponder
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
            if ($this->shouldUseFallback($reply)) {
                return new WP_REST_Response($this->fallbackResponder->unavailableResponse($reply), 200);
            }

            return $reply;
        }

        return new WP_REST_Response([
            'success'  => true,
            'response' => $reply,
        ], 200);
    }

    private function shouldUseFallback(WP_Error $error): bool
    {
        if ($error->get_error_code() === 'n8n_connection_error') {
            return true;
        }

        $data = $error->get_error_data();

        if (! is_array($data) || ! isset($data['status']) || ! is_numeric($data['status'])) {
            return false;
        }

        return (int) $data['status'] >= 500;
    }
}
