<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SourceValue extends Model
{
    protected $table = 'source_values';
    protected $guarded = ['id'];

    const UPDATED_AT = null;
}
