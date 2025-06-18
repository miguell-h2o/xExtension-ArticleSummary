<?php

class FreshExtension_ArticleSummary_Controller extends Minz_ActionController
{
    public function summarizeAction()
    {
        $this->view->_layout(false);
        // Define o cabeçalho de resposta como JSON
        header('Content-Type: application/json');

        // Carrega configurações da OpenAI/Ollama
        $oai_url = FreshRSS_Context::$user_conf->oai_url;
        $oai_key = FreshRSS_Context::$user_conf->oai_key;
        $oai_model = FreshRSS_Context::$user_conf->oai_model;
        $oai_prompt = FreshRSS_Context::$user_conf->oai_prompt;
        $oai_provider = FreshRSS_Context::$user_conf->oai_provider;

        // Carrega configurações do Gemini
        $gemini_url = FreshRSS_Context::$user_conf->gemini_url;
        $gemini_key = FreshRSS_Context::$user_conf->gemini_key;
        $gemini_model = FreshRSS_Context::$user_conf->gemini_model;
        $gemini_prompt = FreshRSS_Context::$user_conf->gemini_prompt;

        // Determina o provedor selecionado
        $provider = $oai_provider; // Usamos oai_provider como o campo de seleção geral

        $config_missing = false;
        if ($provider === 'openai') {
            if ($this->isEmpty($oai_url) || $this->isEmpty($oai_key) || $this->isEmpty($oai_model) || $this->isEmpty($oai_prompt)) {
                $config_missing = true;
            }
        } elseif ($provider === 'ollama') {
            // Adicione validações específicas para Ollama aqui se necessário
            // Por enquanto, apenas verifica a URL e modelo para Ollama, pode precisar de mais
            if ($this->isEmpty($oai_url) || $this->isEmpty($oai_model)) {
                $config_missing = true;
            }
        } elseif ($provider === 'gemini') {
            if ($this->isEmpty($gemini_url) || $this->isEmpty($gemini_key) || $this->isEmpty($gemini_model) || $this->isEmpty($gemini_prompt)) {
                $config_missing = true;
            }
        } else {
            // Provedor desconhecido ou não selecionado
            $config_missing = true;
        }

        if ($config_missing) {
            echo json_encode(array(
                'response' => array(
                    'data' => 'missing config',
                    'error' => 'configuration'
                ),
                'status' => 200 // Considere mudar para 400 Bad Request em um ambiente real
            ));
            return;
        }

        $entry_id = Minz_Request::param('id');
        $entry_dao = FreshRSS_Factory::createEntryDao();
        $entry = $entry_dao->searchById($entry_id);

        if ($entry === null) {
            echo json_encode(array('status' => 404));
            return;
        }

        $content = $entry->content(); // Conteúdo do artigo
        $markdownContent = $this->htmlToMarkdown($content); // Converte o HTML para Markdown

        $successResponse = null; // Inicializa a variável de resposta

        // Lógica para OpenAI
        if ($provider === "openai") {
            // Processa $oai_url para OpenAI
            $oai_url_processed = rtrim($oai_url, '/');
            if (!preg_match('/\/v\d+\/?$/', $oai_url_processed)) {
                $oai_url_processed .= '/v1'; // Se não houver informação de versão, adiciona /v1
            }

            $successResponse = array(
                'response' => array(
                    'data' => array(
                        "oai_url" => $oai_url_processed . '/chat/completions',
                        "oai_key" => $oai_key,
                        "model" => $oai_model,
                        "messages" => [
                            [
                                "role" => "system",
                                "content" => $oai_prompt
                            ],
                            [
                                "role" => "user",
                                "content" => "input: \n" . $markdownContent,
                            ]
                        ],
                        "max_tokens" => 2048,
                        "temperature" => 0.7,
                        "n" => 1
                    ),
                    'provider' => 'openai',
                    'error' => null
                ),
                'status' => 200
            );
        }
        // Lógica para Ollama
        elseif ($provider === "ollama") {
            $successResponse = array(
                'response' => array(
                    'data' => array(
                        "oai_url" => rtrim($oai_url, '/') . '/api/generate',
                        "oai_key" => $oai_key, // Ollama geralmente não usa chave de API no cabeçalho assim, mas mantido para consistência
                        "model" => $oai_model,
                        "system" => $oai_prompt,
                        "prompt" => $markdownContent,
                        "stream" => true,
                    ),
                    'provider' => 'ollama',
                    'error' => null
                ),
                'status' => 200
            );
        }
        // Lógica para Gemini
        elseif ($provider === "gemini") {
            $geminiPayload = [
                "contents" => [
                    [
                        "parts" => [
                            ["text" => $gemini_prompt],
                            ["text" => "input: \n" . $markdownContent]
                        ]
                    ]
                ],
                "generationConfig" => [
                    "temperature" => 0.7,
                    "maxOutputTokens" => 2048
                ]
            ];

            $successResponse = array(
                'response' => array(
                    'data' => array(
                        "oai_url" => $gemini_url . '?key=' . $gemini_key, // URL do Gemini com a chave de API
                        "model" => $gemini_model,
                        "payload" => $geminiPayload // O corpo da requisição JSON para o Gemini
                    ),
                    'provider' => 'gemini',
                    'error' => null
                ),
                'status' => 200
            );
        }

        echo json_encode($successResponse);
        return;
    }

