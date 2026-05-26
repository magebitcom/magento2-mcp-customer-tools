<?php
/**
 * @author    Magebit <info@magebit.com>
 * @copyright Copyright (c) Magebit, Ltd. (https://magebit.com)
 * @license   MIT
 */
declare(strict_types=1);

namespace Magebit\McpCustomerTools\Tool\Customer\Account;

use Magebit\Mcp\Api\ToolInterface;
use Magebit\Mcp\Api\ToolResultInterface;
use Magebit\Mcp\Api\UnderlyingAclAwareInterface;
use Magebit\Mcp\Model\Tool\Schema\Builder\IntegerBuilder;
use Magebit\Mcp\Model\Tool\Schema\Builder\StringBuilder;
use Magebit\Mcp\Model\Tool\Schema\Schema;
use Magebit\Mcp\Model\Tool\ToolResult;
use Magebit\Mcp\Model\Tool\WriteMode;
use Magebit\McpCustomerTools\Model\EntityFinder;
use Magento\Customer\Api\AccountManagementInterface;
use Magento\Framework\Exception\LocalizedException;

/**
 * Returns one of {@see AccountManagementInterface::ACCOUNT_CONFIRMED},
 * `ACCOUNT_CONFIRMATION_REQUIRED`, or `ACCOUNT_CONFIRMATION_NOT_REQUIRED`.
 *
 * Unknown id/email collapses to `ACCOUNT_CONFIRMATION_REQUIRED` so the wire
 * response is indistinguishable from an existing-but-unconfirmed customer —
 * prevents the tool from doubling as a customer-existence oracle. The
 * truthful "customer not found" outcome is preserved in `auditSummary` for
 * operator review.
 */
class ConfirmationStatus implements ToolInterface, UnderlyingAclAwareInterface
{
    public const TOOL_NAME = 'customer.account.confirmation_status';
    public const ACL_RESOURCE = 'Magebit_McpCustomerTools::tool_customer_account_confirmation_status';

    /**
     * @param EntityFinder $entityFinder
     * @param AccountManagementInterface $accountManagement
     */
    public function __construct(
        private readonly EntityFinder $entityFinder,
        private readonly AccountManagementInterface $accountManagement
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
        return 'Get Account Confirmation Status';
    }

    /**
     * @inheritDoc
     */
    public function getDescription(): string
    {
        return 'Check whether a customer account is confirmed, still '
            . 'awaiting confirmation, or whether confirmation is disabled '
            . 'at the store level. Identify by `id` or `email`.';
    }

    /**
     * @inheritDoc
     */
    public function getInputSchema(): array
    {
        return Schema::object()
            ->integer('id', fn (IntegerBuilder $i) => $i->minimum(1))
            ->string('email', fn (StringBuilder $s) => $s->minLength(1)->format('email'))
            ->integer('website_id', fn (IntegerBuilder $i) => $i->minimum(0))
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
        return WriteMode::READ;
    }

    /**
     * @inheritDoc
     */
    public function getConfirmationRequired(): bool
    {
        return false;
    }

    /**
     * @inheritDoc
     */
    public function execute(array $arguments): ToolResultInterface
    {
        try {
            $customer = $this->entityFinder->customerFrom($arguments);
            $customerId = (int) $customer->getId();
            $email = (string) $customer->getEmail();
            $status = $this->accountManagement->getConfirmationStatus($customerId);
            $found = true;
        } catch (LocalizedException) {
            $customerId = 0;
            $email = isset($arguments['email']) && is_string($arguments['email'])
                ? $arguments['email']
                : '';
            $status = AccountManagementInterface::ACCOUNT_CONFIRMATION_REQUIRED;
            $found = false;
        }

        $payload = [
            'id' => $customerId,
            'email' => $email,
            'status' => $status,
        ];

        $json = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        if ($json === false) {
            throw new LocalizedException(__('Failed to encode confirmation status as JSON.'));
        }

        return new ToolResult(
            content: [['type' => 'text', 'text' => $json]],
            auditSummary: [
                'id' => $customerId,
                'status' => $status,
                'customer_found' => $found,
            ]
        );
    }
}
