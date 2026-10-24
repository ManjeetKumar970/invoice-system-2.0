<script setup lang="ts">
import { ref, onMounted, computed } from 'vue'
import { posDb, type LocalItem, type LocalTaxSlab, type LocalCategory } from '../db/posDatabase'
import { Plus, Search, Edit2, Trash2, Package, X, Tags, FolderPlus } from 'lucide-vue-next'

const items = ref<LocalItem[]>([])
const taxSlabs = ref<LocalTaxSlab[]>([])
const categories = ref<LocalCategory[]>([])
const search = ref('')
const selectedCategoryFilter = ref('All')

// Modals
const showProductModal = ref(false)
const showCategoryModal = ref(false)
const isEditing = ref(false)
const isQuickAddingCategory = ref(false)
const newCategoryInput = ref('')

const form = ref({
  id: 0,
  name: '',
  barcode: '',
  sku: '',
  hsn_code: '',
  category_id: 0,
  category_name: '',
  unit_price: 0,
  cost_price: 0,
  tax_rate: 18,
  stock_quantity: 10
})

onMounted(async () => {
  await loadData()
})

async function loadData() {
  items.value = await posDb.items.toArray()
  taxSlabs.value = await posDb.tax_slabs.toArray()
  categories.value = await posDb.categories.toArray()

  // If no categories exist yet, infer from existing items or start clean
  if (categories.value.length === 0) {
    const existingCats = new Set<string>()
    items.value.forEach(i => {
      if (i.category_name) existingCats.add(i.category_name)
    })
    
    let nextId = 1
    for (const catName of existingCats) {
      await posDb.categories.add({
        id: nextId++,
        uuid: crypto.randomUUID(),
        name: catName,
        slug: catName.toLowerCase().replace(/[^a-z0-9]+/g, '-'),
        is_active: 1
      })
    }
    categories.value = await posDb.categories.toArray()
  }
}

const filteredItems = computed(() => {
  return items.value.filter(i => {
    const matchesSearch = !search.value ||
      i.name.toLowerCase().includes(search.value.toLowerCase()) ||
      i.barcode.includes(search.value) ||
      i.hsn_code.includes(search.value)
    
    const matchesCat = selectedCategoryFilter.value === 'All' || i.category_name === selectedCategoryFilter.value
    return matchesSearch && matchesCat
  })
})

function openAddProductModal() {
  isEditing.value = false
  isQuickAddingCategory.value = false
  newCategoryInput.value = ''
  
  const defaultCat = categories.value[0]?.name || ''
  const defaultCatId = categories.value[0]?.id || 0

  form.value = {
    id: 0,
    name: '',
    barcode: '890' + Math.floor(1000000000 + Math.random() * 9000000000),
    sku: 'SKU-' + Date.now().toString().slice(-4),
    hsn_code: '1006',
    category_id: defaultCatId,
    category_name: defaultCat,
    unit_price: 100,
    cost_price: 70,
    tax_rate: 18,
    stock_quantity: 25
  }
  showProductModal.value = true
}

function openEditProductModal(item: LocalItem) {
  isEditing.value = true
  isQuickAddingCategory.value = false
  newCategoryInput.value = ''

  form.value = {
    id: item.id,
    name: item.name,
    barcode: item.barcode,
    sku: item.sku,
    hsn_code: item.hsn_code,
    category_id: item.category_id || 0,
    category_name: item.category_name || '',
    unit_price: item.unit_price,
    cost_price: item.cost_price,
    tax_rate: item.tax_rate,
    stock_quantity: item.stock_quantity
  }
  showProductModal.value = true
}

// Create custom category directly
async function createCustomCategory(catName: string) {
  const trimmed = catName.trim()
  if (!trimmed) return null

  // Check if already exists
  const existing = categories.value.find(c => c.name.toLowerCase() === trimmed.toLowerCase())
  if (existing) {
    return existing
  }

  const maxId = (await posDb.categories.toCollection().last())?.id || 0
  const newCat: LocalCategory = {
    id: maxId + 1,
    uuid: crypto.randomUUID(),
    name: trimmed,
    slug: trimmed.toLowerCase().replace(/[^a-z0-9]+/g, '-'),
    is_active: 1
  }

  await posDb.categories.add(newCat)
  categories.value = await posDb.categories.toArray()
  return newCat
}

