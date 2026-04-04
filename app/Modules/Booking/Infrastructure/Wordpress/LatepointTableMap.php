<?php

declare(strict_types=1);

namespace QS\Modules\Booking\Infrastructure\Wordpress;

final class LatepointTableMap
{
    private string $prefix;

    public function __construct()
    {
        global $wpdb;

        $this->prefix = isset($wpdb) ? $wpdb->prefix : 'wp_';
    }

    public function bookings(): string
    {
        return $this->prefix . 'lp_bookings';
    }

    public function customers(): string
    {
        return $this->prefix . 'lp_customers';
    }

    public function agents(): string
    {
        return $this->prefix . 'lp_agents';
    }

    public function services(): string
    {
        return $this->prefix . 'lp_services';
    }
}
