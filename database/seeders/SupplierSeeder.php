<?php

namespace Database\Seeders;

use App\Models\Supplier;
use Illuminate\Database\Seeder;

class SupplierSeeder extends Seeder
{
    public function run(): void
    {
        foreach (['supplier-a', 'supplier-b'] as $name) {
            Supplier::firstOrCreate(['name' => $name]);
        }
    }
}
