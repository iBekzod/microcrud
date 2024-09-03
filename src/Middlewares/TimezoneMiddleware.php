<?php

namespace Microcrud\Middlewares;

use Carbon\Carbon;
use Closure;
use Illuminate\Http\Request;

class TimezoneMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        if ($timezone = $request->header('Timezone', 'Asia/Tashkent')) {
            date_default_timezone_set($timezone);
            // $possible_dates = [
            //     'created_at',
            //     'updated_at',
            //     'deleted_at',
            //     'start_date',
            //     'end_date',
            //     'from_date',
            //     'to_date',
            //     'date',
            // ];
            // foreach ($possible_dates as $date_filed) {
            //     $value = request()->{$date_filed};
            //     if(isset($value)){
            //         if(is_int($value)){
            //             request()->merge([
            //                 "{$date_filed}" => Carbon::parse(date("Y-m-d H:i:s", $value))->setTimezone('UTC'),
            //             ]);
            //         }else if(is_string($value)){
            //             request()->merge([
            //                 "{$date_filed}" => Carbon::parse($value)->setTimezone('UTC')->toDateTimeString(),
            //             ]);
            //         }
            //     }
            // }
        }
        return $next($request);
    }
}
