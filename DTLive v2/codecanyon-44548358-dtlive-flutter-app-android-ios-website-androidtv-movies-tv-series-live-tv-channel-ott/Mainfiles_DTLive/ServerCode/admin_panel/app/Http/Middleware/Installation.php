<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Exception;
use Illuminate\Support\Facades\DB;

class Installation
{
    public function handle(Request $request, Closure $next)
    {
        // This opt-in disables only the installer/license gate; admin authentication remains active.
        if (filter_var(config('installation.allow_unverified_admin', false), FILTER_VALIDATE_BOOLEAN)) {
            return $next($request);
        }

        try {
            $verflyDomain = Demo_Domain();
            if ($verflyDomain == 1) {

                return $next($request);
            } else {

                $pc = config('installation.purchase_code');
                $un = config('installation.buyer_username');
                $status = config('installation.purchase_status');

                if (!empty($pc) && !empty($un) && $status == 1) {

                    DB::connection()->getPdo();
                    return $next($request);
                } else {
                    return redirect()->route('step0');
                }
            }
        } catch (Exception $e) {
            session()->flash('error', $e->getMessage());
            return redirect()->route('step0');
        }
    }
}
