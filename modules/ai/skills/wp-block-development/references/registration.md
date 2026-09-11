# Registration patterns (PHP-first)

Use this file when you need to register blocks robustly across repo types (plugin/theme/site).

## Prefer metadata registration

Prefer:

- `register_block_type_from_metadata( $path_to_block_dir, $args = [] )`

Why:

- keeps metadata authoritative (`block.json`)
- supports dynamic render (`render`) and other metadata-driven fields
- enables cleaner asset handling

Upstream reference:

- https://developer.wordpress.org/reference/functions/register_block_type_from_metadata/

## Where to register

- Plugins: register on `init` in the main plugin bootstrap or a dedicated loader.
- Themes: register on `init` (or `after_setup_theme` if you need theme supports first), but keep it predictable.

## Dynamic render mapping

If `block.json` includes `render`, ensure the file exists relative to the block root.
Inside the render file, use `get_block_wrapper_attributes()` for wrapper attributes.

---

## Appendix: Block Styles & Block Variations API

### 1. Registering Block Styles (PHP & JS)
Block styles allow editors to switch visual presentations for any block (e.g. "Outline", "Gradient").

**PHP Registration (Preferred for server-side reliability):**
```php
add_action( 'init', function() {
    register_block_style( 'core/button', [
        'name'         => 'flat-minimal',
        'label'        => esc_html__( 'Flat Minimal', 'my-textdomain' ),
        'inline_style' => '.is-style-flat-minimal { border: none; box-shadow: none; }',
    ] );
} );
```

**JavaScript Registration:**
```javascript
import { registerBlockStyle } from '@wordpress/blocks';

wp.domReady( () => {
    registerBlockStyle( 'core/quote', {
        name: 'modern-callout',
        label: 'Modern Callout',
    } );
} );
```

### 2. Registering Block Variations
Variations allow creating specialized instances of a block with pre-configured attributes (e.g. Media & Text with image on the right).

**In `block.json`:**
```json
{
    "variations": [
        {
            "name": "hero-full",
            "title": "Full-Width Hero",
            "description": "Pre-configured full-width hero cover block.",
            "attributes": { "align": "full", "minHeight": 500 },
            "scope": [ "inserter", "transform" ],
            "isDefault": false
        }
    ]
}
```

**In JavaScript:**
```javascript
import { registerBlockVariation } from '@wordpress/blocks';

registerBlockVariation( 'core/embed', {
    name: 'custom-provider',
    title: 'Custom Provider Video',
    attributes: { providerNameSlug: 'custom-provider', responsive: true },
    isActive: [ 'providerNameSlug' ],
} );
```

