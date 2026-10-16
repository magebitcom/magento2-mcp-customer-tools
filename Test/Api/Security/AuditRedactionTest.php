<?php
/**
 * @author    Magebit <info@magebit.com>
 * @copyright Copyright (c) Magebit, Ltd. (https://magebit.com)
 * @license   MIT
 */
declare(strict_types=1);

namespace Magebit\McpCustomerTools\Test\Api\Security;

use Magebit\Mcp\Test\Api\McpTestCase;
use Magebit\Mcp\Test\Api\Security\AuditLogReader;

/**
 * Personal data passed as named tool arguments is stored as a fingerprint in the audit log.
 *
 * @magentoApiDataFixture Magento/User/_files/user_with_role.php
 */
class AuditRedactionTest extends McpTestCase
{
    private const ARGUMENTS = [
        'email' => 'mcpsec.probe@example.com',
        'telephone' => '+37120000000',
        'street' => 'Probe street 1',
    ];

    /**
     * @return void
     */
    public function testNamedPiiArgumentsAreFingerprintedInAuditRow(): void
    {
        // The answer does not matter; only the audit copy of the arguments is checked.
        $this->toolsCall('customer.customer.get', self::ARGUMENTS);

        $row = AuditLogReader::latest('customer.customer.get', $this->tokenRowId);
        self::assertNotNull($row, 'Expected an audit row for the call.');
        $json = $row['arguments_json'] ?? null;
        self::assertIsString($json);

        foreach (self::ARGUMENTS as $raw) {
            self::assertStringNotContainsString($raw, $json);
        }

        $arguments = json_decode($json, true);
        self::assertIsArray($arguments);
        foreach (array_keys(self::ARGUMENTS) as $key) {
            self::assertArrayHasKey($key, $arguments, 'Argument kept in the audit row: ' . $key);
            self::assertIsString($arguments[$key]);
            self::assertMatchesRegularExpression('/^\*\*\*\[[0-9a-f]{16}\]$/', $arguments[$key]);
        }
    }
}
