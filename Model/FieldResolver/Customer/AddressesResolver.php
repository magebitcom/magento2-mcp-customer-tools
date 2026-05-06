<?php
/**
 * @author    Magebit <info@magebit.com>
 * @copyright Copyright (c) Magebit, Ltd. (https://magebit.com)
 * @license   MIT
 */
declare(strict_types=1);

namespace Magebit\McpCustomerTools\Model\FieldResolver\Customer;

use Magebit\McpCustomerTools\Api\CustomerFieldResolverInterface;
use Magento\Customer\Api\Data\AddressInterface;
use Magento\Customer\Api\Data\CustomerInterface;

/**
 * Largest PII payload on a customer response — register only on
 * `customer.customer.get`, never on list, to avoid multiplying the full
 * address book by page size.
 */
class AddressesResolver implements CustomerFieldResolverInterface
{
    public const KEY = 'addresses';

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
    public function resolve(CustomerInterface $customer, array $args): array
    {
        unset($args);
        $rows = [];
        foreach ((array) $customer->getAddresses() as $address) {
            if (!$address instanceof AddressInterface) {
                continue;
            }
            $region = $address->getRegion();
            $rows[] = [
                'id' => (int) $address->getId(),
                'firstname' => (string) $address->getFirstname(),
                'lastname' => (string) $address->getLastname(),
                'company' => $address->getCompany(),
                'street' => (array) $address->getStreet(),
                'city' => (string) $address->getCity(),
                'region_id' => $address->getRegionId() !== null ? (int) $address->getRegionId() : null,
                'region' => $region !== null ? $region->getRegion() : null,
                'postcode' => (string) $address->getPostcode(),
                'country_id' => (string) $address->getCountryId(),
                'telephone' => $address->getTelephone(),
                'vat_id' => $address->getVatId(),
                'default_billing' => (bool) $address->isDefaultBilling(),
                'default_shipping' => (bool) $address->isDefaultShipping(),
            ];
        }
        return $rows;
    }
}
