<?php

use Illuminate\Database\Capsule\Manager as Capsule;

/**
 * Class Database
 */
class Database
{


    private $settings = array();
    private $capsule;

    /**
     * Create new Capsule and init database connection
     */
    public function __construct()
    {
        $this->initSettings();
        $this->capsule = new Capsule;
        $this->capsule->addConnection($this->settings);
        $this->capsule->bootEloquent();

        return $this->capsule;
    }

    /**
     * Get database configuration
     */
    private function initSettings()
    {
        $f3 = \Base::instance();
        $this->settings = [
            'driver' => 'mysql',
            'host' => $f3->get('DB_HOST'),
            'database' => $f3->get('DB_NAME'),
            'username' => $f3->get('DB_USER'),
            'password' => $f3->get('DB_PASSWORD'),
            'charset' => 'utf8',
            'collation' => 'utf8_unicode_ci',
            'prefix' => ''
        ];
    }
}