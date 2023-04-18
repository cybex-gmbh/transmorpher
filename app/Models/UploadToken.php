<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UploadToken extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'token',
        'identifier',
        'id_token',
        'callback_url',
        'validation_rules',
        'user_id',
    ];

    /**
     * Returns the user that owns the upload token.
     */
    public function User(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
