<?php

namespace App\Filament\Resources\Payments\Schemas;

use Filament\Schemas\Schema;

/**
 * The View page renders a custom blade
 * (filament.resources.payments.pages.view-payment) with the same cw/ow-style
 * visual code as the contract and order views, so there's no infolist to
 * configure here — kept as a stub because the resource still calls
 * infolist() on the View page.
 */
class PaymentInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([]);
    }
}
