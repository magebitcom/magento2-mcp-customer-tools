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

class NameResolver implements AddressFieldResolverInterface
{
    public const KEY = 'name';

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
        return 20;
    }

    /**
     * @inheritDoc
     */
    public function resolve(AddressInterface $address, array $args): array
    {
        unset($args);
        return [
            'prefix' => $address->getPrefix(),
            'firstname' => (string) $address->getFirstname(),
            'middlename' => $address->getMiddlename(),
            'lastname' => (string) $address->getLastname(),
            'suffix' => $address->getSuffix(),
            'company' => $address->getCompany(),
        ];
    }
}
