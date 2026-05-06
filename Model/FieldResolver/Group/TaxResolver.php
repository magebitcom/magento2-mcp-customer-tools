<?php
/**
 * @author    Magebit <info@magebit.com>
 * @copyright Copyright (c) Magebit, Ltd. (https://magebit.com)
 * @license   MIT
 */
declare(strict_types=1);

namespace Magebit\McpCustomerTools\Model\FieldResolver\Group;

use Magebit\McpCustomerTools\Api\GroupFieldResolverInterface;
use Magento\Customer\Api\Data\GroupInterface;

class TaxResolver implements GroupFieldResolverInterface
{
    public const KEY = 'tax';

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
    public function resolve(GroupInterface $group, array $args): array
    {
        unset($args);
        return [
            'tax_class_id' => (int) $group->getTaxClassId(),
            'tax_class_name' => $group->getTaxClassName(),
        ];
    }
}
