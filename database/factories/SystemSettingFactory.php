<?php

namespace Database\Factories;

use App\Models\SystemSetting;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<SystemSetting> */
class SystemSettingFactory extends Factory
{
    protected $model = SystemSetting::class;

    public function definition(): array
    {
        return [
            'scope_key' => SystemSetting::makeScopeKey(null, null),
            'key' => 'setting.'.$this->faker->unique()->slug(2),
            'value' => ['value' => 30],
            'is_encrypted' => false,
            'description' => 'Default agent polling interval.',
        ];
    }
}
