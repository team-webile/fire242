<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class CountriesTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $countries = [
            ['name' => 'Jamaica'],
            ['name' => 'Trinidad and Tobago'],
            ['name' => 'Barbados'],
            ['name' => 'Bahamas'],
            ['name' => 'Saint Lucia'],
            ['name' => 'Grenada'],
            ['name' => 'Saint Vincent and the Grenadines'],
            ['name' => 'Antigua and Barbuda'],
            ['name' => 'Dominica'],
        ];  

        foreach ($countries as $country) {
            DB::table('countries')->insert([
                'name' => $country['name'],
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }
} 
