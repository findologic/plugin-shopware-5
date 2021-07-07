<?php

declare(strict_types=1);

namespace FinSearchUnified\Tests;

trait OldPhpUnitVersionAware
{
    public static function assertStringContainsString(string $needle, string $haystack, string $message = ''): void
    {
        if (method_exists(parent::class, 'assertStringContainsString')) {
            parent::assertStringContainsString($needle, $haystack, $message);
            return;
        }

        parent::assertContains($needle, $haystack, $message);
    }
}
