<?php

namespace App\Exports;

use App\Models\User;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Color;

class CustomersExport implements FromCollection, WithHeadings, WithMapping, WithStyles, ShouldAutoSize
{

   public function collection()
    {
        $customers = User::with(['orders.orderItems'])
            ->withCount('orders')
            ->withSum('orders', 'total')
            ->get();

        $data = collect();

        foreach ($customers as $customer) {
            if ($customer->orders->count() > 0) {
                foreach ($customer->orders as $order) {
                    if ($order->orderItems->count() > 0) {
                        foreach ($order->orderItems as $item) {
                            $data->push([
                                'customer' => $customer,
                                'order' => $order,
                                'item' => $item,
                                'total_orders' => $customer->orders_count,
                                'total_spent' => $customer->orders_sum_total,
                            ]);
                        }
                    } else {
                        $data->push([
                            'customer' => $customer,
                            'order' => $order,
                            'item' => null,
                            'total_orders' => $customer->orders_count,
                            'total_spent' => $customer->orders_sum_total,
                        ]);
                    }
                }
            } else {
                $data->push([
                    'customer' => $customer,
                    'order' => null,
                    'item' => null,
                    'total_orders' => 0,
                    'total_spent' => 0,
                ]);
            }
        }

        return $data;
    }

    public function headings(): array
    {
        return [
            'Customer ID',
            'Customer Name',
            'Email',
            'Phone',
            'Join Date',
            'Total Orders',
            'Total Spent',
            'Loyalty Points',
            'Order Number',
            'Order Date',
            'Order Status',
            'Product Name',
            'Quantity',
            'Unit Price',
            'Item Subtotal',
            'Order Total',
        ];
    }

    public function map($row): array
    {
        $customer = $row['customer'];
        $order = $row['order'];
        $item = $row['item'];

        return [
            $customer->id,
            $customer->name,
            $customer->email ?? 'N/A',
            $customer->phone ?? 'N/A',
            $customer->created_at->format('d M Y'),
            $row['total_orders'],
            number_format($row['total_spent'], 2),
            $customer->loyalty_points,

            $order ? $order->order_number : 'No Orders',
            $order ? $order->created_at->format('d M Y') : 'N/A',
            $order ? ucfirst($order->order_status) : 'N/A',

            $item ? $item->product_name : 'N/A',
            $item ? $item->quantity : 'N/A',
            $item ? number_format($item->price, 2) : 'N/A',
            $item ? number_format($item->subtotal, 2) : 'N/A',
            $order ? number_format($order->total, 2) : 'N/A',
        ];
    }

    public function styles(Worksheet $sheet)
    {
        return [
            1 => [
                'font' => ['bold' => true, 'size' => 12, 'color' => ['rgb' => 'FFFFFF']],
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => ['rgb' => '4472C4']
                ]
            ],
        ];
    }
}
