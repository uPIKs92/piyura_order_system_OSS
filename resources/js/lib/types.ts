import type { PaletteColor } from '@/lib/theme';

export type { PaletteColor };

export interface User {
    id: number;
    name: string;
    email: string;
    role: 'owner' | 'staff';
    tenant_id: number;
    is_active: boolean;
    is_platform_admin?: boolean;
}

export type ThemeMode = 'light' | 'dark' | 'system';

export interface TenantBranding {
    id: number;
    slug: string;
    name: string;
    tagline?: string | null;
    address?: string | null;
    phone?: string | null;
    email?: string | null;
    logo_url?: string | null;
    invoice_footer_text?: string | null;
    theme_mode?: ThemeMode;
    theme_palette?: PaletteColor;
}

export interface AppBranding {
    app_name: string;
    platform_name: string;
    show_platform_credit_on_invoice: boolean;
}

export interface AuthResponse {
    user: User;
    tenant: TenantBranding | null;
    token?: string;
}

export interface Category {
    id: number;
    nama: string;
    deskripsi?: string;
    is_active: boolean;
    products_count?: number;
}

export interface ProductUnit {
    id: number;
    product_id: number;
    satuan: string;
    harga_jual: number;
    harga_beli?: number;
    stok: number;
    min_stok?: number;
    is_default: boolean;
    product?: Product;
}

export interface Product {
    id: number;
    category_id: number | null;
    nama: string;
    sku?: string;
    barcode?: string;
    deskripsi?: string;
    is_active: boolean;
    photo_path?: string | null;
    photo_url?: string | null;
    category?: Category;
    units?: ProductUnit[];
}

export interface OrderItem {
    id?: number;
    product_id: number;
    product_unit_id?: number;
    product_name?: string;
    satuan?: string;
    quantity: number;
    price_snapshot?: number;
    subtotal?: number;
    discount_type?: string;
    discount_value?: number;
    product?: Product;
    product_unit?: ProductUnit;
}

export interface Payment {
    id: number;
    amount: number;
    tendered?: number;
    change_amount?: number;
    metode: string;
    paid_at?: string;
    notes?: string;
    external_reference?: string;
}

export interface OrderStatusLog {
    id: number;
    from_status: string;
    to_status: string;
    reason?: string;
    created_at: string;
    changed_by?: User;
}

export interface OrderDateHistory {
    id: number;
    old_date: string;
    new_date: string;
    reason?: string;
    changed_by?: User;
}

export type DeliveryMethod = 'diantar' | 'diambil';

export interface DeliveryFeeSettings {
    fee_mode: 'per_km' | 'fixed';
    fee_per_km: number;
    min_fee: number;
    fixed_fee: number;
    origin?: { lat: number; lon: number } | null;
}

export interface DeliveryEstimateResult extends DeliveryFeeSettings {
    distance_km: number;
    fee_amount: number;
}

export interface DeliverySettingsResponse extends DeliveryFeeSettings {
    google_maps_configured: boolean;
}

export interface DeliveryTestResponse {
    connected: boolean;
    message: string;
    latency_ms?: number;
}

export interface Order {
    id: number;
    user_id: number;
    invoice_no: string;
    status: string;
    customer_name?: string;
    customer_phone?: string;
    customer_address?: string;
    order_date: string;
    subtotal: number;
    discount_total: number;
    ppn_percentage: number;
    ppn_amount: number;
    grand_total: number;
    total_paid: number;
    payment_status?: string;
    remaining_amount?: number;
    change_due?: number;
    change_amount?: number;
    delivery_method?: DeliveryMethod;
    delivery_fee?: number;
    delivery_distance_km?: number | null;
    notes?: string;
    version: number;
    mayar_qr_url?: string | null;
    mayar_amount?: number | null;
    items?: OrderItem[];
    items_count?: number;
    payments?: Payment[];
    status_logs?: OrderStatusLog[];
    statusLogs?: OrderStatusLog[];
    date_histories?: OrderDateHistory[];
    dateHistories?: OrderDateHistory[];
    user?: User;
}

export interface OrderFormData {
    customer_name: string;
    customer_phone: string;
    customer_address?: string;
    order_date: string;
    notes: string;
    delivery_method?: DeliveryMethod;
    delivery_distance_km?: string;
    delivery_fee_override?: string;
    items: { product_unit_id: number | string; quantity: number; discount_type?: string; discount_value?: number }[];
    status?: string;
    version?: number;
}

export interface OrderSummary {
    total_orders: number;
    status_counts: Record<string, number>;
    total_revenue: number;
    unpaid_amount: number;
    unpaid_count: number;
    daily: { date: string; orders: number; revenue: number }[];
}

