<?php

namespace Tests\Unit;

use Helpers;
use PHPUnit\Framework\TestCase;

class HelpersTest extends TestCase
{
    private $servidorOriginal;

    protected function setUp(): void
    {
        parent::setUp();
        $this->servidorOriginal = $_SERVER;
    }

    protected function tearDown(): void
    {
        $_SERVER = $this->servidorOriginal;
        parent::tearDown();
    }

    /**
     * @dataProvider valoresParaContar
     */
    public function test_count_conta_listas_e_trata_valores_soltos($valor, int $esperado)
    {
        $this->assertSame($esperado, Helpers::count($valor));
    }

    public function valoresParaContar(): array
    {
        return [
            'lista com itens' => [[1, 2, 3], 3],
            'lista vazia' => [[], 0],
            'nulo conta zero' => [null, 0],
            'texto solto conta um' => ['texto', 1],
        ];
    }

    public function test_formata_cpf_e_cnpj_com_mascara()
    {
        $this->assertSame('123.456.789-09', Helpers::formatCnpjCpf('12345678909'));
        $this->assertSame('12.345.678/0001-95', Helpers::formatCnpjCpf('12345678000195'));
        $this->assertSame('123.456.789-09', Helpers::formatCnpjCpf('123.456.789-09'));
    }

    public function test_valida_extensao_contra_a_lista_permitida()
    {
        $this->assertTrue(Helpers::validarExt('png', ['jpg', 'png']));
        $this->assertFalse(Helpers::validarExt('exe', ['jpg', 'png']));
    }

    /**
     * @dataProvider datasBrasileiras
     */
    public function test_converte_data_brasileira_para_o_banco($entrada, $esperado)
    {
        $this->assertSame($esperado, Helpers::convertdateBr2DB($entrada));
    }

    public function datasBrasileiras(): array
    {
        return [
            'dia mes ano com barras' => ['20/05/2030', '2030-05-20'],
            'ja no formato do banco fica igual' => ['2030-05-20', '2030-05-20'],
            'vazio vira nulo' => ['', null],
            'nulo continua nulo' => [null, null],
        ];
    }

    /**
     * @dataProvider datasSemSeparador
     */
    public function test_converte_data_digitada_sem_separador(string $entrada, string $esperado)
    {
        $this->assertSame($esperado, Helpers::convertDateWithoutSeparatorToDatabase($entrada));
    }

    public function datasSemSeparador(): array
    {
        return [
            'so digitos' => ['20052030', '2030-05-20'],
            'com barras' => ['20/05/2030', '2030-05-20'],
            'com tracos fica igual' => ['2030-05-20', '2030-05-20'],
        ];
    }

    public function test_converte_hora_digitada_sem_separador()
    {
        $this->assertSame('14:30:00', Helpers::convertHourWithoutSeparatorToDatabase('143000'));
        $this->assertSame('14:30', Helpers::convertHourWithoutSeparatorToDatabase('14:30'));
    }

    public function test_remove_mascara_de_telefone()
    {
        $this->assertSame('62 999990001', Helpers::removerCaracteresPhone('(62) 99999-0001'));
    }

    public function test_remove_pontuacao_e_espacos()
    {
        $this->assertSame('12345678909', Helpers::removerCaracteresEspeciaisEspacos('123.456.789-09'));
        $this->assertSame('RuaA10', Helpers::removerCaracteresEspeciaisEspacos('Rua A, 10'));
    }

    /**
     * @dataProvider cpfs
     */
    public function test_valida_cpf(string $cpf, bool $valido)
    {
        $this->assertSame($valido, Helpers::validarCpf($cpf));
    }

    public function cpfs(): array
    {
        return [
            'valido sem mascara' => ['52998224725', true],
            'valido com mascara' => ['529.982.247-25', true],
            'digito verificador errado' => ['52998224724', false],
            'tamanho errado' => ['123', false],
            'vazio' => ['', false],
        ];
    }

    /**
     * @dataProvider numerosDeTelefone
     */
    public function test_acrescenta_nono_digito_em_celulares_de_dez_digitos(string $numero, $esperado)
    {
        $this->assertSame($esperado, Helpers::numeroNonoDigito($numero));
    }

