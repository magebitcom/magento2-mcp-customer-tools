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
 * Cascades to addresses, carts, wishlists, and order-customer links;
 * {@see self::getConfirmationRequired()} returns true so the MCP client
 * prompts before executing.
 */
class CustomerDelete implements ToolInterface, UnderlyingAclAwareInterface
{
    public const TOOL_NAME = 'customer.customer.delete';
    public const ACL_RESOURCE = 'Magebit_McpCustomerTools::tool_customer_customer_delete';

    /**
     * @param EntityFinder $entityFinder
     * @param CustomerRepositoryInterface $customerRepository
     */
    public function __construct(
        private readonly EntityFinder $entityFinder,
        private readonly CustomerRepositoryInterface $customerRepository
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
        return 'Delete Customer';
    }

    /**
     * @inheritDoc
     */
    public function getDescription(): string
    {
        return 'Permanently delete a customer account. Identify by `id` or '
            . '`email` (+ optional `website_id` for email lookup).';
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
        return 'Magento_Customer::delete';
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
        $customerId = (int) $customer->getId();
        $email = (string) $customer->getEmail();

        $deleted = $this->customerRepository->delete($customer);

        $payload = [
            'deleted' => (bool) $deleted,
            'id' => $customerId,
            'email' => $email,
        ];

        $json = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        if ($json === false) {
            throw new LocalizedException(__('Failed to encode delete result as JSON.'));
        }

        return new ToolResult(
            content: [['type' => 'text', 'text' => $json]],
            auditSummary: [
                'id' => $customerId,
                'email' => $email,
                'deleted' => (bool) $deleted,
            ]
        );
    }
}
