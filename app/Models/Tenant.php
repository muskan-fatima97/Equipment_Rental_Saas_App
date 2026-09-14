<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Tenant extends Model
{
    use HasFactory;
    protected $fillable=[
        'company_name',
        'subscription_tier',
    ];

    public function users():HasMany{
        return $this->HasMany(User::class);
    }
}
