# Extending `Magebit_McpCustomerTools`

Every read tool in this module composes its response from a DI-injected
array of **field resolvers**. Each resolver owns one named slice of the
output; 3rd parties add, replace, or remove slices from their own
`etc/di.xml` without touching this module.

The same pattern applies symmetrically to customers, addresses, and groups
— each with its own entity-scoped interface.

## Add a new field to `customer.customer.get` / `customer.customer.list`

### 1. Implement `CustomerFieldResolverInterface`

```php
<?php
declare(strict_types=1);

namespace Vendor\Loyalty\Mcp\Resolver;

use Magebit\McpCustomerTools\Api\CustomerFieldResolverInterface;
use Magento\Customer\Api\Data\CustomerInterface;

final class LoyaltyTierResolver implements CustomerFieldResolverInterface
{
    public function getKey(): string
    {
        return 'loyalty';
    }

    public function getSortOrder(): int
    {
        // built-ins live in [10, 90]; anything in [100, 999] renders after them.
        return 100;
    }

    public function resolve(CustomerInterface $customer, array $args): array
    {
        return [
            'tier' => (string) $customer->getCustomAttribute('loyalty_tier')?->getValue(),
            'points' => (int) $customer->getCustomAttribute('loyalty_points')?->getValue(),
        ];
    }
}
```

### 2. Register the resolver in `etc/di.xml`

```xml
<type name="Magebit\McpCustomerTools\Tool\Customer\Customer\CustomerGet">
    <arguments>
        <argument name="fieldResolvers" xsi:type="array">
            <item name="loyalty" xsi:type="object">
                Vendor\Loyalty\Mcp\Resolver\LoyaltyTierResolver
            </item>
        </argument>
    </arguments>
</type>
```

Register it against `CustomerList` too if you want the slice on list rows.
Keep in mind list tools multiply your payload by the page size; lean
resolvers (plain scalars) are fine, but resolvers that trigger DB joins
should usually ship only on `Get`.

### 3. Run setup:upgrade && setup:di:compile

```bash
bin/magento setup:upgrade
bin/magento setup:di:compile
bin/magento cache:flush
```

### Opt-out from a caller

Callers narrow the payload per request:

```json
{ "id": 42, "fields": ["identity", "loyalty"] }
{ "id": 42, "exclude": ["addresses", "profile"] }
```

## Restricting the `custom_attributes` / `extension_attributes` payload

`CustomAttributesResolver` and `ExtensionAttributesResolver` iterate the EAV
custom-attributes bag and the typed extension-attributes bag respectively
and emit every key by default. A small built-in blocklist
(`password_hash`, `password`, `rp_token`, `rp_token_created_at`,
`confirmation`, `failures_num`, `first_failure`, `lock_expires`) is always
applied as a defense-in-depth backstop — Magento normally keeps these out
of those channels, but the blocklist guards against a misconfigured EAV
attribute or a 3rd-party extension attribute reusing one of those names.

If your project attaches sensitive 3rd-party data through either channel
(fraud_score, vip_tier, internal_notes, store_credit_balance,
2fa_enrollment_flag, …), opt into a **strict allowlist** via `etc/di.xml`:

```xml
<type name="Magebit\McpCustomerTools\Model\FieldResolver\Customer\CustomAttributesResolver">
    <arguments>
        <argument name="allowedKeys" xsi:type="array">
            <item name="0" xsi:type="string">loyalty_tier</item>
            <item name="1" xsi:type="string">loyalty_points</item>
            <item name="2" xsi:type="string">newsletter_opt_in</item>
        </argument>
    </arguments>
</type>

<type name="Magebit\McpCustomerTools\Model\FieldResolver\Customer\ExtensionAttributesResolver">
    <arguments>
        <argument name="allowedKeys" xsi:type="array">
            <item name="0" xsi:type="string">is_subscribed</item>
            <item name="1" xsi:type="string">company_attributes</item>
        </argument>
    </arguments>
</type>
```

When `allowedKeys` is non-empty, the resolver emits ONLY those keys and the
blocklist is irrelevant. When empty (the default), every key not in the
blocklist passes through. To extend the blocklist without switching to a
strict allowlist, override `blockedKeys` instead.

## Add a new filter to `customer.customer.list`

Implement `CustomerFilterTranslatorInterface`:

```php
final class LoyaltyTierFilter implements CustomerFilterTranslatorInterface
{
    public function supports(string $key): bool
    {
        return $key === 'loyalty_tier';
    }

    public function translate(string $key, mixed $value, SearchCriteriaBuilder $builder): void
    {
        if (!is_string($value)) {
            return;
        }
        $builder->addFilter('loyalty_tier', $value);
    }
}
```

Then wire it to `CustomerSearchCriteriaBuilder`'s `filterTranslators` DI
array:

```xml
<type name="Magebit\McpCustomerTools\Model\Search\CustomerSearchCriteriaBuilder">
    <arguments>
        <argument name="filterTranslators" xsi:type="array">
            <item name="loyalty_tier" xsi:type="object">
                Vendor\Loyalty\Mcp\Filter\LoyaltyTierFilter
            </item>
        </argument>
    </arguments>
</type>
```

Translators are consulted in DI order; returning true from `supports()`
signals "I handled this key". If no translator claims a filter key and it
isn't a built-in (`email`, `firstname`, `lastname`, `group_id`,
`website_id`, `store_id`, `created_at_from/_to`, `updated_at_from/_to`,
`dob_from/_to`), the request fails with `INVALID_PARAMS`.

## Address / group translators

`AddressFilterTranslatorInterface` and `GroupFilterTranslatorInterface`
follow the identical two-method contract; wire them against
`AddressSearchCriteriaBuilder` / `GroupSearchCriteriaBuilder`
respectively. Field-resolver analogs are `AddressFieldResolverInterface`
and `GroupFieldResolverInterface`.

## Custom write tools

To add a new write tool (e.g. a "merge two customer accounts" action):

1. Implement `Magebit\Mcp\Api\ToolInterface` and
   `Magebit\Mcp\Api\UnderlyingAclAwareInterface`.
2. Return `WriteMode::WRITE` from `getWriteMode()` and usually
   `true` from `getConfirmationRequired()`.
3. Pick the narrowest matching Magento ACL from
   `vendor/magento/module-customer/etc/acl.xml` — usually
   `Magento_Customer::manage` for edit actions, `Magento_Customer::delete`
   for delete, `Magento_Customer::reset_password` for reset flows.
4. Declare a new `Magebit_McpCustomerTools::tool_*` leaf in `etc/acl.xml`.
5. Register the tool in `etc/di.xml` under
   `Magebit\Mcp\Model\Tool\ToolRegistry`.

The middleware enforces two gates on every call: the MCP tool ACL
(from `getAclResource()`) and the underlying Magento ACL (from
`getUnderlyingAclResource()`). Without the underlying gate, a tool could
let an admin perform actions their role in the admin UI wouldn't permit.
