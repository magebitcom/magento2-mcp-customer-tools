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

class IdentityResolver implements CustomerFieldResolverInterface
{
    public const KEY = 'identity';

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
        return 10;
    }

    /**
     * @inheritDoc
     */
    public function resolve(CustomerInterface $customer, array $args): array
    {
        unset($args);
        return [
            'id' => (int) $customer->getId(),
            'email' => (string) $customer->getEmail(),
            'firstname' => (string) $customer->getFirstname(),
            'lastname' => (string) $customer->getLastname(),
        ];
    }
}
