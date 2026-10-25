<?php
// Gemountet unter /api/client/extensions/sfmanager

use Illuminate\Support\Facades\Route;

Route::get('/ping', fn () => ['ok' => true, 'version' => '{version}']);
