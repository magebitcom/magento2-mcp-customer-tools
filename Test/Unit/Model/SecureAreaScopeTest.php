<?php
/**
 * @author    Magebit <info@magebit.com>
 * @copyright Copyright (c) Magebit, Ltd. (https://magebit.com)
 * @license   MIT
 */
declare(strict_types=1);

namespace Magebit\McpCustomerTools\Test\Unit\Model;

use Magebit\McpCustomerTools\Model\SecureAreaScope;
use Magento\Framework\Registry;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class SecureAreaScopeTest extends TestCase
{
    private Registry $registry;

    private SecureAreaScope $scope;

    protected function setUp(): void
    {
        $this->registry = new Registry();
        $this->scope = new SecureAreaScope($this->registry);
    }

    public function testFlagIsSetInsideAndUnsetAfter(): void
    {
        $inside = $this->scope->run(fn () => $this->registry->registry('isSecureArea'));

        $this->assertTrue($inside);
        $this->assertNull($this->registry->registry('isSecureArea'));
    }

    public function testCallableResultIsReturned(): void
    {
        $this->assertSame('done', $this->scope->run(static fn (): string => 'done'));
    }

    public function testPreviousValueIsRestored(): void
    {
        $this->registry->register('isSecureArea', false);

        $this->scope->run(static fn (): bool => true);

        $this->assertFalse($this->registry->registry('isSecureArea'));
    }

    public function testFlagIsRestoredWhenTheCallableThrows(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('delete failed');

        try {
            $this->scope->run(static function (): void {
                throw new RuntimeException('delete failed');
            });
        } finally {
            $this->assertNull($this->registry->registry('isSecureArea'));
        }
    }
}
