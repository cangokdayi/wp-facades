<?php

namespace Cangokdayi\WPFacades\Traits;

use Cangokdayi\WPFacades\Database\TableSchema;
use wpdb;

// For dbDelta()
require_once(ABSPATH . 'wp-admin/includes/upgrade.php');

/**
 * Helper methods for wpdb class and database transactions
 */
trait InteractsWithDatabase
{
    /**
     * Returns the given table name with WordPress table prefix added to it
     */
    public function tableName(string $tableName): string
    {
        return $this->database()->prefix . $tableName;
    }

    /**
     * Returns the charset collate of the database
     */
    public function tableCollate(): string
    {
        return $this->database()->collate;
    }

    /**
     * Returns the global WPDB instance
     */
    public function database(): wpdb
    {
        global $wpdb;

        return $wpdb;
    }

    /**
     * Returns the schema of the given table
     * 
     * @param string $tableName Without the wpdb prefix
     */
    public function tableSchema(string $tableName): TableSchema
    {
        return new TableSchema($tableName);
    }

    /**
     * Retuns the total rows count of the given table
     */
    public function getRowCount(string $table): int
    {
        $rows = $this->database()
            ->get_var("SELECT COUNT(*) FROM $table");

        return $rows ?? 0;
    }
}
