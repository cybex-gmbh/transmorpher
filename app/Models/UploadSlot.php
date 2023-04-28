<?php

namespace App\Models;

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
 * @property string $token
 * @property string $identifier
 * @property string|null $callback_token
 * @property string|null $callback_url
 * @property string|null $validation_rules
 * @property string $valid_until
 * @property int $user_id
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\User $User
 * @method static \Illuminate\Database\Eloquent\Builder|UploadSlot newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|UploadSlot newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|UploadSlot query()
 * @method static \Illuminate\Database\Eloquent\Builder|UploadSlot whereCallbackUrl($value)
 * @method static \Illuminate\Database\Eloquent\Builder|UploadSlot whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|UploadSlot whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|UploadSlot whereIdToken($value)
 * @method static \Illuminate\Database\Eloquent\Builder|UploadSlot whereIdentifier($value)
 * @method static \Illuminate\Database\Eloquent\Builder|UploadSlot whereToken($value)
 * @method static \Illuminate\Database\Eloquent\Builder|UploadSlot whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|UploadSlot whereUserId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|UploadSlot whereValidUntil($value)
 * @method static \Illuminate\Database\Eloquent\Builder|UploadSlot whereValidationRules($value)
 * @property string|null $id_token
 * @method static \Illuminate\Database\Eloquent\Builder|UploadSlot whereCallbackToken($value)
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
        'token',
        'identifier',
        'callback_token',
        'callback_url',
        'validation_rules',
        'valid_until',
        'user_id',
    ];

    /**
     * The "booted" method of the model.
     */
    protected static function booted(): void
    {
        static::addGlobalScope('valid', function (Builder $builder) {
            $builder->where('valid_until', '>', Carbon::now());
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

    protected function isValid(): Attribute
    {
        return Attribute::make(
            get: fn() => Carbon::now()->isBefore($this->valid_until)
        );
    }
}
