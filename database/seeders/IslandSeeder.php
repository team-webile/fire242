<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class IslandSeeder extends Seeder
{
    public function run()
    {
        $islands = [
            ['name' => 'New Providence'],
            ['name' => 'Grand Bahama'],
            ['name' => 'Abaco'],
            ['name' => 'Andros'],
            ['name' => 'Other Family Islands'],
        ];

        DB::table('islands')->insert($islands);
    }
}