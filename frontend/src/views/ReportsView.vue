<script setup lang="ts">
import { ref, onMounted, computed } from 'vue'
import { posDb, type LocalSaleRecord, type LocalItem } from '../db/posDatabase'
import { BarChart3, TrendingUp, AlertTriangle, FileSpreadsheet, Receipt } from 'lucide-vue-next'

const sales = ref<LocalSaleRecord[]>([])
const items = ref<LocalItem[]>([])

onMounted(async () => {
  sales.value = await posDb.sales_queue.toArray()
  items.value = await posDb.items.toArray()
})

const totalSalesAmount = computed(() => {
  return sales.value.reduce((acc, s) => acc + s.grand_total, 0)
})

const totalTaxCollected = computed(() => {
  return sales.value.reduce((acc, s) => acc + s.total_tax, 0)
})

const totalCgstCollected = computed(() => {
  return sales.value.reduce((acc, s) => acc + s.cgst_total, 0)
})

const totalSgstCollected = computed(() => {
  return sales.value.reduce((acc, s) => acc + s.sgst_total, 0)
})

const totalIgstCollected = computed(() => {
  return sales.value.reduce((acc, s) => acc + s.igst_total, 0)
})

const lowStockItems = computed(() => {
  return items.value.filter(i => i.stock_quantity <= 15)
})
</script>

