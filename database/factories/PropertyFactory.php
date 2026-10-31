<?php

namespace Database\Factories;

use App\Enums\AreaUnit;
use App\Enums\PropertyStatus;
use App\Enums\PropertyType;
use App\Enums\Purpose;
use App\Models\Area;
use App\Models\Property;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Property>
 */
class PropertyFactory extends Factory
{
    protected $model = Property::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory()->dealer(),
            'title' => fake()->numberBetween(3, 20).' Marla House in '.fake()->city(),
            'purpose' => Purpose::Sale,
            'type' => PropertyType::House,
            'city' => 'Lahore',
            'area_id' => fn () => Area::query()->inRandomOrder()->value('id') ?? Area::create(['city' => 'Lahore', 'name' => 'Test Area '.fake()->unique()->numberBetween(1, 99999), 'slug' => 'test-area-'.fake()->unique()->numberBetween(1, 99999)])->id,
            'price' => 25_000_000,
            'area_size' => 10,
            'area_unit' => AreaUnit::Marla,
            'bedrooms' => 4,
            'bathrooms' => 4,
            'description' => str_repeat('A well maintained property close to schools and markets. ', 3),
            'amenities' => ['parking', 'gas'],
            'status' => PropertyStatus::Published,
            'published_at' => now()->subDay(),
            'expires_at' => now()->addDays(90),
        ];
    }

    public function status(PropertyStatus $status): static
    {
        return $this->state(fn () => ['status' => $status]);
    }

    public function forDealer(User $dealer): static
    {
        return $this->state(fn () => ['user_id' => $dealer->id]);
    }
}
