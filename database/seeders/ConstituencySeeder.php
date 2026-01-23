<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ConstituencySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $constituencies = [
            // New Providence
            ['name' => 'Bain Town and Grants Town', 'island_id' => 1],
            ['name' => 'Bamboo Town', 'island_id' => 1],
            ['name' => 'Carmichael', 'island_id' => 1],
            ['name' => 'Centreville', 'island_id' => 1],
            ['name' => 'Elizabeth', 'island_id' => 1],
            ['name' => 'Englerston', 'island_id' => 1],
            ['name' => 'Freetown', 'island_id' => 1],
            ['name' => 'Fort Charlotte', 'island_id' => 1],
            ['name' => 'Fox Hill', 'island_id' => 1],
            ['name' => 'Garden Hills', 'island_id' => 1],
            ['name' => 'Golden Gates', 'island_id' => 1],
            ['name' => 'Golden Isles', 'island_id' => 1],
            ['name' => 'Killarney', 'island_id' => 1],
            ['name' => 'Marathon', 'island_id' => 1],
            ['name' => 'Mount Moriah', 'island_id' => 1],
            ['name' => 'Nassau Village', 'island_id' => 1],
            ['name' => 'Pinewood', 'island_id' => 1],
            ['name' => 'Saint Anne\'s', 'island_id' => 1],
            ['name' => 'Saint Barnabas', 'island_id' => 1],
            ['name' => 'Sea Breeze', 'island_id' => 1],
            ['name' => 'South Beach', 'island_id' => 1],
            ['name' => 'Southern Shores', 'island_id' => 1],
            ['name' => 'Tall Pines', 'island_id' => 1],
            ['name' => 'Yamacraw', 'island_id' => 1],

            // Grand Bahama
            ['name' => 'Central Grand Bahama', 'island_id' => 2],
            ['name' => 'East Grand Bahama', 'island_id' => 2],
            ['name' => 'Marco City', 'island_id' => 2],
            ['name' => 'Pineridge', 'island_id' => 2],
            ['name' => 'West Grand Bahama & Bimini', 'island_id' => 2],

            // Abaco
            ['name' => 'Central and South Abaco', 'island_id' => 3],
            ['name' => 'North Abaco', 'island_id' => 3],

            // Andros
            ['name' => 'Mangrove Cay and South Andros', 'island_id' => 4],
            ['name' => 'North Andros and Berry Islands', 'island_id' => 4],

            // Other Family Islands
            ['name' => 'Cat Island, Rum Cay & San Salvador', 'island_id' => 5],
            ['name' => 'Central and South Eleuthera', 'island_id' => 5],
            ['name' => 'North Eleuthera', 'island_id' => 5],
            ['name' => 'The Exumas and Ragged Island', 'island_id' => 5],
            ['name' => 'Long Island', 'island_id' => 5],
            ['name' => 'MICAL', 'island_id' => 5],
        ];

        DB::table('constituencies')->insert($constituencies);
    }
}
