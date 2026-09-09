<?php

namespace Tests\Unit;

use App\Exceptions\ContentWriteException;
use App\Services\ContentWriteService;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

class ContentWriteHelpersTest extends TestCase
{
    private function call(string $method, array $args): mixed
    {
        $reflection = new ReflectionMethod(ContentWriteService::class, $method);
        $reflection->setAccessible(true);

        return $reflection->invokeArgs(null, $args);
    }

    public static function booleanShapes(): array
    {
        return [
            'true'         => [true, false, true],
            'false'        => [false, true, false],
            'string true'  => ['true', false, true],
            'string on'    => ['on', false, true],
            'string yes'   => ['yes', false, true],
            'string 1'     => ['1', false, true],
            'string false' => ['false', true, false],
            'string off'   => ['off', true, false],
            'int 1'        => [1, false, true],
            'int 0'        => [0, true, false],
        ];
    }

    #[DataProvider('booleanShapes')]
    public function test_enable_flags_accept_the_shapes_llm_clients_send(mixed $given, bool $default, bool $expected): void
    {
        $this->assertSame($expected, $this->call('boolInput', [['enable' => $given], 'enable', $default]));
    }

    public function test_absent_or_null_flag_falls_back_to_the_default(): void
    {
        $this->assertTrue($this->call('boolInput', [[], 'enable', true]));
        $this->assertFalse($this->call('boolInput', [[], 'enable', false]));
        $this->assertTrue($this->call('boolInput', [['enable' => null], 'enable', true]));
    }

    public static function acceptedDates(): array
    {
        return [
            'date only'        => ['2024-03-01', '2024-03-01 00:00:00'],
            'date and time'    => ['2024-03-01 14:30:00', '2024-03-01 14:30:00'],
            'no seconds'       => ['2024-03-01 14:30', '2024-03-01 14:30:00'],
            'unpadded'         => ['2024-3-1', '2024-03-01 00:00:00'],
            'iso separator'    => ['2024-03-01T14:30:00', '2024-03-01 14:30:00'],
            'iso zulu'         => ['2024-03-01T14:30:00Z', '2024-03-01 14:30:00'],
            'iso offset'       => ['2024-03-01T14:30:00+07:00', '2024-03-01 14:30:00'],
            'surrounding space' => ['  2024-03-01  ', '2024-03-01 00:00:00'],
        ];
    }

    #[DataProvider('acceptedDates')]
    public function test_created_at_accepts_absolute_dates(string $given, string $expected): void
    {
        $this->assertSame($expected, $this->call('parseDate', [['created_at' => $given], 'created_at']));
    }

    public function test_absent_created_at_means_leave_it_alone(): void
    {
        $this->assertNull($this->call('parseDate', [[], 'created_at']));
        $this->assertNull($this->call('parseDate', [['created_at' => ''], 'created_at']));
        $this->assertNull($this->call('parseDate', [['created_at' => '   '], 'created_at']));
        $this->assertNull($this->call('parseDate', [['created_at' => null], 'created_at']));
    }

    public static function rejectedDates(): array
    {
        return [

            'bare year'        => ['2024'],
            'single letter'    => ['x'],
            'month name'       => ['march'],
            'relative day'     => ['tomorrow'],
            'relative weekday' => ['last tuesday'],
            'relative time'    => ['yesterday 5pm'],
            'relative month'   => ['next month'],

            'zero date'        => ['0000-00-00'],
            'month 13'         => ['2026-13-45'],
            'impossible day'   => ['2026-02-30'],
            'not a date'       => ['not-a-date'],
            'word'             => ['probe'],
            'number'           => ['12'],
            'impossible time'  => ['2024-03-01 99:99:99'],
        ];
    }

    #[DataProvider('rejectedDates')]
    public function test_ambiguous_or_relative_dates_are_rejected(string $given): void
    {
        $this->expectException(ContentWriteException::class);

        $this->call('parseDate', [['created_at' => $given], 'created_at']);
    }

    public function test_the_rejection_message_states_the_expected_format(): void
    {
        $this->expectException(ContentWriteException::class);
        $this->expectExceptionMessageMatches('/YYYY-MM-DD/');

        $this->call('parseDate', [['created_at' => 'not-a-date'], 'created_at']);
    }

    public function test_images_are_optional(): void
    {
        $this->assertSame('', $this->call('normalizeImage', ['']));
        $this->assertSame('', $this->call('normalizeImage', [null]));
    }

    public function test_absolute_urls_pass_through(): void
    {
        $this->assertSame('https://cdn.example.com/a.png', $this->call('normalizeImage', ['https://cdn.example.com/a.png']));
        $this->assertSame('http://example.test/a.png', $this->call('normalizeImage', ['http://example.test/a.png']));
    }

