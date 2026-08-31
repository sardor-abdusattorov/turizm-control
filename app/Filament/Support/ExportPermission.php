<?php

namespace App\Filament\Support;

class ExportPermission
{
    public static function allows(string $ability): bool
    {
        $user = auth()->user();

        return $user !== null
            && ($user->hasRole('super_admin') || $user->can($ability));
    }
}
