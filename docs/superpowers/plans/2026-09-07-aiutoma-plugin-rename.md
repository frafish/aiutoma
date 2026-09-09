# Implementation Plan: Complete Rename from Wizard AI to Aiutoma

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Completely rename the core plugin `wizard-ai` to `aiutoma`, the companion developer extension `wizard-ai-developer-extension` to `aiutoma-dev`, and update external consumers (`wizard-blocks-pro`), using canonical `aiutoma` naming with no abbreviated forms or legacy migration layers.

**Architecture:** Systematic multi-layer refactoring: PHP namespaces (`WizardAi\` -> `Aiutoma\`, `WizardAiDeveloperExtension\` -> `AiutomaDev\`), constants (`WIZARD_AI_*` -> `AIUTOMA_*`, `WIZARD_AI_DEV_EXTENSION_*` -> `AIUTOMA_DEV_*`), DB options/transients/nonces/CPT (`wai_*` & `wizard_ai_*` -> `aiutoma_*`), REST endpoints (`wizard-ai/v1` -> `aiutoma/v1`), frontend CSS/JS/DOM, followed by directory and bootstrap file renames.

**Tech Stack:** PHP 8.1+, WordPress 6.0+, JavaScript ES6, CSS.

## Global Constraints
- No legacy backwards-compatibility or migration code. Clean, zero-based rename.
- Canonical `aiutoma` naming everywhere: no short nicknames (`wai_` or `aiu_`).
- Dev extension named `aiutoma-dev`.
- Update REST fetch in `wizard-blocks-pro`.

---

### Task 1: Core Plugin PHP Bootstrap & Cleanup Files
**Files:**
- Modify: `wp-content/plugins/wizard-ai/wizard-ai.php`
- Modify: `wp-content/plugins/wizard-ai/uninstall.php`
- Modify: `wp-content/plugins/wizard-ai/composer.json`

- [ ] **Step 1: Update `wizard-ai.php`**
  - Define `AIUTOMA_FILE`, `AIUTOMA_PATH`, `AIUTOMA_URL`, `AIUTOMA_VERSION` (replace `WIZARD_AI_*`).
  - Update autoloader prefix from `'WizardAi\\'` to `'Aiutoma\\'`.
  - Update option `'wizard_ai_active_modules'` to `'aiutoma_active_modules'`.
  - Update class calls `\WizardAi\Modules\...` to `\Aiutoma\Modules\...`.
  - Update deactivation hook: `register_deactivation_hook(AIUTOMA_FILE, 'aiutoma_deactivate')` and function `aiutoma_deactivate()` triggering `do_action('aiutoma_deactivated')`.

- [ ] **Step 2: Update `uninstall.php`**
  - Replace all `wai_*` option keys with `aiutoma_*` (`aiutoma_license_key`, `aiutoma_license_status`, `aiutoma_openai_api_key`, `aiutoma_anthropic_api_key`, `aiutoma_gemini_api_key`, `aiutoma_huggingface_api_key`, `aiutoma_default_model`, `aiutoma_mcp_token`, `aiutoma_wpml_model`).
  - Replace transient prefix `wai_` with `aiutoma_`.
  - Replace custom post type `wai_task` with `aiutoma_task`.

- [ ] **Step 3: Syntax check**
  Run: `php -l wp-content/plugins/wizard-ai/wizard-ai.php && php -l wp-content/plugins/wizard-ai/uninstall.php`
  Expected: No syntax errors detected.

---

### Task 2: Core Plugin Modules PHP Refactor (Namespaces, Options, Hooks, REST)
**Files:**
- Modify all PHP files in `wp-content/plugins/wizard-ai/modules/`:
  - `modules/ai/` (including `abilities/`, `classes/`, `traits/`)
  - `modules/chatbot/` (including `traits/`)
  - `modules/editor/` (including `traits/`)
  - `modules/markdown/` (including `traits/`)
  - `modules/mcp/` (including `traits/`)
  - `modules/playground/` (including `traits/`)
  - `modules/providers/`
  - `modules/seo/` (including `traits/`)
  - `modules/wpml/` (including `traits/`)

- [ ] **Step 1: Refactor Namespaces**
  - Replace `namespace WizardAi\` with `namespace Aiutoma\` in all files under `modules/`.
  - Replace `use WizardAi\` with `use Aiutoma\` and `\WizardAi\` with `\Aiutoma\`.

- [ ] **Step 2: Refactor Constants, Options, Hooks, Nonces, and REST API**
  - Constants: `WIZARD_AI_*` -> `AIUTOMA_*`
  - Options & Transients: `wai_*` -> `aiutoma_*` and `wizard_ai_*` -> `aiutoma_*`
  - Hooks: `wizard_ai/abilities` -> `aiutoma/abilities`, `wizard_ai/skills` -> `aiutoma/skills`, `wizard_ai_*` -> `aiutoma_*`
  - Nonces: `wai_*` -> `aiutoma_*`, `wizard_ai_oauth_consent` -> `aiutoma_oauth_consent`
  - REST namespace: `wizard-ai/v1` -> `aiutoma/v1`
  - MCP scheme: `wizard://skills/` -> `aiutoma://skills/`
  - Text domain in translation calls: `'wizard-ai'` -> `'aiutoma'`
  - Storage directory: `get_storage_dir()` using `wp-content/uploads/aiutoma`
  - CPT: `wai_task` -> `aiutoma_task`
  - Safe mode flag & parameter: `.wai_safe` -> `.aiutoma_safe`, `wai_enforce_safe_mode` -> `aiutoma_enforce_safe_mode`, `/wai` -> `/aiutoma`

- [ ] **Step 3: Syntax check**
  Run: `find wp-content/plugins/wizard-ai/modules -name "*.php" -exec php -l {} \;`
  Expected: All files report "No syntax errors detected".

---

### Task 3: Core Plugin Frontend Assets Refactor (CSS, JS, SVG)
**Files:**
- Modify: `wp-content/plugins/wizard-ai/modules/*/assets/**/*.{js,css}`
- Rename: `wp-content/plugins/wizard-ai/modules/ai/assets/svg/wizard-ai.svg` -> `aiutoma.svg`

- [ ] **Step 1: Refactor CSS and JS files**
  - Replace `.wai-*` with `.aiutoma-*`
  - Replace `#wai-*` and `#wai_*` with `#aiutoma-*` and `#aiutoma_*`
  - Replace REST route endpoints `wizard-ai/v1` with `aiutoma/v1`
  - Replace JS globals / localization objects `wizard_ai_*` / `wai_*` with `aiutoma_*`

- [ ] **Step 2: Rename SVG asset**
  - Rename `modules/ai/assets/svg/wizard-ai.svg` to `modules/ai/assets/svg/aiutoma.svg`.
  - Update references in PHP trait/module code to `aiutoma.svg`.

---

### Task 4: Developer Extension Plugin Refactor (`aiutoma-dev`)
**Files:**
- Modify: `wp-content/plugins/wizard-ai-developer-extension/wizard-ai-developer-extension.php`
- Modify: `wp-content/plugins/wizard-ai-developer-extension/includes/*.php`
- Modify: `wp-content/plugins/wizard-ai-developer-extension/README.md`

- [ ] **Step 1: Refactor `wizard-ai-developer-extension.php`**
  - Update plugin header:
    - Plugin Name: `Aiutoma Dev`
    - Text Domain: `aiutoma-dev`
  - Constants:
    - `AIUTOMA_DEV_VERSION`, `AIUTOMA_DEV_PATH`, `AIUTOMA_DEV_URL`
  - Update namespaces:
    - `\AiutomaDev\Includes\Safe_Mode::init();`
    - `\AiutomaDev\Includes\Mcp_Companion::init();`
    - `\AiutomaDev\Includes\Im_Companion::init();`
  - Check core plugin: `defined('AIUTOMA_VERSION')`
  - Hooks: `aiutoma_register_abilities`, `aiutoma_enable_php_codemirror`

- [ ] **Step 2: Refactor `includes/*.php`**
  - Namespace: `namespace AiutomaDev\Includes;`
  - In `change-recorder.php`: class name and references
  - In `developer-abilities.php`: execution globals `$aiutoma_dev_is_executing`, `$aiutoma_dev_shutdown_registered`
  - In `safe-mode.php`: `.aiutoma_safe`, `aiutoma_enforce_safe_mode`, `aiutoma_safe_mode_allowlist`, `aiutoma_mcp_token`, `/aiutoma`
  - In `mcp-companion.php`: `aiutoma_mcp_acting_user`, filters
  - In `im-companion.php`: companion hooks and classes

- [ ] **Step 3: Syntax check**
  Run: `find wp-content/plugins/wizard-ai-developer-extension -name "*.php" -exec php -l {} \;`
  Expected: All files report "No syntax errors detected".

---

### Task 5: External Consumer Integration: WizardBlocksPRO
**Files:**
- Modify: `wp-content/plugins/wizard-blocks-pro/modules/ai/assets/js/ai.js`

- [ ] **Step 1: Update REST endpoint call**
  - In `modules/ai/assets/js/ai.js`, change `wizardData.rest_url + 'wizard-ai/v1/ai-models'` to `wizardData.rest_url + 'aiutoma/v1/ai-models'`.

---

### Task 6: Directory and Main File Renames
**Commands:**
- Rename `wp-content/plugins/wizard-ai/wizard-ai.php` -> `aiutoma.php`
- Rename `wp-content/plugins/wizard-ai` -> `wp-content/plugins/aiutoma`
- Rename `wp-content/plugins/wizard-ai-developer-extension/wizard-ai-developer-extension.php` -> `aiutoma-dev.php`
- Rename `wp-content/plugins/wizard-ai-developer-extension` -> `wp-content/plugins/aiutoma-dev`
- If exists: rename `wp-content/uploads/wizard-ai` -> `wp-content/uploads/aiutoma`

- [ ] **Step 1: Rename files and folders via filesystem/git**
  - Perform clean rename of core plugin directory and entry file.
  - Perform clean rename of developer extension directory and entry file.
  - Perform rename of uploads directory if present.

---

### Task 7: Comprehensive Verification & Linters
**Files:**
- All files in `aiutoma`, `aiutoma-dev`, and `wizard-blocks-pro`

- [ ] **Step 1: Syntax linting across entire codebase**
  Run: `find wp-content/plugins/aiutoma wp-content/plugins/aiutoma-dev -name "*.php" -exec php -l {} \;`
  Expected: 100% pass without syntax errors.

- [ ] **Step 2: Grep audit for stale references**
  Run: grep searches for `wizard_ai`, `WIZARD_AI`, `WizardAi`, `wai_` in active code.
  Expected: Zero unintended occurrences in code.

