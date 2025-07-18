document.addEventListener('DOMContentLoaded', () => {
    
    // Lógica para os botões de sumarização usando delegação de eventos
    document.body.addEventListener('click', async (event) => {
        
        const summaryBtn = event.target.closest('.oai-summary-btn');
        if (!summaryBtn) {
            return; // Not a summary button click
        }
        

        const summaryWrapper = summaryBtn.closest('.oai-summary-wrap');
        const summaryContent = summaryWrapper.querySelector('.oai-summary-content');

        // Ensure button text is set and enabled on initial click if it wasn't already
        if (!summaryBtn.textContent || summaryBtn.textContent === 'Loading...' || summaryBtn.textContent === 'Request Failed') {
            summaryBtn.textContent = 'Summarize Article';
            summaryBtn.disabled = false;
        }

        if (summaryWrapper.classList.contains('active')) {
            // Se o resumo já estiver ativo, oculte-o
            summaryWrapper.classList.remove('active');
            summaryBtn.textContent = 'Summarize Article';
            summaryContent.innerHTML = ''; // Limpa o conteúdo
            return;
        }

        // Exibir mensagem de carregamento
        summaryContent.innerHTML = '<div class="loading">Loading summary...</div>';
        summaryWrapper.classList.remove('error');
        summaryContent.classList.remove('error');
        summaryBtn.textContent = 'Loading...';
        summaryBtn.disabled = true; // Desabilita o botão enquanto carrega

        const requestUrl = summaryBtn.dataset.request; // A URL para o seu controlador PHP
        const csrfToken = document.querySelector('input[name="_csrf"]').value;

        try {
            // Primeiro, chame seu controlador PHP para obter os detalhes da requisição da API
            const response = await axios.post(requestUrl, {
                _csrf: csrfToken
            });

            // Verifica se o backend retornou um erro
            if (response.data.response.error) {
                let errorDetails = response.data.response.details || 'No details provided by the server.';
                throw new Error(errorDetails);
            }

            const provider = response.data.response.provider;
            let summary = '';

            if (provider === 'gemini') {
                summary = response.data.response.data.summary;
            } else {
                const model = response.data.response.data.model;
                const apiUrl = response.data.response.data.oai_url;
                let apiKey = response.data.response.data.oai_key;
                let requestBody = {};
                let headers = {};

                if (provider === 'openai') {
                    requestBody = {
                        model: model,
                        messages: response.data.response.data.messages,
                        max_tokens: response.data.response.data.max_tokens,
                        temperature: response.data.response.data.temperature,
                        n: response.data.response.data.n
                    };
                    headers = {
                        'Content-Type': 'application/json',
                        'Authorization': `Bearer ${apiKey}`
                    };
                } else if (provider === 'ollama') {
                    requestBody = {
                        model: model,
                        system: response.data.response.data.system,
                        prompt: response.data.response.data.prompt,
                        stream: response.data.response.data.stream,
                    };
                    headers = {
                        'Content-Type': 'application/json'
                    };
                } else {
                    summary = 'Erro: Provedor de IA desconhecido ou não configurado.';
                }

                if (!summary) {
                    const apiResponse = await axios.post(apiUrl, requestBody, { headers: headers });
                    if (provider === 'openai') {
                        summary = apiResponse.data.choices[0].message.content;
                    } else if (provider === 'ollama') {
                        summary = apiResponse.data.response || 'No summary from Ollama';
                    }
                }
            }

            summaryContent.innerHTML = marked.parse(summary);
            summaryWrapper.classList.add('active');
            summaryBtn.textContent = 'Hide Summary';
            summaryContent.classList.remove('error');
            summaryWrapper.classList.remove('error');

        } catch (apiError) {
            console.error('API Error:', apiError.response ? apiError.response.data : apiError.message);
            let errorMessage = 'Request failed. Please check your settings and API quota.';

            if (apiError.response && apiError.response.data) {
                if (apiError.response.data.message) {
                    errorMessage += ` Details: ${apiError.response.data.message}`;
                } else if (apiError.response.data.error && apiError.response.data.error.message) {
                    errorMessage += ` Details: ${apiError.response.data.error.message}`;
                } else if (typeof apiError.response.data === 'string') {
                    errorMessage += ` Details: ${apiError.response.data}`;
                }
            }

            summaryContent.textContent = errorMessage;
            summaryContent.classList.add('error');
            summaryWrapper.classList.add('error');
            summaryBtn.textContent = 'Request Failed';
        } finally {
            summaryBtn.disabled = false; // Habilita o botão novamente
        }
    });

    // Lógica para a página de configuração
    const providerSelect = document.getElementById('oai_provider');
    if (providerSelect) {
        const urlLabel = document.getElementById('url_label');
        const urlHint = document.getElementById('url_hint');
        const keyLabel = document.getElementById('key_label');
        const keyHint = document.getElementById('key_hint');
        const apiKeyInput = document.getElementById('api_key');

        const providerSettings = {
            openai: {
                urlLabel: 'Base URL',
                urlHint: "Endpoint URL for OpenAI API, e.g., https://api.openai.com/v1",
                keyLabel: 'API Key',
                keyHint: 'Your OpenAI API key.',
                keyFieldType: 'password'
            },
            ollama: {
                urlLabel: 'Ollama URL',
                urlHint: "Endpoint URL for Ollama, e.g., http://localhost:11434",
                keyLabel: 'API Key (Optional)',
                keyHint: 'API key if required by your Ollama setup.',
                keyFieldType: 'password'
            },
            gemini: {
                urlLabel: 'Gemini URL',
                urlHint: "Endpoint URL for Gemini API, e.g., https://generativelanguage.googleapis.com/v1beta/models/gemini-2.0-flash-lite:generateContent",
                keyLabel: 'API Key',
                keyHint: 'Your Gemini API key.',
                keyFieldType: 'password'
            }
        };

        function updateConfigFields() {
            const selectedProvider = providerSelect.value;
            const settings = providerSettings[selectedProvider];

            if (settings) {
                urlLabel.textContent = settings.urlLabel;
                urlHint.textContent = settings.urlHint;
                keyLabel.textContent = settings.keyLabel;
                keyHint.textContent = settings.keyHint;
                apiKeyInput.type = settings.keyFieldType;
            }
        }

        providerSelect.addEventListener('change', updateConfigFields);
        // Chama na carga da página para definir o estado inicial
        updateConfigFields();
    }
    
});