async function handleQuickAddCategory() {
  if (!newCategoryInput.value.trim()) return
  const created = await createCustomCategory(newCategoryInput.value)
  if (created) {
    form.value.category_id = created.id
    form.value.category_name = created.name
    newCategoryInput.value = ''
    isQuickAddingCategory.value = false
  }
}

async function saveProduct() {
  if (!form.value.name || !form.value.barcode) {
    alert('Name and Barcode are required')
    return
  }

  // Ensure category is assigned
  if (!form.value.category_name && categories.value.length > 0) {
    form.value.category_name = categories.value[0].name
    form.value.category_id = categories.value[0].id
  }

  if (isEditing.value) {
    await posDb.items.update(form.value.id, {
      name: form.value.name,
      barcode: form.value.barcode,
      sku: form.value.sku,
      hsn_code: form.value.hsn_code,
      category_id: form.value.category_id,
      category_name: form.value.category_name,
      unit_price: Number(form.value.unit_price),
      cost_price: Number(form.value.cost_price),
      tax_rate: Number(form.value.tax_rate),
      stock_quantity: Number(form.value.stock_quantity),
      updated_at: new Date().toISOString()
    })
  } else {
    const maxId = (await posDb.items.toCollection().last())?.id || 0
    await posDb.items.add({
      id: maxId + 1,
      uuid: crypto.randomUUID(),
      name: form.value.name,
      barcode: form.value.barcode,
      sku: form.value.sku,
      hsn_code: form.value.hsn_code,
      category_id: form.value.category_id,
      category_name: form.value.category_name,
      unit_price: Number(form.value.unit_price),
      cost_price: Number(form.value.cost_price),
      tax_rate: Number(form.value.tax_rate),
      stock_quantity: Number(form.value.stock_quantity),
      is_active: 1,
      updated_at: new Date().toISOString()
    })
  }

  showProductModal.value = false
  await loadData()
}

async function deleteProduct(id: number) {
  if (confirm('Are you sure you want to delete this item?')) {
    await posDb.items.delete(id)
    await loadData()
  }
}

// Category Management Functions
const newCategoryForm = ref('')

async function addCategoryFromManager() {
  if (!newCategoryForm.value.trim()) return
  await createCustomCategory(newCategoryForm.value)
  newCategoryForm.value = ''
}

async function deleteCategory(cat: LocalCategory) {
  if (confirm(`Delete category "${cat.name}"?`)) {
    await posDb.categories.delete(cat.id)
    categories.value = await posDb.categories.toArray()
  }
}
</script>

