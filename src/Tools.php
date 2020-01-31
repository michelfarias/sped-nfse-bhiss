<?php

namespace NFePHP\NFSeBHISS;

/**
 * Class for comunications with NFSe webserver in Nacional Standard
 *
 * @category  NFePHP
 * @package   NFePHP\NFSeBHISS
 * @copyright NFePHP Copyright (c) 2008-2018
 * @license   http://www.gnu.org/licenses/lgpl.txt LGPLv3+
 * @license   https://opensource.org/licenses/MIT MIT
 * @license   http://www.gnu.org/licenses/gpl.txt GPLv3+
 * @author    Roberto L. Machado <linux.rlm at gmail dot com>
 * @link      http://github.com/nfephp-org/sped-nfse-bhiss for the canonical source repository
 */

use NFePHP\Common\Certificate;
use NFePHP\Common\Validator;
use NFePHP\NFSeBHISS\Common\Signer;
use NFePHP\NFSeBHISS\Common\Tools as BaseTools;
use NFePHP\NFSeBHISS\RpsInterface;

class Tools extends BaseTools
{
    const CANCEL_ERRO_EMISSAO = 1;
    const CANCEL_SERVICO_NAO_CONCLUIDO = 2;
    const CANCEL_DUPLICIDADE = 4;
    
    protected $xsdpath;
    
    public function __construct($config, Certificate $cert)
    {
        parent::__construct($config, $cert);
        $path = realpath(
            __DIR__ . '/../storage/schemes'
        );
        $this->xsdpath = $path . '/nfse_v20_08_2015.xsd';
    }
    
    /**
     * Solicita o cancelamento de NFSe (SINCRONO)
     * @param string $id
     * @param integer $numero
     * @param integer $codigo
     * @return string
     */
    public function cancelarNfse($id, $numero, $codigo = self::CANCEL_ERRO_EMISSAO)
    {
        $operation = 'CancelarNfse';
        $pedido = "<CancelarNfseEnvio xmlns=\"{$this->wsobj->msgns}\">"
            . "<Pedido>"
            . "<InfPedidoCancelamento Id=\"$id\">"
            . "<IdentificacaoNfse>"
            . "<Numero>$numero</Numero>"
            . "<Cnpj>{$this->config->cnpj}</Cnpj>"
            . "<InscricaoMunicipal>{$this->config->im}</InscricaoMunicipal>"
            . "<CodigoMunicipio>{$this->config->cmun}</CodigoMunicipio>"
            . "</IdentificacaoNfse>"
            . "<CodigoCancelamento>$codigo</CodigoCancelamento>"
            . "</InfPedidoCancelamento>"
            . "</Pedido>"
            . "</CancelarNfseEnvio>";
        $content = Signer::sign(
            $this->certificate,
            $pedido,
            'InfPedidoCancelamento',
            'Id',
            OPENSSL_ALGO_SHA1,
            [true,false,null,null],
            'Pedido'
        );
        $content = str_replace(
            ['<?xml version="1.0"?>', '<?xml version="1.0" encoding="UTF-8"?>'],
            '',
            $content
        );
        Validator::isValid($content, $this->xsdpath);
        return $this->send($content, $operation);
    }
    
    /**
     * Consulta Lote RPS (SINCRONO) após envio com recepcionarLoteRps() (ASSINCRONO)
     * complemento do processo de envio assincono.
     * Que deve ser usado quando temos mais de um RPS sendo enviado
     * por vez.
     * @param string $protocolo
     * @return string
     */
    public function consultarLoteRps($protocolo)
    {
        $operation = 'ConsultarLoteRps';
        $content = "<ConsultarLoteRpsEnvio xmlns=\"{$this->wsobj->msgns}\">"
            . $this->prestador
            . "<Protocolo>$protocolo</Protocolo>"
            . "</ConsultarLoteRpsEnvio>";
        Validator::isValid($content, $this->xsdpath);
        return $this->send($content, $operation);
    }
    
    /**
     * Consulta NFSe emitidas em um periodo e por tomador (SINCRONO)
     * @param string $dini
     * @param string $dfim
     * @param string $tomadorCnpj
     * @param string $tomadorCpf
     * @param string $tomadorIM
     * @return string
     */
    public function consultarNfse($dini, $dfim, $tomadorCnpj = null, $tomadorCpf = null, $tomadorIM = null)
    {
        $operation = 'ConsultarNfse';
        $content = "<ConsultarNfseEnvio xmlns=\"{$this->wsobj->msgns}\">"
            . $this->prestador
            . "<PeriodoEmissao>"
            . "<DataInicial>$dini</DataInicial>"
            . "<DataFinal>$dfim</DataFinal>"
            . "</PeriodoEmissao>";
            
        if ($tomadorCnpj || $tomadorCpf) {
            $content .= "<Tomador>"
            . "<CpfCnpj>";
            if (isset($tomadorCnpj)) {
                $content .= "<Cnpj>$tomadorCnpj</Cnpj>";
            } else {
                $content .= "<Cpf>$tomadorCpf</Cpf>";
            }
            $content .= "</CpfCnpj>";
            if (isset($tomadorIM)) {
                $content .= "<InscricaoMunicipal>$tomadorIM</InscricaoMunicipal>";
            }
            $content .= "</Tomador>";
        }
        $content .= "</ConsultarNfseEnvio>";
        Validator::isValid($content, $this->xsdpath);
        return $this->send($content, $operation);
    }
    
