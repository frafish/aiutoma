---
name: wp-content-management
description: "Create, update, and manage WordPress editorial content: posts, pages, media attachments, categories, tags, and navigation menus via WP-CLI or the WordPress REST API. Use for bulk publishing, scheduling posts, importing media, managing terms, and updating content."
compatibility: "WordPress 6.0+ (PHP 7.4+ / PHP 8.1+)."
---

# WordPress Content Management & Publishing

## When to use

Use this skill when:
- creating or updating blog posts, landing pages, and custom post types
- scheduling posts or publishing drafts in bulk
- importing images into the WordPress Media Library and setting featured images
- managing taxonomies (creating categories, parent-child hierarchies, tags)
- creating, updating, or reordering items in WordPress navigation menus
- running batch content operations or search-and-replace on editorial text

---

## 1. Creating Posts & Pages

### Single Post Creation (WP-CLI)
```bash
# Basic draft post
wp post create \
  --post_type=post \
  --post_title="10 Tips for Autonomous WordPress Management" \
  --post_content="<p>Discover how AI co-pilots streamline operations.</p>" \
  --post_status=draft \
  --post_category=3,5

# Creating post from a clean HTML file (recommended for rich/long content)
wp post create ./article.html \
  --post_type=post \
  --post_title="Comprehensive Guide" \
  --post_status=publish
```

### Scheduled Posts
```bash
# Schedule for a specific future date/time
wp post create \
  --post_type=post \
  --post_title="Upcoming Announcement" \
  --post_date="2026-10-15 09:00:00" \
  --post_status=future
```

### In PHP:
```php
$post_id = wp_insert_post( [
    'post_title'    => 'New Article',
    'post_content'  => '<p>Article body content...</p>',
    'post_status'   => 'publish',
    'post_author'   => get_current_user_id(),
    'post_category' => [ 2, 4 ],
    'tags_input'    => [ 'AI', 'WordPress' ],
] );
```

---

## 2. Media Library Management

### Importing Images & Setting Featured Images
```bash
# Import local image into media library
wp media import /path/to/image.jpg --title="Dashboard Preview"

# Import image from remote URL and assign as featured image in one command
wp media import "https://example.com/cover.jpg" \
  --post_id=123 \
  --featured_image \
  --title="Article Hero Image"

# Regenerate thumbnails after theme change
wp media regenerate --yes
```

### In PHP:
```php
// Set existing attachment as featured image for post
set_post_thumbnail( $post_id, $attachment_id );
```

---

## 3. Taxonomies (Categories & Tags)

```bash
# List categories with IDs and post counts
wp term list category --fields=term_id,name,slug,count

# Create parent and child categories
PARENT_ID=$(wp term create category "Technology" --slug=technology --porcelain)
wp term create category "Artificial Intelligence" --slug=ai --parent=$PARENT_ID

# Assign categories to a post
wp post term add 123 category technology ai

# Add tags
wp post term add 123 post_tag automation agents
```

---

## 4. Navigation Menus

```bash
# List existing menus
wp menu list

# Add a page to the main navigation menu
wp menu item add-post main-menu 123 --title="Our Solutions"

# Add a custom external link to menu
wp menu item add-custom main-menu "Documentation" "https://docs.example.com"

# Add a category archive to menu
wp menu item add-term main-menu category 5

# Reorder menu item (set position index)
wp menu item update 45 --position=2
```

---

## 5. Bulk Content Operations

### Bulk Publishing Drafts
```bash
# Find all drafts of post_type 'post' and publish them
wp post list --post_type=post --post_status=draft --field=ID | \
  xargs -I {} wp post update {} --post_status=publish
```

### Bulk Import from CSV
```bash
while IFS=, read -r title slug content_file category; do
  wp post create "$content_file" \
    --post_type=post \
    --post_title="$title" \
    --post_name="$slug" \
    --post_category="$category" \
    --post_status=draft
  sleep 0.2
done < articles.csv
```

---

## 6. Content Operations via WordPress REST API

When executing operations remotely or via HTTP clients:

```bash
# Create post with Application Password authentication
AUTH=$(echo -n "username:xxxx xxxx xxxx xxxx" | base64)

curl -s -X POST https://example.com/wp-json/wp/v2/posts \
  -H "Authorization: Basic $AUTH" \
  -H "Content-Type: application/json" \
  -d '{
    "title": "Published via REST API",
    "content": "<p>Content created programmatically.</p>",
    "status": "publish",
    "categories": [2],
    "tags": [5, 8]
  }'
```

