<?php

namespace Cangokdayi\WPFacades\Database;

use Cangokdayi\WPFacades\Traits\InteractsWithDatabase;
use Faker\Factory;
use Faker\Generator;

abstract class Seeder
{
    use InteractsWithDatabase;

    /**
     * Faker instance
     */
    protected Generator $faker;

    /**
     * WPDB Database client
     */
    protected \wpdb $database;

    public function __construct()
    {
        $this->faker = Factory::create('en_EN');
        $this->database = $this->database();
        $this->run();
    }

    /**
     * Runs the seeder
     */
    abstract public function run();
}