    /**
     * Consulta NFSe emitidas por faixa de numeros (SINCRONO)
     * @param integer $nini
     * @param integer $nfim
     * @param integer $pagina
     * @return string
     */
    public function consultarNfsePorFaixa($nini, $nfim, $pagina = 1)
    {
        $operation = 'ConsultarNfsePorFaixa';
        $content = "<ConsultarNfseFaixaEnvio xmlns=\"{$this->wsobj->msgns}\">"
            . $this->prestador
            . "<Faixa>"
            . "<NumeroNfseInicial>$nini</NumeroNfseInicial>"
            . "<NumeroNfseFinal>$nfim</NumeroNfseFinal>"
            . "</Faixa>"
            . "<Pagina>$pagina</Pagina>"
            . "</ConsultarNfseFaixaEnvio>";
        Validator::isValid($content, $this->xsdpath);
        return $this->send($content, $operation);
    }
    
    /**
     * Consulta NFSe por RPS (SINCRONO)
     * @param integer $numero
     * @param string $serie
     * @param integer $tipo
     * @return string
     */
    public function consultarNfsePorRps($numero, $serie, $tipo)
    {
        $operation = "ConsultarNfsePorRps";
        $content = "<ConsultarNfseRpsEnvio xmlns=\"{$this->wsobj->msgns}\">"
            . "<IdentificacaoRps>"
            . "<Numero>$numero</Numero>"
            . "<Serie>$serie</Serie>"
            . "<Tipo>$tipo</Tipo>"
            . "</IdentificacaoRps>"
            . $this->prestador
            . "</ConsultarNfseRpsEnvio>";
        Validator::isValid($content, $this->xsdpath);
        return $this->send($content, $operation);
    }
    
    /**
     * Envia LOTE de RPS para emissão de NFSe (ASSINCRONO)
     * @param array $arps Array contendo de 1 a 50 RPS::class
     * @param string $lote Número do lote de envio
     * @return string
     * @throws \Exception
     */
    public function recepcionarLoteRps($arps, $lote)
    {
        $operation = 'RecepcionarLoteRps';
        $no_of_rps_in_lot = count($arps);
        if ($no_of_rps_in_lot > 50) {
            throw new \Exception('O limite é de 50 RPS por lote enviado.');
        }
        $content = '';
        foreach ($arps as $rps) {
            $rps->config($this->config);
            $xmlsigned = $this->sign($rps->render(), 'InfRps', 'Id');
            $content .= $xmlsigned;
        }
        $contentmsg = "<EnviarLoteRpsEnvio xmlns=\"{$this->wsobj->msgns}\">"
            . "<LoteRps Id=\"$lote\" versao=\"{$this->wsobj->version}\">"
            . "<NumeroLote>$lote</NumeroLote>"
            . "<Cnpj>" . $this->config->cnpj . "</Cnpj>"
            . "<InscricaoMunicipal>" . $this->config->im . "</InscricaoMunicipal>"
            . "<QuantidadeRps>$no_of_rps_in_lot</QuantidadeRps>"
            . "<ListaRps>"
            . $content
            . "</ListaRps>"
            . "</LoteRps>"
            . "</EnviarLoteRpsEnvio>";
        
        $content = $this->sign($contentmsg, 'LoteRps', 'Id');
        Validator::isValid($content, $this->xsdpath);
        return $this->send($content, $operation);
    }
    
    /**
     * Solicita a emissão de uma NFSe de forma SINCRONA
     * @param RpsInterface $rps
     * @param string $lote Identificação do lote
     * @return string
     */
    public function gerarNfse(RpsInterface $rps, $lote)
    {
        $operation = "GerarNfse";
        
        $xmlsigned = $this->putPrestadorInRps($rps);
        $xmlsigned = $this->sign($xmlsigned, 'InfRps', 'Id');
        
        $contentmsg = "<GerarNfseEnvio xmlns=\"{$this->wsobj->msgns}\">"
            . "<LoteRps Id=\"Lote$lote\" versao=\"{$this->wsobj->version}\">"
            . "<NumeroLote>$lote</NumeroLote>"
            . "<Cnpj>" . $this->config->cnpj . "</Cnpj>"
            . "<InscricaoMunicipal>" . $this->config->im . "</InscricaoMunicipal>"
            . "<QuantidadeRps>1</QuantidadeRps>"
            . "<ListaRps>"
            . $xmlsigned
            . "</ListaRps>"
            . "</LoteRps>"
            . "</GerarNfseEnvio>";
        $content = $this->sign($contentmsg, 'LoteRps', 'Id');
        Validator::isValid($content, $this->xsdpath);
        return $this->send($content, $operation);
    }
}
