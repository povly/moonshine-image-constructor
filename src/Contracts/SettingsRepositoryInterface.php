<?php

declare(strict_types=1);

namespace Povly\MoonShineImageEditor\Contracts;

interface SettingsRepositoryInterface
{
    public function getSettings(): array;

    public function saveSettings(array $settings): void;

    public function getOptimizerConfig(): array;

    public function applyToConfig(?array $settings = null): void;
}
