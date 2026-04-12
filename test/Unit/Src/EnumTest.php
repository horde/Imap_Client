<?php

declare(strict_types=1);

namespace Horde\Imap\Client\Test\Unit\Src;

use Horde\Imap\Client\AclRight;
use Horde\Imap\Client\MailboxListMode;
use Horde\Imap\Client\OpenMode;
use Horde\Imap\Client\SearchResultType;
use Horde\Imap\Client\SecureMode;
use Horde\Imap\Client\SortCriteria;
use Horde\Imap\Client\SpecialUse;
use Horde\Imap\Client\SystemFlag;
use Horde\Imap\Client\ThreadAlgorithm;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Verify enum backing values match lib/ constants for migration.
 */
#[CoversClass(SecureMode::class)]
#[CoversClass(OpenMode::class)]
#[CoversClass(MailboxListMode::class)]
#[CoversClass(SortCriteria::class)]
#[CoversClass(SearchResultType::class)]
#[CoversClass(ThreadAlgorithm::class)]
#[CoversClass(AclRight::class)]
#[CoversClass(SystemFlag::class)]
#[CoversClass(SpecialUse::class)]
class EnumTest extends TestCase
{
    public function testSecureModeValues(): void
    {
        $this->assertSame('', SecureMode::None->value);
        $this->assertSame('ssl', SecureMode::Ssl->value);
        $this->assertSame('tls', SecureMode::Tls->value);
        $this->assertSame('tlsv1', SecureMode::Tlsv1->value);
        $this->assertCount(4, SecureMode::cases());
    }

    public function testOpenModeValues(): void
    {
        $this->assertSame(1, OpenMode::Readonly->value);
        $this->assertSame(2, OpenMode::ReadWrite->value);
        $this->assertSame(3, OpenMode::Auto->value);
    }

    public function testMailboxListModeValues(): void
    {
        $this->assertSame(1, MailboxListMode::Subscribed->value);
        $this->assertSame(4, MailboxListMode::All->value);
        $this->assertCount(5, MailboxListMode::cases());
    }

    public function testSortCriteriaValues(): void
    {
        $this->assertSame(1, SortCriteria::Arrival->value);
        $this->assertSame(3, SortCriteria::Date->value);
        $this->assertSame(13, SortCriteria::Relevancy->value);
        $this->assertSame(15, SortCriteria::DisplayToFallback->value);
        $this->assertCount(15, SortCriteria::cases());
    }

    public function testSearchResultTypeValues(): void
    {
        $this->assertSame(1, SearchResultType::Count->value);
        $this->assertSame(2, SearchResultType::Match->value);
        $this->assertSame(6, SearchResultType::Relevancy->value);
        $this->assertCount(6, SearchResultType::cases());
    }

    public function testThreadAlgorithmValues(): void
    {
        $this->assertSame(1, ThreadAlgorithm::OrderedSubject->value);
        $this->assertSame(2, ThreadAlgorithm::References->value);
        $this->assertSame(3, ThreadAlgorithm::Refs->value);
    }

    public function testAclRightValues(): void
    {
        $this->assertSame('l', AclRight::Lookup->value);
        $this->assertSame('r', AclRight::Read->value);
        $this->assertSame('a', AclRight::Administer->value);
        $this->assertCount(11, AclRight::cases());
    }

    public function testAclRightOmitsDeprecated(): void
    {
        $values = array_map(fn(AclRight $r) => $r->value, AclRight::cases());
        $this->assertNotContains('c', $values);
        $this->assertNotContains('d', $values);
    }

    public function testSystemFlagValues(): void
    {
        $this->assertSame('\\answered', SystemFlag::Answered->value);
        $this->assertSame('\\seen', SystemFlag::Seen->value);
        $this->assertSame('$mdnsent', SystemFlag::MdnSent->value);
        $this->assertSame('$junk', SystemFlag::Junk->value);
        $this->assertCount(10, SystemFlag::cases());
    }

    public function testSpecialUseValues(): void
    {
        $this->assertSame('\\All', SpecialUse::All->value);
        $this->assertSame('\\Trash', SpecialUse::Trash->value);
        $this->assertCount(7, SpecialUse::cases());
    }

    /**
     * All int-backed enums must be constructable from their value.
     */
    public static function intBackedEnumProvider(): array
    {
        return [
            [OpenMode::class, 1, 'Readonly'],
            [MailboxListMode::class, 4, 'All'],
            [SortCriteria::class, 3, 'Date'],
            [SearchResultType::class, 2, 'Match'],
            [ThreadAlgorithm::class, 2, 'References'],
        ];
    }

    #[DataProvider('intBackedEnumProvider')]
    public function testFromInt(string $enum, int $value, string $expectedName): void
    {
        $case = $enum::from($value);
        $this->assertSame($expectedName, $case->name);
    }

    /**
     * All string-backed enums must be constructable from their value.
     */
    public static function stringBackedEnumProvider(): array
    {
        return [
            [SecureMode::class, 'ssl', 'Ssl'],
            [AclRight::class, 'l', 'Lookup'],
            [SystemFlag::class, '\\answered', 'Answered'],
            [SpecialUse::class, '\\Drafts', 'Drafts'],
        ];
    }

    #[DataProvider('stringBackedEnumProvider')]
    public function testFromString(string $enum, string $value, string $expectedName): void
    {
        $case = $enum::from($value);
        $this->assertSame($expectedName, $case->name);
    }
}
