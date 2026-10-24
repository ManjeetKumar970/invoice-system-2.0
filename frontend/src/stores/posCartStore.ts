import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import { 
  posDb, 
  type LocalItem, 
  type LocalCustomer, 
  type LocalSaleItem, 
  type LocalSaleRecord, 
  type LocalPayment,
  type LocalSuspendedSale
} from '../db/posDatabase'
import { useOnline } from '@vueuse/core'

export const usePosCartStore = defineStore('posCart', () => {
  const isOnline = useOnline()

  // Store Configuration
  const storeConfig = ref({
    storeId: 1,
    storeName: 'FTPL Retail POS',
    storeAddress: 'Shop 12-14, Ground Floor, Central Plaza, Andheri East, Mumbai',
    storePhone: '+91 98200 98200',
    storeGstin: '27AAAAA0000A1Z5',
    storeStateCode: '27', // Maharashtra (27)
    storeStateName: 'Maharashtra',
    storeEmail: 'billing@ftplpos.com',
    terminalId: 'POS-01',
    cashierId: 1,
    cashierName: 'Admin Cashier',
    taxIncluded: false,
    bankName: 'HDFC Bank',
    bankAccountNo: '50200012345678',
    bankIfsc: 'HDFC0001234',
    upiId: 'ftplretail@hdfcbank'
  })

  // Active Cart State
  const cartItems = ref<LocalSaleItem[]>([])
  const selectedCustomer = ref<LocalCustomer | null>(null)
  const paymentType = ref<'cash' | 'card' | 'upi' | 'credit'>('cash')
  const amountTendered = ref<number>(0)
  const paymentReference = ref<string>('')
  const billDiscountPercent = ref<number>(0)
  const invoiceNotes = ref<string>('')

  // GST Calculation: Intra-state (CGST+SGST) vs Inter-state (IGST)
  const isInterState = computed(() => {
    if (!selectedCustomer.value || !selectedCustomer.value.state_code) return false
    return selectedCustomer.value.state_code !== storeConfig.value.storeStateCode
  })

  const subtotal = computed(() => {
    return Number(cartItems.value.reduce((acc, item) => acc + item.taxable_amount, 0).toFixed(2))
  })

  const itemDiscountsTotal = computed(() => {
    return Number(cartItems.value.reduce((acc, item) => acc + item.discount_amount, 0).toFixed(2))
  })

  const billDiscountTotal = computed(() => {
    if (billDiscountPercent.value <= 0) return 0
    return Number(((subtotal.value * billDiscountPercent.value) / 100).toFixed(2))
  })

  const totalDiscount = computed(() => {
    return Number((itemDiscountsTotal.value + billDiscountTotal.value).toFixed(2))
  })

  const netTaxableAmount = computed(() => {
    return Math.max(0, Number((subtotal.value - billDiscountTotal.value).toFixed(2)))
  })

  const cgstTotal = computed(() => {
    if (isInterState.value) return 0
    // If bill discount exists, scale taxes proportionally
    const ratio = subtotal.value > 0 ? (netTaxableAmount.value / subtotal.value) : 1
    const rawCgst = cartItems.value.reduce((acc, item) => acc + item.cgst_amount, 0)
    return Number((rawCgst * ratio).toFixed(2))
  })

  const sgstTotal = computed(() => {
    if (isInterState.value) return 0
    const ratio = subtotal.value > 0 ? (netTaxableAmount.value / subtotal.value) : 1
    const rawSgst = cartItems.value.reduce((acc, item) => acc + item.sgst_amount, 0)
    return Number((rawSgst * ratio).toFixed(2))
  })

  const igstTotal = computed(() => {
    if (!isInterState.value) return 0
    const ratio = subtotal.value > 0 ? (netTaxableAmount.value / subtotal.value) : 1
    const rawIgst = cartItems.value.reduce((acc, item) => acc + item.igst_amount, 0)
    return Number((rawIgst * ratio).toFixed(2))
  })

  const totalTax = computed(() => {
    return isInterState.value ? igstTotal.value : Number((cgstTotal.value + sgstTotal.value).toFixed(2))
  })

  const exactTotal = computed(() => {
    return Number((netTaxableAmount.value + totalTax.value).toFixed(2))
  })

  const grandTotal = computed(() => {
    return Math.round(exactTotal.value)
  })

  const roundOff = computed(() => {
    return Number((grandTotal.value - exactTotal.value).toFixed(2))
  })

  const changeDue = computed(() => {
    if (paymentType.value !== 'cash') return 0
    return Math.max(0, Number((amountTendered.value - grandTotal.value).toFixed(2)))
  })

  // Recalculate line item tax based on inter-state status
  function calculateLineTaxes(
    qty: number,
    price: number,
    discPercent: number,
    taxRate: number
  ) {
    const rawTotal = qty * price
    const discAmount = Number(((rawTotal * discPercent) / 100).toFixed(2))
    const taxable = Number((rawTotal - discAmount).toFixed(2))

    let cgst = 0
    let sgst = 0
    let igst = 0

    if (isInterState.value) {
      igst = Number(((taxable * taxRate) / 100).toFixed(2))
    } else {
      const halfRate = taxRate / 2
      cgst = Number(((taxable * halfRate) / 100).toFixed(2))
      sgst = Number(((taxable * halfRate) / 100).toFixed(2))
    }

    const totalTax = isInterState.value ? igst : Number((cgst + sgst).toFixed(2))
    const lineTotal = Number((taxable + totalTax).toFixed(2))

    return {
      discount_amount: discAmount,
      taxable_amount: taxable,
      cgst_amount: cgst,
      sgst_amount: sgst,
      igst_amount: igst,
      total_tax: totalTax,
      line_total: lineTotal
    }
  }

  // Add Item to cart
  async function addItem(item: LocalItem, quantity = 1) {
    const existingIndex = cartItems.value.findIndex(ci => ci.item_id === item.id)

    if (existingIndex > -1) {
      const current = cartItems.value[existingIndex]
      const newQty = current.quantity + quantity
      const calc = calculateLineTaxes(newQty, current.unit_price, current.discount_percent, current.tax_rate)
      
      cartItems.value[existingIndex] = {
        ...current,
        quantity: newQty,
        ...calc
      }
    } else {
      const calc = calculateLineTaxes(quantity, item.unit_price, 0, item.tax_rate || 0)
      
      cartItems.value.push({
        item_id: item.id,
        item_uuid: item.uuid,
        name: item.name,
        barcode: item.barcode,
        hsn_code: item.hsn_code || '0000',
        quantity,
        unit_price: item.unit_price,
        discount_percent: 0,
        tax_rate: item.tax_rate || 0,
        ...calc
      })
    }

    // Default amount tendered to grand total
    amountTendered.value = grandTotal.value
  }

  // Fast Barcode Scanner (< 1ms direct lookup)
  async function scanBarcode(barcode: string): Promise<boolean> {
    const trimmed = barcode.trim()
    if (!trimmed) return false

    const item = await posDb.items.where('barcode').equals(trimmed).first()
      || await posDb.items.where('sku').equals(trimmed).first()

    if (item) {
      await addItem(item, 1)
      return true
    }
    return false
  }

  function updateQty(index: number, newQty: number) {
    if (newQty <= 0) {
      removeItem(index)
      return
    }
    const current = cartItems.value[index]
    const calc = calculateLineTaxes(newQty, current.unit_price, current.discount_percent, current.tax_rate)
    cartItems.value[index] = { ...current, quantity: newQty, ...calc }
    amountTendered.value = grandTotal.value
  }

  function updatePrice(index: number, newPrice: number) {
    if (newPrice < 0) return
    const current = cartItems.value[index]
    const calc = calculateLineTaxes(current.quantity, newPrice, current.discount_percent, current.tax_rate)
    cartItems.value[index] = { ...current, unit_price: newPrice, ...calc }
    amountTendered.value = grandTotal.value
  }

  function updateDiscount(index: number, discPercent: number) {
    const current = cartItems.value[index]
    const calc = calculateLineTaxes(current.quantity, current.unit_price, discPercent, current.tax_rate)
    cartItems.value[index] = { ...current, discount_percent: discPercent, ...calc }
    amountTendered.value = grandTotal.value
  }

  function removeItem(index: number) {
    cartItems.value.splice(index, 1)
    amountTendered.value = grandTotal.value
  }

  function clearCart() {
    cartItems.value = []
    amountTendered.value = 0
    billDiscountPercent.value = 0
    paymentReference.value = ''
    invoiceNotes.value = ''
  }

  function setCustomer(cust: LocalCustomer | null) {
    selectedCustomer.value = cust
    // Recalculate line taxes for state differences
    cartItems.value = cartItems.value.map(ci => {
      const calc = calculateLineTaxes(ci.quantity, ci.unit_price, ci.discount_percent, ci.tax_rate)
      return { ...ci, ...calc }
    })
    amountTendered.value = grandTotal.value
  }

  // Hold (Suspend) active cart to serve another customer
  async function holdCart(title = ''): Promise<string> {
    if (cartItems.value.length === 0) throw new Error('Cannot hold an empty cart')

    const suspendedUuid = crypto.randomUUID()
    const holdTitle = title || `Held Cart (${selectedCustomer.value?.name || 'Walk-in'} - ${cartItems.value.length} items)`

    const suspendedSale: LocalSuspendedSale = {
      uuid: suspendedUuid,
      title: holdTitle,
      customer_name: selectedCustomer.value?.name || 'Walk-in Customer',
      customer_phone: selectedCustomer.value?.phone || '',
      customer_id: selectedCustomer.value?.id,
      items: JSON.parse(JSON.stringify(cartItems.value)),
      subtotal: subtotal.value,
      grand_total: grandTotal.value,
      created_at: new Date().toISOString()
    }

    await posDb.suspended_sales.put(suspendedSale)
    clearCart()
    return suspendedUuid
  }

  // Resume (Recall) a held cart
  async function resumeCart(uuid: string): Promise<void> {
    const held = await posDb.suspended_sales.get(uuid)
    if (!held) throw new Error('Held sale not found')

    clearCart()
    cartItems.value = JSON.parse(JSON.stringify(held.items))

    if (held.customer_id) {
      const cust = await posDb.customers.get(held.customer_id)
      selectedCustomer.value = cust || null
    }

    await posDb.suspended_sales.delete(uuid)
    amountTendered.value = grandTotal.value
  }

  // Complete bill (Works 100% Offline with Auto-Sync to Laravel MySQL)
  async function completeSale(customPayments?: LocalPayment[]): Promise<LocalSaleRecord> {
    if (cartItems.value.length === 0) {
      throw new Error('Cart is empty. Please add items before completing bill.')
    }

    const saleUuid = crypto.randomUUID()
    const invoiceNum = `INV-${new Date().getFullYear()}-${Date.now().toString().slice(-6)}`

    // Determine payment array
    const finalPayments: LocalPayment[] = customPayments && customPayments.length > 0 
      ? customPayments 
      : [{
          payment_type: paymentType.value,
          amount: Number(grandTotal.value.toFixed(2)),
          reference: paymentReference.value || undefined
        }]

    const saleRecord: LocalSaleRecord = {
      uuid: saleUuid,
      invoice_number: invoiceNum,
      terminal_id: storeConfig.value.terminalId,
      store_id: storeConfig.value.storeId,
      cashier_id: storeConfig.value.cashierId,
      cashier_name: storeConfig.value.cashierName,
      customer_id: selectedCustomer.value?.id,
      customer_name: selectedCustomer.value?.name || 'Walk-in Customer',
      customer_phone: selectedCustomer.value?.phone || '',
      customer_gstin: selectedCustomer.value?.gstin || '',
      customer_state_code: selectedCustomer.value?.state_code || storeConfig.value.storeStateCode,
      is_interstate: isInterState.value,
      subtotal: Number(netTaxableAmount.value.toFixed(2)),
      total_tax: Number(totalTax.value.toFixed(2)),
      cgst_total: Number(cgstTotal.value.toFixed(2)),
      sgst_total: Number(sgstTotal.value.toFixed(2)),
      igst_total: Number(igstTotal.value.toFixed(2)),
      discount_total: Number(totalDiscount.value.toFixed(2)),
      round_off: Number(roundOff.value.toFixed(2)),
      grand_total: Number(grandTotal.value.toFixed(2)),
      amount_tendered: paymentType.value === 'cash' ? Number(amountTendered.value) : Number(grandTotal.value),
      change_due: changeDue.value,
      items: JSON.parse(JSON.stringify(cartItems.value)),
      payments: finalPayments,
      created_at: new Date().toISOString(),
      sync_status: 'pending',
      sync_attempts: 0,
      notes: invoiceNotes.value || undefined
    }

    // 1. Save directly to local IndexedDB queue
    const cleanRecord = JSON.parse(JSON.stringify(saleRecord))
    await posDb.sales_queue.put(cleanRecord)

    // 2. Decrement local stock for each item safely
    for (const item of cartItems.value) {
      try {
        const localItem = await posDb.items.get(item.item_id)
        if (localItem) {
          const currentQty = Number(localItem.stock_quantity || 0)
          await posDb.items.update(item.item_id, {
            stock_quantity: Math.max(0, currentQty - item.quantity),
            updated_at: new Date().toISOString()
          })
        }
      } catch (err) {
        console.warn('Non-fatal item stock update warning:', err)
      }
    }

    // 3. Clear cart
    clearCart()

    // 4. Background Sync to Laravel MySQL Backend (Non-blocking)
    syncSaleToLaravelBackend(cleanRecord)

    return cleanRecord
  }

  // Background async sync to Laravel backend API
  async function syncSaleToLaravelBackend(record: LocalSaleRecord) {
    try {
      const response = await fetch('http://127.0.0.1:8000/api/sync/push', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
        body: JSON.stringify({ sales: [record] })
      })

      if (response.ok) {
        const data = await response.json()
        if (data.status === 'success' && data.synced_uuids?.includes(record.uuid)) {
          await posDb.sales_queue.update(record.uuid, { 
            sync_status: 'synced',
            sync_error: undefined 
          })
        }
      }
    } catch {
      // Offline: stays 'pending' in Dexie queue, ready for batch sync
    }
  }

  return {
    isOnline,
    storeConfig,
    cartItems,
    selectedCustomer,
    paymentType,
    amountTendered,
    paymentReference,
    billDiscountPercent,
    invoiceNotes,
    isInterState,
    subtotal,
    itemDiscountsTotal,
    billDiscountTotal,
    totalDiscount,
    netTaxableAmount,
    cgstTotal,
    sgstTotal,
    igstTotal,
    totalTax,
    exactTotal,
    grandTotal,
    roundOff,
    changeDue,
    addItem,
    scanBarcode,
    updateQty,
    updatePrice,
    updateDiscount,
    removeItem,
    clearCart,
    setCustomer,
    holdCart,
    resumeCart,
    completeSale
  }
})
