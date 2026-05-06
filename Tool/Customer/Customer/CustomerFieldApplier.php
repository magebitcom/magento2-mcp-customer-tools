<?php
/**
 * @author    Magebit <info@magebit.com>
 * @copyright Copyright (c) Magebit, Ltd. (https://magebit.com)
 * @license   MIT
 */
declare(strict_types=1);

namespace Magebit\McpCustomerTools\Tool\Customer\Customer;

use Magento\Customer\Api\Data\CustomerInterface;

class CustomerFieldApplier
{
    /**
     * Returns the keys actually written so the caller can log a truthful
     * audit summary — type-mismatched values are silently dropped here.
     *
     * @param CustomerInterface $customer
     * @param array $args
     * @phpstan-param array<string, mixed> $args
     * @return string[]
     * @phpstan-return array<int, string>
     */
    public function applyOptional(CustomerInterface $customer, array $args): array
    {
        $written = [];

        if (array_key_exists('email', $args) && is_string($args['email'])) {
            $customer->setEmail($args['email']);
            $written[] = 'email';
        }
        if (array_key_exists('firstname', $args) && is_string($args['firstname'])) {
            $customer->setFirstname($args['firstname']);
            $written[] = 'firstname';
        }
        if (array_key_exists('lastname', $args) && is_string($args['lastname'])) {
            $customer->setLastname($args['lastname']);
            $written[] = 'lastname';
        }
        if (array_key_exists('middlename', $args) && is_string($args['middlename'])) {
            $customer->setMiddlename($args['middlename']);
            $written[] = 'middlename';
        }
        if (array_key_exists('prefix', $args) && is_string($args['prefix'])) {
            $customer->setPrefix($args['prefix']);
            $written[] = 'prefix';
        }
        if (array_key_exists('suffix', $args) && is_string($args['suffix'])) {
            $customer->setSuffix($args['suffix']);
            $written[] = 'suffix';
        }
        if (array_key_exists('dob', $args) && is_string($args['dob'])) {
            $customer->setDob($args['dob']);
            $written[] = 'dob';
        }
        if (array_key_exists('gender', $args) && is_numeric($args['gender'])) {
            $customer->setGender((int) $args['gender']);
            $written[] = 'gender';
        }
        if (array_key_exists('taxvat', $args) && is_string($args['taxvat'])) {
            $customer->setTaxvat($args['taxvat']);
            $written[] = 'taxvat';
        }
        if (array_key_exists('group_id', $args) && is_numeric($args['group_id'])) {
            $customer->setGroupId((int) $args['group_id']);
            $written[] = 'group_id';
        }
        if (array_key_exists('website_id', $args) && is_numeric($args['website_id'])) {
            $customer->setWebsiteId((int) $args['website_id']);
            $written[] = 'website_id';
        }
        if (array_key_exists('store_id', $args) && is_numeric($args['store_id'])) {
            $customer->setStoreId((int) $args['store_id']);
            $written[] = 'store_id';
        }
        if (array_key_exists('created_in', $args) && is_string($args['created_in'])) {
            $customer->setCreatedIn($args['created_in']);
            $written[] = 'created_in';
        }

        return $written;
    }
}
