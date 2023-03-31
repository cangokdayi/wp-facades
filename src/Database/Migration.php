<?php

namespace Cangokdayi\WPFacades\Database;

use Cangokdayi\WPFacades\Traits\InteractsWithDatabase;

/**
 * Base class for migrations. There's no smart migration handling like Laravel.
 */
abstract class Migration
{
    use InteractsWithDatabase;

    /**
     * WPDB Database client
     */
    protected \wpdb $database;

    /**
     * Queries/migrations to run on plugin **activation** event
     */
    abstract public function up();

    /**
     * Reverse queries/migrations to run on plugin **uninstallation** event
     */
    abstract public function down();

    public function __construct()
    {
        $this->database = $this->database();
    }
}
