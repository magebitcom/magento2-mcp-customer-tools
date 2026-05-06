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
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class AddressSearchCriteriaBuilderTest extends TestCase
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

    public function testCustomerIdAddsEqualsFilter(): void
    {
        $this->criteriaBuilder->expects($this->atLeastOnce())
            ->method('addFilter')
            ->with('customer_id', 7);

        $this->builder()->build(['filters' => ['customer_id' => 7]]);
    }

    public function testCountryIdArrayAddsInFilter(): void
    {
        $this->criteriaBuilder->expects($this->atLeastOnce())
            ->method('addFilter')
            ->with('country_id', $this->equalTo(['US', 'CA']), 'in');

        $this->builder()->build(['filters' => ['country_id' => ['US', 'CA']]]);
    }

    public function testCityAddsWildcardLikeFilter(): void
    {
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

    private function builder(): AddressSearchCriteriaBuilder
    {
        return new AddressSearchCriteriaBuilder(
            $this->criteriaBuilder,
            $this->sortBuilder
        );
    }
}
