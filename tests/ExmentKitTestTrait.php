<?php

namespace Exceedone\Exment\Tests;

use Exceedone\Exment\Model\System;
use Exceedone\Exment\Middleware\Morph;

trait ExmentKitTestTrait
{
    static $databaseSetup = false;

    /**
     * Define hooks to migrate the database before and after each test.
     *
     * @return void
     */
    public function runDatabaseMigrations()
    {
        if (!\App::environment('local', 'testing')) {
            return;
        }
        
        if (!static::$databaseSetup) {
            \Artisan::call('migrate:fresh');
            
            System::clearRequestSession();
            Morph::defineMorphMap();

            \Artisan::call('exment:install');

            static::$databaseSetup = true;
        }
    }
}