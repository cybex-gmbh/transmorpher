<?php

namespace App\Models;

use App\Enums\MediaStorage;
use FilePathHelper;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * App\Models\Version
 *
 * @property int $id
 * @property int $number
 * @property string|null $filename
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
     * The "booted" method of the model.
     */
    protected static function booted(): void
    {
        static::deleting(function (Version $version) {
            $version->deleteFiles();
        });
    }

    protected function deleteFiles(): void
    {
        MediaStorage::ORIGINALS->getDisk()->delete(FilePathHelper::toOriginalFile($this));
        MediaStorage::IMAGE_DERIVATIVES->getDisk()->deleteDirectory(FilePathHelper::toImageDerivativeVersionDirectory($this));
        // Video derivatives may not be deleted here, otherwise failed jobs would delete the only existing video derivative.
    }

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
