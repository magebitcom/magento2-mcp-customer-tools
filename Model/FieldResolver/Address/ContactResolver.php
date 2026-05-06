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

class ContactResolver implements AddressFieldResolverInterface
{
    public const KEY = 'contact';

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
        return 40;
    }

    /**
     * @inheritDoc
     */
    public function resolve(AddressInterface $address, array $args): array
    {
        unset($args);
        return [
            'telephone' => $address->getTelephone(),
            'fax' => $address->getFax(),
            'vat_id' => $address->getVatId(),
        ];
    }
}
