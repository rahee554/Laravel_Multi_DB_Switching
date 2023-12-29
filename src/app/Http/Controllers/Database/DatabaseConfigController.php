<?php

namespace App\Http\Controllers\Database;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Schema;

class DatabaseConfigController extends Controller
{

    // Saving Databas
    public function save(Request $request)
    {
        $host = $request->input('host');
        $database = $request->input('database');
        $username = $request->input('username');
        $password = $request->input('password');

        // Update the database_config.json file with the new values
        $configFilePath = storage_path('database_config.json');
        $configData = json_decode(file_get_contents($configFilePath), true);

        $configData['hosts'][$host] = [
            'database' => $database ?: "",
            // Store an empty string if $database is null or empty
            'username' => $username ?: "",
            // Store an empty string if $username is null or empty
            'password' => $password ?: "", // Store an empty string if $password is null or empty
        ];

        file_put_contents($configFilePath, json_encode($configData));

        return response()->json(['success' => true]);
    }


    // Editing Database 
    public function edit($host)
    {
        // Retrieve the existing database configuration
        $configFilePath = storage_path('database_config.json');
        $configData = json_decode(file_get_contents($configFilePath), true);
        $existingConfig = $configData['hosts'][$host] ?? null;

        return view('edit_database_config', compact('host', 'existingConfig'));
    }

    public function update(Request $request, $host)
    {
        $database = $request->input('database');
        $username = $request->input('username');
        $password = $request->input('password');

        // Update the database_config.json file with the updated values
        $configFilePath = storage_path('database_config.json');
        $configData = json_decode(file_get_contents($configFilePath), true);

        if (isset($configData['hosts'][$host])) {
            $configData['hosts'][$host]['database'] = $database ?: "";
            $configData['hosts'][$host]['username'] = $username ?: "";
            $configData['hosts'][$host]['password'] = $password ?: "";

            // Save the updated configuration back to the file
            file_put_contents($configFilePath, json_encode($configData));

            return response()->json(['success' => true]);
        }

        return response()->json(['success' => false, 'message' => 'Entry not found']);
    }



    public function delete($host)
    {
        // Update the database_config.json file by removing the host entry
        $configFilePath = storage_path('database_config.json');
        $configData = json_decode(file_get_contents($configFilePath), true);

        if (isset($configData['hosts'][$host])) {
            unset($configData['hosts'][$host]);

            file_put_contents($configFilePath, json_encode($configData));

            return response()->json(['success' => true]);
        }

        return response()->json(['success' => false, 'message' => 'Entry not found']);
    }


