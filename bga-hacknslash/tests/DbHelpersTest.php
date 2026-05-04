<?php

use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__) . '/modules/HNS_DbHelpers.php';

final class DbHelpersTest extends TestCase
{
    private object $host;

    protected function setUp(): void
    {
        $this->host = new class {
            use HNS_DbHelpers {
                hns_sql_escape as public;
                hns_sql_nullable_string as public;
            }
        };
    }

    public function testEscapeFallsBackToStrReplaceWhenHostHasNoBgaHelper(): void
    {
        $this->assertSame("O\\'Brien", $this->host->hns_sql_escape("O'Brien"));
    }

    public function testEscapePrefersBgaHelperWhenAvailable(): void
    {
        $host = new class {
            use HNS_DbHelpers {
                hns_sql_escape as public;
            }

            public function escapeStringForDB(string $value): string
            {
                return '<<' . $value . '>>';
            }
        };

        $this->assertSame("<<O'Brien>>", $host->hns_sql_escape("O'Brien"));
    }

    public function testNullableStringRendersNullForNullValue(): void
    {
        $this->assertSame('NULL', $this->host->hns_sql_nullable_string(null));
    }

    public function testNullableStringWrapsValueInSingleQuotes(): void
    {
        $this->assertSame("'foo'", $this->host->hns_sql_nullable_string('foo'));
    }

    public function testNullableStringEscapesValue(): void
    {
        $this->assertSame("'O\\'Brien'", $this->host->hns_sql_nullable_string("O'Brien"));
    }
}
