<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Support\TenantContext;
class ResolveTenantContext
{

    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function __construct(protected TenantContext $tenantContext){
    }
    public function handle(Request $request, Closure $next): Response
    {
        if($request->user()){
            $this->tenantContext->set($request->user()->tenant_id());
        }
        return $next($request);
    }
}
