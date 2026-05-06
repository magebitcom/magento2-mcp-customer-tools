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
use Magento\Customer\Api\AccountManagementInterface;
use Magento\Customer\Api\Data\CustomerInterfaceFactory;
use Magento\Framework\Exception\LocalizedException;

/**
 * Wraps {@see AccountManagementInterface::createAccount()}, NOT
 * `CustomerRepositoryInterface::save()` — only the former runs the
 * validation, password hashing, welcome-email, and group-resolution flows
 * that the admin UI uses. Don't switch to `save()`.
 */
class CustomerCreate implements ToolInterface, UnderlyingAclAwareInterface
{
    public const TOOL_NAME = 'customer.customer.create';
    public const ACL_RESOURCE = 'Magebit_McpCustomerTools::tool_customer_customer_create';

    /**
     * @param AccountManagementInterface $accountManagement
     * @param CustomerInterfaceFactory $customerFactory
     * @param CustomerFieldApplier $fieldApplier
     */
    public function __construct(
        private readonly AccountManagementInterface $accountManagement,
        private readonly CustomerInterfaceFactory $customerFactory,
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
        return 'Create Customer';
    }

    /**
     * @inheritDoc
     */
    public function getDescription(): string
    {
        return 'Create a new customer account. `email`, `firstname`, and '
            . '`lastname` are required. If `password` is omitted, Magento '
            . 'generates one and the caller should initiate a password-reset '
            . 'flow. `website_id` picks the account-scope website — if '
            . 'omitted, the default website is used.';
    }

    /**
     * @inheritDoc
     */
    public function getInputSchema(): array
    {
        return Schema::object()
            ->string('email', fn (StringBuilder $s) => $s->minLength(1)->format('email')->required())
            ->string('firstname', fn (StringBuilder $s) => $s->minLength(1)->required())
            ->string('lastname', fn (StringBuilder $s) => $s->minLength(1)->required())
            ->string('middlename', fn (StringBuilder $s) => $s)
            ->string('prefix', fn (StringBuilder $s) => $s)
            ->string('suffix', fn (StringBuilder $s) => $s)
            ->string('dob', fn (StringBuilder $s) => $s->description('YYYY-MM-DD date of birth.'))
            ->integer('gender', fn (IntegerBuilder $i) => $i
                ->description('1 = Male, 2 = Female, 3 = Not Specified.')
            )
            ->string('taxvat', fn (StringBuilder $s) => $s)
            ->integer('group_id', fn (IntegerBuilder $i) => $i->minimum(0))
            ->integer('website_id', fn (IntegerBuilder $i) => $i->minimum(1))
            ->integer('store_id', fn (IntegerBuilder $i) => $i->minimum(0))
            ->string('created_in', fn (StringBuilder $s) => $s)
            ->string('password', fn (StringBuilder $s) => $s
                ->minLength(1)
                ->description('Plain-text password. Omit to let Magento generate one.')
            )
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
        $customer = $this->customerFactory->create();
        $this->fieldApplier->applyOptional($customer, $arguments);

        $password = null;
        if (array_key_exists('password', $arguments) && is_string($arguments['password'])) {
            $password = $arguments['password'];
        }

        $created = $this->accountManagement->createAccount($customer, $password);

        $payload = [
            'id' => (int) $created->getId(),
            'email' => (string) $created->getEmail(),
            'website_id' => (int) $created->getWebsiteId(),
            'group_id' => (int) $created->getGroupId(),
        ];

        $json = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        if ($json === false) {
            throw new LocalizedException(__('Failed to encode created customer as JSON.'));
        }

        return new ToolResult(
            content: [['type' => 'text', 'text' => $json]],
            auditSummary: [
                'id' => (int) $created->getId(),
                'email' => (string) $created->getEmail(),
                'website_id' => (int) $created->getWebsiteId(),
                'password_supplied' => $password !== null,
            ]
        );
    }
}
