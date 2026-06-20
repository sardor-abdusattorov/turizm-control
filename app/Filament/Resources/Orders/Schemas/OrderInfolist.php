<?php

namespace App\Filament\Resources\Orders\Schemas;

use Filament\Schemas\Schema;

/**
 * The View page renders a custom blade
 * (filament.resources.orders.pages.view-order) with the cw-* visual code
 * that matches the contract view, so there is no infolist to configure
 * here. Kept as a stub because the resource still calls
 * `infolist($schema)` on the View page.
 */
class OrderInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([]);
    }
}
