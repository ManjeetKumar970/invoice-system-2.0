<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database with GST masters and sample catalog.
     */
    public function run(): void
    {
        // 1. Admin Cashier User
        $userId = DB::table('users')->insertGetId([
            'name'       => 'Cashier Admin',
            'email'      => 'admin@ftpl.com',
            'password'   => Hash::make('password'),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // 2. Default Store
        $storeId = DB::table('stores')->insertGetId([
            'uuid'            => (string) Str::uuid(),
            'name'            => 'FTPL Retail Superstore',
            'code'            => 'FTPL-HQ',
            'phone'           => '+91 98765 43210',
            'email'           => 'store@ftpl.com',
            'address'         => 'Plot 42, Commerce Park, Andheri East',
            'city'            => 'Mumbai',
            'state'           => 'Maharashtra',
            'state_code'      => '27',
            'pincode'         => '400069',
            'gstin'           => '27AAAAA0000A1Z5',
            'currency_symbol' => '₹',
            'tax_included'    => false,
            'is_active'       => true,
            'created_at'      => now(),
            'updated_at'      => now(),
        ]);

        // 3. Counter Terminal
        $terminalId = DB::table('terminals')->insertGetId([
            'uuid'          => (string) Str::uuid(),
            'store_id'      => $storeId,
            'terminal_code' => 'POS-01',
            'device_name'   => 'Counter 1 Windows POS',
            'is_active'     => true,
            'created_at'    => now(),
            'updated_at'    => now(),
        ]);

        // 4. Default Stock Location
        $locationId = DB::table('stock_locations')->insertGetId([
            'uuid'       => (string) Str::uuid(),
            'store_id'   => $storeId,
            'name'       => 'Main Sales Counter',
            'is_active'  => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // 5. GST Tax Slabs
        $taxSlabs = [
            ['name' => 'GST 0% / Nil-Rated', 'rate' => 0.0,  'cgst' => 0.0,  'sgst' => 0.0,  'igst' => 0.0,  'default' => false],
            ['name' => 'GST 5%',             'rate' => 5.0,  'cgst' => 2.5,  'sgst' => 2.5,  'igst' => 5.0,  'default' => false],
            ['name' => 'GST 12%',            'rate' => 12.0, 'cgst' => 6.0,  'sgst' => 6.0,  'igst' => 12.0, 'default' => false],
            ['name' => 'GST 18%',            'rate' => 18.0, 'cgst' => 9.0,  'sgst' => 9.0,  'igst' => 18.0, 'default' => true],
            ['name' => 'GST 28%',            'rate' => 28.0, 'cgst' => 14.0, 'sgst' => 14.0, 'igst' => 28.0, 'default' => false],
            ['name' => 'GST Exempt',         'rate' => 0.0,  'cgst' => 0.0,  'sgst' => 0.0,  'igst' => 0.0,  'default' => false],
        ];

        $taxMap = [];
        foreach ($taxSlabs as $slab) {
            $taxId = DB::table('tax_categories')->insertGetId([
                'uuid'       => (string) Str::uuid(),
                'name'       => $slab['name'],
                'rate'       => $slab['rate'],
                'cgst_rate'  => $slab['cgst'],
                'sgst_rate'  => $slab['sgst'],
                'igst_rate'  => $slab['igst'],
                'cess_rate'  => 0.0,
                'is_default' => $slab['default'],
                'is_active'  => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $taxMap[(string)(float)$slab['rate']] = $taxId;
        }

        // 6. Categories
        $categories = [
            ['name' => 'Groceries & Staples',    'slug' => 'groceries-staples'],
            ['name' => 'Electronics & Cables',   'slug' => 'electronics-cables'],
            ['name' => 'Beverages & Snacks',     'slug' => 'beverages-snacks'],
            ['name' => 'Dry Fruits & Nuts',      'slug' => 'dry-fruits-nuts'],
        ];

        $catMap = [];
        foreach ($categories as $cat) {
            $catId = DB::table('categories')->insertGetId([
                'uuid'       => (string) Str::uuid(),
                'name'       => $cat['name'],
                'slug'       => $cat['slug'],
                'is_active'  => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $catMap[$cat['slug']] = $catId;
        }

        // 7. Sample Products
        $items = [
            [
                'name'          => 'Royal Basmati Rice 5KG',
                'barcode'       => '8901030382341',
                'sku'           => 'RICE-BAS-5K',
                'hsn_code'      => '1006',
                'category_slug' => 'groceries-staples',
                'tax_rate'      => '5.0',
                'cost_price'    => 420.00,
                'unit_price'    => 549.00,
                'mrp'           => 599.00,
                'reorder_level' => 10,
                'stock'         => 85,
            ],
            [
                'name'          => 'Braided USB-C Fast Charging Cable 1.5m',
                'barcode'       => '8901725181222',
                'sku'           => 'CAB-USBC-15',
                'hsn_code'      => '8544',
                'category_slug' => 'electronics-cables',
                'tax_rate'      => '18.0',
                'cost_price'    => 140.00,
                'unit_price'    => 299.00,
                'mrp'           => 399.00,
                'reorder_level' => 15,
                'stock'         => 120,
            ],
            [
                'name'          => 'Darjeeling Premium Green Tea 250g',
                'barcode'       => '8901491101833',
                'sku'           => 'TEA-GRN-250',
                'hsn_code'      => '0902',
                'category_slug' => 'beverages-snacks',
                'tax_rate'      => '5.0',
                'cost_price'    => 170.00,
                'unit_price'    => 245.00,
                'mrp'           => 260.00,
                'reorder_level' => 8,
                'stock'         => 45,
            ],
            [
                'name'          => 'Logitech B100 Optical Mouse USB',
                'barcode'       => '8902519001015',
                'sku'           => 'MOU-LOG-B100',
                'hsn_code'      => '8471',
                'category_slug' => 'electronics-cables',
                'tax_rate'      => '18.0',
                'cost_price'    => 260.00,
                'unit_price'    => 399.00,
                'mrp'           => 495.00,
                'reorder_level' => 5,
                'stock'         => 30,
            ],
            [
                'name'          => 'California Jumbo Almonds 500g',
                'barcode'       => '8901233014112',
                'sku'           => 'NUT-ALM-500',
                'hsn_code'      => '0802',
                'category_slug' => 'dry-fruits-nuts',
                'tax_rate'      => '12.0',
                'cost_price'    => 360.00,
                'unit_price'    => 480.00,
                'mrp'           => 525.00,
                'reorder_level' => 10,
                'stock'         => 60,
            ],
        ];

        foreach ($items as $item) {
            $itemId = DB::table('items')->insertGetId([
                'uuid'            => (string) Str::uuid(),
                'category_id'     => $catMap[$item['category_slug']],
                'tax_category_id' => $taxMap[(string)(float)$item['tax_rate']],
                'name'            => $item['name'],
                'barcode'         => $item['barcode'],
                'sku'             => $item['sku'],
                'hsn_code'        => $item['hsn_code'],
                'unit'            => 'PCS',
                'cost_price'      => $item['cost_price'],
                'unit_price'      => $item['unit_price'],
                'mrp'             => $item['mrp'],
                'reorder_level'   => $item['reorder_level'],
                'is_serialized'   => false,
                'track_stock'     => true,
                'is_active'       => true,
                'sync_version'    => 1,
                'created_at'      => now(),
                'updated_at'      => now(),
            ]);

            // Initial stock in item_stocks
            DB::table('item_stocks')->insert([
                'item_id'           => $itemId,
                'stock_location_id' => $locationId,
                'quantity'          => $item['stock'],
                'created_at'        => now(),
                'updated_at'        => now(),
            ]);

            // Opening stock entry in immutable inventory ledger
            DB::table('inventory_ledger')->insert([
                'uuid'              => (string) Str::uuid(),
                'item_id'           => $itemId,
                'stock_location_id' => $locationId,
                'user_id'           => $userId,
                'transaction_type'  => 'adjustment',
                'reference_uuid'    => null,
                'quantity_delta'    => $item['stock'],
                'balance_after'     => $item['stock'],
                'comment'           => 'Initial Opening Stock',
                'created_at'        => now(),
            ]);
        }

        // 8. Sample Customer
        DB::table('customers')->insert([
            'uuid'           => (string) Str::uuid(),
            'store_id'       => $storeId,
            'name'           => 'Rahul Sharma (VIP)',
            'phone'          => '9820198201',
            'email'          => 'rahul@example.com',
            'gstin'          => '27BCIPA1234F1Z8',
            'state_code'     => '27',
            'city'           => 'Mumbai',
            'loyalty_points' => 250.00,
            'credit_balance' => 0.00,
            'is_active'      => true,
            'created_at'     => now(),
            'updated_at'     => now(),
        ]);
    }
}
