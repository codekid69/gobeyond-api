<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Email extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'gmail_message_id',
        'thread_id',
        'sender',
        'receiver',
        'subject',
        'body_html',
        'body_text',
        'date',
        'has_attachment',
    ];

    protected $casts = [
        'date' => 'datetime',
        'has_attachment' => 'boolean',
    ];

    /**
     * Get the user who owns this email.
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get all attachments for this email.
     */
    public function attachments()
    {
        return $this->hasMany(Attachment::class);
    }
}
