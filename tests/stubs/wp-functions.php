<?php
/**
 * WordPress function stubs for unit tests without WordPress.
 *
 * Defined in the GLOBAL namespace so that namespaced plugin code
 * (BricksMCP\MCP\*, BricksMCP\Admin\*, etc.) can find them via
 * PHP's namespace fallback resolution.
 *
 * @package BricksMCP
 * @license GPL-2.0-or-later
 */

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedClassFound

// ---------------------------------------------------------------------------
// Controllable globals — tests set these in setUp() to drive stub behavior.
// ---------------------------------------------------------------------------
$GLOBALS['_bricks_mcp_test_cache']            = [];
$GLOBALS['_bricks_mcp_test_settings']         = [];
$GLOBALS['_bricks_mcp_test_ext_object_cache'] = true;
$GLOBALS['_bricks_mcp_test_transients']       = [];

// ---------------------------------------------------------------------------
// WP_Error class.
// ---------------------------------------------------------------------------
if ( ! class_exists( 'WP_Error' ) ) {
	/**
	 * Minimal WP_Error stub.
	 */
	class WP_Error {
		/** @var string */
		public string $code;
		/** @var string */
		public string $message;
		/** @var mixed */
		public mixed $data;

		/**
		 * @param string $code    Error code.
		 * @param string $message Error message.
		 * @param mixed  $data    Additional data.
		 */
		public function __construct( string $code = '', string $message = '', mixed $data = '' ) {
			$this->code    = $code;
			$this->message = $message;
			$this->data    = $data;
		}

		public function get_error_code(): string {
			return $this->code;
		}

		public function get_error_data( string $code = '' ): mixed {
			return $this->data;
		}
	}
}

// ---------------------------------------------------------------------------
// Core WordPress functions.
// ---------------------------------------------------------------------------
if ( ! function_exists( 'get_option' ) ) {
	function get_option( string $option, mixed $default = false ): mixed {
		if ( 'bricks_mcp_settings' === $option ) {
			return $GLOBALS['_bricks_mcp_test_settings'] ?? $default;
		}
		return $default;
	}
}

if ( ! function_exists( 'update_option' ) ) {
	function update_option( string $option, mixed $value ): bool {
		if ( 'bricks_mcp_settings' === $option ) {
			$GLOBALS['_bricks_mcp_test_settings'] = $value;
		}
		return true;
	}
}

// ---------------------------------------------------------------------------
// Object cache functions.
// ---------------------------------------------------------------------------
if ( ! function_exists( 'wp_cache_add' ) ) {
	function wp_cache_add( string $key, mixed $data, string $group = '', int $expire = 0 ): bool {
		$full_key = $group . ':' . $key;
		if ( array_key_exists( $full_key, $GLOBALS['_bricks_mcp_test_cache'] ) ) {
			return false;
		}
		$GLOBALS['_bricks_mcp_test_cache'][ $full_key ] = $data;
		return true;
	}
}

if ( ! function_exists( 'wp_cache_incr' ) ) {
	function wp_cache_incr( string $key, int $offset = 1, string $group = '' ): int|false {
		$full_key = $group . ':' . $key;
		if ( ! array_key_exists( $full_key, $GLOBALS['_bricks_mcp_test_cache'] ) ) {
			return false;
		}
		$GLOBALS['_bricks_mcp_test_cache'][ $full_key ] += $offset;
		return $GLOBALS['_bricks_mcp_test_cache'][ $full_key ];
	}
}

if ( ! function_exists( 'wp_cache_flush' ) ) {
	function wp_cache_flush(): bool {
		$GLOBALS['_bricks_mcp_test_cache'] = [];
		return true;
	}
}

if ( ! function_exists( 'wp_using_ext_object_cache' ) ) {
	function wp_using_ext_object_cache( ?bool $using = null ): bool {
		if ( null !== $using ) {
			$GLOBALS['_bricks_mcp_test_ext_object_cache'] = $using;
		}
		return (bool) ( $GLOBALS['_bricks_mcp_test_ext_object_cache'] ?? false );
	}
}

// ---------------------------------------------------------------------------
// Transient functions.
// ---------------------------------------------------------------------------
if ( ! function_exists( 'get_transient' ) ) {
	function get_transient( string $transient ): mixed {
		return $GLOBALS['_bricks_mcp_test_transients'][ $transient ] ?? false;
	}
}

if ( ! function_exists( 'set_transient' ) ) {
	function set_transient( string $transient, mixed $value, int $expiration = 0 ): bool {
		$GLOBALS['_bricks_mcp_test_transients'][ $transient ] = $value;
		return true;
	}
}

if ( ! function_exists( 'delete_transient' ) ) {
	function delete_transient( string $transient ): bool {
		unset( $GLOBALS['_bricks_mcp_test_transients'][ $transient ] );
		return true;
	}
}

// ---------------------------------------------------------------------------
// Error / sanitization / i18n helpers.
// ---------------------------------------------------------------------------
if ( ! function_exists( 'is_wp_error' ) ) {
	function is_wp_error( mixed $thing ): bool {
		return $thing instanceof \WP_Error;
	}
}

if ( ! function_exists( '__' ) ) {
	function __( string $text, string $domain = 'default' ): string {
		return $text;
	}
}

if ( ! function_exists( 'esc_html' ) ) {
	function esc_html( string $text ): string {
		return $text;
	}
}

if ( ! function_exists( 'apply_filters' ) ) {
	function apply_filters( string $tag, mixed $value, mixed ...$args ): mixed {
		return $value;
	}
}

// phpcs:enable
