<?php

declare(strict_types=1);

namespace QS\Modules\Booking\Infrastructure\Wordpress;

final class LatepointTableMap
{
    public function __construct(private readonly \wpdb $wpdb)
    {
    }

    public function bookings(): string
    {
        return $this->wpdb->prefix . 'lp_bookings';
    }

    public function customers(): string
    {
        return $this->wpdb->prefix . 'lp_customers';
    }

    public function agents(): string
    {
        return $this->wpdb->prefix . 'lp_agents';
    }

    public function services(): string
    {
        return $this->wpdb->prefix . 'lp_services';
    }
}
