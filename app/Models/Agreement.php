<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Agreement extends Model
{
    use HasFactory;

    /**
     * Once an agreement reaches one of these, later events do not change it.
     */
    public const TERMINAL_STATUSES = ['completed', 'rejected', 'failed', 'canceled'];

    protected $fillable = ['signer_name', 'signer_email', 'envelope_id', 'status'];
}