export type ExpenseCategory = 'bahan_baku' | 'kemasan' | 'transport' | 'operasional' | 'gaji' | 'lain_lain';

export interface Expense {
    id: number;
    tenant_id: number;
    user_id?: number | null;
    expense_date: string;
    amount: string;
    category: ExpenseCategory;
    note?: string | null;
    created_at?: string;
    updated_at?: string;
}

export interface DailyReport {
    total_orders: number;
    total_revenue: number;
    returns_total?: number;
    net_revenue?: number;
    net_profit?: number;
    expenses_total?: number;
    laba_bersih?: number;
}

export interface DailyTrendPoint {
    date: string;
    total_orders: number;
    total_revenue: number;
    sales_turnover: number;
    purchasing_cost: number;
    net_profit: number;
    expenses_total?: number;
    laba_bersih?: number;
    gross_subtotal: number;
    total_discount: number;
    aov: number;
    returns_total?: number;
    net_revenue?: number;
}

export interface DailyTrendTotals {
    sales_turnover: number;
    purchasing_cost: number;
    net_profit: number;
    expenses_total?: number;
    laba_bersih?: number;
    total_orders: number;
    total_revenue: number;
    gross_subtotal: number;
    total_discount: number;
    aov: number;
    returns_total?: number;
    net_revenue?: number;
}

export interface DailyTrendReport {
    period: { from: string; to: string };
    totals: DailyTrendTotals;
    items: DailyTrendPoint[];
}

export interface StatusReportRow {
    status: string;
    count: number;
    total: number;
    unpaid_count: number;
    unpaid_amount: number;
}

export interface StatusReport {
    period: { from: string | null; to: string | null };
    statuses: StatusReportRow[];
    totals: {
        orders: number;
        active_orders: number;
        active_value: number;
        piutang_count: number;
        piutang_amount: number;
    };
}

export interface ActivityLogEntry {
    id: number;
    description: string;
    event?: string;
    properties?: Record<string, unknown>;
    created_at?: string;
    causer?: { id: number; name: string; email: string };
}

export interface PpnSettings {
    enabled: boolean;
    percentage: number;
}

export interface PajakSettings {
    npwp: string | null;
    pph_mode: PphMode;
}

export interface IntegrationSettings {
    sheets_sync_enabled: boolean;
    sheets_spreadsheet_id: string | null;
    sheets_products_tab: string;
    sheets_orders_tab: string;
    sheets_reporting_tab: string;
    google_connected: boolean;
    google_connected_email: string | null;
}

export interface StaffReportRow {
    staff_id: number;
    staff_name: string;
    total_orders: number;
    total_revenue: number;
    total_ppn?: number;
    unpaid_count: number;
    unpaid_amount: number;
    aov: number;
}

export interface StaffReport {
    period?: { from: string; to: string };
    all?: boolean;
    items?: StaffReportRow[];
    total_orders: number;
    total_revenue: number;
    total_ppn?: number;
    unpaid_count: number;
    unpaid_amount: number;
    aov: number;
    by_status?: Record<string, number>;
}

export interface ProductReportRow {
    product_id: number;
    product_name: string;
    satuan: string;
    total_orders: number;
    total_revenue: number;
    total_qty: number;
    total_cost: number;
    net_profit: number;
    returns_total?: number;
    net_revenue?: number;
    returned_qty?: number;
    net_qty?: number;
    prev_net_qty?: number;
    prev_net_revenue?: number;
    delta_qty_pct?: number | null;
    delta_revenue_pct?: number | null;
}

export interface ProductReport {
    period?: { from: string; to: string };
    product_id?: number;
    all?: boolean;
    items?: ProductReportRow[];
    total_orders: number;
    total_revenue: number;
    total_qty: number;
    total_cost: number;
    net_profit: number;
    returns_total?: number;
    net_revenue?: number;
    returned_qty?: number;
    net_qty?: number;
}

export type PphMode = 'umkm_non_pkp' | 'umkm_pkp_22';

export interface TaxMonth {
    month: number;
    orders: number;
    omzet: number;
    faktur_count: number;
    dpp: number;
    ppn: number;
    pph: number;
}

export interface TaxReport {
    year: number;
    ppn: { enabled: boolean; percentage: number };
    pph: { mode: PphMode; total: number };
    totals: { orders: number; omzet: number; dpp: number; ppn: number; faktur_count: number };
    months: TaxMonth[];
}

export interface OrderReturnResult {
    id: number;
    return_no: string;
    refund_amount: number;
    restock_status: boolean;
}

