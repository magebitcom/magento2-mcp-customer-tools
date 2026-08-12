# Magento2 MCP - Customer Tools

This is a sub-module for the [Magento2 MCP module](https://github.com/magebitcom/magento2-mcp-module)

----

Customer-domain MCP tools for `Magebit_Mcp`. Reads and writes against
customer accounts, addresses, customer groups, and account management
flows (password reset, confirmation).

Each tool is a thin wrapper over a Magento service contract
(`CustomerRepositoryInterface`, `AddressRepositoryInterface`,
`GroupRepositoryInterface`, `AccountManagementInterface`) and composes its
read response from field resolvers that 3rd-party modules can extend.

## Install

```bash
composer require magebitcom/magento2-mcp-customer-tools
bin/magento module:enable Magebit_McpCustomerTools
bin/magento setup:upgrade
bin/magento setup:di:compile
bin/magento cache:flush
```

## Tool catalog

### Read tools

| Tool | What it does |
|---|---|
| `customer.customer.list` | Paginated customer search; filter by email (exact / glob / array), firstname/lastname substring, group_id, website_id, store_id, created_at range, updated_at range, dob range. |
| `customer.customer.get` | Single customer by numeric id or by email (+ optional `website_id` for email lookup in per-website scope). |
| `customer.address.list` | Paginated address search; filter by customer_id, country_id, region_id, postcode, city, telephone. |
| `customer.address.get` | Single customer address by id. |
| `customer.group.list` | Paginated group search; filter by code (exact / glob / array) and tax_class_id. |
| `customer.group.get` | Single customer group by id. |
| `customer.account.confirmation_status` | Returns `account_confirmed`, `account_confirmation_required`, or `account_confirmation_not_required`. |

### Write tools

All writes require the global `magebit_mcp/general/allow_writes` flag **and**
the token's own `allow_writes` flag to be `1`. Destructive operations
additionally set `requires_confirmation` so MCP clients (Claude Desktop,
etc.) prompt before firing.

| Tool | Confirm? | Delegates to | Underlying ACL |
|---|---|---|---|
| `customer.customer.create` | yes | `AccountManagementInterface::createAccount()` | `Magento_Customer::manage` |
| `customer.customer.update` | yes | `CustomerRepositoryInterface::save()` (PATCH) | `Magento_Customer::manage` |
| `customer.customer.delete` | yes | `CustomerRepositoryInterface::delete()` | `Magento_Customer::delete` |
| `customer.address.create` | yes | `AddressRepositoryInterface::save()` | `Magento_Customer::manage` |
| `customer.address.update` | yes | `AddressRepositoryInterface::save()` (PATCH) | `Magento_Customer::manage` |
| `customer.address.delete` | yes | `AddressRepositoryInterface::delete()` | `Magento_Customer::manage` |
| `customer.account.reset_password` | yes | `AccountManagementInterface::initiatePasswordReset()` | `Magento_Customer::reset_password` |
| `customer.account.resend_confirmation` | no | `AccountManagementInterface::resendConfirmation()` | `Magento_Customer::manage` |

Every write tool also implements `Magebit\Mcp\Api\UnderlyingAclAwareInterface`
so the handler blocks calls from admins who wouldn't be allowed to perform
the same action in the admin UI.

## Identity lookups

`customer.customer.get`, `customer.customer.update`, `customer.customer.delete`,
`customer.account.confirmation_status` accept either `id` (numeric primary
key) **or** `email`. Email lookups take an optional `website_id` because
`customer/account_share/scope` may be per-website (the Magento default), in
which case the same address can exist on multiple sites as distinct
accounts.

Address tools are keyed by numeric `id` only — addresses are unique per
row, not per customer+label.

## PII handling

Customer and address records are PII-heavy by design. Every read tool
exposes the `fields` / `exclude` arguments so callers can narrow the
payload:

- `customer.customer.get { fields: ["identity", "scope"] }` — just id /
  email / website / group.
- `customer.customer.get { exclude: ["addresses", "profile"] }` — skip the
  full address book and the dob/gender/taxvat triplet.
- `customer.customer.list` ships with a lean default set (`identity`,
  `scope`, `timestamps`) — `addresses`, `custom_attributes`, and
  `extension_attributes` are omitted from list responses to avoid
  multiplying the payload by the size of each customer's attribute set.

Audit summaries stored in `magebit_mcp_audit_log` contain identifiers only
(id, email, website_id, row counts) — never the full record.

## Extending

See `docs/EXTENDING.md` for:

- adding a new field to any tool response via `CustomerFieldResolverInterface`
  / `AddressFieldResolverInterface` / `GroupFieldResolverInterface`;
- adding a new filter to any list tool via `CustomerFilterTranslatorInterface`
  / `AddressFilterTranslatorInterface` / `GroupFilterTranslatorInterface`;
- the ACL layering rules for custom write tools.

## License

Released under the [MIT License](LICENSE).

---

![Magebit](https://github.com/user-attachments/assets/cdc904ce-e839-40a0-a86f-792f7ab7961f)

Magebit - Full-service e-commerce agency

[magebit.com](https://magebit.com)
