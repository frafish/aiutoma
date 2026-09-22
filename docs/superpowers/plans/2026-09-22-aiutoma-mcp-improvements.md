# AIUTOMA MCP Improvements & New WooCommerce Abilities Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Resolve Plesk WP-CLI execution failures, fix Abilities API input/output schema validation roadblocks over MCP, implement `woocommerce/bulk-update-variations` and `woocommerce/product-diagnostics`, and provide SSL staging guidance.

**Architecture:**
- `aiutoma-dev`: Provide multi-tiered PHP CLI discovery (custom path, constant, option, Plesk/cPanel paths, and `PHP_BINARY` sbin replacement) and invoke WP-CLI directly via `<php_cli> <wp_path>`.
- `aiutoma`: Inject default input schema for abilities missing input schemas via `wp_register_ability_args` and `wp_ability_validate_input`; relax price output schema for `woocommerce/products-query` to `['string', 'number', 'null']`.
- `aiutoma` WooCommerce abilities: Register `woocommerce/bulk-update-variations` with generic native and `meta_data` support; register `woocommerce/product-diagnostics` for deep purchasability, price, stock, and hook inspection.
- `aiutoma` MCP module: Document `-k / --insecure` SSL curl usage for staging environments.

**Tech Stack:** PHP 8.1+, WordPress 6.9+ Abilities API, WooCommerce, WP-CLI, JSON Schema.

## Global Constraints

- Never hardcode metadata keys (support arbitrary keys in `meta_data`).
- Maintain compatibility with WordPress Core Abilities API filters (`wp_register_ability_args`, `wp_ability_validate_input`, `wp_ability_validate_output`, `wp_pre_execute_ability`, `wp_ability_execute_result`).
- Guarantee WP-CLI execution on Plesk, cPanel, Docker, and standard Linux hosts.

---

### Task 1: Plesk / cPanel PHP CLI Resolution in `aiutoma-dev`

**Files:**
- Modify: `/var/www/wp/wp-content/plugins/aiutoma-dev/includes/developer-abilities.php:220-330`

**Interfaces:**
- Produces: `find_php_cli_binary(?string $custom_path = null): ?string` in `Developer_Abilities`.
- Extends: `aiutoma/run-wp-cli` input schema with optional `php_path` property.

- [ ] **Step 1: Write verification test for PHP CLI discovery**

Create scratch test script `/var/www/wp/wp-content/plugins/aiutoma-dev/scratch/test_php_discovery.php`:
```php
<?php
require_once __DIR__ . '/../../../../wp-load.php';
require_once __DIR__ . '/../includes/developer-abilities.php';

$detected = \AiutomaDev\Includes\Developer_Abilities::find_php_cli_binary();
echo "Detected PHP CLI: " . var_export($detected, true) . "\n";
assert(!empty($detected) && is_executable($detected), "PHP CLI binary must exist and be executable");
```

- [ ] **Step 2: Run test to verify initial state or failure**

Run: `wp eval-file wp-content/plugins/aiutoma-dev/scratch/test_php_discovery.php`
Expected: Method does not exist yet.

- [ ] **Step 3: Implement `find_php_cli_binary()` and update `register_run_wp_cli()`**

In `/var/www/wp/wp-content/plugins/aiutoma-dev/includes/developer-abilities.php`:
1. Add `public static function find_php_cli_binary(?string $custom_path = null): ?string`:
   - Check `$custom_path` if non-empty and `is_executable($custom_path)`.
   - Check defined `AIUTOMA_PHP_PATH` if non-empty and `is_executable(AIUTOMA_PHP_PATH)`.
   - Check option `get_option('aiutoma_dev_php_path')` and filter `apply_filters('aiutoma_php_binary_path', null)`.
   - Check `PHP_BINARY`: if ends with `/php`, return it; if contains `php-fpm` or `php-cgi`, check replacement `/sbin/php-fpm` -> `/bin/php`.
   - Check Plesk paths: `/opt/plesk/php/{v}/bin/php` where `{v}` is current `PHP_MAJOR_VERSION . '.' . PHP_MINOR_VERSION`, plus `8.3`, `8.2`, `8.1`, `8.0`.
   - Check cPanel paths: `/opt/cpanel/ea-php{v}/root/usr/bin/php`.
   - Check `/usr/local/bin/php`, `/usr/bin/php`, `/bin/php`.
   - Fallback to `which php 2>/dev/null`.
