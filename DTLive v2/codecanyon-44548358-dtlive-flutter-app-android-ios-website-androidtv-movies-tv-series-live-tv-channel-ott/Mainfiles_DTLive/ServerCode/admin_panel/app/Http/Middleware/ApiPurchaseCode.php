<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Exception;

class ApiPurchaseCode
{
    public function handle(Request $request, Closure $next)
    {
        try {

            // Some self-hosted deployments do not have an active marketplace
            // registration. Keep this opt-in so the public API can be used
            // without stale purchase-code values on those installations.
            if (filter_var(config('installation.allow_unverified_api', false), FILTER_VALIDATE_BOOLEAN)) {
                return $next($request);
            }

            $verflyDomain = Demo_Domain();
            if ($verflyDomain == 1) {

                return $next($request);
            } else {

                $pc = config('installation.purchase_code');
                $un = config('installation.buyer_username');
                $status = config('installation.purchase_status');

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
