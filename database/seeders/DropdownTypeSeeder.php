<?php

namespace Database\Seeders;

use App\Models\DropdownType;
use Illuminate\Database\Seeder;

class DropdownTypeSeeder extends Seeder
{
    public function run()
    {
        $types = [
            // Religion
            ['type' => 'religion', 'value' => 'Catholic'],
            ['type' => 'religion', 'value' => 'Protestant'],
            ['type' => 'religion', 'value' => 'Baptist'],
            ['type' => 'religion', 'value' => 'Anglican'],
            ['type' => 'religion', 'value' => 'Other'],


            // Located
            ['type' => 'located', 'value' => 'Nassau'],
            ['type' => 'located', 'value' => 'Grand Bahama'],
            ['type' => 'located', 'value' => 'Abaco'],
            ['type' => 'located', 'value' => 'Other Islands'],

            // Voter in House
            ['type' => 'voter_in_house', 'value' => 'Yes'],
            ['type' => 'voter_in_house', 'value' => 'No'],

            // Gender
            ['type' => 'gender', 'value' => 'Male'],
            ['type' => 'gender', 'value' => 'Female'],
            ['type' => 'gender', 'value' => 'Other'],

            // Marital Status
            ['type' => 'marital_status', 'value' => 'Single'],
            ['type' => 'marital_status', 'value' => 'Married'],
            ['type' => 'marital_status', 'value' => 'Divorced'],
            ['type' => 'marital_status', 'value' => 'Widowed'],

            

            // Employed
            ['type' => 'employed', 'value' => 'Yes'],
            ['type' => 'employed', 'value' => 'No'],

            // Children
            ['type' => 'children', 'value' => 'Yes'],
            ['type' => 'children', 'value' => 'No'],

            // Voting For
            ['type' => 'voting_for', 'value' => 'PLP'],
            ['type' => 'voting_for', 'value' => 'FNM'],
            ['type' => 'voting_for', 'value' => 'DNA'],
            ['type' => 'voting_for', 'value' => 'Other'],

            // Employment Type
            ['type' => 'employment_type', 'value' => 'Government'],
            ['type' => 'employment_type', 'value' => 'Private Sector'],
            ['type' => 'employment_type', 'value' => 'Self Employed'],
            ['type' => 'employment_type', 'value' => 'Retired'],

            // Voted in Last Election
            ['type' => 'voted_last_election', 'value' => 'Yes'],
            ['type' => 'voted_last_election', 'value' => 'No'],
            ['type' => 'voted_last_election', 'value' => 'Not Eligible'],
        ];

        foreach ($types as $type) {
            DropdownType::create($type);
        }
    }
}