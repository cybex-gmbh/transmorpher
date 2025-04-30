<?php

namespace App\Models;

use App\Enums\MediaStorage;
use App\Enums\MediaType;
use DB;
use File;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;
use Validator;

/**
 * App\Models\Media
 *
 * @property int $id
 * @property string $identifier
 * @property MediaType $type
 * @property int $user_id
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\User $User
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Version> $Versions
 * @property-read int|null $versions_count
 * @property-read \App\Models\Version $current_version
 * @property-read \App\Models\Version|null $latest_version
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Media newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Media newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Media query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Media whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Media whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Media whereIdentifier($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Media whereType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Media whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Media whereUserId($value)
 * @mixin \Eloquent
 */
class Media extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'identifier',
        'type',
    ];

    /**
     * The "booted" method of the model.
     */
    protected static function booted(): void
    {
        static::deleting(function (Media $media) {
            $media->deleteRelatedModels();
            $media->deleteBaseDirectories();
        });
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => MediaType::class,
        ];
    }

    protected function deleteRelatedModels(): void
    {
        DB::transaction(function () {
            $this->Versions()->get()->each->delete();
            $this->User->UploadSlots()->withoutGlobalScopes()->firstWhere('identifier', $this->identifier)?->delete();
        });
    }

    protected function deleteBaseDirectories(): void
    {
        $fileBasePath = $this->baseDirectory();
        $this->type->handler()->getDerivativesDisk()->deleteDirectory($fileBasePath);
        MediaStorage::ORIGINALS->getDisk()->deleteDirectory($fileBasePath);
    }

    /**
     * Returns the user that owns the media.
     */
    public function User(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Returns versions for the media.
     */
    public function Versions(): HasMany
    {
        return $this->hasMany(Version::class);
    }

    /**
     * Get the route key for the model.
     *
     * @return string
     */
    public function getRouteKeyName(): string
    {
        return 'identifier';
    }

    /**
     * Validates an uploaded file after the chunks have been combined successfully.
     * This has to be done after all chunks have been received, because the mime type of the received chunks is 'application/octet-stream'.
     *
     * For videos:
     *  Mimetypes to mimes:
     *      video/x-msvideo => avi
     *      video/mpeg => mpeg mpg mpe m1v m2v
     *      video/ogg => ogv
     *      video/webm => webm
     *      video/mp4 => mp4 mp4v mpg4
     *
     * @param UploadedFile $file
     * @param string $mimeTypes
     *
     * @return void
     * @throws ValidationException
     */
    public function validateUploadFile(UploadedFile $file, string $mimeTypes): void
    {
        $validator = Validator::make(['file' => $file], ['file' => [
            'required',
            $mimeTypes,
        ]]);

        $validator->validate();

        $failed = $validator->fails();

        $validator->after(function () use ($file, $failed) {
            if ($failed) {
                File::delete($file);
            }
        });
    }

    public function currentVersion(): Attribute
    {
        return Attribute::make(
            get: function (): Version {
                $versions = $this->Versions();
                return $versions->whereNumber($versions->whereProcessed(true)->max('number'))->firstOrFail();
            }
        );
    }

    public function latestVersion(): Attribute
    {
        return Attribute::make(
            get: function (): ?Version {
                $versions = $this->Versions();
                return $versions->whereNumber($versions->max('number'))->first();
            }
        );
    }

    /**
     * Get the base path for files.
     * Path structure: {username}/{identifier}/
     *
     * @return string
     */
    public function baseDirectory(): string
    {
        return sprintf('%s/%s', $this->User->name, $this->identifier);
    }

    /**
     * Get the path to a video derivative.
     * Path structure: {username}/{identifier}/{format}/{filename}
     *
     * @param string $format
     * @param string|null $fileName
     *
     * @return string
     */
    public function videoDerivativeFilePath(string $format, string $fileName = null): string
    {
        return sprintf('%s/%s/%s', $this->baseDirectory(), $format, $fileName ?? 'video');
    }
}
