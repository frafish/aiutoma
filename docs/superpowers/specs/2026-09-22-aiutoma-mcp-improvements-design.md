# Design Document: AIUTOMA MCP Improvements & New WooCommerce Abilities

- **Date:** 2026-09-22
- **Topic:** AIUTOMA MCP Improvements & WooCommerce Abilities
- **Status:** Approved / Spec Review

---

## 1. Context & Motivation

During remote MCP REST API interactions with WordPress running AIUTOMA, several operational hurdles were identified:
1. **Plesk PHP Binary Mismatch in `aiutoma-run-wp-cli`**: Under Plesk web server environments (PHP-FPM), the system default `/usr/bin/env: 'php'` fails because PHP CLI executables reside in isolated paths like `/opt/plesk/php/8.x/bin/php`. Furthermore, `PHP_BINARY` in FPM often references `php-fpm`, which cannot run CLI phars.
2. **Missing Input Schema in `mcp-adapter/discover-abilities`**: WordPress Core Abilities API (`WP_Ability::validate_input`) throws `ability_missing_input_schema` whenever an ability defines no `input_schema` and the caller passes an empty object `{}` or array `[]` (standard behavior in JSON-RPC MCP clients).
3. **Overly Strict Output Schema in `woocommerce/products-query`**: WooCommerce core specifies `'type' => 'string'` for `price`, `regular_price`, and `sale_price`. Variable products, products with 0 price, or unpriced draft products return numeric `0` or `null`, failing REST validation and causing error: `output[products][0][price] non è del tipo string`.
4. **Missing Bulk Variation & Diagnostic Capabilities**:
   - Lack of an ability to bulk update regular price, sale price, stock parameters, and custom/arbitrary metadata across all or selected variations of a variable product.
   - Lack of a deep diagnostic ability to analyze why a product (simple or variable) cannot be purchased or added to cart, identify variations missing prices, and inspect active filters on `woocommerce_is_purchasable`.
5. **Self-Signed SSL Certificates on Staging**: Lack of explicit guidance for MCP clients connecting via `curl` to staging environments where self-signed certificates cause `curl (60)` errors.

---

## 2. Architecture & Components

### 2.1 Plesk / cPanel PHP CLI Resolution (`aiutoma-dev`)

**Target File:** `/var/www/wp/wp-content/plugins/aiutoma-dev/includes/developer-abilities.php`

#### Logic:
1. **Helper Method `find_php_cli_binary(?string $custom_path = null): ?string`**:
   - Priority 1: `$custom_path` from input parameters (if supplied and executable).
   - Priority 2: Constant `AIUTOMA_PHP_PATH` (if defined in `wp-config.php`).
   - Priority 3: Option `get_option('aiutoma_dev_php_path')` or filter `apply_filters('aiutoma_php_binary_path', null)`.
   - Priority 4: Dynamic Discovery:
     - Check `PHP_BINARY`. If `basename(PHP_BINARY) === 'php'`, use it. If it contains `php-fpm` or `php-cgi` (e.g. `/opt/plesk/php/8.2/sbin/php-fpm`), test replacement to `bin/php` (`/opt/plesk/php/8.2/bin/php`).
     - Check Plesk paths for current PHP version (`PHP_MAJOR_VERSION . '.' . PHP_MINOR_VERSION`) and common versions (8.4, 8.3, 8.2, 8.1, 8.0): `/opt/plesk/php/{version}/bin/php`.
     - Check cPanel paths: `/opt/cpanel/ea-php{version_no_dot}/root/usr/bin/php`.
     - Check standard paths: `/usr/local/bin/php`, `/usr/bin/php`, `/bin/php`.
     - Check `which php 2>/dev/null`.
2. **WP-CLI Command Execution**:
   - Rather than executing `$wp_path` directly (which triggers `#!/usr/bin/env php` and fails on Plesk), always invoke explicitly via the detected PHP CLI binary:
     ```bash
     PATH="<php_dir>:$PATH" <php_binary> <wp_path> <args...> 2>&1
     ```
   - Prepend `PATH="<php_dir>:$PATH"` so child processes spawned by WP-CLI also inherit the correct PHP CLI binary.
3. **Input Schema Extension**:
   - Add optional `php_path` property to `aiutoma/run-wp-cli` schema (`type: 'string'`, description: 'Custom path to the PHP CLI executable').

---

### 2.2 Schema Resilience & Normalization (`aiutoma`)

