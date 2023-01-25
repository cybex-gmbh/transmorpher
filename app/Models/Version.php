<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Version extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'number',
        'filename',
        'processed',
        'media_id',
    ];

    /**
     * Returns the media that the version belongs to.
     */
    public function Media(): BelongsTo
    {
        return $this->belongsTo(Media::class);
    }
}
