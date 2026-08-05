<?php

namespace App\Services;

use App\Models\Category;

class SkuGenerator
{
    /** @var array<string,int> Counter per prefiks untuk mode non-DB (unit test / deterministik) */
    protected static array $counters = [];

    /**
     * Generate SKU format XXX-####.
     *
     * Prefiks = 3 huruf kapital dari nama kategori (tanpa spasi/simbol), atau 'GEN' bila null.
     * Nomor = jumlah produk ber-prefiks sama di DB + 1; jika kategori tidak punya produk,
     * increment dari static counter agar deterministik saat dipanggil berulang tanpa simpan.
     */
    public static function generate(?Category $category): string
    {
        $prefix = self::prefix($category);

        $existing = $category?->products()->count() ?? 0;
        $count = max($existing, self::$counters[$prefix] ?? 0);

        $next = $count + 1;
        self::$counters[$prefix] = $next;

        return sprintf('%s-%04d', $prefix, $next);
    }

    protected static function prefix(?Category $category): string
    {
        if ($category === null || $category->name === null) {
            return 'GEN';
        }

        $clean = preg_replace('/[^a-zA-Z0-9]/', '', $category->name) ?: 'GEN';

        return strtoupper(substr($clean, 0, 3));
    }
}