<?php
/**
 * @author    Magebit <info@magebit.com>
 * @copyright Copyright (c) Magebit, Ltd. (https://magebit.com)
 * @license   MIT
 */
declare(strict_types=1);

namespace Magebit\McpCustomerTools\Tool\Customer\Address;

use Magebit\Mcp\Api\ToolInterface;
use Magebit\Mcp\Api\ToolResultInterface;
use Magebit\Mcp\Api\UnderlyingAclAwareInterface;
use Magebit\Mcp\Model\Tool\Schema\Builder\ArrayBuilder;
use Magebit\Mcp\Model\Tool\Schema\Builder\BooleanBuilder;
use Magebit\Mcp\Model\Tool\Schema\Builder\IntegerBuilder;
use Magebit\Mcp\Model\Tool\Schema\Builder\StringBuilder;
use Magebit\Mcp\Model\Tool\Schema\Schema;
use Magebit\Mcp\Model\Tool\ToolResult;
use Magebit\Mcp\Model\Tool\WriteMode;
use Magebit\McpCustomerTools\Model\EntityFinder;
use Magento\Customer\Api\AddressRepositoryInterface;
use Magento\Framework\Exception\LocalizedException;

class AddressUpdate implements ToolInterface, UnderlyingAclAwareInterface
{
    public const TOOL_NAME = 'customer.address.update';
    public const ACL_RESOURCE = 'Magebit_McpCustomerTools::tool_customer_address_update';

    /**
     * @param EntityFinder $entityFinder
     * @param AddressRepositoryInterface $addressRepository
     * @param AddressFieldApplier $fieldApplier
     */
    public function __construct(
        private readonly EntityFinder $entityFinder,
        private readonly AddressRepositoryInterface $addressRepository,
        private readonly AddressFieldApplier $fieldApplier
    ) {
    }

    /**
     * @inheritDoc
     */
    public function getName(): string
    {
        return self::TOOL_NAME;
    }

    /**
     * @inheritDoc
     */
    public function getTitle(): string
    {
        return 'Update Customer Address';
    }

    /**
     * @inheritDoc
     */
    public function getDescription(): string
    {
        return 'Partial update of a customer address. Identify by `id`. Only '
            . 'fields you provide are changed.';
    }

    /**
     * @inheritDoc
     */
    public function getInputSchema(): array
    {
        return Schema::object()
            ->integer('id', fn (IntegerBuilder $i) => $i->minimum(1)->required())
            ->integer('customer_id', fn (IntegerBuilder $i) => $i->minimum(1))
            ->string('firstname', fn (StringBuilder $s) => $s->minLength(1))
            ->string('lastname', fn (StringBuilder $s) => $s->minLength(1))
            ->string('middlename', fn (StringBuilder $s) => $s)
            ->string('prefix', fn (StringBuilder $s) => $s)
            ->string('suffix', fn (StringBuilder $s) => $s)
            ->string('company', fn (StringBuilder $s) => $s)
            ->array('street', fn (ArrayBuilder $a) => $a
                ->ofStrings()
                ->minItems(1)
                ->description('Street lines as an array of strings, '
                    . 'one line per element.')
            )
            ->string('city', fn (StringBuilder $s) => $s->minLength(1))
            ->string('postcode', fn (StringBuilder $s) => $s->minLength(1))
            ->string('country_id', fn (StringBuilder $s) => $s->minLength(2)->maxLength(2))
            ->integer('region_id', fn (IntegerBuilder $i) => $i->minimum(1))
            ->string('region', fn (StringBuilder $s) => $s)
            ->string('telephone', fn (StringBuilder $s) => $s)
            ->string('fax', fn (StringBuilder $s) => $s)
            ->string('vat_id', fn (StringBuilder $s) => $s)
            ->boolean('default_billing', fn (BooleanBuilder $b) => $b)
            ->boolean('default_shipping', fn (BooleanBuilder $b) => $b)
            ->toArray();
    }

    /**
     * @inheritDoc
     */
    public function getAclResource(): string
    {
        return self::ACL_RESOURCE;
    }

    /**
     * @inheritDoc
     */
    public function getUnderlyingAclResource(): ?string
    {
        return 'Magento_Customer::manage';
    }

    /**
     * @inheritDoc
     */
    public function getWriteMode(): WriteMode
    {
        return WriteMode::WRITE;
    }

    /**
     * @inheritDoc
     */
    public function getConfirmationRequired(): bool
    {
        return true;
    }

    /**
     * @inheritDoc
     */
    public function execute(array $arguments): ToolResultInterface
    {
        $address = $this->entityFinder->addressFrom($arguments);
        $written = $this->fieldApplier->applyOptional($address, $arguments);

        $saved = $this->addressRepository->save($address);

        $payload = [
            'id' => (int) $saved->getId(),
            'customer_id' => (int) $saved->getCustomerId(),
        ];

        $json = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        if ($json === false) {
            throw new LocalizedException(__('Failed to encode updated address as JSON.'));
        }

        return new ToolResult(
            content: [['type' => 'text', 'text' => $json]],
            auditSummary: [
                'id' => (int) $saved->getId(),
                'customer_id' => (int) $saved->getCustomerId(),
                'fields_changed' => $written,
            ]
        );
    }
}
