=== AIutoma – The AIO Autonomous AI Assistant ===
Contributors: frapesce
Tags: ai, agent, free tier, mcp, gutenberg
Requires at least: 7.0
Tested up to: 7.1
Stable tag: 1.0.2
Requires PHP: 8.1
License: GPLv3 or later
License URI: https://www.gnu.org/licenses/gpl-3.0.html

Aiutoma is an agentic AI assistant designed to seamlessly integrate into your WordPress and WooCommerce ecosystem.

== Description ==

Welcome to **Aiutoma** — an enterprise-grade AI co-pilot that transforms your WordPress website into an autonomous, intelligent powerhouse.

Built natively on top of the WordPress 7.0 AI API, Aiutoma brings true agentic automation directly to your WordPress and WooCommerce dashboard.

### ✨ Key Features

* **AI Playground & Automation Hub**: Centralized command center. Write content, manage settings, or schedule recurring AI tasks with instant 1-click rollbacks.
* **Smart Frontend Chatbot (EU AI Act Ready)**: Conversational assistant trained on your site content. Fully compliant with EU AI Act (Art. 50) transparency requirements with AI badges, accuracy disclaimers, and operator takeover.
* **Gutenberg Editor Agent**: Co-writer living inside the block editor. Generate layouts, rewrite copy, or create featured images on the fly.
* **Omnichannel IM (WhatsApp & Telegram)**: Deploy AI conversational agents to provide instant support and trigger site tasks directly from mobile.
* **AI Skills & Custom Personas**: Fine-tune the AI's behavior and business logic to match your brand.
* **Bring Your Own Model (Free Tiers Included)**: Seamlessly connects to OpenAI, Anthropic Claude, Google Gemini, Hugging Face, Mistral, Groq, Cohere, AWS Bedrock, or custom local endpoints.
* **Token Usage & Budget Management**: Visual budget monitoring and threshold alerts keep AI infrastructure costs predictable.
* **WPML Multi-Language Auto-Translation**: Auto-translates content and strings across multiple languages.
* **RAG Knowledge Base**: Indexes posts, WooCommerce products, and documentation into a local vector store for hyper-accurate context.
* **Remote Control (MCP & OpenAPI)**: Connect external AI clients like Claude Desktop or Custom GPTs via Model Context Protocol (MCP).
* **AI-Ready SEO**: Generates clean Markdown pages and `/llms.txt` for AI search engines.
* **Automated Dependency Management**: One-click installation and updates of optional external libraries directly from official sources.

== AI & Data Privacy Disclosures ==

