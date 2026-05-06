<?php
/**
 * @author    Magebit <info@magebit.com>
 * @copyright Copyright (c) Magebit, Ltd. (https://magebit.com)
 * @license   MIT
 */
declare(strict_types=1);

namespace Magebit\McpCustomerTools\Model;

use Magento\Customer\Api\AddressRepositoryInterface;
use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Customer\Api\Data\AddressInterface;
use Magento\Customer\Api\Data\CustomerInterface;
use Magento\Customer\Api\Data\GroupInterface;
use Magento\Customer\Api\GroupRepositoryInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;

/**
 * Shared id-or-email lookups for the customer-domain entities. Each method
 * enforces "exactly one identity form" — both-missing and both-present
 * raise {@see LocalizedException} so the tool layer reports `INVALID_PARAMS`.
 */
class EntityFinder
{
    /**
     * @param CustomerRepositoryInterface $customerRepository
     * @param AddressRepositoryInterface $addressRepository
     * @param GroupRepositoryInterface $groupRepository
     */
    public function __construct(
        private readonly CustomerRepositoryInterface $customerRepository,
        private readonly AddressRepositoryInterface $addressRepository,
        private readonly GroupRepositoryInterface $groupRepository
    ) {
    }

    /**
     * `website_id` matters on email lookup when `customer/account_share/scope`
     * is per-website (Magento's default) and the same email lives on
     * multiple sites.
     *
     * @param array $args
     * @phpstan-param array<string, mixed> $args
     * @return CustomerInterface
     * @throws LocalizedException
     */
    public function customerFrom(array $args): CustomerInterface
    {
        $id = $this->pickNumeric($args, ['id', 'customer_id'], 1);
        $email = $this->pickString($args, ['email']);
        $this->requireOneOf($id, $email, 'id/customer_id', 'email');

        if ($id !== null) {
            try {
                return $this->customerRepository->getById($id);
            } catch (NoSuchEntityException $e) {
                throw new LocalizedException(__('Customer %1 not found.', $id), $e);
            }
        }

        $websiteId = $this->pickNumeric($args, ['website_id'], 0);
        try {
            return $this->customerRepository->get((string) $email, $websiteId);
        } catch (NoSuchEntityException $e) {
            $scope = $websiteId === null ? 'default scope' : 'website ' . $websiteId;
            throw new LocalizedException(
                __('Customer with email "%1" not found in %2.', (string) $email, $scope),
                $e
            );
        }
    }

    /**
     * @param array $args
     * @phpstan-param array<string, mixed> $args
     * @return AddressInterface
     * @throws LocalizedException
     */
    public function addressFrom(array $args): AddressInterface
    {
        $id = $this->pickNumeric($args, ['id', 'address_id'], 1);
        if ($id === null) {
            throw new LocalizedException(__('Argument "id" or "address_id" is required.'));
        }
        try {
            return $this->addressRepository->getById($id);
        } catch (NoSuchEntityException $e) {
            throw new LocalizedException(__('Customer address %1 not found.', $id), $e);
        }
    }

    /**
     * @param array $args
     * @phpstan-param array<string, mixed> $args
     * @return GroupInterface
     * @throws LocalizedException
     */
    public function groupFrom(array $args): GroupInterface
    {
        $id = $this->pickNumeric($args, ['id', 'group_id'], 0);
        if ($id === null) {
            throw new LocalizedException(__('Argument "id" or "group_id" is required.'));
        }
        try {
            return $this->groupRepository->getById($id);
        } catch (NoSuchEntityException $e) {
            throw new LocalizedException(__('Customer group %1 not found.', $id), $e);
        }
    }

    /**
     * Pass `$minimum = 0` to admit the "NOT LOGGED IN" group and the
     * "no website binding" sentinel; default `1` rejects both.
     *
     * @param array $args
     * @phpstan-param array<string, mixed> $args
     * @param array $candidates
     * @phpstan-param array<int, string> $candidates
     * @param int $minimum
     * @return int|null
     */
    private function pickNumeric(array $args, array $candidates, int $minimum = 1): ?int
    {
        foreach ($candidates as $key) {
            if (!array_key_exists($key, $args)) {
                continue;
            }
            $value = $args[$key];
            if (is_int($value) && $value >= $minimum) {
                return $value;
            }
            if (is_string($value) && ctype_digit($value) && (int) $value >= $minimum) {
                return (int) $value;
            }
        }
        return null;
    }

    /**
     * @param array $args
     * @phpstan-param array<string, mixed> $args
     * @param array $candidates
     * @phpstan-param array<int, string> $candidates
     * @return string|null
     */
    private function pickString(array $args, array $candidates): ?string
    {
        foreach ($candidates as $key) {
            if (!array_key_exists($key, $args)) {
                continue;
            }
            $value = $args[$key];
            if (is_string($value) && $value !== '') {
                return $value;
            }
        }
        return null;
    }

    /**
     * @param int|null $id
     * @param string|null $email
     * @param string $idLabel
     * @param string $emailLabel
     * @return void
     * @throws LocalizedException
     */
    private function requireOneOf(
        ?int $id,
        ?string $email,
        string $idLabel,
        string $emailLabel
    ): void {
        if ($id === null && $email === null) {
            throw new LocalizedException(
                __('One of "%1" or "%2" is required.', $idLabel, $emailLabel)
            );
        }
        if ($id !== null && $email !== null) {
            throw new LocalizedException(
                __('Provide either "%1" or "%2", not both.', $idLabel, $emailLabel)
            );
        }
    }
}
