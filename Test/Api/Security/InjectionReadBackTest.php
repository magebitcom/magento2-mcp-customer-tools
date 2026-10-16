<?php
/**
 * @author    Magebit <info@magebit.com>
 * @copyright Copyright (c) Magebit, Ltd. (https://magebit.com)
 * @license   MIT
 */
declare(strict_types=1);

namespace Magebit\McpCustomerTools\Test\Api\Security;

use Magebit\Mcp\Test\Api\McpTestCase;
use Magebit\Mcp\Test\Api\Security\InjectionPayload;
use Magebit\Mcp\Test\Api\Security\ToolPayload;
use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\TestFramework\Helper\Bootstrap;

/**
 * A read tool returns stored prompt injection text as plain data; the server does not act on it.
 *
 * @magentoApiDataFixture Magento/User/_files/user_with_role.php
 * @magentoApiDataFixture Magebit_McpCustomerTools::Test/Api/_files/mcpsec_injection_customer.php
 */
class InjectionReadBackTest extends McpTestCase
{
    /**
     * @return void
     */
    public function testReadToolReturnsInjectionTextAsData(): void
    {
        /** @var CustomerRepositoryInterface $customers */
        $customers = Bootstrap::getObjectManager()->get(CustomerRepositoryInterface::class);
        $id = $customers->get(InjectionPayload::CUSTOMER_EMAIL, 1)->getId();

        $response = $this->toolsCall('customer.customer.get', ['id' => (int) $id]);

        $this->assertJsonRpcSuccess($response);
        self::assertTrue(
            ToolPayload::contains(ToolPayload::decode($response), InjectionPayload::NAME_TEXT),
            'Expected the stored injection text in the tool output.'
        );
    }
}
