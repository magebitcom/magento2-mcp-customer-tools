<?php
/**
 * @author    Magebit <info@magebit.com>
 * @copyright Copyright (c) Magebit, Ltd. (https://magebit.com)
 * @license   MIT
 */
declare(strict_types=1);

namespace Magebit\McpCustomerTools\Test\Unit\Tool\Customer\Group;

use Magebit\Mcp\Model\Util\ResolverPipeline;
use Magebit\McpCustomerTools\Model\Search\GroupSearchCriteriaBuilder;
use Magebit\McpCustomerTools\Tool\Customer\Group\GroupList;
use Magento\Customer\Api\GroupRepositoryInterface;
use PHPUnit\Framework\TestCase;

class GroupListTest extends TestCase
{
    private GroupList $tool;

    protected function setUp(): void
    {
        $this->tool = new GroupList(
            $this->createMock(GroupRepositoryInterface::class),
            $this->createMock(GroupSearchCriteriaBuilder::class),
            $this->createMock(ResolverPipeline::class),
            []
        );
    }

    public function testSchemaDeclaresTypedFilterKeys(): void
    {
        $schema = $this->tool->getInputSchema();
        $this->assertIsArray($schema['properties']);
        $filters = $schema['properties']['filters'];
        $this->assertIsArray($filters);
        $this->assertTrue($filters['additionalProperties']);
        $props = $filters['properties'];
        $this->assertIsArray($props);

        $expected = [
            'code' => ['string', 'array'],
            'tax_class_id' => ['integer', 'array'],
        ];
        $this->assertSame(array_keys($expected), array_keys($props));
        foreach ($expected as $key => $type) {
            $prop = $props[$key];
            $this->assertIsArray($prop);
            $this->assertSame($type, $prop['type'], $key);
            $this->assertNotEmpty($prop['description'], $key);
        }
    }
}
