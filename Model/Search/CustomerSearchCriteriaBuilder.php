<?php
/**
 * @author    Magebit <info@magebit.com>
 * @copyright Copyright (c) Magebit, Ltd. (https://magebit.com)
 * @license   MIT
 */
declare(strict_types=1);

namespace Magebit\McpCustomerTools\Model\Search;

use Magebit\Mcp\Model\Util\WebsiteStoreResolver;
use Magebit\McpCustomerTools\Api\CustomerFilterTranslatorInterface;
use Magento\Customer\Api\Data\CustomerInterface;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\Api\SearchCriteriaInterface;
use Magento\Framework\Api\SortOrder;
use Magento\Framework\Api\SortOrderBuilder;
use Magento\Framework\Exception\LocalizedException;

/**
 * Unhandled keys throw {@see LocalizedException} so the tool layer reports
 * `INVALID_PARAMS` cleanly; extend recognised keys via
 * {@see CustomerFilterTranslatorInterface}.
 */
class CustomerSearchCriteriaBuilder
{
    public const MAX_PAGE_SIZE = 100;
    public const DEFAULT_PAGE_SIZE = 25;

    /** @var array<int, string> Ordered for default sort preference. */
    public const SORTABLE_FIELDS = [
        CustomerInterface::CREATED_AT,
        CustomerInterface::UPDATED_AT,
        CustomerInterface::ID,
        CustomerInterface::EMAIL,
        CustomerInterface::FIRSTNAME,
        CustomerInterface::LASTNAME,
    ];

    /**
     * @param SearchCriteriaBuilder $criteriaBuilder
     * @param SortOrderBuilder $sortBuilder
     * @param WebsiteStoreResolver $websiteStoreResolver
     * @param CustomerFilterTranslatorInterface[] $filterTranslators
     */
    public function __construct(
        private readonly SearchCriteriaBuilder $criteriaBuilder,
        private readonly SortOrderBuilder $sortBuilder,
        private readonly WebsiteStoreResolver $websiteStoreResolver,
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
            case CustomerInterface::EMAIL:
                $this->addEmailFilter($value);
                return;

            case CustomerInterface::FIRSTNAME:
            case CustomerInterface::LASTNAME:
                $this->addLikeFilter($key, $value);
                return;

            case CustomerInterface::GROUP_ID:
            case CustomerInterface::STORE_ID:
                $this->addEqualsOrIn($key, $value);
                return;

            case CustomerInterface::WEBSITE_ID:
                // Customer row stores `website_id` directly — no
                // WebsiteStoreResolver expansion needed.
                $this->addEqualsOrIn(CustomerInterface::WEBSITE_ID, $value);
                return;

            case 'store_from_website_id':
                // Escape hatch for callers that want the website → store-id
                // expansion anyway (e.g. to cross-match order store_ids).
                $storeIds = $this->websiteStoreResolver->resolveStoreIds($value);
                if ($storeIds === []) {
                    $this->criteriaBuilder->addFilter(CustomerInterface::STORE_ID, 0);
                    return;
                }
                $this->criteriaBuilder->addFilter(CustomerInterface::STORE_ID, $storeIds, 'in');
                return;

            case 'created_at_from':
                $this->addRangeBoundary(CustomerInterface::CREATED_AT, 'gteq', $value);
                return;
            case 'created_at_to':
                $this->addRangeBoundary(CustomerInterface::CREATED_AT, 'lteq', $value);
                return;

            case 'updated_at_from':
                $this->addRangeBoundary(CustomerInterface::UPDATED_AT, 'gteq', $value);
                return;
            case 'updated_at_to':
                $this->addRangeBoundary(CustomerInterface::UPDATED_AT, 'lteq', $value);
                return;

            case 'dob_from':
                $this->addRangeBoundary(CustomerInterface::DOB, 'gteq', $value);
                return;
            case 'dob_to':
                $this->addRangeBoundary(CustomerInterface::DOB, 'lteq', $value);
                return;
        }

        foreach ($this->filterTranslators as $translator) {
            if ($translator->supports($key)) {
                $translator->translate($key, $value, $this->criteriaBuilder);
                return;
            }
        }

        throw new LocalizedException(__('Unknown customer filter: "%1".', $key));
    }

    /**
     * @param mixed $value
     * @return void
     * @throws LocalizedException
     */
    private function addEmailFilter(mixed $value): void
    {
        if (is_array($value)) {
            $this->addEqualsOrIn(CustomerInterface::EMAIL, $value);
            return;
        }
        if (!is_scalar($value) || (string) $value === '') {
            throw new LocalizedException(__('Filter "email" requires a non-empty value.'));
        }
        $str = (string) $value;
        if (str_contains($str, '*') || str_contains($str, '%')) {
            $this->criteriaBuilder->addFilter(
                CustomerInterface::EMAIL,
                str_replace('*', '%', $str),
                'like'
            );
            return;
        }
        $this->criteriaBuilder->addFilter(CustomerInterface::EMAIL, $str);
    }

    /**
     * @param string $field
     * @param mixed $value
     * @return void
     * @throws LocalizedException
     */
    private function addLikeFilter(string $field, mixed $value): void
    {
        if (!is_scalar($value) || (string) $value === '') {
            throw new LocalizedException(__('Filter "%1" requires a non-empty value.', $field));
        }
        $this->criteriaBuilder->addFilter($field, '%' . (string) $value . '%', 'like');
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
     * @param string $field
     * @param string $condition
     * @param mixed $value
     * @return void
     * @throws LocalizedException
     */
    private function addRangeBoundary(string $field, string $condition, mixed $value): void
    {
        if (!is_scalar($value) || (string) $value === '') {
            throw new LocalizedException(__('Range boundary for "%1" must be scalar.', $field));
        }
        $this->criteriaBuilder->addFilter($field, (string) $value, $condition);
    }

    /**
     * Defaults to `created_at DESC` — newest signups first, the usual
     * operator default for a customer list.
     *
     * @param array $args
     * @phpstan-param array<string, mixed> $args
     * @return void
     * @throws LocalizedException
     */
    private function applySort(array $args): void
    {
        $sortBy = $args['sort_by'] ?? CustomerInterface::CREATED_AT;
        if (!is_string($sortBy) || $sortBy === '') {
            throw new LocalizedException(__('"sort_by" must be a non-empty string.'));
        }
        if (!in_array($sortBy, self::SORTABLE_FIELDS, true)) {
            throw new LocalizedException(__(
                '"sort_by" must be one of: %1.',
                implode(', ', self::SORTABLE_FIELDS)
            ));
        }

        $dirRaw = $args['sort_dir'] ?? 'desc';
        $dir = is_string($dirRaw) ? strtolower($dirRaw) : 'desc';
        if ($dir !== 'asc' && $dir !== 'desc') {
            throw new LocalizedException(__('"sort_dir" must be "asc" or "desc".'));
        }

        $column = $sortBy === CustomerInterface::ID ? 'entity_id' : $sortBy;
        $this->sortBuilder->setField($column);
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
