<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StoreStaffInvitation extends Model
{
    protected $fillable = ['store_id', 'invited_by', 'email', 'token_hash', 'can_stamp', 'can_redeem', 'expires_at'];

    protected $hidden = ['token_hash'];

    protected $casts = ['can_stamp' => 'boolean', 'can_redeem' => 'boolean', 'expires_at' => 'datetime'];

    public function store()
    {
        return $this->belongsTo(Store::class);
    }
}
