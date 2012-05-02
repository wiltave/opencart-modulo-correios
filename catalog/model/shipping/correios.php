<?php
class ModelShippingCorreios extends Model {
    // máximo valor declarado, em reais
    private $valor_max = 10000;
    // todas as medidas em cm
    private $altura_max = 105;
    private $largura_max = 105;
    private $comprimento_max = 105;
    private $altura_min = 2;
    private $largura_min = 11;
    private $comprimento_min = 16;
    // medida máxima das somas da altura, largura, comprimento
    private $soma_dim_max = 200;
    // em kg
    private $peso_max = 30;
    private $peso_min = 0.300;
    // produto com peso cúbico menor que o limite usa-se o peso da balança, senão usa-se o maior peso entre o da balança e o cúbico
    private $peso_limite = 5;
    private $nCdServico = array();
    private $url = '';
    private $quote_data = array();
    private $cep_destino;
    private $cep_origem;
    private $esedex_codigo = '';
    private $esedex_senha = '';
    private $correios = array(
        'Sedex'          => '40010',
        '40010'          => 'Sedex',
        'Sedex a Cobrar' => '40045',
        '40045'          => 'Sedex a Cobrar',
        'PAC'            => '41106',
        '41106'          => 'PAC',
        'Sedex 10'       => '40215',
        '40215'          => 'Sedex 10',
        'e-Sedex'        => '81019',
        '81019'          => 'e-Sedex',
    );

    // função responsável pelo retorno à loja dos valores finais dos valores dos fretes
    public function getQuote($address) {
        $this->load->language('shipping/correios');
        if ($this->config->get('correios_status')) {
            $query = $this->db->query("SELECT * FROM " . DB_PREFIX . "zone_to_geo_zone WHERE geo_zone_id = '" . (int)$this->config->get('correios_geo_zone_id') . "' AND country_id = '" . (int)$address['country_id'] . "' AND (zone_id = '" . (int)$address['zone_id'] . "' OR zone_id = '0')");

            if (!$this->config->get('correios_geo_zone_id')) {
                $status = TRUE;
            } elseif ($query->num_rows) {
                $status = TRUE;
            } else {
                $status = FALSE;
            }
        } else {
            $status = FALSE;
        }

        $method_data = array();

        if ($status) {
            $produtos = $this->cart->getProducts();

            // obtém só a parte numérica do CEP
            $this->cep_origem = preg_replace ('/[^\d\s]/', '', $this->config->get('correios_postcode'));
            $this->cep_destino = preg_replace ('/[^\d\s]/', '', $address['postcode']);

            // ajusta os códigos dos serviços
            if ($this->config->get('correios_' . $this->correios['PAC'])) {
                $this->nCdServico[] = $this->correios['PAC'];
            }
            if ($this->config->get('correios_' . $this->correios['Sedex'])) {
                $this->nCdServico[] = $this->correios['Sedex'];
            }
            if ($this->config->get('correios_' . $this->correios['Sedex a Cobrar'])){
                $this->nCdServico[] = $this->correios['Sedex a Cobrar'];
            }
            if($this->config->get('correios_' . $this->correios['Sedex 10'])){
                $this->nCdServico[] = $this->correios['Sedex 10'];
            }
            if($this->config->get('correios_' . $this->correios['e-Sedex']) && (trim($this->config->get('correios_esedex_codigo')) != "") && (trim($this->config->get('correios_esedex_senha')) != "")){
                $this->nCdServico[] = $this->correios['e-Sedex'];
                $this->esedex_codigo = $this->config->get('correios_esedex_codigo');
                $this->esedex_senha = $this->config->get('correios_esedex_senha');
            }

            // 'empacotando' o carrinho em caixas
            $caixas = $this->organizarEmCaixas($produtos);

            // obtém o frete de cada caixa
            foreach ($caixas as $caixa) {
                $this->setQuoteData($caixa);
            }

            // ajustes finais
            if ($this->quote_data) {
                $valor_adicional = (is_numeric($this->config->get('correios_adicional'))) ? $this->config->get('correios_adicional') : 0 ;

                foreach ($this->quote_data as $codigo => $data) {
                    // soma o valor adicional ao valor final do frete - não aplicado ao Sedex a Cobrar
                    if($codigo != $this->correios['Sedex a Cobrar']) {
                        $new_cost = $this->quote_data[$codigo]['cost'] + ($this->quote_data[$codigo]['cost'] * ($valor_adicional/100));
                        // novo custo
                        $this->quote_data[$codigo]['cost'] = $new_cost;
                        // novo texto
                        $this->quote_data[$codigo]['text'] = $this->currency->format($this->tax->calculate($new_cost, $this->config->get('correios_tax_class_id'), $this->config->get('config_tax')));
                    } else {
                        // zera o valor do frete do Sedex a Cobrar para evitar de ser adiconado ao valor do carrinho
                        $this->quote_data[$codigo]['cost'] = 0;
                    }
                }
                $method_data = array(
                    'code'         => 'correios',
                    'title'        => $this->language->get('text_title'),
                    'quote'        => $this->quote_data,
                    'sort_order'   => $this->config->get('correios_sort_order'),
                    'error'        => false,
                );
            }
        }
        return $method_data;
    }

