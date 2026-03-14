# Easy WhatsApp

A simple, secure, and lightweight WordPress plugin that adds a WhatsApp number meta field to selected post types and displays a modern floating WhatsApp button on the frontend.

## Features

- **Custom Meta Field**: Securely add a WhatsApp number to any supported post type.
- **Floating Button**: Automatically displays a visually appealing floating WhatsApp button on the bottom-right corner of posts/pages with a configured number.
- **Smart Device Routing**: 
  - Mobile users are directed to the native WhatsApp app.
  - Desktop users are routed directly to WhatsApp Web.
- **Configurable**: Choose exactly which post types (Posts, Pages, Custom Post Types) should have the WhatsApp field enabled via a simple settings page.
- **Secure**: Strict validation and sanitization ensure only valid phone numbers are saved to the database.

## Installation

1. Upload the `easy-whatsapp` folder to your `/wp-content/plugins/` directory.
2. Activate the plugin through the **Plugins** screen in WordPress.
3. Go to **Easy WhatsApp > Settings** to select the post types where the field should be available.

## Usage

1. Edit a post within your selected post types (e.g., a standard Post).
2. Locate the **WhatsApp Number** meta box on the side panel.
3. Enter the target WhatsApp number using the international format with the country code (e.g., `+12025550123`).
4. Update or Publish the post.
5. Visit the post on the frontend to see the floating WhatsApp button in action!

## Developer APIs

Developers can use the built-in helper function to retrieve the WhatsApp number programmatically within themes or other plugins:

```php
// Get the WhatsApp number for the current post
$number = easy_whatsapp_get_number();

// Get the WhatsApp number for a specific post ID
$number = easy_whatsapp_get_number( 123 );
```

You can also filter the allowed post types via code:

```php
add_filter( 'easy_whatsapp_post_types', function( $post_types ) {
    $post_types[] = 'my_custom_post_type';
    return $post_types;
} );
```

## Changelog

### 1.2.0
* Added a floating WhatsApp button to the frontend for desktop and mobile apps.

### 1.1.0
* Added admin settings page to choose one or multiple post types.
* Added sanitized option storage for selected post types.

### 1.0.0
* Initial release.

## License

This project is licensed under the GPL v2 or later.
