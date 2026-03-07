<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Attachment extends Model
{
    use HasFactory;

    protected $fillable = [
        'email_id',
        'file_name',
        'mime_type',
        'file_path',
    ];

    /**
     * Get the email this attachment belongs to.
     */
    public function email()
    {
        return $this->belongsTo(Email::class);
    }
}
