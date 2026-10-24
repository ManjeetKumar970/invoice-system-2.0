<script setup lang="ts">
import { ref, onMounted, onUnmounted, computed, nextTick } from 'vue'
import { usePosCartStore } from '../stores/posCartStore'
import { 
  posDb, 
  type LocalItem, 
  type LocalCustomer, 
  type LocalSaleRecord,
  type LocalSuspendedSale
} from '../db/posDatabase'
import QRCode from 'qrcode'
import { 
  Barcode, 
  ShoppingCart, 
  Trash2, 
  Search, 
  CreditCard, 
  Banknote, 
  QrCode, 
  Printer, 
  ArrowRight,
  AlertCircle,
  RotateCcw,
  UserPlus,
  PauseCircle,
  PlayCircle,
  History,
  Check,
  FileText
} from 'lucide-vue-next'

const cartStore = usePosCartStore()

// State variables
const barcodeInput = ref('')
const searchInput = ref('')
const selectedCategory = ref('All')
const itemsList = ref<LocalItem[]>([])
const customCategories = ref<string[]>([])
const customersList = ref<LocalCustomer[]>([])
const showCustomerDropdown = ref(false)

// Modals
const showCheckoutModal = ref(false)
const showReceiptModal = ref(false)
const showNewCustomerModal = ref(false)
const showHeldSalesModal = ref(false)
const showRecentSalesModal = ref(false)
const printFormat = ref<'thermal' | 'a4'>('thermal')
const autoPrint = ref(true)

// Data refs
const lastCompletedSale = ref<LocalSaleRecord | null>(null)
const checkoutError = ref('')
const heldSalesList = ref<LocalSuspendedSale[]>([])
const recentSalesList = ref<LocalSaleRecord[]>([])
const upiQrDataUrl = ref<string>('')

// Quick Add Customer Form
const newCustomerForm = ref({
  name: '',
  phone: '',
  gstin: '',
  state_code: '27' // Maharashtra default
})

// Barcode input ref for autofocus
const barcodeInputRef = ref<HTMLInputElement | null>(null)

const emit = defineEmits<{
  (e: 'sale-completed'): void
}>()

onMounted(async () => {
  await reloadCatalog()
  await reloadCustomers()
  await reloadHeldSales()
  window.addEventListener('keydown', handleGlobalKeydown)
  focusBarcode()
})

onUnmounted(() => {
  window.removeEventListener('keydown', handleGlobalKeydown)
})

function focusBarcode() {
  nextTick(() => {
    barcodeInputRef.value?.focus()
  })
}

function handleGlobalKeydown(e: KeyboardEvent) {
  if (e.key === 'F8') {
    e.preventDefault()
    if (cartStore.cartItems.length > 0 && !showCheckoutModal.value) {
      openCheckoutModal()
    }
  } else if (e.key === 'F2') {
    e.preventDefault()
    focusBarcode()
  } else if (e.key === 'Escape') {
    if (showReceiptModal.value) {
      showReceiptModal.value = false
    } else if (showCheckoutModal.value) {
      showCheckoutModal.value = false
    } else if (showNewCustomerModal.value) {
      showNewCustomerModal.value = false
    } else if (showHeldSalesModal.value) {
      showHeldSalesModal.value = false
    } else if (showRecentSalesModal.value) {
      showRecentSalesModal.value = false
    } else if (cartStore.cartItems.length > 0) {
      if (confirm('Clear active cart?')) {
        cartStore.clearCart()
      }
    }
  }
}

async function reloadCatalog() {
  itemsList.value = await posDb.items.toArray()
  const dbCats = await posDb.categories.toArray()
  customCategories.value = dbCats.map(c => c.name)
}

async function reloadCustomers() {
  customersList.value = await posDb.customers.toArray()
}

async function reloadHeldSales() {
  heldSalesList.value = await posDb.suspended_sales.toArray()
}

async function reloadRecentSales() {
  recentSalesList.value = await posDb.sales_queue.orderBy('created_at').reverse().limit(20).toArray()
}

const filteredItems = computed(() => {
  return itemsList.value.filter(item => {
    const matchesCat = selectedCategory.value === 'All' || item.category_name === selectedCategory.value
    const matchesSearch = !searchInput.value || 
      item.name.toLowerCase().includes(searchInput.value.toLowerCase()) ||
      item.barcode.includes(searchInput.value) ||
      item.sku.toLowerCase().includes(searchInput.value.toLowerCase())
    return matchesCat && matchesSearch
  })
})

const categories = computed(() => {
  const cats = new Set<string>(customCategories.value)
  itemsList.value.forEach(i => {
    if (i.category_name) cats.add(i.category_name)
  })
  return ['All', ...Array.from(cats)]
})

async function handleBarcodeSubmit() {
  if (!barcodeInput.value.trim()) return
  const success = await cartStore.scanBarcode(barcodeInput.value.trim())
  if (success) {
    barcodeInput.value = ''
    focusBarcode()
  } else {
    checkoutError.value = `Barcode "${barcodeInput.value}" not found in local catalog!`
    setTimeout(() => { checkoutError.value = '' }, 4000)
  }
}

// Quick Customer Select / Add
function selectCustomer(cust: LocalCustomer | null) {
  cartStore.setCustomer(cust)
  showCustomerDropdown.value = false
}

async function saveQuickCustomer() {
  if (!newCustomerForm.value.name.trim() || !newCustomerForm.value.phone.trim()) {
    alert('Customer Name and Phone are required')
    return
  }

  const maxId = (await posDb.customers.toCollection().last())?.id || 0
  const created: LocalCustomer = {
    id: maxId + 1,
    uuid: crypto.randomUUID(),
    name: newCustomerForm.value.name.trim(),
    phone: newCustomerForm.value.phone.trim(),
    gstin: newCustomerForm.value.gstin.trim() || undefined,
    state_code: newCustomerForm.value.state_code,
    loyalty_points: 0,
    updated_at: new Date().toISOString()
  }

  await posDb.customers.add(created)
  await reloadCustomers()
  selectCustomer(created)
  showNewCustomerModal.value = false
  newCustomerForm.value = { name: '', phone: '', gstin: '', state_code: '27' }
}

// Open Payment Checkout Modal
async function openCheckoutModal() {
  if (cartStore.cartItems.length === 0) {
    checkoutError.value = 'Please scan or add items before completing bill.'
    return
  }

  cartStore.amountTendered = cartStore.grandTotal
  await generateUpiQr()
  showCheckoutModal.value = true
}

async function generateUpiQr() {
  try {
    const upiString = `upi://pay?pa=${cartStore.storeConfig.upiId}&pn=${encodeURIComponent(cartStore.storeConfig.storeName)}&am=${cartStore.grandTotal.toFixed(2)}&cu=INR&tn=POSBill`
    upiQrDataUrl.value = await QRCode.toDataURL(upiString, { width: 220, margin: 1 })
  } catch (err) {
    console.error('QR code generation failed:', err)
  }
}

function setQuickCash(amount: number) {
  cartStore.amountTendered = amount
}

function addCash(amount: number) {
  cartStore.amountTendered = Number((Number(cartStore.amountTendered || 0) + amount).toFixed(2))
}

// Complete Billing Action
async function executeCheckout(shouldPrint = true) {
  checkoutError.value = ''
  try {
    const sale = await cartStore.completeSale()
    lastCompletedSale.value = sale
    showCheckoutModal.value = false
    await reloadCatalog()
    emit('sale-completed')

    if (shouldPrint) {
      showReceiptModal.value = true
      if (autoPrint.value) {
        nextTick(() => {
          setTimeout(() => {
            window.print()
          }, 300)
        })
      }
    } else {
      focusBarcode()
    }
  } catch (err: any) {
    console.error('Billing completion error:', err)
    checkoutError.value = `${err?.name || 'Error'}: ${err?.message || 'Failed to complete sale'}`
    alert(`Checkout error: ${err?.message || err}`)
  }
}

// Print Handler
function triggerPrint() {
  window.print()
}

function startNewSale() {
  showReceiptModal.value = false
  lastCompletedSale.value = null
  cartStore.clearCart()
  focusBarcode()
}

// Hold & Resume Cart
async function handleHoldCart() {
  try {
    await cartStore.holdCart()
    await reloadHeldSales()
    focusBarcode()
  } catch (err: any) {
    alert(err?.message || 'Failed to hold cart')
  }
}

async function handleResumeCart(uuid: string) {
  try {
    await cartStore.resumeCart(uuid)
    await reloadHeldSales()
    showHeldSalesModal.value = false
    focusBarcode()
  } catch (err: any) {
    alert(err?.message || 'Failed to resume cart')
  }
}

async function openRecentSalesModal() {
  await reloadRecentSales()
  showRecentSalesModal.value = true
}

function reprintPastSale(sale: LocalSaleRecord, format: 'thermal' | 'a4') {
  lastCompletedSale.value = sale
  printFormat.value = format
  showRecentSalesModal.value = false
  showReceiptModal.value = true
  nextTick(() => {
    setTimeout(() => {
      window.print()
    }, 200)
  })
}

