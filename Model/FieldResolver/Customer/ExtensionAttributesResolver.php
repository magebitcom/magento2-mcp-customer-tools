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
use Magento\Framework\Api\AbstractSimpleObject;

/**
 * Modular extension attributes added by other modules (company_attributes,
 * is_subscribed, …). Shape is dynamic — register only on
 * `customer.customer.get`.
 *
 * Output filtering:
 *   - If `$allowedKeys` is non-empty, ONLY listed attribute keys are emitted
 *     (strict allowlist).
 *   - Otherwise every attribute key NOT in `$blockedKeys` is emitted.
 *   - `$blockedKeys` defaults to a small set of well-known sensitive field
 *     names that should never reach the wire as a defense-in-depth backstop.
 *
 * Projects with sensitive 3rd-party extension attributes (risk_score,
 * store_credit_balance, 2fa_enrolled, …) should configure an explicit
 * `$allowedKeys` list via `etc/di.xml` — see `docs/EXTENDING.md`.
 */
class ExtensionAttributesResolver implements CustomerFieldResolverInterface
{
    public const KEY = 'extension_attributes';

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
        return 80;
    }

    /**
     * @inheritDoc
     * @return array<string, mixed>
     */
    public function resolve(CustomerInterface $customer, array $args): array
    {
        unset($args);
        $extension = $customer->getExtensionAttributes();
        // Every Magento-generated extension class extends AbstractSimpleObject —
        // the interface itself declares no methods, so we lean on that contract.
        if (!$extension instanceof AbstractSimpleObject) {
            return [];
        }
        $out = [];
        foreach ($extension->__toArray() as $key => $value) {
            $stringKey = (string) $key;
            if (!$this->shouldEmit($stringKey)) {
                continue;
            }
            $out[$stringKey] = $value;
        }
        return $out;
    }

    /**
     * @param string $key
     * @return bool
     */
    private function shouldEmit(string $key): bool
    {
        if ($this->allowedKeys !== [] && !in_array($key, $this->allowedKeys, true)) {
            return false;
        }
        return !in_array($key, $this->blockedKeys, true);
    }
}
