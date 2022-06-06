<?php

declare (strict_types=1);
namespace RectorPrefix20220606;

use Rector\Config\RectorConfig;
use Rector\Set\ValueObject\DowngradeLevelSetList;
use Rector\Set\ValueObject\DowngradeSetList;
return static function (\Rector\Config\RectorConfig $rectorConfig) : void {
    $rectorConfig->sets([\Rector\Set\ValueObject\DowngradeLevelSetList::DOWN_TO_PHP_81, \Rector\Set\ValueObject\DowngradeSetList::PHP_81]);
};
