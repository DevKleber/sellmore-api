<?php

namespace Tests\Unit;

use App\Console\BloqueioDeComandosDestrutivos;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class BloqueioDeComandosDestrutivosTest extends TestCase
{
    private const BANCO_DE_PRODUCAO = 'ipeweb51_wiseller';
    private const BANCO_LOCAL = 'wiseller';
    private const BANCO_DE_TESTES = 'wiseller_test';

    #[DataProvider('comandosDestrutivos')]
    public function test_comando_destrutivo_e_bloqueado_fora_do_banco_de_testes(string $comando)
    {
        $this->assertTrue(BloqueioDeComandosDestrutivos::deveBloquear($comando, self::BANCO_DE_PRODUCAO));
        $this->assertTrue(BloqueioDeComandosDestrutivos::deveBloquear($comando, self::BANCO_LOCAL));
    }

    #[DataProvider('comandosDestrutivos')]
    public function test_comando_destrutivo_e_liberado_no_banco_de_testes(string $comando)
    {
        $this->assertFalse(BloqueioDeComandosDestrutivos::deveBloquear($comando, self::BANCO_DE_TESTES));
    }

    public static function comandosDestrutivos(): array
    {
        return [['migrate:fresh'], ['migrate:refresh'], ['migrate:reset'], ['migrate:rollback'], ['db:wipe']];
    }

    #[DataProvider('comandosSeguros')]
    public function test_comando_que_nao_apaga_dados_continua_liberado_em_producao(string $comando)
    {
        $this->assertFalse(BloqueioDeComandosDestrutivos::deveBloquear($comando, self::BANCO_DE_PRODUCAO));
    }

    public static function comandosSeguros(): array
    {
        return [['migrate'], ['migrate:status'], ['package:discover'], ['route:list']];
    }
}
