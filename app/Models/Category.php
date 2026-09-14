<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use App\Models\Concerns\BelongsToTenant;
class Category extends Model
{
    use HasFactory, BelongsToTenant;
    protected $fillable = [
        'tenant_id',
        'name',
    ];

    public function equipment():MorphToMany{
        return $this->morphedByMany(Equipment::class, 'categorizable');
    }
}