2. In `register_run_wp_cli()`:
   - Accept `$input['php_path']`.
   - Resolve `$php_cli = self::find_php_cli_binary($input['php_path'] ?? null);`
   - If no PHP binary is found and `$wp_path` is not natively executable, return `WP_Error('php_binary_not_found', ...)`.
   - Build `$cmd`:
     ```php
     $php_dir = $php_cli ? dirname($php_cli) : '';
     $path_prefix = $php_dir ? 'PATH=' . escapeshellarg($php_dir . ':$PATH') . ' ' : '';
     $executable = $php_cli ? escapeshellcmd($php_cli) . ' ' . escapeshellarg($wp_path) : escapeshellarg($wp_path);
     $cmd = $path_prefix . $executable . ' ' . implode(' ', $cmd_args) . ' 2>&1';
     ```
   - Add `php_path` property to `input_schema`.

- [ ] **Step 4: Run test to verify it passes**

Run: `wp eval-file wp-content/plugins/aiutoma-dev/scratch/test_php_discovery.php`
Expected: Output showing detected PHP CLI path and test assertion passes.

- [ ] **Step 5: Test WP-CLI ability execution**

Run WP evaluation executing the ability:
```php
$ability = wp_get_ability('aiutoma/run-wp-cli');
$res = $ability->execute(['command' => 'core version']);
print_r($res);
```

- [ ] **Step 6: Remove scratch script**

Remove `/var/www/wp/wp-content/plugins/aiutoma-dev/scratch/test_php_discovery.php`.

---

### Task 2: Schema Resilience for `mcp-adapter/discover-abilities` and `woocommerce/products-query`

**Files:**
- Modify: `/var/www/wp/wp-content/plugins/aiutoma/modules/ai/abilities.php`
- Modify: `/var/www/wp/wp-content/plugins/aiutoma/modules/ai/abilities/woo-commerce.php:168-235`

**Interfaces:**
- Consumes: WordPress `wp_register_ability_args`, `wp_ability_validate_input`, `wp_pre_execute_ability`, `wp_ability_execute_result`.
- Produces: Schema patches ensuring empty input schemas are handled and price fields accept `['string', 'number', 'null']`.

- [ ] **Step 1: Write test for schema handling**

Create scratch test script `/var/www/wp/wp-content/plugins/aiutoma/scratch/test_schemas.php`:
```php
<?php
require_once __DIR__ . '/../../../../wp-load.php';

// Test 1: Ability with empty input schema invoked with empty array
$discover = wp_get_ability('mcp-adapter/discover-abilities');
if ($discover) {
    $valid = $discover->validate_input([]);
    echo "mcp-adapter/discover-abilities validate_input([]): " . var_export($valid, true) . "\n";
    assert(!is_wp_error($valid), "validate_input([]) should not fail with missing input schema");
}

// Test 2: woocommerce/products-query output schema allows null/number prices
$pq = wp_get_ability('woocommerce/products-query');
if ($pq) {
    $schema = $pq->get_output_schema();
    $price_type = $schema['properties']['products']['items']['properties']['price']['type'] ?? null;
    echo "woocommerce/products-query price type: " . json_encode($price_type) . "\n";
    assert(is_array($price_type) && in_array('number', $price_type) && in_array('null', $price_type), "Price schema must allow number and null");
}
```

- [ ] **Step 2: Run test to verify current state**

Run: `wp eval-file wp-content/plugins/aiutoma/scratch/test_schemas.php`
Expected: Fails on missing input schema and price type assertion.

- [ ] **Step 3: Implement filters in `aiutoma`**

In `modules/ai/abilities.php`:
1. Add `wp_register_ability_args` filter:
   - For any ability where `empty($args['input_schema'])`, default to `['type' => 'object', 'properties' => (object)[]]`.
   - For `woocommerce/products-query` (and `woocommerce/product-read` if present): recursively or directly modify `output_schema` price properties (`price`, `regular_price`, `sale_price`) to `'type' => ['string', 'number', 'null']`.
