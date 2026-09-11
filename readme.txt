=== AIutoma – The AIO Autonomous AI Assistant ===
Contributors: frapesce
Tags: ai, agent, free tier, mcp, gutenberg
Requires at least: 7.0
Tested up to: 7.1
Stable tag: 1.0.1
Requires PHP: 8.1
License: GPLv3 or later
License URI: https://www.gnu.org/licenses/gpl-3.0.html

Aiutoma is an agentic AI assistant designed to seamlessly integrate into your WordPress and WooCommerce ecosystem.

== Description ==

Welcome to **Aiutoma** — an enterprise-grade AI co-pilot that transforms your WordPress website into an autonomous, intelligent powerhouse. 

Are you tired of juggling multiple complex automation tools, bloated plugins, and fragmented workflows? **Aiutoma** is a fully-integrated, agentic assistant designed to bring true automation directly to your WordPress and WooCommerce dashboard. Whether you need to generate high-converting content, orchestrate complex automated workflows, provide real-time omnichannel customer support, or expose your site to the world of AI via MCP, Aiutoma handles it all effortlessly.

### ✨ Why Choose Aiutoma?

Aiutoma isn't just another ChatGPT wrapper. It is built natively on top of the futuristic WordPress 7.0 AI API. It feels like a core part of WordPress—ensuring a clean, ultra-secure, and lightning-fast experience that never slows down your frontend. Stop working *in* your website and start working *on* your business while Aiutoma handles the heavy lifting.

### 🚀 Powerful Features Included

#### 1. 🦸‍♂️ AI Playground & Automation Hub
Your centralized command center. Chat with your AI to write content, configure complex settings, or manage your database. Need things done automatically? Schedule **Automated AI Tasks** using our native task infrastructure to run on a repeating schedule or at precise dates and times. Everything comes with instant 1-click rollbacks for peace of mind.

#### 2. 📱 Omnichannel IM & Chatbot (WhatsApp & Telegram)
Take your customer engagement to the next level. Seamlessly integrate your site with Telegram and WhatsApp to deploy AI-driven conversational agents. Provide instant customer support, send proactive notifications, and even trigger automated tasks directly from your smartphone.

#### 3. 🎭 AI Skills & Personas
Tailor your AI's brain to match your brand's DNA. Easily toggle custom "Skills" in the Playground to give the AI specific personalities, strict coding guidelines, or structural rules. It learns your business logic and executes tasks exactly how you want them.

#### 4. 🪄 Gutenberg Editor Agent
Meet your brilliant new co-writer. A floating AI assistant lives directly inside your block editor. Just type `/prompt` anywhere to visually generate complex layouts, rewrite paragraphs, or autonomously create and attach stunning, context-aware featured images.

#### 5. 💰 AI Token & Budget Management
Maintain absolute control over your AI costs. Our advanced Token Usage Monitoring system provides a visual budget bar and low-token alerts, ensuring you never exceed your monthly budget caps while scaling your automation.

#### 6. 🧠 Bring Your Own Model (Free Tiers Included!)
Start automating at zero cost! Aiutoma seamlessly integrates with top providers offering generous free tiers (like Google Gemini and HuggingFace), as well as premium models (OpenAI) and edge networks (Cloudflare Workers AI). Easily configure custom endpoints to connect any model you prefer.

#### 7. 🌍 WPML & Multi-Language Auto-Translation
Take your business global without lifting a finger. Aiutoma natively integrates with WPML to automatically translate and sync your content across multiple languages using state-of-the-art AI, keeping your international SEO flawless and saving you thousands in translation fees.

#### 8. 📡 AI-Ready SEO (LLMs.txt & Markdown)
Prepare your website for the future of search. Aiutoma automatically generates clean, readable Markdown versions of your pages and an `/llms.txt` file, ensuring your site is perfectly indexed by AI search engines like ChatGPT, Claude, and Perplexity.

#### 9. 🔗 Remote Control (MCP & OpenAPI)
Manage your WordPress site from anywhere. Securely connect your favorite external AI clients (like Claude Desktop or Custom GPTs) via the Model Context Protocol (MCP) or OpenAPI to read data, update content, and execute tasks remotely with repository-compliant security.

#### 10. 🔔 AI Webhook Receiver
Keep a perfect audit trail. Provide external agents (like Zapier, Make, or custom scripts) with a secure endpoint that automatically logs their actions as native WordPress comments directly on the relevant posts.

#### 11. 📚 RAG Knowledge Base
Give your AI perfect memory. Aiutoma automatically syncs your posts, WooCommerce products, and documentation into a local vector database so it always knows your exact inventory and content, ensuring hyper-accurate responses.

#### 12. 💬 Smart Frontend Chatbot (EU AI Act Ready)
Empower your visitors with total legal peace of mind. Deploy a sleek, lightning-fast chat widget powered by your own site data to provide instant customer support, smart WooCommerce recommendations, and lead generation on autopilot. Fully compliant with EU AI Act (Art. 50) transparency requirements, featuring permanent AI badges, accuracy disclaimers, seamless operator takeover, and GDPR consent.

#### 13. 🛠️ Automated Dependency Management
Keep your system secure and up-to-date effortlessly. Aiutoma features a robust, one-click interface for automating the installation and updates of complex external libraries (like PDF generators and device detectors) directly from official sources, completely hands-free.

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
* **Terms of Service:** [terms of service](https://mistral.ai/terms/)
* **Privacy Policy:** [privacy policy](https://mistral.ai/privacy-policy/)

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

== Third-Party Libraries & Source Code ==

In compliance with WordPress.org Guideline 4 (Code must be mostly human-readable), the public source code and build instructions for all third-party and compiled/minified assets included in this plugin are documented below:

*   **AI Request Logs Dashboard**
    *   **Description:** React and DataViews dashboard for viewing and monitoring AI request logs, latency, and token consumption under Tools.
    *   **Source Project:** Official WordPress AI Plugin (WordPress/ai)
    *   **Public Repository:** https://github.com/WordPress/ai
    *   **Public Source Code:** https://github.com/WordPress/ai/tree/trunk/src/admin/ai-request-logs
    *   **Local Uncompiled Source:** Included in `modules/ai/assets/tokens-log/src/`
    *   **License:** GPL-2.0-or-later (https://github.com/WordPress/ai/blob/trunk/LICENSE.md)
    *   **Copyright:** WordPress.org Contributors & Plugin Contributors
    *   **Build Tool & Instructions:** Built using `@wordpress/scripts`. To compile from source: clone `https://github.com/WordPress/ai`, run `npm install`, and execute `npx wp-scripts build src/admin/ai-request-logs/index.tsx --output-path=build-scripts/admin`.

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
For maximum security and 100% compliance with WordPress.org guidelines, the core Aiutoma plugin does not execute arbitrary PHP code. If you require advanced developer tools (PHP execution, file modification, WP-CLI), simply activate the official companion "Aiutoma Dev".

== Changelog ==

= 1.0.1 =
* Added Amazon AWS AI (Bedrock) connector support.
* Added dynamic Markdown endpoints and `/llms.txt` generator for AI crawlers.
* Added Model Context Protocol (MCP) and OpenAPI external integrations.
* Added secure Webhook Receiver for AI logging via native WordPress comments.
* Major enhancements to the Gutenberg Editor Agent and Inline Prompt blocks.
* Documented WPML integration and Free-Tier Provider capabilities.

= 1.0.0 =
* Initial release.
