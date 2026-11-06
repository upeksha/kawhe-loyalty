<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AccountFileCleanup extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['payload' => 'array', 'available_at' => 'datetime', 'completed_at' => 'datetime'];
    }
}
