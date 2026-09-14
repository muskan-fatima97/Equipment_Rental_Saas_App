<?php

namespace App\Models;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphToMany;

class Equipment extends Model
{
    use HasFactory, BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'name',
        'description',
        'day_rate'
    ];
protected function casts():array{
    return ['day_rate'=>'decimal:2',];
}
    public function categories():MorphToMany{
        return $this->morphToMany(Category::class,'categorizable');
    }
    public function bookings():HasMany{
        return $this->hasMany(Booking::class);
    }
}
