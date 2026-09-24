<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FolioCounter extends Model
{
    protected $table = 'folio_counters';

    protected $primaryKey = 'tipo';

    protected $keyType = 'string';

    public $incrementing = false;

    public $timestamps = false;

    protected $fillable = ['tipo', 'siguiente'];
}
