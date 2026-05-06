<?php
/**
 * @author    Magebit <info@magebit.com>
 * @copyright Copyright (c) Magebit, Ltd. (https://magebit.com)
 * @license   MIT
 */
declare(strict_types=1);

namespace Magebit\McpCustomerTools\Model\Search;

use Magebit\McpCustomerTools\Api\GroupFilterTranslatorInterface;
use Magento\Customer\Api\Data\GroupInterface;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\Api\SearchCriteriaInterface;
use Magento\Framework\Api\SortOrder;
use Magento\Framework\Api\SortOrderBuilder;
use Magento\Framework\Exception\LocalizedException;

/**
 * Unhandled keys throw {@see LocalizedException} so the tool layer reports
 * `INVALID_PARAMS` cleanly; extend recognised keys via
 * {@see GroupFilterTranslatorInterface}.
 */
class GroupSearchCriteriaBuilder
{
    public const MAX_PAGE_SIZE = 100;
    public const DEFAULT_PAGE_SIZE = 25;

    /** @var array<int, string> */
    public const SORTABLE_FIELDS = [
        GroupInterface::ID,
        GroupInterface::CODE,
        GroupInterface::TAX_CLASS_ID,
    ];

    /**
     * @param SearchCriteriaBuilder $criteriaBuilder
     * @param SortOrderBuilder $sortBuilder
     * @param GroupFilterTranslatorInterface[] $filterTranslators
     */
    public function __construct(
        private readonly SearchCriteriaBuilder $criteriaBuilder,
        private readonly SortOrderBuilder $sortBuilder,
        private readonly array $filterTranslators = []
    ) {
    }

    /**
     * @param array $args
     * @phpstan-param array<string, mixed> $args
     * @return SearchCriteriaInterface
     * @throws LocalizedException
     */
    public function build(array $args): SearchCriteriaInterface
    {
        $filtersRaw = $args['filters'] ?? [];
        if (!is_array($filtersRaw)) {
            throw new LocalizedException(__('Filter payload must be an object.'));
        }

        foreach ($filtersRaw as $key => $value) {
            if (!is_string($key) || $key === '') {
                throw new LocalizedException(__('Filter keys must be non-empty strings.'));
            }
            $this->applyFilter($key, $value);
        }

        $this->applySort($args);
        $this->applyPaging($args);

        return $this->criteriaBuilder->create();
    }

    /**
     * @param string $key
     * @param mixed $value
     * @return void
     * @throws LocalizedException
     */
    private function applyFilter(string $key, mixed $value): void
    {
        switch ($key) {
            case GroupInterface::CODE:
                $this->addCodeFilter($value);
                return;

            case GroupInterface::TAX_CLASS_ID:
                $this->addEqualsOrIn(GroupInterface::TAX_CLASS_ID, $value);
                return;
        }

        foreach ($this->filterTranslators as $translator) {
            if ($translator->supports($key)) {
                $translator->translate($key, $value, $this->criteriaBuilder);
                return;
            }
        }

        throw new LocalizedException(__('Unknown group filter: "%1".', $key));
    }

    /**
     * @param mixed $value
     * @return void
     * @throws LocalizedException
     */
    private function addCodeFilter(mixed $value): void
    {
        if (is_array($value)) {
            $this->addEqualsOrIn(GroupInterface::CODE, $value);
            return;
        }
        if (!is_scalar($value) || (string) $value === '') {
            throw new LocalizedException(__('Filter "code" requires a non-empty value.'));
        }
        $str = (string) $value;
        if (str_contains($str, '*') || str_contains($str, '%')) {
            $this->criteriaBuilder->addFilter(
                GroupInterface::CODE,
                str_replace('*', '%', $str),
                'like'
            );
            return;
        }
        $this->criteriaBuilder->addFilter(GroupInterface::CODE, $str);
    }

    /**
     * @param string $field
     * @param mixed $value
     * @return void
     * @throws LocalizedException
     */
    private function addEqualsOrIn(string $field, mixed $value): void
    {
        if (is_array($value)) {
            $list = array_values(array_filter(
                $value,
                static fn($v): bool => is_scalar($v) && (string) $v !== ''
            ));
            if ($list === []) {
                return;
            }
            $this->criteriaBuilder->addFilter($field, $list, 'in');
            return;
        }
        if (!is_scalar($value) || (string) $value === '') {
            throw new LocalizedException(__('Filter "%1" requires a non-empty value.', $field));
        }
        $this->criteriaBuilder->addFilter($field, $value);
    }

    /**
     * @param array $args
     * @phpstan-param array<string, mixed> $args
     * @return void
     * @throws LocalizedException
     */
    private function applySort(array $args): void
    {
        $sortBy = $args['sort_by'] ?? GroupInterface::ID;
        if (!is_string($sortBy) || $sortBy === '') {
            throw new LocalizedException(__('"sort_by" must be a non-empty string.'));
        }
        if (!in_array($sortBy, self::SORTABLE_FIELDS, true)) {
            throw new LocalizedException(__(
                '"sort_by" must be one of: %1.',
                implode(', ', self::SORTABLE_FIELDS)
            ));
        }

        $dirRaw = $args['sort_dir'] ?? 'asc';
        $dir = is_string($dirRaw) ? strtolower($dirRaw) : 'asc';
        if ($dir !== 'asc' && $dir !== 'desc') {
            throw new LocalizedException(__('"sort_dir" must be "asc" or "desc".'));
        }

        $this->sortBuilder->setField($sortBy);
        $this->sortBuilder->setDirection($dir === 'asc' ? SortOrder::SORT_ASC : SortOrder::SORT_DESC);
        $this->criteriaBuilder->addSortOrder($this->sortBuilder->create());
    }

    /**
     * @param array $args
     * @phpstan-param array<string, mixed> $args
     * @return void
     * @throws LocalizedException
     */
    private function applyPaging(array $args): void
    {
        $pageRaw = $args['page'] ?? 1;
        $page = is_numeric($pageRaw) ? max(1, (int) $pageRaw) : 1;

        $sizeRaw = $args['page_size'] ?? self::DEFAULT_PAGE_SIZE;
        if (!is_numeric($sizeRaw)) {
            throw new LocalizedException(__('"page_size" must be numeric.'));
        }
        $size = max(1, (int) $sizeRaw);
        if ($size > self::MAX_PAGE_SIZE) {
            $size = self::MAX_PAGE_SIZE;
        }

        $this->criteriaBuilder->setCurrentPage($page);
        $this->criteriaBuilder->setPageSize($size);
    }
}