**Target Files:**
- `/var/www/wp/wp-content/plugins/aiutoma/modules/ai/abilities.php`
- `/var/www/wp/wp-content/plugins/aiutoma/modules/ai/abilities/woo-commerce.php`
- `/var/www/wp/wp-content/plugins/aiutoma/modules/ai/abilities/core.php`

#### Logic:
1. **Resolving `ability_missing_input_schema` (`mcp-adapter/discover-abilities`)**:
   - Hook `wp_register_ability_args`: If any ability is registered with an empty or missing `input_schema`, default it to:
     ```php
     'input_schema' => [
         'type' => 'object',
         'properties' => (object)[]
     ]
     ```
   - Hook `wp_ability_validate_input`: If validation fails with `ability_missing_input_schema` and `$input` is empty (`empty($input)` or `$input === []`), treat validation as successful (`return true`).
   - Hook `wp_ability_normalize_input`: Normalize empty array input to `null` if the ability defines no input schema.
2. **Relaxing Output Schema for `woocommerce/products-query`**:
   - Hook `wp_register_ability_args`: For `woocommerce/products-query` (and any other product listing/query abilities), inspect and modify `output_schema`:
     - Set `'type' => ['string', 'number', 'null']` on `price`, `regular_price`, and `sale_price` (both at the root level and within `properties.products.items.properties`).
   - Hook `wp_ability_validate_output`: If output validation fails solely on price field types, allow recovery.
   - Interceptor / Formatter: In `modules/ai/abilities/woo-commerce.php` and `modules/ai/abilities/core.php`, ensure price fields are formatted consistently.

---

### 2.3 New Ability: `woocommerce/bulk-update-variations` (`aiutoma`)

**Target File:** `/var/www/wp/wp-content/plugins/aiutoma/modules/ai/abilities/woo-commerce.php`

#### Specification:
- **Name:** `woocommerce/bulk-update-variations`
- **Category:** `woocommerce`
- **Permission:** `manage_woocommerce` capability
- **Annotations:** `readonly: false`, `idempotent: true`, `destructive: false`
- **Input Schema:**
  ```json
  {
    "type": "object",
    "properties": {
      "product_id": {
        "type": "integer",
        "description": "ID of the parent variable product."
      },
      "variation_ids": {
        "type": "array",
        "items": { "type": "integer" },
        "description": "Optional list of specific variation IDs to update. If omitted or empty, all variations of the product are updated."
      },
      "regular_price": {
        "type": ["string", "number"],
        "description": "Regular price to set across target variations."
      },
      "sale_price": {
        "type": ["string", "number", "null"],
        "description": "Sale price to set across target variations. Pass empty string or null to clear."
      },
      "stock_status": {
        "type": "string",
        "enum": ["instock", "outofstock", "onbackorder"],
        "description": "Stock status for target variations."
      },
      "stock_quantity": {
        "type": "integer",
        "description": "Stock quantity for target variations (automatically enables manage_stock)."
      },
      "manage_stock": {
        "type": "boolean",
        "description": "Explicitly toggle inventory management at variation level."
      },
      "status": {
        "type": "string",
        "enum": ["publish", "private"],
        "description": "Status of the variations."
      },
      "virtual": {
        "type": "boolean",
        "description": "Whether variations are virtual."
      },
      "downloadable": {
        "type": "boolean",
        "description": "Whether variations are downloadable."
      },
      "weight": {
        "type": ["string", "number"],
        "description": "Weight for target variations."
      },
      "dimensions": {
        "type": "object",
        "properties": {
          "length": { "type": ["string", "number"] },
          "width": { "type": ["string", "number"] },
          "height": { "type": ["string", "number"] }
        },
        "description": "Dimensions for target variations."
      },
      "description": {
        "type": "string",
        "description": "Description for target variations."
      },
      "meta_data": {
        "type": "object",
        "description": "Generic key-value map of post metadata to set on each variation (e.g. {'_b2c_price': '29.99', '_b2b_price': '24.99'})."
      }
    },
    "required": ["product_id"]
  }
  ```
- **Execution Flow:**
  1. Validate parent product exists and is `WC_Product_Variable`.
  2. Retrieve child IDs via `$parent->get_children()`.
  3. Filter variations by `variation_ids` if provided; ensure all target IDs belong to `$product_id`.
  4. Loop through each target variation:
     - Load `wc_get_product($id)`.
     - Update native fields if provided in input.
     - Update metadata generically from `meta_data` via `$variation->update_meta_data($key, $value)`.
     - `$variation->save()`.
  5. Synchronize parent variable product: `\WC_Product_Variable::sync($product_id)`.
  6. Invalidate product caches: `wc_delete_product_transients($product_id)`.
  7. Return:
     ```json
     {
       "success": true,
       "product_id": 123,
       "total_updated": 4,
       "variation_ids": [124, 125, 126, 127],
       "applied_changes": { ... }
     }
     ```

