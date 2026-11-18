<?php
/**
 * @author    Magebit <info@magebit.com>
 * @copyright Copyright (c) Magebit, Ltd. (https://magebit.com)
 * @license   MIT
 */
declare(strict_types=1);

namespace Magebit\McpCustomerTools\Test\Unit\Model\Search;

use Magebit\Mcp\Model\Util\WebsiteStoreResolver;
use Magebit\McpCustomerTools\Api\CustomerFilterTranslatorInterface;
use Magebit\McpCustomerTools\Model\Search\CustomerSearchCriteriaBuilder;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\Api\SearchCriteriaInterface;
use Magento\Framework\Api\SortOrder;
use Magento\Framework\Api\SortOrderBuilder;
use Magento\Framework\Exception\LocalizedException;
use PHPUnit\Framework\TestCase;

class CustomerSearchCriteriaBuilderTest extends TestCase
{
    private SearchCriteriaBuilder $criteriaBuilder;

    private SortOrderBuilder $sortBuilder;

    private SortOrder $sortOrder;

    private WebsiteStoreResolver $websiteStoreResolver;

    protected function setUp(): void
    {
        $this->criteriaBuilder = $this->createCriteriaBuilder();
        $this->sortOrder = $this->createStub(SortOrder::class);
        $this->sortBuilder = $this->createSortBuilder();
        $this->websiteStoreResolver = $this->createStub(WebsiteStoreResolver::class);
    }

    public function testDefaultSortIsCreatedAtDesc(): void
    {
        $this->sortBuilder = $this->createSortBuilder(true);

        $this->sortBuilder->expects($this->once())->method('setField')->with('created_at');
        $this->sortBuilder->expects($this->once())->method('setDirection')->with(SortOrder::SORT_DESC);

        $this->builder()->build([]);
    }

    public function testSortByIdMapsToEntityIdColumn(): void
    {
        $this->sortBuilder = $this->createSortBuilder(true);

        $this->sortBuilder->expects($this->once())->method('setField')->with('entity_id');
        $this->sortBuilder->expects($this->once())->method('setDirection')->with(SortOrder::SORT_DESC);

        $this->builder()->build(['sort_by' => 'id', 'sort_dir' => 'desc']);
    }

    public function testSortByIdAscMapsToEntityIdColumn(): void
    {
        $this->sortBuilder = $this->createSortBuilder(true);

        $this->sortBuilder->expects($this->once())->method('setField')->with('entity_id');
        $this->sortBuilder->expects($this->once())->method('setDirection')->with(SortOrder::SORT_ASC);

        $this->builder()->build(['sort_by' => 'id', 'sort_dir' => 'asc']);
    }

    public function testSortByEmailPassesThroughUnchanged(): void
    {
        $this->sortBuilder = $this->createSortBuilder(true);

        $this->sortBuilder->expects($this->once())->method('setField')->with('email');
        $this->sortBuilder->expects($this->once())->method('setDirection')->with(SortOrder::SORT_ASC);

        $this->builder()->build(['sort_by' => 'email', 'sort_dir' => 'asc']);
    }

    public function testExactEmailAddsEqualsFilter(): void
    {
        $this->criteriaBuilder = $this->createCriteriaBuilder(true);

        $this->criteriaBuilder->expects($this->atLeastOnce())
            ->method('addFilter')
            ->with($this->equalTo('email'), $this->equalTo('alice@example.com'));

        $this->builder()->build(['filters' => ['email' => 'alice@example.com']]);
    }

    public function testGlobEmailAddsLikeFilter(): void
    {
        $this->criteriaBuilder = $this->createCriteriaBuilder(true);

        $calls = [];
        $this->criteriaBuilder->expects($this->atLeastOnce())
            ->method('addFilter')
            ->willReturnCallback(function (string $field, mixed $value, ?string $cond = null) use (&$calls) {
                $calls[] = [$field, $value, $cond];
                return $this->criteriaBuilder;
            });

        $this->builder()->build(['filters' => ['email' => 'alice@*']]);

        $this->assertContains(['email', 'alice@%', 'like'], $calls);
    }

    public function testArrayEmailAddsInFilter(): void
    {
        $this->criteriaBuilder = $this->createCriteriaBuilder(true);

        $this->criteriaBuilder->expects($this->atLeastOnce())
            ->method('addFilter')
            ->with('email', $this->equalTo(['a@x.com', 'b@y.com']), 'in');

        $this->builder()->build(['filters' => ['email' => ['a@x.com', 'b@y.com']]]);
    }

    public function testFirstnameAddsWildcardLikeFilter(): void
    {
        $this->criteriaBuilder = $this->createCriteriaBuilder(true);

        $calls = [];
        $this->criteriaBuilder->expects($this->atLeastOnce())
            ->method('addFilter')
            ->willReturnCallback(function (string $field, mixed $value, ?string $cond = null) use (&$calls) {
                $calls[] = [$field, $value, $cond];
                return $this->criteriaBuilder;
            });

        $this->builder()->build(['filters' => ['firstname' => 'Ali']]);

        $this->assertContains(['firstname', '%Ali%', 'like'], $calls);
    }

    public function testGroupIdScalarEqualsFilter(): void
    {
        $this->criteriaBuilder = $this->createCriteriaBuilder(true);

        $this->criteriaBuilder->expects($this->atLeastOnce())
            ->method('addFilter')
            ->with('group_id', 3);

        $this->builder()->build(['filters' => ['group_id' => 3]]);
    }

