<?php

namespace App\Services;

use App\Exceptions\ContentWriteException;
use App\Models\General;
use App\Models\LinkedInPost;
use App\Support\SafeUrlFetcher;
use GuzzleHttp\Psr7\Utils;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class LinkedInService
{
    public const SCOPES = 'openid profile w_member_social';

    public const MAX_COMMENTARY = 3000;

    private const AUTHORIZE_URL = 'https://www.linkedin.com/oauth/v2/authorization';

    private const TOKEN_URL = 'https://www.linkedin.com/oauth/v2/accessToken';

    private const USERINFO_URL = 'https://api.linkedin.com/v2/userinfo';

    private const POSTS_URL = 'https://api.linkedin.com/rest/posts';

    private const IMAGES_URL = 'https://api.linkedin.com/rest/images';

    private const DOCUMENTS_URL = 'https://api.linkedin.com/rest/documents';

    private const VIDEOS_URL = 'https://api.linkedin.com/rest/videos';

    private const REACTIONS_URL = 'https://api.linkedin.com/rest/reactions';

    public const MAX_ALT_TEXT = 4086;

    public const MAX_IMAGES = 20;

    public const MIN_MULTI_IMAGES = 2;

    public const MAX_TITLE = 400;

    public const EXPIRY_WARNING_DAYS = 14;

    public const REACTIONS = ['LIKE', 'PRAISE', 'APPRECIATION', 'EMPATHY', 'INTEREST', 'ENTERTAINMENT'];

    public const MAX_IMAGE_BYTES = 8388608;

    public const MAX_DOCUMENT_BYTES = 104857600;

    public const MAX_VIDEO_BYTES = 209715200;

    public const DOCUMENT_EXTENSIONS = ['pdf', 'doc', 'docx', 'ppt', 'pptx'];

    public const VIDEO_EXTENSIONS = ['mp4', 'mov'];

    private const API_VERSION = '202608';

    public static function settings(): ?General
    {
        return General::find(1);
    }

    public static function redirectUri(): string
    {
        return rtrim((string) config('app.url'), '/').'/admin/linkedin/callback';
    }

    public static function connectUrl(): string
    {
        return rtrim((string) config('app.url'), '/').'/admin/linkedin/connect';
    }

    public static function publicSiteUrl(): string
    {
        $url = rtrim((string) config('app.url'), '/');
        $host = (string) parse_url($url, PHP_URL_HOST);

        if (str_starts_with($host, 'admin.')) {
            return str_replace('//'.$host, '//'.substr($host, 6), $url);
        }

        return $url;
    }

    public static function isConfigured(): bool
    {
        return trim((string) config('services.linkedin.client_id')) !== ''
            && trim((string) config('services.linkedin.client_secret')) !== '';
    }

    public static function authorizeUrl(string $state): string
    {
        return self::AUTHORIZE_URL.'?'.http_build_query([
            'response_type' => 'code',
            'client_id'     => trim((string) config('services.linkedin.client_id')),
            'redirect_uri'  => self::redirectUri(),
            'state'         => $state,
            'scope'         => self::SCOPES,
        ]);
    }

    public static function status(): array
    {
        $general = self::settings();

        if (! self::isConfigured()) {
            return [
                'connected'   => false,
                'reason'      => 'not_configured',
                'message'     => 'LINKEDIN_CLIENT_ID and LINKEDIN_CLIENT_SECRET are not set in the environment.',
            ];
        }

        $token = trim((string) $general->linkedin_access_token);
        $person = trim((string) $general->linkedin_person_urn);

        if ($token === '' || $person === '') {
            return [
                'connected'   => false,
                'reason'      => 'not_connected',
                'message'     => 'No LinkedIn account is connected. Open connect_url in a browser while signed in to the admin panel, approve access, then try again.',
                'connect_url' => self::connectUrl(),
            ];
        }

        $expiresAt = $general->linkedin_token_expires_at ? Carbon::parse($general->linkedin_token_expires_at) : null;

        if ($expiresAt && $expiresAt->isPast()) {
            return [
                'connected'   => false,
                'reason'      => 'expired',
                'message'     => 'The stored LinkedIn token expired on '.$expiresAt->toDateString().'. Open connect_url in a browser to reconnect, then try again.',
                'expires_at'  => $expiresAt->toDateTimeString(),
                'connect_url' => self::connectUrl(),
            ];
        }

        $daysLeft = $expiresAt ? max(0, (int) floor(now()->diffInDays($expiresAt, false))) : null;
        $expiringSoon = $daysLeft !== null && $daysLeft <= self::EXPIRY_WARNING_DAYS;

        return array_filter([
            'connected'      => true,
            'as'             => $person,
            'expires_at'     => $expiresAt?->toDateTimeString(),
            'days_left'      => $daysLeft,
            'expiring_soon'  => $expiringSoon,
            'warning'        => $expiringSoon
                ? 'This LinkedIn token expires in '.$daysLeft.' day'.($daysLeft === 1 ? '' : 's').'. Reconnect before then or posting will start failing: open connect_url in a browser while signed in to the admin panel.'
                : null,
            'connect_url'    => $expiringSoon ? self::connectUrl() : null,
            'scopes'         => self::SCOPES,
        ], fn ($value) => $value !== null);
    }

    public static function react(string $urn, ?string $reaction): array
    {
        $status = self::status();

        if (empty($status['connected'])) {
            return $status;
        }

        $general = self::settings();
        $person = trim((string) $general->linkedin_person_urn);
        $token = trim((string) $general->linkedin_access_token);

        $removed = self::removeReaction($token, $person, $urn);

        if ($reaction === null) {
            return $removed['ok']
                ? ['ok' => true, 'reaction' => null, 'removed' => true, 'entity_urn' => $urn]
                : $removed + ['entity_urn' => $urn];
        }

        $response = Http::withToken($token)
            ->withHeaders(self::apiHeaders())
            ->timeout(30)
            ->post(self::REACTIONS_URL.'?actor='.rawurlencode($person), [
                'root'         => $urn,
                'reactionType' => $reaction,
            ]);

        if (! $response->successful() && $response->status() !== 201 && $response->status() !== 204) {
            Log::warning('LinkedIn reaction failed: HTTP '.$response->status().' '.substr($response->body(), 0, 300));

            return [
                'ok'         => false,
                'reason'     => 'reaction_failed',
                'status'     => $response->status(),
                'response'   => substr($response->body(), 0, 300),
                'entity_urn' => $urn,
            ];
        }

        return ['ok' => true, 'reaction' => $reaction, 'entity_urn' => $urn];
    }

    private static function removeReaction(string $token, string $person, string $urn): array
    {
        $key = '(actor:'.rawurlencode($person).',entity:'.rawurlencode($urn).')';

        $response = Http::withToken($token)
            ->withHeaders(self::apiHeaders())
            ->timeout(30)
            ->delete(self::REACTIONS_URL.'/'.$key);

        if ($response->successful() || in_array($response->status(), [204, 404], true)) {
            return ['ok' => true];
        }

        Log::warning('LinkedIn reaction removal failed: HTTP '.$response->status().' '.substr($response->body(), 0, 300));

        return [
            'ok'       => false,
            'reason'   => 'reaction_remove_failed',
            'status'   => $response->status(),
            'response' => substr($response->body(), 0, 300),
        ];
    }

    public static function thumbnailFor(string $source): ?string
    {
        $uploaded = self::uploadImage($source);

        return empty($uploaded['ok']) ? null : $uploaded['image'];
    }

    public static function exchangeCode(string $code): array
    {
        $response = Http::asForm()->timeout(30)->post(self::TOKEN_URL, [
            'grant_type'    => 'authorization_code',
            'code'          => $code,
            'redirect_uri'  => self::redirectUri(),
            'client_id'     => trim((string) config('services.linkedin.client_id')),
            'client_secret' => trim((string) config('services.linkedin.client_secret')),
        ]);

        if (! $response->successful()) {
            Log::warning('LinkedIn token exchange failed: HTTP '.$response->status().' '.substr($response->body(), 0, 300));

            return ['ok' => false, 'error' => 'Token exchange failed with HTTP '.$response->status().'.'];
        }

        $accessToken = (string) $response->json('access_token');
        $expiresIn = (int) $response->json('expires_in');

        if ($accessToken === '') {
            return ['ok' => false, 'error' => 'LinkedIn did not return an access token.'];
        }

        $userinfo = Http::withToken($accessToken)->timeout(30)->get(self::USERINFO_URL);

        if (! $userinfo->successful()) {
            Log::warning('LinkedIn userinfo failed: HTTP '.$userinfo->status().' '.substr($userinfo->body(), 0, 300));

            return ['ok' => false, 'error' => 'Signed in, but could not read the member id (HTTP '.$userinfo->status().').'];
        }

        $sub = trim((string) $userinfo->json('sub'));

        if ($sub === '') {
            return ['ok' => false, 'error' => 'Signed in, but LinkedIn returned no member id.'];
        }

        General::where('id', 1)->update([
            'linkedin_access_token'     => $accessToken,
            'linkedin_token_expires_at' => $expiresIn > 0 ? now()->addSeconds($expiresIn) : null,
            'linkedin_person_urn'       => 'urn:li:person:'.$sub,
        ]);

        return [
            'ok'         => true,
            'as'         => 'urn:li:person:'.$sub,
            'name'       => $userinfo->json('name'),
            'expires_in' => $expiresIn,
        ];
    }

    public static function disconnect(): void
    {
        General::where('id', 1)->update([
            'linkedin_access_token'     => null,
            'linkedin_token_expires_at' => null,
            'linkedin_person_urn'       => null,
        ]);
    }

    public static function articleUrl(int $id): array
    {
        $article = DB::table('articles')->where('id', $id)->first();

        if (! $article) {
            throw new ContentWriteException("No article with id {$id}.");
        }

        if ((int) $article->enable !== 1) {
            throw new ContentWriteException("Article {$id} is not published, so its URL would 404. Publish it first.");
        }

        $slug = self::slug((string) $article->title);

        if ($slug === '') {
            throw new ContentWriteException("Article {$id} has a title that produces an empty slug.");
        }

        return [
            'title'   => (string) $article->title,
            'url'     => self::publicSiteUrl().'/articles/'.$slug,
            'image'   => (string) ($article->image ?? ''),
            'summary' => trim((string) ($article->short_desc ?? '')),
        ];
    }

    public static function slug(string $name): string
    {
        $value = mb_strtolower(Str::ascii($name), 'UTF-8');
        $value = preg_replace('/[^a-z0-9\s-]/', '', $value) ?? '';
        $value = preg_replace('/[\s-]+/', '-', $value) ?? '';

        return trim($value, '-');
    }

    private static function apiHeaders(): array
    {
        return [
            'X-Restli-Protocol-Version' => '2.0.0',
            'LinkedIn-Version'          => self::API_VERSION,
        ];
    }

    private static function confineToDisk(string $absolute, string $root): string
    {
        $real = realpath($absolute);
        $base = realpath($root);

        if ($real === false || $base === false || ! str_starts_with($real, rtrim($base, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR)) {
            throw new ContentWriteException('That path resolves outside its storage directory and was refused.');
        }

        return $real;
    }

    private static function readImage(string $source): array
    {
        $source = trim($source);

        if ($source === '') {
            throw new ContentWriteException('No image source given.');
        }

        if (str_starts_with($source, 'http://') || str_starts_with($source, 'https://')) {
            return SafeUrlFetcher::fetch($source, self::MAX_IMAGE_BYTES);
        }

        $path = uploads_path_for_disk($source);
        $disk = Storage::disk('uploads');

        if ($path === '' || ! str_starts_with($path, 'admin/') || ! $disk->exists($path)) {
            throw new ContentWriteException("No uploaded image at \"{$source}\". Upload it to /mcp/uploads first.");
        }

        $file = self::confineToDisk($disk->path($path), public_path('uploads'));
        $size = filesize($file);

        if ($size === false || $size <= 0) {
            throw new ContentWriteException("\"{$source}\" is empty.");
        }

        if ($size > self::MAX_IMAGE_BYTES) {
            throw new ContentWriteException("\"{$source}\" is larger than the ".round(self::MAX_IMAGE_BYTES / 1048576)."MB image limit.");
        }

        $mime = mime_content_type($file);

        return [(string) file_get_contents($file), $mime ?: 'application/octet-stream'];
    }

    private static function readMediaFile(string $source, array $extensions, int $maxBytes, string $label): array
    {
        $source = trim($source);

        if ($source === '') {
            throw new ContentWriteException("No {$label} source given.");
        }

        if (str_starts_with($source, 'http://') || str_starts_with($source, 'https://')) {
            throw new ContentWriteException(
                "A {$label} cannot be pulled from a URL. Upload it to /mcp/uploads with kind={$label} and pass the returned path."
            );
        }

        if (! media_path_is_allowed($source)) {
            throw new ContentWriteException(
                "\"{$source}\" is not a {$label} upload path. Upload it to /mcp/uploads with kind={$label} and pass the path it returns."
            );
        }

        $path = media_path_for_disk($source);
        $disk = Storage::disk('media');

        if (! $disk->exists($path)) {
            throw new ContentWriteException("No uploaded {$label} at \"{$source}\". Upload it to /mcp/uploads first.");
        }

        $file = self::confineToDisk($disk->path($path), storage_path('app/media'));
        $extension = strtolower((string) pathinfo($file, PATHINFO_EXTENSION));

        if (! in_array($extension, $extensions, true)) {
            throw new ContentWriteException(
                "\"{$source}\" is a .{$extension} file. Accepted ".$label." types are: ".implode(', ', $extensions).'.'
            );
        }

        $size = filesize($file);

        if ($size === false || $size <= 0) {
            throw new ContentWriteException("\"{$source}\" is empty.");
        }

        if ($size > $maxBytes) {
            throw new ContentWriteException(
                "\"{$source}\" is ".round($size / 1048576, 1)."MB, over the ".round($maxBytes / 1048576)."MB {$label} limit."
            );
        }

        return [$file, $size, mime_content_type($file) ?: 'application/octet-stream'];
    }

    public static function uploadImage(string $source): array
    {
        $general = self::settings();
        $token = trim((string) $general->linkedin_access_token);

        [$bytes, $mime] = self::readImage($source);

        $init = Http::withToken($token)
            ->withHeaders(self::apiHeaders())
            ->timeout(30)
            ->post(self::IMAGES_URL.'?action=initializeUpload', [
                'initializeUploadRequest' => ['owner' => trim((string) $general->linkedin_person_urn)],
            ]);

        if (! $init->successful()) {
            Log::warning('LinkedIn image initializeUpload failed: HTTP '.$init->status().' '.substr($init->body(), 0, 300));

            return ['ok' => false, 'reason' => 'image_init_failed', 'status' => $init->status(), 'response' => substr($init->body(), 0, 300)];
        }

        $uploadUrl = (string) $init->json('value.uploadUrl');
        $urn = (string) $init->json('value.image');

        if ($uploadUrl === '' || $urn === '') {
            return ['ok' => false, 'reason' => 'image_init_incomplete', 'message' => 'LinkedIn did not return an upload URL and image URN.'];
        }

        $put = Http::withToken($token)->withBody($bytes, $mime)->timeout(120)->put($uploadUrl);

        if (! $put->successful()) {
            Log::warning('LinkedIn image upload failed: HTTP '.$put->status().' '.substr($put->body(), 0, 300));

            return ['ok' => false, 'reason' => 'image_upload_failed', 'status' => $put->status(), 'response' => substr($put->body(), 0, 300)];
        }

        return ['ok' => true, 'image' => $urn, 'bytes' => strlen($bytes)];
    }

    public static function uploadDocument(string $source): array
    {
        $general = self::settings();
        $token = trim((string) $general->linkedin_access_token);

        [$file, $size, $mime] = self::readMediaFile(
            $source,
            self::DOCUMENT_EXTENSIONS,
            self::MAX_DOCUMENT_BYTES,
            'document'
        );

        $init = Http::withToken($token)
            ->withHeaders(self::apiHeaders())
            ->timeout(30)
            ->post(self::DOCUMENTS_URL.'?action=initializeUpload', [
                'initializeUploadRequest' => ['owner' => trim((string) $general->linkedin_person_urn)],
            ]);

        if (! $init->successful()) {
            Log::warning('LinkedIn document initializeUpload failed: HTTP '.$init->status().' '.substr($init->body(), 0, 300));

            return ['ok' => false, 'reason' => 'document_init_failed', 'status' => $init->status(), 'response' => substr($init->body(), 0, 300)];
        }

        $uploadUrl = (string) $init->json('value.uploadUrl');
        $urn = (string) $init->json('value.document');

        if ($uploadUrl === '' || $urn === '') {
            return ['ok' => false, 'reason' => 'document_init_incomplete', 'message' => 'LinkedIn did not return an upload URL and document URN.'];
        }

        $handle = fopen($file, 'rb');

        if ($handle === false) {
            throw new ContentWriteException('Could not open the uploaded document for reading.');
        }

        try {
            $put = Http::withToken($token)
                ->withBody(Utils::streamFor($handle), $mime)
                ->timeout(600)
                ->put($uploadUrl);
        } finally {
            if (is_resource($handle)) {
                fclose($handle);
            }
        }

        if (! $put->successful()) {
            Log::warning('LinkedIn document upload failed: HTTP '.$put->status().' '.substr($put->body(), 0, 300));

            return ['ok' => false, 'reason' => 'document_upload_failed', 'status' => $put->status(), 'response' => substr($put->body(), 0, 300)];
        }

        return ['ok' => true, 'document' => $urn, 'bytes' => $size];
    }

    public static function uploadVideo(string $source): array
    {
        $general = self::settings();
        $token = trim((string) $general->linkedin_access_token);

        [$file, $size] = self::readMediaFile(
            $source,
            self::VIDEO_EXTENSIONS,
            self::MAX_VIDEO_BYTES,
            'video'
        );

        $init = Http::withToken($token)
            ->withHeaders(self::apiHeaders())
            ->timeout(30)
            ->post(self::VIDEOS_URL.'?action=initializeUpload', [
                'initializeUploadRequest' => [
                    'owner'           => trim((string) $general->linkedin_person_urn),
                    'fileSizeBytes'   => $size,
                    'uploadCaptions'  => false,
                    'uploadThumbnail' => false,
                ],
            ]);

        if (! $init->successful()) {
            Log::warning('LinkedIn video initializeUpload failed: HTTP '.$init->status().' '.substr($init->body(), 0, 300));

            return ['ok' => false, 'reason' => 'video_init_failed', 'status' => $init->status(), 'response' => substr($init->body(), 0, 300)];
        }

        $urn = (string) $init->json('value.video');
        $uploadToken = (string) $init->json('value.uploadToken');
        $instructions = $init->json('value.uploadInstructions');

        if ($urn === '' || ! is_array($instructions) || $instructions === []) {
            return ['ok' => false, 'reason' => 'video_init_incomplete', 'message' => 'LinkedIn did not return upload instructions and a video URN.'];
        }

        $handle = fopen($file, 'rb');

        if ($handle === false) {
            throw new ContentWriteException('Could not open the uploaded video for reading.');
        }

        $parts = [];

        try {
            $outcome = self::uploadVideoParts($handle, $token, $instructions, $parts);
        } finally {
            if (is_resource($handle)) {
                fclose($handle);
            }
        }

        if ($outcome !== null) {
            return $outcome;
        }

        $finalize = Http::withToken($token)
            ->withHeaders(self::apiHeaders())
            ->timeout(60)
            ->post(self::VIDEOS_URL.'?action=finalizeUpload', [
                'finalizeUploadRequest' => [
                    'video'           => $urn,
                    'uploadToken'     => $uploadToken,
                    'uploadedPartIds' => $parts,
                ],
            ]);

        if (! $finalize->successful()) {
            Log::warning('LinkedIn video finalizeUpload failed: HTTP '.$finalize->status().' '.substr($finalize->body(), 0, 300));

            return ['ok' => false, 'reason' => 'video_finalize_failed', 'status' => $finalize->status(), 'response' => substr($finalize->body(), 0, 300)];
        }

        return ['ok' => true, 'video' => $urn, 'bytes' => $size, 'parts' => count($parts)];
    }

    private static function uploadVideoParts($handle, string $token, array $instructions, array &$parts): ?array
    {
        foreach ($instructions as $index => $instruction) {
            $url = (string) ($instruction['uploadUrl'] ?? '');
            $first = (int) ($instruction['firstByte'] ?? 0);
            $last = (int) ($instruction['lastByte'] ?? 0);
            $length = $last - $first + 1;

            if ($url === '' || $length <= 0) {
                return ['ok' => false, 'reason' => 'video_instruction_invalid', 'message' => 'LinkedIn returned an unusable upload instruction for part '.$index.'.'];
            }

            if (fseek($handle, $first) !== 0) {
                return ['ok' => false, 'reason' => 'video_read_failed', 'message' => 'Could not seek to byte '.$first.' of the video.'];
            }

            $chunk = fread($handle, $length);

            if ($chunk === false || $chunk === '') {
                return ['ok' => false, 'reason' => 'video_read_failed', 'message' => 'Could not read part '.$index.' of the video.'];
            }

            $put = Http::withToken($token)
                ->withBody($chunk, 'application/octet-stream')
                ->timeout(600)
                ->put($url);

            unset($chunk);

            if (! $put->successful()) {
                Log::warning('LinkedIn video part upload failed: HTTP '.$put->status().' '.substr($put->body(), 0, 300));

                return ['ok' => false, 'reason' => 'video_upload_failed', 'part' => $index, 'status' => $put->status(), 'response' => substr($put->body(), 0, 300)];
            }

            $etag = (string) ($put->header('ETag') ?: $put->header('etag'));

            if ($etag === '') {
                return ['ok' => false, 'reason' => 'video_etag_missing', 'message' => 'LinkedIn did not return an ETag for part '.$index.'.'];
            }

            $parts[] = trim($etag, '"');
        }

        return null;
    }

    public static function share(string $commentary, string $visibility = 'PUBLIC', ?array $content = null, array $meta = []): array
    {
        $status = self::status();

        if (empty($status['connected'])) {
            return $status;
        }

        $general = self::settings();

        $response = Http::withToken(trim((string) $general->linkedin_access_token))
            ->withHeaders(self::apiHeaders())
            ->timeout(30)
            ->post(self::POSTS_URL, [
                'author'                    => trim((string) $general->linkedin_person_urn),
                'commentary'                => $commentary,
                'visibility'                => $visibility,
                'distribution'              => [
                    'feedDistribution'               => 'MAIN_FEED',
                    'targetEntities'                 => [],
                    'thirdPartyDistributionChannels' => [],
                ],
                'lifecycleState'            => 'PUBLISHED',
                'isReshareDisabledByAuthor' => false,
            ] + ($content ? ['content' => $content] : []));

        if ($response->status() === 401) {
            return [
                'ok'          => false,
                'reason'      => 'unauthorized',
                'message'     => 'LinkedIn rejected the stored token. Open connect_url in a browser to reconnect, then try again.',
                'connect_url' => self::connectUrl(),
            ];
        }

        if (! $response->successful()) {
            Log::warning('LinkedIn post failed: HTTP '.$response->status().' '.substr($response->body(), 0, 500));

            return [
                'ok'       => false,
                'reason'   => 'post_failed',
                'status'   => $response->status(),
                'message'  => 'LinkedIn returned HTTP '.$response->status().'.',
                'response' => substr($response->body(), 0, 500),
            ];
        }

        $urn = $response->header('x-restli-id');
        $record = $urn ? self::record($urn, $commentary, $visibility, $meta) : null;

        return [
            'ok'         => true,
            'post_urn'   => $urn ?: null,
            'post_url'   => $urn ? 'https://www.linkedin.com/feed/update/'.$urn.'/' : null,
            'post_id'    => $record?->id,
            'visibility' => $visibility,
            'characters' => mb_strlen($commentary),
        ];
    }

    private static function record(string $urn, string $commentary, string $visibility, array $meta): ?LinkedInPost
    {
        try {
            return LinkedInPost::updateOrCreate(
                ['urn' => $urn],
                [
                    'article_id' => $meta['article_id'] ?? null,
                    'media_type' => $meta['media_type'] ?? 'text',
                    'visibility' => $visibility,
                    'commentary' => $commentary,
                    'posted_at'  => now(),
                ]
            );
        } catch (\Throwable $e) {
            Log::warning('LinkedIn post published but could not be recorded locally: '.$e->getMessage());

            return null;
        }
    }

    public static function findPost(?int $id, ?string $urn): LinkedInPost
    {
        if ($id) {
            $post = LinkedInPost::find($id);

            if (! $post) {
                throw new ContentWriteException("No recorded LinkedIn post with id {$id}. Call list_linkedin_posts to see what is on record.");
            }

            return $post;
        }

        $urn = trim((string) $urn);

        if ($urn === '') {
            throw new ContentWriteException('Pass either id or urn to identify the post.');
        }

        $post = LinkedInPost::where('urn', $urn)->first();

        if (! $post) {
            throw new ContentWriteException("\"{$urn}\" is not a recorded LinkedIn post. Only posts made through this tool are on record.");
        }

        return $post;
    }

    public static function deletePost(LinkedInPost $post): array
    {
        $status = self::status();

        if (empty($status['connected'])) {
            return $status;
        }

        if ($post->isDeleted()) {
            return [
                'ok'         => false,
                'reason'     => 'already_deleted',
                'message'    => 'That post was already deleted on '.$post->deleted_at->toDateTimeString().'.',
                'post_urn'   => $post->urn,
            ];
        }

        $response = Http::withToken(trim((string) self::settings()->linkedin_access_token))
            ->withHeaders(self::apiHeaders())
            ->timeout(30)
            ->delete(self::POSTS_URL.'/'.rawurlencode($post->urn));

        if ($response->status() === 404) {
            $post->forceFill(['deleted_at' => now()])->save();

            return [
                'ok'       => false,
                'reason'   => 'not_found',
                'message'  => 'LinkedIn has no such post. It was probably deleted there already; the local record is now marked deleted too.',
                'post_urn' => $post->urn,
            ];
        }

        if (! $response->successful() && $response->status() !== 204) {
            Log::warning('LinkedIn post delete failed: HTTP '.$response->status().' '.substr($response->body(), 0, 300));

            return [
                'ok'       => false,
                'reason'   => 'delete_failed',
                'status'   => $response->status(),
                'response' => substr($response->body(), 0, 300),
                'post_urn' => $post->urn,
            ];
        }

        $post->forceFill(['deleted_at' => now()])->save();

        return [
            'ok'         => true,
            'deleted'    => true,
            'post_urn'   => $post->urn,
            'post_id'    => $post->id,
            'article_id' => $post->article_id,
        ];
    }

    public static function editPost(LinkedInPost $post, string $commentary): array
    {
        $status = self::status();

        if (empty($status['connected'])) {
            return $status;
        }

        if ($post->isDeleted()) {
            return [
                'ok'       => false,
                'reason'   => 'already_deleted',
                'message'  => 'That post was deleted on '.$post->deleted_at->toDateTimeString().', so there is nothing to edit.',
                'post_urn' => $post->urn,
            ];
        }

        $response = Http::withToken(trim((string) self::settings()->linkedin_access_token))
            ->withHeaders(self::apiHeaders() + ['X-RestLi-Method' => 'PARTIAL_UPDATE'])
            ->timeout(30)
            ->post(self::POSTS_URL.'/'.rawurlencode($post->urn), [
                'patch' => ['$set' => ['commentary' => $commentary]],
            ]);

        if (! $response->successful() && $response->status() !== 204) {
            Log::warning('LinkedIn post edit failed: HTTP '.$response->status().' '.substr($response->body(), 0, 300));

            return [
                'ok'       => false,
                'reason'   => 'edit_failed',
                'status'   => $response->status(),
                'response' => substr($response->body(), 0, 300),
                'post_urn' => $post->urn,
            ];
        }

        $post->forceFill(['commentary' => $commentary, 'edited_at' => now()])->save();

        return [
            'ok'         => true,
            'edited'     => true,
            'post_urn'   => $post->urn,
            'post_id'    => $post->id,
            'post_url'   => $post->url(),
            'characters' => mb_strlen($commentary),
        ];
    }
}
