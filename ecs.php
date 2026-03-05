<?php

declare(strict_types=1);

use PhpCsFixer\Fixer\ClassNotation\ClassAttributesSeparationFixer;
use PhpCsFixer\Fixer\Operator\NotOperatorWithSuccessorSpaceFixer;
use Symplify\EasyCodingStandard\Config\ECSConfig;
use Symplify\EasyCodingStandard\ValueObject\Set\SetList;

return function (ECSConfig $ecsConfig): void {
    $ecsConfig->cacheDirectory('./cache/ecs');
    $ecsConfig->paths([
        __DIR__ . '/service',
        __DIR__ . '/src',
        __DIR__ . '/tests',
    ]);
    $ecsConfig->parallel();
    $ecsConfig->sets([
        SetList::ARRAY,
        SetList::CLEAN_CODE,
        SetList::COMMENTS,
        SetList::COMMON,
        SetList::CONTROL_STRUCTURES,
        SetList::DOCBLOCK,
        SetList::NAMESPACES,
        SetList::PHPUNIT,
        SetList::PSR_12,
        SetList::SPACES,
        SetList::STRICT,
    ]);
    $ecsConfig->skip([
        ClassAttributesSeparationFixer::class,
        NotOperatorWithSuccessorSpaceFixer::class,
    ]);
};
