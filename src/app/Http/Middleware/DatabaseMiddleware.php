<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;

class DatabaseMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle($request, Closure $next)
    {
        $host = $request->getHost();

        $databaseConfig = $this->getDatabaseConfig($host);

        if ($databaseConfig) {
            $this->switchDatabaseConnection($databaseConfig);
        }

        return $next($request);
    }

    private function getDatabaseConfig($host)
    {
        $cacheKey = 'database_config:' . $host;
        $cacheTime = 360; // minutes

        return Cache::remember($cacheKey, $cacheTime, function () use ($host) {
            $configFilePath = storage_path('database_config.json');
            $customConfig = json_decode(file_get_contents($configFilePath), true);

            return $customConfig['hosts'][$host] ?? null;
        });
    }


    private function switchDatabaseConnection($config)
    {
        Config::set('database.connections.mysql.database', $config['database']);
        Config::set('database.connections.mysql.username', $config['username']);
        Config::set('database.connections.mysql.password', $config['password']);

        // Set the connection as persistent
        Config::set('database.connections.mysql.persistent', true);

        // Reconnect to the database
        DB::reconnect();

        // Make the connection active
        DB::setDefaultConnection('mysql');
    }
}
