<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Console\Scheduling\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Register the AutoTaggingJob to be run manually
Artisan::command('video:auto-tag', function () {
    $this->call('app:auto-tagging-job');
})->purpose('Automatically assign tags to videos');
