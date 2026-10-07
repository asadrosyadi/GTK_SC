<?php

namespace Modules\Yolo\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class HistoryYolo extends Model
{
    use HasFactory;

    protected $table = 'sensor_kameras';
    protected $fillable = [];

}