    public function numerosDeTelefone(): array
    {
        return [
            'celular com dez digitos ganha o nove' => ['6299990001', '62999990001'],
            'celular com onze digitos fica igual' => ['62999990001', '62999990001'],
            'fixo comecando com tres fica igual' => ['6232220002', '6232220002'],
            'fixo comecando com dois fica igual' => ['6222220002', '6222220002'],
            'tamanho inesperado devolve falso' => ['999990001', false],
        ];
    }

    public function test_remove_emojis_do_texto()
    {
        $this->assertSame('Maria  Souza', Helpers::remove_emoji("Maria \u{1F600} Souza\u{2764}"));
        $this->assertSame('Sem emoji', Helpers::remove_emoji('Sem emoji'));
    }

    /**
     * @dataProvider sistemasOperacionais
     */
    public function test_identifica_o_sistema_pelo_user_agent(string $userAgent, string $esperado)
    {
        $_SERVER['HTTP_USER_AGENT'] = $userAgent;

        $this->assertSame($esperado, Helpers::getOSClient());
    }

    public function sistemasOperacionais(): array
    {
        return [
            'windows' => ['Mozilla/5.0 (Windows NT 10.0; Win64; x64)', 'Windows'],
            'linux' => ['Mozilla/5.0 (X11; Linux x86_64)', 'Linux'],
            'android tambem cita linux e vence por vir depois' => ['Mozilla/5.0 (Linux; Android 13)', 'Android'],
            'iphone' => ['Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X)', 'iPhone'],
            'ipad' => ['Mozilla/5.0 (iPad; CPU OS 17_0 like Mac OS X)', 'iPad'],
            'mac nao esta na lista' => ['Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7)', 'Unknown OS Platform'],
        ];
    }

    /**
     * @dataProvider navegadores
     */
    public function test_identifica_o_navegador_pelo_user_agent(string $userAgent, string $esperado)
    {
        $_SERVER['HTTP_USER_AGENT'] = $userAgent;

        $this->assertSame($esperado, Helpers::get_browser_name());
    }

    public function navegadores(): array
    {
        return [
            'chrome' => ['Mozilla/5.0 AppleWebKit/537.36 Chrome/120.0 Safari/537.36', 'Chrome'],
            'safari' => ['Mozilla/5.0 (Macintosh) AppleWebKit/605.1.15 Version/17.0 Safari/605.1.15', 'Safari'],
            'firefox' => ['Mozilla/5.0 (X11; Linux x86_64; rv:120.0) Gecko/20100101 Firefox/120.0', 'Firefox'],
            'opera' => ['Mozilla/5.0 AppleWebKit/537.36 Chrome/120.0 Safari/537.36 OPR/106.0', 'Opera'],
            'edge antigo' => ['Mozilla/5.0 AppleWebKit/537.36 Chrome/64.0 Safari/537.36 Edge/17.0', 'Edge'],
            'internet explorer' => ['Mozilla/5.0 (Windows NT 10.0; Trident/7.0; rv:11.0) like Gecko', 'Internet Explorer'],
            'robo do google' => ['Googlebot/2.1 (+http://www.google.com/bot.html)', '[Bot] Googlebot'],
            'desconhecido' => ['curl/8.0', 'Other (Unknown)'],
        ];
    }

    public function test_ip_do_cliente_prefere_cabecalhos_de_proxy()
    {
        $_SERVER['REMOTE_ADDR'] = '10.0.0.1';
        $ipDireto = Helpers::getUserIpAddr();
        $_SERVER['HTTP_X_FORWARDED_FOR'] = '203.0.113.7';
        $ipPorProxy = Helpers::getUserIpAddr();
        $_SERVER['HTTP_CLIENT_IP'] = '198.51.100.9';
        $ipInformadoPeloCliente = Helpers::getUserIpAddr();

        $this->assertSame('10.0.0.1', $ipDireto);
        $this->assertSame('203.0.113.7', $ipPorProxy);
        $this->assertSame('198.51.100.9', $ipInformadoPeloCliente);
    }
}