To function properly, Aiutoma sends specific data to the third-party AI provider(s) you configure (such as OpenAI, Anthropic, Google Gemini, or custom endpoints). 
*   **Data Transmitted:** When you use the Playground, Chatbot, or Editor Agent, the text of your prompts, selected post content, and relevant system context (like basic site configurations or schema) are sent to the AI provider to generate a response.
*   **User Consent:** No data is transmitted passively. Data is only sent when an explicit action is taken (e.g., clicking "Send" in the chat, running a translation, or executing a task).
*   **Data Protection:** We do not harvest, store, or sell your data. Aiutoma communicates directly between your WordPress server and the AI API. Please refer to the privacy policy of your chosen AI provider (e.g., [OpenAI Privacy Policy](https://openai.com/policies/privacy-policy)) to understand how they handle the transmitted data.

== EU AI Act (Regulation (EU) 2024/1689) Compliance ==

Aiutoma is engineered to keep website owners fully compliant as AI deployers under European Union regulations:
*   **Limited Risk Profile:** Aiutoma strictly operates within the Limited Risk category. It does not engage in prohibited practices (Art. 5) or high-risk classification tasks (Art. 6 / Annex III).
*   **Article 50 Transparency Obligations:** The Frontend Chatbot ensures natural persons are clearly informed they are interacting with an AI system through visible header badges, explicit AI greetings, and persistent accuracy disclaimers.
*   **Human-in-the-Loop Handover:** Provides clear real-time system notices when a human operator joins or leaves a conversation.
*   **Human Editorial Oversight:** Content generated in the Gutenberg editor and Playground requires explicit human review and publication (Art. 50(4)).

== External services ==

This plugin connects to external third-party services to provide artificial intelligence completions, embeddings generation, anti-bot verification, image creation, content translation, and instant messaging functionality. All external connections are made directly from your WordPress server to the respective service endpoints. No personal data is sent without explicit user or administrator action.

= Cohere =
* **Service Description & Purpose:** This plugin connects to the Cohere API (`api.cohere.ai`, `dashboard.cohere.com`) to generate text completions, chat responses, and embeddings when configured by the site administrator as an AI provider.
* **What Data is Sent and When:** User prompt text, chat history, and model configuration parameters are sent only when an administrator or visitor initiates an AI query using a Cohere model.
* **Service Provider:** Cohere Inc.
* **Terms of Service:** [terms of use](https://cohere.com/terms-of-use)
* **Privacy Policy:** [privacy policy](https://cohere.com/privacy)

= Groq =
* **Service Description & Purpose:** This plugin connects to the GroqCloud API (`api.groq.com`, `console.groq.com`) to provide high-speed Large Language Model (LLM) inference when configured by the site administrator with a Groq API key.
* **What Data is Sent and When:** Prompt text, system instructions, and chat messages are sent only when an AI query is executed using a Groq model.
* **Service Provider:** Groq, Inc.
* **Terms of Service:** [terms of use](https://groq.com/terms-of-use/)
* **Privacy Policy:** [privacy policy](https://groq.com/privacy-policy/)

= Hugging Face =
* **Service Description & Purpose:** This plugin connects to the Hugging Face Inference API and Model Router (`router.huggingface.co`, `api-inference.huggingface.co`, `huggingface.co`) to execute open-source AI models and calculate sentence embeddings (feature extraction) for the RAG knowledge base when configured by the site administrator.
* **What Data is Sent and When:** User prompts and post text excerpts are sent only when calculating embeddings or requesting text inference from a configured Hugging Face model.
* **Service Provider:** Hugging Face, Inc.
* **Terms of Service:** [terms of service](https://huggingface.co/terms-of-service)
* **Privacy Policy:** [privacy policy](https://huggingface.co/privacy)

= Cloudflare Workers AI =
* **Service Description & Purpose:** This plugin connects to the Cloudflare Workers AI API (`api.cloudflare.com`, `dash.cloudflare.com`) to execute serverless AI models on Cloudflare's edge network when configured with a Cloudflare Account ID and API Token.
* **What Data is Sent and When:** Prompt text and chat messages are sent only when an AI query is dispatched to a configured Cloudflare AI model.
* **Service Provider:** Cloudflare, Inc.
* **Terms of Service:** [terms of use](https://www.cloudflare.com/website-terms/)
* **Privacy Policy:** [privacy policy](https://www.cloudflare.com/privacypolicy/)

= Cloudflare Turnstile =
* **Service Description & Purpose:** This plugin connects to Cloudflare Turnstile (`challenges.cloudflare.com`) to provide bot protection and CAPTCHA verification for the frontend chatbot.
* **What Data is Sent and When:** The Turnstile verification token and the visitor's IP address are sent to Cloudflare's verification endpoint only when a visitor submits a message through a chatbot that has Turnstile protection enabled.
* **Service Provider:** Cloudflare, Inc.
* **Terms of Service:** [terms of use](https://www.cloudflare.com/website-terms/)
* **Privacy Policy:** [privacy policy](https://www.cloudflare.com/privacypolicy/)

= Mistral AI =
* **Service Description & Purpose:** This plugin connects to the Mistral AI API (`api.mistral.ai`, `console.mistral.ai`) to generate text and chat completions when configured by the administrator with a Mistral API key.
* **What Data is Sent and When:** User prompts and conversation context are sent only when an AI query is directed to a Mistral AI model.
* **Service Provider:** Mistral AI SAS
* **Terms of Service:** [terms of service](https://legal.mistral.ai/terms/get-started/)
* **Privacy Policy:** [privacy policy](https://legal.mistral.ai/terms/privacy-policy/)

= OpenRouter =
* **Service Description & Purpose:** This plugin connects to the OpenRouter API (`openrouter.ai`) as an AI gateway to route requests across diverse AI models when configured by the administrator with an OpenRouter API key.
* **What Data is Sent and When:** Prompt text, conversation history, and requested model parameters are sent only when an AI query is submitted through OpenRouter.
* **Service Provider:** OpenRouter
* **Terms of Service:** [terms of service](https://openrouter.ai/terms)
* **Privacy Policy:** [privacy policy](https://openrouter.ai/privacy)

= Amazon Web Services (AWS Bedrock) =
* **Service Description & Purpose:** This plugin connects to the Amazon Bedrock Runtime API (`amazonaws.com`, `bedrock-runtime`, `console.aws.amazon.com`) to execute foundation models via the Converse API when configured with AWS IAM credentials.
* **What Data is Sent and When:** Conversation messages and inference parameters are sent to the administrator's designated AWS region only when querying an AWS Bedrock model.
* **Service Provider:** Amazon Web Services, Inc.
* **Terms of Service:** [terms of service](https://aws.amazon.com/service-terms/)
* **Privacy Policy:** [privacy policy](https://aws.amazon.com/privacy/)

= GitHub Models & Azure AI =
* **Service Description & Purpose:** This plugin connects to GitHub Models via Microsoft Azure AI Inference (`models.inference.ai.azure.com`, `models.github.ai`) to execute AI models using a GitHub Personal Access Token.
* **What Data is Sent and When:** Prompts and conversation history are sent only when an AI query is run using a GitHub model.
* **Service Provider:** GitHub, Inc. / Microsoft Corporation
* **Terms of Service:** [terms of service](https://docs.github.com/en/site-policy/github-terms/github-terms-of-service)
* **Privacy Policy:** [privacy policy](https://privacy.microsoft.com/privacystatement)

= OpenAI =
* **Service Description & Purpose:** This plugin connects to the OpenAI API (`api.openai.com`) to generate vector embeddings and text completions when configured by the administrator with an OpenAI API key.
* **What Data is Sent and When:** Text content, post excerpts, and user prompts are sent only when generating embeddings or running queries with an OpenAI model.
* **Service Provider:** OpenAI, LLC
* **Terms of Service:** [terms of use](https://openai.com/policies/terms-of-use/)
* **Privacy Policy:** [privacy policy](https://openai.com/policies/privacy-policy/)

= Anthropic =
* **Service Description & Purpose:** This plugin connects to the Anthropic API (`api.anthropic.com`) to generate text completions, chat responses, and monitor AI token usage when configured by the administrator with an Anthropic API key or when sending queries to Anthropic Claude models.
* **What Data is Sent and When:** User prompts, conversation history, system instructions, and request parameters are sent only when an administrator or visitor initiates an AI query directed to an Anthropic Claude model.
* **Service Provider:** Anthropic PBC
* **Terms of Service:** [terms of service](https://www.anthropic.com/legal/commercial-terms)
* **Privacy Policy:** [privacy policy](https://www.anthropic.com/legal/privacy)

= Google Gemini & AI Studio =
* **Service Description & Purpose:** This plugin connects to the Google Generative Language API and Google AI Studio (`generativelanguage.googleapis.com`, `aistudio.google.com`) to generate text, process multimodal content, and generate vector embeddings when configured with a Gemini API key.
* **What Data is Sent and When:** Prompts, attached images, and text content are sent only when an AI request is executed with Google Gemini.
* **Service Provider:** Google LLC
* **Terms of Service:** [terms of service](https://ai.google.dev/gemini-api/terms) and [terms of use](https://policies.google.com/terms)
* **Privacy Policy:** [privacy policy](https://policies.google.com/privacy)

= Google Translate =
* **Service Description & Purpose:** This plugin connects to the Google Translate API (`translate.googleapis.com`) in the WPML integration module to automatically translate post titles and content into other languages.
* **What Data is Sent and When:** Post titles and content strings are sent only when the administrator explicitly triggers an automated translation action.
* **Service Provider:** Google LLC
* **Terms of Service:** [terms of service](https://policies.google.com/terms)
* **Privacy Policy:** [privacy policy](https://policies.google.com/privacy)

= Telegram =
* **Service Description & Purpose:** This plugin connects to the Telegram Bot API (`api.telegram.org`, `t.me`) to provide instant messaging capabilities, allowing users to interact with the site chatbot via Telegram.
* **What Data is Sent and When:** Webhook callback URLs are registered when setting up the bot; chat messages and automated replies are transmitted only when conversing with Telegram users.
* **Service Provider:** Telegram FZ-LLC
* **Terms of Service:** [terms of service](https://telegram.org/tos)
* **Privacy Policy:** [privacy policy](https://telegram.org/privacy)

= Meta / WhatsApp Cloud API =
* **Service Description & Purpose:** This plugin connects to the Meta Graph API and WhatsApp Business Cloud API (`graph.facebook.com`, `developers.facebook.com`, `business.facebook.com`) to allow users to interact with the site chatbot via WhatsApp.
* **What Data is Sent and When:** User phone numbers, inbound inquiries, and outbound chatbot reply messages are transmitted when an instant messaging conversation takes place over WhatsApp.
* **Service Provider:** Meta Platforms, Inc.
* **Terms of Service:** [terms of service](https://www.facebook.com/legal/terms)
* **Privacy Policy:** [privacy policy](https://www.facebook.com/privacy/policy/)

= Pollinations AI =
* **Service Description & Purpose:** This plugin connects to Pollinations AI (`image.pollinations.ai`) to generate featured images and media assets from text descriptions in the WordPress editor abilities.
* **What Data is Sent and When:** The text prompt describing the desired image is included in the URL query string when an administrator triggers the image generation ability.
* **Service Provider:** Pollinations AI
* **Terms of Service:** [terms of service](https://pollinations.ai/)
* **Privacy Policy:** [privacy policy](https://pollinations.ai/)

= WordPress.org Block Pattern Directory =
* **Service Description & Purpose:** This plugin connects to the WordPress.org Block Patterns API (`api.wordpress.org`) to search for public block patterns in the Gutenberg assistant ability.
* **What Data is Sent and When:** Pattern search keywords are sent when an administrator searches for block patterns.
* **Service Provider:** WordPress.org
* **Terms of Service:** [terms of service](https://wordpress.org/about/privacy/)
* **Privacy Policy:** [privacy policy](https://wordpress.org/about/privacy/)

== Source Code & Third-Party Libraries ==

In compliance with WordPress.org Guideline 4 (Code must be mostly human-readable), the public source code repository, custom scripts, and third-party libraries included in this plugin are documented below:

*   **Aiutoma Plugin Source Code Repository**
    *   **Public Repository:** https://github.com/frafish/aiutoma
    *   **License:** GPLv3 or later (https://www.gnu.org/licenses/gpl-3.0.html)
    *   **Native Plugin Scripts & Stylesheets:** All first-party JavaScript files (such as `modules/editor/assets/js/agent.js`, `modules/editor/assets/js/inline-prompt-block.js`, `modules/editor/assets/js/media-integration.js`, `modules/playground/assets/js/playground.js`, `modules/chatbot/assets/js/chatbot.js`, and `modules/mcp/assets/js/mcp.js`) and CSS stylesheets are original, uncompiled, human-readable source code written directly in vanilla JavaScript, jQuery, and CSS. They are authored by hand and require no compilation, bundling, or transpilation build steps.

*   **AI Request Logs Dashboard**
    *   **Description:** React and DataViews dashboard for viewing and monitoring AI request logs, latency, and token consumption under Tools.
    *   **Source Project:** Official WordPress AI Plugin (WordPress/ai)
    *   **Public Repository:** https://github.com/WordPress/ai
    *   **Public Source Code:** https://github.com/WordPress/ai/tree/trunk/src/admin/ai-request-logs
    *   **Local Uncompiled Source:** Included in `modules/ai/assets/tokens-log/src/`
    *   **License:** GPL-2.0-or-later (https://github.com/WordPress/ai/blob/trunk/LICENSE.md)
    *   **Copyright:** WordPress.org Contributors & Plugin Contributors
    *   **Build Tool & Instructions:** Built using @wordpress/scripts. To compile from source: clone https://github.com/WordPress/ai , run npm install, and execute npx wp-scripts build src/admin/ai-request-logs/index.tsx --output-path=build-scripts/admin .

*   **Select2**
    *   **Description:** Accessible, searchable replacement for dropdown select elements.
    *   **Files:** `modules/ai/assets/js/select2.min.js`, `modules/ai/assets/css/select2.min.css`
    *   **Version:** 4.1.0 (Stable)
    *   **Public Repository:** https://github.com/select2/select2
    *   **Public Source Code:** https://github.com/select2/select2/tree/4.1.0/src
    *   **License:** MIT (https://github.com/select2/select2/blob/master/LICENSE.md)
    *   **Copyright:** Kevin Brown, Igor Vaynberg, and Select2 contributors
    *   **Build Tool & Instructions:** Built using Grunt. To compile from source: `npm install && grunt compile`.

== Future Roadmap ==

1. **Advanced PDF Generation**: Create dynamic page exports, email attachments, and print reports directly via AI capabilities.
2. **Enhanced Booking Integrations**: Deep AI hooks for property management and booking systems to handle reservations and guest communications.
3. **And Much More**: We are constantly shipping new features to keep your site at the cutting edge of AI technology.

== Installation ==

1. Upload the plugin files to the `/wp-content/plugins/aiutoma` directory.
2. Activate the plugin through the 'Plugins' screen in WordPress.
3. Configure your API Keys (Gemini, OpenAI, or HuggingFace) under the AI Connectors menu.
4. Enjoy your new agentic superpowers!

== Frequently Asked Questions ==

= Is Aiutoma safe to use? =
Yes. Aiutoma operates with strict boundaries. It cannot modify WordPress core files. All sensitive database operations or options updates require administrator confirmation and offer instant rollback states.

= Does Aiutoma comply with the EU AI Act? =
Yes! Aiutoma is designed to satisfy the transparency requirements of the EU AI Act (Regulation (EU) 2024/1689, Article 50). The Frontend Chatbot prominently notifies users that they are interacting with an AI system, displays permanent accuracy disclaimers, clearly identifies when a human operator takes over, and provides direct links to your site's Privacy Policy.

= Can I run custom PHP scripts or modify theme/plugin files? =
For maximum security and 100% compliance with WordPress.org guidelines, the plugin does not execute arbitrary PHP code, edit theme/plugin files, or run terminal commands. All operations strictly adhere to safe, sandboxed WordPress APIs.

= What can external AI clients (Claude Desktop, Custom GPTs) do via MCP / OpenAPI? =
Aiutoma strictly complies with WordPress.org guidelines regarding remote execution. External MCP and OpenAPI integrations only expose content and data abilities (such as querying published posts, catalog lookup, or drafting content). In accordance with WordPress.org security policies, Aiutoma strictly blocks remote administration: it does not permit remote installation, updating, or activation of plugins/themes, arbitrary code execution, filesystem modifications, or user management.

== Changelog ==

= 1.0.2 =
* Hardened permission callbacks across all abilities with fail-closed checks on the abilities dispatcher.
* Removed administrative and filesystem abilities to strictly comply with WordPress.org security policies.
* Enforced non-administrative security filters on remote MCP and OpenAPI endpoints.
* Refactored uncompiled JavaScript assets for readability and documented the public source repository.
* Cleaned up development checklist artifacts.

= 1.0.1 =
* Added Amazon AWS AI (Bedrock) connector support.
* Added dynamic Markdown endpoints and `/llms.txt` generator for AI crawlers.
* Added Model Context Protocol (MCP) and OpenAPI external integrations.
* Added secure Webhook Receiver for AI logging via native WordPress comments.
* Major enhancements to the Gutenberg Editor Agent and Inline Prompt blocks.
* Documented WPML integration and Free-Tier Provider capabilities.

= 1.0.0 =
* Initial release.
