document.addEventListener('DOMContentLoaded', () => {
    // Lógica para o botão de sumarização
    const summaryBtn = document.querySelector('.oai-summary-btn');
    const summaryContent = document.querySelector('.oai-summary-content');
    const summaryWrapper = document.querySelector('.oai-summary-wrap');

    if (summaryBtn) {
        summaryBtn.textContent = 'Sumarizar Artigo'; // Texto inicial do botão

        summaryBtn.addEventListener('click', async () => {
            if (summaryWrapper.classList.contains('active')) {
                // Se o resumo já estiver ativo, oculte-o
                summaryWrapper.classList.remove('active');
                summaryBtn.textContent = 'Sumarizar Artigo';
                summaryContent.innerHTML = ''; // Limpa o conteúdo
                return;
            }

            // Exibir mensagem de carregamento
            summaryContent.innerHTML = '<div class="loading">Carregando resumo...</div>';
            summaryWrapper.classList.remove('error');
            summaryContent.classList.remove('error');
            summaryBtn.textContent = 'Carregando...';
            summaryBtn.disabled = true; // Desabilita o botão enquanto carrega

            const requestUrl = summaryBtn.dataset.request; // A URL para o seu controlador PHP

            try {
                // Primeiro, chame seu controlador PHP para obter os detalhes da requisição da API
                const response = await axios.post(requestUrl);

                const provider = response.data.response.provider;
                const model = response.data.response.data.model;
                const apiUrl = response.data.response.data.oai_url; // Esta URL já terá a chave para Gemini se for o caso
                let apiKey = response.data.response.data.oai_key; // Pode ser null para Gemini, não será usado no header
                let requestBody = {};
                let headers = {};

                // Preparar a requisição para a API do provedor (OpenAI, Ollama, Gemini)
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
                } else if (provider === 'gemini') { // Lógica para Gemini
                    requestBody = response.data.response.data.payload; // O payload completo já vem do PHP
                    headers = {
                        'Content-Type': 'application/json'
                    };
                    // A API key para Gemini já está na URL (oai_url), então não precisa de cabeçalho 'Authorization'
                } else {
                    // Tratar caso de provedor desconhecido (erro ou fallback)
                    summaryContent.textContent = 'Erro: Provedor de IA desconhecido ou não configurado.';
                    summaryContent.classList.add('error');
                    summaryWrapper.classList.add('error');
                    summaryBtn.textContent = 'Requisição Falhou';
                    summaryBtn.disabled = false;
                    return;
                }

                // Chamar a API externa (OpenAI, Ollama, Gemini)
                const apiResponse = await axios.post(apiUrl, requestBody, { headers: headers });

                let summary = '';
                if (provider === 'openai') {
                    summary = apiResponse.data.choices[0].message.content;
                } else if (provider === 'ollama') {
                    // Para Ollama, se for um stream, você pode precisar concatenar as respostas
                    // Exemplo básico:
                    summary = apiResponse.data.response || 'No summary from Ollama';
                    // Para lidar com streamings mais complexos, o ideal seria usar um ResponseTransformer ou outra lógica
                } else if (provider === 'gemini') { // Processa a resposta do Gemini
                    summary = apiResponse.data.candidates[0].content.parts[0].text;
                } else {
                    summary = 'Erro: Não foi possível processar a resposta do provedor desconhecido.';
                }

                summaryContent.innerHTML = marked.parse(summary);
                summaryWrapper.classList.add('active');
                summaryBtn.textContent = 'Ocultar Resumo';
                summaryContent.classList.remove('error');
                summaryWrapper.classList.remove('error');

            } catch (apiError) {
                console.error('API Error:', apiError.response ? apiError.response.data : apiError.message);
                let errorMessage = 'Requisição falhou. Verifique as configurações e sua cota de API.';

                if (apiError.response && apiError.response.data) {
                    if (apiError.response.data.message) {
                        errorMessage += ` Detalhes: ${apiError.response.data.message}`;
                    } else if (apiError.response.data.error && apiError.response.data.error.message) {
                        errorMessage += ` Detalhes: ${apiError.response.data.error.message}`;
                    } else if (typeof apiError.response.data === 'string') {
                        errorMessage += ` Detalhes: ${apiError.response.data}`;
                    }
                }

                summaryContent.textContent = errorMessage;
                summaryContent.classList.add('error');
                summaryWrapper.classList.add('error');
                summaryBtn.textContent = 'Requisição Falhou';
            } finally {
                summaryBtn.disabled = false; // Habilita o botão novamente
            }
        });
    }

    // Lógica para mostrar/esconder campos de configuração na página da extensão
    // Esta parte é executada na página de configuração (configure.phtml)
    const providerSelect = document.querySelector('select[name="oai_provider"]');
    const commonConfigDiv = document.getElementById('common_config');
    const geminiConfigDiv = document.getElementById('gemini_config');

    function toggleConfigFields() {
        const selectedProvider = providerSelect.value;

        // Oculta tudo por padrão
        if (commonConfigDiv) commonConfigDiv.style.display = 'none';
        if (geminiConfigDiv) geminiConfigDiv.style.display = 'none';

        // Mostra o bloco de configuração relevante
        if (selectedProvider === 'openai' || selectedProvider === 'ollama') {
            if (commonConfigDiv) commonConfigDiv.style.display = 'block';
        } else if (selectedProvider === 'gemini') {
            if (geminiConfigDiv) geminiConfigDiv.style.display = 'block';
        }
    }

    if (providerSelect) {
        providerSelect.addEventListener('change', toggleConfigFields);
        toggleConfigFields(); // Chama na carga da página para definir o estado inicial
    }
});
