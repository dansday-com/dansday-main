<?php

namespace Tests\Feature;

use App\Http\Middleware\McpAuth;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class McpUploadTest extends TestCase
{
    private string $scratch;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware(McpAuth::class);

        Storage::fake('uploads');
        Storage::fake('media');

        $this->scratch = sys_get_temp_dir().'/mcp-upload-test-'.bin2hex(random_bytes(6));
        mkdir($this->scratch, 0777, true);
    }

    protected function tearDown(): void
    {
        foreach (glob($this->scratch.'/*') ?: [] as $file) {
            @unlink($file);
        }

        @rmdir($this->scratch);

        parent::tearDown();
    }

    private function file(string $clientName, string $bytes): UploadedFile
    {
        $path = $this->scratch.'/'.bin2hex(random_bytes(8));
        file_put_contents($path, $bytes);

        return new UploadedFile($path, $clientName, null, null, true);
    }

    private function png(string $name = 'cover.png'): UploadedFile
    {
        $image = imagecreatetruecolor(4, 4);
        $path = $this->scratch.'/src.png';
        imagepng($image, $path);
        imagedestroy($image);

        return $this->file($name, (string) file_get_contents($path));
    }

    private function pdf(string $name = 'paper.pdf'): UploadedFile
    {
        return $this->file($name, "%PDF-1.4\n1 0 obj\n<<>>\nendobj\ntrailer\n<<>>\n%%EOF\n");
    }

    private function mp4(string $name = 'clip.mp4'): UploadedFile
    {
        return $this->file($name, "\x00\x00\x00\x20ftypisom\x00\x00\x02\x00isomiso2avc1mp41".str_repeat("\x00", 512));
    }

    private function upload(string $kind, UploadedFile $file)
    {
        return $this->post('/mcp/uploads', ['kind' => $kind, 'file' => $file]);
    }

    public function test_an_image_lands_on_the_public_disk(): void
    {
        $response = $this->upload('article', $this->png());

        $response->assertOk();
        $path = $response->json('path');

        $this->assertStringStartsWith('uploads/admin/articles/', $path);
        $this->assertSame('png', $response->json('type'));
        $this->assertNotNull($response->json('url'));
        Storage::disk('uploads')->assertExists(str_replace('uploads/', '', $path));
    }

    public function test_a_script_disguised_as_an_image_is_refused(): void
    {
        $this->upload('article', $this->file('cover.png', "<?php system(\$_GET['c']); ?>"))
            ->assertStatus(422);

        $this->assertSame([], Storage::disk('uploads')->allFiles());
    }

    private function polyglot(): string
    {
        $image = imagecreatetruecolor(4, 4);
        $path = $this->scratch.'/poly.png';
        imagepng($image, $path);
        imagedestroy($image);

        return file_get_contents($path)."<?php system(\$_GET['c']); ?>";
    }

    public function test_a_valid_image_carrying_a_php_extension_is_refused_outright(): void
    {
        $this->upload('article', $this->file('cover.php', $this->polyglot()))
            ->assertStatus(422);

        $this->assertSame([], Storage::disk('uploads')->allFiles());
    }

    public function test_a_polyglot_is_stored_under_the_extension_its_content_earns(): void
    {
        $response = $this->upload('article', $this->file('cover.png', $this->polyglot()));

        $response->assertOk();
        $this->assertSame('png', $response->json('type'));
        $this->assertStringEndsWith('.png', $response->json('path'));
        $this->assertStringNotContainsString('.php', $response->json('path'));
    }

    public function test_a_document_lands_on_the_private_disk(): void
    {
        $response = $this->upload('document', $this->pdf());

        $response->assertOk();
        $path = $response->json('path');

        $this->assertStringStartsWith('media/linkedin/documents/', $path);
        $this->assertSame('pdf', $response->json('type'));
        Storage::disk('media')->assertExists(str_replace('media/', '', $path));
    }

    public function test_a_private_upload_is_never_given_a_public_url(): void
    {
        $response = $this->upload('document', $this->pdf());

        $response->assertOk();
        $this->assertNull($response->json('url'));
        $this->assertSame([], Storage::disk('uploads')->allFiles());
    }

    public function test_a_video_lands_on_the_private_disk(): void
    {
        $response = $this->upload('video', $this->mp4());

        $response->assertOk();
        $this->assertStringStartsWith('media/linkedin/videos/', $response->json('path'));
        $this->assertSame('mp4', $response->json('type'));
    }

    public function test_an_executable_claiming_to_be_a_document_is_refused(): void
    {
        $this->upload('document', $this->file('report.pdf', "MZ\x90\x00".str_repeat("\x00", 128)))
            ->assertStatus(422);

        $this->assertSame([], Storage::disk('media')->allFiles());
    }

    public function test_types_cannot_cross_between_kinds(): void
    {
        $this->upload('document', $this->mp4())->assertStatus(422);
        $this->upload('video', $this->pdf())->assertStatus(422);
        $this->upload('article', $this->pdf())->assertStatus(422);

        $this->assertSame([], Storage::disk('media')->allFiles());
        $this->assertSame([], Storage::disk('uploads')->allFiles());
    }

    public function test_an_unknown_kind_is_refused(): void
    {
        $this->upload('nonsense', $this->png())
            ->assertStatus(422)
            ->assertJsonPath('error', 'Unknown kind "nonsense". Use one of: article, project, inline, document, video.');
    }

    public function test_the_client_filename_never_reaches_the_disk(): void
    {
        $response = $this->upload('document', $this->pdf('../../../evil name.pdf'));

        $response->assertOk();
        $path = $response->json('path');

        $this->assertStringStartsWith('media/linkedin/documents/document_', $path);
        $this->assertStringNotContainsString('evil', $path);
        $this->assertStringNotContainsString(' ', $path);
        $this->assertStringNotContainsString('..', $path);
    }

    public function test_a_zip_that_is_not_really_a_document_is_refused(): void
    {
        $zip = $this->scratch.'/plain.zip';
        $archive = new \ZipArchive();
        $archive->open($zip, \ZipArchive::CREATE);
        $archive->addFromString('notes.txt', 'nothing to see');
        $archive->close();

        $this->upload('document', $this->file('deck.docx', (string) file_get_contents($zip)))
            ->assertStatus(422);

        $this->assertSame([], Storage::disk('media')->allFiles());
    }
}
