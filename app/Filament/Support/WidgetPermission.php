<?php

namespace App\Filament\Support;

use BezhanSalleh\FilamentShield\Facades\FilamentShield;

/**
 * Resolves the Shield-generated `view_<widget>` permission for a widget class
 * and checks it against the current user, so dashboard widgets are gated by
 * assignable permissions (the Shield "Widgets" tab) instead of hardcoded roles.
 */
class WidgetPermission
{
    /** @var array<class-string, string|null> */
    private static array $permissionKeys = [];

    public static function allows(string $widgetClass): bool
    {
        $permission = self::permissionKey($widgetClass);

        // Widget excluded from Shield (no generated permission) → leave visible.
        if ($permission === null) {
            return true;
        }

        return auth()->user()?->can($permission) ?? false;
    }

    private static function permissionKey(string $widgetClass): ?string
    {
        if (! array_key_exists($widgetClass, self::$permissionKeys)) {
            $widget = FilamentShield::getWidgets()[$widgetClass] ?? null;
            self::$permissionKeys[$widgetClass] = $widget
                ? array_key_first($widget['permissions'])
                : null;
        }

        return self::$permissionKeys[$widgetClass];
    }
}
