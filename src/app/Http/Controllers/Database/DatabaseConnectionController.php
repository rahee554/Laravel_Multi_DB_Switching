<?php

namespace App\Http\Controllers\Database;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

class DatabaseConnectionController extends Controller
{
    public function setDatabaseConnection(Request $request)
    {
        $database = $request->input('database');
        $username = $request->input('username');
        $password = $request->input('password');

        // Store the current database connection details
        $currentConfig = $this->getCurrentDatabaseConfig();

        // Set the new database connection
        $this->setNewDatabaseConfig($database, $username, $password);

        // Get the connected database name
        $connectedDatabase = DB::getDatabaseName();

        // Run the migration and seeding commands
        $migrationResult = $this->runMigrateAndSeed();

        // Restore the previous database connection
        $this->restoreDatabaseConfig($currentConfig);

        if ($migrationResult) {
            return response()->json(['success' => true, 'message' => 'Database connection set successfully.', 'database' => $connectedDatabase]);
        } else {
            return response()->json(['success' => false, 'message' => 'An error occurred during migration and seeding.']);
        }
    }

    private function getCurrentDatabaseConfig()
    {
        $currentConfig = [
            'database' => config('database.connections.mysql.database'),
            'username' => config('database.connections.mysql.username'),
            'password' => config('database.connections.mysql.password'),
        ];

        return $currentConfig;
    }

    private function setNewDatabaseConfig($database, $username, $password)
    {
        Config::set('database.connections.mysql.database', $database);
        Config::set('database.connections.mysql.username', $username);
        Config::set('database.connections.mysql.password', $password);
        DB::purge('mysql');
    }

    private function runMigrateAndSeed()
    {
        try {
            // Reseting All migrations
            Artisan::call('migrate:reset');
            Artisan::call('migrate', ['--force' => true]);
            Artisan::call('db:seed', ['--force' => true]);
            // Artisan::call('passport:install', ['--force' => true]);
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    private function getMigrationCount()
    {
        $migrationPath = database_path('migrations');
        $migrationFiles = File::glob($migrationPath . '/*.php');

        return count($migrationFiles);
    }

    private function getSeederCount()
    {
        $seederPath = database_path('seeders');
        $seederFiles = File::glob($seederPath . '/*.php');

        return count($seederFiles);
    }

    private function restoreDatabaseConfig($config)
    {
        Config::set('database.connections.mysql.database', $config['database']);
        Config::set('database.connections.mysql.username', $config['username']);
        Config::set('database.connections.mysql.password', $config['password']);
        DB::purge('mysql');
    }
}
