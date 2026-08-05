<?php

namespace Tests\Unit;

use App\Models\Category;
use App\Services\SkuGenerator;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class SkuGeneratorTest extends TestCase
{
    #[Test]
    public function generates_prefix_from_category_name(): void
    {
        $category = new Category(['name' => 'Elektronik']);

        $this->assertSame('ELE-0001', SkuGenerator::generate($category));
    }

    #[Test]
    public function uses_gen_prefix_when_no_category(): void
    {
        $this->assertSame('GEN-0001', SkuGenerator::generate(null));
    }

    #[Test]
    public function increments_per_category(): void
    {
        $category = new Category(['name' => 'Furniture']);
        $next = fn (int $n) => sprintf('%s-%04d', 'FUR', $n);

        $this->assertSame($next(1), SkuGenerator::generate($category));
        $this->assertSame($next(2), SkuGenerator::generate($category));
        $this->assertSame($next(3), SkuGenerator::generate($category));
    }
}