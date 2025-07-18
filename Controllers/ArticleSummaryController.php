<?php

class FreshExtension_ArticleSummary_Controller extends Minz_ActionController
{
    public function summarizeAction()
    {
        $this->view->_layout(false);
        header('Content-Type: application/json');

        $provider = FreshRSS_Context::$user_conf->oai_provider;
        $url = '';
        $key = '';
        $model = '';
        $prompt = '';

        if ($provider === 'openai') {
            $url = FreshRSS_Context::$user_conf->openai_url;
            $key = FreshRSS_Context::$user_conf->openai_key;
            $model = FreshRSS_Context::$user_conf->openai_model;
            $prompt = FreshRSS_Context::$user_conf->openai_prompt;
        } elseif ($provider === 'ollama') {
            $url = FreshRSS_Context::$user_conf->ollama_url;
            $key = FreshRSS_Context::$user_conf->ollama_key;
            $model = FreshRSS_Context::$user_conf->ollama_model;
            $prompt = FreshRSS_Context::$user_conf->ollama_prompt;
        } elseif ($provider === 'gemini') {
            $url = FreshRSS_Context::$user_conf->gemini_url;
            $key = FreshRSS_Context::$user_conf->gemini_key;
            $model = FreshRSS_Context::$user_conf->gemini_model;
            $prompt = FreshRSS_Context::$user_conf->gemini_prompt;
        }

        if ($this->isEmpty($url) || $this->isEmpty($model) || $this->isEmpty($prompt)) {
            echo json_encode(['response' => ['data' => 'missing config', 'error' => 'configuration'], 'status' => 400]);
            return;
        }

        $entry_id = Minz_Request::param('id');
        $entry = FreshRSS_Factory::createEntryDao()->searchById($entry_id);

        if ($entry === null) {
            echo json_encode(['status' => 404]);
            return;
        }

        $markdownContent = $this->htmlToMarkdown($entry->content());
        $successResponse = null;

        if ($provider === 'openai') {
            $processed_url = rtrim($url, '/');
            if (!preg_match('/\/v\d+\/?$/', $processed_url)) {
                $processed_url .= '/v1';
            }
            $successResponse = [
                'response' => [
                    'data' => [
                        "oai_url" => $processed_url . '/chat/completions',
                        "oai_key" => $key,
                        "model" => $model,
                        "messages" => [
                            ["role" => "system", "content" => $prompt],
                            ["role" => "user", "content" => "input: \n" . $markdownContent],
                        ],
                        "max_tokens" => 2048,
                        "temperature" => 0.7,
                        "n" => 1
                    ],
                    'provider' => 'openai',
                    'error' => null
                ],
                'status' => 200
            ];
        } elseif ($provider === 'ollama') {
            $successResponse = [
                'response' => [
                    'data' => [
                        "oai_url" => rtrim($url, '/') . '/api/generate',
                        "oai_key" => $key,
                        "model" => $model,
                        "system" => $prompt,
                        "prompt" => $markdownContent,
                        "stream" => true,
                    ],
                    'provider' => 'ollama',
                    'error' => null
                ],
                'status' => 200
            ];
        } elseif ($provider === 'gemini') {
            $geminiPayload = [
                "contents" => [
                    ["parts" => [["text" => $prompt], ["text" => "input: \n" . $markdownContent]]]
                ],
                "generationConfig" => ["temperature" => 0.7, "maxOutputTokens" => 2048]
            ];

            $requestUrl = $url . '?key=' . $key;
            $ch = curl_init($requestUrl);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => json_encode($geminiPayload)
            ]);

            $apiResponse = curl_exec($ch);
            $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curl_error = curl_error($ch);
            curl_close($ch);

            if ($apiResponse === false || $httpcode >= 400) {
                $successResponse = ['response' => ['data' => 'API call failed', 'error' => 'api_error', 'details' => $curl_error . ' - ' . $apiResponse], 'status' => $httpcode];
            } else {
                $responseData = json_decode($apiResponse, true);
                $summary = $responseData['candidates'][0]['content']['parts'][0]['text'] ?? 'No summary found.';
                $successResponse = ['response' => ['data' => ['summary' => $summary], 'provider' => 'gemini', 'error' => null], 'status' => 200];
            }
        }

        echo json_encode($successResponse);
    }

    private function isEmpty($item)
    {
        return $item === null || trim($item) === '';
    }

    private function htmlToMarkdown($content)
    {
        $dom = new DOMDocument();
        libxml_use_internal_errors(true);
        $dom->loadHTML('<?xml encoding="UTF-8">' . $content);
        libxml_clear_errors();

        $xpath = new DOMXPath($dom);
        $processNode = function ($node, $indentLevel = 0) use (&$processNode, $xpath) {
            $markdown = '';
            if ($node->nodeType === XML_TEXT_NODE) {
                $markdown .= trim($node->nodeValue);
            }
            if ($node->nodeType === XML_ELEMENT_NODE) {
                switch ($node->nodeName) {
                    case 'p': case 'div':
                        foreach ($node->childNodes as $child) $markdown .= $processNode($child);
                        $markdown .= "\n\n";
                        break;
                    case 'h1': $markdown .= "# " . $processNode($node->firstChild) . "\n\n"; break;
                    case 'h2': $markdown .= "## " . $processNode($node->firstChild) . "\n\n"; break;
                    case 'h3': $markdown .= "### " . $processNode($node->firstChild) . "\n\n"; break;
                    case 'h4': $markdown .= "#### " . $processNode($node->firstChild) . "\n\n"; break;
                    case 'h5': $markdown .= "##### " . $processNode($node->firstChild) . "\n\n"; break;
                    case 'h6': $markdown .= "###### " . $processNode($node->firstChild) . "\n\n"; break;
                    case 'a': $markdown .= "`" . $processNode($node->firstChild) . "`"; break;
                    case 'img': $markdown .= "img: `" . $node->getAttribute('alt') . "`"; break;
                    case 'strong': case 'b': $markdown .= "**" . $processNode($node->firstChild) . "**"; break;
                    case 'em': case 'i': $markdown .= "*" . $processNode($node->firstChild) . "*"; break;
                    case 'ul': case 'ol':
                        $markdown .= "\n";
                        foreach ($node->childNodes as $child) {
                            if ($child->nodeName === 'li') {
                                $markdown .= str_repeat("  ", $indentLevel) . "- " . $processNode($child, $indentLevel + 1) . "\n";
                            }
                        }
                        $markdown .= "\n";
                        break;
                    case 'li':
                        $markdown .= str_repeat("  ", $indentLevel) . "- ";
                        foreach ($node->childNodes as $child) $markdown .= $processNode($child, $indentLevel + 1);
                        $markdown .= "\n";
                        break;
                    case 'br': $markdown .= "\n"; break;
                    case 'audio': case 'video':
                        $alt = $node->getAttribute('alt');
                        $markdown .= "[" . ($alt ? $alt : 'Media') . "]";
                        break;
                    default:
                        foreach ($node->childNodes as $child) $markdown .= $processNode($child);
                        break;
                }
            }
            return $markdown;
        };

        $nodes = $xpath->query('//body/*');
        $markdown = '';
        foreach ($nodes as $node) {
            $markdown .= $processNode($node);
        }

        return preg_replace('/(\n){3,}/', "\n\n", $markdown);
    }
}