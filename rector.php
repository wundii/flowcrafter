<?php

declare(strict_types=1);

use Rector\Config\RectorConfig;
use Rector\Naming\Rector\Class_\RenamePropertyToMatchTypeRector;
use Rector\PHPUnit\Set\PHPUnitSetList;
use Rector\Set\ValueObject\SetList;
use Rector\ValueObject\PhpVersion;

return static function (RectorConfig $rectorConfig): void
{
    $rectorConfig->phpVersion(PhpVersion::PHP_82);
    $rectorConfig->phpstanConfig(__DIR__ . '/phpstan.neon');
    $rectorConfig->cacheDirectory('./cache/rector');
    $rectorConfig->paths(
        [
            __DIR__ . '/console',
            __DIR__ . '/service',
            __DIR__ . '/src',
            __DIR__ . '/tests',
        ]
    );

    $rectorConfig->sets([
        PHPUnitSetList::PHPUNIT_100,
        PHPUnitSetList::PHPUNIT_CODE_QUALITY,
        SetList::DEAD_CODE,
        SetList::CODE_QUALITY,
        SetList::CODING_STYLE,
        SetList::TYPE_DECLARATION,
        SetList::PRIVATIZATION,
        SetList::NAMING,
        SetList::INSTANCEOF,
        SetList::EARLY_RETURN,
        SetList::RECTOR_PRESET,
    ]);

    $rectorConfig->skip([
        RenamePropertyToMatchTypeRector::class => [
            __DIR__ . '/src/FlowRunner.php',
        ],
    ]);
};
