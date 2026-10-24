import Dexie, { type EntityTable } from 'dexie'

export interface LocalCategory {
  id: number
  uuid: string
  name: string
  slug: string
  is_active: number
}

export interface LocalItem {
  id: number
  uuid: string
  name: string
  barcode: string
  sku: string
  category_id?: number
  category_name?: string
  unit_price: number
  cost_price: number
  tax_category_id?: number
  tax_rate: number
  hsn_code: string
  stock_quantity: number
  is_active: number
  updated_at: string
}

export interface LocalCustomer {
  id: number
  uuid: string
  name: string
  phone: string
  email?: string
  gstin?: string
  state_code: string
  loyalty_points: number
  updated_at: string
}

export interface LocalTaxSlab {
  id: number
  uuid: string
  name: string
  rate: number
  cgst_rate: number
  sgst_rate: number
  igst_rate: number
}

export interface LocalSaleItem {
  item_id: number
  item_uuid: string
  name: string
  barcode: string
  hsn_code: string
  quantity: number
  unit_price: number
  discount_percent: number
  discount_amount: number
  taxable_amount: number
  tax_rate: number
  cgst_amount: number
  sgst_amount: number
  igst_amount: number
  total_tax: number
  line_total: number
}

export interface LocalPayment {
  payment_type: 'cash' | 'card' | 'upi' | 'credit'
  amount: number
  reference?: string
}

export interface LocalSuspendedSale {
  uuid: string
  title: string
  customer_name: string
  customer_phone?: string
  customer_id?: number
  items: LocalSaleItem[]
  subtotal: number
  grand_total: number
  created_at: string
}

export interface LocalSaleRecord {
  uuid: string // Unique ID generated on counter
  invoice_number: string
  terminal_id: string
  store_id: number
  cashier_id: number
  cashier_name: string
  customer_id?: number
  customer_name: string
  customer_phone?: string
  customer_gstin?: string
  customer_state_code?: string
  is_interstate: boolean
  subtotal: number
  total_tax: number
  cgst_total: number
  sgst_total: number
  igst_total: number
  discount_total: number
  round_off: number
  grand_total: number
  amount_tendered?: number
  change_due?: number
  items: LocalSaleItem[]
  payments: LocalPayment[]
  created_at: string
  sync_status: 'pending' | 'syncing' | 'synced' | 'failed'
  sync_attempts: number
  sync_error?: string
  notes?: string
}

export interface LocalSyncMeta {
  key: string
  value: string
}

export class PosDatabase extends Dexie {
  items!: EntityTable<LocalItem, 'id'>
  categories!: EntityTable<LocalCategory, 'id'>
  customers!: EntityTable<LocalCustomer, 'id'>
  tax_slabs!: EntityTable<LocalTaxSlab, 'id'>
  sales_queue!: EntityTable<LocalSaleRecord, 'uuid'>
  suspended_sales!: EntityTable<LocalSuspendedSale, 'uuid'>
  sync_meta!: EntityTable<LocalSyncMeta, 'key'>

  constructor() {
    super('FtplPosLocalDB')
    
    this.version(1).stores({
      items: 'id, uuid, barcode, sku, name, hsn_code, updated_at',
      customers: 'id, uuid, phone, gstin, name, updated_at',
      tax_slabs: 'id, uuid, rate',
      sales_queue: 'uuid, invoice_number, sync_status, created_at, customer_id',
      sync_meta: 'key'
    })

    this.version(2).stores({
      items: 'id, uuid, barcode, sku, name, hsn_code, category_id, updated_at',
      categories: 'id, uuid, name, slug',
      customers: 'id, uuid, phone, gstin, name, updated_at',
      tax_slabs: 'id, uuid, rate',
      sales_queue: 'uuid, invoice_number, sync_status, created_at, customer_id',
      sync_meta: 'key'
    })

    this.version(3).stores({
      items: 'id, uuid, barcode, sku, name, hsn_code, category_id, updated_at',
      categories: 'id, uuid, name, slug',
      customers: 'id, uuid, phone, gstin, name, updated_at',
      tax_slabs: 'id, uuid, rate',
      sales_queue: 'uuid, invoice_number, sync_status, created_at',
      sync_meta: 'key'
    })

    this.version(4).stores({
      items: 'id, uuid, barcode, sku, name, hsn_code, category_id, updated_at',
      categories: 'id, uuid, name, slug',
      customers: 'id, uuid, phone, gstin, name, updated_at',
      tax_slabs: 'id, uuid, rate',
      sales_queue: 'uuid, invoice_number, sync_status, created_at',
      suspended_sales: 'uuid, customer_name, created_at',
      sync_meta: 'key'
    })
  }
}

export const posDb = new PosDatabase()
