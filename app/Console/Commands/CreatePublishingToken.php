<?php

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('portfolio:mcp-token')]
#[Description('Generate a blog publishing credential and its server-side SHA-256 value; does not change configuration')]
class CreatePublishingToken extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $token = bin2hex(random_bytes(32));
        $this->line('Set this on the live server, then rebuild its configuration cache:');
        $this->line('BLOG_MCP_TOKEN_SHA256='.hash('sha256', $token));
        $this->newLine();
        $this->line('Configure the Claude connector request header Authorization with the following value:');
        $this->line('Bearer '.$token);
        $this->newLine();
        $this->info('Keep the bearer credential private. Generating a value does not activate or rotate the live credential.');

        return self::SUCCESS;
    }
}
