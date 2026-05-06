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

class LocationResolver implements AddressFieldResolverInterface
{
    public const KEY = 'location';

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
        return 30;
    }

    /**
     * @inheritDoc
     */
    public function resolve(AddressInterface $address, array $args): array
    {
        unset($args);
        $region = $address->getRegion();
        return [
            'street' => (array) $address->getStreet(),
            'city' => (string) $address->getCity(),
            'region_id' => $address->getRegionId() !== null ? (int) $address->getRegionId() : null,
            'region_code' => $region !== null ? $region->getRegionCode() : null,
            'region' => $region !== null ? $region->getRegion() : null,
            'postcode' => (string) $address->getPostcode(),
            'country_id' => (string) $address->getCountryId(),
        ];
    }
}
