<?php

namespace App\Services;

use App\Exceptions\ContentWriteException;
use App\Models\Article;
use App\Models\Category;
use App\Models\Experience;
use App\Models\Home;
use App\Models\Project;
use App\Models\ProjectCategory;
use App\Models\Section;
use App\Models\Service;
use App\Models\Skill;
use App\Models\Testimonial;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class ContentWriteService
{
    public static function createArticle(array $input): array
    {
        self::validate($input, [
            'title'       => ['required', 'string', 'max:55'],
            'short_desc'  => ['nullable', 'string', 'max:255'],
            'description' => ['required', 'string'],
        ]);

        $article = new Article();
        $article->enable     = self::boolInput($input, 'enable', true) ? 1 : 0;
        $article->title      = $input['title'];
        $article->short_desc = $input['short_desc'] ?? null;
        $article->description = self::sanitizeHtml($input['description']);
        $article->image      = self::normalizeImage($input['image'] ?? null);
        $article->category_id = self::resolveCategoryId('article', $input, true);

        if ($created = self::parseDate($input, 'created_at')) {
            $article->created_at = $created;
        }

        $article->save();
        EmbeddingService::embedRow('articles', $article->id);

        return self::showArticle($article->id);
    }

    public static function updateArticle(int $id, array $input): array
    {
        $article = Article::find($id);
        if (! $article) {
            throw new ContentWriteException("No article with id {$id}.");
        }

        self::validate($input, [
            'title'       => ['sometimes', 'required', 'string', 'max:55'],
            'short_desc'  => ['nullable', 'string', 'max:255'],
            'description' => ['sometimes', 'required', 'string'],
        ]);

        $update = [];
        if (array_key_exists('enable', $input))      $update['enable'] = self::boolInput($input, 'enable', true) ? 1 : 0;
        if (array_key_exists('title', $input))       $update['title'] = $input['title'];
        if (array_key_exists('short_desc', $input))  $update['short_desc'] = $input['short_desc'];
        if (array_key_exists('description', $input)) $update['description'] = self::sanitizeHtml($input['description']);

        if (array_key_exists('image', $input)) {
            $update['image'] = self::normalizeImage($input['image']);

            if ($update['image'] !== $article->image) {
                self::deleteUpload($article->image);
            }
        }

        $categoryId = self::resolveCategoryId('article', $input, false);
        if ($categoryId !== null) {
            $update['category_id'] = $categoryId;
        }

        if ($created = self::parseDate($input, 'created_at')) {
            $update['created_at'] = $created;
        }

        if (empty($update)) {
            throw new ContentWriteException('Nothing to update: provide at least one field to change.');
        }

        Article::where('id', $id)->update($update);
        EmbeddingService::embedRow('articles', $id);

        return self::showArticle($id);
    }

    public static function deleteArticle(int $id): array
    {
        $article = Article::find($id);
        if (! $article) {
            throw new ContentWriteException("No article with id {$id}.");
        }

        $title = $article->title;
        self::deleteUpload($article->image);

        Article::where('id', $id)->delete();
        EmbeddingService::deleteRow('articles', $id);

        return ['deleted' => true, 'id' => $id, 'title' => $title];
    }

    public static function showArticle(int $id): array
    {
        $row = DB::table('articles')->where('id', $id)->first();
        if (! $row) {
            throw new ContentWriteException("No article with id {$id}.");
        }

        return [
            'id'          => $row->id,
            'enable'      => (bool) $row->enable,
            'title'       => $row->title,
            'short_desc'  => $row->short_desc,
            'description' => $row->description,
            'image'       => $row->image,
            'created_by'  => self::siteOwnerName(),
            'category_id' => $row->category_id,
            'category'    => DB::table('article_categories')->where('id', $row->category_id)->value('name'),
            'created_at'  => (string) $row->created_at,
            'updated_at'  => (string) $row->updated_at,
        ];
    }

    public static function createProject(array $input): array
    {
        self::validate($input, [
            'title'       => ['required', 'string', 'max:55'],
            'short_desc'  => ['nullable', 'string', 'max:110'],
            'description' => ['required', 'string'],
        ]);

        $project = new Project();
        $project->enable      = self::boolInput($input, 'enable', true) ? 1 : 0;
        $project->title       = $input['title'];

        $project->short_desc  = $input['short_desc'] ?? '';
        $project->description = self::sanitizeHtml($input['description']);
        $project->image       = self::normalizeImage($input['image'] ?? null);
        $project->category_id = self::resolveCategoryId('project', $input, true);

        if ($created = self::parseDate($input, 'created_at')) {
            $project->created_at = $created;
        }

        $project->save();
        EmbeddingService::embedRow('projects', $project->id);

        return self::showProject($project->id);
    }

    public static function updateProject(int $id, array $input): array
    {
        $project = Project::find($id);
        if (! $project) {
            throw new ContentWriteException("No project with id {$id}.");
        }

        self::validate($input, [
            'title'       => ['sometimes', 'required', 'string', 'max:55'],
            'short_desc'  => ['nullable', 'string', 'max:110'],
            'description' => ['sometimes', 'required', 'string'],
        ]);

        $update = [];
        if (array_key_exists('enable', $input))      $update['enable'] = self::boolInput($input, 'enable', true) ? 1 : 0;
        if (array_key_exists('title', $input))       $update['title'] = $input['title'];
        if (array_key_exists('short_desc', $input))  $update['short_desc'] = $input['short_desc'] ?? '';
        if (array_key_exists('description', $input)) $update['description'] = self::sanitizeHtml($input['description']);

        if (array_key_exists('image', $input)) {
            $update['image'] = self::normalizeImage($input['image']);

            if ($update['image'] !== $project->image) {
                self::deleteUpload($project->image);
            }
        }

        $categoryId = self::resolveCategoryId('project', $input, false);
        if ($categoryId !== null) {
            $update['category_id'] = $categoryId;
        }

        if ($created = self::parseDate($input, 'created_at')) {
            $update['created_at'] = $created;
        }

        if (empty($update)) {
            throw new ContentWriteException('Nothing to update: provide at least one field to change.');
        }

        Project::where('id', $id)->update($update);
        EmbeddingService::embedRow('projects', $id);

        return self::showProject($id);
    }

    public static function deleteProject(int $id): array
    {
        $project = Project::find($id);
        if (! $project) {
            throw new ContentWriteException("No project with id {$id}.");
        }

        $title = $project->title;
        self::deleteUpload($project->image);

        Project::where('id', $id)->delete();
        EmbeddingService::deleteRow('projects', $id);

        return ['deleted' => true, 'id' => $id, 'title' => $title];
    }

    public static function showProject(int $id): array
    {
        $row = DB::table('projects')->where('id', $id)->first();
        if (! $row) {
            throw new ContentWriteException("No project with id {$id}.");
        }

        return [
            'id'          => $row->id,
            'enable'      => (bool) $row->enable,
            'title'       => $row->title,
            'short_desc'  => $row->short_desc,
            'description' => $row->description,
            'image'       => $row->image,
            'created_by'  => self::siteOwnerName(),
            'category_id' => $row->category_id,
            'category'    => DB::table('project_categories')->where('id', $row->category_id)->value('name'),
            'created_at'  => (string) $row->created_at,
            'updated_at'  => (string) $row->updated_at,
        ];
    }

    public static function createCategory(string $kind, array $input): array
    {
        self::validate($input, ['name' => ['required', 'string', 'max:55']]);

        $model = self::categoryModel($kind);
        $table = self::categoryTable($kind);

        if (DB::table($table)->whereRaw('LOWER(name) = ?', [mb_strtolower($input['name'])])->exists()) {
            throw new ContentWriteException("A {$kind} category named \"{$input['name']}\" already exists.");
        }

        $category = new $model();
        $category->name = $input['name'];

        if ($created = self::parseDate($input, 'created_at')) {
            $category->created_at = $created;
        }

        $category->save();

        return ['id' => $category->id, 'name' => $category->name, 'kind' => $kind];
    }

    public static function updateCategory(string $kind, int $id, array $input): array
    {
        self::validate($input, ['name' => ['required', 'string', 'max:55']]);

        $model = self::categoryModel($kind);
        $table = self::categoryTable($kind);

        if (! DB::table($table)->where('id', $id)->exists()) {
            throw new ContentWriteException("No {$kind} category with id {$id}.");
        }

        $clash = DB::table($table)
            ->whereRaw('LOWER(name) = ?', [mb_strtolower($input['name'])])
            ->where('id', '!=', $id)
            ->exists();
        if ($clash) {
            throw new ContentWriteException("Another {$kind} category is already named \"{$input['name']}\".");
        }

        $model::where('id', $id)->update(['name' => $input['name']]);

        return ['id' => $id, 'name' => $input['name'], 'kind' => $kind];
    }

    public static function deleteCategory(string $kind, int $id): array
    {
        $model = self::categoryModel($kind);
        $table = self::categoryTable($kind);

        $category = DB::table($table)->where('id', $id)->first();
        if (! $category) {
            throw new ContentWriteException("No {$kind} category with id {$id}.");
        }

        $contentTable = $kind === 'article' ? 'articles' : 'projects';
        $inUse = DB::table($contentTable)->where('category_id', $id)->count();
        if ($inUse > 0) {
            $noun = $kind === 'article' ? 'article' : 'project';
            throw new ContentWriteException(
                "Cannot delete {$kind} category \"{$category->name}\": {$inUse} {$noun}(s) still use it. "
                . "Move or delete them first, or reassign them to another category."
            );
        }

        $model::where('id', $id)->delete();

        return ['deleted' => true, 'id' => $id, 'name' => $category->name, 'kind' => $kind];
    }

    public static function listCategories(string $kind): array
    {
        $table = self::categoryTable($kind);
        $contentTable = $kind === 'article' ? 'articles' : 'projects';

        return DB::table($table)->orderBy('name')->get()->map(fn ($c) => [
            'id'    => $c->id,
            'name'  => $c->name,
            'count' => DB::table($contentTable)->where('category_id', $c->id)->count(),
        ])->all();
    }

    public static function createSkill(array $input): array
    {
        self::validate($input, [
            'title' => ['required', 'string', 'max:55'],
            'type'  => ['required', 'string', 'in:design,development'],
        ]);

        $skill = new Skill();
        $skill->type  = $input['type'];
        $skill->title = $input['title'];
        $skill->order = self::nextOrder('skill', ['type' => $input['type']]);
        $skill->save();

        EmbeddingService::embedRow('skill', $skill->id);

        return self::showAbout('skill', $skill->id);
    }

    public static function updateSkill(int $id, array $input): array
    {
        if (! Skill::find($id)) {
            throw new ContentWriteException("No skill with id {$id}.");
        }

        self::validate($input, [
            'title' => ['sometimes', 'required', 'string', 'max:55'],
            'type'  => ['sometimes', 'required', 'string', 'in:design,development'],
            'order' => ['nullable', 'integer', 'min:1'],
        ]);

        $update = [];
        if (array_key_exists('title', $input)) $update['title'] = $input['title'];
        if (array_key_exists('type', $input))  $update['type'] = $input['type'];
        if (array_key_exists('order', $input) && $input['order'] !== null) $update['order'] = (int) $input['order'];

        if (empty($update)) {
            throw new ContentWriteException('Nothing to update: provide at least one field to change.');
        }

        Skill::where('id', $id)->update($update);
        EmbeddingService::embedRow('skill', $id);

        return self::showAbout('skill', $id);
    }

    public static function createExperience(array $input): array
    {
        self::validate($input, [
            'title'       => ['required', 'string', 'max:55'],
            'period'      => ['required', 'string', 'max:55'],
            'description' => ['required', 'string', 'max:255'],
            'type'        => ['required', 'string', 'in:education,employment'],
        ]);

        $experience = new Experience();
        $experience->type        = $input['type'];
        $experience->title       = $input['title'];
        $experience->period      = $input['period'];
        $experience->description = $input['description'];
        $experience->order       = self::nextOrder('experience', ['type' => $input['type']]);
        $experience->save();

        EmbeddingService::embedRow('experience', $experience->id);

        return self::showAbout('experience', $experience->id);
    }

    public static function updateExperience(int $id, array $input): array
    {
        if (! Experience::find($id)) {
            throw new ContentWriteException("No experience with id {$id}.");
        }

        self::validate($input, [
            'title'       => ['sometimes', 'required', 'string', 'max:55'],
            'period'      => ['sometimes', 'required', 'string', 'max:55'],
            'description' => ['sometimes', 'required', 'string', 'max:255'],
            'type'        => ['sometimes', 'required', 'string', 'in:education,employment'],
            'order'       => ['nullable', 'integer', 'min:1'],
        ]);

        $update = [];
        foreach (['title', 'period', 'description', 'type'] as $field) {
            if (array_key_exists($field, $input)) $update[$field] = $input[$field];
        }
        if (array_key_exists('order', $input) && $input['order'] !== null) $update['order'] = (int) $input['order'];

        if (empty($update)) {
            throw new ContentWriteException('Nothing to update: provide at least one field to change.');
        }

        Experience::where('id', $id)->update($update);
        EmbeddingService::embedRow('experience', $id);

        return self::showAbout('experience', $id);
    }

    public static function createService(array $input): array
    {
        self::validate($input, [
            'title'       => ['required', 'string', 'max:55'],
            'description' => ['required', 'string', 'max:255'],
            'info'        => ['required', 'string', 'max:510'],
        ]);

        $service = new Service();
        $service->title       = $input['title'];
        $service->description = $input['description'];
        $service->info        = $input['info'];
        $service->order       = self::nextOrder('service');
        $service->save();

        EmbeddingService::embedRow('service', $service->id);

        return self::showAbout('service', $service->id);
    }

    public static function updateService(int $id, array $input): array
    {
        if (! Service::find($id)) {
            throw new ContentWriteException("No service with id {$id}.");
        }

        self::validate($input, [
            'title'       => ['sometimes', 'required', 'string', 'max:55'],
            'description' => ['sometimes', 'required', 'string', 'max:255'],
            'info'        => ['sometimes', 'required', 'string', 'max:510'],
            'order'       => ['nullable', 'integer', 'min:1'],
        ]);

        $update = [];
        foreach (['title', 'description', 'info'] as $field) {
            if (array_key_exists($field, $input)) $update[$field] = $input[$field];
        }
        if (array_key_exists('order', $input) && $input['order'] !== null) $update['order'] = (int) $input['order'];

        if (empty($update)) {
            throw new ContentWriteException('Nothing to update: provide at least one field to change.');
        }

        Service::where('id', $id)->update($update);
        EmbeddingService::embedRow('service', $id);

        return self::showAbout('service', $id);
    }

    public static function createTestimonial(array $input): array
    {
        self::validate($input, [
            'name'    => ['required', 'string', 'max:55'],
            'company' => ['required', 'string', 'max:55'],
            'text'    => ['required', 'string', 'max:255'],
        ]);

        $testimonial = new Testimonial();
        $testimonial->name    = $input['name'];
        $testimonial->company = $input['company'];
        $testimonial->{self::testimonialBodyColumn()} = $input['text'];
        $testimonial->order   = self::nextOrder('testimonial');
        $testimonial->save();

        EmbeddingService::embedRow('testimonial', $testimonial->id);

        return self::showAbout('testimonial', $testimonial->id);
    }

    public static function updateTestimonial(int $id, array $input): array
    {
        if (! Testimonial::find($id)) {
            throw new ContentWriteException("No testimonial with id {$id}.");
        }

        self::validate($input, [
            'name'    => ['sometimes', 'required', 'string', 'max:55'],
            'company' => ['sometimes', 'required', 'string', 'max:55'],
            'text'    => ['sometimes', 'required', 'string', 'max:255'],
            'order'   => ['nullable', 'integer', 'min:1'],
        ]);

        $update = [];
        foreach (['name', 'company'] as $field) {
            if (array_key_exists($field, $input)) $update[$field] = $input[$field];
        }
        if (array_key_exists('text', $input)) $update[self::testimonialBodyColumn()] = $input['text'];
        if (array_key_exists('order', $input) && $input['order'] !== null) $update['order'] = (int) $input['order'];

        if (empty($update)) {
            throw new ContentWriteException('Nothing to update: provide at least one field to change.');
        }

        Testimonial::where('id', $id)->update($update);
        EmbeddingService::embedRow('testimonial', $id);

        return self::showAbout('testimonial', $id);
    }

    public static function deleteAbout(string $kind, int $id): array
    {
        $table = self::aboutTable($kind);

        $row = DB::table($table)->where('id', $id)->first();
        if (! $row) {
            throw new ContentWriteException("No {$kind} with id {$id}.");
        }

        DB::table($table)->where('id', $id)->delete();
        EmbeddingService::deleteRow($kind, $id);

        $scope = property_exists($row, 'type') ? ['type' => $row->type] : [];
        self::resequence($table, $scope);

        return ['deleted' => true, 'id' => $id, 'kind' => $kind];
    }

    public static function listAbout(string $kind): array
    {
        $table = self::aboutTable($kind);

        return DB::table($table)->orderBy('order')->get()
            ->map(fn ($r) => self::mapAbout($kind, (array) $r))
            ->all();
    }

    public static function showAbout(string $kind, int $id): array
    {
        $table = self::aboutTable($kind);
        $row = DB::table($table)->where('id', $id)->first();
        if (! $row) {
            throw new ContentWriteException("No {$kind} with id {$id}.");
        }

        return self::mapAbout($kind, (array) $row);
    }

    public static function reorderAbout(string $kind, int $id, int $position): array
    {
        $table = self::aboutTable($kind);

        $row = DB::table($table)->where('id', $id)->first();
        if (! $row) {
            throw new ContentWriteException("No {$kind} with id {$id}.");
        }
        if ($position < 1) {
            throw new ContentWriteException('Position must be 1 or greater.');
        }

        $query = DB::table($table);
        if (property_exists($row, 'type')) {
            $query->where('type', $row->type);
        }
        $siblings = $query->orderBy('order')->pluck('id')->all();

        $siblings = array_values(array_filter($siblings, fn ($sid) => (int) $sid !== $id));
        $position = min($position, count($siblings) + 1);
        array_splice($siblings, $position - 1, 0, [$id]);

        foreach ($siblings as $index => $siblingId) {
            DB::table($table)->where('id', $siblingId)->update(['order' => $index + 1]);
        }

        return self::showAbout($kind, $id);
    }

    public static function showHome(): array
    {
        $home = DB::table('page_home')->where('id', 1)->first();
        if (! $home) {
            throw new ContentWriteException('Home page row not found. Run: php artisan db:seed');
        }

        return ['title' => $home->title, 'description' => $home->description];
    }

    public static function updateHome(array $input): array
    {
        self::validate($input, [
            'title'       => ['sometimes', 'required', 'string', 'max:75'],
            'description' => ['nullable', 'string', 'max:5000'],
        ]);

        $update = [];
        if (array_key_exists('title', $input))       $update['title'] = $input['title'];
        if (array_key_exists('description', $input)) $update['description'] = $input['description'];

        if (empty($update)) {
            throw new ContentWriteException('Nothing to update: provide title and/or description.');
        }

        Home::where('id', 1)->update($update);

        return self::showHome();
    }

    public const SECTION_FLAGS = [
        'about_enable', 'experience_enable', 'skills_enable', 'testimonial_enable',
        'services_enable', 'projects_enable', 'articles_enable', 'terminal_enable',
        'contribute_enable',
    ];

    public const SECTION_ORDERS = [
        'about_experience_order', 'about_services_order',
        'about_skills_order', 'about_testimonial_order',
    ];

    public static function showSections(): array
    {
        $section = DB::table('page_section')->where('id', 1)->first();
        if (! $section) {
            throw new ContentWriteException('Section row not found. Run: php artisan db:seed');
        }

        $out = [];
        foreach (self::SECTION_FLAGS as $flag) {
            if (property_exists($section, $flag)) $out[$flag] = (bool) $section->$flag;
        }
        foreach (self::SECTION_ORDERS as $order) {
            if (property_exists($section, $order)) $out[$order] = (int) $section->$order;
        }

        return $out;
    }

    public static function updateSections(array $input): array
    {
        $update = [];
        foreach (self::SECTION_FLAGS as $flag) {
            if (array_key_exists($flag, $input)) {
                $update[$flag] = self::boolInput($input, $flag, true) ? 1 : 0;
            }
        }
        foreach (self::SECTION_ORDERS as $order) {
            if (array_key_exists($order, $input) && $input[$order] !== null) {
                $update[$order] = max(0, (int) $input[$order]);
            }
        }

        if (empty($update)) {
            throw new ContentWriteException('Nothing to update: provide at least one section flag or order.');
        }

        Section::where('id', 1)->update($update);

        return self::showSections();
    }

    private static function validate(array $input, array $rules): void
    {
        $validator = Validator::make($input, $rules);
        if ($validator->fails()) {
            throw new ContentWriteException($validator->errors()->first());
        }
    }

    private static function boolInput(array $input, string $key, bool $default): bool
    {
        if (! array_key_exists($key, $input) || $input[$key] === null) {
            return $default;
        }

        $value = $input[$key];
        if (is_bool($value)) return $value;
        if (is_numeric($value)) return (int) $value === 1;

        return in_array(mb_strtolower(trim((string) $value)), ['1', 'true', 'yes', 'on'], true);
    }

    private static function nonEmpty(?string $value): ?string
    {
        $value = trim((string) $value);
        return $value === '' ? null : $value;
    }

    private static function sanitizeHtml(string $html): string
    {
        $dangerous = 'script|style|iframe|object|embed|form';

        $html = preg_replace('#<\s*(' . $dangerous . ')\b[^>]*>.*?<\s*/\s*\1\s*>#is', '', $html) ?? $html;

        $html = preg_replace('#<\s*/?\s*(' . $dangerous . ')\b[^>]*>#i', '', $html) ?? $html;

        $html = preg_replace('/\son\w+\s*=\s*(?:"[^"]*"|\'[^\']*\'|[^\s>]+)/i', '', $html) ?? $html;

        $html = preg_replace('/\b(href|src)\s*=\s*(["\'])\s*javascript:[^"\']*\2/i', '$1=$2#$2', $html) ?? $html;

        return $html;
    }

    private static function siteOwnerName(): string
    {
        return self::nonEmpty(User::find(1)?->name) ?? 'Admin';
    }

    private static function parseDate(array $input, string $key): ?string
    {
        if (! array_key_exists($key, $input) || $input[$key] === null) {
            return null;
        }

        $raw = trim((string) $input[$key]);
        if ($raw === '') {
            return null;
        }

        $candidate = preg_replace('/(?:Z|[+-]\d{2}:?\d{2})$/', '', $raw);

        $pattern = '/^(\d{4})-(\d{1,2})-(\d{1,2})(?:[ T](\d{1,2}):(\d{2})(?::(\d{2}))?)?$/';
        if (! preg_match($pattern, $candidate, $m)) {
            throw self::badDate($key, $raw);
        }

        [$year, $month, $day] = [(int) $m[1], (int) $m[2], (int) $m[3]];
        [$hour, $minute, $second] = [(int) ($m[4] ?? 0), (int) ($m[5] ?? 0), (int) ($m[6] ?? 0)];

        if (! checkdate($month, $day, $year) || $hour > 23 || $minute > 59 || $second > 59) {
            throw self::badDate($key, $raw);
        }

        return Carbon::create($year, $month, $day, $hour, $minute, $second)->toDateTimeString();
    }

    private static function badDate(string $key, string $raw): ContentWriteException
    {
        return new ContentWriteException(
            "Could not read {$key} \"{$raw}\". Use an absolute date: YYYY-MM-DD or YYYY-MM-DD HH:MM:SS. "
            . "Relative values like \"today\" or \"last tuesday\" are not accepted — work out the date first."
        );
    }

    private static function normalizeImage(?string $image): string
    {
        $image = trim((string) $image);
        if ($image === '') {
            return '';
        }

        if (str_starts_with($image, 'http://') || str_starts_with($image, 'https://')) {
            return $image;
        }

        $path = uploads_path_for_disk($image);
        if ($path === '' || ! str_starts_with($path, 'admin/')) {
            throw new ContentWriteException(
                "Image must be an absolute URL or a path under uploads/admin/, e.g. uploads/admin/articles/foo.png. Got \"{$image}\"."
            );
        }

        return 'uploads/' . $path;
    }

    private static function deleteUpload(?string $image): void
    {
        if (empty($image) || ! uploads_path_safe_to_delete($image)) {
            return;
        }

        $disk = Storage::disk('uploads');
        $path = uploads_path_for_disk($image);
        if ($path !== '' && $disk->exists($path)) {
            $disk->delete($path);
        }
    }

    private static function resolveCategoryId(string $kind, array $input, bool $required): ?int
    {
        $table = self::categoryTable($kind);

        $id = $input['category_id'] ?? null;
        if ($id !== null && $id !== '') {
            $id = (int) $id;
            if (! DB::table($table)->where('id', $id)->exists()) {
                throw new ContentWriteException(
                    "No {$kind} category with id {$id}. Available: " . self::categoryHint($table)
                );
            }
            return $id;
        }

        $name = self::nonEmpty($input['category'] ?? null);
        if ($name !== null) {
            $matches = DB::table($table)
                ->whereRaw('LOWER(name) = ?', [mb_strtolower($name)])
                ->pluck('id')->all();

            if (count($matches) === 1) {
                return (int) $matches[0];
            }
            if (count($matches) > 1) {
                throw new ContentWriteException(
                    "More than one {$kind} category is named \"{$name}\". Pass category_id instead."
                );
            }
            throw new ContentWriteException(
                "No {$kind} category named \"{$name}\". Available: " . self::categoryHint($table)
                . ". Create it first if you need a new one."
            );
        }

        if ($required) {
            throw new ContentWriteException(
                "A category is required. Pass category_id or category (name). Available: " . self::categoryHint($table)
            );
        }

        return null;
    }

    private static function categoryHint(string $table): string
    {
        $names = DB::table($table)->orderBy('name')->pluck('name')->all();
        return empty($names) ? '(none yet)' : implode(', ', $names);
    }

    private static function categoryTable(string $kind): string
    {
        return match ($kind) {
            'article' => 'article_categories',
            'project' => 'project_categories',
            default   => throw new ContentWriteException("Unknown category kind \"{$kind}\"."),
        };
    }

    private static function categoryModel(string $kind): string
    {
        return match ($kind) {
            'article' => Category::class,
            'project' => ProjectCategory::class,
            default   => throw new ContentWriteException("Unknown category kind \"{$kind}\"."),
        };
    }

    private static function aboutTable(string $kind): string
    {
        return match ($kind) {
            'skill', 'experience', 'service', 'testimonial' => $kind,
            default => throw new ContentWriteException(
                "Unknown about kind \"{$kind}\". Use skill, experience, service, or testimonial."
            ),
        };
    }

    private static function testimonialBodyColumn(): string
    {
        static $column = null;

        if ($column === null) {
            $column = Schema::hasColumn('testimonial', 'text') ? 'text' : 'description';
        }

        return $column;
    }

    private static function mapAbout(string $kind, array $row): array
    {
        $base = ['id' => $row['id'], 'order' => $row['order'] ?? null];

        return match ($kind) {
            'skill' => $base + ['type' => $row['type'], 'title' => $row['title']],
            'experience' => $base + [
                'type'        => $row['type'],
                'title'       => $row['title'],
                'period'      => $row['period'],
                'description' => $row['description'],
            ],
            'service' => $base + [
                'title'       => $row['title'],
                'description' => $row['description'],
                'info'        => $row['info'],
            ],
            'testimonial' => $base + [
                'name'    => $row['name'],
                'company' => $row['company'],
                'text'    => $row[self::testimonialBodyColumn()] ?? '',
            ],
            default => $base,
        };
    }

    private static function nextOrder(string $table, array $scope = []): int
    {
        $query = DB::table($table);
        foreach ($scope as $column => $value) {
            $query->where($column, $value);
        }

        return ((int) $query->max('order')) + 1;
    }

    private static function resequence(string $table, array $scope = []): void
    {
        $query = DB::table($table);
        foreach ($scope as $column => $value) {
            $query->where($column, $value);
        }

        foreach ($query->orderBy('order')->pluck('id')->all() as $index => $id) {
            DB::table($table)->where('id', $id)->update(['order' => $index + 1]);
        }
    }
}
