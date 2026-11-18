<?php
/**
 * @author    Magebit <info@magebit.com>
 * @copyright Copyright (c) Magebit, Ltd. (https://magebit.com)
 * @license   MIT
 */
declare(strict_types=1);

namespace Magebit\McpCustomerTools\Test\Unit\Model\Search;

use Magebit\McpCustomerTools\Api\GroupFilterTranslatorInterface;
use Magebit\McpCustomerTools\Model\Search\GroupSearchCriteriaBuilder;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\Api\SearchCriteriaInterface;
use Magento\Framework\Api\SortOrder;
use Magento\Framework\Api\SortOrderBuilder;
use Magento\Framework\Exception\LocalizedException;
use PHPUnit\Framework\TestCase;

class GroupSearchCriteriaBuilderTest extends TestCase
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

    public function testDefaultSortIsIdAsc(): void
    {
        $this->sortBuilder = $this->createSortBuilder(true);

        $this->sortBuilder->expects($this->once())->method('setField')->with('id');
        $this->sortBuilder->expects($this->once())->method('setDirection')->with(SortOrder::SORT_ASC);

        $this->builder()->build([]);
    }

    public function testExactCodeAddsEqualsFilter(): void
    {
        $this->criteriaBuilder = $this->createCriteriaBuilder(true);

        $this->criteriaBuilder->expects($this->atLeastOnce())
            ->method('addFilter')
            ->with($this->equalTo('code'), $this->equalTo('General'));

        $this->builder()->build(['filters' => ['code' => 'General']]);
    }

    public function testGlobCodeAddsLikeFilter(): void
    {
        $this->criteriaBuilder = $this->createCriteriaBuilder(true);

        $calls = [];
        $this->criteriaBuilder->expects($this->atLeastOnce())
            ->method('addFilter')
            ->willReturnCallback(function (string $field, mixed $value, ?string $cond = null) use (&$calls) {
                $calls[] = [$field, $value, $cond];
                return $this->criteriaBuilder;
            });

        $this->builder()->build(['filters' => ['code' => 'Whole*']]);

        $this->assertContains(['code', 'Whole%', 'like'], $calls);
    }

    public function testTaxClassIdArrayAddsInFilter(): void
    {
        $this->criteriaBuilder = $this->createCriteriaBuilder(true);

        $this->criteriaBuilder->expects($this->atLeastOnce())
            ->method('addFilter')
            ->with('tax_class_id', $this->equalTo([2, 5]), 'in');

        $this->builder()->build(['filters' => ['tax_class_id' => [2, 5]]]);
    }

    public function testUnknownFilterThrows(): void
    {
        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessageMatches('/Unknown group filter: "wat"/');

        $this->builder()->build(['filters' => ['wat' => 'lol']]);
    }

    public function testTranslatorClaimsCustomKey(): void
    {
        $translator = $this->createMock(GroupFilterTranslatorInterface::class);
        $translator->method('supports')->willReturnCallback(fn(string $k): bool => $k === 'custom_attr');
        $translator->expects($this->once())
            ->method('translate')
            ->with('custom_attr', 'val', $this->criteriaBuilder);

        $sut = new GroupSearchCriteriaBuilder(
            $this->criteriaBuilder,
            $this->sortBuilder,
            [$translator]
        );

        $sut->build(['filters' => ['custom_attr' => 'val']]);
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

    private function builder(): GroupSearchCriteriaBuilder
    {
        return new GroupSearchCriteriaBuilder(
            $this->criteriaBuilder,
            $this->sortBuilder
        );
    }
}
