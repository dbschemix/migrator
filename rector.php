<?php

/**
 * @noinspection PhpUnhandledExceptionInspection
 */

declare(strict_types=1);

use Rector\DeadCode\Rector\Node\RemoveNonExistingVarAnnotationRector;
use Rector\Set\ValueObject\SetList;

return Rector\Config\RectorConfig::configure()
    ->withPaths(
        [
            __DIR__ . '/src',
            __DIR__ . '/tests',
        ]
    )
    ->withParallel()
    ->withCache('/tmp/var/rector')
    ->withPhpSets(php83: true)
    ->withSets(
        [
            SetList::CODE_QUALITY,
            SetList::DEAD_CODE,
            SetList::PRIVATIZATION,
            SetList::TYPE_DECLARATION_DOCBLOCKS,
        ]
    )
    ->withSkip(
        [
            RemoveNonExistingVarAnnotationRector::class,
        ]
    );
