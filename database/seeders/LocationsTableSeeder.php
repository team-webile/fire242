<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class LocationsTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $locations = [
            // Jamaica
            [
                'country_name' => 'Jamaica',
                'locations' => [
                    [
                        'name' => 'Kingston',
                        'address' => 'Downtown Kingston',
                        'city' => 'Kingston',
                        'postal_code' => 'JMAKN',
                        'latitude' => 18.0179,
                        'longitude' => -76.8099,
                    ],
                    [
                        'name' => 'Montego Bay',
                        'address' => 'Hip Strip',
                        'city' => 'Montego Bay',
                        'state' => 'Saint James',
                        'postal_code' => 'JMAMB',
                        'latitude' => 18.4762,
                        'longitude' => -77.8939,
                    ],
                ]
            ],
            // Trinidad and Tobago
            [
                'country_name' => 'Trinidad and Tobago',
                'locations' => [
                    [
                        'name' => 'Port of Spain',
                        'address' => 'Independence Square',
                        'city' => 'Port of Spain',
                        'postal_code' => '190129',
                        'latitude' => 10.6550,
                        'longitude' => -61.5175,
                    ],
                ]
            ],
            // Barbados
            [
                'country_name' => 'Barbados',
                'locations' => [
                    [
                        'name' => 'Bridgetown',
                        'address' => 'Broad Street',
                        'city' => 'Bridgetown',
                        'postal_code' => 'BB11000',
                        'latitude' => 13.1132,
                        'longitude' => -59.5988,
                    ],
                ]
            ],
        ];

        foreach ($locations as $countryData) {
            $country_id = DB::table('countries')
                ->where('name', $countryData['country_name'])
                ->value('id');

            foreach ($countryData['locations'] as $location) {
                DB::table('locations')->insert([
                    'country_id' => $country_id,
                    'name' => $location['name'],
                    'address' => $location['address'],
                    'city' => $location['city'],
                    'state' => $location['state'] ?? null,
                    'postal_code' => $location['postal_code'],
                    'latitude' => $location['latitude'],
                    'longitude' => $location['longitude'],
                    'is_active' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        } 
    }
}
