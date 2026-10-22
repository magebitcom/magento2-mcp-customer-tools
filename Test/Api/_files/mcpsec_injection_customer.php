<?php
/**
 * @author    Magebit <info@magebit.com>
 * @copyright Copyright (c) Magebit, Ltd. (https://magebit.com)
 * @license   MIT
 */
declare(strict_types=1);

/**
 * Fixture: one customer `mcpsec.injection@example.com` whose last name carries the name-safe injection text.
 */

use Magebit\Mcp\Test\Api\Security\InjectionPayload;
use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Customer\Api\Data\CustomerInterfaceFactory;
use Magento\TestFramework\Helper\Bootstrap;

// Remove a leftover from an interrupted run first.
require __DIR__ . '/mcpsec_injection_customer_rollback.php';

$om = Bootstrap::getObjectManager();
/** @var CustomerInterfaceFactory $customerFactory */
$customerFactory = $om->get(CustomerInterfaceFactory::class);
$customer = $customerFactory->create();
$customer->setWebsiteId(1)
    ->setStoreId(1)
    ->setGroupId(1)
    ->setEmail(InjectionPayload::CUSTOMER_EMAIL)
    ->setFirstname('Mcpsec')
    ->setLastname(InjectionPayload::NAME_TEXT);
/** @var CustomerRepositoryInterface $customerRepository */
$customerRepository = $om->get(CustomerRepositoryInterface::class);
$customerRepository->save($customer);
