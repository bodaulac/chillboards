<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DesignMapping extends Model
{
    protected $fillable = [
        'base_sku',
        'design_url',
        'mockup_url',
        'design_url_2',
        'mockup_url_2',
    ];
}
