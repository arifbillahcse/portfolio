<?php

namespace App\Console\Commands;

use App\Support\SeoRedirects;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Validation\ValidationException;
use JsonException;

#[Signature('portfolio:import-redirects {file : JSON list of exact redirect rules} {--dry-run : Validate without changing redirects}')]
#[Description('Import redirect rules while preserving existing sources')]
class ImportSeoRedirects extends Command
{
    public function handle(SeoRedirects $redirects): int
    {
        $file = $this->argument('file');
        if (! is_file($file) || ! is_readable($file) || filesize($file) > 1000000) {
            $this->error('Choose a readable JSON file up to 1 MB.');

            return self::FAILURE;
        }
        try {
            $entries = json_decode(file_get_contents($file), true, 32, JSON_THROW_ON_ERROR);
            $result = $redirects->import($entries, (bool) $this->option('dry-run'));
        } catch (JsonException) {
            $this->error('The redirect catalogue is not valid JSON.');

            return self::FAILURE;
        } catch (ValidationException $exception) {
            foreach ($exception->errors() as $messages) {
                foreach ($messages as $message) {
                    $this->error($message);
                }
            }

            return self::FAILURE;
        }
        $this->info(($this->option('dry-run') ? 'Validated ' : 'Imported ').$result['created'].' redirect rules; preserved '.$result['skipped'].' existing sources.');

        return self::SUCCESS;
    }
}