---

### 2.4 New Ability: `woocommerce/product-diagnostics` (`aiutoma`)

**Target File:** `/var/www/wp/wp-content/plugins/aiutoma/modules/ai/abilities/woo-commerce.php`

#### Specification:
- **Name:** `woocommerce/product-diagnostics`
- **Category:** `woocommerce`
- **Permission:** `manage_woocommerce` capability
- **Annotations:** `readonly: true`, `idempotent: true`, `destructive: false`
- **Input Schema:**
  ```json
  {
    "type": "object",
    "properties": {
      "product_id": {
        "type": "integer",
        "description": "Product ID to diagnose."
      },
      "sku": {
        "type": "string",
        "description": "Product SKU to diagnose (used if product_id is not provided)."
      }
    }
  }
  ```
- **Diagnostic Logic:**
  1. **Product Resolution:** Resolve product by `product_id` or `wc_get_product_id_by_sku($sku)`. Return error if not found.
  2. **General / Catalog:**
     - Type (`simple`, `variable`, `grouped`, `external`, `variation`).
     - Post status (`publish`, `draft`, `pending`, `private`, `trash`).
     - Catalog visibility (`visible`, `catalog`, `search`, `hidden`).
  3. **Price Analysis:**
     - For simple/variation: check regular price, sale price, active price. Flag if price is empty string or 0.
     - For variable: total variations, count and specific IDs of variations missing regular price, min/max price range.
  4. **Stock Analysis:**
     - `manage_stock`, `stock_status`, `stock_quantity`, `backorders` status.
     - For variable: count of variations in stock vs out of stock vs backordered.
  5. **Purchasability & Cart Eligibility (`is_purchasable`):**
     - Run `$product->is_purchasable()` and `$product->is_in_stock()`.
     - Pinpoint exact blocking reasons:
       - `status_not_published`: Post status is not 'publish'.
       - `empty_price`: No price defined on product or any variation.
       - `out_of_stock`: Out of stock with backorders disallowed.
       - `no_active_variations`: Variable product has no variations or no purchasable variations.
       - `missing_variation_prices`: Some or all variations have no price.
     - **Filter Inspection:**
       - Inspect `$wp_filter['woocommerce_is_purchasable']` and `$wp_filter['woocommerce_variation_is_purchasable']`.
       - Extract registered callback names / class methods / closures so developers can immediately see which third-party plugins/themes modify purchasability.
  6. **Output Response:**
     - `product_id`: integer
     - `product_name`: string
     - `product_type`: string
     - `is_purchasable`: boolean
     - `can_be_added_to_cart`: boolean
     - `health_status`: `'healthy' | 'warning' | 'critical'`
     - `issues`: Array of `{ "severity": "critical"|"warning"|"info", "code": string, "message": string }`
     - `filter_hooks`: Array of active callbacks on `woocommerce_is_purchasable`
     - `recommendations`: Array of actionable instructions.

---

### 2.5 Staging SSL Guidance

**Target File:** `/var/www/wp/wp-content/plugins/aiutoma/modules/mcp/mcp.php`

- In the MCP Server settings tab / prompt copy section:
  Add clear note and instructions:
  `Tip: In staging or local environments with self-signed SSL certificates, add "-k" or "--insecure" to your curl commands to prevent SSL handshake errors (curl error 60).`

---

## 3. Verification Plan

1. **WP-CLI on Plesk / CLI Fallback**:
   - Verify `find_php_cli_binary()` correctly discovers local PHP CLI binary (`/usr/bin/php`, Plesk paths).
   - Test execution of `aiutoma/run-wp-cli` with and without custom `php_path`.
2. **Schema Resilience**:
   - Call `mcp-adapter/discover-abilities` with `{}` and verify that `ability_missing_input_schema` does NOT trigger.
   - Query variable products and unpriced products with `woocommerce/products-query` and verify output passes schema validation without error.
3. **New WooCommerce Abilities**:
   - Test `woocommerce/bulk-update-variations` on a variable product: update regular price, stock, and custom `meta_data` (`_b2c_price`, `_b2b_price`); verify parent sync and transient invalidation.
   - Test `woocommerce/product-diagnostics` on both simple and variable products; verify detection of missing prices, stock status, and filter hooks.

