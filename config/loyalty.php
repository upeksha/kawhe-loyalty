<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Default Reward Target
    |--------------------------------------------------------------------------
    |
    | The default number of stamps required to earn a reward.
    | This is used as a fallback when a store doesn't have a reward_target set.
    |
    */
    'reward_target' => env('LOYALTY_REWARD_TARGET', 10),

    /*
    |--------------------------------------------------------------------------
    | Stamp UX cooldown (seconds) - server 409 response
    |--------------------------------------------------------------------------
    |
    | Set to 0 to disable: no 409 cooldown; only the 5-second hard duplicate
    | window applies. When 0, the scanner app uses client_cooldown_seconds
    | to show a countdown before allowing the next scan.
    |
    */
    'stamp_cooldown_seconds' => (int) env('STAMP_COOLDOWN_SECONDS', 0),

    /*
    |--------------------------------------------------------------------------
    | Stamp client countdown (seconds)
    |--------------------------------------------------------------------------
    |
    | After a successful stamp (or redeem), the scanner UI counts down this
    | many seconds before the camera can scan again. Should be >= 5 to align
    | with the server’s 5-second duplicate window.
    |
    */
    'stamp_client_cooldown_seconds' => (int) env('STAMP_CLIENT_COOLDOWN_SECONDS', 5),
];
