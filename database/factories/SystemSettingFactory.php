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
            'key' => 'agent.polling_interval_seconds',
            'value' => ['value' => 30],
            'is_encrypted' => false,
            'description' => 'Default agent polling interval.',
        ];
    }
}
