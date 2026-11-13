<?php

namespace Tests\Feature;

use Tests\TestCase;

class DesignSystemPageTest extends TestCase
{
    public function test_the_design_system_page_is_not_exposed_outside_local_development(): void
    {
        // The test suite runs with APP_ENV=testing, so the route must not exist.
        $this->get('/dev/design-system')->assertNotFound();
    }
}
