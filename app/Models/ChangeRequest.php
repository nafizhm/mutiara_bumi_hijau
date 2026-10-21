<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ChangeRequest extends Model
{
    protected $guarded = ['id'];
    protected $hidden = ['payload'];
    protected function casts(): array
    {
        return ['payload' => 'encrypted:array', 'reviewed_at' => 'datetime'];
    }
    public function requester() { return $this->belongsTo(User::class, 'user_id'); }
}
