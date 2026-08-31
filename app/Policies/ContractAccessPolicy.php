<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Contract;
use Illuminate\Foundation\Auth\User as AuthUser;

class ContractAccessPolicy extends ContractPolicy
{
    public function delete(AuthUser $authUser, Contract $contract): bool
    {
        return parent::delete($authUser, $contract)
            && $contract->canBeDeletedBy($authUser);
    }
}