    public function createDatabase(Request $request)
    {
        // Retrieve the root user details from the database_config.json file
        $configFilePath = storage_path('database_config.json');
        $configData = json_decode(file_get_contents($configFilePath), true);
        $rootConfig = $configData['root'] ?? null;

        // Check if root user details exist
        if (!$rootConfig || empty($rootConfig['username'])) {
            return response()->json(['success' => false, 'message' => 'Root user details not found']);
        }

        // Connect to MySQL using root user details
        $rootUsername = $rootConfig['username'];
        $rootPassword = $rootConfig['password'];
        $host = $rootConfig['host'] ?? 'localhost';

        try {
            // Set the root user credentials dynamically
            config(['database.connections.mysql.host' => $host]);
            config(['database.connections.mysql.username' => $rootUsername]);
            config(['database.connections.mysql.password' => $rootPassword]);

            // Reconnect to the database with the updated root user credentials
            DB::reconnect();

            // Create the new database
            $databaseName = $request->input('database');
            $createDatabaseStatement = "CREATE DATABASE IF NOT EXISTS {$databaseName}";
            if ($rootPassword === '') {
                $createDatabaseStatement .= " CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci";
            }
            DB::statement($createDatabaseStatement);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Failed to create database: ' . $e->getMessage()]);
        }

        return response()->json(['success' => true, 'message' => 'Database created successfully']);
    }
    public function showDatabases()
    {
        try {
            // Retrieve database configuration data
            $configFilePath = storage_path('database_config.json');
            $configData = json_decode(file_get_contents($configFilePath), true);
            $rootConfig = $configData['root'] ?? null;
            $databasePrefix = $configData['database_prefix'] ?? '';

            // Validate root user details
            if (!$rootConfig || empty($rootConfig['username'])) {
                return response()->json(['success' => false, 'message' => 'Root user details not found']);
            }

            // Establish connection to MySQL using root user details
            $rootUsername = $rootConfig['username'];
            $rootPassword = $rootConfig['password'];
            $host = $rootConfig['host'] ?? 'localhost';

            config(['database.connections.mysql.host' => $host]);
            config(['database.connections.mysql.username' => $rootUsername]);
            config(['database.connections.mysql.password' => $rootPassword]);

            // Reconnect to the database with updated credentials
            DB::reconnect();

            // Retrieve databases with the specified prefix
            $databaseNames = $this->getDatabasesWithPrefix($databasePrefix);

            // Prepare database details including migration counts
            $databaseDetails = [];
            foreach ($databaseNames as $databaseName) {
                $databaseSize = $this->getDatabaseSize($databaseName);
                $totalRows = $this->getTotalRows($databaseName);
                $tableCount = $this->getTableCount($databaseName);

                $migrationFileCount = $this->countMigrationFiles();
                $migrationTableCount = $this->countMigrationTables();

                $databaseDetails[] = [
                    'name' => $databaseName,
                    'size' => $databaseSize,
                    'rows' => $totalRows,
                    'tableCount' => $tableCount,
                    'migrationTableCount' => $migrationTableCount,
                ];
            }

            // Pass the combined data to the view using consistent variable name
            return view('app_admin.database.show_databases', ['databaseDetails' => $databaseDetails]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Failed to fetch databases: ' . $e->getMessage()]);
        }
    }


    private function getTableCount($databaseName)
    {
        $connection = config('database.connections.mysql');
        $pdo = new \PDO(
            "mysql:host={$connection['host']};port={$connection['port']}",
            $connection['username'],
            $connection['password']
        );

        $query = "SELECT COUNT(*) AS table_count FROM information_schema.tables WHERE table_schema = :databaseName";
        $statement = $pdo->prepare($query);
        $statement->bindParam(':databaseName', $databaseName);
        $statement->execute();

        $result = $statement->fetch(\PDO::FETCH_ASSOC);

        return $result ? $result['table_count'] : 0;
    }




    private function getDatabaseSize($databaseName)
    {
        $connection = config('database.connections.mysql');
        $pdo = new \PDO(
            "mysql:host={$connection['host']};port={$connection['port']}",
            $connection['username'],
            $connection['password']
        );

        $query = "SELECT
                    ROUND(SUM(data_length + index_length) / 1024 / 1024, 2) AS size_mb
                  FROM information_schema.tables
                  WHERE table_schema = :databaseName";
        $statement = $pdo->prepare($query);
        $statement->bindParam(':databaseName', $databaseName);
        $statement->execute();

        $result = $statement->fetch(\PDO::FETCH_ASSOC);

        return $result ? $result['size_mb'] . ' MB' : 'N/A';
    }

    private function getTotalRows($databaseName)
    {
        $connection = config('database.connections.mysql');
        $pdo = new \PDO(
            "mysql:host={$connection['host']};port={$connection['port']}",
            $connection['username'],
            $connection['password']
        );

        $query = "SELECT SUM(table_rows) AS total_rows FROM information_schema.tables WHERE table_schema = :databaseName";
        $statement = $pdo->prepare($query);
        $statement->bindParam(':databaseName', $databaseName);
        $statement->execute();

        $result = $statement->fetch(\PDO::FETCH_ASSOC);

        return $result ? $result['total_rows'] : 0;
    }



    private function getDatabasesWithPrefix($prefix)
    {
        $connection = config('database.connections.mysql');

        $dsn = "mysql:host={$connection['host']};port={$connection['port']}";
        $username = $connection['username'];
        $password = $connection['password'];

        $pdo = new \PDO($dsn, $username, $password);

        $query = "SHOW DATABASES";
        $statement = $pdo->query($query);

        $databases = [];
        while ($row = $statement->fetch(\PDO::FETCH_ASSOC)) {
            $databaseName = $row['Database'];
            if (strpos($databaseName, $prefix) === 0) {
                $databases[] = $databaseName;
            }
        }

        return $databases;
    }



    // Count Migration files in Code
    private function countMigrationFiles()
    {
        $migrationPath = database_path('migrations');
        $migrationFiles = glob($migrationPath . '/*.php');
        return count($migrationFiles);
    }


    // Count Records of Migrations Table
    private function countMigrationTables()
    {
        $tables = Schema::getConnection()->getDoctrineSchemaManager()->listTableNames();
        $migrationTables = array_filter($tables, function ($table) {
            return strpos($table, 'migrations') !== false;
        });
        return count($migrationTables);
    }
}
