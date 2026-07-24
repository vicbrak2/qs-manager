<?php

declare(strict_types=1);

namespace QSManager\Tests\Integration\Http;

use QSManager\Tests\Support\HttpTestCase;

/**
 * Extraido de HttpRoutesTest.php (Fase 5 del plan de migracion).
 * Rutas /api/v1/team.
 */
final class TeamRoutesTest extends HttpTestCase
{
    public function testTeamRoutesValidateAndCreate(): void
    {
        $response = $this->json('GET', '/api/v1/team');
        self::assertSame(200, $response->getStatusCode());

        $invalid = $this->json('POST', '/api/v1/team', [
            'display_name' => 'Ca',
            'role' => 'Owner',
        ]);
        self::assertSame(422, $invalid->getStatusCode());
        $invalidPayload = $this->payload($invalid);
        self::assertArrayHasKey('display_name', $invalidPayload['errors']);
        self::assertArrayHasKey('role', $invalidPayload['errors']);

        $created = $this->json('POST', '/api/v1/team', [
            'display_name' => 'Camila Villalobos',
            'role' => 'coordinadora',
        ]);
        self::assertSame(201, $created->getStatusCode());
        self::assertSame('coordinadora', $this->payload($created)['staff_member']['role']);
    }

    public function testTeamStoreValidationExhaustive(): void
    {
        // 1. Display name missing
        $res = $this->json('POST', '/api/v1/team', [
            'role' => 'admin',
        ]);
        self::assertSame(422, $res->getStatusCode());
        self::assertArrayHasKey('display_name', $this->payload($res)['errors']);

        // 2. Display name too short
        $res = $this->json('POST', '/api/v1/team', [
            'display_name' => 'Ca',
            'role' => 'admin',
        ]);
        self::assertSame(422, $res->getStatusCode());
        self::assertArrayHasKey('display_name', $this->payload($res)['errors']);

        // 3. Display name too long
        $res = $this->json('POST', '/api/v1/team', [
            'display_name' => str_repeat('a', 161),
            'role' => 'admin',
        ]);
        self::assertSame(422, $res->getStatusCode());
        self::assertArrayHasKey('display_name', $this->payload($res)['errors']);

        // 4. Role missing
        $res = $this->json('POST', '/api/v1/team', [
            'display_name' => 'Camila',
        ]);
        self::assertSame(422, $res->getStatusCode());
        self::assertArrayHasKey('role', $this->payload($res)['errors']);

        // 5. Role invalid
        $res = $this->json('POST', '/api/v1/team', [
            'display_name' => 'Camila',
            'role' => 'owner',
        ]);
        self::assertSame(422, $res->getStatusCode());
        self::assertArrayHasKey('role', $this->payload($res)['errors']);
    }
}
