<?php

namespace Cangokdayi\WPFacades\Services;

use Cangokdayi\WPFacades\Traits\HandlesFiles;
use Cangokdayi\WPFacades\Traits\HandlesViews;
use Cangokdayi\WPFacades\Traits\InteractsWithDatabase;

/**
 * Helper service for database migrations
 * 
 * For automatic migrations, you need to set up plugin activation & uninstall
 * hooks in your plugin bootstrap file. See migrate() and revertMigrations() for
 * details.
 */
class MigrationService
{
    use InteractsWithDatabase, HandlesFiles, HandlesViews;

    /**
     * Path to the migration files
     */
    private string $migrationsDir;

    /**
     * Name of the migrations table
     */
    private const MIGRATIONS_TABLE = 'wp_facades__migrations';

    /**
     * @param ?string $migrationsFolder Relative path to the migration files,
     *                                  defaults to the "migrations" folder in 
     *                                  project root & it can also be overridden
     *                                  with the env val "WPF_MIGRATIONS_FOLDER"
     * 
     * @throws \InvalidArgumentException If the given path is invalid
     */
    public function __construct(?string $migrationsFolder = null)
    {
        $this->migrationsDir = $this->getBasePath(
            $migrationsFolder
                ?? $_ENV['WPFS_MIGRATIONS_FOLDER']
                ?? 'migrations'
        );

        if (!is_dir($this->migrationsDir)) {
            throw new \InvalidMenuException(
                'The given path is not a directory'
            );
        }
    }

    /**
     * Runs the migrations on plugin activation event
     * 
     * You can call this method on activation hook like below;
     * 
     * ```php
     * register_activation_hook(
     *      __FILE__, 
     *      [(new MigrationService("some-custom-path")), "migrate"]
     * );
     * ```
     */
    public function migrate(): void
    {
        $this->createMigrationsTable();

        $migrations = $this->filterMigrationFiles($this->getMigrationFiles());
        $records = [];

        foreach ($migrations as $migration) {
            (include_once $migration)->up();

            $records[$migration] = time();
        }

        $this->saveMigrationRecords($records);
    }

    /**
     * Reverts the migrations on plugin uninstallation event
     * 
     * You can call this method on deactivation or uninstall hooks like below;
     * 
     * ```php
     * register_uninstall_hook(
     *      __FILE__, 
     *      [MigrationService::class, "revertMigrations"]
     * );
     * 
     * // OR 
     * 
     * $revert = static function () {
     *      $_ENV['WPF_MIGRATIONS_FOLDER'] = 'some-custom-path';
     *
     *      MigrationService::revertMigrations();
     * };
     * 
     * register_uninstall_hook(__FILE__, $revert);
     * ```
     */
    public static function revertMigrations(): void
    {
        $migrations = (new static())->getMigrationFiles();

        foreach (array_reverse($migrations) as $migration) {
            (include_once $migration)->down();
        }

        (new static())->deleteMigrationsTable();
    }

    private function getMigrationFiles(): array
    {
        return $this->sortFilesByDate(
            $this->getFilesFromDir($this->migrationsDir)
        );
    }

    /**
     * Saves the given migration records to the migrations table
     * 
     * @param array<string, int> Entries in [timestamp => filename] format
     */
    private function saveMigrationRecords(array $migrations): void
    {
        $table = $this->tableName(self::MIGRATIONS_TABLE);

        foreach ($migrations as $migration => $timestamp) {
            $this->database()->insert($table, [
                'migration' => $this->getFileName($migration),
                'timestamp' => $timestamp
            ]);
        }
    }

    /**
     * Creates a migrations table in db to keep track of previously executed
     * migrations
     */
    private function createMigrationsTable(): void
    {
        $table = $this->tableName(self::MIGRATIONS_TABLE);

        dbDelta("CREATE TABLE IF NOT EXISTS $table (
            id int UNSIGNED NOT NULL AUTO_INCREMENT,
            migration varchar(255) NOT NULL,
            `timestamp` int(50) NOT NULL,
            PRIMARY KEY (id)
        ); {$this->tableCollate()}");
    }

    /**
     * Removes the migrations table on plugin uninstall event
     */
    private function deleteMigrationsTable(): void
    {
        $table = $this->tableName(self::MIGRATIONS_TABLE);

        $this->database()->query("DROP TABLE IF EXISTS $table");
    }

    /**
     * Removes the previously migrated migrations from the given list
     */
    private function filterMigrationFiles(array $files): array
    {
        $table = $this->tableName(self::MIGRATIONS_TABLE);
        $database = $this->database();

        foreach ($files as $i => $file) {
            $query = $database->prepare(
                "SELECT 'timestamp' FROM $table WHERE migration = %s",
                $this->getFileName($file)
            );

            if (!is_null($database->get_var($query))) {
                unset($files[$i]);
            }
        }

        return $files;
    }
}
