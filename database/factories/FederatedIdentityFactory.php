<?php

namespace Database\Factories;

use App\Models\FederatedIdentity;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FederatedIdentity>
 */
class FederatedIdentityFactory extends Factory
{
    protected $model = FederatedIdentity::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'provider' => FederatedIdentity::PROVIDER_BSPDX,
            'subject' => $this->faker->uuid(),
            'email_at_link' => $this->faker->safeEmail(),
        ];
    }
}
