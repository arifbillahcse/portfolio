<?php

namespace App\Console\Commands;

use App\Support\WordPressImporter;
use App\Support\WordPressXml;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Validation\ValidationException;

#[Signature('portfolio:import-wordpress {file : Path to a WordPress WXR XML export} {--only-id= : Queue only this WordPress post ID; preserve existing posts} {--validate-only : Check the XML without saving posts or queuing jobs} {--clean-invalid-characters : Remove forbidden XML 1.0 characters in memory from a UTF-8 export; leave the source file unchanged}')]
#[Description('Queue a WordPress post and image import from a local XML file')]
class ImportWordPress extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(WordPressImporter $importer, WordPressXml $xml): int
    {
        $path = realpath($this->argument('file'));
        if ($path === false || ! is_file($path) || ! is_readable($path)) {
            $this->error('The XML file does not exist or is not readable.');

            return self::FAILURE;
        }
        try {
            $selected = $this->option('only-id');
            if ($selected !== null && (! ctype_digit((string) $selected) || (int) $selected < 1)) {
                throw ValidationException::withMessages(['only-id' => 'Choose a positive WordPress post ID.']);
            }
            $onlyId = $selected !== null ? (int) $selected : null;
            $cleanInvalidCharacters = (bool) $this->option('clean-invalid-characters');
            if ($cleanInvalidCharacters) {
                $this->warn('XML character cleanup enabled. The original file will not be changed.');
            }
            if ($this->option('validate-only')) {
                $posts = $xml->read($path, $cleanInvalidCharacters);
                if ($onlyId !== null) {
                    $posts = array_filter($posts, fn (array $post): bool => $post['wordpress_id'] === $onlyId);
                    if ($posts === []) {
                        throw ValidationException::withMessages(['xml' => 'The selected WordPress post ID is not in this export.']);
                    }
                }
                if ($cleanInvalidCharacters) {
                    $this->line('Forbidden characters removed in memory: '.$xml->removedCharacters().'.');
                }
                $this->info('Valid WordPress XML: '.count($posts).' importable posts. No data was saved and no jobs were queued.');

                return self::SUCCESS;
            }
            $import = $importer->start($path, basename($path), $cleanInvalidCharacters, $onlyId);
        } catch (ValidationException $exception) {
            foreach ($exception->errors() as $messages) {
                foreach ($messages as $message) {
                    $this->error($message);
                }
            }

            return self::FAILURE;
        }
        $this->info('Queued import #'.$import->id.': '.$import->total.' posts.');
        $this->line('Keep the database queue worker / Cron running for mail,default. Review progress at /admin/wordpress-import.');

        return self::SUCCESS;
    }
}