    // obtém os dados dos fretes para os produtos da caixa
    private function setQuoteData($caixa) {
        // obtém o valor total da caixa
        $total_caixa = $this->getTotalCaixa($caixa['produtos']);
        $total_caixa = ($total_caixa > $this->valor_max) ? $this->valor_max : $total_caixa;
        list($weight, $height, $width, $length) = $this->ajustarDimensoes($caixa);

        // fazendo a chamada ao site dos Correios e obtendo os dados
        $servicos = $this->getServicos($weight, $total_caixa, $length, $width, $height);
        foreach ($servicos as $servico) {
            // o site dos Correios retornou os dados sem erros.
            $valor_frete_sem_adicionais = $servico['Valor'] - $servico['ValorAvisoRecebimento'] - $servico['ValorMaoPropria'] - $servico['ValorValorDeclarado'];
            if($servico['Erro'] == 0 && $valor_frete_sem_adicionais > 0) {
                // subtrai do valor do frete as opções desabilitadas nas configurações do módulo - 'declarar valor' é obrigatório para sedex a cobrar
                $cost = ($this->config->get('correios_declarar_valor') == 'n' && $servico['Codigo'] != $this->correios['Sedex a Cobrar']) ? ($servico['Valor'] - $servico['ValorValorDeclarado']) : $servico['Valor'];
                $cost = ($this->config->get('correios_aviso_recebimento') == 'n') ? ($cost - $servico['ValorAvisoRecebimento']) : $cost;
                $cost = ($this->config->get('correios_mao_propria') == 'n') ? ($cost - $servico['ValorMaoPropria']) : $cost;

                // o valor do frete para a caixa atual é somado ao valor total já calculado para outras caixas 
                if (isset($this->quote_data[$servico['Codigo']])) {
                    $cost += $this->quote_data[$servico['Codigo']]['cost'];
                }
                // texto a ser exibido para Sedex a Cobrar
                if($servico['Codigo'] == $this->correios['Sedex a Cobrar']) {
                    $title = sprintf($this->language->get('text_'.$servico['Codigo']), $servico['PrazoEntrega'], $this->currency->format($cost));
                    $text = $this->currency->format($this->tax->calculate($cost, $this->config->get('correios_tax_class_id'), $this->config->get('config_tax')));
                } else {
                    $title = sprintf($this->language->get('text_'.$servico['Codigo']), $servico['PrazoEntrega']);
                    $text = $this->currency->format($this->tax->calculate($cost, $this->config->get('correios_tax_class_id'), $this->config->get('config_tax')));
                }
                $this->quote_data[$servico['Codigo']] = array(
                    'code'         => 'correios.' . $servico['Codigo'],
                    'title'        => $title,
                    'cost'         => $cost,
                    'tax_class_id' => $this->config->get('correios_tax_class_id'),
                    'text'         => $text
                );
            } else {
                // grava no log de erros do OpenCart a mensagem de erro retornado pelos Correios
                $this->log->write($this->correios[$servico['Codigo']].': '.$servico['MsgErro']);
            }
        }
    }

    // prepara a url de chamada ao site dos Correios
    private function setUrl($peso, $valor, $comp, $larg, $alt) {
        $url = 'http://ws.correios.com.br/calculador/CalcPrecoPrazo.aspx?'
             . 'nCdEmpresa=' . $this->esedex_codigo
             . '&sDsSenha=' . $this->esedex_senha
             . '&sCepOrigem=%s'
             . '&sCepDestino=%s'
             . '&nVlPeso=%s'
             . '&nCdFormato=1'
             . '&nVlComprimento=%s'
             . '&nVlLargura=%s'
             . '&nVlAltura=%s'
             . '&sCdMaoPropria=s'
             . '&nVlValorDeclarado=%s'
             . '&sCdAvisoRecebimento=s'
             . '&nCdServico=' . implode(',', $this->nCdServico)
             . '&nVlDiametro=0'
             . '&StrRetorno=xml';
        $this->url = sprintf($url, $this->cep_origem, $this->cep_destino, $peso, $comp, $larg, $alt, $valor);
    }

