<?php

namespace Tests\Unit;

use App\Support\AppVersion;
use PHPUnit\Framework\TestCase;

class AppVersionTest extends TestCase
{
    public function test_semantic_version_bumps(): void
    {
        $this->assertSame('1.2.1', AppVersion::bump('1.2.0', 'patch'));
        $this->assertSame('1.3.0', AppVersion::bump('1.2.7', 'minor'));
        $this->assertSame('2.0.0', AppVersion::bump('1.9.3', 'major'));
        $this->assertSame('1.0.1', AppVersion::bump('1', 'patch'));
    }
}
