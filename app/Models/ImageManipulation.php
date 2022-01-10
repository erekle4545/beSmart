<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ImageManipulation extends Model
{
    use HasFactory;

    const TYPE_RESIZE = 'resize';
    const UPDATED_AT = null;
    protected $fillable = ['name','type','path','data','output_path','album_id','user_id'];
}
