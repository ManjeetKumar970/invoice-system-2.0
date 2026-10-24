<script setup lang="ts">
import { ref, onMounted, computed } from 'vue'
import { posDb, type LocalCustomer } from '../db/posDatabase'
import { usePosCartStore } from '../stores/posCartStore'
import { Users, Plus, Search, UserCheck, Trash2, X } from 'lucide-vue-next'

const cartStore = usePosCartStore()
const customers = ref<LocalCustomer[]>([])
const search = ref('')
const showModal = ref(false)

const form = ref({
  name: '',
  phone: '',
  gstin: '',
  state_code: '27',
  email: ''
})

const emit = defineEmits<{
  (e: 'navigate', view: string): void
}>()

onMounted(async () => {
  await loadCustomers()
})

async function loadCustomers() {
  customers.value = await posDb.customers.toArray()
}

const filteredCustomers = computed(() => {
  return customers.value.filter(c => 
    !search.value ||
    c.name.toLowerCase().includes(search.value.toLowerCase()) ||
    c.phone.includes(search.value) ||
    (c.gstin && c.gstin.toLowerCase().includes(search.value.toLowerCase()))
  )
})

function openAddModal() {
  form.value = {
    name: '',
    phone: '',
    gstin: '',
    state_code: '27',
    email: ''
  }
  showModal.value = true
}

async function saveCustomer() {
  if (!form.value.name || !form.value.phone) {
    alert('Customer Name and Phone are required')
    return
  }

  const maxId = (await posDb.customers.toCollection().last())?.id || 0
  const newCust: LocalCustomer = {
    id: maxId + 1,
    uuid: crypto.randomUUID(),
    name: form.value.name,
    phone: form.value.phone,
    email: form.value.email || undefined,
    gstin: form.value.gstin ? form.value.gstin.toUpperCase() : undefined,
    state_code: form.value.state_code,
    loyalty_points: 0,
    updated_at: new Date().toISOString()
  }

  await posDb.customers.add(newCust)
  showModal.value = false
  await loadCustomers()
}

function selectForBilling(c: LocalCustomer) {
  cartStore.selectedCustomer = c
  emit('navigate', 'sales')
}

async function deleteCustomer(id: number) {
  if (confirm('Delete this customer?')) {
    await posDb.customers.delete(id)
    await loadCustomers()
  }
}
</script>

