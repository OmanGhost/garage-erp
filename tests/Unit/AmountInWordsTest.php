<?php

namespace Tests\Unit;

use App\Support\AmountInWords as W;
use PHPUnit\Framework\TestCase;

class AmountInWordsTest extends TestCase
{
    public function test_arabic_amounts_in_rials_and_baisa(): void
    {
        $this->assertSame('فقط ريال عماني واحد لا غير', W::arabic('1'));
        $this->assertSame('فقط ريالان عمانيان لا غير', W::arabic('2'));
        $this->assertSame('فقط خمسة ريالات عمانية لا غير', W::arabic('5'));
        $this->assertSame('فقط أحد عشر ريالاً عمانياً لا غير', W::arabic('11'));
        $this->assertSame('فقط ثلاثة آلاف ريال عماني لا غير', W::arabic('3000'));
        $this->assertSame('فقط ألف ومائتان وخمسة وثلاثون ريالاً عمانياً وخمسمائة بيسة لا غير', W::arabic('1235.500'));
        $this->assertSame('فقط خمسمائة بيسة لا غير', W::arabic('0.500'));
        $this->assertSame('فقط مليونان وخمسمائة ألف ريال عماني لا غير', W::arabic('2500000'));
    }

    public function test_english_amounts(): void
    {
        $this->assertSame('One Omani Rial only', W::english('1'));
        $this->assertSame('Eighty-seven Omani Rials and six hundred seventy-five Baisa only', W::english('87.675'));
        $this->assertSame([1235, 500, false], W::split('1235.5'));
    }
}
