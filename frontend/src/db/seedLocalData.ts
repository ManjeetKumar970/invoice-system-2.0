import { posDb, type LocalItem, type LocalTaxSlab } from './posDatabase'

export async function initLocalDatabase() {
  const count = await posDb.items.count()
  if (count > 0) {
    return // Already initialized
  }

  // Initial GST Tax Slabs
  const taxSlabs: LocalTaxSlab[] = [
    { id: 1, uuid: 'tax-0', name: 'GST 0% / Nil-Rated', rate: 0.0, cgst_rate: 0.0, sgst_rate: 0.0, igst_rate: 0.0 },
    { id: 2, uuid: 'tax-5', name: 'GST 5%', rate: 5.0, cgst_rate: 2.5, sgst_rate: 2.5, igst_rate: 5.0 },
    { id: 3, uuid: 'tax-12', name: 'GST 12%', rate: 12.0, cgst_rate: 6.0, sgst_rate: 6.0, igst_rate: 12.0 },
    { id: 4, uuid: 'tax-18', name: 'GST 18%', rate: 18.0, cgst_rate: 9.0, sgst_rate: 9.0, igst_rate: 18.0 },
    { id: 5, uuid: 'tax-28', name: 'GST 28%', rate: 28.0, cgst_rate: 14.0, sgst_rate: 14.0, igst_rate: 28.0 },
  ]
  await posDb.tax_slabs.bulkAdd(taxSlabs)

  // Initial Master Items cached in IndexedDB
  const initialItems: LocalItem[] = [
    {
      id: 1,
      uuid: 'item-rice-1',
      name: 'Royal Basmati Rice 5KG',
      barcode: '8901030382341',
      sku: 'RICE-BAS-5K',
      category_id: 1,
      category_name: 'Groceries',
      unit_price: 549.00,
      cost_price: 420.00,
      tax_category_id: 2,
      tax_rate: 5.0,
      hsn_code: '1006',
      stock_quantity: 85,
      is_active: 1,
      updated_at: new Date().toISOString()
    },
    {
      id: 2,
      uuid: 'item-cable-2',
      name: 'Braided USB-C Fast Charging Cable 1.5m',
      barcode: '8901725181222',
      sku: 'CAB-USBC-15',
      category_id: 2,
      category_name: 'Electronics',
      unit_price: 299.00,
      cost_price: 140.00,
      tax_category_id: 4,
      tax_rate: 18.0,
      hsn_code: '8544',
      stock_quantity: 120,
      is_active: 1,
      updated_at: new Date().toISOString()
    },
    {
      id: 3,
      uuid: 'item-tea-3',
      name: 'Darjeeling Premium Green Tea 250g',
      barcode: '8901491101833',
      sku: 'TEA-GRN-250',
      category_id: 3,
      category_name: 'Beverages',
      unit_price: 245.00,
      cost_price: 170.00,
      tax_category_id: 2,
      tax_rate: 5.0,
      hsn_code: '0902',
      stock_quantity: 45,
      is_active: 1,
      updated_at: new Date().toISOString()
    },
    {
      id: 4,
      uuid: 'item-mouse-4',
      name: 'Logitech B100 Optical Mouse USB',
      barcode: '8902519001015',
      sku: 'MOU-LOG-B100',
      category_id: 2,
      category_name: 'Electronics',
      unit_price: 399.00,
      cost_price: 260.00,
      tax_category_id: 4,
      tax_rate: 18.0,
      hsn_code: '8471',
      stock_quantity: 30,
      is_active: 1,
      updated_at: new Date().toISOString()
    },
    {
      id: 5,
      uuid: 'item-almond-5',
      name: 'California Jumbo Almonds 500g',
      barcode: '8901233014112',
      sku: 'NUT-ALM-500',
      category_id: 4,
      category_name: 'Dry Fruits',
      unit_price: 480.00,
      cost_price: 360.00,
      tax_category_id: 3,
      tax_rate: 12.0,
      hsn_code: '0802',
      stock_quantity: 60,
      is_active: 1,
      updated_at: new Date().toISOString()
    }
  ]
  await posDb.items.bulkAdd(initialItems)

  // Seed default customer
  await posDb.customers.add({
    id: 1,
    uuid: 'cust-1',
    name: 'Rahul Sharma (VIP)',
    phone: '9820198201',
    gstin: '27BCIPA1234F1Z8',
    state_code: '27',
    loyalty_points: 250,
    updated_at: new Date().toISOString()
  })
}
