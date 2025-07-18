<?php
class ArticleSummaryExtension extends Minz_Extension
{


  protected array $csp_policies = [
    'default-src' => '*',
  ];

  public function init()
  {
    $this->registerHook('entry_before_display', array($this, 'addSummaryButton'));
    $this->registerController('ArticleSummary');
    Minz_View::appendStyle($this->getFileUrl('style.css', 'css'));
    Minz_View::appendScript($this->getFileUrl('axios.js', 'js'));
    Minz_View::appendScript($this->getFileUrl('marked.js', 'js'));
    Minz_View::appendScript($this->getFileUrl('script.js', 'js'));
  }

  public function addSummaryButton($entry)
  {
    $url_summary = Minz_Url::display(array(
      'c' => 'ArticleSummary',
      'a' => 'summarize',
      'params' => array(
        'id' => $entry->id()
      )
    ));

    $entry->_content(
      '<div class="oai-summary-wrap">'
      . '<button data-request="' . $url_summary . '" class="oai-summary-btn">Summarize Article</button>'
      . '<div class="oai-summary-content"></div>'
      . '</div>'
      . $entry->content()
    );
    return $entry;
  }

  public function handleConfigureAction()
  {
    if (Minz_Request::isPost()) {
        $provider = Minz_Request::param('oai_provider', 'openai');
        FreshRSS_Context::$user_conf->oai_provider = $provider;

        $url = Minz_Request::param('api_url', '');
        $key = Minz_Request::param('api_key', '');
        $model = Minz_Request::param('api_model', '');
        $prompt = Minz_Request::param('api_prompt', '');

        if ($provider === 'openai') {
            FreshRSS_Context::$user_conf->openai_url = $url;
            FreshRSS_Context::$user_conf->openai_key = $key;
            FreshRSS_Context::$user_conf->openai_model = $model;
            FreshRSS_Context::$user_conf->openai_prompt = $prompt;
        } elseif ($provider === 'ollama') {
            FreshRSS_Context::$user_conf->ollama_url = $url;
            FreshRSS_Context::$user_conf->ollama_key = $key;
            FreshRSS_Context::$user_conf->ollama_model = $model;
            FreshRSS_Context::$user_conf->ollama_prompt = $prompt;
        } elseif ($provider === 'gemini') {
            FreshRSS_Context::$user_conf->gemini_url = $url;
            FreshRSS_Context::$user_conf->gemini_key = $key;
            FreshRSS_Context::$user_conf->gemini_model = $model;
            FreshRSS_Context::$user_conf->gemini_prompt = $prompt;
        }

        FreshRSS_Context::$user_conf->save();
    }
  }
}