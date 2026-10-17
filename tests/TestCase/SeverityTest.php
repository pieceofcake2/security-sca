<?php
declare(strict_types=1);

namespace PieceOfCake2\SecuritySca\Test;

use PHPUnit\Framework\TestCase;
use PieceOfCake2\SecuritySca\Severity;

class SeverityTest extends TestCase
{
    public function testNormalize(): void
    {
        $this->assertSame('high', Severity::normalize('High'));
        $this->assertSame('medium', Severity::normalize('moderate'));
        $this->assertSame('unknown', Severity::normalize(null));
        $this->assertSame('unknown', Severity::normalize('severe'));
    }

    public function testReaches(): void
    {
        $this->assertTrue(Severity::reaches('critical', 'high'));
        $this->assertTrue(Severity::reaches('high', 'high'));
        $this->assertFalse(Severity::reaches('medium', 'high'));
        $this->assertTrue(Severity::reaches('low', 'low'));
        $this->assertFalse(Severity::reaches('critical', 'none'));
        // An advisory without a risk counts only for the lowest threshold
        $this->assertFalse(Severity::reaches('unknown', 'medium'));
        $this->assertTrue(Severity::reaches('unknown', 'low'));
    }
}
