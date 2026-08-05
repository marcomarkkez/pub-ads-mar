<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Walkthrough tracing (BR-17)
    |--------------------------------------------------------------------------
    |
    | Turns on App\Http\Middleware\WalkTrace, which writes one line per API
    | request to storage/logs/walk.log. It exists for the human walkthroughs:
    | a person running WALK-n tails that file and sees what the app really did,
    | rather than what a static reader believed it would do.
    |
    | Off by default. Set WALK_TRACE=true in .env before a walkthrough.
    |
    */

    'trace' => env('WALK_TRACE', false),

];
