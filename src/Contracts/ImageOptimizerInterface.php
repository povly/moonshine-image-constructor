<?php

declare(strict_types=1);

namespace Povly\MoonShineImageEditor\Contracts;

interface ImageOptimizerInterface
{
    public function process(): void;

    public function convertFormat(string $targetFormat): string;

    public function optimize(): void;
}
