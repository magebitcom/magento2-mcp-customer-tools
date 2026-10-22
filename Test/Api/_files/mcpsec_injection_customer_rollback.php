<?php
/**
 * @author    Magebit <info@magebit.com>
 * @copyright Copyright (c) Magebit, Ltd. (https://magebit.com)
 * @license   MIT
 */
declare(strict_types=1);

/**
 * Rollback: delete only the `mcpsec.injection@example.com` customer.
 */

use Magebit\Mcp\Test\Api\Security\InjectionPayload;
use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Registry;
use Magento\TestFramework\Helper\Bootstrap;

$om = Bootstrap::getObjectManager();
/** @var Registry $registry */
$registry = $om->get(Registry::class);
$registry->unregister('isSecureArea');
$registry->register('isSecureArea', true);

/** @var CustomerRepositoryInterface $repository */
$repository = $om->get(CustomerRepositoryInterface::class);
try {
    $repository->delete($repository->get(InjectionPayload::CUSTOMER_EMAIL, 1));
} catch (NoSuchEntityException) {
    // Nothing to delete.
}

$registry->unregister('isSecureArea');
$registry->register('isSecureArea', false);
