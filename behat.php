<?php

use Behat\Config\Config;
use Behat\Config\Profile;
use Behat\Config\Suite;
use Tests\Bdd\Context\ServiceOrderSagaContext;

// Testes BDD (Gherkin em português). Rodar: vendor/bin/behat
return (new Config())
    ->withProfile(
        (new Profile('default'))
            ->withSuite(
                (new Suite('saga_ordem_de_servico'))
                    ->withPaths('%paths.base%/tests/Bdd/features')
                    ->withContexts(ServiceOrderSagaContext::class)
            )
    );
