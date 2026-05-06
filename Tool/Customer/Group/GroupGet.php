<?php
/**
 * @author    Magebit <info@magebit.com>
 * @copyright Copyright (c) Magebit, Ltd. (https://magebit.com)
 * @license   MIT
 */
declare(strict_types=1);

namespace Magebit\McpCustomerTools\Tool\Customer\Group;

use Magebit\Mcp\Api\ToolInterface;
use Magebit\Mcp\Api\ToolResultInterface;
use Magebit\Mcp\Model\Tool\Schema\Builder\ArrayBuilder;
use Magebit\Mcp\Model\Tool\Schema\Builder\IntegerBuilder;
use Magebit\Mcp\Model\Tool\Schema\Schema;
use Magebit\Mcp\Model\Tool\ToolResult;
use Magebit\Mcp\Model\Tool\WriteMode;
use Magebit\Mcp\Model\Util\ResolverPipeline;
use Magebit\McpCustomerTools\Api\GroupFieldResolverInterface;
use Magebit\McpCustomerTools\Model\EntityFinder;
use Magento\Framework\Exception\LocalizedException;

class GroupGet implements ToolInterface
{
    public const TOOL_NAME = 'customer.group.get';
    public const ACL_RESOURCE = 'Magebit_McpCustomerTools::tool_customer_group_get';

    /**
     * @param EntityFinder $entityFinder
     * @param ResolverPipeline $pipeline
     * @param GroupFieldResolverInterface[] $fieldResolvers
     */
    public function __construct(
        private readonly EntityFinder $entityFinder,
        private readonly ResolverPipeline $pipeline,
        private readonly array $fieldResolvers = []
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
        return 'Get Customer Group';
    }

    /**
     * @inheritDoc
     */
    public function getDescription(): string
    {
        return 'Fetch a single customer group by numeric id. Group `0` is '
            . 'the reserved "NOT LOGGED IN" group.';
    }

    /**
     * @inheritDoc
     */
    public function getInputSchema(): array
    {
        return Schema::object()
            ->integer('id', fn (IntegerBuilder $i) => $i->minimum(0)->required())
            ->array('fields', fn (ArrayBuilder $a) => $a->ofStrings()
                ->description('Whitelist of resolver keys to include — '
                    . 'overrides defaults.')
            )
            ->array('exclude', fn (ArrayBuilder $a) => $a->ofStrings()
                ->description('Resolver keys to omit from the response.')
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
        $group = $this->entityFinder->groupFrom($arguments);

        $response = [];
        foreach ($this->pipeline->plan($this->fieldResolvers, $arguments) as $resolver) {
            $response[$resolver->getKey()] = $resolver->resolve($group, $arguments);
        }

        $json = json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        if ($json === false) {
            throw new LocalizedException(__('Failed to encode group payload as JSON.'));
        }

        return new ToolResult(
            content: [['type' => 'text', 'text' => $json]],
            auditSummary: [
                'id' => $group->getId() !== null ? (int) $group->getId() : null,
                'code' => (string) $group->getCode(),
            ]
        );
    }
}
