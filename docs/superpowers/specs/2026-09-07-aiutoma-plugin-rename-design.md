# Design Document: Complete Rename to Aiutoma

## Overview
This document specifies the complete rename of the WordPress plugin suite from **Wizard AI** (`wizard-ai`) to **Aiutoma** (`aiutoma`), along with its companion developer extension from **Wizard AI Developer Extension** (`wizard-ai-developer-extension`) to **Aiutoma Dev** (`aiutoma-dev`).

Per requirements:
- No legacy backwards-compatibility or migration layer is needed; treated as a fresh plugin built cleanly from zero.
- Standardized, consistent naming: no shortened nicknames (`wai_`, `aiu_`). Everything uses the canonical `aiutoma` naming convention.
- Developer extension renamed to `aiutoma-dev`.
- External consumer references (such as in `wizard-blocks-pro`) updated to call the new endpoints.

---

## 1. Directory and File Layout

### 1.1 Core Plugin (`aiutoma`)
- Directory: `/var/www/html/wp-content/plugins/aiutoma/` (renamed from `wizard-ai/`)
- Main bootstrap file: `aiutoma.php` (renamed from `wizard-ai.php`)
- Asset icon: `modules/ai/assets/svg/aiutoma.svg` (renamed from `wizard-ai.svg`)
- Uploads directory: `wp-content/uploads/aiutoma/` (renamed from `wp-content/uploads/wizard-ai/`)

### 1.2 Developer Extension (`aiutoma-dev`)
- Directory: `/var/www/html/wp-content/plugins/aiutoma-dev/` (renamed from `wizard-ai-developer-extension/`)
- Main bootstrap file: `aiutoma-dev.php` (renamed from `wizard-ai-developer-extension.php`)

---

## 2. Naming Conventions & Identifiers

### 2.1 Core Plugin
| Category | Old Value | New Value (`aiutoma`) |
|---|---|---|
| **Plugin Name** | Wizard AI AIO | AIutoma – The Autonomous AI Assistant |
| **Plugin Slug / Directory** | `wizard-ai` | `aiutoma` |
| **Text Domain** | `wizard-ai` | `aiutoma` |
| **Main PHP Constants** | `WIZARD_AI_FILE`, `WIZARD_AI_PATH`, `WIZARD_AI_URL`, `WIZARD_AI_VERSION` | `AIUTOMA_FILE`, `AIUTOMA_PATH`, `AIUTOMA_URL`, `AIUTOMA_VERSION` |
| **PHP Root Namespace** | `\WizardAi\` | `\Aiutoma\` |
| **Autoloader Prefix** | `'WizardAi\\'` | `'Aiutoma\\'` |
| **Deactivation Hook/Action**| `wizard_ai_deactivate`, `wizard_ai_deactivated` | `aiutoma_deactivate`, `aiutoma_deactivated` |
| **Option: Active Modules** | `wizard_ai_active_modules` | `aiutoma_active_modules` |
| **Options: API Keys** | `wai_openai_api_key`, `wai_anthropic_api_key`, `wai_gemini_api_key`, `wai_huggingface_api_key`, etc. | `aiutoma_openai_api_key`, `aiutoma_anthropic_api_key`, `aiutoma_gemini_api_key`, `aiutoma_huggingface_api_key`, etc. |
| **Options: General** | `wai_license_key`, `wai_license_status`, `wai_default_model`, `wai_wpml_model` | `aiutoma_license_key`, `aiutoma_license_status`, `aiutoma_default_model`, `aiutoma_wpml_model` |
| **Options: MCP** | `wai_mcp_token`, `wai_mcp_acting_user`, `wai_mcp_webhook_token` | `aiutoma_mcp_token`, `aiutoma_mcp_acting_user`, `aiutoma_mcp_webhook_token` |
| **Options: Safe Mode** | `wai_safe_mode_allowlist` | `aiutoma_safe_mode_allowlist` |
| **Custom Post Type** | `wai_task` | `aiutoma_task` |
| **Transients** | `wai_*` | `aiutoma_*` |
| **REST Namespace** | `wizard-ai/v1` | `aiutoma/v1` |
| **MCP URI Scheme** | `wizard://skills/...` | `aiutoma://skills/...` |
| **MCP Bundle Name** | `wizardai-<site>.mcpb` | `aiutoma-<site>.mcpb` |
| **Safe Mode Flag File** | `.wai_safe` (in ABSPATH) | `.aiutoma_safe` |
| **Safe Mode URL & Param** | `/wai`, `wai_enforce_safe_mode` | `/aiutoma`, `aiutoma_enforce_safe_mode` |
| **Action/Filter Hooks** | `wizard_ai/*`, `wizard_ai_*` | `aiutoma/*`, `aiutoma_*` |
| **Nonce Names** | `wai_mcp_save_settings`, `wai_download_mcpb`, `wizard_ai_oauth_consent`, etc. | `aiutoma_mcp_save_settings`, `aiutoma_download_mcpb`, `aiutoma_oauth_consent`, etc. |
| **CSS Classes & IDs** | `.wai-*`, `#wai-*`, `#wai_*` | `.aiutoma-*`, `#aiutoma-*`, `#aiutoma_*` |
| **JS Global Objects** | `wizard_ai_*`, `wai_*` | `aiutoma_*` |

