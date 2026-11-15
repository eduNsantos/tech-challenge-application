<?php

namespace Tests\Bdd\Support;

use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\TextUI\CliArguments\Builder as CliBuilder;
use PHPUnit\TextUI\Configuration\Registry;
use PHPUnit\TextUI\XmlConfiguration\DefaultConfiguration;
use Tests\TestCase;

/**
 * Reaproveita o Tests\TestCase do PHPUnit dentro do Behat.
 *
 * O Behat não executa o PHPUnit, então este harness expõe o setUp/tearDown
 * para os hooks dos contextos. Com isso os cenários BDD usam a mesma base
 * dos testes de Feature: banco SQLite em memória com migrations (RefreshDatabase),
 * FakePaymentGateway no lugar do payment-service e os helpers HTTP do Laravel
 * (postJson, actingAs, assertDatabaseHas...).
 *
 * O arquivo não termina em "Test.php", então o PHPUnit não o executa.
 */
final class LaravelHarness extends TestCase
{
    use RefreshDatabase;

    /**
     * Mesmo ambiente do phpunit.xml (o Behat não lê esse arquivo).
     * Precisa rodar antes de o Laravel subir, senão o .env tem prioridade.
     */
    private const ENV = [
        'APP_ENV' => 'testing',
        'APP_MAINTENANCE_DRIVER' => 'file',
        'BCRYPT_ROUNDS' => '4',
        'BROADCAST_CONNECTION' => 'null',
        'CACHE_STORE' => 'array',
        'DB_CONNECTION' => 'sqlite',
        'DB_DATABASE' => ':memory:',
        'DB_URL' => '',
        'MAIL_MAILER' => 'array',
        'QUEUE_CONNECTION' => 'sync',
        'SESSION_DRIVER' => 'array',
        'PAYMENT_CALLBACK_BASE_URL' => '',
        'NEW_RELIC_ENABLED' => 'false',
    ];

    public static function configureEnvironment(): void
    {
        foreach (self::ENV as $key => $value) {
            self::setEnv($key, $value);
        }

        // Valores de teste apenas quando o ambiente não define (ex.: fora do container de CI)
        if (! getenv('APP_KEY')) {
            self::setEnv('APP_KEY', 'base64:'.base64_encode(str_repeat('b', 32)));
        }

        if (! getenv('JWT_SECRET')) {
            self::setEnv('JWT_SECRET', str_repeat('bdd-jwt-secret-', 4));
        }

        // As asserções do PHPUnit leem a configuração do runner ao montar a mensagem de falha;
        // fora do PHPUnit ela precisa ser inicializada com os valores padrão.
        Registry::init(
            (new CliBuilder())->fromParameters([]),
            DefaultConfiguration::create()
        );
    }

    public function boot(): void
    {
        $this->setUp();
    }

    public function shutdown(): void
    {
        $this->tearDown();
    }

    public function application(): \Illuminate\Foundation\Application
    {
        return $this->app;
    }

    public function paymentGateway(): \Tests\Fakes\FakePaymentGateway
    {
        return $this->paymentGateway;
    }

    /** @param  array<string, mixed>  $data */
    public function seeInDatabase(string $table, array $data): void
    {
        $this->assertDatabaseHas($table, $data);
    }

    /** @param  array<string, mixed>  $data */
    public function dontSeeInDatabase(string $table, array $data): void
    {
        $this->assertDatabaseMissing($table, $data);
    }

    /** Troca de ator entre requisições (atendente → mecânico → chamadas sem usuário). */
    public function logout(): void
    {
        $this->app['auth']->forgetGuards();
    }

    private static function setEnv(string $key, string $value): void
    {
        putenv("{$key}={$value}");
        $_ENV[$key] = $value;
        $_SERVER[$key] = $value;
    }
}