    public function test_upload_paths_are_normalised_to_a_single_form(): void
    {
        $this->assertSame('uploads/admin/articles/a.png', $this->call('normalizeImage', ['uploads/admin/articles/a.png']));
        $this->assertSame('uploads/admin/articles/a.png', $this->call('normalizeImage', ['admin/articles/a.png']));
        $this->assertSame('uploads/admin/projects/b.jpg', $this->call('normalizeImage', ['/uploads/admin/projects/b.jpg']));
    }

    public static function rejectedImagePaths(): array
    {
        return [
            'absolute system path' => ['/etc/passwd'],
            'parent traversal'     => ['../../../etc/passwd'],
            'embedded traversal'   => ['uploads/../../secret.png'],
            'outside img dir'      => ['storage/private/x.png'],
        ];
    }

    #[DataProvider('rejectedImagePaths')]
    public function test_paths_outside_the_uploads_image_tree_are_refused(string $path): void
    {
        $this->expectException(ContentWriteException::class);

        $this->call('normalizeImage', [$path]);
    }

    public function test_category_kinds_map_to_their_tables(): void
    {
        $this->assertSame('article_categories', $this->call('categoryTable', ['article']));
        $this->assertSame('project_categories', $this->call('categoryTable', ['project']));
    }

    public function test_an_unknown_category_kind_is_refused(): void
    {
        $this->expectException(ContentWriteException::class);

        $this->call('categoryTable', ['bogus']);
    }

    public function test_about_kinds_map_to_their_tables(): void
    {
        foreach (['skill', 'experience', 'service', 'testimonial'] as $kind) {
            $this->assertSame($kind, $this->call('aboutTable', [$kind]));
        }
    }

    public function test_an_unknown_about_kind_names_the_valid_kinds(): void
    {
        $this->expectException(ContentWriteException::class);
        $this->expectExceptionMessageMatches('/testimonial/');

        $this->call('aboutTable', ['bogus']);
    }

    public static function dangerousHtml(): array
    {
        return [
            'inline script'      => ['<p>hi</p><script>alert(1)</script>', 'alert'],
            'external script'    => ['<p>a</p><script src="//evil.test/x.js"></script>', 'script'],
            'uppercase script'   => ['<SCRIPT>bad()</SCRIPT>', 'script'],
            'orphan close tag'   => ['<p>a</p></script>', 'script'],
            'onerror attribute'  => ['<img src=x onerror="steal()">', 'onerror'],
            'onclick attribute'  => ["<div onclick='go()'>x</div>", 'onclick'],
            'unquoted handler'   => ['<div onmouseover=go()>x</div>', 'onmouseover'],
            'javascript url'     => ['<a href="javascript:evil()">x</a>', 'javascript:'],
            'iframe'             => ['<iframe src="//evil.test"></iframe>', 'iframe'],
            'form'               => ['<form action="/x"><input></form>', '<form'],
        ];
    }

    #[DataProvider('dangerousHtml')]
    public function test_dangerous_html_is_stripped_from_bodies(string $html, string $needle): void
    {
        $this->assertStringNotContainsStringIgnoringCase(
            $needle,
            $this->call('sanitizeHtml', [$html])
        );
    }

    public static function legitimateHtml(): array
    {
        return [
            'escaped code sample' => ['<pre><code>if (a &lt; b &amp;&amp; c &gt; d) { return &lt;T&gt;(x); }</code></pre>'],
            'article markup'      => ['<h2>Title</h2><p>Text with <strong>bold</strong> and <a href="/x">link</a>.</p><ul><li>one</li></ul>'],
            'image'               => ['<img src="uploads/admin/articles/a.png" alt="a" />'],
            'table'               => ['<table><thead><tr><th>a</th></tr></thead><tbody><tr><td>1</td></tr></tbody></table>'],
            'video'               => ['<video controls><source src="/v.mp4" type="video/mp4"></video>'],
            'prose with tag word' => ['<p>I wrote a description of my transcription work.</p>'],
            'prose starting on'   => ['<p>Later on we shipped it. Only one thing left.</p>'],
        ];
    }

    #[DataProvider('legitimateHtml')]
    public function test_legitimate_markup_survives_untouched(string $html): void
    {
        $this->assertSame($html, $this->call('sanitizeHtml', [$html]));
    }

    public function test_blank_strings_are_treated_as_absent(): void
    {
        $this->assertNull($this->call('nonEmpty', ['   ']));
        $this->assertNull($this->call('nonEmpty', [null]));
        $this->assertSame('hi', $this->call('nonEmpty', ['  hi  ']));
    }
}
