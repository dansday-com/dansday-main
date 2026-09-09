<?php

namespace Tests\Unit;

use App\Exceptions\ContentWriteException;
use App\Mcp\Tools\LinkedInTools;
use App\Mcp\ToolRegistry;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class LinkedInPlanningTest extends TestCase
{
    private function plan(array $args): array
    {
        return LinkedInTools::validatePayload($args + ['commentary' => 'Hello there']);
    }

    public function test_body_is_the_default_link_placement(): void
    {
        $this->assertSame('body', $this->plan(['article_id' => 3])['placement']);
    }

    public static function placements(): array
    {
        return [
            'body'          => ['body'],
            'card'          => ['card'],
            'none'          => ['none'],
        ];
    }

    #[DataProvider('placements')]
    public function test_every_documented_placement_is_accepted(string $placement): void
    {
        $this->assertSame($placement, $this->plan(['article_id' => 3, 'link_placement' => $placement])['placement']);
    }

    public function test_an_unknown_placement_is_refused(): void
    {
        $this->expectException(ContentWriteException::class);
        $this->plan(['article_id' => 3, 'link_placement' => 'sidebar']);
    }

    public function test_a_card_needs_an_article_to_point_at(): void
    {
        $this->expectException(ContentWriteException::class);
        $this->plan(['link_placement' => 'card']);
    }

    public function test_first_comment_placement_is_no_longer_accepted(): void
    {
        $this->expectException(ContentWriteException::class);
        $this->plan(['article_id' => 3, 'link_placement' => 'first_comment']);
    }

    public static function conflictingMedia(): array
    {
        return [
            'image'    => [['image' => 'uploads/admin/articles/a.png']],
            'document' => [['document' => 'media/linkedin/documents/a.pdf', 'document_title' => 'x']],
            'video'    => [['video' => 'media/linkedin/videos/a.mp4']],
            'images'   => [['images' => [['path' => 'a.png'], ['path' => 'b.png']]]],
        ];
    }

    #[DataProvider('conflictingMedia')]
    public function test_a_card_cannot_share_the_content_slot_with_media(array $media): void
    {
        $this->expectException(ContentWriteException::class);
        $this->plan(['article_id' => 3, 'link_placement' => 'card'] + $media);
    }

    public function test_empty_commentary_is_refused(): void
    {
        $this->expectException(ContentWriteException::class);
        LinkedInTools::validatePayload(['commentary' => '   ']);
    }

    public function test_visibility_is_still_constrained(): void
    {
        $this->expectException(ContentWriteException::class);
        $this->plan(['visibility' => 'EVERYONE']);
    }

    public static function badSchedules(): array
    {
        return [
            'relative'   => ['tomorrow'],
            'vague'      => ['next tuesday'],
            'past'       => ['2020-01-01 09:00'],
            'nonsense'   => ['soon'],
            'wrong shape' => ['01/02/2027'],
        ];
    }

    #[DataProvider('badSchedules')]
    public function test_schedule_refuses_unusable_times(string $when): void
    {
        $this->expectException(ContentWriteException::class);

        ToolRegistry::call('schedule_linkedin_post', [
            'publish_at' => $when,
            'commentary' => 'Hello',
            'confirm'    => true,
        ]);
    }

    public function test_scheduling_requires_confirmation(): void
    {
        $this->expectException(ContentWriteException::class);

        ToolRegistry::call('schedule_linkedin_post', [
            'publish_at' => '2099-01-01 09:00',
            'commentary' => 'Hello',
            'confirm'    => false,
        ]);
    }

    public static function badReactions(): array
    {
        return [
            'made up'    => ['SUPERLIKE'],
            'emoji'      => ['\u{1F44D}'],
            'empty-ish'  => ['   x   '],
            'sql'        => ["LIKE'; DROP TABLE"],
        ];
    }

    #[DataProvider('badReactions')]
    public function test_unknown_reactions_are_refused(string $reaction): void
    {
        $this->expectException(ContentWriteException::class);

        ToolRegistry::call('react_to_linkedin_post', [
            'urn'      => 'urn:li:share:123',
            'reaction' => $reaction,
            'confirm'  => true,
        ]);
    }

    public static function goodReactions(): array
    {
        return array_map(fn ($r) => [$r], ['LIKE', 'PRAISE', 'APPRECIATION', 'EMPATHY', 'INTEREST', 'ENTERTAINMENT']);
    }

    #[DataProvider('goodReactions')]
    public function test_documented_reactions_pass_validation(string $reaction): void
    {
        $this->assertContains($reaction, \App\Services\LinkedInService::REACTIONS);
    }

    public static function badUrns(): array
    {
        return [
            'plain text' => ['not a urn'],
            'url'        => ['https://linkedin.com/feed/update/123'],
            'wrong ns'   => ['urn:x:share:123'],
            'empty'      => ['   '],
        ];
    }

    #[DataProvider('badUrns')]
    public function test_reactions_refuse_anything_that_is_not_a_urn(string $urn): void
    {
        $this->expectException(ContentWriteException::class);

        ToolRegistry::call('react_to_linkedin_post', [
            'urn'      => $urn,
            'reaction' => 'LIKE',
            'confirm'  => true,
        ]);
    }

    public function test_reacting_requires_confirmation(): void
    {
        $this->expectException(ContentWriteException::class);

        ToolRegistry::call('react_to_linkedin_post', [
            'urn'      => 'urn:li:share:123',
            'reaction' => 'LIKE',
            'confirm'  => false,
        ]);
    }

}
