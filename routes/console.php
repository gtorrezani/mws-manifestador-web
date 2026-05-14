<?php

use Illuminate\Support\Facades\Artisan;

Artisan::command('mws:quality-info', function (): void {
    $this->info('MWS Manifestador NF-e quality tooling is configured.');
});