### 2.2 Developer Extension (`aiutoma-dev`)
| Category | Old Value | New Value (`aiutoma-dev`) |
|---|---|---|
| **Plugin Name** | Wizard AI Developer Extension | Aiutoma Dev |
| **Plugin Slug / Directory** | `wizard-ai-developer-extension` | `aiutoma-dev` |
| **Main File** | `wizard-ai-developer-extension.php` | `aiutoma-dev.php` |
| **Text Domain** | `wizard-ai-developer-extension` | `aiutoma-dev` |
| **PHP Constants** | `WIZARD_AI_DEV_EXTENSION_VERSION`, `..._PATH`, `..._URL` | `AIUTOMA_DEV_VERSION`, `AIUTOMA_DEV_PATH`, `AIUTOMA_DEV_URL` |
| **PHP Root Namespace** | `\WizardAiDeveloperExtension\` | `\AiutomaDev\` |
| **Global Exec Flags** | `$wai_is_executing`, `$wai_shutdown_registered` | `$aiutoma_dev_is_executing`, `$aiutoma_dev_shutdown_registered` |
| **Dependency Check** | `defined('WIZARD_AI_VERSION')` | `defined('AIUTOMA_VERSION')` |
| **Hooks** | `wizard_ai_register_abilities`, `wizard_ai_enable_php_codemirror` | `aiutoma_register_abilities`, `aiutoma_enable_php_codemirror` |

---

## 3. External Plugin Updates: WizardBlocksPRO
In `wp-content/plugins/wizard-blocks-pro/modules/ai/assets/js/ai.js`:
- Update REST endpoint fetch from `wizardData.rest_url + 'wizard-ai/v1/ai-models'` to `wizardData.rest_url + 'aiutoma/v1/ai-models'`.

---

## 4. Verification and Testing
1. **PHP Syntax & Linter:** Run `php -l` on all renamed and modified PHP files across `aiutoma`, `aiutoma-dev`, and `wizard-blocks-pro`.
2. **Namespace & Autoloading Verification:** Verify that classes load correctly under `Aiutoma\...` and `AiutomaDev\...`.
3. **No Legacy Occurrences:** Perform grep checks to ensure no unintended occurrences of `wizard_ai`, `WIZARD_AI`, `WizardAi`, or `wai_` remain in active PHP/JS/CSS code.
4. **Clean Uninstall Verification:** Confirm `uninstall.php` cleans up `aiutoma_*` options, transients, and CPT records.

