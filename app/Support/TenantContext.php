<?php

namespace App\Support;

class TenantContext {
    protected ?int $tenantId=null;

    public function set(?int $tenantId):void{
    $this->tenantId=$tenantId;
    }

    public function get(): ?int{
        return $this->tenantId;
    }

    public function check():bool{
        return $this->tenantId !== null;
    }

    public function clear():void{
        $this->tenantId=null;
    }
}