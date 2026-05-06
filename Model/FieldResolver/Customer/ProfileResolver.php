<?php
/**
 * @author    Magebit <info@magebit.com>
 * @copyright Copyright (c) Magebit, Ltd. (https://magebit.com)
 * @license   MIT
 */
declare(strict_types=1);

namespace Magebit\McpCustomerTools\Model\FieldResolver\Customer;

use Magebit\McpCustomerTools\Api\CustomerFieldResolverInterface;
use Magento\Customer\Api\Data\CustomerInterface;

/**
 * dob / gender / taxvat — all PII. Opt out via the tool's `exclude` arg.
 */
class ProfileResolver implements CustomerFieldResolverInterface
{
    public const KEY = 'profile';

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
    public function resolve(CustomerInterface $customer, array $args): array
    {
        unset($args);
        return [
            'dob' => $customer->getDob(),
            'gender' => $customer->getGender(),
            'taxvat' => $customer->getTaxvat(),
        ];
    }
}