// Convert amount to words for Indian GST invoice
function amountToWords(num: number): string {
  if (!num) return 'Zero Rupees Only'
  const ones = ['', 'One', 'Two', 'Three', 'Four', 'Five', 'Six', 'Seven', 'Eight', 'Nine', 'Ten', 
    'Eleven', 'Twelve', 'Thirteen', 'Fourteen', 'Fifteen', 'Sixteen', 'Seventeen', 'Eighteen', 'Nineteen']
  const tens = ['', '', 'Twenty', 'Thirty', 'Forty', 'Fifty', 'Sixty', 'Seventy', 'Eighty', 'Ninety']

  const numToWords = (n: number): string => {
    let str = ''
    if (n >= 10000000) {
      str += numToWords(Math.floor(n / 10000000)) + ' Crore '
      n %= 10000000
    }
    if (n >= 100000) {
      str += numToWords(Math.floor(n / 100000)) + ' Lakh '
      n %= 100000
    }
    if (n >= 1000) {
      str += numToWords(Math.floor(n / 1000)) + ' Thousand '
      n %= 1000
    }
    if (n >= 100) {
      str += numToWords(Math.floor(n / 100)) + ' Hundred '
      n %= 100
    }
    if (n > 0) {
      if (n < 20) {
        str += ones[n] + ' '
      } else {
        str += tens[Math.floor(n / 10)] + (n % 10 > 0 ? ' ' + ones[n % 10] : '') + ' '
      }
    }
    return str
  }

  const integerPart = Math.floor(num)
  const paisePart = Math.round((num - integerPart) * 100)
  let result = numToWords(integerPart).trim() + ' Rupees'
  if (paisePart > 0) {
    result += ' and ' + numToWords(paisePart).trim() + ' Paise'
  }
  return result + ' Only'
}

defineExpose({ reloadCatalog })
</script>

