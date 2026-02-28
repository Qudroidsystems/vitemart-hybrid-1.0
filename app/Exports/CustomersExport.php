<?php

namespace App\Exports;

use App\Models\Customer;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class CustomersExport implements FromCollection, WithHeadings, WithMapping
{
    public function collection()
    {
        return Customer::withCount('orders')
            ->withSum('orders', 'total_amount')
            ->get();
    }

    public function headings(): array
    {
        return [
            'ID',
            'Full Name',
            'Email',
            'Phone Number',
            'Customer Type',
            'Status',
            'Total Orders',
            'Total Spent',
            'Credit Limit',
            'Credit Balance',
            'Loyalty Points',
            'Company Name',
            'Date Created'
        ];
    }

    public function map($customer): array
    {
        return [
            $customer->id,
            $customer->first_name . ' ' . $customer->last_name,
            $customer->email,
            $customer->phone_number,
            ucfirst($customer->customer_type),
            ucfirst($customer->status),
            $customer->orders_count,
            number_format($customer->orders_sum_total_amount ?? 0, 2),
            number_format($customer->credit_limit, 2),
            number_format($customer->credit_balance, 2),
            $customer->loyalty_points,
            $customer->company_name,
            $customer->created_at->format('Y-m-d H:i:s'),
        ];
    }
}
