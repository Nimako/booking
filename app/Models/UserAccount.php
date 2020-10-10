<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UserAccount extends Model
{
    use HasFactory;

    protected $table       = 'useraccount';

    protected $primaryKey  = 'id';

    protected $fillable = ['Email'];

    const CREATED_AT       = 'DateCreated';
    const UPDATED_AT       = null;
}
