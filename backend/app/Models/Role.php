<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['nombre', 'descripcion'])]
class Role extends Model
{
    public $timestamps = false;

    public const DIRECTOR = 'Director';
    public const ADMIN = 'Admin';
    public const SUPERVISOR = 'Supervisor';
}