<template>
  <div class="flex-1 flex flex-col p-6 bg-slate-900 text-slate-100 overflow-y-auto">
    <!-- Header -->
    <div class="flex items-center gap-3 mb-6">
      <div class="p-2.5 bg-purple-600/20 text-purple-400 rounded-xl">
        <BarChart3 class="w-6 h-6" />
      </div>
      <div>
        <h1 class="text-xl font-bold text-slate-100">Reports & GST Analytics</h1>
        <p class="text-xs text-slate-400">Real-time GSTR-1 Tax Summary & Inventory Valuation</p>
      </div>
    </div>

    <!-- Metric Cards -->
    <div class="grid grid-cols-4 gap-4 mb-6">
      <div class="bg-slate-950 border border-slate-800 rounded-2xl p-4 shadow">
        <div class="flex items-center justify-between text-slate-400 text-xs mb-2">
          <span>Gross Sales Revenue</span>
          <TrendingUp class="w-4 h-4 text-emerald-400" />
        </div>
        <p class="text-2xl font-bold font-mono text-emerald-400">₹{{ totalSalesAmount.toFixed(2) }}</p>
        <p class="text-[11px] text-slate-500 mt-1">Total Invoices: {{ sales.length }}</p>
      </div>

      <div class="bg-slate-950 border border-slate-800 rounded-2xl p-4 shadow">
        <div class="flex items-center justify-between text-slate-400 text-xs mb-2">
          <span>Total GST Collected</span>
          <FileSpreadsheet class="w-4 h-4 text-purple-400" />
        </div>
        <p class="text-2xl font-bold font-mono text-purple-400">₹{{ totalTaxCollected.toFixed(2) }}</p>
        <p class="text-[11px] text-slate-500 mt-1">CGST + SGST + IGST</p>
      </div>

      <div class="bg-slate-950 border border-slate-800 rounded-2xl p-4 shadow">
        <div class="flex items-center justify-between text-slate-400 text-xs mb-2">
          <span>GST Breakdown</span>
          <Receipt class="w-4 h-4 text-blue-400" />
        </div>
        <p class="text-xs font-bold font-mono text-slate-200">CGST: ₹{{ totalCgstCollected.toFixed(2) }}</p>
        <p class="text-xs font-bold font-mono text-slate-200 mt-0.5">SGST: ₹{{ totalSgstCollected.toFixed(2) }}</p>
        <p class="text-xs font-bold font-mono text-blue-400 mt-0.5">IGST: ₹{{ totalIgstCollected.toFixed(2) }}</p>
      </div>

      <div class="bg-slate-950 border border-slate-800 rounded-2xl p-4 shadow">
        <div class="flex items-center justify-between text-slate-400 text-xs mb-2">
          <span>Low Stock Alerts</span>
          <AlertTriangle class="w-4 h-4 text-rose-400" />
        </div>
        <p class="text-2xl font-bold font-mono text-rose-400">{{ lowStockItems.length }} Products</p>
        <p class="text-[11px] text-slate-500 mt-1">Items at or below reorder limit</p>
      </div>
    </div>

    <!-- Tables Grid -->
    <div class="grid grid-cols-2 gap-5 flex-1">
      <!-- Recent Bills / Invoices -->
      <div class="bg-slate-950 border border-slate-800 rounded-2xl p-4 flex flex-col">
        <h2 class="text-xs font-bold uppercase tracking-wider text-slate-300 mb-3 flex items-center gap-2">
          <Receipt class="w-4 h-4 text-emerald-400" />
          <span>Recent Sales Log</span>
        </h2>
        <div class="flex-1 overflow-auto border border-slate-800 rounded-xl">
          <table class="w-full text-left text-xs">
            <thead class="bg-slate-900 text-slate-400 uppercase text-[10px] tracking-wider border-b border-slate-800 sticky top-0">
              <tr>
                <th class="py-2.5 px-3">Invoice #</th>
                <th class="py-2.5 px-3">Customer</th>
                <th class="py-2.5 px-3 text-right">Tax (₹)</th>
                <th class="py-2.5 px-3 text-right">Grand Total (₹)</th>
                <th class="py-2.5 px-3 text-center">Sync Status</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-800/60">
              <tr v-if="sales.length === 0">
                <td colspan="5" class="py-8 text-center text-slate-500">No sales recorded yet.</td>
              </tr>
              <tr v-for="s in sales" :key="s.uuid" class="hover:bg-slate-900/40">
                <td class="py-2.5 px-3 font-mono font-bold text-slate-200">{{ s.invoice_number }}</td>
                <td class="py-2.5 px-3 text-slate-300">{{ s.customer_name }}</td>
                <td class="py-2.5 px-3 text-right font-mono text-slate-400">₹{{ s.total_tax.toFixed(2) }}</td>
                <td class="py-2.5 px-3 text-right font-mono font-bold text-emerald-400">₹{{ s.grand_total.toFixed(2) }}</td>
                <td class="py-2.5 px-3 text-center">
                  <span 
                    :class="s.sync_status === 'synced' ? 'bg-emerald-950 text-emerald-400 border-emerald-800' : 'bg-amber-950 text-amber-400 border-amber-800'"
                    class="px-2 py-0.5 rounded-full border text-[10px] uppercase font-bold"
                  >
                    {{ s.sync_status }}
                  </span>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

      <!-- Low Stock Warnings -->
      <div class="bg-slate-950 border border-slate-800 rounded-2xl p-4 flex flex-col">
        <h2 class="text-xs font-bold uppercase tracking-wider text-slate-300 mb-3 flex items-center gap-2">
          <AlertTriangle class="w-4 h-4 text-rose-400" />
          <span>Low Stock Inventory Warning</span>
        </h2>
        <div class="flex-1 overflow-auto border border-slate-800 rounded-xl">
          <table class="w-full text-left text-xs">
            <thead class="bg-slate-900 text-slate-400 uppercase text-[10px] tracking-wider border-b border-slate-800 sticky top-0">
              <tr>
                <th class="py-2.5 px-3">Item Name</th>
                <th class="py-2.5 px-3">Barcode</th>
                <th class="py-2.5 px-3 text-right">Selling Price</th>
                <th class="py-2.5 px-3 text-center">Stock Remaining</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-800/60">
              <tr v-if="lowStockItems.length === 0">
                <td colspan="4" class="py-8 text-center text-slate-500">All inventory levels healthy!</td>
              </tr>
              <tr v-for="item in lowStockItems" :key="item.id" class="hover:bg-slate-900/40">
                <td class="py-2.5 px-3 font-medium text-slate-200">{{ item.name }}</td>
                <td class="py-2.5 px-3 font-mono text-slate-400">{{ item.barcode }}</td>
                <td class="py-2.5 px-3 text-right font-mono text-emerald-400">₹{{ item.unit_price.toFixed(2) }}</td>
                <td class="py-2.5 px-3 text-center">
                  <span class="bg-rose-950 text-rose-400 border border-rose-800 px-2 py-0.5 rounded-full text-[10px] font-bold">
                    {{ item.stock_quantity }} units
                  </span>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
</template>
