<?php
/**
 * @author    Magebit <info@magebit.com>
 * @copyright Copyright (c) Magebit, Ltd. (https://magebit.com)
 * @license   MIT
 */
declare(strict_types=1);

namespace Magebit\McpCustomerTools\Tool\Customer\Customer;

use Magebit\Mcp\Api\ToolInterface;
use Magebit\Mcp\Api\ToolResultInterface;
use Magebit\Mcp\Api\UnderlyingAclAwareInterface;
use Magebit\Mcp\Model\Tool\Schema\Builder\IntegerBuilder;
use Magebit\Mcp\Model\Tool\Schema\Builder\StringBuilder;
use Magebit\Mcp\Model\Tool\Schema\Schema;
use Magebit\Mcp\Model\Tool\ToolResult;
use Magebit\Mcp\Model\Tool\WriteMode;
use Magebit\McpCustomerTools\Model\EntityFinder;
use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Framework\Exception\LocalizedException;

/**
 * Password changes are NOT handled here — use `customer.account.reset_password`.
 * Addresses are out of scope too: use the `customer.address.*` tools.
 */
class CustomerUpdate implements ToolInterface, UnderlyingAclAwareInterface
{
    public const TOOL_NAME = 'customer.customer.update';
    public const ACL_RESOURCE = 'Magebit_McpCustomerTools::tool_customer_customer_update';

    /**
     * @param EntityFinder $entityFinder
     * @param CustomerRepositoryInterface $customerRepository
     * @param CustomerFieldApplier $fieldApplier
     */
    public function __construct(
        private readonly EntityFinder $entityFinder,
        private readonly CustomerRepositoryInterface $customerRepository,
        private readonly CustomerFieldApplier $fieldApplier
    ) {
    }

    /**
     * @inheritDoc
     */
    public function getName(): string
    {
        return self::TOOL_NAME;
    }

    /**
     * @inheritDoc
     */
    public function getTitle(): string
    {
        return 'Update Customer';
    }

    /**
     * @inheritDoc
     */
    public function getDescription(): string
    {
        return 'Partial update of a customer. Identify by `id` or `email` '
            . '(+ optional `website_id` for email lookup in per-website '
            . 'account scope). Only fields you provide are changed. '
            . 'Use `customer.account.reset_password` to change passwords.';
    }

    /**
     * @inheritDoc
     */
    public function getInputSchema(): array
    {
        return Schema::object()
            ->integer('id', fn (IntegerBuilder $i) => $i->minimum(1))
            ->string('email', fn (StringBuilder $s) => $s->minLength(1)->format('email'))
            ->integer('website_id', fn (IntegerBuilder $i) => $i
                ->minimum(0)
                ->description('Scope for email lookup. Also accepted as a write-through '
                    . 'field when the caller is moving the customer to a new website.')
            )
            ->string('new_email', fn (StringBuilder $s) => $s
                ->minLength(1)
                ->format('email')
                ->description('Rename the email address.')
            )
            ->string('firstname', fn (StringBuilder $s) => $s->minLength(1))
            ->string('lastname', fn (StringBuilder $s) => $s->minLength(1))
            ->string('middlename', fn (StringBuilder $s) => $s)
            ->string('prefix', fn (StringBuilder $s) => $s)
            ->string('suffix', fn (StringBuilder $s) => $s)
            ->string('dob', fn (StringBuilder $s) => $s)
            ->integer('gender', fn (IntegerBuilder $i) => $i)
            ->string('taxvat', fn (StringBuilder $s) => $s)
            ->integer('group_id', fn (IntegerBuilder $i) => $i->minimum(0))
            ->integer('store_id', fn (IntegerBuilder $i) => $i->minimum(0))
            ->string('created_in', fn (StringBuilder $s) => $s)
            ->toArray();
    }

    /**
     * @inheritDoc
     */
    public function getAclResource(): string
    {
        return self::ACL_RESOURCE;
    }

    /**
     * @inheritDoc
     */
    public function getUnderlyingAclResource(): ?string
    {
        return 'Magento_Customer::manage';
    }

    /**
     * @inheritDoc
     */
    public function getWriteMode(): WriteMode
    {
        return WriteMode::WRITE;
    }

    /**
     * @inheritDoc
     */
    public function getConfirmationRequired(): bool
    {
        return true;
    }

    /**
     * @inheritDoc
     */
    public function execute(array $arguments): ToolResultInterface
    {
        $customer = $this->entityFinder->customerFrom($arguments);

        // `email` on input is the lookup key; `new_email` is the write-through.
        $patch = $arguments;
        if (array_key_exists('new_email', $patch)) {
            $patch['email'] = $patch['new_email'];
        } else {
            unset($patch['email']);
        }

        $written = $this->fieldApplier->applyOptional($customer, $patch);

        $saved = $this->customerRepository->save($customer);

        $payload = [
            'id' => (int) $saved->getId(),
            'email' => (string) $saved->getEmail(),
            'website_id' => (int) $saved->getWebsiteId(),
            'group_id' => (int) $saved->getGroupId(),
        ];

        $json = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        if ($json === false) {
            throw new LocalizedException(__('Failed to encode updated customer as JSON.'));
        }

        return new ToolResult(
            content: [['type' => 'text', 'text' => $json]],
            auditSummary: [
                'id' => (int) $saved->getId(),
                'email' => (string) $saved->getEmail(),
                'fields_changed' => $written,
            ]
        );
    }
}
