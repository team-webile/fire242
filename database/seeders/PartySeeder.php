<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class PartySeeder extends Seeder
{
    public function run()
    {
        $parties = [
            [
                'name' => 'Progressive Liberal Party',
                'short_name' => 'PLP',
                'status' => 'active',
                'created_at' => now(),
                'updated_at' => now()
            ],
            [
                'name' => 'Free National Movement',
                'short_name' => 'FNM',
                'status' => 'active',
                'created_at' => now(),
                'updated_at' => now()
            ],
            [
                'name' => 'Democratic National Alliance',
                'short_name' => 'DNA',
                'status' => 'active',
                'created_at' => now(),
                'updated_at' => now()
            ],
            [
                'name' => 'Coalition of Independents',
                'short_name' => 'COI',
                'status' => 'active',
                'created_at' => now(),
                'updated_at' => now()
            ],
            [
                'name' => 'Bahamas Constitution Party',
                'short_name' => 'BCP',
                'status' => 'active',
                'created_at' => now(),
                'updated_at' => now()
            ],
            [
                'name' => 'Bahamas Democratic Movement',
                'short_name' => 'BDM',
                'status' => 'active',
                'created_at' => now(),
                'updated_at' => now()
            ]
        ];

        DB::table('parties')->insert($parties);
    }
}