<template>
  <div class="flex-1 flex flex-col p-6 bg-slate-900 text-slate-100 overflow-hidden">
    <!-- Header Controls -->
    <div class="flex items-center justify-between mb-5">
      <div class="flex items-center gap-3">
        <div class="p-2.5 bg-emerald-600/20 text-emerald-400 rounded-xl">
          <Package class="w-6 h-6" />
        </div>
        <div>
          <h1 class="text-xl font-bold text-slate-100">Item & Inventory Management</h1>
          <p class="text-xs text-slate-400">Total Items: {{ items.length }} | Custom Categories: {{ categories.length }}</p>
        </div>
      </div>

      <div class="flex items-center gap-3">
        <!-- Category Filter -->
        <select 
          v-model="selectedCategoryFilter"
          class="bg-slate-950 border border-slate-700 rounded-xl px-3 py-2 text-xs text-slate-200 focus:outline-none focus:border-emerald-500"
        >
          <option value="All">All Categories</option>
          <option v-for="c in categories" :key="c.id" :value="c.name">{{ c.name }}</option>
        </select>

        <div class="relative w-64">
          <Search class="absolute left-3.5 top-2.5 w-4 h-4 text-slate-400" />
          <input 
            v-model="search"
            type="text" 
            placeholder="Search barcode, name, HSN..."
            class="w-full bg-slate-950 border border-slate-700 rounded-xl pl-10 pr-3 py-2 text-xs text-slate-100 placeholder-slate-500 focus:outline-none focus:border-emerald-500 transition"
          />
        </div>

        <!-- Manage Categories Button -->
        <button 
          @click="showCategoryModal = true"
          class="bg-slate-800 hover:bg-slate-700 border border-slate-700 text-slate-200 font-semibold px-3.5 py-2 rounded-xl text-xs flex items-center gap-2 transition"
        >
          <Tags class="w-4 h-4 text-purple-400" />
          <span>Manage Categories</span>
        </button>

        <!-- New Product Button -->
        <button 
          @click="openAddProductModal"
          class="bg-emerald-600 hover:bg-emerald-500 text-white font-semibold px-4 py-2 rounded-xl text-xs flex items-center gap-2 shadow-lg transition"
        >
          <Plus class="w-4 h-4" />
          <span>New Product</span>
        </button>
      </div>
    </div>

    <!-- Data Table -->
    <div class="flex-1 overflow-auto border border-slate-800 rounded-xl bg-slate-950/60 shadow">
      <table class="w-full text-left text-xs">
        <thead class="bg-slate-950 border-b border-slate-800 text-slate-400 uppercase text-[10px] tracking-wider sticky top-0">
          <tr>
            <th class="py-3 px-4">Barcode / SKU</th>
            <th class="py-3 px-4">Item Name</th>
            <th class="py-3 px-3">Custom Category</th>
            <th class="py-3 px-3">HSN Code</th>
            <th class="py-3 px-3 text-right">Cost Price</th>
            <th class="py-3 px-3 text-right">Selling Price</th>
            <th class="py-3 px-3 text-right">GST %</th>
            <th class="py-3 px-3 text-center">Stock</th>
            <th class="py-3 px-4 text-center">Actions</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-slate-800/60">
          <tr v-for="item in filteredItems" :key="item.id" class="hover:bg-slate-800/40 transition">
            <td class="py-3 px-4 font-mono">
              <span class="text-emerald-400 font-semibold block">{{ item.barcode }}</span>
              <span class="text-[10px] text-slate-500">{{ item.sku }}</span>
            </td>
            <td class="py-3 px-4 font-medium text-slate-200">{{ item.name }}</td>
            <td class="py-3 px-3">
              <span class="bg-slate-800 text-slate-300 border border-slate-700 px-2 py-0.5 rounded text-[11px] font-medium">
                {{ item.category_name || 'Uncategorized' }}
              </span>
            </td>
            <td class="py-3 px-3 font-mono text-slate-300">{{ item.hsn_code }}</td>
            <td class="py-3 px-3 text-right font-mono text-slate-400">₹{{ item.cost_price.toFixed(2) }}</td>
            <td class="py-3 px-3 text-right font-mono font-bold text-emerald-400">₹{{ item.unit_price.toFixed(2) }}</td>
            <td class="py-3 px-3 text-right font-mono text-slate-300">{{ item.tax_rate }}%</td>
            <td class="py-3 px-3 text-center">
              <span 
                :class="item.stock_quantity > 15 ? 'bg-emerald-950/80 text-emerald-400 border-emerald-800' : 'bg-rose-950/80 text-rose-400 border-rose-800'"
                class="px-2 py-0.5 rounded-full border text-[11px] font-bold"
              >
                {{ item.stock_quantity }}
              </span>
            </td>
            <td class="py-3 px-4 text-center">
              <div class="flex items-center justify-center gap-2">
                <button @click="openEditProductModal(item)" class="p-1 hover:text-emerald-400 text-slate-400 transition" title="Edit Item">
                  <Edit2 class="w-3.5 h-3.5" />
                </button>
                <button @click="deleteProduct(item.id)" class="p-1 hover:text-rose-400 text-slate-400 transition" title="Delete Item">
                  <Trash2 class="w-3.5 h-3.5" />
                </button>
              </div>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- Product Modal with Dynamic Category Picker & Quick "+ New Category" -->
    <div v-if="showProductModal" class="fixed inset-0 bg-black/75 flex items-center justify-center z-50 p-4">
      <div class="bg-slate-900 border border-slate-700 rounded-2xl w-full max-w-lg p-5 shadow-2xl">
        <div class="flex items-center justify-between border-b border-slate-800 pb-3 mb-4">
          <h2 class="text-base font-bold text-slate-100">{{ isEditing ? 'Edit Product' : 'Add New Product' }}</h2>
          <button @click="showProductModal = false" class="text-slate-400 hover:text-white">
            <X class="w-5 h-5" />
          </button>
        </div>

        <div class="space-y-3 text-xs">
          <div>
            <label class="block text-slate-400 mb-1">Product Name</label>
            <input v-model="form.name" type="text" placeholder="e.g. Premium Cotton Shirt" class="w-full bg-slate-950 border border-slate-700 rounded-lg px-3 py-2 text-slate-100 focus:outline-none focus:border-emerald-500" />
          </div>

          <div class="grid grid-cols-2 gap-3">
            <div>
              <label class="block text-slate-400 mb-1">Barcode</label>
              <input v-model="form.barcode" type="text" class="w-full bg-slate-950 border border-slate-700 rounded-lg px-3 py-2 text-slate-100 font-mono" />
            </div>
            <div>
              <label class="block text-slate-400 mb-1">HSN / SAC Code</label>
              <input v-model="form.hsn_code" type="text" placeholder="e.g. 5208" class="w-full bg-slate-950 border border-slate-700 rounded-lg px-3 py-2 text-slate-100 font-mono" />
            </div>
          </div>

          <!-- Dynamic Category Selection / Instant Creation -->
          <div class="bg-slate-950/80 border border-slate-800 rounded-xl p-3">
            <div class="flex items-center justify-between mb-1.5">
              <label class="text-slate-300 font-semibold">Product Category</label>
              <button 
                type="button"
                @click="isQuickAddingCategory = !isQuickAddingCategory" 
                class="text-[11px] text-emerald-400 hover:text-emerald-300 font-semibold flex items-center gap-1"
              >
                <FolderPlus class="w-3.5 h-3.5" />
                <span>{{ isQuickAddingCategory ? 'Select Existing' : '+ Create New Category' }}</span>
              </button>
            </div>

            <!-- Existing Dynamic Category Dropdown -->
            <div v-if="!isQuickAddingCategory" class="flex gap-2">
              <select 
                v-model="form.category_name" 
                @change="form.category_id = categories.find(c => c.name === form.category_name)?.id || 0"
                class="w-full bg-slate-900 border border-slate-700 rounded-lg px-3 py-2 text-slate-100"
              >
                <option v-if="categories.length === 0" value="">-- No Categories Created Yet --</option>
                <option v-for="c in categories" :key="c.id" :value="c.name">{{ c.name }}</option>
              </select>
            </div>

            <!-- Quick Add New Custom Category on the fly -->
            <div v-else class="flex gap-2">
              <input 
                v-model="newCategoryInput"
                @keydown.enter.prevent="handleQuickAddCategory"
                type="text" 
                placeholder="Enter new category name..." 
                class="flex-1 bg-slate-900 border border-emerald-500 rounded-lg px-3 py-2 text-slate-100 text-xs focus:outline-none"
                autofocus
              />
              <button 
                type="button"
                @click="handleQuickAddCategory"
                class="bg-emerald-600 hover:bg-emerald-500 text-white font-bold px-3 py-2 rounded-lg text-xs"
              >
                Add & Use
              </button>
            </div>
          </div>

          <div class="grid grid-cols-3 gap-3">
            <div>
              <label class="block text-slate-400 mb-1">Cost Price (₹)</label>
              <input v-model.number="form.cost_price" type="number" step="0.01" class="w-full bg-slate-950 border border-slate-700 rounded-lg px-3 py-2 text-slate-100" />
            </div>
            <div>
              <label class="block text-slate-400 mb-1">Selling Price (₹)</label>
              <input v-model.number="form.unit_price" type="number" step="0.01" class="w-full bg-slate-950 border border-slate-700 rounded-lg px-3 py-2 text-slate-100 font-bold text-emerald-400" />
            </div>
            <div>
              <label class="block text-slate-400 mb-1">Stock Quantity</label>
              <input v-model.number="form.stock_quantity" type="number" class="w-full bg-slate-950 border border-slate-700 rounded-lg px-3 py-2 text-slate-100" />
            </div>
          </div>

          <div>
            <label class="block text-slate-400 mb-1">GST Tax Slab</label>
            <select v-model.number="form.tax_rate" class="w-full bg-slate-950 border border-slate-700 rounded-lg px-3 py-2 text-slate-100">
              <option v-for="t in taxSlabs" :key="t.id" :value="t.rate">{{ t.name }} ({{ t.rate }}%)</option>
            </select>
          </div>
        </div>

        <div class="mt-5 pt-3 border-t border-slate-800 flex justify-end gap-2 text-xs">
          <button @click="showProductModal = false" class="px-4 py-2 border border-slate-700 rounded-lg hover:bg-slate-800 text-slate-300">
            Cancel
          </button>
          <button @click="saveProduct" class="px-5 py-2 bg-emerald-600 hover:bg-emerald-500 text-white font-bold rounded-lg shadow">
            Save Product
          </button>
        </div>
      </div>
    </div>

    <!-- Category Manager Modal -->
    <div v-if="showCategoryModal" class="fixed inset-0 bg-black/75 flex items-center justify-center z-50 p-4">
      <div class="bg-slate-900 border border-slate-700 rounded-2xl w-full max-w-md p-5 shadow-2xl">
        <div class="flex items-center justify-between border-b border-slate-800 pb-3 mb-4">
          <div class="flex items-center gap-2">
            <Tags class="w-5 h-5 text-purple-400" />
            <h2 class="text-base font-bold text-slate-100">Your Custom Categories</h2>
          </div>
          <button @click="showCategoryModal = false" class="text-slate-400 hover:text-white">
            <X class="w-5 h-5" />
          </button>
        </div>

        <!-- Add Category Form -->
        <div class="flex gap-2 mb-4">
          <input 
            v-model="newCategoryForm" 
            @keydown.enter="addCategoryFromManager"
            type="text" 
            placeholder="Type new category name..." 
            class="flex-1 bg-slate-950 border border-slate-700 rounded-xl px-3 py-2 text-xs text-slate-100 focus:outline-none focus:border-purple-500" 
          />
          <button 
            @click="addCategoryFromManager"
            class="bg-purple-600 hover:bg-purple-500 text-white font-semibold px-4 py-2 rounded-xl text-xs flex items-center gap-1 shadow"
          >
            <Plus class="w-4 h-4" />
            <span>Add</span>
          </button>
        </div>

        <!-- Categories List -->
        <div class="max-h-60 overflow-y-auto divide-y divide-slate-800 border border-slate-800 rounded-xl bg-slate-950/60">
          <div v-if="categories.length === 0" class="py-8 text-center text-xs text-slate-500">
            No custom categories created yet.
          </div>
          <div 
            v-for="cat in categories" 
            :key="cat.id" 
            class="p-2.5 flex items-center justify-between text-xs hover:bg-slate-900 transition"
          >
            <span class="font-medium text-slate-200">{{ cat.name }}</span>
            <button @click="deleteCategory(cat)" class="text-slate-500 hover:text-rose-400 p-1">
              <Trash2 class="w-3.5 h-3.5" />
            </button>
          </div>
        </div>

        <div class="mt-4 pt-3 border-t border-slate-800 flex justify-end">
          <button @click="showCategoryModal = false" class="px-4 py-1.5 bg-slate-800 hover:bg-slate-700 rounded-lg text-xs font-semibold text-slate-300">
            Done
          </button>
        </div>
      </div>
    </div>
  </div>
</template>
