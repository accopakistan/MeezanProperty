<?php

namespace Database\Factories;

use App\Enums\DealerStatus;
use App\Enums\Role;
use App\Models\DealerProfile;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
            'role' => Role::Buyer,
            'status' => 'active',
            'city' => 'Lahore',
        ];
    }

    public function admin(): static
    {
        return $this->state(fn () => ['role' => Role::Admin]);
    }

    public function suspended(): static
    {
        return $this->state(fn () => ['status' => 'suspended']);
    }

    /**
     * A dealer with an agency profile. Pass a verification status and the
     * area ids the dealer operates in.
     *
     * @param  list<int>  $areaIds
     */
    public function dealer(DealerStatus $status = DealerStatus::Pending, array $areaIds = []): static
    {
        return $this->state(fn () => ['role' => Role::Dealer, 'phone' => '03001234567'])
            ->afterCreating(function (User $user) use ($status, $areaIds) {
                $profile = new DealerProfile([
                    'agency_name' => fake()->company().' Estate',
                    'slug' => Str::slug(fake()->unique()->company()).'-'.$user->id,
                    'city' => 'Lahore',
                ]);
                $profile->user_id = $user->id;
                $profile->verification_status = $status;
                $profile->verified_at = $status === DealerStatus::Verified ? now() : null;
                $profile->save();
                $profile->areas()->sync($areaIds);
            });
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }
}
