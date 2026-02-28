<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Order;
use App\Models\User;
use App\Models\Customer;

class UpdateOrdersCustomerIdSeeder extends Seeder
{
    public function run(): void
    {
        // Get all orders that have user_id but no customer_id
        $orders = Order::whereNotNull('user_id')->whereNull('customer_id')->get();

        foreach ($orders as $order) {
            // Try to find a customer with the same email as the user
            $user = User::find($order->user_id);
            if ($user) {
                $customer = Customer::where('email', $user->email)->first();

                if ($customer) {
                    $order->customer_id = $customer->id;
                    $order->save();
                }
            }
        }

        $this->command->info('Updated ' . $orders->count() . ' orders with customer_id.');
    }
}
