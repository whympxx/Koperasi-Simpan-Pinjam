<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Setting extends Model
{
    protected $table = 'system_settings';

    protected $fillable = ['setting_key', 'setting_value', 'setting_type', 'description', 'updated_by'];
}
