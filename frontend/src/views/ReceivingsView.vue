<script setup lang="ts">
import { ref, onMounted, computed } from 'vue'
import { posDb, type LocalItem } from '../db/posDatabase'
import { Truck, Plus, Trash2, CheckCircle, PackageCheck } from 'lucide-vue-next'

const items = ref<LocalItem[]>([])
const selectedSupplier = ref('Tata Consumer Products Ltd')
const receivingCart = ref<{ item: LocalItem; quantity: number; costPrice: number }[]>([])
const successMsg = ref('')

onMounted(async () => {
  items.value = await posDb.items.toArray()
})

function addItemToReceiving(item: LocalItem) {
  const existing = receivingCart.value.find(r => r.item.id === item.id)
  if (existing) {
    existing.quantity += 5
  } else {
    receivingCart.value.push({
      item,
      quantity: 10,
      costPrice: item.cost_price
    })
  }
}

function removeReceivingItem(index: number) {
  receivingCart.value.splice(index, 1)
}

const totalReceivingAmount = computed(() => {
  return receivingCart.value.reduce((acc, r) => acc + (r.quantity * r.costPrice), 0)
})

async function completeReceiving() {
  if (receivingCart.value.length === 0) {
    alert('Receiving cart is empty')
    return
  }

  // Atomically increment stock in Dexie items table
  for (const entry of receivingCart.value) {
    const current = await posDb.items.get(entry.item.id)
    if (current) {
      await posDb.items.update(entry.item.id, {
        stock_quantity: current.stock_quantity + entry.quantity,
        cost_price: entry.costPrice,
        updated_at: new Date().toISOString()
      })
    }
  }

  items.value = await posDb.items.toArray()
  receivingCart.value = []
  successMsg.value = `Successfully received goods into inventory! Stock counts updated.`
  setTimeout(() => { successMsg.value = '' }, 4000)
}
</script>

