<?php

namespace App\Models;

use App\Enums\MediaType;
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
 * @method static \Illuminate\Database\Eloquent\Builder|Media newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|Media newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|Media query()
 * @method static \Illuminate\Database\Eloquent\Builder|Media whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Media whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Media whereIdentifier($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Media whereType($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Media whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Media whereUserId($value)
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
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'type' => MediaType::class,
    ];

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
     * @param UploadSlot $uploadSlot
     *
     * @return void
     * @throws ValidationException
     */
    public function validateUploadFile(UploadedFile $file, string $mimeTypes, UploadSlot $uploadSlot): void
    {
        $validator = Validator::make(['file' => $file], ['file' => [
            'required',
            $mimeTypes,
        ]]);

        $validator->validate();

        $failed = $validator->fails();

        $validator->after(function () use ($file, $failed, $uploadSlot) {
            if ($failed) {
                File::delete($file);
            }
        });
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

    public function currentVersion(): Attribute
    {
        return Attribute::make(
            get: function () {
                $versions = $this->Versions();
                return $versions->whereNumber($versions->whereProcessed(true)->max('number'))->firstOrFail();
            }
        );
    }
}
