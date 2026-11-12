<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Laravel\Fortify\Features;
use RuntimeException;

abstract class TestCase extends BaseTestCase
{
    /**
     * Refuse to run against anything but the dedicated "testing" database.
     *
     * Runs before traits like RefreshDatabase, which drop every table, so a
     * misconfigured environment fails loudly instead of wiping real data.
     */
    public function createApplication()
    {
        $app = parent::createApplication();

        $connection = config('database.default');
        $database = config("database.connections.{$connection}.database");

        if ($database !== 'testing') {
            throw new RuntimeException(
                "Tests must use the 'testing' database, got [{$connection}:{$database}]. Check phpunit.xml.",
            );
        }

        return $app;
    }

    protected function skipUnlessFortifyHas(string $feature, ?string $message = null): void
    {
        if (! Features::enabled($feature)) {
            $this->markTestSkipped($message ?? "Fortify feature [{$feature}] is not enabled.");
        }
    }
}