<template>
  <div class="flex-1 flex flex-col p-6 bg-slate-900 text-slate-100 overflow-hidden">
    <!-- Header -->
    <div class="flex items-center justify-between mb-5">
      <div class="flex items-center gap-3">
        <div class="p-2.5 bg-amber-600/20 text-amber-400 rounded-xl">
          <Truck class="w-6 h-6" />
        </div>
        <div>
          <h1 class="text-xl font-bold text-slate-100">Receivings / Goods Inward</h1>
          <p class="text-xs text-slate-400">Supplier Purchase Orders & Stock Inward Intake</p>
        </div>
      </div>

      <div class="flex items-center gap-3">
        <select v-model="selectedSupplier" class="bg-slate-950 border border-slate-700 rounded-xl px-3 py-2 text-xs text-slate-100">
          <option value="Tata Consumer Products Ltd">Tata Consumer Products Ltd (GSTIN: 27AABCT1234F1Z1)</option>
          <option value="Hindustan Unilever Distributor">Hindustan Unilever Distributor (GSTIN: 27AABCH5678F1Z2)</option>
          <option value="Electronics Wholesale Mumbai">Electronics Wholesale Mumbai (GSTIN: 27AABCE9999F1Z9)</option>
        </select>
      </div>
    </div>

    <!-- Main Workspace -->
    <div class="flex-1 flex gap-4 overflow-hidden">
      <!-- Item Selection for Inward -->
      <div class="w-5/12 border border-slate-800 rounded-xl bg-slate-950/60 p-4 flex flex-col overflow-hidden">
        <h2 class="text-xs font-bold text-slate-300 uppercase tracking-wider mb-3">Tap to Receive Products</h2>
        <div class="flex-1 overflow-y-auto space-y-2 pr-1">
          <div 
            v-for="item in items" 
            :key="item.id"
            @click="addItemToReceiving(item)"
            class="p-2.5 bg-slate-900 hover:bg-slate-800 border border-slate-800 hover:border-amber-500/50 rounded-xl cursor-pointer transition flex items-center justify-between"
          >
            <div>
              <p class="text-xs font-semibold text-slate-200">{{ item.name }}</p>
              <p class="text-[11px] text-slate-400 font-mono">Barcode: {{ item.barcode }} | Current Stock: {{ item.stock_quantity }}</p>
            </div>
            <Plus class="w-4 h-4 text-amber-400" />
          </div>
        </div>
      </div>

      <!-- Receiving Table -->
      <div class="w-7/12 border border-slate-800 rounded-xl bg-slate-950/60 p-4 flex flex-col justify-between overflow-hidden">
        <div>
          <h2 class="text-xs font-bold text-slate-300 uppercase tracking-wider mb-3">Receiving Inward Order Sheet</h2>
          <div class="max-h-[50vh] overflow-y-auto border border-slate-800 rounded-xl bg-slate-900/40">
            <table class="w-full text-left text-xs">
              <thead class="bg-slate-950 text-slate-400 uppercase text-[10px] tracking-wider border-b border-slate-800 sticky top-0">
                <tr>
                  <th class="py-2.5 px-3">Item</th>
                  <th class="py-2.5 px-3 text-center">Qty Received</th>
                  <th class="py-2.5 px-3 text-right">Cost Price (₹)</th>
                  <th class="py-2.5 px-3 text-right">Total (₹)</th>
                  <th class="py-2.5 px-2"></th>
                </tr>
              </thead>
              <tbody class="divide-y divide-slate-800/60">
                <tr v-if="receivingCart.length === 0">
                  <td colspan="5" class="py-12 text-center text-slate-500">
                    No items selected. Tap items from the left to receive stock.
                  </td>
                </tr>
                <tr v-for="(entry, idx) in receivingCart" :key="entry.item.id">
                  <td class="py-2.5 px-3 font-medium text-slate-200">{{ entry.item.name }}</td>
                  <td class="py-2.5 px-3 text-center">
                    <input v-model.number="entry.quantity" type="number" min="1" class="w-16 bg-slate-950 border border-slate-700 rounded text-center py-1 font-bold text-amber-400" />
                  </td>
                  <td class="py-2.5 px-3 text-right">
                    <input v-model.number="entry.costPrice" type="number" step="0.01" class="w-20 bg-slate-950 border border-slate-700 rounded text-right py-1 font-mono text-slate-200" />
                  </td>
                  <td class="py-2.5 px-3 text-right font-mono font-bold text-slate-100">
                    ₹{{ (entry.quantity * entry.costPrice).toFixed(2) }}
                  </td>
                  <td class="py-2.5 px-2 text-center">
                    <button @click="removeReceivingItem(idx)" class="text-slate-500 hover:text-rose-400">
                      <Trash2 class="w-3.5 h-3.5" />
                    </button>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>

        <div class="border-t border-slate-800 pt-3">
          <div v-if="successMsg" class="mb-3 text-xs bg-emerald-950 border border-emerald-800 text-emerald-400 px-3 py-2 rounded-xl flex items-center gap-2">
            <CheckCircle class="w-4 h-4" />
            <span>{{ successMsg }}</span>
          </div>

          <div class="flex items-center justify-between mb-3">
            <span class="text-xs text-slate-400">Total Purchase Value:</span>
            <span class="text-lg font-bold font-mono text-amber-400">₹{{ totalReceivingAmount.toFixed(2) }}</span>
          </div>

          <button 
            @click="completeReceiving"
            :disabled="receivingCart.length === 0"
            class="w-full bg-amber-600 hover:bg-amber-500 disabled:opacity-40 text-slate-950 font-bold py-3 rounded-xl flex items-center justify-center gap-2 shadow-lg transition"
          >
            <PackageCheck class="w-5 h-5" />
            <span>Confirm & Update Inventory Stock</span>
          </button>
        </div>
      </div>
    </div>
  </div>
</template>
