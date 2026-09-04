<?php

return [
    'path' => storage_path('app/imports'),
    'template_path' => storage_path('app/imports/templates/orders-import-template.xlsx'),

    'column_map' => [
        'customer_name' => 'customer_name',
        'customer_phone' => 'customer_phone',
        'order_date' => 'order_date',
        'product_name' => 'product_name',
        'quantity' => 'quantity',
        'price' => 'price',
        'status' => 'status',
    ],

    'sheets_legacy' => [
        'products_sheet' => 'Products',
        'orders_sheet' => 'Orders',
        'products_sheet_index' => 0,
        'orders_sheet_index' => 1,
        'products' => [
            'nama' => ['Product Name', 'Nama Produk', 'Product'],
            'satuan' => ['Unit', 'Satuan', 'UOM'],
            'harga_beli' => ['COGS', 'COGS (Cost Price)', 'Harga Beli', 'Harga beli', 'Cost', 'Purchase Price'],
            'harga_jual' => ['Selling Price', 'Harga Jual', 'Harga jual', 'Price', 'Sell Price'],
        ],
        'orders' => [
            'order_date' => 'Date',
            'customer_name' => 'Customer Name',
            'product_name' => 'Product Name',
            'quantity' => 'Qty',
            'satuan' => 'Unit',
            'payment_status' => 'Status',
            'delivery_status' => 'Delivery',
        ],
        'delivery_map' => [
            'Selesai' => 'selesai',
            'selesai' => 'selesai',
            'Menunggu' => 'pending',
            'menunggu' => 'pending',
            'Diproses' => 'diproses',
            'diproses' => 'diproses',
            'Dikirim' => 'dikirim',
            'dikirim' => 'dikirim',
            'Batal' => 'cancelled',
            'batal' => 'cancelled',
        ],
        'cancelled_status_values' => ['Batal', 'batal', 'Cancel', 'cancel', 'Cancelled', 'cancelled'],
        'payment_status_paid' => ['lunas', 'Lunas'],
        'default_category' => 'Import',
    ],

    'queue_threshold' => 100,
];