    // conecta ao sites dos Correios e obtém o arquivo XML com os dados do frete
    private function getXML($url) {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_HEADER, 0);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);

        $result = curl_exec($ch);
        curl_close($ch);

        $result = str_replace('&amp;lt;sup&amp;gt;&amp;amp;reg;&amp;lt;/sup&amp;gt;', '', $result);
        $result = str_replace('&amp;lt;sup&amp;gt;&amp;amp;trade;&amp;lt;/sup&amp;gt;', '', $result);
        $result = str_replace('**', '', $result);
        $result = str_replace("\r\n", '', $result);
        $result = str_replace('\"', '"', $result);

        return $result;
    }

    // faz a chamada e lê os dados no arquivo XML retornado pelos Correios 
    public function getServicos($peso, $valor, $comp, $larg, $alt) {
        $dados = array();
        // troca o separador decimal de ponto para vírgula nos dados a serem enviados para os Correios
        $peso = str_replace('.', ',', $peso);
        $valor = str_replace('.', ',', $valor);
        $valor = number_format((float)$valor, 2, ',' , '.');
        $comp = str_replace('.', ',', $comp);
        $larg = str_replace('.', ',', $larg);
        $alt = str_replace('.', ',', $alt);

        // ajusta a url de chamada
        $this->setUrl($peso, $valor, $comp, $larg, $alt);

        // faz a chamada e retorna o xml com os dados
        $xml = $this->getXML($this->url);

        // lendo o xml
        if ($xml) {
            $dom = new DOMDocument('1.0', 'ISO-8859-1');
            $dom->loadXml($xml);

            $servicos = $dom->getElementsByTagName('cServico');
            if ($servicos) {
                // obtendo o prazo adicional a ser somado com o dos Correios
                $prazo_adicional = (is_numeric($this->config->get('correios_prazo_adicional'))) ? $this->config->get('correios_prazo_adicional') : 0;

                foreach ($servicos as $servico) {
                    $codigo = $servico->getElementsByTagName('Codigo')->item(0)->nodeValue;
                    // Sedex 10 não tem prazo adicional
                    $prazo = ($codigo == $this->correios['Sedex 10']) ? 0 : $prazo_adicional;

                    $dados[$codigo] = array(
                        'Codigo'                => $codigo,
                        'Valor'                 => str_replace(',', '.', $servico->getElementsByTagName('Valor')->item(0)->nodeValue),
                        'PrazoEntrega'          => ($servico->getElementsByTagName('PrazoEntrega')->item(0)->nodeValue + $prazo),
                        'Erro'                  => $servico->getElementsByTagName('Erro')->item(0)->nodeValue,
                        'MsgErro'               => $servico->getElementsByTagName('MsgErro')->item(0)->nodeValue,
                        'ValorMaoPropria'       => (isset($servico->getElementsByTagName('ValorMaoPropria')->item(0)->nodeValue)) ? str_replace(',', '.', $servico->getElementsByTagName('ValorMaoPropria')->item(0)->nodeValue) : 0,
                        "ValorAvisoRecebimento" => (isset($servico->getElementsByTagName('ValorAvisoRecebimento')->item(0)->nodeValue)) ? str_replace(',', '.', $servico->getElementsByTagName('ValorAvisoRecebimento')->item(0)->nodeValue) : 0,
                        "ValorValorDeclarado"   => (isset($servico->getElementsByTagName('ValorValorDeclarado')->item(0)->nodeValue)) ? str_replace(',', '.', $servico->getElementsByTagName('ValorValorDeclarado')->item(0)->nodeValue) : 0
                    );
                }
            }
        }
        return $dados;
    }

    // retorna a dimensão em centímetros
    private function getDimensaoEmCm($unidade, $dimensao) {
        if($unidade == 'mm' && is_numeric($dimensao)) {
            return $dimensao / 10;
        }
        return $dimensao;
    }

    // retorna o peso em quilogramas
    private function getPesoEmKg($unidade, $peso) {
        if($unidade == 'g' && is_numeric($peso)){
            return ($peso / 1000);
        }
        return $peso;
    }

    // seleciona o maior peso entre o da balança e o cúbico com base na regra dos Correios
    private function getMaiorPeso($pesoNormal, $pesoCubico) {
        if ($pesoCubico <= $this->peso_limite) {
            return $pesoNormal;
        } else {
            return ($pesoNormal >= $pesoCubico) ? $pesoNormal : $pesoCubico; 
        }
    }

    // pré-validação das dimensões e peso do produto 
    private function validar($produto) {
        if(!is_numeric($produto['height']) || !is_numeric($produto['width']) || !is_numeric($produto['length']) || !is_numeric($produto['weight'])) {
            $this->log->write(sprintf($this->language->get('error_dim'), $produto['name']));
            return false;
        }

        $altura = $produto['height'];
        $largura = $produto['width'];
        $comprimento = $produto['length'];
        $peso = $produto['weight'];

        if ($altura > $this->altura_max || $largura > $this->largura_max || $comprimento > $this->comprimento_max ) {
            $this->log->write(sprintf($this->language->get('error_dim_limite'), $this->comprimento_max, $this->largura_max, $this->altura_max, $produto['name'], $comprimento, $largura, $altura));
            return false;
        }

        $soma_dim = $altura + $largura + $comprimento;
        if ($soma_dim > $this->soma_dim_max) {
            $this->log->write(sprintf($this->language->get('error_dim_soma'), $this->soma_dim_max, $produto['name'], $soma_dim));
            return false;
        }

        if( $peso > $this->peso_max) {
            $this->log->write(sprintf($this->language->get('error_peso'), $this->peso_max, $produto['name'], $peso));
            return false;
        }

        return true;
    }

    // 'empacota' os produtos do carrinho em caixas com dimensões e peso dentro dos limites definidos pelos Correios
    // algoritmo desenvolvido por: Thalles Cardoso <thallescard@gmail.com>
    private function organizarEmCaixas($produtos) {
        $caixas = array();
        foreach ($produtos as $prod) {
            $prod_copy = $prod;
            // muda-se a quantidade do produto para incrementá-la em cada caixa
            $prod_copy['quantity'] = 1;

            $prod_copy['length_class'] = $this->getProductLengthUnit($prod_copy['length_class_id']);
            $prod_copy['weight_class'] = $this->getProductWeightUnit($prod_copy['weight_class_id']);

            // todas as dimensões da caixa serão em cm e kg
            $prod_copy['width'] = $this->getDimensaoEmCm($prod_copy['length_class'], $prod_copy['width']);
            $prod_copy['height']= $this->getDimensaoEmCm($prod_copy['length_class'], $prod_copy['height']);
            $prod_copy['length']= $this->getDimensaoEmCm($prod_copy['length_class'], $prod_copy['length']);

            // O peso do produto não é unitário como a dimensão. É multiplicado pela quantidade. Se quisermos o peso unitário, teremos que dividir pela quantidade.
            $prod_copy['weight']= $this->getPesoEmKg($prod_copy['weight_class'], $prod_copy['weight'])/$prod['quantity'];
            $prod_copy['length_class'] = 'cm';
            $prod_copy['weight_class'] = 'kg';

            $cx_num = 0;
            for ($i = 1; $i <= $prod['quantity']; $i++) {
                // valida as dimensões do produto com as dos Correios
                if ($this->validar($prod_copy)) {
                    // cria-se a caixa caso ela não exista.
                    isset($caixas[$cx_num]['weight']) ? true : $caixas[$cx_num]['weight'] = 0;
                    isset($caixas[$cx_num]['height']) ? true : $caixas[$cx_num]['height'] = 0;
                    isset($caixas[$cx_num]['width']) ? true : $caixas[$cx_num]['width'] = 0;
                    isset($caixas[$cx_num]['length']) ? true : $caixas[$cx_num]['length'] = 0;

                    $new_width     = $caixas[$cx_num]['width'] + $prod_copy['width'];
                    $new_height = $caixas[$cx_num]['height'] + $prod_copy['height'];
                    $new_length = $caixas[$cx_num]['length'] + $prod_copy['length'];
                    $new_weight = $caixas[$cx_num]['weight'] + $prod_copy['weight'];

                    $cabe_do_lado = ($new_width < $this->largura_max) && ($new_width + $caixas[$cx_num]['height'] + $caixas[$cx_num]['length'] < $this->soma_dim_max);

                    $cabe_no_fundo = ($new_length < $this->comprimento_max) && ($new_length + $caixas[$cx_num]['width'] + $caixas[$cx_num]['height'] < $this->soma_dim_max);

                    $cabe_em_cima = ($new_height < $this->altura_max) && ($new_height + $caixas[$cx_num]['width'] + $caixas[$cx_num]['length'] < $this->soma_dim_max);

                    $peso_dentro_limite = ($new_weight <= $this->peso_max) ? true : false;

                    // o produto cabe na caixa
                    if (($cabe_do_lado || $cabe_no_fundo || $cabe_em_cima) && $peso_dentro_limite) {
                        // já existe o mesmo produto na caixa, assim incrementa-se a sua quantidade
                        if (isset($caixas[$cx_num]['produtos'][$prod_copy['key']])) {
                            $caixas[$cx_num]['produtos'][$prod_copy['key']]['quantity']++;
                        } else {
                            // adiciona o novo produto
                            $caixas[$cx_num]['produtos'][$prod_copy['key']] = $prod_copy;
                        }

                        // aumenta-se o peso da caixa
                        $caixas[$cx_num]['weight'] += $prod_copy['weight'];
                        // ajusta-se as dimensões da nova caixa 
                        if ($cabe_do_lado) {
                            $caixas[$cx_num]['width'] += $prod_copy['width'];
                            // a caixa vai ficar com a altura do maior produto que estiver nela
                            $caixas[$cx_num]['height'] = max($caixas[$cx_num]['height'], $prod_copy['height']);
                            // a caixa vai ficar com o comprimento do maior produto que estiver nela
                            $caixas[$cx_num]['length'] = max($caixas[$cx_num]['length'], $prod_copy['length']);
                        } elseif ($cabe_no_fundo) {
                            $caixas[$cx_num]['length'] += $prod_copy['length'];
                            // a caixa vai ficar com a altura do maior produto que estiver nela
                            $caixas[$cx_num]['height'] = max($caixas[$cx_num]['height'], $prod_copy['height']);
                            // a caixa vai ficar com a largura do maior produto que estiver nela
                            $caixas[$cx_num]['width'] = max($caixas[$cx_num]['width'], $prod_copy['width']);
                        } elseif ($cabe_em_cima) {
                            $caixas[$cx_num]['height'] += $prod_copy['height'];
                            //a caixa vai ficar com a altura do maior produto que estiver nela
                            $caixas[$cx_num]['width'] = max($caixas[$cx_num]['width'], $prod_copy['width']);
                            //a caixa vai ficar com a largura do maior produto que estiver nela
                            $caixas[$cx_num]['length'] = max($caixas[$cx_num]['length'], $prod_copy['length']);
                        }
                    } else {
                        // tenta adicionar o produto que não coube em uma nova caixa
                        $cx_num++;
                        $i--;
                    }
                } else {
                    // produto não tem as dimensões/peso válidos então abandona sem calcular o frete. 
                    $caixas = array();
                    // sai dos dois foreach
                    break 2;
                }
            }
        }
        return $caixas;
    }

    // retorna o valor total dos prodtos na caixa
    private function getTotalCaixa($products) {
        $total = 0;
        foreach ($products as $product) {
            $total += $this->currency->format($this->tax->calculate($product['total'], $product['tax_class_id'], $this->config->get('config_tax')), '', '', false);
        }
        return $total;
    }

    private function ajustarDimensoes($caixa) {
        // a altura não pode ser maior que o comprimento, assim inverte-se as dimensões
        $height = $caixa['height'];
        $width = $caixa['width'];
        $length = $caixa['length'];
        $weight = $caixa['weight'];

        // se dimensões menores que a permitida, ajusta para o padrão
        if ( $height < $this->altura_min) {
            $height = $this->altura_min;
        }
        if ($width < $this->largura_min) {
            $width = $this->largura_min;
        }
        if ($length < $this->comprimento_min ) {
            $length = $this->comprimento_min;
        }
        if ($weight < $this->peso_min ) {
            $weight = $this->peso_min;
        }
        if( $height > $length) {
            $temp = $height;
            $height = $length;
            $length = $temp;
        }

        return array($weight, $height, $width, $length);      
    }

    private function getProductLengthUnit($id) {
        $this->getProductUnit('length', $id);
    }

    private function getProductWeightUnit($id) {
        $this->getProductUnit('weight', $id);
    }

    private function getProductUnit($type, $id) {
        $unit = '';
        $sql = "SELECT * FROM " . DB_PREFIX . $type . "_class_description "
             . "WHERE " . $type . "_class_id = '$id'";

        $query = $this->db->query($sql);
        if (isset($query->row->unit)) {
            $unit = $query->row->unit;
        }
        return $unit;
    }
}
