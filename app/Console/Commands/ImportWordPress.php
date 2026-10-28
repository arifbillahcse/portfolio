<?php

namespace App\Console\Commands;

use App\Support\WordPressImporter;
use App\Support\WordPressXml;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Validation\ValidationException;

#[Signature('portfolio:import-wordpress {file : Path to a WordPress WXR XML export} {--validate-only : Check the XML without saving posts or queuing jobs}')]
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
            if ($this->option('validate-only')) {
                $posts = $xml->read($path);
                $this->info('Valid WordPress XML: '.count($posts).' importable posts. No data was saved and no jobs were queued.');

                return self::SUCCESS;
            }
            $import = $importer->start($path, basename($path));
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
