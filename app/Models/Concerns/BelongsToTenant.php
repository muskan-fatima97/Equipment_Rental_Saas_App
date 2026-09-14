<?php

namespace App\Models\Concerns;

use App\Models\Scopes\TenantScope;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Elequent\Relations\BelongsTo;
use App\Models\Tenant;
use App\Support\TenantContext;

trait BelongsToTenant {
    public static function bootBelongsToTenant():void{
        static::addGlobalScope(new TenantScope());
        static::creating(function($model){
            if(empty($model->tenantId)){
                $model->tenantId=app(TenantContext::class)->get();
            }
        });
    }
    public function tenant():BelongsTo{
        return $this->belongsTo(Tenant::class);
    }

    public function resolveRouteBinding($value, $field = null){
        $field ??=$this->getRouteKeyName();

        $record=static::withoutGlobalScope(TenantScope::class)->where($field, $value)->first();
        if(! $record){
            return null;
        }
        $currentTenantId=app(TenantContext::class)->get();
        if($record->tenantId !== $$currentTenantId){
            throw new AuthenticationException('You do not have access for this resource');
        }
        return $record;
    }

}