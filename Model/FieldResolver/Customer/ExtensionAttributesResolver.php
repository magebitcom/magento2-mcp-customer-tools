<?php
/**
 * @author    Magebit <info@magebit.com>
 * @copyright Copyright (c) Magebit, Ltd. (https://magebit.com)
 * @license   MIT
 */
declare(strict_types=1);

namespace Magebit\McpCustomerTools\Model\FieldResolver\Customer;

use Magebit\McpCustomerTools\Api\CustomerFieldResolverInterface;
use Magento\Customer\Api\Data\CustomerInterface;
use Magento\Framework\Api\AbstractSimpleObject;

/**
 * Modular extension attributes added by other modules (company_attributes,
 * is_subscribed, …). Shape is dynamic — register only on
 * `customer.customer.get`.
 */
class ExtensionAttributesResolver implements CustomerFieldResolverInterface
{
    public const KEY = 'extension_attributes';

    /**
     * @inheritDoc
     */
    public function getKey(): string
    {
        return self::KEY;
    }

    /**
     * @inheritDoc
     */
    public function getSortOrder(): int
    {
        return 80;
    }

    /**
     * @inheritDoc
     * @return array<string, mixed>
     */
    public function resolve(CustomerInterface $customer, array $args): array
    {
        unset($args);
        $extension = $customer->getExtensionAttributes();
        // Every Magento-generated extension class extends AbstractSimpleObject —
        // the interface itself declares no methods, so we lean on that contract.
        if (!$extension instanceof AbstractSimpleObject) {
            return [];
        }
        return $extension->__toArray();
    }
}
