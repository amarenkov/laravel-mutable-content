<?php

namespace Amarenkov\MutableContent\Tests\Unit\Macros;

use Illuminate\Support\Str;

use PHPUnit\Framework\Attributes\DataProvider;

use Amarenkov\MutableContent\Tests\TestCase;

class StrTest extends TestCase
{
    public static function labels(): array
    {
        return [
            'cyrillic' => ['Поверхностная плотность', 'poverkhnostnaia_plotnost'],
            'punctuation and case' => ['  Hello, World! (v2)  ', 'hello_world_v2'],
            'only symbols' => ['—!?', 'item'],
            'digits' => ['2024 год', '2024_god'],
        ];
    }

    #[DataProvider('labels')]
    public function test_to_code(string $label, string $code): void
    {
        $this->assertSame($code, Str::toCode($label));
    }

    public function test_to_code_respects_max_length(): void
    {
        $this->assertSame('abc', Str::toCode('abc def', 4));
        $this->assertSame('abc_d', Str::toCode('abc def', 5));
    }

    public function test_unique_code(): void
    {
        $this->assertSame('code', Str::uniqueCode('code', ['other']));
        $this->assertSame('code_2', Str::uniqueCode('code', ['code']));
        $this->assertSame('code_3', Str::uniqueCode('code', ['code', 'code_2']));
        $this->assertSame('10_2', Str::uniqueCode('10', [10]));
    }

    public function test_unique_code_respects_max_length(): void
    {
        $this->assertSame('abc_2', Str::uniqueCode('abcdef', ['abcdef'], 5));
    }
}
