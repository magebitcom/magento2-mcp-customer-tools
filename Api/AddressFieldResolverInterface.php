<?php
/**
 * @author    Magebit <info@magebit.com>
 * @copyright Copyright (c) Magebit, Ltd. (https://magebit.com)
 * @license   MIT
 */
declare(strict_types=1);

namespace Magebit\McpCustomerTools\Api;

use Magebit\Mcp\Api\FieldResolverInterface;
use Magento\Customer\Api\Data\AddressInterface;

interface AddressFieldResolverInterface extends FieldResolverInterface
{
    /**
     * @param AddressInterface $address
     * @param array $args
     * @phpstan-param array<string, mixed> $args
     * @return array<int|string, mixed>
     */
    public function resolve(AddressInterface $address, array $args): array;
}
