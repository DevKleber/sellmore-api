<?php

namespace Tests\Feature;

use Illuminate\Http\UploadedFile;
use Tests\ApiTestCase;

class EnvioDeArquivoTest extends ApiTestCase
{
    private const PASTA_DE_IMAGENS = 'app/public/imagens';
    private const LADO_DA_MINIATURA_EM_PIXELS = 200;
    private const PREFIXO_DO_CAMINHO_PUBLICO = 'public/storage/imagens/';

    private $arquivosAntesDoTeste = [];

    private $pastaCriadaPeloTeste = false;

    protected function setUp(): void
    {
        parent::setUp();
        $this->garantirPastaDeImagens();
        $this->arquivosAntesDoTeste = $this->arquivosNaPasta();
    }

    protected function tearDown(): void
    {
        array_map('unlink', array_diff($this->arquivosNaPasta(), $this->arquivosAntesDoTeste));
        if ($this->pastaCriadaPeloTeste) {
            rmdir($this->pastaDeImagens());
        }
        parent::tearDown();
    }

    private function pastaDeImagens(): string
    {
        return storage_path(self::PASTA_DE_IMAGENS);
    }

    private function garantirPastaDeImagens(): void
    {
        if (is_dir($this->pastaDeImagens())) {
            return;
        }
        mkdir($this->pastaDeImagens(), 0775, true);
        $this->pastaCriadaPeloTeste = true;
    }

    private function arquivosNaPasta(): array
    {
        return glob($this->pastaDeImagens().'/*') ?: [];
    }

    public function test_envio_de_imagem_gera_miniatura_quadrada_e_devolve_o_caminho_publico()
    {
        [$usuario, $token] = $this->criarUsuarioAutenticado();
        $imagem = UploadedFile::fake()->image('foto.png', 640, 480);

        $resposta = $this->enviarArquivoParaApi('/api/file', ['imagem' => $imagem], $token);

        $resposta->assertStatus(200)->assertJson(['response' => 'Salvo com sucesso']);
        $caminho = $resposta->json('dados');
        $this->assertStringStartsWith(self::PREFIXO_DO_CAMINHO_PUBLICO, $caminho);
        $this->assertStringEndsWith("{$usuario['id']}_min.png", $caminho);
        $arquivoGravado = $this->pastaDeImagens().'/'.basename($caminho);
        $this->assertFileExists($arquivoGravado);
        [$largura, $altura] = getimagesize($arquivoGravado);
        $this->assertSame([self::LADO_DA_MINIATURA_EM_PIXELS, self::LADO_DA_MINIATURA_EM_PIXELS], [$largura, $altura]);
    }

    public function test_envio_sem_arquivo_responde_sucesso_sem_caminho()
    {
        [, $token] = $this->criarUsuarioAutenticado();

        $resposta = $this->enviarArquivoParaApi('/api/file', [], $token);

        $resposta->assertStatus(200)->assertExactJson(['response' => 'Salvo com sucesso', 'dados' => '']);
    }

    public function test_arquivo_que_nao_e_imagem_e_recusado()
    {
        [, $token] = $this->criarUsuarioAutenticado();
        $documento = UploadedFile::fake()->create('contrato.pdf', 10, 'application/pdf');

        $resposta = $this->enviarArquivoParaApi('/api/file', ['imagem' => $documento], $token);

        $resposta->assertStatus(400)->assertExactJson(['response' => 'Erro ao salvar Arquivo']);
        $this->assertSame($this->arquivosAntesDoTeste, $this->arquivosNaPasta());
    }

    public function test_envio_de_arquivo_exige_autenticacao()
    {
        $resposta = $this->enviarArquivoParaApi('/api/file', ['imagem' => UploadedFile::fake()->image('foto.png')]);

        $resposta->assertStatus(400)->assertExactJson(['error' => 'Authorization Token not found']);
    }
}
