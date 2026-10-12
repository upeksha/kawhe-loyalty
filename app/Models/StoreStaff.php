<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StoreStaff extends Model
{
    protected $table = 'store_staff';

    protected $fillable = ['store_id', 'user_id', 'can_stamp', 'can_redeem'];

    protected $casts = ['can_stamp' => 'boolean', 'can_redeem' => 'boolean'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function store()
    {
        return $this->belongsTo(Store::class);
    }
}