export interface SheetsSyncEntry {
    id: number;
    order_id: number;
    status: string;
    attempts: number;
    last_error: string | null;
    order?: { id: number; invoice_no: string };
}

export interface BackupEntry {
    id: number;
    name: string;
    disk: string;
    size: number;
    path?: string;
    checksum?: string;
    status: string;
    created_at: string;
}

export interface BackupSettings {
    disk: string;
    driver: string;
    folder: string;
    backup_name: string;
}

export interface ImportResult {
    queued?: boolean;
    rows?: number;
    message?: string;
    total?: number;
    success?: number;
    failed?: number;
    errors?: Array<{ row: number; field: string; reason: string }>;
}

export type StockMovementType =
    | 'sale'
    | 'return'
    | 'restock'
    | 'receive'
    | 'adjustment'
    | 'write_off'
    | 'cancel_restore'
    | 'delete_restore';

export interface StockMovement {
    id: number;
    product_unit_id: number;
    type: StockMovementType;
    quantity_delta: number;
    quantity_before: number;
    quantity_after: number;
    notes?: string | null;
    created_at: string;
    product_unit?: ProductUnit & { product?: Product };
    creator?: { id: number; name: string };
}

export interface StockAlert {
    product_unit_id: number;
    product_id: number;
    product_name: string;
    category_name?: string;
    satuan: string;
    stok: number;
    min_stok: number;
    is_out_of_stock: boolean;
}

export interface StockAlertsResponse {
    count: number;
    items: StockAlert[];
}

export interface StockReceiptLine {
    id: number;
    product_unit_id: number;
    quantity: number;
    unit_cost?: number | null;
    batch_no?: string | null;
    expired_at?: string | null;
    product_unit?: ProductUnit & { product?: Product };
}

export interface StockReceipt {
    id: number;
    receipt_no: string;
    supplier_id?: number | null;
    supplier_name?: string | null;
    notes?: string | null;
    received_at: string;
    lines?: StockReceiptLine[];
}

export interface ExpiryAlertBatch {
    id: number;
    product_unit_id: number;
    product_id: number;
    product_name: string;
    category_name?: string | null;
    satuan: string;
    batch_no?: string | null;
    expired_at: string;
    qty: number;
    days_left: number;
}

export interface ExpiryAlertsResponse {
    alert_days: number;
    expired: ExpiryAlertBatch[];
    near_expiry: ExpiryAlertBatch[];
    expired_count: number;
    near_expiry_count: number;
}

export interface ProductBatch {
    id: number;
    product_unit_id: number;
    product_id: number;
    product_name: string;
    category_name?: string | null;
    satuan: string;
    batch_no?: string | null;
    expired_at?: string | null;
    qty: number;
}

export interface StockOpnameResult {
    product_unit_id: number;
    product_name: string;
    satuan: string;
    system_qty: number;
    counted_qty: number;
    diff: number;
}

export interface StockOpnameResponse {
    results: StockOpnameResult[];
}

export interface Supplier {
    id: number;
    name: string;
    phone?: string | null;
    address?: string | null;
    notes?: string | null;
    created_at?: string;
}

export interface Customer {
    id: number;
    name: string;
    phone?: string | null;
    address?: string | null;
    latitude?: number | null;
    longitude?: number | null;
    notes?: string | null;
    last_ordered_at?: string | null;
    orders_count?: number;
    created_at?: string;
    updated_at?: string;
}

export interface ExpirySettings {
    alert_days: number;
}

export interface PaginatedResponse<T> {
    data: T[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
}

export interface ReceivableOrder {
    id: number;
    order_date: string;
    grand_total: number;
    total_paid: number;
    due_amount: number;
    status: string;
    user_id: number;
}

export interface ReceivableGroup {
    customer_id: number | null;
    name: string;
    phone: string | null;
    unpaid_amount: number;
    unpaid_count: number;
    oldest_order_date: string;
    oldest_days: number;
    aging_bucket: '<=7' | '8-14' | '15-30' | '>30';
    orders: ReceivableOrder[];
}

export interface ReceivablesReport {
    totals: {
        unpaid_amount: number;
        unpaid_count: number;
        debtor_count: number;
    };
    groups: ReceivableGroup[];
}

export interface PaymentSettings {
    bank_name: string | null;
    bank_account_name: string | null;
    bank_account_number: string | null;
    qris_image_url: string | null;
    mayar_enabled: boolean;
}

export interface MayarLinkResponse {
    enabled: boolean;
    qr_url?: string;
    amount?: number;
    message?: string;
}

export interface MayarTestResponse {
    connected: boolean;
    message: string;
}
