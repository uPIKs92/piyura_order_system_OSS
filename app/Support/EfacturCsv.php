<?php

namespace App\Support;

use App\Models\Order;
use App\Models\OrderItem;

/**
 * Generates e-Faktur CSV impor rows (DJP e-Faktur 3.0 / Coretax-compatible)
 * for tax-exclusive PPN keluaran orders.
 *
 * All CSV-shape knowledge is intentionally isolated in this class so that a
 * field-order fix against a future DJP Petunjuk Teknis is a single-file edit.
 *
 * Format:
 * - `;`-delimited, NO header row, LF line endings. One FK record per faktur,
 *   followed by one OF record per order item. Numbers use a dot decimal
 *   separator with 2 decimals and no thousands separators.
 * - FK (24 fields): FK;KD_JENIS_TRANSAKSI;FG_PENGGANTI;NOMOR_FAKTUR;
 *   MASA_PAJAK;TAHUN_PAJAK;TANGGAL_FAKTUR;NPWP;NAMA;JALAN;BLOK;NOMOR;RT;RW;
 *   KECAMATAN;KELURAHAN;KABUPATEN;PROPINSI;KODE_POS;NOMOR_TELEPON;FAX;EMAIL
 *   plus 2 trailing blank FK fields required by the DJP CSV impor spec.
 * - OF (8 fields): OF;KODE_OBJEK;NAMA;HARGA_SATUAN;JUMLAH_BARANG;HARGA_TOTAL;
 *   DISKON;DPP.
 *
 * Conventions (B2C end-consumer buyers, per project plan):
 * - KD_JENIS_TRANSAKSI=01 (penjualan normal), FG_PENGGANTI=0 (faktur normal);
 *   retur (02) is out of scope.
 * - NOMOR_FAKTUR: deterministic `010.000-{YY}.{order-id padded to 8}` where
 *   YY is the 2-digit year of order_date; unique per tenant because order ids
 *   are globally unique.
 * - MASA/TAHUN_PAJAK and TANGGAL_FAKTUR (dd/mm/yyyy) derive from order_date.
 * - Buyer: NPWP = 16 zeros, NAMA = customer_name (fallback `UMUM`), JALAN =
 *   customer_address truncated to 80 chars (DJP JALAN field limit); all other
 *   buyer address fields are empty.
 * - KODE_OBJEK: the product's SKU when present; otherwise `P{id}` zero-padded
 *   to 5 digits for items still linked to a product, or `I{id}` zero-padded to
 *   5 digits from the order-item id when the product link is gone. Capped at
 *   20 chars.
 * - Text sanitizing: `;` is replaced with `,` and control characters
 *   (newlines, tabs, etc.) with a space so a value can never split a record.
 * - DISKON = HARGA_TOTAL − line subtotal (after line discount), floored at 0
 *   to protect against inconsistent data.
 */
class EfacturCsv
{
    private const LINE_ENDING = "\n";

    private const BUYER_NPWP_ZEROS = '0000000000000000';

    private const NAMA_MAX_LENGTH = 255;

    private const JALAN_MAX_LENGTH = 80;

    private const KODE_OBJEK_MAX_LENGTH = 20;

    public static function forOrders(iterable $orders): string
    {
        $csv = '';

        foreach ($orders as $order) {
            $csv .= self::forOrder($order);
        }

        return $csv;
    }

    public static function forOrder(Order $order): string
    {
        $lines = [self::fakturLine($order)];

        foreach ($order->items as $item) {
            $lines[] = self::objekLine($item);
        }

        return implode(self::LINE_ENDING, $lines).self::LINE_ENDING;
    }

    public static function nomorFaktur(Order $order): string
    {
        return sprintf('010.000-%s.%08d', $order->order_date->format('y'), $order->id);
    }

    private static function fakturLine(Order $order): string
    {
        $date = $order->order_date;

        return implode(';', [
            'FK',
            '01',
            '0',
            self::nomorFaktur($order),
            $date->format('m'),
            $date->format('Y'),
            $date->format('d/m/Y'),
            self::BUYER_NPWP_ZEROS,
            self::text($order->customer_name ?: 'UMUM', self::NAMA_MAX_LENGTH),
            self::text($order->customer_address, self::JALAN_MAX_LENGTH),
            '', '', '', '', '', '', '', '', '', '', '', '',
            '', '',
        ]);
    }

    private static function objekLine(OrderItem $item): string
    {
        $hargaTotal = round((float) $item->price_snapshot * (int) $item->quantity, 2);
        $dpp = (float) $item->subtotal;

        return implode(';', [
            'OF',
            self::kodeObjek($item),
            self::text($item->product_name, self::NAMA_MAX_LENGTH),
            self::amount((float) $item->price_snapshot),
            (string) (int) $item->quantity,
            self::amount($hargaTotal),
            self::amount(max(0, $hargaTotal - $dpp)),
            self::amount($dpp),
        ]);
    }

    private static function kodeObjek(OrderItem $item): string
    {
        $sku = $item->product?->sku;

        if ($sku !== null && trim($sku) !== '') {
            return self::text($sku, self::KODE_OBJEK_MAX_LENGTH);
        }

        if ($item->product_id !== null) {
            return sprintf('P%05d', (int) $item->product_id);
        }

        return sprintf('I%05d', (int) $item->id);
    }

    private static function amount(float $value): string
    {
        return number_format($value, 2, '.', '');
    }

    private static function text(?string $value, int $maxLength): string
    {
        $clean = (string) preg_replace('/\p{C}+/u', ' ', (string) $value);
        $clean = str_replace(';', ',', $clean);

        return mb_substr(trim($clean), 0, $maxLength);
    }
}
