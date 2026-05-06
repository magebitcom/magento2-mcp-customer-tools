<?php
/**
 * @author    Magebit <info@magebit.com>
 * @copyright Copyright (c) Magebit, Ltd. (https://magebit.com)
 * @license   MIT
 */
declare(strict_types=1);

namespace Magebit\McpCustomerTools\Api;

use Magento\Framework\Api\SearchCriteriaBuilder;

/**
 * Translators are consulted in DI order; the first to return true from
 * `supports()` claims the key and shadows the built-in handler. Unclaimed
 * non-built-in keys fail with `INVALID_PARAMS`.
 */
interface CustomerFilterTranslatorInterface
{
    /**
     * @param string $key
     * @return bool
     */
    public function supports(string $key): bool;

    /**
     * @param string $key
     * @param mixed $value
     * @param SearchCriteriaBuilder $builder
     * @return void
     */
    public function translate(string $key, mixed $value, SearchCriteriaBuilder $builder): void;
}
