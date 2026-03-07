<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'password',
        'google_id',
        'access_token',
        'refresh_token',
    ];

    protected $hidden = [
        'password',
        'access_token',
        'refresh_token',
        'remember_token',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
    ];

    /**
     * Get all emails belonging to this user.
     */
    public function emails()
    {
        return $this->hasMany(Email::class);
    }

    /**
     * Check if user has connected their Gmail account.
     */
    public function isGmailConnected(): bool
    {
        return !empty($this->google_id) && !empty($this->access_token);
    }
}
