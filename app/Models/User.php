<?php

namespace App\Models;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Auth\Events\Verified;
use App\Notifications\CustomVerifyEmail;
use App\Notifications\CustomResetPassword;

class User extends Authenticatable implements MustVerifyEmail
{
    use HasFactory, Notifiable;

    //protected $table = 'users';

    protected $fillable = [
        'name',
        'email',
        'display_name',
        'phone',
        'type',
        'ip',
        'level',
        'last_activity',
        'avatar',
        'password',
        'device',
        'amount',
        'total_paid',
        'banned',
        'token',
        'role',
        'time_end',
        'api_plan',
        'api_account_limit',
        'api_extra_slots',
        'api_plan_started_at',
        'api_plan_months',
        'api_plan_paid_amount',
        'api_next_plan',
        'api_next_plan_months',
        'api_next_plan_price',
        'api_next_plan_scheduled_at',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'last_activity'     => 'date',
        'password'          => 'hashed',
    ];

    public function sendEmailVerificationNotification()
    {
        $this->notify(new CustomVerifyEmail());
    }

    public function sendPasswordResetNotification($token)
    {
        $this->notify(new CustomResetPassword($token));
    }

    public function markEmailAsVerified()
    {
        if ($this->hasVerifiedEmail()) {
            return false;
        }
        $this->forceFill([
            'email_verified_at' => $this->freshTimestamp(),
            'role'              => 3,
        ])->save();

        event(new Verified($this));

        return true;
    }

    public function buildings()
    {
        return $this->belongsToMany(
            Building::class,
            'building_users',
            'user_id',
            'bkey'
        )
        ->withPivot('role')
        ->withTimestamps();
    }
}
