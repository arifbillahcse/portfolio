<?php

namespace App\Console\Commands;

use App\Models\Category;
use App\Models\Project;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use JsonException;
use RuntimeException;
use Throwable;

#[Signature('portfolio:import-catalogue {file? : Path to a prepared project catalogue JSON; defaults to the bundled cloud portfolio} {--dry-run : Validate all entries and thumbnails without changing records or files}')]
#[Description('Import a prepared project catalogue as drafts, preserving existing projects and edits')]
class ImportProjectCatalogue extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $path = $this->argument('file') ?: resource_path('catalogue/cloud-portfolio.json');
        if (! is_file($path) || ! is_readable($path) || filesize($path) > 2 * 1024 * 1024) {
            $this->error('The catalogue must be a readable JSON file of 2 MB or smaller.');

            return self::FAILURE;
        }

        try {
            $catalogue = $this->readCatalogue($path);
            $thumbnails = $this->readThumbnails($catalogue['entries']);
            if ($this->option('dry-run')) {
                $this->info('Valid catalogue: '.count($catalogue['entries']).' entries. No records or files were changed.');

                return self::SUCCESS;
            }

            [$created, $skipped] = DB::transaction(function () use ($catalogue, $thumbnails): array {
                $created = 0;
                $skipped = 0;
                foreach ($catalogue['entries'] as $entry) {
                    $key = $catalogue['catalogue_id'].':'.$entry['source_id'];
                    if (Project::query()->where('catalogue_key', $key)->exists()) {
                        $skipped++;

                        continue;
                    }

                    $category = Category::query()->firstOrCreate(
                        ['type' => 'project', 'slug' => $entry['category']['slug']],
                        ['name' => $entry['category']['name'], 'sort_order' => $entry['category']['order']],
                    );
                    $coverImage = null;
                    if ($entry['thumbnail']) {
                        $image = $thumbnails[$entry['thumbnail']];
                        $coverImage = 'projects/catalogue/'.$catalogue['catalogue_id'].'/'.$entry['source_id'].'-'.substr(hash('sha256', $image), 0, 16).'.jpg';
                        if (! Storage::disk('public')->exists($coverImage) && ! Storage::disk('public')->put($coverImage, $image)) {
                            throw new RuntimeException('Could not save a catalogue thumbnail.');
                        }
                    }

                    $slug = $entry['slug'];
                    $suffix = 0;
                    while (Project::query()->where('slug', $slug)->exists()) {
                        $suffix++;
                        $slug = $entry['slug'].'-catalogue-'.$entry['source_id'].($suffix > 1 ? '-'.$suffix : '');
                    }
                    $project = new Project([
                        'category_id' => $category->id, 'title' => $entry['title'], 'slug' => $slug,
                        'excerpt' => $entry['excerpt'], 'body' => $entry['body'], 'tools' => $entry['tools'],
                        'video_url' => $entry['video_url'], 'cover_image' => $coverImage,
                        'gallery' => [], 'external_url' => null, 'repository_url' => null,
                        'is_published' => false, 'published_at' => null, 'is_featured' => false, 'is_demo' => false,
                        'sort_order' => $entry['source_id'], 'seo_title' => $entry['title'], 'seo_description' => $entry['seo_description'],
                    ]);
                    $project->forceFill(['catalogue_key' => $key, 'catalogue_metadata' => [
                        ...$entry['review'], 'source_file' => $catalogue['source']['filename'],
                        'source_sha256' => $catalogue['source']['sha256'], 'source_project_number' => $entry['source_id'],
                    ]])->save();
                    $created++;
                }

                return [$created, $skipped];
            });
        } catch (JsonException) {
            $this->error('The catalogue is not valid JSON. No project records were imported.');

            return self::FAILURE;
        } catch (ValidationException $exception) {
            foreach ($exception->errors() as $messages) {
                foreach ($messages as $message) {
                    $this->error($message);
                }
            }

            return self::FAILURE;
        } catch (Throwable $exception) {
            report($exception);
            $this->error('Catalogue import failed. No database changes were committed. Check the Laravel log and retry.');

            return self::FAILURE;
        }

        $this->info('Imported '.$created.' draft projects; skipped '.$skipped.' previously imported entries.');
        $this->line('Existing projects and edits were preserved. Review drafts and source notes in Admin > Projects. Nothing was published.');

        return self::SUCCESS;
    }

    /** @return array{schema_version: int, catalogue_id: string, source: array{filename: string, sha256: string}, entries: list<array<string, mixed>>} */
    private function readCatalogue(string $path): array
    {
        $data = json_decode(file_get_contents($path), true, 64, JSON_THROW_ON_ERROR);

        return Validator::make(['catalogue' => $data], [
            'catalogue' => ['required', 'array:schema_version,catalogue_id,source,entries'],
            'catalogue.schema_version' => ['required', 'integer', 'in:1'],
            'catalogue.catalogue_id' => ['required', 'string', 'max:80', 'regex:/\A[a-z0-9]+(?:-[a-z0-9]+)*\z/D'],
            'catalogue.source' => ['required', 'array:filename,sha256'],
            'catalogue.source.filename' => ['required', 'string', 'max:255'],
            'catalogue.source.sha256' => ['required', 'string', 'regex:/\A[a-fA-F0-9]{64}\z/D'],
            'catalogue.entries' => ['required', 'array', 'list', 'min:1', 'max:200'],
            'catalogue.entries.*' => ['required', 'array:source_id,title,slug,category,excerpt,body,tools,video_url,thumbnail,seo_description,review'],
            'catalogue.entries.*.source_id' => ['required', 'integer', 'min:1', 'max:100000', 'distinct'],
            'catalogue.entries.*.title' => ['required', 'string', 'max:255'],
            'catalogue.entries.*.slug' => ['required', 'string', 'max:220', 'regex:/\A[a-z0-9]+(?:-[a-z0-9]+)*\z/D', 'distinct'],
            'catalogue.entries.*.category' => ['required', 'array:name,slug,order'],
            'catalogue.entries.*.category.name' => ['required', 'string', 'max:255'],
            'catalogue.entries.*.category.slug' => ['required', 'string', 'max:255', 'regex:/\A[a-z0-9]+(?:-[a-z0-9]+)*\z/D'],
            'catalogue.entries.*.category.order' => ['required', 'integer', 'min:0', 'max:1000000'],
            'catalogue.entries.*.excerpt' => ['required', 'string', 'max:1000'],
            'catalogue.entries.*.body' => ['required', 'string', 'max:100000'],
            'catalogue.entries.*.tools' => ['present', 'array', 'list', 'max:30'],
            'catalogue.entries.*.tools.*' => ['required', 'string', 'max:80'],
            'catalogue.entries.*.video_url' => ['present', 'nullable', 'string', 'max:2048', 'regex:'.Project::VIDEO_URL_PATTERN],
            'catalogue.entries.*.thumbnail' => ['present', 'nullable', 'string', 'regex:/\A[A-Za-z0-9_-]{11}\.jpg\z/D'],
            'catalogue.entries.*.seo_description' => ['required', 'string', 'max:320'],
            'catalogue.entries.*.review' => ['required', 'array:status,source_title,original_url,candidate_url,notes,checked_date'],
            'catalogue.entries.*.review.status' => ['required', 'in:verified_original,replacement_topic_match,source_unavailable,overlap_narrow_scope,candidate_scope_change,needs_specific_repository'],
            'catalogue.entries.*.review.source_title' => ['required', 'string', 'max:255'],
            'catalogue.entries.*.review.original_url' => ['required', 'url:http,https', 'max:2048'],
            'catalogue.entries.*.review.candidate_url' => ['present', 'nullable', 'url:https', 'max:2048'],
            'catalogue.entries.*.review.notes' => ['required', 'string', 'max:10000'],
            'catalogue.entries.*.review.checked_date' => ['required', 'date_format:Y-m-d'],
        ])->validate()['catalogue'];
    }

    /** @param list<array<string, mixed>> $entries
     * @return array<string, string>
     */
    private function readThumbnails(array $entries): array
    {
        $images = [];
        foreach ($entries as $index => $entry) {
            $matched = in_array($entry['review']['status'], ['verified_original', 'replacement_topic_match'], true);
            if ((! $matched && ($entry['video_url'] || $entry['thumbnail'])) || ($entry['thumbnail'] && ! $entry['video_url'])) {
                throw ValidationException::withMessages(['catalogue.entries.'.$index.'.video_url' => 'Only matched evidence may supply a video or thumbnail.']);
            }
            if (! $entry['thumbnail']) {
                continue;
            }
            $path = resource_path('catalogue/thumbnails/'.$entry['thumbnail']);
            if (! is_file($path) || ! is_readable($path) || filesize($path) > 4 * 1024 * 1024) {
                throw ValidationException::withMessages(['catalogue.entries.'.$index.'.thumbnail' => 'A bundled thumbnail is missing, unreadable or larger than 4 MB.']);
            }
            $bytes = file_get_contents($path);
            $size = @getimagesizefromstring($bytes);
            if (! $size || $size[2] !== IMAGETYPE_JPEG) {
                throw ValidationException::withMessages(['catalogue.entries.'.$index.'.thumbnail' => 'A bundled thumbnail must be a valid JPEG image.']);
            }
            $images[$entry['thumbnail']] = $bytes;
        }

        return $images;
    }
}
