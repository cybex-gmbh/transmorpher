<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * App\Models\Version
 *
 * @property int $id
 * @property int $number
 * @property string $filename
 * @property int $processed
 * @property int $media_id
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\Media $Media
 * @method static \Illuminate\Database\Eloquent\Builder|Version newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|Version newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|Version query()
 * @method static \Illuminate\Database\Eloquent\Builder|Version whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Version whereFilename($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Version whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Version whereMediaId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Version whereNumber($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Version whereProcessed($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Version whereUpdatedAt($value)
 * @mixin \Eloquent
 */
class Version extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     *  Version number determines the current version.
     *  Version key distinguishes the files and database rows.
     *
     * @var array
     */
    protected $fillable = [
        'number',
        'filename',
        'processed',
    ];

    /**
     * Returns the media that the version belongs to.
     */
    public function Media(): BelongsTo
    {
        return $this->belongsTo(Media::class);
    }

    /**
     * Get the route key for the model.
     *
     * @return string
     */
    public function getRouteKeyName(): string
    {
        return 'number';
    }
}
