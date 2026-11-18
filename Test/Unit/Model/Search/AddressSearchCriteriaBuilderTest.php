<?php
/**
 * @author    Magebit <info@magebit.com>
 * @copyright Copyright (c) Magebit, Ltd. (https://magebit.com)
 * @license   MIT
 */
declare(strict_types=1);

namespace Magebit\McpCustomerTools\Test\Unit\Model\Search;

use Magebit\McpCustomerTools\Api\AddressFilterTranslatorInterface;
use Magebit\McpCustomerTools\Model\Search\AddressSearchCriteriaBuilder;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\Api\SearchCriteriaInterface;
use Magento\Framework\Api\SortOrder;
use Magento\Framework\Api\SortOrderBuilder;
use Magento\Framework\Exception\LocalizedException;
use PHPUnit\Framework\TestCase;

class AddressSearchCriteriaBuilderTest extends TestCase
{
    private SearchCriteriaBuilder $criteriaBuilder;

    private SortOrderBuilder $sortBuilder;

    private SortOrder $sortOrder;

    protected function setUp(): void
    {
        $this->criteriaBuilder = $this->createCriteriaBuilder();
        $this->sortOrder = $this->createStub(SortOrder::class);
        $this->sortBuilder = $this->createSortBuilder();
    }

    public function testDefaultSortIsIdAscOnEntityId(): void
    {
        $this->sortBuilder = $this->createSortBuilder(true);

        $this->sortBuilder->expects($this->once())->method('setField')->with('entity_id');
        $this->sortBuilder->expects($this->once())->method('setDirection')->with(SortOrder::SORT_ASC);

        $this->builder()->build([]);
    }

    public function testCustomerIdAddsEqualsFilterOnParentId(): void
    {
        $this->criteriaBuilder = $this->createCriteriaBuilder(true);

        $this->criteriaBuilder->expects($this->atLeastOnce())
            ->method('addFilter')
            ->with('parent_id', 7);

        $this->builder()->build(['filters' => ['customer_id' => 7]]);
    }

    public function testCustomerIdArrayAddsInFilterOnParentId(): void
    {
        $this->criteriaBuilder = $this->createCriteriaBuilder(true);

        $this->criteriaBuilder->expects($this->atLeastOnce())
            ->method('addFilter')
            ->with('parent_id', $this->equalTo([7, 8]), 'in');

        $this->builder()->build(['filters' => ['customer_id' => [7, 8]]]);
    }

    public function testSortByCustomerIdSortsOnParentId(): void
    {
        $this->sortBuilder = $this->createSortBuilder(true);

        $this->sortBuilder->expects($this->once())->method('setField')->with('parent_id');
        $this->sortBuilder->expects($this->once())->method('setDirection')->with(SortOrder::SORT_DESC);

        $this->builder()->build(['sort_by' => 'customer_id', 'sort_dir' => 'desc']);
    }

    public function testSortByCountryIdKeepsTheFieldName(): void
    {
        $this->sortBuilder = $this->createSortBuilder(true);

        $this->sortBuilder->expects($this->once())->method('setField')->with('country_id');

        $this->builder()->build(['sort_by' => 'country_id']);
    }

    public function testCountryIdArrayAddsInFilter(): void
    {
        $this->criteriaBuilder = $this->createCriteriaBuilder(true);

        $this->criteriaBuilder->expects($this->atLeastOnce())
            ->method('addFilter')
            ->with('country_id', $this->equalTo(['US', 'CA']), 'in');

        $this->builder()->build(['filters' => ['country_id' => ['US', 'CA']]]);
    }

    public function testCityAddsWildcardLikeFilter(): void
    {
        $this->criteriaBuilder = $this->createCriteriaBuilder(true);

        $calls = [];
        $this->criteriaBuilder->expects($this->atLeastOnce())
            ->method('addFilter')
            ->willReturnCallback(function (string $field, mixed $value, ?string $cond = null) use (&$calls) {
                $calls[] = [$field, $value, $cond];
                return $this->criteriaBuilder;
            });

        $this->builder()->build(['filters' => ['city' => 'Ber']]);

        $this->assertContains(['city', '%Ber%', 'like'], $calls);
    }

    public function testPageSizeCapsAtMax(): void
    {
        $this->criteriaBuilder = $this->createCriteriaBuilder(true);

        $this->criteriaBuilder->expects($this->once())
            ->method('setPageSize')
            ->with(AddressSearchCriteriaBuilder::MAX_PAGE_SIZE);

        $this->builder()->build(['page_size' => 1000]);
    }

    public function testUnknownFilterThrows(): void
    {
        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessageMatches('/Unknown address filter: "wat"/');

        $this->builder()->build(['filters' => ['wat' => 'lol']]);
    }

    public function testTranslatorClaimsCustomKey(): void
    {
        $translator = $this->createMock(AddressFilterTranslatorInterface::class);
        $translator->method('supports')->willReturnCallback(fn(string $k): bool => $k === 'custom_attr');
        $translator->expects($this->once())
            ->method('translate')
            ->with('custom_attr', 'val', $this->criteriaBuilder);

        $sut = new AddressSearchCriteriaBuilder(
            $this->criteriaBuilder,
            $this->sortBuilder,
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

    private function builder(): AddressSearchCriteriaBuilder
    {
        return new AddressSearchCriteriaBuilder(
            $this->criteriaBuilder,
            $this->sortBuilder
        );
    }
}
