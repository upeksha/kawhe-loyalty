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
];
