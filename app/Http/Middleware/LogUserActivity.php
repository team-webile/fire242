<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Spatie\Activitylog\Facades\LogActivity;
use GeoIp2\Database\Reader;

class LogUserActivity
{
    public function handle(Request $request, Closure $next)
    {

        \Log::info('middleware called');

        if (Auth::check()) {
            try {
                $geoData = $this->getGeoInfo($request->ip());
                \Log::info('Geo data:', ['data' => $geoData]);
                activity('auth')
                    ->causedBy(Auth::user())
                    ->withProperties([
                        'ip' => $request->ip(),
                        'user_agent' => $request->userAgent(),
                        'session_id' => session()->getId(),
                        'login_time' => now(),
                        'geo' => $geoData,
                    ])
                    ->log('User Logged In');
            } catch (\Exception $e) {
                \Log::error('Failed to log user activity: ' . $e->getMessage());
            }
        }
        else{
            \Log::info('Geo data:', ['data' => 'unknow']);
        }

        return $next($request);
    }

    private function getGeoInfo($ip)
    {
        try {
            $reader = new Reader(storage_path('geoip/GeoLite2-City.mmdb'));
            $record = $reader->city($ip);
           
            $data = json_encode($record);

            return $data;

        } catch (\GeoIp2\Exception\AddressNotFoundException $e) {
            \Log::warning('IP address not found in GeoIP database: ' . $ip);
            return ['error' => 'IP address not found'];
        } catch (\MaxMind\Db\Reader\InvalidDatabaseException $e) {
            \Log::error('Invalid GeoIP database: ' . $e->getMessage());
            return ['error' => 'Invalid GeoIP database'];
        } catch (\Exception $e) {
            \Log::error('GeoIP lookup failed: ' . $e->getMessage());
            return ['error' => 'GeoIP lookup failed'];
        }   
    }
}
