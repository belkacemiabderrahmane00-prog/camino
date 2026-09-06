<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
        protected function setUp(): void
    {
        parent::setUp();
        // Les tests décrivent l'interface en français ; le client de test envoie sinon Accept-Language: en-us.
        $this->withHeader('Accept-Language', 'fr');
    }

//
}
