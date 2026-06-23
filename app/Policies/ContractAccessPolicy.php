<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Contract;
use Illuminate\Foundation\Auth\User as AuthUser;

/**
 * Custom contract authorization that must survive `php artisan project:init`
 * (which runs `shield:generate` and overwrites ContractPolicy with a plain
 * permission map). Registered over the generated policy in AppServiceProvider,
 * so the generated file can stay fully owned by Shield while the business rules
 * live here, untouched by regeneration.
 */
class ContractAccessPolicy extends ContractPolicy
{
    /**
     * A contract may be deleted only while it is a draft — owned by the user,
     * or any draft for a super admin. The delete_contract permission alone is
     * not enough.
     */
    public function delete(AuthUser $authUser, Contract $contract): bool
    {
        return parent::delete($authUser, $contract)
            && $contract->canBeDeletedBy($authUser);
    }
}