    private function isEmpty($item)
    {
        return $item === null || trim($item) === '';
    }

    private function htmlToMarkdown($content)
    {
        // Cria objeto DOMDocument
        $dom = new DOMDocument();
        libxml_use_internal_errors(true); // Ignora erros de parsing HTML
        $dom->loadHTML('<?xml encoding="UTF-8">' . $content);
        libxml_clear_errors(); // Limpa erros de libxml após o loadHTML

        // Cria objeto XPath
        $xpath = new DOMXPath($dom);

        // Define uma função anônima para processar o nó
        $processNode = function ($node, $indentLevel = 0) use (&$processNode, $xpath) {
            $markdown = '';

            // Processa nós de texto
            if ($node->nodeType === XML_TEXT_NODE) {
                $markdown .= trim($node->nodeValue);
            }

            // Processa nós de elemento
            if ($node->nodeType === XML_ELEMENT_NODE) {
                switch ($node->nodeName) {
                    case 'p':
                    case 'div':
                        foreach ($node->childNodes as $child) {
                            $markdown .= $processNode($child);
                        }
                        $markdown .= "\n\n";
                        break;
                    case 'h1':
                        $markdown .= "# ";
                        $markdown .= $processNode($node->firstChild);
                        $markdown .= "\n\n";
                        break;
                    case 'h2':
                        $markdown .= "## ";
                        $markdown .= $processNode($node->firstChild);
                        $markdown .= "\n\n";
                        break;
                    case 'h3':
                        $markdown .= "### ";
                        $markdown .= $processNode($node->firstChild);
                        $markdown .= "\n\n";
                        break;
                    case 'h4':
                        $markdown .= "#### ";
                        $markdown .= $processNode($node->firstChild);
                        $markdown .= "\n\n";
                        break;
                    case 'h5':
                        $markdown .= "##### ";
                        $markdown .= $processNode($node->firstChild);
                        $markdown .= "\n\n";
                        break;
                    case 'h6':
                        $markdown .= "###### ";
                        $markdown .= $processNode($node->firstChild);
                        $markdown .= "\n\n";
                        break;
                    case 'a':
                        $markdown .= "`"; // Usando backticks para links, como no original
                        $markdown .= $processNode($node->firstChild);
                        $markdown .= "`";
                        break;
                    case 'img':
                        $alt = $node->getAttribute('alt');
                        $markdown .= "img: `" . $alt . "`";
                        break;
                    case 'strong':
                    case 'b':
                        $markdown .= "**";
                        $markdown .= $processNode($node->firstChild);
                        $markdown .= "**";
                        break;
                    case 'em':
                    case 'i':
                        $markdown .= "*";
                        $markdown .= $processNode($node->firstChild);
                        $markdown .= "*";
                        break;
                    case 'ul':
                    case 'ol':
                        $markdown .= "\n";
                        foreach ($node->childNodes as $child) {
                            if ($child->nodeName === 'li') {
                                $markdown .= str_repeat("  ", $indentLevel) . "- ";
                                $markdown .= $processNode($child, $indentLevel + 1);
                                $markdown .= "\n";
                            }
                        }
                        $markdown .= "\n";
                        break;
                    case 'li':
                        $markdown .= str_repeat("  ", $indentLevel) . "- ";
                        foreach ($node->childNodes as $child) {
                            $markdown .= $processNode($child, $indentLevel + 1);
                        }
                        $markdown .= "\n";
                        break;
                    case 'br':
                        $markdown .= "\n";
                        break;
                    case 'audio':
                    case 'video':
                        $alt = $node->getAttribute('alt');
                        $markdown .= "[" . ($alt ? $alt : 'Media') . "]";
                        break;
                    default:
                        // Tags não consideradas, apenas o conteúdo de texto interno é mantido
                        foreach ($node->childNodes as $child) {
                            $markdown .= $processNode($child);
                        }
                        break;
                }
            }

            return $markdown;
        };

        // Obtém todos os nós
        $nodes = $xpath->query('//body/*');

        // Processa todos os nós
        $markdown = '';
        foreach ($nodes as $node) {
            $markdown .= $processNode($node);
        }

        // Remove quebras de linha extras
        $markdown = preg_replace('/(\n){3,}/', "\n\n", $markdown);

        return $markdown;
    }
}
