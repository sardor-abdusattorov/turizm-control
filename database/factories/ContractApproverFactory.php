<?php

namespace Database\Factories;

use App\Models\Contract;
use App\Models\ContractApprover;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ContractApprover>
 */
class ContractApproverFactory extends Factory
{
    protected $model = ContractApprover::class;

    public function definition(): array
    {
        return [
            'contract_id' => Contract::factory(),
            'user_id' => User::factory(),
            'order' => 1,
            'status' => ContractApprover::STATUS_PENDING,
            'comment' => null,
            'acted_at' => null,
        ];
    }
}
