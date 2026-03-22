# Bricks MCP

AI-powered assistant for [Bricks Builder](https://bricksbuilder.io/). Control your website with natural language through MCP-compatible AI tools like Claude.

**Talk to your website. It listens.**

## What It Does

Bricks MCP is a WordPress plugin that implements an [MCP (Model Context Protocol)](https://modelcontextprotocol.io/) server, letting AI assistants read and write your Bricks Builder site. Connect Claude, Gemini, or any MCP-compatible client and manage pages, templates, global classes, theme styles, WooCommerce layouts, and more — all through natural language.

## Features

- 20+ MCP tools covering the full Bricks Builder data model
- Read and write pages, templates, elements, and global settings
- WooCommerce support (product pages, cart, checkout, account templates)
- Global classes, theme styles, typography scales, color palettes, variables
- Media library management with Unsplash integration
- WordPress menus, fonts, and custom code management
- Built-in connection tester and config snippet generator
- Works with Claude Code, Claude Desktop, Gemini, and any MCP client

## Requirements

- WordPress 6.4+
- PHP 8.2+
- Bricks Builder 1.6+

## Installation

1. Download the latest release from [GitHub Releases](https://github.com/buffupmedia/bricks-mcp/releases)
2. Upload to your WordPress site via Plugins > Add New > Upload Plugin
3. Activate the plugin
4. Go to Settings > Bricks MCP to configure

## Connecting Your AI Tool

### Claude Code

```bash
claude mcp add --transport http bricks-mcp https://yoursite.com/wp-json/bricks-mcp/v1/mcp
```

### Claude Desktop / Other MCP Clients

Add to your MCP config (`.mcp.json` or equivalent):

```json
{
  "mcpServers": {
    "bricks-mcp": {
      "type": "http",
      "url": "https://yoursite.com/wp-json/bricks-mcp/v1/mcp",
      "headers": {
        "Authorization": "Basic BASE64_ENCODED_CREDENTIALS"
      }
    }
  }
}
```

Authentication uses WordPress [Application Passwords](https://make.wordpress.org/core/2020/11/05/application-passwords-integration-guide/) (Users > Profile > Application Passwords).

## Available Tools

| Tool | Description |
|------|-------------|
| `get_site_info` | WordPress site information and configuration |
| `wordpress` | Manage posts, pages, users, plugins, comments, taxonomies |
| `get_builder_guide` | Bricks Builder documentation for AI context |
| `bricks` | Read/write Bricks settings, query loops, interactions |
| `page` | Create, read, update, delete Bricks pages and elements |
| `element` | Fine-grained element operations (add, move, style, clone) |
| `template` | Manage Bricks templates (header, footer, section, etc.) |
| `template_condition` | Set display conditions on templates |
| `template_taxonomy` | Manage template taxonomy terms |
| `global_class` | Create and manage global CSS classes |
| `theme_style` | Create and manage theme styles |
| `typography_scale` | Typography scale presets |
| `color_palette` | Color palette management |
| `global_variable` | Global CSS variables |
| `media` | Upload media, search Unsplash, manage library |
| `menu` | WordPress menu management |
| `component` | Bricks component (reusable element) management |
| `woocommerce` | WooCommerce page templates and product layouts |
| `font` | Custom font management |
| `code` | Page-level CSS and JavaScript |

## Configuration

Go to **Settings > Bricks MCP** in WordPress admin:

- **Enable MCP Server** — toggle the server on/off
- **Require Authentication** — restrict access to authenticated users
- **Custom Base URL** — for reverse proxies or custom domains
- **Dangerous Actions** — enable write access to global Bricks settings and code execution

## Extending

Add custom tools using the `bricks_mcp_tools` filter:

```php
add_filter( 'bricks_mcp_tools', function( $tools ) {
    $tools['my_custom_tool'] = [
        'name'        => 'my_custom_tool',
        'description' => 'My custom tool description',
        'inputSchema' => [
            'type'       => 'object',
            'properties' => [],
        ],
        'handler'     => function( $args ) {
            return ['result' => 'success'];
        },
    ];
    return $tools;
});
```

## Local Development

```bash
npm install
npm run start        # Start WordPress (Docker via wp-env)
npm run test         # Run PHPUnit tests
npm run lint         # WordPress coding standards check
npm run lint:fix     # Auto-fix linting issues
```

Local site: http://localhost:8888 (admin / password)

## License

GPL-2.0-or-later
