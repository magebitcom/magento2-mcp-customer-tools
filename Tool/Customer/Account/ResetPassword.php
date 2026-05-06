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
use Magento\Customer\Model\AccountManagement;
use Magento\Framework\Exception\LocalizedException;

/**
 * Initiates the same flow the storefront "Forgot your password?" link uses
 * — sends a reset email; the actual password change happens when the
 * customer clicks the link.
 */
class ResetPassword implements ToolInterface, UnderlyingAclAwareInterface
{
    public const TOOL_NAME = 'customer.account.reset_password';
    public const ACL_RESOURCE = 'Magebit_McpCustomerTools::tool_customer_account_reset_password';

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
        return 'Initiate Password Reset';
    }

    /**
     * @inheritDoc
     */
    public function getDescription(): string
    {
        return 'Send a password-reset email to the customer at `email`. '
            . '`website_id` scopes the lookup (required when '
            . '`customer/account_share/scope` is per-website and the same '
            . 'email lives on multiple sites).';
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
        return 'Magento_Customer::reset_password';
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
        $email = isset($arguments['email']) && is_string($arguments['email'])
            ? $arguments['email']
            : '';
        $websiteId = isset($arguments['website_id']) && is_numeric($arguments['website_id'])
            ? (int) $arguments['website_id']
            : null;

        $initiated = $this->accountManagement->initiatePasswordReset(
            $email,
            AccountManagement::EMAIL_RESET,
            $websiteId
        );

        $payload = [
            'initiated' => (bool) $initiated,
            'email' => $email,
            'website_id' => $websiteId,
        ];

        $json = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        if ($json === false) {
            throw new LocalizedException(__('Failed to encode reset-password result as JSON.'));
        }

        return new ToolResult(
            content: [['type' => 'text', 'text' => $json]],
            auditSummary: [
                'email' => $email,
                'website_id' => $websiteId,
                'initiated' => (bool) $initiated,
            ]
        );
    }
}
