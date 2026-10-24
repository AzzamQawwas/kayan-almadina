<?php

use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function (): void {
    $this->comment('Ship the right materials, to the right place.');
})->purpose('Display an inspiring quote');
