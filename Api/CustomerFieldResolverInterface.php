<?php
/**
 * @author    Magebit <info@magebit.com>
 * @copyright Copyright (c) Magebit, Ltd. (https://magebit.com)
 * @license   MIT
 */
declare(strict_types=1);

namespace Magebit\McpCustomerTools\Api;

use Magebit\Mcp\Api\FieldResolverInterface;
use Magento\Customer\Api\Data\CustomerInterface;

/**
 * Each resolver owns one named slice of the response. Register custom
 * implementations under the tool's `fieldResolvers` DI array — see
 * `docs/EXTENDING.md`.
 */
interface CustomerFieldResolverInterface extends FieldResolverInterface
{
    /**
     * @param CustomerInterface $customer
     * @param array $args
     * @phpstan-param array<string, mixed> $args
     * @return array<int|string, mixed>
     */
    public function resolve(CustomerInterface $customer, array $args): array;
}
