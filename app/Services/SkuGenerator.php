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
     * Nomor = existingCount + 1 (diisi caller dari count DB), atau dari static counter
     * saat existingCount null (mode deterministik tanpa DB, untuk unit test).
     */
    public static function generate(?Category $category, ?int $existingCount = null): string
    {
        $prefix = self::prefix($category);

        $existing = $existingCount ?? (self::$counters[$prefix] ?? 0);
        $next = $existing + 1;
        self::$counters[$prefix] = max(self::$counters[$prefix] ?? 0, $next);

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