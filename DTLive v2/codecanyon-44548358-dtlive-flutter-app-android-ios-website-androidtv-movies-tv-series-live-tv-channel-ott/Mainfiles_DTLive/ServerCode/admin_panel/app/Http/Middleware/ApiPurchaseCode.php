<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Exception;
use Illuminate\Support\Facades\Artisan;

class ApiPurchaseCode
{
    public function handle(Request $request, Closure $next)
    {
        try {

            // Some self-hosted deployments do not have an active marketplace
            // registration. Keep this opt-in so the public API can be used
            // without stale purchase-code values on those installations.
            if (filter_var(env('DTLIVE_ALLOW_UNVERIFIED_API', false), FILTER_VALIDATE_BOOLEAN)) {
                return $next($request);
            }

            Artisan::call('config:clear');

            $verflyDomain = Demo_Domain();
            if ($verflyDomain == 1) {

                return $next($request);
            } else {

                $pc = env(base64_decode('UFVSQ0hBU0VfQ09ERQ=='));
                $un = env(base64_decode('QlVZRVJfVVNFUk5BTUU='));
                $status = env(base64_decode('UFVSQ0hBU0VfU1RBVFVT'));

                if (!empty($pc) && !empty($un) && $status == 1) {
                    return $next($request);
                } else {
                    return response()->json(['status' => 400, 'errors' => __('label.purchase_code_is_not_verifly')]);
                }
            }
        } catch (Exception $e) {
            return response()->json(['status' => 400, 'errors' => $e->getMessage()]);
        }
    }
}
