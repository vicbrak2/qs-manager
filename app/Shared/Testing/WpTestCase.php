<?php

declare(strict_types=1);

namespace QS\Shared\Testing;

abstract class WpTestCase extends TestCase
{
    protected function requireWordPressRuntime(): void
    {
        if (! function_exists('add_action')) {
            $this->markTestSkipped('WordPress runtime is not available.');
        }
    }
}
