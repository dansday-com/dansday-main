<?php

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class UploadPathSafetyTest extends TestCase
{
    public static function hostilePaths(): array
    {
        return [
            'parent'            => ['../etc/passwd'],
            'nested parent'     => ['uploads/../../etc/passwd'],
            'doubled dot slash' => ['....//etc/passwd'],
            'absolute'          => ['/etc/passwd'],
            'backslash'         => ['..\\..\\etc\\passwd'],
            'mixed separators'  => ['uploads/admin/..\\../../etc/passwd'],
            'current dir'       => ['./././../etc/passwd'],
            'trailing parent'   => ['uploads/admin/articles/../../../../etc/passwd'],
        ];
    }

    #[DataProvider('hostilePaths')]
    public function test_normalizer_never_escapes_its_root(string $path): void
    {
        $normalized = storage_path_normalize($path);

        $this->assertStringStartsNotWith('/', $normalized);
        $this->assertStringStartsNotWith('../', $normalized);
        $this->assertStringNotContainsString('/../', $normalized);
        $this->assertNotSame('..', $normalized);
    }

    #[DataProvider('hostilePaths')]
    public function test_uploads_paths_never_escape_their_root(string $path): void
    {
        $normalized = uploads_path_for_disk($path);

        $this->assertStringStartsNotWith('/', $normalized);
        $this->assertStringStartsNotWith('../', $normalized);
        $this->assertStringNotContainsString('/../', $normalized);
    }

    public function test_normalizer_rejects_null_bytes(): void
    {
        $this->assertSame('', storage_path_normalize("admin/articles/x.png\0.php"));
    }

    public function test_legitimate_upload_paths_are_unchanged(): void
    {
        $this->assertSame('admin/articles/post_image_abc.png', uploads_path_for_disk('uploads/admin/articles/post_image_abc.png'));
        $this->assertSame('admin/articles/post_image_abc.png', uploads_path_for_disk('admin/articles/post_image_abc.png'));
        $this->assertSame('admin/temp/img_abc.jpg', uploads_path_for_disk('/uploads/admin/temp/img_abc.jpg'));
    }

    public function test_media_paths_are_recognised(): void
    {
        $this->assertSame('linkedin/documents/document_abc.pdf', media_path_for_disk('media/linkedin/documents/document_abc.pdf'));
        $this->assertSame('linkedin/videos/video_abc.mp4', media_path_for_disk('linkedin/videos/video_abc.mp4'));
    }

    public function test_only_linkedin_media_folders_are_allowed(): void
    {
        $this->assertTrue(media_path_is_allowed('media/linkedin/documents/document_abc.pdf'));
        $this->assertTrue(media_path_is_allowed('media/linkedin/videos/video_abc.mp4'));

        $this->assertFalse(media_path_is_allowed('media/linkedin/documents/'));
        $this->assertFalse(media_path_is_allowed('media/linkedin/documents'));
        $this->assertFalse(media_path_is_allowed('media/../.env'));
        $this->assertFalse(media_path_is_allowed('media/linkedin/documents/../../../.env'));
        $this->assertFalse(media_path_is_allowed('uploads/admin/articles/post_image_abc.png'));
        $this->assertFalse(media_path_is_allowed(''));
        $this->assertFalse(media_path_is_allowed(null));
    }

    public function test_public_uploads_are_never_valid_media_paths(): void
    {
        $this->assertFalse(media_path_is_allowed('admin/articles/post_image_abc.png'));
    }

    public function test_delete_allowlist_still_holds(): void
    {
        $this->assertTrue(uploads_path_safe_to_delete('uploads/admin/articles/post_image_abc.png'));
        $this->assertFalse(uploads_path_safe_to_delete('uploads/../.env'));
        $this->assertFalse(uploads_path_safe_to_delete('media/linkedin/videos/video_abc.mp4'));
    }
}
