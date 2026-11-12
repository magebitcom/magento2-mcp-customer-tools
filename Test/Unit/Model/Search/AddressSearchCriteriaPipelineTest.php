<?php
/**
 * @author    Magebit <info@magebit.com>
 * @copyright Copyright (c) Magebit, Ltd. (https://magebit.com)
 * @license   MIT
 */
declare(strict_types=1);

namespace Magebit\McpCustomerTools\Test\Unit\Model\Search;

use Magebit\McpCustomerTools\Model\Search\AddressSearchCriteriaBuilder;
use Magento\Customer\Model\ResourceModel\Address\Collection;
use Magento\Eav\Model\Api\SearchCriteria\CollectionProcessor\FilterProcessor;
use Magento\Framework\Api\FilterBuilder;
use Magento\Framework\Api\ObjectFactory;
use Magento\Framework\Api\Search\FilterGroupBuilder;
use Magento\Framework\Api\SearchCriteria\CollectionProcessor\SortingProcessor;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\Api\SortOrderBuilder;
use Magento\Framework\ObjectManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * Runs the built criteria through the processors Magento's AddressRepository uses
 * (EAV filter processor, sorting processor) and checks what reaches the address
 * collection: owner filters and sorts must use the `parent_id` column.
 */
class AddressSearchCriteriaPipelineTest extends TestCase
{
    /** @var array<int, mixed> */
    private array $filters = [];

    /** @var array<int, array{string, string}> */
    private array $orders = [];

    private Collection&MockObject $collection;

    private AddressSearchCriteriaBuilder $builder;

    protected function setUp(): void
    {
        $objectManager = $this->createMock(ObjectManagerInterface::class);
        $objectManager->method('create')->willReturnCallback(
            static function (string $type, array $arguments = []): object {
                return new $type($arguments['data'] ?? []);
            }
        );
        $factory = new ObjectFactory($objectManager);
        $filterBuilder = new FilterBuilder($factory);
        $this->builder = new AddressSearchCriteriaBuilder(
            new SearchCriteriaBuilder($factory, new FilterGroupBuilder($factory, $filterBuilder), $filterBuilder),
            new SortOrderBuilder($factory)
        );

        $this->collection = $this->createMock(Collection::class);
        $this->collection->method('addFieldToFilter')->willReturnCallback(function (mixed $fields) {
            $this->filters[] = $fields;
            return $this->collection;
        });
        $this->collection->method('addOrder')->willReturnCallback(function (string $field, string $dir) {
            $this->orders[] = [$field, $dir];
            return $this->collection;
        });
    }

    public function testCustomerIdFilterReachesTheCollectionAsParentId(): void
    {
        $this->process(['filters' => ['customer_id' => 7]]);

        $this->assertSame([[['attribute' => 'parent_id', 'eq' => 7]]], $this->filters);
    }

    public function testCustomerIdListReachesTheCollectionAsParentIdIn(): void
    {
        $this->process(['filters' => ['customer_id' => [7, 8], 'country_id' => 'LV']]);

        $this->assertSame(
            [
                [['attribute' => 'parent_id', 'in' => [7, 8]]],
                [['attribute' => 'country_id', 'eq' => 'LV']],
            ],
            $this->filters
        );
    }

    public function testSortByCustomerIdAndIdUseTheEntityColumns(): void
    {
        $this->process(['sort_by' => 'customer_id', 'sort_dir' => 'desc']);
        $this->process(['sort_by' => 'id', 'sort_dir' => 'desc']);

        $this->assertSame([['parent_id', 'DESC'], ['entity_id', 'DESC']], $this->orders);
    }

    /**
     * @param array<string, mixed> $args
     * @return void
     */
    private function process(array $args): void
    {
        $criteria = $this->builder->build($args);
        (new FilterProcessor())->process($criteria, $this->collection);
        (new SortingProcessor())->process($criteria, $this->collection);
    }
}
