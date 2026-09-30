<?php

/*
|--------------------------------------------------------------------------
| CONTRACT TEST SUITE
|--------------------------------------------------------------------------
| Validation + persistence behaviors and the never-again-unguarded gaps
| (rate limiting, session lifetimes, lockout, envelopes). Error message
| literals are part of the pinned API contract.
*/

require_once __DIR__.'/Helpers.php';

pest()->extend(Tests\TestCase::class)
    ->use(Illuminate\Foundation\Testing\RefreshDatabase::class)
    ->in('Feature', 'Unit');
