# FreshRSS Article Summary Extension

This version was improved by simplifiying the LLM configuration forms, fix issues, simplify parts of the code, translated to english and improved dark mode.

This FreshRSS improved extension enables users to effortlessly generate concise summaries of articles by leveraging a large language model (LLM) API compatible with the OpenAI API specification.

#### Key Features

- **Seamless Integration:** Easily configure the API endpoint, API key, chosen model, and a custom prompt directly within a user-friendly interface.

- **Convenient Summarization:** A "Summarize" button is added to each article, allowing users to quickly send content to the configured LLM API for automated summarization.

- **Customizable Experience:** Tailor the summarization process by setting prompts or choosing specific models to fit your preferred summary style or API provider.

With this extension, FreshRSS users can streamline their reading workflow by generating article summaries on demand, directly from the feed interface.

## Installation

1. **Download the Extension**: Clone or download this repository to your FreshRSS extensions directory.
2. **Enable the Extension**: Go to the FreshRSS extensions management page and enable the "ArticleSummary" extension.
3. **Configure the Extension**: Navigate to the extension's configuration page to set up your API details.

## Configuration for Gemini Free Tier

**Choose AI Provier:** Gemini

**URL:** https://generativelanguage.googleapis.com/v1beta/models/gemini-2.0-flash-lite:generateContent

**API Key:** [Google AIStudio](https://aistudio.google.com/apikey)

**Model Name:** gemini-2.0-flash-lite

**Prompt:** 
You are an expert summarizer. Create a concise summary of the following text in paragraph format only. Write in clear, accessible language. STRICT LIMIT: Maximum 200 words. Include only information from the original text. Do not use bullet points, numbered lists, headers, labels, or any formatting. Do not include explanations, guidelines, or instructions in your response. Write only the summary as continuous prose. Keep it brief and focused on the most essential points. Text:

Done!

Feel free to change the url, model name and prompt to your needs.

## Usage

Once configured and enabled, the extension will automatically add a "Summarize" button to each article. Clicking this button will:

1. Send the article content to the configured API.
2. Display the generated summary below the button.

## Dependencies

- **Axios**: Used for making HTTP requests from the browser.
- **Marked**: Converts Markdown content to HTML for display.

## Contributing

Contributions are welcome! Please feel free to submit issues or pull requests.

## License

This project is licensed under the MIT License. See the [LICENSE](LICENSE) file for details.

## Acknowledgments

- Thanks to the FreshRSS community for providing a robust platform for RSS management.
- Inspired by the need for efficient article summarization tools.
- This is the original creator of this extension: [LiangWei88](https://github.com/LiangWei88/xExtension-ArticleSummary)
- This is the original developer that initially implemented Gemini: [Davidalben](https://github.com/davidalben/xExtension-ArticleSummary)

---

For any questions or support, please open an issue on this repository.