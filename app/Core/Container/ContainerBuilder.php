<?php

declare(strict_types=1);

namespace QS\Core\Container;

use DI\Container;
use DI\ContainerBuilder as DiContainerBuilder;
use QS\Core\Config\EnvironmentDetector;

final class ContainerBuilder
{
    public function __construct(private readonly string $rootDir)
    {
    }

    public function build(): Container
    {
        $builder = new DiContainerBuilder();
        $environmentDetector = new EnvironmentDetector();

        if ($environmentDetector->isProduction()) {
            $builder->enableCompilation($this->rootDir . '/var/cache/di');
        }

        $builder->addDefinitions($this->rootDir . '/config/di.php');

        return $builder->build();
    }
}
