<?php
/**
 * @author    Magebit <info@magebit.com>
 * @copyright Copyright (c) Magebit, Ltd. (https://magebit.com)
 * @license   MIT
 */
declare(strict_types=1);

namespace Magebit\McpCustomerTools\Api;

use Magebit\Mcp\Api\FieldResolverInterface;
use Magento\Customer\Api\Data\GroupInterface;

interface GroupFieldResolverInterface extends FieldResolverInterface
{
    /**
     * @param GroupInterface $group
     * @param array $args
     * @phpstan-param array<string, mixed> $args
     * @return array<int|string, mixed>
     */
    public function resolve(GroupInterface $group, array $args): array;
}
