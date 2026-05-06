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
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class GroupSearchCriteriaBuilderTest extends TestCase
{
    private SearchCriteriaBuilder&MockObject $criteriaBuilder;

    private SortOrderBuilder&MockObject $sortBuilder;

    private SortOrder&MockObject $sortOrder;

    protected function setUp(): void
    {
        $this->criteriaBuilder = $this->createMock(SearchCriteriaBuilder::class);
        $this->sortBuilder = $this->createMock(SortOrderBuilder::class);
        $this->sortOrder = $this->createMock(SortOrder::class);
        $this->sortBuilder->method('setField')->willReturnSelf();
        $this->sortBuilder->method('setDirection')->willReturnSelf();
        $this->sortBuilder->method('create')->willReturn($this->sortOrder);

        $this->criteriaBuilder->method('addFilter')->willReturnSelf();
        $this->criteriaBuilder->method('addSortOrder')->willReturnSelf();
        $this->criteriaBuilder->method('setCurrentPage')->willReturnSelf();
        $this->criteriaBuilder->method('setPageSize')->willReturnSelf();
        $this->criteriaBuilder->method('create')
            ->willReturn($this->createStub(SearchCriteriaInterface::class));
    }

    public function testDefaultSortIsIdAsc(): void
    {
        $this->sortBuilder->expects($this->once())->method('setField')->with('id');
        $this->sortBuilder->expects($this->once())->method('setDirection')->with(SortOrder::SORT_ASC);

        $this->builder()->build([]);
    }

    public function testExactCodeAddsEqualsFilter(): void
    {
        $this->criteriaBuilder->expects($this->atLeastOnce())
            ->method('addFilter')
            ->with($this->equalTo('code'), $this->equalTo('General'));

        $this->builder()->build(['filters' => ['code' => 'General']]);
    }

    public function testGlobCodeAddsLikeFilter(): void
    {
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

    private function builder(): GroupSearchCriteriaBuilder
    {
        return new GroupSearchCriteriaBuilder(
            $this->criteriaBuilder,
            $this->sortBuilder
        );
    }
}
