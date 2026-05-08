<?php

namespace App\Modules\Basics\Model;

use Illuminate\Database\Eloquent\Model;

class RiskRule extends Model
{
    protected $fillable = [
        'category',
        'keyword',
        'severity',
        'weight',
        'enabled',
    ];
}
