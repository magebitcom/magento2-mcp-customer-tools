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
 * `firstname` / `lastname` are emitted by {@see IdentityResolver}; this
 * slice carries only the optional name parts.
 */
class NameResolver implements CustomerFieldResolverInterface
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
    public function resolve(CustomerInterface $customer, array $args): array
    {
        unset($args);
        return [
            'prefix' => $customer->getPrefix(),
            'middlename' => $customer->getMiddlename(),
            'suffix' => $customer->getSuffix(),
        ];
    }
}
