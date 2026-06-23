<?php

namespace App\Filament\Support;

/**
 * Single gate for the spreadsheet export actions. Kept separate from the
 * resource "view" permissions so an admin can let a role browse a list without
 * also letting it pull the whole dataset out as a file. Super admins always
 * pass; everyone else needs the explicit Shield permission.
 */
class ExportPermission
{
    public static function allows(string $ability): bool
    {
        $user = auth()->user();

        return $user !== null
            && ($user->hasRole('super_admin') || $user->can($ability));
    }
}
