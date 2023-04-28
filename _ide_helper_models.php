<?php

// @formatter:off
/**
 * A helper file for your Eloquent Models
 * Copy the phpDocs from this file to the correct Model,
 * And remove them from this file, to prevent double declarations.
 *
 * @author Barry vd. Heuvel <barryvdh@gmail.com>
 */


namespace App\Models{
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
 * @property-read \Illuminate\Database\Eloquent\Collection|\App\Models\Version[] $Versions
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
	class Media extends \Eloquent {}
}

namespace App\Models{
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
	class UploadSlot extends \Eloquent {}
}

namespace App\Models{
/**
 * App\Models\User
 *
 * @property int $id
 * @property string $name
 * @property string $email
 * @property \Illuminate\Support\Carbon|null $email_verified_at
 * @property string $password
 * @property string|null $remember_token
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \Illuminate\Database\Eloquent\Collection|\App\Models\Media[] $Media
 * @property-read int|null $media_count
 * @property-read \Illuminate\Notifications\DatabaseNotificationCollection|\Illuminate\Notifications\DatabaseNotification[] $notifications
 * @property-read int|null $notifications_count
 * @property-read \Illuminate\Database\Eloquent\Collection|\Laravel\Sanctum\PersonalAccessToken[] $tokens
 * @property-read int|null $tokens_count
 * @method static \Database\Factories\UserFactory factory(...$parameters)
 * @method static \Illuminate\Database\Eloquent\Builder|User newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|User newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|User query()
 * @method static \Illuminate\Database\Eloquent\Builder|User whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|User whereEmail($value)
 * @method static \Illuminate\Database\Eloquent\Builder|User whereEmailVerifiedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|User whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|User whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder|User wherePassword($value)
 * @method static \Illuminate\Database\Eloquent\Builder|User whereRememberToken($value)
 * @method static \Illuminate\Database\Eloquent\Builder|User whereUpdatedAt($value)
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\UploadSlot> $UploadSlots
 * @property-read int|null $upload_slots_count
 * @mixin \Eloquent
 */
	class User extends \Eloquent {}
}

namespace App\Models{
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
	class Version extends \Eloquent {}
}

