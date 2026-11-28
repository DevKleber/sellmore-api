<?php

namespace App\Mail;

/**
 * Consulta o DNS para evitar envio a dominio que devolveria a mensagem. Um MX vazio
 * ("0 .", RFC 7505) declara que o dominio nao recebe e-mail, como gmail.com.br.
 * Sem nenhum MX, o servidor de envio tenta o proprio dominio (RFC 5321).
 */
class VerificadorDeDominioPorDns implements VerificadorDeDominio
{
    private const DESTINOS_VAZIOS = ['', '.'];

    private $respostas = [];

    public function recebeEmail(string $dominio): bool
    {
        $dominio = strtolower($dominio);
        if (!array_key_exists($dominio, $this->respostas)) {
            $this->respostas[$dominio] = $this->consultar($dominio);
        }

        return $this->respostas[$dominio];
    }

    private function consultar(string $dominio): bool
    {
        $registrosMx = dns_get_record($dominio, DNS_MX) ?: [];
        if (empty($registrosMx)) {
            return checkdnsrr($dominio, 'A');
        }

        foreach ($registrosMx as $registro) {
            if (!in_array($registro['target'] ?? '', self::DESTINOS_VAZIOS, true)) {
                return true;
            }
        }

        return false;
    }
}
