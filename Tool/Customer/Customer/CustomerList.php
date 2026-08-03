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
use Magebit\Mcp\Model\Tool\Schema\Builder\ArrayBuilder;
use Magebit\Mcp\Model\Tool\Schema\Builder\IntegerBuilder;
use Magebit\Mcp\Model\Tool\Schema\Preset\Filters;
use Magebit\Mcp\Model\Tool\Schema\Preset\Sort;
use Magebit\Mcp\Model\Tool\Schema\Schema;
use Magebit\Mcp\Model\Tool\ToolResult;
use Magebit\Mcp\Model\Tool\WriteMode;
use Magebit\Mcp\Model\Util\ResolverPipeline;
use Magebit\McpCustomerTools\Api\CustomerFieldResolverInterface;
use Magebit\McpCustomerTools\Model\Search\CustomerSearchCriteriaBuilder;
use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Framework\Exception\LocalizedException;

/**
 * Registered with a narrower resolver set than `customer.customer.get` —
 * addresses are too heavy to return on every row of a paged response.
 */
class CustomerList implements ToolInterface, UnderlyingAclAwareInterface
{
    public const TOOL_NAME = 'customer.customer.list';
    public const ACL_RESOURCE = 'Magebit_McpCustomerTools::tool_customer_customer_list';

    /**
     * @param CustomerRepositoryInterface $customerRepository
     * @param CustomerSearchCriteriaBuilder $searchBuilder
     * @param ResolverPipeline $pipeline
     * @param CustomerFieldResolverInterface[] $fieldResolvers
     */
    public function __construct(
        private readonly CustomerRepositoryInterface $customerRepository,
        private readonly CustomerSearchCriteriaBuilder $searchBuilder,
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
        return 'List Customers';
    }

    /**
     * @inheritDoc
     */
    public function getDescription(): string
    {
        return 'Search customers with optional filters (email exact/glob/IN, '
            . 'firstname/lastname substring, group_id, website_id, store_id, '
            . 'created_at range, updated_at range, dob range) and paging. '
            . 'Each row is composed from the same field resolvers as '
            . '`customer.customer.get` with a narrower default set '
            . '(addresses omitted); use `fields`/`exclude` to adjust.';
    }

    /**
     * @inheritDoc
     */
    public function getInputSchema(): array
    {
        return Schema::object()
            ->with(Filters::describing(
                'Filter clauses. Scalar or array (array ⇒ IN). `email` accepts '
                . '`*glob*` for wildcard matches. `store_from_website_id` is an '
                . 'escape hatch that expands a website id to its store-view ids.',
                [
                    'email' => ['type' => ['string', 'array'], 'description' => 'Email: exact, `*glob*` wildcard, or array ⇒ IN.'],
                    'firstname' => ['type' => 'string', 'description' => 'Substring match on first name.'],
                    'lastname' => ['type' => 'string', 'description' => 'Substring match on last name.'],
                    'group_id' => ['type' => ['integer', 'array'], 'description' => 'Customer group id(s).'],
                    'store_id' => ['type' => ['integer', 'array'], 'description' => 'Store view id(s) the account is bound to.'],
                    'website_id' => ['type' => ['integer', 'array'], 'description' => 'Website id(s) (matched on the customer row).'],
                    'store_from_website_id' => [
                        'type' => ['integer', 'array'],
                        'description' => 'Website id(s) expanded to their store-view ids, then matched on store_id.',
                    ],
                    'created_at_from' => ['type' => 'string', 'description' => 'Created ISO date/datetime lower bound.'],
                    'created_at_to' => ['type' => 'string', 'description' => 'Created ISO date/datetime upper bound.'],
                    'updated_at_from' => ['type' => 'string', 'description' => 'Updated ISO date/datetime lower bound.'],
                    'updated_at_to' => ['type' => 'string', 'description' => 'Updated ISO date/datetime upper bound.'],
                    'dob_from' => ['type' => 'string', 'description' => 'Date-of-birth lower bound (ISO date).'],
                    'dob_to' => ['type' => 'string', 'description' => 'Date-of-birth upper bound (ISO date).'],
                ]
            ))
            ->with(Sort::fields(CustomerSearchCriteriaBuilder::SORTABLE_FIELDS))
            ->integer('page', fn (IntegerBuilder $i) => $i->minimum(1))
            ->integer('page_size', fn (IntegerBuilder $i) => $i
                ->minimum(1)
                ->maximum(CustomerSearchCriteriaBuilder::MAX_PAGE_SIZE)
            )
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
        $criteria = $this->searchBuilder->build($arguments);
        $result = $this->customerRepository->getList($criteria);

        $plan = $this->pipeline->plan($this->fieldResolvers, $arguments);

        $rows = [];
        foreach ($result->getItems() as $customer) {
            $row = [];
            foreach ($plan as $resolver) {
                $row[$resolver->getKey()] = $resolver->resolve($customer, $arguments);
            }
            $rows[] = $row;
        }

        $payload = [
            'items' => $rows,
            'total_count' => (int) $result->getTotalCount(),
            'page_size' => $criteria->getPageSize() ?? CustomerSearchCriteriaBuilder::DEFAULT_PAGE_SIZE,
            'current_page' => $criteria->getCurrentPage() ?? 1,
        ];

        $json = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        if ($json === false) {
            throw new LocalizedException(__('Failed to encode customer list as JSON.'));
        }

        return new ToolResult(
            content: [['type' => 'text', 'text' => $json]],
            auditSummary: [
                'row_count' => count($rows),
                'total_count' => (int) $result->getTotalCount(),
                'page' => $criteria->getCurrentPage(),
                'page_size' => $criteria->getPageSize(),
            ]
        );
    }
}
