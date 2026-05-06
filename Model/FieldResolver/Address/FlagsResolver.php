<?php
/**
 * @author    Magebit <info@magebit.com>
 * @copyright Copyright (c) Magebit, Ltd. (https://magebit.com)
 * @license   MIT
 */
declare(strict_types=1);

namespace Magebit\McpCustomerTools\Model\FieldResolver\Address;

use Magebit\McpCustomerTools\Api\AddressFieldResolverInterface;
use Magento\Customer\Api\Data\AddressInterface;

class FlagsResolver implements AddressFieldResolverInterface
{
    public const KEY = 'flags';

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
        return 50;
    }

    /**
     * @inheritDoc
     */
    public function resolve(AddressInterface $address, array $args): array
    {
        unset($args);
        return [
            'default_billing' => (bool) $address->isDefaultBilling(),
            'default_shipping' => (bool) $address->isDefaultShipping(),
        ];
    }
}
