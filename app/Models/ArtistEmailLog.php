<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ArtistEmailLog extends Model
{
    protected $guarded = [];

    protected $casts = [
        'is_html' => 'boolean',
    ];
}
