<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('site_settings', function (Blueprint $table): void {
            $table->boolean('analytics_enabled')->default(false);
            $table->string('ga4_measurement_id', 32)->nullable();
            $table->string('ga4_property_id', 32)->nullable();
            $table->string('search_console_property', 255)->nullable();
            $table->longText('google_service_account')->nullable();
            $table->date('next_content_review_on')->nullable();
            $table->text('content_plan')->nullable();
        });

        Schema::table('inquiries', function (Blueprint $table): void {
            $table->string('landing_path', 255)->nullable();
            $table->string('referrer_host', 255)->nullable();
            $table->string('utm_source', 100)->nullable()->index();
            $table->string('utm_medium', 100)->nullable();
            $table->string('utm_campaign', 100)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('inquiries', function (Blueprint $table): void {
            $table->dropIndex(['utm_source']);
            $table->dropColumn(['landing_path', 'referrer_host', 'utm_source', 'utm_medium', 'utm_campaign']);
        });

        Schema::table('site_settings', function (Blueprint $table): void {
            $table->dropColumn(['analytics_enabled', 'ga4_measurement_id', 'ga4_property_id', 'search_console_property', 'google_service_account', 'next_content_review_on', 'content_plan']);
        });
    }
};
