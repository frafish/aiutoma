# WordPress Design System (WPDS) & Theme Token Integration

Use this reference when designing block themes, block controls, and editor UI aligned with the official WordPress Design System (WPDS).

---

## 1. Design Tokens Architecture

The WordPress Design System (WPDS) standardizes design decisions into three layers:

```
Global Primitives (raw colors/scales)
        ↓
Semantic Tokens (surface, text, border, status)
        ↓
Component & Theme Presets (theme.json: var(--wp--preset--*))
```

### Spacing Scale
WPDS defines a fluid, step-based spacing scale rather than arbitrary pixel margins:

| Token / Preset Step | Relative Value | Typical Use |
|---|---|---|
| `spacing|20` | ~0.5rem (8px) | Tight inner gap, badge padding |
| `spacing|30` | ~0.75rem (12px) | Form input padding, card internal spacing |
| `spacing|40` | ~1.0rem (16px) | Standard block spacing / base grid step |
| `spacing|50` | ~1.5rem (24px) | Section gap, card padding |
| `spacing|60` | ~2.0rem (32px) | Sub-section vertical margins |
| `spacing|70` | ~3.0rem (48px) | Medium section padding |
| `spacing|80` | ~4.0rem (64px) | Hero section vertical padding |

---

## 2. Theme.json Token Integration

In block themes, `theme.json` acts as the source of truth for WPDS presets:

```json
{
    "$schema": "https://schemas.wp.org/trunk/theme.json",
    "version": 3,
    "settings": {
        "color": {
            "palette": [
                { "slug": "base", "color": "#ffffff", "name": "Base / Surface" },
                { "slug": "contrast", "color": "#111827", "name": "Contrast / Text" },
                { "slug": "primary", "color": "#2563eb", "name": "Brand Primary" },
                { "slug": "secondary", "color": "#64748b", "name": "Muted Text" },
                { "slug": "tertiary", "color": "#f1f5f9", "name": "Subtle Background" }
            ]
        },
        "spacing": {
            "units": [ "px", "em", "rem", "%", "vh", "vw" ],
            "spacingScale": {
                "operator": "*",
                "increment": 1.5,
                "steps": 7,
                "mediumStep": 1.5,
                "unit": "rem"
            }
        },
        "typography": {
            "fluid": true,
            "fontFamilies": [
                {
                    "fontFamily": "-apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif",
                    "slug": "system",
                    "name": "System Sans"
                }
            ]
        }
    }
}
```

---

## 3. Using WPDS UI Components (`@wordpress/components`)

When extending block inspector controls or theme settings screens:

### Standard Rules:
1. Always import primitives from `@wordpress/components` (e.g. `Button`, `TextControl`, `SelectControl`, `ToggleControl`, `PanelBody`, `Card`).
2. Use WPDS CSS classes and utility variables (`var(--wp-admin-theme-color)`) rather than custom hardcoded styles.
3. Keep spacing aligned to the 8px baseline grid.

```jsx
import { PanelBody, ToggleControl, SelectControl } from '@wordpress/components';
import { InspectorControls } from '@wordpress/block-editor';

export function EditControls( { attributes, setAttributes } ) {
    return (
        <InspectorControls>
            <PanelBody title={ __( 'Display Settings', 'my-theme' ) }>
                <ToggleControl
                    label={ __( 'Full Width Layout', 'my-theme' ) }
                    checked={ attributes.isFullWidth }
                    onChange={ ( val ) => setAttributes( { isFullWidth: val } ) }
                />
            </PanelBody>
        </InspectorControls>
    );
}
```