2. Add `wp_ability_validate_input` filter:
   - If `$is_valid` is a `WP_Error` with code `ability_missing_input_schema` and `empty($input)`, return `true`.
3. In `modules/ai/abilities/woo-commerce.php`:
   - In `wp_pre_execute_ability` variable products query handler: format prices as `(string)$val` or `null` if empty, ensuring clean output.

- [ ] **Step 4: Run test to verify it passes**

Run: `wp eval-file wp-content/plugins/aiutoma/scratch/test_schemas.php`
Expected: All assertions pass.

- [ ] **Step 5: Clean up scratch test**

Remove `/var/www/wp/wp-content/plugins/aiutoma/scratch/test_schemas.php`.

---

### Task 3: Implement `woocommerce/bulk-update-variations`

**Files:**
- Modify: `/var/www/wp/wp-content/plugins/aiutoma/modules/ai/abilities/woo-commerce.php`

**Interfaces:**
- Produces: Registered ability `woocommerce/bulk-update-variations`.
- Supports: `product_id`, optional `variation_ids`, native fields (`regular_price`, `sale_price`, `stock_status`, `stock_quantity`, `manage_stock`, `status`, `virtual`, `downloadable`, `weight`, `dimensions`, `description`), and generic `meta_data`.

- [ ] **Step 1: Write test for bulk variation updates**

Create scratch test `/var/www/wp/wp-content/plugins/aiutoma/scratch/test_bulk_variations.php`:
```php
<?php
require_once __DIR__ . '/../../../../wp-load.php';

$ability = wp_get_ability('woocommerce/bulk-update-variations');
assert(!empty($ability), "woocommerce/bulk-update-variations must be registered");

// Find a variable product
$vars = wc_get_products(['type' => 'variable', 'limit' => 1]);
if (!empty($vars)) {
    $parent = $vars[0];
    $children = $parent->get_children();
    if (!empty($children)) {
        // Test update
        $result = $ability->execute([
            'product_id' => $parent->get_id(),
            'stock_status' => 'instock',
            'meta_data' => ['_test_custom_meta' => 'hello_mcp']
        ]);
        assert($result['success'] === true, "Execution should succeed");
        assert($result['total_updated'] === count($children), "Should update all children");
        
        // Verify meta on first child
        $v1 = wc_get_product($children[0]);
        assert($v1->get_meta('_test_custom_meta') === 'hello_mcp', "Custom metadata should be set");
        $v1->delete_meta_data('_test_custom_meta');
        $v1->save();
        echo "Bulk update test passed on product {$parent->get_id()}!\n";
    }
}
```

- [ ] **Step 2: Run test to verify failure (ability not registered yet)**

Run: `wp eval-file wp-content/plugins/aiutoma/scratch/test_bulk_variations.php`
Expected: FAIL ("woocommerce/bulk-update-variations must be registered").

- [ ] **Step 3: Implement `woocommerce/bulk-update-variations` in `woo-commerce.php`**

Register the ability with complete input schema, permissions (`current_user_can('manage_woocommerce')`), and execute callback implementing:
1. Validation of variable parent product.
2. Retrieval and filtering of child variation IDs.
3. Looping over variations and applying native parameters and generic `meta_data` (`$variation->update_meta_data($key, $value)`).
4. Save each variation.
5. Synchronize parent: `\WC_Product_Variable::sync($parent_id)`.
6. Clear transients: `wc_delete_product_transients($parent_id)`.
7. Return structured report.

- [ ] **Step 4: Run test to verify it passes**

Run: `wp eval-file wp-content/plugins/aiutoma/scratch/test_bulk_variations.php`
Expected: PASS.

- [ ] **Step 5: Clean up scratch test**

Remove `/var/www/wp/wp-content/plugins/aiutoma/scratch/test_bulk_variations.php`.

---

### Task 4: Implement `woocommerce/product-diagnostics`

**Files:**
- Modify: `/var/www/wp/wp-content/plugins/aiutoma/modules/ai/abilities/woo-commerce.php`

**Interfaces:**
- Produces: Registered ability `woocommerce/product-diagnostics`.
- Output: Structured diagnostics object with catalog info, price analysis, stock analysis, purchasability and cart blocker reasons, and active `is_purchasable` filter hooks.

