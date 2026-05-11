<?php

declare(strict_types=1);

namespace QS\Modules\Setup\Application\Command;

use QS\Shared\Bus\CommandInterface;

final class SetupSiteCommand implements CommandInterface
{
    /**
     * @param array<int, array{slug: string, title: string, content: string, status: string}> $pages
     */
    public function __construct(
        public readonly string $siteName,
        public readonly string $siteDescription,
        public readonly string $permalinkStructure = '/%postname%/',
        public readonly array $pages = [],
        public readonly string $menuName = 'Menu Principal',
        public readonly string $menuLocation = 'primary',
        public readonly string $frontPageSlug = 'home',
        public readonly bool $force = false,
        public readonly string $syncSecret = ''
    ) {
    }

    /**
     * @param array<string, mixed> $input
     */
    public static function fromInput(array $input, ?self $base = null): self
    {
        $base ??= self::defaults();

        $siteName = trim((string) self::pick($input, ['site_name', 'site-name'], $base->siteName));
        $siteDescription = trim((string) self::pick($input, ['site_description', 'site-description'], $base->siteDescription));
        $permalinkStructure = trim((string) self::pick($input, ['permalink_structure', 'permalink-structure'], $base->permalinkStructure));
        $menuName = trim((string) self::pick($input, ['menu_name', 'menu-name'], $base->menuName));
        $menuLocation = trim((string) self::pick($input, ['menu_location', 'menu-location'], $base->menuLocation));
        $frontPageSlug = trim((string) self::pick($input, ['front_page_slug', 'front-page-slug'], $base->frontPageSlug));
        $force      = self::toBool(self::pick($input, ['force'], $base->force));
        $syncSecret = trim((string) self::pick($input, ['sync_secret', 'sync-secret'], $base->syncSecret));

        return new self(
            $siteName !== '' ? $siteName : $base->siteName,
            $siteDescription,
            $permalinkStructure !== '' ? $permalinkStructure : $base->permalinkStructure,
            $base->pages,
            $menuName !== '' ? $menuName : $base->menuName,
            $menuLocation !== '' ? $menuLocation : $base->menuLocation,
            $frontPageSlug !== '' ? $frontPageSlug : $base->frontPageSlug,
            $force,
            $syncSecret
        );
    }

    public static function defaults(?string $siteName = null, ?string $siteDescription = null, bool $force = false): self
    {
        $resolvedSiteName = trim($siteName ?? self::option('blogname', 'QS Studio'));
        $resolvedDescription = trim($siteDescription ?? self::option('blogdescription', ''));

        return new self(
            $resolvedSiteName !== '' ? $resolvedSiteName : 'QS Studio',
            $resolvedDescription,
            '/%postname%/',
            self::defaultPages($resolvedSiteName !== '' ? $resolvedSiteName : 'QS Studio'),
            'Menu Principal',
            'primary',
            'home',
            $force,
            ''  // syncSecret — provided explicitly when setting the secret
        );
    }

    /**
     * @return array<int, array{slug: string, title: string, content: string, status: string}>
     */
    public static function defaultPages(string $siteName = 'QS Studio'): array
    {
        return [
            [
                'slug' => 'home',
                'title' => 'Inicio',
                'content' => sprintf("Bienvenida a %s.\n\nEste espacio centraliza la informacion principal del estudio.", $siteName),
                'status' => 'draft',
            ],
            [
                'slug' => 'about',
                'title' => 'Nosotras',
                'content' => 'Presenta el equipo, la propuesta del estudio y la experiencia que ofrece QS.',
                'status' => 'draft',
            ],
            [
                'slug' => 'services',
                'title' => 'Servicios',
                'content' => 'Resume servicios, paquetes y diferenciadores principales.',
                'status' => 'draft',
            ],
            [
                'slug' => 'contact',
                'title' => 'Contacto',
                'content' => 'Canales de contacto, ubicacion, horarios y formulario principal.',
                'status' => 'draft',
            ],
            [
                'slug' => 'chatbot',
                'title' => 'Chatbot',
                'content' => 'Pagina reservada para integrar el asistente conversacional de QS.',
                'status' => 'draft',
            ],
        ];
    }

    /**
     * @param array<string, mixed> $input
     * @param array<int, string> $keys
     */
    private static function pick(array $input, array $keys, mixed $default): mixed
    {
        foreach ($keys as $key) {
            if (array_key_exists($key, $input)) {
                return $input[$key];
            }
        }

        return $default;
    }

    private static function option(string $key, string $default): string
    {
        if (! function_exists('get_option')) {
            return $default;
        }

        return (string) get_option($key, $default);
    }

    private static function toBool(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        if (is_numeric($value)) {
            return (int) $value === 1;
        }

        if (is_string($value)) {
            return in_array(strtolower(trim($value)), ['1', 'true', 'yes', 'on'], true);
        }

        return false;
    }
}
