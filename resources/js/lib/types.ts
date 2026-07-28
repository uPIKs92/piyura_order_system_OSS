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
    harga_beli: number;
    stok: number;
    min_stok?: number;
    is_default: boolean;
    product?: Product;
}

export interface Product {
    id: number;
    category_id: number;
    nama: string;
    sku?: string;
    barcode?: string;
    deskripsi?: string;
    is_active: boolean;
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
    metode: string;
    paid_at?: string;
    notes?: string;
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
    notes?: string;
    version: number;
    items?: OrderItem[];
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
    items: { product_unit_id: number | string; quantity: number; discount_type?: string; discount_value?: number }[];
    status?: string;
    version?: number;
}

export interface DailyReport {
    total_orders: number;
    total_revenue: number;
}

export interface DailyTrendPoint {
    date: string;
    total_orders: number;
    total_revenue: number;
    sales_turnover: number;
    purchasing_cost: number;
    net_profit: number;
}

export interface DailyTrendTotals {
    sales_turnover: number;
    purchasing_cost: number;
    net_profit: number;
    total_orders: number;
    total_revenue: number;
}

export interface DailyTrendReport {
    period: { from: string; to: string };
    totals: DailyTrendTotals;
    items: DailyTrendPoint[];
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
}

export interface StaffReport {
    period?: { from: string; to: string };
    all?: boolean;
    items?: StaffReportRow[];
    total_orders: number;
    total_revenue: number;
    total_ppn?: number;
    by_status?: Record<string, number>;
}

export interface ProductReportRow {
    product_id: number;
    product_name: string;
    total_orders: number;
    total_revenue: number;
}

export interface ProductReport {
    period?: { from: string; to: string };
    product_id?: number;
    all?: boolean;
    items?: ProductReportRow[];
    total_orders: number;
    total_revenue: number;
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
    | 'cancel_restore';

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
    product_unit?: ProductUnit & { product?: Product };
}

export interface StockReceipt {
    id: number;
    receipt_no: string;
    supplier_name?: string | null;
    notes?: string | null;
    received_at: string;
    lines?: StockReceiptLine[];
}

export interface PaginatedResponse<T> {
    data: T[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
}
