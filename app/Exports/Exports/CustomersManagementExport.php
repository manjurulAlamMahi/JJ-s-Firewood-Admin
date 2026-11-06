<?php

namespace App\Exports;

use App\Models\User;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class CustomersManagementExport implements FromQuery, WithHeadings, WithMapping
{
    use Exportable;

    protected $filters;

    public function __construct(array $filters)
    {
        $this->filters = $filters;
    }

    public function query()
    {
        $query = User::query()
            ->where('is_guest', 0)
            ->with(['shippingAddresses'])
            ->withCount(['orders', 'entries'])
            ->withSum('orders as total_spent', 'total');

        // Search
        if (isset($this->filters['search']) && $this->filters['search']) {
            $search = $this->filters['search'];
            $query->where(function($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%")
                  ->orWhere('phone', 'like', "%{$search}%");
            });
        }

        // Filter by role
        if (isset($this->filters['role']) && $this->filters['role']) {
            $query->where('role', $this->filters['role']);
        }

        // Filter by status
        if (isset($this->filters['status']) && $this->filters['status']) {
            if ($this->filters['status'] === 'active') {
                $query->where(function($q) {
                    $q->whereNotNull('fcm_token')
                      ->orWhereNotNull('device_token');
                });
            } else {
                $query->whereNull('fcm_token')
                      ->whereNull('device_token');
            }
        }

        // Filter by city
        if (isset($this->filters['city']) && $this->filters['city']) {
            $city = $this->filters['city'];
            $query->whereHas('shippingAddresses', function($q) use ($city) {
                $q->where('city', $city);
            });
        }

        // Filter by state
        if (isset($this->filters['state']) && $this->filters['state']) {
            $state = $this->filters['state'];
            $query->whereHas('shippingAddresses', function($q) use ($state) {
                $q->where('state', $state);
            });
        }

        return $query->latest();
    }


    public function headings(): array
    {
        return [
            'ID',
            'Name',
            'Email',
            'Phone',
            'Role',
            'Total Orders',
            'Total Spent',
            'Total Entries',
            'Joined Date',
            'Status',
        ];
    }

    public function map($customer): array
    {
        $isActive = ($customer->fcm_token || $customer->device_token) ? 'Active' : 'Inactive';
        
        return [
            $customer->id,
            $customer->name,
            $customer->email,
            $customer->phone,
            ucfirst($customer->role),
            $customer->orders_count,
            $customer->total_spent ?? 0,
            $customer->entries_count ?? 0,
            $customer->created_at->format('Y-m-d'),
            $isActive,
        ];
    }
}
