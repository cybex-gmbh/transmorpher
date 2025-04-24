<?php

namespace App\Models;

use App\Enums\MediaStorage;
use App\Enums\Transformation;
use Arr;
use Eloquent;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * App\Models\Version
 *
 * @property int $id
 * @property int $number
 * @property string|null $filename
 * @property int $processed
 * @property int $media_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read \App\Models\Media $Media
 * @property-read string $hash
 * @method static Builder<static>|Version newModelQuery()
 * @method static Builder<static>|Version newQuery()
 * @method static Builder<static>|Version query()
 * @method static Builder<static>|Version whereCreatedAt($value)
 * @method static Builder<static>|Version whereFilename($value)
 * @method static Builder<static>|Version whereId($value)
 * @method static Builder<static>|Version whereMediaId($value)
 * @method static Builder<static>|Version whereNumber($value)
 * @method static Builder<static>|Version whereProcessed($value)
 * @method static Builder<static>|Version whereUpdatedAt($value)
 * @mixin Eloquent
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
        'filename',
        'number',
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

        if (($derivativesDisk = $this->Media->type->handler()->getDerivativesDisk()) === MediaStorage::VIDEO_DERIVATIVES->getDisk()) {
            // Video derivatives may not be deleted here, otherwise failed jobs would delete the only existing video derivative.
            return;
        }

        $derivativesDisk->deleteDirectory($this->onDemandDerivativeDirectoryPath());
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
     * Get the path to an (existing) on demand derivative.
     * Path structure with all transformations applied: {username}/{identifier}/{versionKey}/{width}w_{height}h_{quality}q_{page}p_{ppi}ppi_{derivativeHash}.{format}
     *
     * @param array|null $transformations
     * @return string
     */
    public function onDemandDerivativeFilePath(?array $transformations = null): string
    {
        $mediaType = $this->Media->type;
        $originalFileExtension = pathinfo($this->filename, PATHINFO_EXTENSION);

        // Hash of transformation parameters and version number to identify already generated derivatives.
        $derivativeHash = hash('sha256', json_encode($transformations) . $this->getKey());

        return sprintf('%s/%s_%s.%s',
            $this->onDemandDerivativeDirectoryPath(),
            implode('_',
                Arr::mapWithKeys(Arr::except($transformations ?? [], Transformation::FORMAT->value),
                    function($value, $key) { return [sprintf('%s%s', $value, $key)]; }
                )
            ),
            $derivativeHash,
            $transformations[Transformation::FORMAT->value] ?? $originalFileExtension,
        );
    }

    /**
     * Get the path to the directory of an on demand derivative version.
     * Path structure: {username}/{identifier}/{versionKey}
     *
     * @return string
     */
    public function onDemandDerivativeDirectoryPath(): string
    {
        return sprintf('%s/%s', $this->Media->baseDirectory(), $this->getKey());
    }

    public function hash(): Attribute
    {
        return Attribute::make(
            get: fn(): string => md5(sprintf('%s-%s', $this->number, $this->created_at))
        );
    }
}
