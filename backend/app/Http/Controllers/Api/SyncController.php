<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class SyncController extends Controller
{
    /**
     * Pull master items, tax slabs, and customers for offline POS cache.
     */
    public function pull(Request $request): JsonResponse
    {
        $since = $request->query('since');

        $itemsQuery = DB::table('items')
            ->join('tax_categories', 'items.tax_category_id', '=', 'tax_categories.id')
            ->leftJoin('categories', 'items.category_id', '=', 'categories.id')
            ->select(
                'items.id',
                'items.uuid',
                'items.name',
                'items.barcode',
                'items.sku',
                'items.hsn_code',
                'items.unit',
                'items.cost_price',
                'items.unit_price',
                'items.mrp',
                'items.is_active',
                'items.updated_at',
                'tax_categories.rate as tax_rate',
                'tax_categories.id as tax_category_id',
                'categories.name as category_name'
            );

        if ($since) {
            $itemsQuery->where('items.updated_at', '>', $since);
        }

        $items = $itemsQuery->where('items.is_active', true)->get();

        $taxSlabs = DB::table('tax_categories')
            ->where('is_active', true)
            ->get();

        $customers = DB::table('customers')
            ->where('is_active', true)
            ->get();

        return response()->json([
            'status'     => 'success',
            'server_time'=> now()->toISOString(),
            'items'      => $items,
            'tax_slabs'  => $taxSlabs,
            'customers'  => $customers,
        ]);
    }

    /**
     * Push offline bills from register queue into central MySQL database.
     */
    public function push(Request $request): JsonResponse
    {
        $request->validate([
            'sales' => 'required|array',
            'sales.*.uuid' => 'required|string',
            'sales.*.invoice_number' => 'required|string',
            'sales.*.grand_total' => 'required|numeric',
            'sales.*.items' => 'required|array',
        ]);

        $sales = $request->input('sales');
        $syncedUuids = [];
        $failedUuids = [];

        $defaultStore = DB::table('stores')->first();
        $defaultTerminal = DB::table('terminals')->first();

        foreach ($sales as $saleData) {
            try {
                DB::transaction(function () use ($saleData, $defaultStore, $defaultTerminal, &$syncedUuids) {
                    // Check if already synced to avoid duplicate insertion
                    $existing = DB::table('sales')->where('uuid', $saleData['uuid'])->first();
                    if ($existing) {
                        $syncedUuids[] = $saleData['uuid'];
                        return;
                    }

                    // 1. Insert Master Sale
                    $saleId = DB::table('sales')->insertGetId([
                        'uuid'           => $saleData['uuid'],
                        'store_id'       => $defaultStore?->id ?? 1,
                        'terminal_id'    => $defaultTerminal?->id ?? 1,
                        'customer_id'    => $saleData['customer_id'] ?? null,
                        'invoice_number' => $saleData['invoice_number'],
                        'invoice_type'   => 'tax_invoice',
                        'sale_status'    => 'completed',
                        'is_interstate'  => $saleData['is_interstate'] ?? false,
                        'customer_gstin' => $saleData['customer_gstin'] ?? null,
                        'subtotal'       => $saleData['subtotal'],
                        'discount_total' => $saleData['discount_total'] ?? 0,
                        'cgst_total'     => $saleData['cgst_total'] ?? 0,
                        'sgst_total'     => $saleData['sgst_total'] ?? 0,
                        'igst_total'     => $saleData['igst_total'] ?? 0,
                        'cess_total'     => 0,
                        'tax_total'      => $saleData['total_tax'] ?? 0,
                        'round_off'      => $saleData['round_off'] ?? 0,
                        'grand_total'    => $saleData['grand_total'],
                        'amount_paid'    => $saleData['grand_total'],
                        'change_due'     => 0,
                        'payment_status' => 'paid',
                        'sale_date'      => substr($saleData['created_at'], 0, 10),
                        'sale_time'      => $saleData['created_at'],
                        'sync_status'    => 'synced',
                        'synced_at'      => now(),
                        'created_at'     => $saleData['created_at'],
                        'updated_at'     => now(),
                    ]);

                    // 2. Insert Line Items & Deduct Stock
                    $lineNum = 1;
                    foreach ($saleData['items'] as $itemData) {
                        DB::table('sale_items')->insert([
                            'uuid'             => (string) Str::uuid(),
                            'sale_id'          => $saleId,
                            'item_id'          => $itemData['item_id'],
                            'line_number'      => $lineNum++,
                            'item_name'        => $itemData['name'],
                            'barcode'          => $itemData['barcode'],
                            'hsn_code'         => $itemData['hsn_code'] ?? '',
                            'quantity'         => $itemData['quantity'],
                            'cost_price'       => 0,
                            'unit_price'       => $itemData['unit_price'],
                            'discount_percent' => $itemData['discount_percent'] ?? 0,
                            'discount_amount'  => $itemData['discount_amount'] ?? 0,
                            'taxable_amount'   => $itemData['taxable_amount'],
                            'tax_rate'         => $itemData['tax_rate'],
                            'cgst_rate'        => $itemData['tax_rate'] / 2,
                            'cgst_amount'      => $itemData['cgst_amount'] ?? 0,
                            'sgst_rate'        => $itemData['tax_rate'] / 2,
                            'sgst_amount'      => $itemData['sgst_amount'] ?? 0,
                            'igst_rate'        => $itemData['tax_rate'],
                            'igst_amount'      => $itemData['igst_amount'] ?? 0,
                            'cess_rate'        => 0,
                            'cess_amount'      => 0,
                            'total_tax'        => $itemData['total_tax'],
                            'line_total'       => $itemData['line_total'],
                            'created_at'       => $saleData['created_at'],
                            'updated_at'       => now(),
                        ]);

                        // Decrement stock in item_stocks
                        DB::table('item_stocks')
                            ->where('item_id', $itemData['item_id'])
                            ->decrement('quantity', $itemData['quantity']);

                        // Add audit ledger entry
                        $curStock = DB::table('item_stocks')->where('item_id', $itemData['item_id'])->value('quantity') ?? 0;
                        DB::table('inventory_ledger')->insert([
                            'uuid'              => (string) Str::uuid(),
                            'item_id'           => $itemData['item_id'],
                            'stock_location_id' => 1,
                            'user_id'           => 1,
                            'transaction_type'  => 'sale',
                            'reference_uuid'    => $saleData['uuid'],
                            'quantity_delta'    => -$itemData['quantity'],
                            'balance_after'     => $curStock,
                            'comment'           => 'POS Sale ' . $saleData['invoice_number'],
                            'created_at'        => $saleData['created_at'],
                        ]);
                    }

                    // 3. Insert Payment
                    if (!empty($saleData['payments'])) {
                        foreach ($saleData['payments'] as $payment) {
                            DB::table('sale_payments')->insert([
                                'uuid'           => (string) Str::uuid(),
                                'sale_id'        => $saleId,
                                'payment_method' => $payment['payment_type'],
                                'amount'         => $payment['amount'],
                                'created_at'     => $saleData['created_at'],
                                'updated_at'     => now(),
                            ]);
                        }
                    }

                    $syncedUuids[] = $saleData['uuid'];
                });
            } catch (\Throwable $e) {
                $failedUuids[$saleData['uuid']] = $e->getMessage();
            }
        }

        return response()->json([
            'status'       => 'success',
            'synced_count' => count($syncedUuids),
            'synced_uuids' => $syncedUuids,
            'failed_uuids' => $failedUuids,
        ]);
    }
}
