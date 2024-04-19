<?php

namespace App\Models;

use App\Enums\MediaStorage;
use App\Enums\Transformation;
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
        MediaStorage::ORIGINALS->getDisk()->delete($this->originalFilePath());
        MediaStorage::IMAGE_DERIVATIVES->getDisk()->deleteDirectory($this->imageDerivativeDirectoryPath());
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

    /**
     * Get the path to an original.
     * Path structure: {username}/{identifier}/{filename}
     *
     * @return string
     */
    public function originalFilePath(): string
    {
        return sprintf('%s/%s', $this->Media->baseDirectory(), $this->filename);
    }

    /**
     * Create the filename for an original.
     * Filename structure: {versionKey}-{filename}
     *
     * @param string $filename
     *
     * @return string
     */
    public function createOriginalFileName(string $filename): string
    {
        return sprintf('%s-%s', $this->getKey(), trim($filename));
    }

    /**
     * Get the path to an (existing) image derivative.
     * Path structure: {username}/{identifier}/{versionKey}/{width}x_{height}y_{quality}q_{derivativeHash}.{format}
     *
     * @param array|null $transformations
     * @return string
     */
    public function imageDerivativeFilePath(array $transformations = null): string
    {
        $originalFileExtension = pathinfo($this->filename, PATHINFO_EXTENSION);

        // Hash of transformation parameters and version number to identify already generated derivatives.
        $derivativeHash = hash('sha256', json_encode($transformations) . $this->getKey());

        return sprintf('%s/%sx_%sy_%sq_%s.%s',
            $this->imageDerivativeDirectoryPath(),
            $transformations[Transformation::WIDTH->value] ?? '',
            $transformations[Transformation::HEIGHT->value] ?? '',
            $transformations[Transformation::QUALITY->value] ?? '',
            $derivativeHash,
            $transformations[Transformation::FORMAT->value] ?? $originalFileExtension,
        );
    }

    /**
     * Get the path to the directory of an image derivative version.
     * Path structure: {username}/{identifier}/{versionKey}
     *
     * @return string
     */
    public function imageDerivativeDirectoryPath(): string
    {
        return sprintf('%s/%s', $this->Media->baseDirectory(), $this->getKey());
    }
}
