<?php

declare(strict_types=1);

use Rector\DeadCode\Rector\For_\RemoveDeadLoopRector;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

return static function (ContainerConfigurator $containerConfigurator): void {
    $services = $containerConfigurator->services();
    $services->set(RemoveDeadLoopRector::class);
};
