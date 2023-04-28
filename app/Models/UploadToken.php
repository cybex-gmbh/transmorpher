<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * App\Models\UploadToken
 *
 * @property int $id
 * @property string $token
 * @property string $identifier
 * @property string|null $id_token
 * @property string|null $callback_url
 * @property string|null $validation_rules
 * @property string $valid_until
 * @property int $user_id
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\User $User
 * @method static \Illuminate\Database\Eloquent\Builder|UploadToken newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|UploadToken newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|UploadToken query()
 * @method static \Illuminate\Database\Eloquent\Builder|UploadToken whereCallbackUrl($value)
 * @method static \Illuminate\Database\Eloquent\Builder|UploadToken whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|UploadToken whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|UploadToken whereIdToken($value)
 * @method static \Illuminate\Database\Eloquent\Builder|UploadToken whereIdentifier($value)
 * @method static \Illuminate\Database\Eloquent\Builder|UploadToken whereToken($value)
 * @method static \Illuminate\Database\Eloquent\Builder|UploadToken whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|UploadToken whereUserId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|UploadToken whereValidUntil($value)
 * @method static \Illuminate\Database\Eloquent\Builder|UploadToken whereValidationRules($value)
 * @mixin \Eloquent
 */
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
        'valid_until',
        'user_id',
    ];

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
