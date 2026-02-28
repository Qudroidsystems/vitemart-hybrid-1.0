<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Customer;

class CustomerSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $customers = [
            [
                'first_name' => 'John',
                'last_name' => 'Doe',
                'email' => 'john.doe@example.com',
                'phone_number' => '08012345678',
                'gender' => 'male',
                'home_address' => '123 Main Street, Lagos',
                'customer_type' => 'regular',
                'status' => 'active',
            ],
            [
                'first_name' => 'Jane',
                'last_name' => 'Smith',
                'email' => 'jane.smith@example.com',
                'phone_number' => '08023456789',
                'gender' => 'female',
                'home_address' => '456 Broad Street, Abuja',
                'customer_type' => 'wholesale',
                'status' => 'active',
                'company_name' => 'Smith Enterprises',
            ],
            [
                'first_name' => 'Michael',
                'last_name' => 'Johnson',
                'email' => 'michael.j@example.com',
                'phone_number' => '08034567890',
                'phone_number_2' => '09012345678',
                'gender' => 'male',
                'home_address' => '789 Market Road, Port Harcourt',
                'office_address' => 'Suite 101, Business Plaza',
                'customer_type' => 'corporate',
                'status' => 'active',
                'company_name' => 'Johnson & Sons Ltd.',
                'contact_person' => 'Michael Johnson',
            ],
            [
                'first_name' => 'Sarah',
                'last_name' => 'Williams',
                'email' => 'sarah.w@example.com',
                'phone_number' => '08045678901',
                'gender' => 'female',
                'home_address' => '321 Park Avenue, Ibadan',
                'customer_type' => 'regular',
                'status' => 'active',
                'loyalty_card_number' => 'LOY-001',
                'loyalty_points' => 150,
            ],
            [
                'first_name' => 'David',
                'last_name' => 'Brown',
                'email' => 'david.brown@example.com',
                'phone_number' => '08056789012',
                'gender' => 'male',
                'home_address' => '654 Hilltop Drive, Enugu',
                'customer_type' => 'wholesale',
                'status' => 'active',
                'company_name' => 'Brown Distributors',
                'credit_limit' => 500000,
                'credit_balance' => 150000,
            ],
        ];

        foreach ($customers as $customer) {
            Customer::create($customer);
        }

        $this->command->info('Customers seeded successfully!');
    }
}