<template>
  <div class="flex-1 flex flex-col p-6 bg-slate-900 text-slate-100 overflow-hidden">
    <!-- Header -->
    <div class="flex items-center justify-between mb-5">
      <div class="flex items-center gap-3">
        <div class="p-2.5 bg-blue-600/20 text-blue-400 rounded-xl">
          <Users class="w-6 h-6" />
        </div>
        <div>
          <h1 class="text-xl font-bold text-slate-100">Customer Directory</h1>
          <p class="text-xs text-slate-400">Total Registered Customers: {{ customers.length }}</p>
        </div>
      </div>

      <div class="flex items-center gap-3">
        <div class="relative w-72">
          <Search class="absolute left-3.5 top-2.5 w-4 h-4 text-slate-400" />
          <input 
            v-model="search"
            type="text" 
            placeholder="Search by name, mobile, GSTIN..."
            class="w-full bg-slate-950 border border-slate-700 rounded-xl pl-10 pr-3 py-2 text-xs text-slate-100 placeholder-slate-500 focus:outline-none focus:border-blue-500 transition"
          />
        </div>

        <button 
          @click="openAddModal"
          class="bg-blue-600 hover:bg-blue-500 text-white font-semibold px-4 py-2 rounded-xl text-xs flex items-center gap-2 shadow-lg transition"
        >
          <Plus class="w-4 h-4" />
          <span>New Customer</span>
        </button>
      </div>
    </div>

    <!-- Data Table -->
    <div class="flex-1 overflow-auto border border-slate-800 rounded-xl bg-slate-950/60 shadow">
      <table class="w-full text-left text-xs">
        <thead class="bg-slate-950 border-b border-slate-800 text-slate-400 uppercase text-[10px] tracking-wider sticky top-0">
          <tr>
            <th class="py-3 px-4">Customer Name</th>
            <th class="py-3 px-4">Mobile Phone</th>
            <th class="py-3 px-3">GSTIN Number</th>
            <th class="py-3 px-3">State Code</th>
            <th class="py-3 px-3 text-right">Loyalty Points</th>
            <th class="py-3 px-4 text-center">Action</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-slate-800/60">
          <tr v-for="c in filteredCustomers" :key="c.id" class="hover:bg-slate-800/40 transition">
            <td class="py-3 px-4 font-semibold text-slate-200">
              {{ c.name }}
              <span v-if="cartStore.selectedCustomer?.id === c.id" class="ml-2 text-[10px] bg-emerald-950 text-emerald-400 border border-emerald-800 px-1.5 py-0.5 rounded">Active in Register</span>
            </td>
            <td class="py-3 px-4 font-mono text-slate-300">{{ c.phone }}</td>
            <td class="py-3 px-3 font-mono text-emerald-400 font-medium">{{ c.gstin || 'B2C (Unregistered)' }}</td>
            <td class="py-3 px-3 font-mono text-slate-300">{{ c.state_code }}</td>
            <td class="py-3 px-3 text-right font-mono font-bold text-amber-400">{{ c.loyalty_points.toFixed(0) }} pts</td>
            <td class="py-3 px-4 text-center">
              <div class="flex items-center justify-center gap-2">
                <button 
                  @click="selectForBilling(c)"
                  class="bg-emerald-600 hover:bg-emerald-500 text-white px-2.5 py-1 rounded-lg text-[11px] font-semibold flex items-center gap-1 shadow"
                >
                  <UserCheck class="w-3 h-3" />
                  <span>Select for POS</span>
                </button>
                <button @click="deleteCustomer(c.id)" class="p-1 hover:text-rose-400 text-slate-400 transition">
                  <Trash2 class="w-3.5 h-3.5" />
                </button>
              </div>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- Add Modal -->
    <div v-if="showModal" class="fixed inset-0 bg-black/75 flex items-center justify-center z-50 p-4">
      <div class="bg-slate-900 border border-slate-700 rounded-2xl w-full max-w-md p-5 shadow-2xl">
        <div class="flex items-center justify-between border-b border-slate-800 pb-3 mb-4">
          <h2 class="text-base font-bold text-slate-100">Add Customer</h2>
          <button @click="showModal = false" class="text-slate-400 hover:text-white">
            <X class="w-5 h-5" />
          </button>
        </div>

        <div class="space-y-3 text-xs">
          <div>
            <label class="block text-slate-400 mb-1">Customer / Company Name</label>
            <input v-model="form.name" type="text" class="w-full bg-slate-950 border border-slate-700 rounded-lg px-3 py-2 text-slate-100" />
          </div>

          <div class="grid grid-cols-2 gap-3">
            <div>
              <label class="block text-slate-400 mb-1">Mobile Phone</label>
              <input v-model="form.phone" type="text" placeholder="10 digits" class="w-full bg-slate-950 border border-slate-700 rounded-lg px-3 py-2 text-slate-100 font-mono" />
            </div>
            <div>
              <label class="block text-slate-400 mb-1">State Code</label>
              <select v-model="form.state_code" class="w-full bg-slate-950 border border-slate-700 rounded-lg px-3 py-2 text-slate-100">
                <option value="27">27 - Maharashtra</option>
                <option value="24">24 - Gujarat</option>
                <option value="07">07 - Delhi</option>
                <option value="29">29 - Karnataka</option>
                <option value="33">33 - Tamil Nadu</option>
              </select>
            </div>
          </div>

          <div>
            <label class="block text-slate-400 mb-1">GSTIN Number (Optional for B2B Invoices)</label>
            <input v-model="form.gstin" type="text" placeholder="e.g. 27AAAAA0000A1Z5" class="w-full bg-slate-950 border border-slate-700 rounded-lg px-3 py-2 text-slate-100 font-mono uppercase" />
          </div>
        </div>

        <div class="mt-5 pt-3 border-t border-slate-800 flex justify-end gap-2 text-xs">
          <button @click="showModal = false" class="px-4 py-2 border border-slate-700 rounded-lg hover:bg-slate-800 text-slate-300">
            Cancel
          </button>
          <button @click="saveCustomer" class="px-5 py-2 bg-blue-600 hover:bg-blue-500 text-white font-bold rounded-lg shadow">
            Save Customer
          </button>
        </div>
      </div>
    </div>
  </div>
</template>
