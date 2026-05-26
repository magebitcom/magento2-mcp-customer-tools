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
 * EAV custom attributes are site-specific and can be heavy (loyalty points,
 * segments, etc.) — register only on `customer.customer.get`.
 *
 * Output filtering:
 *   - If `$allowedKeys` is non-empty, ONLY listed attribute codes are emitted
 *     (strict allowlist).
 *   - Otherwise every attribute code NOT in `$blockedKeys` is emitted.
 *   - `$blockedKeys` defaults to a small set of well-known Magento-internal
 *     fields that should never reach the wire. Magento normally keeps these
 *     off the custom-attributes channel, but the blocklist is a cheap
 *     defense-in-depth backstop against a misconfigured EAV attribute.
 *
 * Projects with sensitive 3rd-party custom attributes (fraud_score, vip_tier,
 * internal_notes, …) should configure an explicit `$allowedKeys` list via
 * `etc/di.xml` — see `docs/EXTENDING.md`.
 */
class CustomAttributesResolver implements CustomerFieldResolverInterface
{
    public const KEY = 'custom_attributes';

    /** @var array<int, string> */
    private const DEFAULT_BLOCKED = [
        'password_hash',
        'password',
        'rp_token',
        'rp_token_created_at',
        'confirmation',
        'failures_num',
        'first_failure',
        'lock_expires',
    ];

    /** @var array<int, string> */
    private readonly array $allowedKeys;

    /** @var array<int, string> */
    private readonly array $blockedKeys;

    /**
     * @param array<int, string> $allowedKeys
     * @param array<int, string> $blockedKeys
     */
    public function __construct(
        array $allowedKeys = [],
        array $blockedKeys = self::DEFAULT_BLOCKED
    ) {
        $this->allowedKeys = array_values(array_unique(array_map('strval', $allowedKeys)));
        $this->blockedKeys = array_values(array_unique(array_map('strval', $blockedKeys)));
    }

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
        return 70;
    }

    /**
     * @inheritDoc
     * @return array<string, mixed>
     */
    public function resolve(CustomerInterface $customer, array $args): array
    {
        unset($args);
        $out = [];
        foreach ((array) $customer->getCustomAttributes() as $attribute) {
            $code = (string) $attribute->getAttributeCode();
            if (!$this->shouldEmit($code)) {
                continue;
            }
            $out[$code] = $attribute->getValue();
        }
        return $out;
    }

    /**
     * @param string $code
     * @return bool
     */
    private function shouldEmit(string $code): bool
    {
        if ($this->allowedKeys !== [] && !in_array($code, $this->allowedKeys, true)) {
            return false;
        }
        return !in_array($code, $this->blockedKeys, true);
    }
}
