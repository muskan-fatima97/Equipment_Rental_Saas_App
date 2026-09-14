<?php

namespace App\Models;

use App\Enums\BookingStatus;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Elequent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
class Booking extends Model
{

use HasFactory,BelongsToTenant;
    protected $fillable =[
        'tenant_id',
        'equipment_id',
        'user_id',
        'start_date',
        'end_date',
        'status'
    ];

    protected function casts():array{
        return(['start_date'=>'date','end_date'=>'date','status'=>BookingStatus::class]);
    }

    public function equipment():BelongsTo{
        return $this->belongsTo(Equipment::class);
    }

    public function user():BelongsTo{
        return $this->belongsTo(User::class);
    }
}
