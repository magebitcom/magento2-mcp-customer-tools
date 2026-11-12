<?php
/**
 * @author    Magebit <info@magebit.com>
 * @copyright Copyright (c) Magebit, Ltd. (https://magebit.com)
 * @license   MIT
 */
declare(strict_types=1);

namespace Magebit\McpCustomerTools\Test\Unit\Tool\Customer\Customer;

use Magebit\McpCustomerTools\Model\EntityFinder;
use Magebit\McpCustomerTools\Model\SecureAreaScope;
use Magebit\McpCustomerTools\Tool\Customer\Customer\CustomerDelete;
use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Customer\Api\Data\CustomerInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Registry;
use PHPUnit\Framework\TestCase;

class CustomerDeleteTest extends TestCase
{
    public function testDeleteRunsInsideTheSecureAreaAndClearsItAfter(): void
    {
        $registry = new Registry();
        $customer = $this->createMock(CustomerInterface::class);
        $customer->method('getId')->willReturn(42);
        $customer->method('getEmail')->willReturn('jane@example.com');

        $finder = $this->createMock(EntityFinder::class);
        $finder->expects($this->once())->method('customerFrom')->with(['id' => 42])->willReturn($customer);

        $repository = $this->createMock(CustomerRepositoryInterface::class);
        $repository->expects($this->once())
            ->method('delete')
            ->with($customer)
            ->willReturnCallback(static function () use ($registry): bool {
                if ($registry->registry('isSecureArea') !== true) {
                    throw new LocalizedException(__('Delete operation is forbidden for current area'));
                }
                return true;
            });

        $tool = new CustomerDelete($finder, $repository, new SecureAreaScope($registry));
        $text = $tool->execute(['id' => 42])->getContent()[0]['text'] ?? null;

        $this->assertIsString($text);
        $this->assertSame(
            ['deleted' => true, 'id' => 42, 'email' => 'jane@example.com'],
            json_decode($text, true)
        );
        $this->assertNull($registry->registry('isSecureArea'));
    }

    public function testDeleteRequiresConfirmation(): void
    {
        $tool = new CustomerDelete(
            $this->createMock(EntityFinder::class),
            $this->createMock(CustomerRepositoryInterface::class),
            new SecureAreaScope(new Registry())
        );

        $this->assertTrue($tool->getConfirmationRequired());
        $this->assertSame('customer.customer.delete', $tool->getName());
    }
}
