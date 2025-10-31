<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WordTag extends Model
{
    use HasFactory;

    protected $fillable = ['pdf_id', 'text', 'status'];
}