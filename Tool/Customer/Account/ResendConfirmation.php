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
use Magento\Customer\Api\AccountManagementInterface;
use Magento\Framework\Exception\LocalizedException;

/**
 * Magento raises `InvalidTransitionException` when the account is already
 * confirmed; that is allowed to bubble through as a tool error.
 */
class ResendConfirmation implements ToolInterface, UnderlyingAclAwareInterface
{
    public const TOOL_NAME = 'customer.account.resend_confirmation';
    public const ACL_RESOURCE = 'Magebit_McpCustomerTools::tool_customer_account_resend_confirmation';

    /**
     * @param AccountManagementInterface $accountManagement
     */
    public function __construct(
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
        return 'Resend Account Confirmation';
    }

    /**
     * @inheritDoc
     */
    public function getDescription(): string
    {
        return 'Re-send the account-confirmation email for an unconfirmed '
            . 'customer. Fails if the account is already confirmed. '
            . '`website_id` is the scope for the lookup.';
    }

    /**
     * @inheritDoc
     */
    public function getInputSchema(): array
    {
        return Schema::object()
            ->string('email', fn (StringBuilder $s) => $s->minLength(1)->format('email')->required())
            ->integer('website_id', fn (IntegerBuilder $i) => $i
                ->minimum(0)
                ->description('Scope for the email lookup. Omit when '
                    . '`customer/account_share/scope` is global.')
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
        return false;
    }

    /**
     * @inheritDoc
     */
    public function execute(array $arguments): ToolResultInterface
    {
        $email = isset($arguments['email']) && is_string($arguments['email'])
            ? $arguments['email']
            : '';
        $websiteId = isset($arguments['website_id']) && is_numeric($arguments['website_id'])
            ? (int) $arguments['website_id']
            : 0;

        $sent = $this->accountManagement->resendConfirmation($email, $websiteId);

        $payload = [
            'sent' => (bool) $sent,
            'email' => $email,
            'website_id' => $websiteId,
        ];

        $json = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        if ($json === false) {
            throw new LocalizedException(__('Failed to encode resend-confirmation result as JSON.'));
        }

        return new ToolResult(
            content: [['type' => 'text', 'text' => $json]],
            auditSummary: [
                'email' => $email,
                'website_id' => $websiteId,
                'sent' => (bool) $sent,
            ]
        );
    }
}
