<?php

namespace Database\Factories;

use App\Models\Category;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Category>
 */
class CategoryFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = $this->faker->words(2, true);
        return [
            'name' => $name,
            'slug' => str()->slug($name),
            'description' => $this->faker->sentence(),
            'icon' => 'fa-solid fa-list', // قيمة افتراضية
            'image' => $this->faker->imageUrl(),
            'parent_id' => null, // الضيوف دايماً رئيسيين، ونغيرهم في السيرفر
            'sort_order' => $this->faker->numberBetween(1, 100),
            'status' => 'active',
        ];
    }
}
