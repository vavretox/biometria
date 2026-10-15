<?php

namespace Tests\Feature;

use Tests\TestCase;

class ExampleTest extends TestCase
{
    public function test_biometric_devices_require_authentication(): void
    {
        $this->get('/biometric/devices')->assertRedirectToRoute('login');
    }
}
