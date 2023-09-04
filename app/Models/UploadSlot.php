<?php

namespace App\Models;

use App\Enums\MediaType;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * App\Models\UploadSlot
 *
 * @property int $id
 * @property string|null $token
 * @property string $identifier
 * @property string|null $callback_url
 * @property string|null $validation_rules
 * @property string|null $valid_until
 * @property MediaType $media_type
 * @property int $user_id
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\User $User
 * @method static Builder|UploadSlot newModelQuery()
 * @method static Builder|UploadSlot newQuery()
 * @method static Builder|UploadSlot query()
 * @method static Builder|UploadSlot whereCallbackUrl($value)
 * @method static Builder|UploadSlot whereCreatedAt($value)
 * @method static Builder|UploadSlot whereId($value)
 * @method static Builder|UploadSlot whereIdentifier($value)
 * @method static Builder|UploadSlot whereMediaType($value)
 * @method static Builder|UploadSlot whereToken($value)
 * @method static Builder|UploadSlot whereUpdatedAt($value)
 * @method static Builder|UploadSlot whereUserId($value)
 * @method static Builder|UploadSlot whereValidUntil($value)
 * @method static Builder|UploadSlot whereValidationRules($value)
 * @mixin \Eloquent
 */
class UploadSlot extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'identifier',
        'callback_url',
        'validation_rules',
        'media_type',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'media_type' => MediaType::class,
    ];

    /**
     * The "booted" method of the model.
     */
    protected static function booted(): void
    {
        static::addGlobalScope('valid', function (Builder $builder) {
            $builder->where('valid_until', '>', Carbon::now());
        });

        static::saving(function (UploadSlot $uploadSlot) {
            $uploadSlot->token = uniqid();
            $uploadSlot->valid_until = Carbon::now()->addHours(24);
        });
    }

    /**
     * Returns the user that owns the upload token.
     */
    public function User(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the route key for the model.
     *
     * @return string
     */
    public function getRouteKeyName(): string
    {
        return 'token';
    }

    /**
     * Invalidates the UploadSlot by setting valid_until to a date in the past.
     *
     * @return void
     */
    public function invalidate(): void
    {
        $this->valid_until = Carbon::parse($this->valid_until)->subDay();
        $this->saveQuietly();
    }

    /**
     * Retrieve whether the upload token for this upload slot is still valid.
     *
     * @return Attribute
     */
    protected function isValid(): Attribute
    {
        return Attribute::make(
            get: fn() => Carbon::now()->isBefore($this->valid_until)
        );
    }
}
