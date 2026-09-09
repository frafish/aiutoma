# AI Request Logs Dashboard - Source & Build Instructions

This directory contains the dashboard assets for AI Request Logs.

## Source Information
- **Origin Project:** Official WordPress AI Plugin ([WordPress/ai](https://github.com/WordPress/ai))
- **Repository:** [https://github.com/WordPress/ai](https://github.com/WordPress/ai)
- **Source Code Location:** [https://github.com/WordPress/ai/tree/trunk/src/admin/ai-request-logs](https://github.com/WordPress/ai/tree/trunk/src/admin/ai-request-logs)
- **Uncompiled Source Code:** Included in `./src/`
- **License:** GPL-2.0-or-later ([License Details](https://github.com/WordPress/ai/blob/trunk/LICENSE.md))
- **Copyright:** WordPress.org Contributors & Plugin Contributors

## Build Instructions
The bundled JavaScript and CSS (`ai-request-logs.js`, `ai-request-logs.css`) are compiled from the TypeScript/SCSS sources in `./src/` using `@wordpress/scripts`.

To rebuild the assets from source:
1. Ensure Node.js (v18+) and npm are installed.
2. In the `WordPress/ai` repository or a package using `@wordpress/scripts`:
   ```bash
   npm install @wordpress/scripts @wordpress/components @wordpress/element @wordpress/data @wordpress/i18n @wordpress/api-fetch
   npx wp-scripts build src/admin/ai-request-logs/index.tsx --output-path=build-scripts/admin
   ```
3. The resulting bundle outputs `ai-request-logs.js`, `ai-request-logs.css`, and `ai-request-logs.asset.php`.
