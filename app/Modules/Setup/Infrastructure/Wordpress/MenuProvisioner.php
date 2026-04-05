<?php

declare(strict_types=1);

namespace QS\Modules\Setup\Infrastructure\Wordpress;

use QS\Core\Logging\Logger;

final class MenuProvisioner
{
    public function __construct(
        private readonly Logger $logger
    ) {
    }

    /**
     * @param array<string, array{id: int, slug: string, title: string, state: string}> $pages
     * @return array<string, mixed>
     */
    public function provision(string $menuName, string $menuLocation, array $pages): array
    {
        $menu = wp_get_nav_menu_object($menuName);
        $created = false;

        if (! $menu instanceof \WP_Term) {
            $menuId = wp_create_nav_menu($menuName);

            if (is_wp_error($menuId)) {
                throw new \RuntimeException(sprintf('No se pudo crear el menu "%s": %s', $menuName, $menuId->get_error_message()));
            }

            $menu = wp_get_nav_menu_object((int) $menuId);
            $created = true;
        }

        if (! $menu instanceof \WP_Term) {
            throw new \RuntimeException(sprintf('No se pudo resolver el menu "%s" luego de crearlo.', $menuName));
        }

        $existingItems = wp_get_nav_menu_items($menu->term_id) ?: [];
        $linkedPageIds = [];

        foreach ($existingItems as $item) {
            $objectId = isset($item->object_id) ? (int) $item->object_id : 0;

            if ($objectId > 0) {
                $linkedPageIds[] = $objectId;
            }
        }

        $addedPages = [];
        $existingPages = [];

        foreach ($pages as $page) {
            $pageId = $page['id'];

            if ($pageId <= 0) {
                continue;
            }

            if (in_array($pageId, $linkedPageIds, true)) {
                $existingPages[] = $page['slug'];
                continue;
            }

            $menuItemId = wp_update_nav_menu_item($menu->term_id, 0, [
                'menu-item-object-id' => $pageId,
                'menu-item-object' => 'page',
                'menu-item-type' => 'post_type',
                'menu-item-status' => 'publish',
            ]);

            if (is_wp_error($menuItemId)) {
                throw new \RuntimeException(sprintf('No se pudo vincular la pagina "%s" al menu: %s', $page['slug'], $menuItemId->get_error_message()));
            }

            $addedPages[] = $page['slug'];
        }

        $warnings = [];
        $assigned = false;
        $registeredLocations = function_exists('get_registered_nav_menus') ? get_registered_nav_menus() : [];
        $locations = get_theme_mod('nav_menu_locations');

        if (! is_array($locations)) {
            $locations = [];
        }

        if (array_key_exists($menuLocation, $registeredLocations) || array_key_exists($menuLocation, $locations)) {
            $locations[$menuLocation] = (int) $menu->term_id;
            set_theme_mod('nav_menu_locations', $locations);
            $assigned = true;
        } else {
            $warnings[] = sprintf('La ubicacion de menu "%s" no existe en el tema activo.', $menuLocation);
            $this->logger->warning('QS menu provisioning could not assign theme location.');
        }

        $this->logger->info('QS menu provisioning completed.');

        return [
            'menu_id' => (int) $menu->term_id,
            'name' => $menuName,
            'location' => $menuLocation,
            'created' => $created,
            'assigned' => $assigned,
            'added_pages' => $addedPages,
            'existing_pages' => $existingPages,
            'warnings' => $warnings,
        ];
    }
}