    public function testWebsiteIdEqualsFilter(): void
    {
        $this->criteriaBuilder = $this->createCriteriaBuilder(true);

        // `website_id` is a real column on customer — no expansion through
        // the resolver; scalar goes to an equals filter.
        $this->criteriaBuilder->expects($this->atLeastOnce())
            ->method('addFilter')
            ->with('website_id', 1);

        $this->builder()->build(['filters' => ['website_id' => 1]]);
    }

    public function testStoreFromWebsiteIdExpandsViaResolver(): void
    {
        $this->criteriaBuilder = $this->createCriteriaBuilder(true);
        $this->websiteStoreResolver = $this->createMock(WebsiteStoreResolver::class);

        $this->websiteStoreResolver->expects($this->once())
            ->method('resolveStoreIds')
            ->with(2)
            ->willReturn([3, 4]);

        $this->criteriaBuilder->expects($this->atLeastOnce())
            ->method('addFilter')
            ->with('store_id', $this->equalTo([3, 4]), 'in');

        $this->builder()->build(['filters' => ['store_from_website_id' => 2]]);
    }

    public function testCreatedAtRangeSplitsIntoTwoFilters(): void
    {
        $this->criteriaBuilder = $this->createCriteriaBuilder(true);

        $calls = [];
        $this->criteriaBuilder->expects($this->atLeast(2))
            ->method('addFilter')
            ->willReturnCallback(function (string $field, mixed $value, ?string $cond = null) use (&$calls) {
                $calls[] = [$field, $value, $cond];
                return $this->criteriaBuilder;
            });

        $this->builder()->build([
            'filters' => [
                'created_at_from' => '2025-01-01',
                'created_at_to' => '2025-12-31',
            ],
        ]);

        $this->assertContains(['created_at', '2025-01-01', 'gteq'], $calls);
        $this->assertContains(['created_at', '2025-12-31', 'lteq'], $calls);
    }

    public function testPageSizeCapsAtMax(): void
    {
        $this->criteriaBuilder = $this->createCriteriaBuilder(true);

        $this->criteriaBuilder->expects($this->once())
            ->method('setPageSize')
            ->with(CustomerSearchCriteriaBuilder::MAX_PAGE_SIZE);

        $this->builder()->build(['page_size' => 500]);
    }

    public function testPageDefaultsToOne(): void
    {
        $this->criteriaBuilder = $this->createCriteriaBuilder(true);

        $this->criteriaBuilder->expects($this->once())
            ->method('setCurrentPage')
            ->with(1);

        $this->builder()->build([]);
    }

    public function testUnknownFilterThrows(): void
    {
        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessageMatches('/Unknown customer filter: "wat"/');

        $this->builder()->build(['filters' => ['wat' => 'lol']]);
    }

    public function testTranslatorClaimsCustomKey(): void
    {
        $translator = $this->createMock(CustomerFilterTranslatorInterface::class);
        $translator->method('supports')->willReturnCallback(fn(string $k): bool => $k === 'custom_attr');
        $translator->expects($this->once())
            ->method('translate')
            ->with('custom_attr', 'val', $this->criteriaBuilder);

        $sut = new CustomerSearchCriteriaBuilder(
            $this->criteriaBuilder,
            $this->sortBuilder,
            $this->websiteStoreResolver,
            [$translator]
        );

        $sut->build(['filters' => ['custom_attr' => 'val']]);
    }

    public function testInvalidSortFieldThrows(): void
    {
        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessageMatches('/"sort_by" must be one of/');

        $this->builder()->build(['sort_by' => 'not_a_real_field']);
    }

    public function testInvalidSortDirThrows(): void
    {
        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessageMatches('/"sort_dir" must be/');

        $this->builder()->build(['sort_dir' => 'sideways']);
    }

    public function testFiltersMustBeArray(): void
    {
        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessageMatches('/Filter payload must be an object/');

        $this->builder()->build(['filters' => 'email=alice']);
    }

    /**
     * Create a search builder double.
     *
     * @param bool $mock
     * @return ($mock is true ? SearchCriteriaBuilder&\PHPUnit\Framework\MockObject\MockObject : SearchCriteriaBuilder&\PHPUnit\Framework\MockObject\Stub)
     */
    private function createCriteriaBuilder(bool $mock = false): SearchCriteriaBuilder
    {
        $builder = $mock ? $this->createMock(SearchCriteriaBuilder::class) : $this->createStub(SearchCriteriaBuilder::class);
        $builder->method('addFilter')->willReturnSelf();
        $builder->method('addSortOrder')->willReturnSelf();
        $builder->method('setCurrentPage')->willReturnSelf();
        $builder->method('setPageSize')->willReturnSelf();
        $builder->method('create')
            ->willReturn($this->createStub(SearchCriteriaInterface::class));
        return $builder;
    }

    /**
     * Create a search builder double.
     *
     * @param bool $mock
     * @return ($mock is true ? SortOrderBuilder&\PHPUnit\Framework\MockObject\MockObject : SortOrderBuilder&\PHPUnit\Framework\MockObject\Stub)
     */
    private function createSortBuilder(bool $mock = false): SortOrderBuilder
    {
        $builder = $mock ? $this->createMock(SortOrderBuilder::class) : $this->createStub(SortOrderBuilder::class);
        $builder->method('setField')->willReturnSelf();
        $builder->method('setDirection')->willReturnSelf();
        $builder->method('create')->willReturn($this->sortOrder);
        return $builder;
    }

    private function builder(): CustomerSearchCriteriaBuilder
    {
        return new CustomerSearchCriteriaBuilder(
            $this->criteriaBuilder,
            $this->sortBuilder,
            $this->websiteStoreResolver
        );
    }
}
