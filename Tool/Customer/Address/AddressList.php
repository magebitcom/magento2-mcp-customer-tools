<?php
/**
 * @author    Magebit <info@magebit.com>
 * @copyright Copyright (c) Magebit, Ltd. (https://magebit.com)
 * @license   MIT
 */
declare(strict_types=1);

namespace Magebit\McpCustomerTools\Tool\Customer\Address;

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
use Magebit\McpCustomerTools\Api\AddressFieldResolverInterface;
use Magebit\McpCustomerTools\Model\Search\AddressSearchCriteriaBuilder;
use Magento\Customer\Api\AddressRepositoryInterface;
use Magento\Framework\Exception\LocalizedException;

class AddressList implements ToolInterface, UnderlyingAclAwareInterface
{
    public const TOOL_NAME = 'customer.address.list';
    public const ACL_RESOURCE = 'Magebit_McpCustomerTools::tool_customer_address_list';

    /**
     * @param AddressRepositoryInterface $addressRepository
     * @param AddressSearchCriteriaBuilder $searchBuilder
     * @param ResolverPipeline $pipeline
     * @param AddressFieldResolverInterface[] $fieldResolvers
     */
    public function __construct(
        private readonly AddressRepositoryInterface $addressRepository,
        private readonly AddressSearchCriteriaBuilder $searchBuilder,
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
        return 'List Customer Addresses';
    }

    /**
     * @inheritDoc
     */
    public function getDescription(): string
    {
        return 'Search customer addresses with optional filters (customer_id, '
            . 'country_id, region_id, postcode exact/IN, city substring, '
            . 'telephone substring) and paging.';
    }

    /**
     * @inheritDoc
     */
    public function getInputSchema(): array
    {
        return Schema::object()
            ->with(Filters::describing(
                'Filter clauses. Scalar or array values (array ⇒ IN); '
                . 'city/telephone are substring matches.',
                [
                    'customer_id' => ['type' => ['integer', 'array'], 'description' => 'Owning customer id(s).'],
                    'country_id' => ['type' => ['string', 'array'], 'description' => 'ISO country code(s), e.g. "US".'],
                    'region_id' => ['type' => ['integer', 'array'], 'description' => 'Region id(s).'],
                    'postcode' => ['type' => ['string', 'array'], 'description' => 'Postal code(s).'],
                    'city' => ['type' => 'string', 'description' => 'Substring match on city.'],
                    'telephone' => ['type' => 'string', 'description' => 'Substring match on telephone.'],
                ]
            ))
            ->with(Sort::fields(AddressSearchCriteriaBuilder::SORTABLE_FIELDS, 'id', 'asc'))
            ->integer('page', fn (IntegerBuilder $i) => $i->minimum(1))
            ->integer('page_size', fn (IntegerBuilder $i) => $i
                ->minimum(1)
                ->maximum(AddressSearchCriteriaBuilder::MAX_PAGE_SIZE)
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
        $result = $this->addressRepository->getList($criteria);

        $plan = $this->pipeline->plan($this->fieldResolvers, $arguments);

        $rows = [];
        foreach ($result->getItems() as $address) {
            $row = [];
            foreach ($plan as $resolver) {
                $row[$resolver->getKey()] = $resolver->resolve($address, $arguments);
            }
            $rows[] = $row;
        }

        $payload = [
            'items' => $rows,
            'total_count' => (int) $result->getTotalCount(),
            'page_size' => $criteria->getPageSize() ?? AddressSearchCriteriaBuilder::DEFAULT_PAGE_SIZE,
            'current_page' => $criteria->getCurrentPage() ?? 1,
        ];

        $json = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        if ($json === false) {
            throw new LocalizedException(__('Failed to encode address list as JSON.'));
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
