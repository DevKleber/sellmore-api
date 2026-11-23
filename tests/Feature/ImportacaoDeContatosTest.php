<?php

namespace Tests\Feature;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Tests\ApiTestCase;

class ImportacaoDeContatosTest extends ApiTestCase
{
    private const CELULAR_COM_NONO_DIGITO = '62999990001';
    private const FIXO_SEM_ALTERACAO = '6232220002';

    private function arquivoDeContatos(): UploadedFile
    {
        return new UploadedFile(__DIR__.'/../fixtures/contatos.vcf', 'contatos.vcf', 'text/vcard', null, true);
    }

    private function importarPara(int $idCliente, string $token)
    {
        return $this->enviarArquivoParaApi("/api/import-contact/customers/{$idCliente}", ['imagem' => $this->arquivoDeContatos()], $token);
    }

    public function test_importacao_cria_um_referido_por_contato_do_arquivo()
    {
        [$usuario, $token] = $this->criarUsuarioAutenticado();
        $quemIndicou = $this->criarCliente($usuario['id'], ['name' => 'Quem Indicou']);

        $resposta = $this->importarPara($quemIndicou['id'], $token);

        $resposta->assertStatus(200)->assertJson(['repetidos' => []]);
        $this->assertSame(['Joao Indicado', 'Ana Fixo'], array_column($resposta->json('res'), 'name'));
        $importados = DB::table('customers')->where('id_parent', $quemIndicou['id'])->orderBy('id')->get();
        $this->assertSame(['Joao Indicado', 'Ana Fixo'], $importados->pluck('name')->all());
        $this->assertSame(['a', 'a'], $importados->pluck('status')->all());
        $this->assertSame([$usuario['id'], $usuario['id']], $importados->pluck('id_usuario')->all());
    }

    public function test_celular_do_whatsapp_ganha_o_nono_digito_e_fica_marcado_como_whatsapp()
    {
        [$usuario, $token] = $this->criarUsuarioAutenticado();
        $quemIndicou = $this->criarCliente($usuario['id']);

        $this->importarPara($quemIndicou['id'], $token)->assertStatus(200);

        $telefone = DB::table('customers_phone')->where('phone', self::CELULAR_COM_NONO_DIGITO)->first();
        $this->assertNotNull($telefone);
        $this->assertEquals(1, $telefone->bo_whatsapp);
        $this->assertSame('55', $telefone->country_code);
        $this->assertSame('Joao Indicado', DB::table('customers')->where('id', $telefone->id_customers)->value('name'));
    }

    public function test_telefone_fixo_e_gravado_sem_nono_digito_e_sem_whatsapp()
    {
        [$usuario, $token] = $this->criarUsuarioAutenticado();
        $quemIndicou = $this->criarCliente($usuario['id']);

        $this->importarPara($quemIndicou['id'], $token)->assertStatus(200);

        $telefone = DB::table('customers_phone')->where('phone', self::FIXO_SEM_ALTERACAO)->first();
        $this->assertNotNull($telefone);
        $this->assertEquals(0, $telefone->bo_whatsapp);
    }

    public function test_contato_com_telefone_ja_cadastrado_vai_para_a_lista_de_repetidos()
    {
        [$usuario, $token] = $this->criarUsuarioAutenticado();
        $quemIndicou = $this->criarCliente($usuario['id'], ['name' => 'Quem Indicou']);
        $existente = $this->criarCliente($usuario['id'], ['name' => 'Ja Cadastrado']);
        $this->criarTelefone($existente['id'], self::CELULAR_COM_NONO_DIGITO);

        $resposta = $this->importarPara($quemIndicou['id'], $token);

        $resposta->assertStatus(200);
        $this->assertSame(['Ana Fixo'], array_column($resposta->json('res'), 'name'));
        $this->assertSame(['Joao Indicado - Número de telefone já existe (Ja Cadastrado)'], $resposta->json('repetidos'));
    }

    public function test_importar_duas_vezes_o_mesmo_arquivo_nao_duplica_contatos()
    {
        [$usuario, $token] = $this->criarUsuarioAutenticado();
        $quemIndicou = $this->criarCliente($usuario['id'], ['name' => 'Quem Indicou']);
        $this->importarPara($quemIndicou['id'], $token)->assertStatus(200);

        $resposta = $this->importarPara($quemIndicou['id'], $token);

        $this->assertSame([], $resposta->json('res'));
        $this->assertSame(
            ['Joao Indicado - Referido já indicado pelo(a) Quem Indicou', 'Ana Fixo - Referido já indicado pelo(a) Quem Indicou'],
            $resposta->json('repetidos')
        );
        $this->assertSame(2, DB::table('customers')->where('id_parent', $quemIndicou['id'])->count());
    }
}
