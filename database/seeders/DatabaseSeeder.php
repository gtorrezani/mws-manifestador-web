<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(MwsBaselineSeeder::class);

        if (! app()->environment('local')) {
            return;
        }

        User::query()->firstOrCreate(
            ['cpf' => '52998224725'],
            [
                'name' => 'Administrador',
                'password' => Hash::make('password'),
                'is_active' => true,
            ],
        );
    }
}
