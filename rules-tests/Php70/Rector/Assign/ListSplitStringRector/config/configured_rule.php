<?php

declare(strict_types=1);

use Rector\Php70\Rector\Assign\ListSplitStringRector;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

return static function (ContainerConfigurator $containerConfigurator): void {
    $services = $containerConfigurator->services();
    $services->set(ListSplitStringRector::class);
};