<template>
  <div class="flex-1 flex overflow-hidden relative">
    <!-- LEFT PANEL: Touch Product Grid & Barcode Scanner -->
    <section class="w-7/12 flex flex-col border-r border-slate-800 p-3 overflow-hidden no-print">
      <!-- Error Notification Banner -->
      <div v-if="checkoutError" class="mb-2 bg-rose-950 border border-rose-800 text-rose-300 px-3.5 py-2 rounded-xl text-xs flex items-center justify-between shadow-lg">
        <div class="flex items-center gap-2">
          <AlertCircle class="w-4 h-4 text-rose-400 shrink-0" />
          <span>{{ checkoutError }}</span>
        </div>
        <button @click="checkoutError = ''" class="text-rose-400 hover:text-white">&times;</button>
      </div>

      <!-- Top Scanner & Search Bar -->
      <div class="flex gap-2 mb-2.5">
        <div class="relative flex-1">
          <Barcode class="absolute left-3.5 top-3 w-5 h-5 text-emerald-400" />
          <input 
            ref="barcodeInputRef"
            v-model="barcodeInput"
            @keydown.enter="handleBarcodeSubmit"
            type="text" 
            placeholder="Scan Barcode (F2) & Press Enter..."
            class="w-full bg-slate-950 border border-slate-700 rounded-xl pl-11 pr-4 py-2.5 text-sm text-slate-100 placeholder-slate-500 focus:outline-none focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500 transition shadow-inner font-mono"
            autofocus
          />
        </div>

        <div class="relative w-64">
          <Search class="absolute left-3.5 top-3 w-4 h-4 text-slate-400" />
          <input 
            v-model="searchInput"
            type="text" 
            placeholder="Search catalog / SKU..."
            class="w-full bg-slate-950 border border-slate-700 rounded-xl pl-10 pr-3 py-2.5 text-sm text-slate-100 placeholder-slate-500 focus:outline-none focus:border-emerald-500 transition shadow-inner"
          />
        </div>
      </div>

      <!-- Category Filter Tabs -->
      <div class="flex gap-1.5 pb-2 overflow-x-auto scrollbar-none border-b border-slate-800 text-xs">
        <button 
          v-for="cat in categories" 
          :key="cat"
          @click="selectedCategory = cat"
          :class="selectedCategory === cat ? 'bg-emerald-600 text-white shadow' : 'bg-slate-800 text-slate-300 hover:bg-slate-700'"
          class="px-3 py-1.5 rounded-lg whitespace-nowrap font-medium transition"
        >
          {{ cat }}
        </button>
      </div>

      <!-- Touch Product Grid Cards -->
      <div class="flex-1 overflow-y-auto grid grid-cols-3 gap-2.5 pt-2.5 pr-1">
        <div 
          v-for="item in filteredItems" 
          :key="item.id"
          @click="cartStore.addItem(item, 1)"
          class="bg-slate-950/80 hover:bg-slate-800/90 border border-slate-800 hover:border-emerald-500/70 rounded-xl p-2.5 flex flex-col justify-between cursor-pointer transition transform active:scale-98 group shadow"
        >
          <div>
            <div class="flex items-center justify-between text-[11px] text-slate-400 mb-1">
              <span class="font-mono text-emerald-400">{{ item.barcode }}</span>
              <span class="bg-slate-800/80 px-1.5 py-0.5 rounded text-[10px]">HSN: {{ item.hsn_code }}</span>
            </div>
            <h3 class="text-xs font-semibold text-slate-200 line-clamp-2 group-hover:text-white leading-tight">
              {{ item.name }}
            </h3>
          </div>

          <div class="mt-2.5 flex items-end justify-between">
            <div>
              <span class="text-[10px] text-slate-500 block">GST: {{ item.tax_rate }}%</span>
              <span class="text-sm font-bold text-emerald-400">₹{{ item.unit_price.toFixed(2) }}</span>
            </div>
            <span 
              :class="item.stock_quantity > 10 ? 'text-slate-400' : 'text-rose-400 font-bold'"
              class="text-[10px] bg-slate-900 px-2 py-0.5 rounded border border-slate-800"
            >
              Qty: {{ item.stock_quantity }}
            </span>
          </div>
        </div>
      </div>
    </section>

    <!-- RIGHT PANEL: POS Active Cart & Fast Billing Engine -->
    <section class="w-5/12 flex flex-col bg-slate-950 p-3 no-print">
      <!-- Customer Bar & GST Tax Engine Mode -->
      <div class="bg-slate-900 border border-slate-800 rounded-xl p-2.5 mb-2 relative">
        <div class="flex items-center justify-between">
          <div class="flex items-center gap-2">
            <button 
              @click="showCustomerDropdown = !showCustomerDropdown"
              class="w-7 h-7 rounded-lg bg-emerald-600/20 text-emerald-400 flex items-center justify-center font-bold text-xs hover:bg-emerald-600/30 transition"
              title="Switch Customer"
            >
              C
            </button>
            <div>
              <div class="flex items-center gap-1.5">
                <span class="font-bold text-xs text-slate-200">{{ cartStore.selectedCustomer?.name || 'Walk-in Customer' }}</span>
                <span v-if="cartStore.selectedCustomer?.phone" class="text-[10px] text-slate-400">({{ cartStore.selectedCustomer.phone }})</span>
              </div>
              <p class="text-[10px] text-slate-400">
                State: {{ cartStore.selectedCustomer?.state_code || '27 (MH)' }} 
                <span v-if="cartStore.selectedCustomer?.gstin">| GSTIN: {{ cartStore.selectedCustomer.gstin }}</span>
              </p>
            </div>
          </div>

          <div class="flex items-center gap-1.5">
            <button 
              @click="showNewCustomerModal = true"
              class="px-2 py-1 bg-slate-800 hover:bg-slate-700 text-slate-300 rounded-lg text-[10px] font-semibold flex items-center gap-1 transition"
              title="Add Customer"
            >
              <UserPlus class="w-3 h-3 text-emerald-400" />
              <span>+ Add</span>
            </button>
            <span class="bg-emerald-950 text-emerald-400 border border-emerald-800 px-2 py-0.5 rounded-full text-[10px] font-semibold">
              {{ cartStore.isInterState ? 'IGST' : 'CGST+SGST' }}
            </span>
          </div>
        </div>

        <!-- Customer Dropdown Selector -->
        <div v-if="showCustomerDropdown" class="absolute top-12 left-0 right-0 bg-slate-900 border border-slate-700 rounded-xl shadow-2xl p-2 z-30">
          <div class="flex justify-between items-center pb-1.5 border-b border-slate-800 mb-1.5 text-xs text-slate-300">
            <span class="font-bold">Select Customer</span>
            <button @click="showCustomerDropdown = false" class="text-slate-500 hover:text-white">&times;</button>
          </div>
          <div class="max-h-48 overflow-y-auto space-y-1 text-xs">
            <div 
              @click="selectCustomer(null)"
              class="p-2 rounded-lg hover:bg-slate-800 cursor-pointer flex justify-between items-center text-slate-300"
            >
              <div>
                <p class="font-bold">Walk-in Customer</p>
                <p class="text-[10px] text-slate-500">Retail Direct (MH 27)</p>
              </div>
              <Check v-if="!cartStore.selectedCustomer" class="w-4 h-4 text-emerald-400" />
            </div>

            <div 
              v-for="cust in customersList" 
              :key="cust.id"
              @click="selectCustomer(cust)"
              class="p-2 rounded-lg hover:bg-slate-800 cursor-pointer flex justify-between items-center text-slate-300"
            >
              <div>
                <p class="font-bold text-slate-200">{{ cust.name }}</p>
                <p class="text-[10px] text-slate-500">{{ cust.phone }} | State: {{ cust.state_code }} | {{ cust.gstin || 'Unregistered' }}</p>
              </div>
              <Check v-if="cartStore.selectedCustomer?.id === cust.id" class="w-4 h-4 text-emerald-400" />
            </div>
          </div>
        </div>
      </div>

      <!-- Cart Line Items Table -->
      <div class="flex-1 overflow-y-auto border border-slate-800 rounded-xl mb-2 bg-slate-900/40">
        <table class="w-full text-left text-xs">
          <thead class="bg-slate-900 border-b border-slate-800 text-slate-400 uppercase text-[10px] tracking-wider sticky top-0">
            <tr>
              <th class="py-2 px-2.5">Item / HSN</th>
              <th class="py-2 px-1 text-center">Qty</th>
              <th class="py-2 px-1.5 text-right">Price</th>
              <th class="py-2 px-1.5 text-right">Tax</th>
              <th class="py-2 px-2.5 text-right">Total</th>
              <th class="py-2 px-1"></th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-800/60">
            <tr v-if="cartStore.cartItems.length === 0">
              <td colspan="6" class="py-12 text-center text-slate-500">
                <ShoppingCart class="w-8 h-8 mx-auto mb-2 opacity-30" />
                Cart is empty. Scan barcodes (F2) or click items to begin.
              </td>
            </tr>
            <tr v-for="(item, idx) in cartStore.cartItems" :key="item.item_id" class="hover:bg-slate-800/40 transition">
              <td class="py-2 px-2.5">
                <p class="font-semibold text-slate-200 truncate max-w-[130px] leading-tight">{{ item.name }}</p>
                <p class="text-[9px] text-slate-400 font-mono">HSN: {{ item.hsn_code }} ({{ item.tax_rate }}%)</p>
              </td>
              <td class="py-2 px-1">
                <div class="flex items-center justify-center space-x-0.5">
                  <button 
                    @click="cartStore.updateQty(idx, item.quantity - 1)" 
                    class="w-5 h-5 bg-slate-800 hover:bg-slate-700 rounded flex items-center justify-center text-slate-300 font-bold"
                  >-</button>
                  <span class="w-6 text-center font-bold text-slate-100 text-xs">{{ item.quantity }}</span>
                  <button 
                    @click="cartStore.updateQty(idx, item.quantity + 1)" 
                    class="w-5 h-5 bg-slate-800 hover:bg-slate-700 rounded flex items-center justify-center text-slate-300 font-bold"
                  >+</button>
                </div>
              </td>
              <td class="py-2 px-1.5 text-right font-mono text-slate-300">₹{{ item.unit_price.toFixed(2) }}</td>
              <td class="py-2 px-1.5 text-right font-mono text-slate-400">₹{{ item.total_tax.toFixed(2) }}</td>
              <td class="py-2 px-2.5 text-right font-mono font-bold text-emerald-400">₹{{ item.line_total.toFixed(2) }}</td>
              <td class="py-2 px-1 text-center">
                <button @click="cartStore.removeItem(idx)" class="text-slate-500 hover:text-rose-400 p-1">
                  <Trash2 class="w-3.5 h-3.5" />
                </button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Bill Financial Summary -->
      <div class="bg-slate-900 border border-slate-800 rounded-xl p-2.5 mb-2 text-xs space-y-1">
        <div class="flex justify-between text-slate-400">
          <span>Taxable Subtotal:</span>
          <span class="font-mono text-slate-200">₹{{ cartStore.subtotal.toFixed(2) }}</span>
        </div>

        <div v-if="!cartStore.isInterState" class="flex justify-between text-slate-400 text-[11px]">
          <span>CGST (Intra-state 50%):</span>
          <span class="font-mono text-slate-300">₹{{ cartStore.cgstTotal.toFixed(2) }}</span>
        </div>
        <div v-if="!cartStore.isInterState" class="flex justify-between text-slate-400 text-[11px]">
          <span>SGST (Intra-state 50%):</span>
          <span class="font-mono text-slate-300">₹{{ cartStore.sgstTotal.toFixed(2) }}</span>
        </div>
        <div v-if="cartStore.isInterState" class="flex justify-between text-slate-400 text-[11px]">
          <span>IGST (Inter-state 100%):</span>
          <span class="font-mono text-slate-300">₹{{ cartStore.igstTotal.toFixed(2) }}</span>
        </div>

        <div class="flex justify-between text-slate-400 text-[11px]">
          <span>Round Off:</span>
          <span class="font-mono text-slate-300">₹{{ cartStore.roundOff.toFixed(2) }}</span>
        </div>

        <div class="border-t border-slate-800 pt-1.5 flex justify-between items-center text-sm font-bold">
          <span class="text-slate-100">GRAND TOTAL:</span>
          <span class="text-xl font-mono text-emerald-400">₹{{ cartStore.grandTotal.toFixed(2) }}</span>
        </div>
      </div>

      <!-- Register Utility Toolbar: Hold, Resume, Recent Bills, Clear -->
      <div class="flex items-center gap-1.5 mb-2 text-xs">
        <button 
          @click="cartStore.clearCart"
          :disabled="cartStore.cartItems.length === 0"
          class="p-2 rounded-xl bg-slate-900 hover:bg-slate-800 border border-slate-800 text-slate-400 hover:text-rose-400 disabled:opacity-40 transition"
          title="Clear Cart (Esc)"
        >
          <RotateCcw class="w-4 h-4" />
        </button>

        <button 
          @click="handleHoldCart"
          :disabled="cartStore.cartItems.length === 0"
          class="flex-1 py-1.5 px-2 bg-slate-900 hover:bg-slate-800 border border-slate-800 text-slate-300 hover:text-amber-400 rounded-xl font-semibold flex items-center justify-center gap-1 disabled:opacity-40 transition"
          title="Hold Cart"
        >
          <PauseCircle class="w-4 h-4 text-amber-400" />
          <span>Hold Cart</span>
        </button>

        <button 
          @click="showHeldSalesModal = true"
          class="relative py-1.5 px-2.5 bg-slate-900 hover:bg-slate-800 border border-slate-800 text-slate-300 rounded-xl font-semibold flex items-center gap-1 transition"
          title="Recall Held Carts"
        >
          <PlayCircle class="w-4 h-4 text-blue-400" />
          <span>Held ({{ heldSalesList.length }})</span>
        </button>

        <button 
          @click="openRecentSalesModal"
          class="py-1.5 px-2.5 bg-slate-900 hover:bg-slate-800 border border-slate-800 text-slate-300 rounded-xl font-semibold flex items-center gap-1 transition"
          title="Recent Invoices"
        >
          <History class="w-4 h-4 text-emerald-400" />
          <span>Bills</span>
        </button>
      </div>

      <!-- MAIN ACTION BUTTON: COMPLETE BILLING & PRINT (F8) -->
      <button 
        @click="openCheckoutModal"
        :disabled="cartStore.cartItems.length === 0"
        class="w-full bg-emerald-600 hover:bg-emerald-500 active:bg-emerald-700 disabled:opacity-40 text-white font-extrabold py-3 px-4 rounded-xl shadow-lg flex items-center justify-center gap-2 text-sm transition"
      >
        <span>Pay & Complete Bill (F8)</span>
        <ArrowRight class="w-5 h-5" />
      </button>
    </section>

    <!-- CHECKOUT & PAYMENT MODAL -->
    <div v-if="showCheckoutModal" class="fixed inset-0 bg-black/80 flex items-center justify-center z-50 p-4 no-print">
      <div class="bg-slate-900 border border-slate-700 text-slate-100 w-full max-w-xl rounded-2xl p-5 shadow-2xl flex flex-col max-h-[92vh] overflow-y-auto">
        <!-- Modal Header -->
        <div class="flex items-center justify-between pb-3 border-b border-slate-800">
          <div>
            <h2 class="text-base font-bold text-white flex items-center gap-2">
              <Banknote class="w-5 h-5 text-emerald-400" />
              <span>Complete Payment & Checkout</span>
            </h2>
            <p class="text-xs text-slate-400">Customer: <strong class="text-slate-200">{{ cartStore.selectedCustomer?.name || 'Walk-in Customer' }}</strong></p>
          </div>
          <button @click="showCheckoutModal = false" class="text-slate-400 hover:text-white text-lg">&times;</button>
        </div>

        <!-- Grand Total Banner -->
        <div class="my-3 bg-gradient-to-r from-emerald-950 to-slate-900 border border-emerald-800/80 rounded-xl p-3.5 flex items-center justify-between">
          <div>
            <span class="text-xs text-emerald-400 uppercase tracking-wider font-semibold">Total Payable</span>
            <p class="text-2xl font-mono font-extrabold text-white">₹{{ cartStore.grandTotal.toFixed(2) }}</p>
          </div>
          <div class="text-right text-xs text-slate-400">
            <p>{{ cartStore.cartItems.length }} Line Items</p>
            <p class="text-emerald-400 font-medium">{{ cartStore.isInterState ? 'IGST Invoice' : 'CGST+SGST Invoice' }}</p>
          </div>
        </div>

        <!-- Payment Mode Tabs -->
        <div class="grid grid-cols-4 gap-2 mb-3">
          <button 
            @click="cartStore.paymentType = 'cash'"
            :class="cartStore.paymentType === 'cash' ? 'bg-emerald-600 text-white border-emerald-500 shadow' : 'bg-slate-800 text-slate-300 border-slate-700 hover:bg-slate-750'"
            class="py-2.5 rounded-xl border flex flex-col items-center justify-center gap-1 font-semibold text-xs transition"
          >
            <Banknote class="w-4 h-4" />
            <span>Cash</span>
          </button>

          <button 
            @click="cartStore.paymentType = 'upi'; generateUpiQr()"
            :class="cartStore.paymentType === 'upi' ? 'bg-emerald-600 text-white border-emerald-500 shadow' : 'bg-slate-800 text-slate-300 border-slate-700 hover:bg-slate-750'"
            class="py-2.5 rounded-xl border flex flex-col items-center justify-center gap-1 font-semibold text-xs transition"
          >
            <QrCode class="w-4 h-4" />
            <span>UPI / QR</span>
          </button>

          <button 
            @click="cartStore.paymentType = 'card'"
            :class="cartStore.paymentType === 'card' ? 'bg-emerald-600 text-white border-emerald-500 shadow' : 'bg-slate-800 text-slate-300 border-slate-700 hover:bg-slate-750'"
            class="py-2.5 rounded-xl border flex flex-col items-center justify-center gap-1 font-semibold text-xs transition"
          >
            <CreditCard class="w-4 h-4" />
            <span>Card</span>
          </button>

          <button 
            @click="cartStore.paymentType = 'credit'"
            :class="cartStore.paymentType === 'credit' ? 'bg-emerald-600 text-white border-emerald-500 shadow' : 'bg-slate-800 text-slate-300 border-slate-700 hover:bg-slate-750'"
            class="py-2.5 rounded-xl border flex flex-col items-center justify-center gap-1 font-semibold text-xs transition"
          >
            <FileText class="w-4 h-4" />
            <span>Credit / Due</span>
          </button>
        </div>

        <!-- CASH PAYMENT TAB CONTENT -->
        <div v-if="cartStore.paymentType === 'cash'" class="space-y-3 bg-slate-950/70 p-3.5 rounded-xl border border-slate-800">
          <div>
            <label class="block text-xs font-semibold text-slate-300 mb-1">Cash Tendered by Customer (₹)</label>
            <input 
              v-model.number="cartStore.amountTendered"
              type="number"
              step="any"
              class="w-full bg-slate-900 border border-slate-700 rounded-xl px-3 py-2 text-lg font-mono font-bold text-white focus:outline-none focus:border-emerald-500"
              autofocus
            />
          </div>

          <!-- Quick Cash Chips -->
          <div>
            <span class="text-[11px] text-slate-400 block mb-1.5">Quick Cash Tender Buttons:</span>
            <div class="flex flex-wrap gap-1.5">
              <button @click="setQuickCash(cartStore.grandTotal)" class="px-2.5 py-1 bg-slate-800 hover:bg-slate-700 text-slate-200 text-xs rounded-lg font-mono font-semibold">Exact ₹{{ cartStore.grandTotal }}</button>
              <button @click="setQuickCash(Math.ceil(cartStore.grandTotal / 50) * 50)" class="px-2.5 py-1 bg-slate-800 hover:bg-slate-700 text-slate-200 text-xs rounded-lg font-mono font-semibold">Round ₹{{ Math.ceil(cartStore.grandTotal / 50) * 50 }}</button>
              <button @click="setQuickCash(500)" class="px-2.5 py-1 bg-slate-800 hover:bg-slate-700 text-slate-200 text-xs rounded-lg font-mono font-semibold">₹500</button>
              <button @click="setQuickCash(1000)" class="px-2.5 py-1 bg-slate-800 hover:bg-slate-700 text-slate-200 text-xs rounded-lg font-mono font-semibold">₹1000</button>
              <button @click="setQuickCash(2000)" class="px-2.5 py-1 bg-slate-800 hover:bg-slate-700 text-slate-200 text-xs rounded-lg font-mono font-semibold">₹2000</button>
              <button @click="addCash(100)" class="px-2 py-1 bg-emerald-900/60 hover:bg-emerald-900 text-emerald-300 text-xs rounded-lg font-mono font-semibold">+₹100</button>
              <button @click="addCash(500)" class="px-2 py-1 bg-emerald-900/60 hover:bg-emerald-900 text-emerald-300 text-xs rounded-lg font-mono font-semibold">+₹500</button>
            </div>
          </div>

          <!-- Return Change Due Banner -->
          <div class="bg-slate-900 border border-slate-800 p-2.5 rounded-xl flex items-center justify-between">
            <span class="text-xs font-semibold text-slate-300">Change Due (Return to Customer):</span>
            <span :class="cartStore.changeDue > 0 ? 'text-emerald-400 font-extrabold text-lg' : 'text-slate-400 font-bold text-base'" class="font-mono">
              ₹{{ cartStore.changeDue.toFixed(2) }}
            </span>
          </div>
        </div>

        <!-- UPI PAYMENT TAB CONTENT -->
        <div v-if="cartStore.paymentType === 'upi'" class="space-y-3 bg-slate-950/70 p-3.5 rounded-xl border border-slate-800 text-center">
          <p class="text-xs text-slate-300 font-medium">Scan QR Code using Google Pay, PhonePe, Paytm, or BHIM</p>
          <div class="w-48 h-48 mx-auto bg-white p-2 rounded-xl shadow-lg flex items-center justify-center">
            <img v-if="upiQrDataUrl" :src="upiQrDataUrl" alt="UPI QR Code" class="w-full h-full" />
          </div>
          <p class="text-xs font-mono text-emerald-400">Payee: {{ cartStore.storeConfig.upiId }}</p>
          <div>
            <input 
              v-model="cartStore.paymentReference" 
              type="text" 
              placeholder="Enter UPI UTR / Transaction ID (Optional)..." 
              class="w-full bg-slate-900 border border-slate-700 rounded-xl px-3 py-2 text-xs text-slate-200"
            />
          </div>
        </div>

        <!-- CARD PAYMENT TAB CONTENT -->
        <div v-if="cartStore.paymentType === 'card'" class="space-y-3 bg-slate-950/70 p-3.5 rounded-xl border border-slate-800">
          <div>
            <label class="block text-xs font-semibold text-slate-300 mb-1">Card Approval / Auth Code</label>
            <input 
              v-model="cartStore.paymentReference" 
              type="text" 
              placeholder="e.g. AUTH-849202 (Optional)" 
              class="w-full bg-slate-900 border border-slate-700 rounded-xl px-3 py-2 text-xs text-slate-200"
            />
          </div>
          <p class="text-[11px] text-slate-400">Swipe or tap customer card on EDC terminal and confirm authorization.</p>
        </div>

        <!-- CREDIT PAYMENT TAB CONTENT -->
        <div v-if="cartStore.paymentType === 'credit'" class="space-y-3 bg-slate-950/70 p-3.5 rounded-xl border border-slate-800">
          <p class="text-xs text-amber-300">Billing to Customer Ledger (Khata / Credit Account)</p>
          <input 
            v-model="cartStore.invoiceNotes" 
            type="text" 
            placeholder="Credit invoice remarks or due date..." 
            class="w-full bg-slate-900 border border-slate-700 rounded-xl px-3 py-2 text-xs text-slate-200"
          />
        </div>

        <!-- Print Format Toggle & Actions -->
        <div class="mt-4 pt-3 border-t border-slate-800 flex items-center justify-between gap-2">
          <div class="flex items-center gap-3 text-xs">
            <label class="flex items-center gap-1.5 cursor-pointer text-slate-300">
              <input type="checkbox" v-model="autoPrint" class="rounded border-slate-700 text-emerald-600 focus:ring-0" />
              <span>Auto-print receipt</span>
            </label>
            <div class="flex bg-slate-800 p-0.5 rounded-lg border border-slate-700 text-[11px]">
              <button 
                @click="printFormat = 'thermal'"
                :class="printFormat === 'thermal' ? 'bg-emerald-600 text-white' : 'text-slate-400'"
                class="px-2 py-0.5 rounded font-medium transition"
              >80mm Slip</button>
              <button 
                @click="printFormat = 'a4'"
                :class="printFormat === 'a4' ? 'bg-emerald-600 text-white' : 'text-slate-400'"
                class="px-2 py-0.5 rounded font-medium transition"
              >A4 Tax Invoice</button>
            </div>
          </div>

          <div class="flex gap-2">
            <button 
              @click="executeCheckout(false)"
              class="px-3.5 py-2.5 bg-slate-800 hover:bg-slate-700 text-slate-300 rounded-xl text-xs font-bold transition"
            >
              Save Only
            </button>
            
            <button 
              @click="executeCheckout(true)"
              class="px-5 py-2.5 bg-emerald-600 hover:bg-emerald-500 text-white rounded-xl text-xs font-extrabold flex items-center gap-1.5 shadow-lg transition"
            >
              <Printer class="w-4 h-4" />
              <span>Complete & Print Bill (Enter)</span>
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- QUICK ADD CUSTOMER MODAL -->
    <div v-if="showNewCustomerModal" class="fixed inset-0 bg-black/80 flex items-center justify-center z-50 p-4 no-print">
      <div class="bg-slate-900 border border-slate-700 text-slate-100 w-full max-w-md rounded-2xl p-5 shadow-2xl">
        <div class="flex items-center justify-between pb-3 border-b border-slate-800 mb-3">
          <h2 class="text-sm font-bold text-white flex items-center gap-2">
            <UserPlus class="w-4 h-4 text-emerald-400" />
            <span>Add Customer to Directory</span>
          </h2>
          <button @click="showNewCustomerModal = false" class="text-slate-400 hover:text-white">&times;</button>
        </div>

        <div class="space-y-3 text-xs">
          <div>
            <label class="block font-semibold text-slate-300 mb-1">Customer Name *</label>
            <input v-model="newCustomerForm.name" type="text" placeholder="e.g. Amit Patel" class="w-full bg-slate-950 border border-slate-700 rounded-xl px-3 py-2 text-white" />
          </div>
          <div>
            <label class="block font-semibold text-slate-300 mb-1">Mobile Number *</label>
            <input v-model="newCustomerForm.phone" type="text" placeholder="e.g. 9820098200" class="w-full bg-slate-950 border border-slate-700 rounded-xl px-3 py-2 text-white" />
          </div>
          <div>
            <label class="block font-semibold text-slate-300 mb-1">GSTIN (Optional for B2B)</label>
            <input v-model="newCustomerForm.gstin" type="text" placeholder="e.g. 27ABCDE1234F1Z5" class="w-full bg-slate-950 border border-slate-700 rounded-xl px-3 py-2 text-white uppercase font-mono" />
          </div>
          <div>
            <label class="block font-semibold text-slate-300 mb-1">State Code (27 = MH, 24 = GJ, 06 = HR)</label>
            <input v-model="newCustomerForm.state_code" type="text" placeholder="27" class="w-full bg-slate-950 border border-slate-700 rounded-xl px-3 py-2 text-white font-mono" />
          </div>

          <div class="pt-3 border-t border-slate-800 flex justify-end gap-2">
            <button @click="showNewCustomerModal = false" class="px-4 py-2 bg-slate-800 text-slate-300 rounded-xl font-bold">Cancel</button>
            <button @click="saveQuickCustomer" class="px-5 py-2 bg-emerald-600 hover:bg-emerald-500 text-white rounded-xl font-bold">Save & Select</button>
          </div>
        </div>
      </div>
    </div>

    <!-- HELD SALES (SUSPENDED CARTS) MODAL -->
    <div v-if="showHeldSalesModal" class="fixed inset-0 bg-black/80 flex items-center justify-center z-50 p-4 no-print">
      <div class="bg-slate-900 border border-slate-700 text-slate-100 w-full max-w-lg rounded-2xl p-5 shadow-2xl">
        <div class="flex items-center justify-between pb-3 border-b border-slate-800 mb-3">
          <h2 class="text-sm font-bold text-white flex items-center gap-2">
            <PauseCircle class="w-4 h-4 text-amber-400" />
            <span>Held Carts (Parked Sales)</span>
          </h2>
          <button @click="showHeldSalesModal = false" class="text-slate-400 hover:text-white">&times;</button>
        </div>

        <div class="max-h-72 overflow-y-auto space-y-2">
          <p v-if="heldSalesList.length === 0" class="text-xs text-slate-500 text-center py-6">No held carts currently.</p>
          <div 
            v-for="held in heldSalesList" 
            :key="held.uuid" 
            class="bg-slate-950 border border-slate-800 p-3 rounded-xl flex items-center justify-between text-xs"
          >
            <div>
              <p class="font-bold text-slate-200">{{ held.title }}</p>
              <p class="text-[10px] text-slate-400">{{ held.items.length }} Items | {{ new Date(held.created_at).toLocaleTimeString() }}</p>
              <p class="font-mono text-emerald-400 font-bold">₹{{ held.grand_total.toFixed(2) }}</p>
            </div>
            <button 
              @click="handleResumeCart(held.uuid)" 
              class="px-3.5 py-1.5 bg-blue-600 hover:bg-blue-500 text-white font-bold rounded-lg text-xs"
            >
              Resume Cart
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- RECENT INVOICES (REPRINT) MODAL -->
    <div v-if="showRecentSalesModal" class="fixed inset-0 bg-black/80 flex items-center justify-center z-50 p-4 no-print">
      <div class="bg-slate-900 border border-slate-700 text-slate-100 w-full max-w-2xl rounded-2xl p-5 shadow-2xl">
        <div class="flex items-center justify-between pb-3 border-b border-slate-800 mb-3">
          <h2 class="text-sm font-bold text-white flex items-center gap-2">
            <History class="w-4 h-4 text-emerald-400" />
            <span>Recent Completed Bills</span>
          </h2>
          <button @click="showRecentSalesModal = false" class="text-slate-400 hover:text-white">&times;</button>
        </div>

        <div class="max-h-80 overflow-y-auto space-y-2">
          <p v-if="recentSalesList.length === 0" class="text-xs text-slate-500 text-center py-6">No sales recorded yet.</p>
          <div 
            v-for="sale in recentSalesList" 
            :key="sale.uuid" 
            class="bg-slate-950 border border-slate-800 p-3 rounded-xl flex items-center justify-between text-xs"
          >
            <div>
              <div class="flex items-center gap-2">
                <span class="font-mono font-bold text-white">{{ sale.invoice_number }}</span>
                <span :class="sale.sync_status === 'synced' ? 'bg-emerald-950 text-emerald-400 border-emerald-800' : 'bg-amber-950 text-amber-400 border-amber-800'" class="text-[10px] px-2 py-0.5 rounded-full border">
                  {{ sale.sync_status }}
                </span>
              </div>
              <p class="text-slate-400 text-[11px]">{{ sale.customer_name }} | {{ new Date(sale.created_at).toLocaleString() }}</p>
              <p class="font-mono font-bold text-emerald-400">₹{{ sale.grand_total.toFixed(2) }} ({{ sale.payments[0]?.payment_type || 'cash' }})</p>
            </div>
            <div class="flex gap-1.5">
              <button @click="reprintPastSale(sale, 'thermal')" class="px-2.5 py-1.5 bg-slate-800 hover:bg-slate-700 text-slate-200 rounded-lg text-xs font-semibold flex items-center gap-1">
                <Printer class="w-3.5 h-3.5" />
                <span>80mm</span>
              </button>
              <button @click="reprintPastSale(sale, 'a4')" class="px-2.5 py-1.5 bg-emerald-700 hover:bg-emerald-600 text-white rounded-lg text-xs font-semibold flex items-center gap-1">
                <FileText class="w-3.5 h-3.5" />
                <span>A4 Tax Bill</span>
              </button>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- RECEIPT PREVIEW & PRINT DIALOG MODAL -->
    <div v-if="showReceiptModal && lastCompletedSale" class="fixed inset-0 bg-black/85 flex items-center justify-center z-50 p-4 no-print">
      <div class="bg-white text-slate-900 rounded-2xl shadow-2xl flex flex-col max-h-[94vh] overflow-hidden" :class="printFormat === 'thermal' ? 'w-96' : 'w-[820px]'">
        <!-- Modal Top Bar -->
        <div class="bg-slate-100 border-b border-gray-200 px-4 py-2.5 flex items-center justify-between">
          <div class="flex items-center gap-2">
            <span class="w-6 h-6 rounded-full bg-emerald-600 text-white flex items-center justify-center font-bold text-xs">✓</span>
            <span class="font-bold text-xs text-gray-800">Bill Complete: {{ lastCompletedSale.invoice_number }}</span>
          </div>

          <!-- Format Switcher -->
          <div class="flex bg-gray-200 p-0.5 rounded-lg text-xs">
            <button 
              @click="printFormat = 'thermal'"
              :class="printFormat === 'thermal' ? 'bg-white text-black font-bold shadow-sm' : 'text-gray-600'"
              class="px-2.5 py-1 rounded transition"
            >
              80mm Thermal Slip
            </button>
            <button 
              @click="printFormat = 'a4'"
              :class="printFormat === 'a4' ? 'bg-white text-black font-bold shadow-sm' : 'text-gray-600'"
              class="px-2.5 py-1 rounded transition"
            >
              A4 GST Tax Invoice
            </button>
          </div>

          <button @click="showReceiptModal = false" class="text-gray-500 hover:text-black font-bold text-lg">&times;</button>
        </div>

        <!-- Scrollable Document Preview Area -->
        <div class="flex-1 overflow-y-auto p-4 bg-gray-50 flex justify-center">
          <!-- 80mm Thermal Slip Preview -->
          <div v-if="printFormat === 'thermal'" class="bg-white p-4 shadow border border-gray-200 w-80 font-mono text-xs text-black">
            <div class="text-center border-b border-dashed border-gray-400 pb-2 mb-2">
              <h2 class="font-extrabold text-sm uppercase">{{ cartStore.storeConfig.storeName }}</h2>
              <p class="text-[10px] text-gray-600">{{ cartStore.storeConfig.storeAddress }}</p>
              <p class="text-[10px] text-gray-700">Phone: {{ cartStore.storeConfig.storePhone }}</p>
              <p class="text-[11px] font-bold mt-1">GSTIN: {{ cartStore.storeConfig.storeGstin }}</p>
              <p class="text-[10px] text-gray-600">State: {{ cartStore.storeConfig.storeStateName }} ({{ cartStore.storeConfig.storeStateCode }})</p>
            </div>

            <div class="text-[10px] text-gray-700 border-b border-dashed border-gray-300 pb-1.5 mb-1.5 flex justify-between">
              <div>
                <p>Bill: <strong>{{ lastCompletedSale.invoice_number }}</strong></p>
                <p>Cust: {{ lastCompletedSale.customer_name }}</p>
              </div>
              <div class="text-right">
                <p>{{ new Date(lastCompletedSale.created_at).toLocaleDateString() }}</p>
                <p>{{ new Date(lastCompletedSale.created_at).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' }) }}</p>
              </div>
            </div>

            <table class="w-full text-[10px] border-b border-dashed border-gray-400 pb-1.5 mb-1.5">
              <thead>
                <tr class="border-b border-dotted border-gray-300">
                  <th class="text-left py-1">Item</th>
                  <th class="text-center py-1">Qty</th>
                  <th class="text-right py-1">Rate</th>
                  <th class="text-right py-1">Amt</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-dotted divide-gray-200">
                <tr v-for="item in lastCompletedSale.items" :key="item.item_id">
                  <td class="py-1">
                    <p class="font-bold leading-tight">{{ item.name }}</p>
                    <p class="text-[9px] text-gray-500">HSN: {{ item.hsn_code }} ({{ item.tax_rate }}%)</p>
                  </td>
                  <td class="text-center py-1">{{ item.quantity }}</td>
                  <td class="text-right py-1 font-mono">₹{{ item.unit_price }}</td>
                  <td class="text-right py-1 font-mono font-bold">₹{{ item.line_total.toFixed(2) }}</td>
                </tr>
              </tbody>
            </table>

            <div class="space-y-1 text-[10px] text-gray-700">
              <div class="flex justify-between">
                <span>Taxable Subtotal:</span>
                <span>₹{{ lastCompletedSale.subtotal.toFixed(2) }}</span>
              </div>
              <div v-if="!lastCompletedSale.is_interstate" class="flex justify-between">
                <span>CGST:</span>
                <span>₹{{ lastCompletedSale.cgst_total.toFixed(2) }}</span>
              </div>
              <div v-if="!lastCompletedSale.is_interstate" class="flex justify-between">
                <span>SGST:</span>
                <span>₹{{ lastCompletedSale.sgst_total.toFixed(2) }}</span>
              </div>
              <div v-if="lastCompletedSale.is_interstate" class="flex justify-between">
                <span>IGST:</span>
                <span>₹{{ lastCompletedSale.igst_total.toFixed(2) }}</span>
              </div>
              <div v-if="lastCompletedSale.round_off !== 0" class="flex justify-between">
                <span>Round Off:</span>
                <span>₹{{ lastCompletedSale.round_off.toFixed(2) }}</span>
              </div>
              <div class="flex justify-between font-extrabold text-sm border-t-2 border-black pt-1 text-black">
                <span>NET TOTAL:</span>
                <span>₹{{ lastCompletedSale.grand_total.toFixed(2) }}</span>
              </div>
              <div class="flex justify-between border-t border-dotted pt-1 text-[10px]">
                <span>Paid by: <strong class="uppercase">{{ lastCompletedSale.payments[0]?.payment_type || 'Cash' }}</strong></span>
                <span v-if="lastCompletedSale.amount_tendered && lastCompletedSale.amount_tendered > lastCompletedSale.grand_total">
                  Change: <strong>₹{{ lastCompletedSale.change_due?.toFixed(2) }}</strong>
                </span>
              </div>
            </div>

            <div class="text-center text-[10px] text-gray-500 border-t border-dashed border-gray-400 mt-2 pt-2">
              <p>*** Thank You For Shopping With Us ***</p>
              <p class="text-[9px]">Goods once sold cannot be returned.</p>
            </div>
          </div>

          <!-- A4 Official GST Tax Invoice Preview -->
          <div v-if="printFormat === 'a4'" class="bg-white p-6 shadow-md border border-gray-300 w-full text-black font-sans text-xs">
            <div class="border border-gray-800 p-4">
              <!-- Invoice Header -->
              <div class="text-center border-b border-gray-400 pb-3 mb-3">
                <span class="bg-gray-100 px-3 py-1 text-[11px] font-bold uppercase tracking-wider border border-gray-300">TAX INVOICE</span>
                <p class="text-[10px] text-gray-600 mt-0.5">(Original for Recipient - Under Section 31 of CGST Act)</p>
                <h1 class="text-lg font-black uppercase text-black mt-1">{{ cartStore.storeConfig.storeName }}</h1>
                <p class="text-xs text-gray-700">{{ cartStore.storeConfig.storeAddress }}</p>
                <p class="text-xs text-gray-700">Phone: {{ cartStore.storeConfig.storePhone }} | Email: {{ cartStore.storeConfig.storeEmail }}</p>
                <p class="text-xs font-bold mt-0.5">GSTIN: {{ cartStore.storeConfig.storeGstin }} | State: {{ cartStore.storeConfig.storeStateName }} (Code: {{ cartStore.storeConfig.storeStateCode }})</p>
              </div>

              <!-- Seller & Buyer 2-Column Grid -->
              <div class="grid grid-cols-2 border border-gray-300 mb-3 text-xs">
                <div class="p-2.5 border-r border-gray-300 bg-gray-50/50">
                  <h3 class="font-bold text-[11px] uppercase text-gray-700 mb-1">Details of Billed Party (Buyer):</h3>
                  <p class="font-bold text-sm text-black">{{ lastCompletedSale.customer_name }}</p>
                  <p v-if="lastCompletedSale.customer_phone">Phone: {{ lastCompletedSale.customer_phone }}</p>
                  <p>GSTIN / UIN: <strong class="font-mono">{{ lastCompletedSale.customer_gstin || 'Unregistered Consumer' }}</strong></p>
                  <p>State: {{ lastCompletedSale.customer_state_code || '27' }} ({{ lastCompletedSale.is_interstate ? 'Inter-state' : 'Intra-state' }})</p>
                </div>
                <div class="p-2.5">
                  <h3 class="font-bold text-[11px] uppercase text-gray-700 mb-1">Invoice Details:</h3>
                  <p>Invoice No: <strong class="font-mono text-sm text-black">{{ lastCompletedSale.invoice_number }}</strong></p>
                  <p>Invoice Date: {{ new Date(lastCompletedSale.created_at).toLocaleDateString() }}</p>
                  <p>Time: {{ new Date(lastCompletedSale.created_at).toLocaleTimeString() }}</p>
                  <p>Place of Supply: {{ lastCompletedSale.customer_state_code || '27' }}</p>
                  <p>Reverse Charge: <strong>No</strong></p>
                </div>
              </div>

              <!-- Line Items Table -->
              <table class="w-full text-xs border border-gray-400 mb-3">
                <thead class="bg-gray-100 border-b border-gray-400 font-bold text-[11px]">
                  <tr>
                    <th class="border-r border-gray-400 p-1.5 text-center w-8">#</th>
                    <th class="border-r border-gray-400 p-1.5 text-left">Description of Goods</th>
                    <th class="border-r border-gray-400 p-1.5 text-center w-16">HSN/SAC</th>
                    <th class="border-r border-gray-400 p-1.5 text-center w-12">Qty</th>
                    <th class="border-r border-gray-400 p-1.5 text-right w-16">Rate (₹)</th>
                    <th class="border-r border-gray-400 p-1.5 text-right w-20">Taxable (₹)</th>
                    <th class="border-r border-gray-400 p-1.5 text-center w-12">GST %</th>
                    <th class="border-r border-gray-400 p-1.5 text-right w-16">Tax (₹)</th>
                    <th class="p-1.5 text-right w-20">Total (₹)</th>
                  </tr>
                </thead>
                <tbody class="divide-y divide-gray-300">
                  <tr v-for="(item, idx) in lastCompletedSale.items" :key="item.item_id">
                    <td class="border-r border-gray-300 p-1.5 text-center font-mono">{{ idx + 1 }}</td>
                    <td class="border-r border-gray-300 p-1.5 font-semibold">{{ item.name }}</td>
                    <td class="border-r border-gray-300 p-1.5 text-center font-mono">{{ item.hsn_code }}</td>
                    <td class="border-r border-gray-300 p-1.5 text-center font-bold">{{ item.quantity }}</td>
                    <td class="border-r border-gray-300 p-1.5 text-right font-mono">₹{{ item.unit_price.toFixed(2) }}</td>
                    <td class="border-r border-gray-300 p-1.5 text-right font-mono">₹{{ item.taxable_amount.toFixed(2) }}</td>
                    <td class="border-r border-gray-300 p-1.5 text-center font-mono">{{ item.tax_rate }}%</td>
                    <td class="border-r border-gray-300 p-1.5 text-right font-mono">₹{{ item.total_tax.toFixed(2) }}</td>
                    <td class="p-1.5 text-right font-mono font-bold">₹{{ item.line_total.toFixed(2) }}</td>
                  </tr>
                </tbody>
              </table>

              <!-- Totals and Words -->
              <div class="grid grid-cols-2 gap-4 border-t border-gray-300 pt-2 mb-3">
                <div class="text-xs">
                  <p class="font-bold text-gray-700">Amount Chargeable (in words):</p>
                  <p class="font-extrabold text-black italic text-[11px] mt-0.5">{{ amountToWords(lastCompletedSale.grand_total) }}</p>
                  
                  <div class="mt-3 border border-gray-200 p-2 rounded bg-gray-50/50 text-[10px]">
                    <p class="font-bold">Bank Details For Payment:</p>
                    <p>Bank: {{ cartStore.storeConfig.bankName }} | A/C No: {{ cartStore.storeConfig.bankAccountNo }}</p>
                    <p>IFSC: {{ cartStore.storeConfig.bankIfsc }} | UPI: {{ cartStore.storeConfig.upiId }}</p>
                  </div>
                </div>

                <div class="space-y-1 text-xs">
                  <div class="flex justify-between border-b border-gray-200 pb-1">
                    <span class="text-gray-600">Total Taxable Value:</span>
                    <span class="font-mono font-bold">₹{{ lastCompletedSale.subtotal.toFixed(2) }}</span>
                  </div>
                  <div v-if="!lastCompletedSale.is_interstate" class="flex justify-between text-gray-700">
                    <span>Central GST (CGST):</span>
                    <span class="font-mono">₹{{ lastCompletedSale.cgst_total.toFixed(2) }}</span>
                  </div>
                  <div v-if="!lastCompletedSale.is_interstate" class="flex justify-between text-gray-700">
                    <span>State GST (SGST):</span>
                    <span class="font-mono">₹{{ lastCompletedSale.sgst_total.toFixed(2) }}</span>
                  </div>
                  <div v-if="lastCompletedSale.is_interstate" class="flex justify-between text-gray-700">
                    <span>Integrated GST (IGST):</span>
                    <span class="font-mono">₹{{ lastCompletedSale.igst_total.toFixed(2) }}</span>
                  </div>
                  <div v-if="lastCompletedSale.round_off !== 0" class="flex justify-between text-gray-500 text-[11px]">
                    <span>Round Off:</span>
                    <span class="font-mono">₹{{ lastCompletedSale.round_off.toFixed(2) }}</span>
                  </div>
                  <div class="flex justify-between text-base font-black border-t-2 border-black pt-1.5 text-black">
                    <span>TOTAL INVOICE VALUE:</span>
                    <span class="font-mono">₹{{ lastCompletedSale.grand_total.toFixed(2) }}</span>
                  </div>
                </div>
              </div>

              <!-- Signatures & Declarations -->
              <div class="grid grid-cols-2 border-t border-gray-300 pt-3 mt-4 text-[10px]">
                <div>
                  <p class="font-bold">Declaration:</p>
                  <p class="text-gray-600">We declare that this invoice shows the actual price of the goods described and that all particulars are true and correct.</p>
                </div>
                <div class="text-right">
                  <p class="font-bold">For {{ cartStore.storeConfig.storeName }}</p>
                  <div class="h-10"></div>
                  <p class="font-bold border-t border-gray-400 inline-block pt-1">Authorized Signatory</p>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Modal Footer Actions -->
        <div class="bg-gray-100 border-t border-gray-200 px-4 py-3 flex justify-between items-center">
          <p class="text-xs text-gray-500">Press Print or Esc to close</p>
          <div class="flex gap-2">
            <button 
              @click="triggerPrint" 
              class="px-5 py-2.5 bg-emerald-600 hover:bg-emerald-700 text-white font-bold rounded-xl flex items-center gap-1.5 shadow"
            >
              <Printer class="w-4 h-4" />
              <span>Print Invoice</span>
            </button>
            <button 
              @click="startNewSale" 
              class="px-4 py-2.5 border border-gray-300 hover:bg-gray-200 text-gray-700 font-bold rounded-xl"
            >
              New Sale
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- DEDICATED POS PRINT CONTAINER (RENDERED STRICTLY ON @MEDIA PRINT) -->
    <div id="pos-print-area">
      <!-- 80mm Print Layout -->
      <div v-if="lastCompletedSale && printFormat === 'thermal'" class="thermal-80mm">
        <div style="text-align: center; border-bottom: 1px dashed black; padding-bottom: 5px; margin-bottom: 5px;">
          <h2 style="font-size: 14px; font-weight: bold; margin: 0; text-transform: uppercase;">{{ cartStore.storeConfig.storeName }}</h2>
          <p style="font-size: 10px; margin: 2px 0;">{{ cartStore.storeConfig.storeAddress }}</p>
          <p style="font-size: 10px; margin: 2px 0;">GSTIN: {{ cartStore.storeConfig.storeGstin }}</p>
          <p style="font-size: 10px; margin: 2px 0;">Phone: {{ cartStore.storeConfig.storePhone }}</p>
        </div>

        <div style="font-size: 10px; border-bottom: 1px dashed black; padding-bottom: 4px; margin-bottom: 4px; display: flex; justify-content: space-between;">
          <div>
            <span>Inv: {{ lastCompletedSale.invoice_number }}</span><br/>
            <span>Cust: {{ lastCompletedSale.customer_name }}</span>
          </div>
          <div style="text-align: right;">
            <span>{{ new Date(lastCompletedSale.created_at).toLocaleDateString() }}</span><br/>
            <span>{{ new Date(lastCompletedSale.created_at).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' }) }}</span>
          </div>
        </div>

        <table style="width: 100%; font-size: 10px; border-bottom: 1px dashed black; margin-bottom: 5px;">
          <thead>
            <tr style="border-bottom: 1px dotted black;">
              <th style="text-align: left; padding: 2px 0;">Item</th>
              <th style="text-align: center; padding: 2px 0;">Qty</th>
              <th style="text-align: right; padding: 2px 0;">Price</th>
              <th style="text-align: right; padding: 2px 0;">Total</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="item in lastCompletedSale.items" :key="item.item_id">
              <td style="padding: 2px 0;">
                <div>{{ item.name }}</div>
                <div style="font-size: 8px;">HSN: {{ item.hsn_code }} ({{ item.tax_rate }}%)</div>
              </td>
              <td style="text-align: center; vertical-align: top; padding: 2px 0;">{{ item.quantity }}</td>
              <td style="text-align: right; vertical-align: top; padding: 2px 0;">{{ item.unit_price }}</td>
              <td style="text-align: right; vertical-align: top; font-weight: bold; padding: 2px 0;">{{ item.line_total.toFixed(2) }}</td>
            </tr>
          </tbody>
        </table>

        <div style="font-size: 10px; line-height: 1.4;">
          <div style="display: flex; justify-content: space-between;">
            <span>Subtotal:</span>
            <span>Rs. {{ lastCompletedSale.subtotal.toFixed(2) }}</span>
          </div>
          <div v-if="!lastCompletedSale.is_interstate" style="display: flex; justify-content: space-between;">
            <span>CGST:</span>
            <span>Rs. {{ lastCompletedSale.cgst_total.toFixed(2) }}</span>
          </div>
          <div v-if="!lastCompletedSale.is_interstate" style="display: flex; justify-content: space-between;">
            <span>SGST:</span>
            <span>Rs. {{ lastCompletedSale.sgst_total.toFixed(2) }}</span>
          </div>
          <div v-if="lastCompletedSale.is_interstate" style="display: flex; justify-content: space-between;">
            <span>IGST:</span>
            <span>Rs. {{ lastCompletedSale.igst_total.toFixed(2) }}</span>
          </div>
          <div v-if="lastCompletedSale.round_off !== 0" style="display: flex; justify-content: space-between;">
            <span>Round Off:</span>
            <span>Rs. {{ lastCompletedSale.round_off.toFixed(2) }}</span>
          </div>
          <div style="display: flex; justify-content: space-between; font-size: 13px; font-weight: bold; border-top: 2px solid black; padding-top: 3px; margin-top: 2px;">
            <span>NET AMOUNT:</span>
            <span>Rs. {{ lastCompletedSale.grand_total.toFixed(2) }}</span>
          </div>
          <div style="display: flex; justify-content: space-between; border-top: 1px dotted black; padding-top: 3px; margin-top: 3px; font-size: 9px;">
            <span>Payment: {{ (lastCompletedSale.payments[0]?.payment_type || 'Cash').toUpperCase() }}</span>
            <span v-if="lastCompletedSale.amount_tendered">Tendered: {{ lastCompletedSale.amount_tendered }}</span>
          </div>
        </div>

        <div style="text-align: center; font-size: 9px; margin-top: 8px; border-top: 1px dashed black; padding-top: 4px;">
          <p style="margin: 0;">*** Thank you for your business! ***</p>
        </div>
      </div>

      <!-- A4 Print Layout -->
      <div v-if="lastCompletedSale && printFormat === 'a4'" class="a4-invoice">
        <div style="border: 2px solid black; padding: 12px;">
          <div style="text-align: center; border-bottom: 1px solid black; padding-bottom: 8px; margin-bottom: 8px;">
            <div style="font-weight: bold; font-size: 13px; text-transform: uppercase;">TAX INVOICE</div>
            <div style="font-size: 9px; color: #444;">(Original for Recipient)</div>
            <h1 style="font-size: 18px; font-weight: bold; margin: 4px 0;">{{ cartStore.storeConfig.storeName }}</h1>
            <p style="font-size: 11px; margin: 2px 0;">{{ cartStore.storeConfig.storeAddress }}</p>
            <p style="font-size: 11px; margin: 2px 0;">GSTIN: {{ cartStore.storeConfig.storeGstin }} | State: {{ cartStore.storeConfig.storeStateName }} (Code: {{ cartStore.storeConfig.storeStateCode }})</p>
          </div>

          <table style="width: 100%; border: 1px solid black; margin-bottom: 10px; font-size: 11px;">
            <tbody>
              <tr>
                <td style="width: 50%; padding: 6px; border-right: 1px solid black; vertical-align: top;">
                  <strong>Details of Buyer:</strong><br/>
                  Name: <strong>{{ lastCompletedSale.customer_name }}</strong><br/>
                  Phone: {{ lastCompletedSale.customer_phone || 'N/A' }}<br/>
                  GSTIN: {{ lastCompletedSale.customer_gstin || 'Unregistered' }}<br/>
                  State: {{ lastCompletedSale.customer_state_code || '27' }}
                </td>
                <td style="width: 50%; padding: 6px; vertical-align: top;">
                  Invoice No: <strong>{{ lastCompletedSale.invoice_number }}</strong><br/>
                  Invoice Date: {{ new Date(lastCompletedSale.created_at).toLocaleDateString() }}<br/>
                  Time: {{ new Date(lastCompletedSale.created_at).toLocaleTimeString() }}<br/>
                  Reverse Charge: No
                </td>
              </tr>
            </tbody>
          </table>

          <table style="width: 100%; border: 1px solid black; border-collapse: collapse; margin-bottom: 10px; font-size: 11px;">
            <thead>
              <tr style="background: #f0f0f0; border-bottom: 1px solid black; font-weight: bold;">
                <th style="border-right: 1px solid black; padding: 4px; width: 25px; text-align: center;">#</th>
                <th style="border-right: 1px solid black; padding: 4px; text-align: left;">Item Description</th>
                <th style="border-right: 1px solid black; padding: 4px; width: 60px; text-align: center;">HSN</th>
                <th style="border-right: 1px solid black; padding: 4px; width: 40px; text-align: center;">Qty</th>
                <th style="border-right: 1px solid black; padding: 4px; width: 60px; text-align: right;">Rate</th>
                <th style="border-right: 1px solid black; padding: 4px; width: 70px; text-align: right;">Taxable</th>
                <th style="border-right: 1px solid black; padding: 4px; width: 45px; text-align: center;">GST %</th>
                <th style="border-right: 1px solid black; padding: 4px; width: 65px; text-align: right;">Tax</th>
                <th style="padding: 4px; width: 75px; text-align: right;">Total</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="(item, idx) in lastCompletedSale.items" :key="item.item_id" style="border-bottom: 1px solid #ccc;">
                <td style="border-right: 1px solid black; padding: 4px; text-align: center;">{{ idx + 1 }}</td>
                <td style="border-right: 1px solid black; padding: 4px;">{{ item.name }}</td>
                <td style="border-right: 1px solid black; padding: 4px; text-align: center;">{{ item.hsn_code }}</td>
                <td style="border-right: 1px solid black; padding: 4px; text-align: center; font-weight: bold;">{{ item.quantity }}</td>
                <td style="border-right: 1px solid black; padding: 4px; text-align: right;">{{ item.unit_price.toFixed(2) }}</td>
                <td style="border-right: 1px solid black; padding: 4px; text-align: right;">{{ item.taxable_amount.toFixed(2) }}</td>
                <td style="border-right: 1px solid black; padding: 4px; text-align: center;">{{ item.tax_rate }}%</td>
                <td style="border-right: 1px solid black; padding: 4px; text-align: right;">{{ item.total_tax.toFixed(2) }}</td>
                <td style="padding: 4px; text-align: right; font-weight: bold;">{{ item.line_total.toFixed(2) }}</td>
              </tr>
            </tbody>
          </table>

          <table style="width: 100%; font-size: 11px; margin-bottom: 12px;">
            <tbody>
              <tr>
                <td style="width: 55%; vertical-align: top;">
                  <strong>Amount in Words:</strong><br/>
                  <em>{{ amountToWords(lastCompletedSale.grand_total) }}</em><br/><br/>
                  <strong>Bank:</strong> {{ cartStore.storeConfig.bankName }} | <strong>A/C:</strong> {{ cartStore.storeConfig.bankAccountNo }}<br/>
                  <strong>IFSC:</strong> {{ cartStore.storeConfig.bankIfsc }}
                </td>
                <td style="width: 45%; vertical-align: top;">
                  <table style="width: 100%; border-collapse: collapse;">
                    <tbody>
                      <tr>
                        <td style="padding: 2px 0;">Taxable Amount:</td>
                        <td style="text-align: right; font-weight: bold;">Rs. {{ lastCompletedSale.subtotal.toFixed(2) }}</td>
                      </tr>
                      <tr v-if="!lastCompletedSale.is_interstate">
                        <td style="padding: 2px 0;">CGST:</td>
                        <td style="text-align: right;">Rs. {{ lastCompletedSale.cgst_total.toFixed(2) }}</td>
                      </tr>
                      <tr v-if="!lastCompletedSale.is_interstate">
                        <td style="padding: 2px 0;">SGST:</td>
                        <td style="text-align: right;">Rs. {{ lastCompletedSale.sgst_total.toFixed(2) }}</td>
                      </tr>
                      <tr v-if="lastCompletedSale.is_interstate">
                        <td style="padding: 2px 0;">IGST:</td>
                        <td style="text-align: right;">Rs. {{ lastCompletedSale.igst_total.toFixed(2) }}</td>
                      </tr>
                      <tr v-if="lastCompletedSale.round_off !== 0">
                        <td style="padding: 2px 0;">Round Off:</td>
                        <td style="text-align: right;">Rs. {{ lastCompletedSale.round_off.toFixed(2) }}</td>
                      </tr>
                      <tr style="border-top: 2px solid black; font-weight: bold; font-size: 13px;">
                        <td style="padding: 4px 0;">GRAND TOTAL:</td>
                        <td style="text-align: right;">Rs. {{ lastCompletedSale.grand_total.toFixed(2) }}</td>
                      </tr>
                    </tbody>
                  </table>
                </td>
              </tr>
            </tbody>
          </table>

          <table style="width: 100%; border-top: 1px solid black; padding-top: 8px; font-size: 10px;">
            <tbody>
              <tr>
                <td style="width: 60%; vertical-align: bottom;">
                  We declare that this invoice shows the actual price of goods described.
                </td>
                <td style="width: 40%; text-align: right; vertical-align: bottom;">
                  For <strong>{{ cartStore.storeConfig.storeName }}</strong><br/><br/><br/>
                  Authorized Signatory
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
</template>
