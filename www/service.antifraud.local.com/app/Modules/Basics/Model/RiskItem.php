<?php

namespace App\Modules\Basics\Model;

use Illuminate\Database\Eloquent\Model;

class RiskItem extends Model
{
    protected $fillable = [
        'record_id',
        'category',
        'severity',
        'description',
        'evidence_text',
    ];
}