- [ ] **Step 1: Write test for product diagnostics**

Create scratch test `/var/www/wp/wp-content/plugins/aiutoma/scratch/test_product_diagnostics.php`:
```php
<?php
require_once __DIR__ . '/../../../../wp-load.php';

$ability = wp_get_ability('woocommerce/product-diagnostics');
assert(!empty($ability), "woocommerce/product-diagnostics must be registered");

// Test on any existing product
$prods = wc_get_products(['limit' => 1]);
if (!empty($prods)) {
    $p = $prods[0];
    $diag = $ability->execute(['product_id' => $p->get_id()]);
    assert(isset($diag['product_id']), "Must contain product_id");
    assert(isset($diag['is_purchasable']), "Must contain is_purchasable");
    assert(isset($diag['can_be_added_to_cart']), "Must contain can_be_added_to_cart");
    assert(isset($diag['health_status']), "Must contain health_status");
    assert(is_array($diag['issues']), "Must contain issues array");
    echo "Product diagnostics test passed for product {$p->get_id()}!\n";
}
```

- [ ] **Step 2: Run test to verify failure**

Run: `wp eval-file wp-content/plugins/aiutoma/scratch/test_product_diagnostics.php`
Expected: FAIL ("woocommerce/product-diagnostics must be registered").

- [ ] **Step 3: Implement `woocommerce/product-diagnostics` in `woo-commerce.php`**

Register the ability with complete input/output schemas and execute callback implementing:
1. Product lookup by `product_id` or `sku`.
2. General info extraction (type, status, visibility).
3. Price evaluation (simple vs variable, unpriced variations detection).
4. Stock evaluation (status, manage stock, quantity, backorders).
5. Purchasability diagnosis:
   - Evaluate `$product->is_purchasable()`.
   - Identify failure causes (draft status, empty price, out of stock, unpriced variations).
   - Inspect `$wp_filter['woocommerce_is_purchasable']` and `$wp_filter['woocommerce_variation_is_purchasable']` for registered callbacks (class name, function name).
6. Return structured diagnostic response with health status, issues, recommendations.

- [ ] **Step 4: Run test to verify it passes**

Run: `wp eval-file wp-content/plugins/aiutoma/scratch/test_product_diagnostics.php`
Expected: PASS.

- [ ] **Step 5: Clean up scratch test**

Remove `/var/www/wp/wp-content/plugins/aiutoma/scratch/test_product_diagnostics.php`.

---

### Task 5: Staging SSL Guidance in `aiutoma` MCP Server Settings

**Files:**
- Modify: `/var/www/wp/wp-content/plugins/aiutoma/modules/mcp/mcp.php:1260-1290`

- [ ] **Step 1: Update prompt instructions in `mcp.php`**

In `modules/mcp/mcp.php`, update the instruction block rendered for LLM prompt and REST documentation to include:
`If connecting to a staging or local site with self-signed SSL certificates, add the -k or --insecure flag to your curl commands.`

- [ ] **Step 2: Verify markup rendering**

Check that the instructions load without syntax or template errors.

---

### Task 6: End-to-End Verification & Commit

- [ ] **Step 1: Verify all modified files with `php -l`**

Run: `php -l /var/www/wp/wp-content/plugins/aiutoma-dev/includes/developer-abilities.php`
Run: `php -l /var/www/wp/wp-content/plugins/aiutoma/modules/ai/abilities.php`
Run: `php -l /var/www/wp/wp-content/plugins/aiutoma/modules/ai/abilities/woo-commerce.php`
Run: `php -l /var/www/wp/wp-content/plugins/aiutoma/modules/mcp/mcp.php`

- [ ] **Step 2: Verify MCP tools registry**

Run WP-CLI command or script to ensure all abilities appear in `wp_get_abilities()`:
`aiutoma/run-wp-cli`, `woocommerce/bulk-update-variations`, `woocommerce/product-diagnostics`.

- [ ] **Step 3: Commit all changes in both repositories**

In `aiutoma`: commit changes with informative commit message.
In `aiutoma-dev`: commit changes with informative commit message.

