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

/**
 * EAV custom attributes are site-specific and can be heavy (loyalty points,
 * segments, etc.) — register only on `customer.customer.get`.
 */
class CustomAttributesResolver implements CustomerFieldResolverInterface
{
    public const KEY = 'custom_attributes';

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
        return 70;
    }

    /**
     * @inheritDoc
     * @return array<string, mixed>
     */
    public function resolve(CustomerInterface $customer, array $args): array
    {
        unset($args);
        $out = [];
        foreach ((array) $customer->getCustomAttributes() as $attribute) {
            $out[$attribute->getAttributeCode()] = $attribute->getValue();
        }
        return $out;
    }
}
