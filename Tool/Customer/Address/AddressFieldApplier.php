<?php
/**
 * @author    Magebit <info@magebit.com>
 * @copyright Copyright (c) Magebit, Ltd. (https://magebit.com)
 * @license   MIT
 */
declare(strict_types=1);

namespace Magebit\McpCustomerTools\Tool\Customer\Address;

use Magento\Customer\Api\Data\AddressInterface;
use Magento\Customer\Api\Data\RegionInterface;
use Magento\Customer\Api\Data\RegionInterfaceFactory;

class AddressFieldApplier
{
    public function __construct(
        private readonly RegionInterfaceFactory $regionFactory
    ) {
    }

    /**
     * Returns the keys actually written so the caller can log a truthful
     * audit summary — type-mismatched values are silently dropped here.
     *
     * @param AddressInterface $address
     * @param array $args
     * @phpstan-param array<string, mixed> $args
     * @return string[]
     * @phpstan-return array<int, string>
     */
    public function applyOptional(AddressInterface $address, array $args): array
    {
        $written = [];

        if (array_key_exists('customer_id', $args) && is_numeric($args['customer_id'])) {
            $address->setCustomerId((int) $args['customer_id']);
            $written[] = 'customer_id';
        }
        if (array_key_exists('firstname', $args) && is_string($args['firstname'])) {
            $address->setFirstname($args['firstname']);
            $written[] = 'firstname';
        }
        if (array_key_exists('lastname', $args) && is_string($args['lastname'])) {
            $address->setLastname($args['lastname']);
            $written[] = 'lastname';
        }
        if (array_key_exists('middlename', $args) && is_string($args['middlename'])) {
            $address->setMiddlename($args['middlename']);
            $written[] = 'middlename';
        }
        if (array_key_exists('prefix', $args) && is_string($args['prefix'])) {
            $address->setPrefix($args['prefix']);
            $written[] = 'prefix';
        }
        if (array_key_exists('suffix', $args) && is_string($args['suffix'])) {
            $address->setSuffix($args['suffix']);
            $written[] = 'suffix';
        }
        if (array_key_exists('company', $args) && is_string($args['company'])) {
            $address->setCompany($args['company']);
            $written[] = 'company';
        }
        if (array_key_exists('street', $args)) {
            $address->setStreet($this->coerceStreet($args['street']));
            $written[] = 'street';
        }
        if (array_key_exists('city', $args) && is_string($args['city'])) {
            $address->setCity($args['city']);
            $written[] = 'city';
        }
        if (array_key_exists('postcode', $args) && is_string($args['postcode'])) {
            $address->setPostcode($args['postcode']);
            $written[] = 'postcode';
        }
        if (array_key_exists('country_id', $args) && is_string($args['country_id'])) {
            $address->setCountryId($args['country_id']);
            $written[] = 'country_id';
        }
        if (array_key_exists('region_id', $args) && is_numeric($args['region_id'])) {
            $address->setRegionId((int) $args['region_id']);
            $written[] = 'region_id';
        }
        if (array_key_exists('region', $args) && is_string($args['region'])) {
            $address->setRegion($this->buildRegion($args['region'], $args['region_id'] ?? null));
            $written[] = 'region';
        }
        if (array_key_exists('telephone', $args) && is_string($args['telephone'])) {
            $address->setTelephone($args['telephone']);
            $written[] = 'telephone';
        }
        if (array_key_exists('fax', $args) && is_string($args['fax'])) {
            $address->setFax($args['fax']);
            $written[] = 'fax';
        }
        if (array_key_exists('vat_id', $args) && is_string($args['vat_id'])) {
            $address->setVatId($args['vat_id']);
            $written[] = 'vat_id';
        }
        if (array_key_exists('default_billing', $args)) {
            $address->setIsDefaultBilling((bool) $args['default_billing']);
            $written[] = 'default_billing';
        }
        if (array_key_exists('default_shipping', $args)) {
            $address->setIsDefaultShipping((bool) $args['default_shipping']);
            $written[] = 'default_shipping';
        }

        return $written;
    }

    /**
     * `AddressInterface::setStreet()` expects `string[]`; accept a single
     * newline-separated string for caller convenience.
     *
     * @param mixed $raw
     * @return array<int, string>
     */
    private function coerceStreet(mixed $raw): array
    {
        if (is_array($raw)) {
            $lines = [];
            foreach ($raw as $line) {
                if (is_string($line)) {
                    $lines[] = $line;
                }
            }
            return $lines;
        }
        if (is_string($raw)) {
            return array_values(array_filter(
                preg_split('/\r?\n/', $raw) ?: [],
                static fn(string $line): bool => $line !== ''
            ));
        }
        return [];
    }

    /**
     * @param string $regionName
     * @param mixed $regionId
     * @return RegionInterface
     */
    private function buildRegion(string $regionName, mixed $regionId): RegionInterface
    {
        $region = $this->regionFactory->create();
        $region->setRegion($regionName);
        if (is_numeric($regionId)) {
            $region->setRegionId((int) $regionId);
        }
        return $region;
    }
}
