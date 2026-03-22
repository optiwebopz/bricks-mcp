<?php
/**
 * MCP Server implementation.
 *
 * @package BricksMCP
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace BricksMCP\MCP;

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Server class.
 *
 * Main MCP (Model Context Protocol) server implementation.
 * Registers the single /mcp endpoint using the Streamable HTTP transport.
 */
final class Server {

	/**
	 * REST API namespace.
	 *
	 * @var string
	 */
	public const API_NAMESPACE = 'bricks-mcp/v1';

	/**
	 * Router instance.
	 *
	 * @var Router
	 */
	private Router $router;

	/**
	 * Streamable HTTP handler instance.
	 *
	 * @var StreamableHttpHandler
	 */
	private StreamableHttpHandler $handler;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->router  = new Router();
		$this->handler = new StreamableHttpHandler( $this->router );
	}

	/**
	 * Initialize the MCP server.
	 *
	 * @return void
	 */
	public function init(): void {
		add_action( 'rest_api_init', [ $this, 'register_routes' ] );
		add_filter( 'rest_pre_serve_request', [ $this, 'add_cors_headers' ], 10, 4 );
		add_filter( 'rest_request_before_callbacks', [ $this, 'intercept_json_parse_error' ], 10, 3 );
	}

	/**
	 * Register REST API routes.
	 *
	 * Registers the single /mcp endpoint supporting POST, GET, and DELETE
	 * per the MCP Streamable HTTP transport specification.
	 *
	 * @return void
	 */
	public function register_routes(): void {
		register_rest_route(
			self::API_NAMESPACE,
			'/mcp',
			[
				[
					'methods'             => 'POST',
					'callback'            => [ $this->handler, 'handle_post' ],
					'permission_callback' => [ $this, 'check_permissions' ],
				],
				[
					'methods'             => 'GET',
					'callback'            => [ $this->handler, 'handle_get' ],
					'permission_callback' => [ $this, 'check_permissions' ],
				],
				[
					'methods'             => 'DELETE',
					'callback'            => [ $this->handler, 'handle_delete' ],
					'permission_callback' => [ $this, 'check_permissions' ],
				],
			]
		);
	}

	/**
	 * Intercept WordPress JSON parse errors for the /mcp route.
	 *
	 * WordPress validates the JSON body via has_valid_params() before calling our callback.
	 * When the body is not valid JSON, it returns rest_invalid_json WP_Error.
	 * We intercept this for our /mcp POST route and emit a proper JSON-RPC parse error SSE event.
	 *
	 * @param mixed            $response Current response (WP_Error or null).
	 * @param array            $handler  The matched route handler.
	 * @param \WP_REST_Request $request  The REST request.
	 * @return mixed The response (unchanged), or WP_REST_Response if we handle it.
	 */
	public function intercept_json_parse_error( mixed $response, array $handler, \WP_REST_Request $request ): mixed {
		// Only intercept JSON parse errors on our /mcp POST route.
		if ( ! is_wp_error( $response ) ) {
			return $response;
		}

		if ( 'rest_invalid_json' !== $response->get_error_code() ) {
			return $response;
		}

		$route = $request->get_route();
		if ( ! str_starts_with( $route, '/' . self::API_NAMESPACE . '/mcp' ) ) {
			return $response;
		}

		if ( 'POST' !== $request->get_method() ) {
			return $response;
		}

		// Emit SSE parse error and exit — we handle it directly.
		$this->handler->emit_parse_error_and_exit();

		// Unreachable, but satisfies return type.
		return $response;
	}

	/**
	 * Add CORS headers for bricks-mcp namespace routes.
	 *
	 * Scoped to the bricks-mcp/v1 namespace only to avoid affecting other REST routes.
	 *
	 * @param bool              $served  Whether the request has already been served.
	 * @param \WP_REST_Response $result  The response object.
	 * @param \WP_REST_Request  $request The request object.
	 * @param \WP_REST_Server   $server  The REST server instance.
	 * @return bool Whether the request has been served.
	 */
	public function add_cors_headers( bool $served, \WP_REST_Response $result, \WP_REST_Request $request, \WP_REST_Server $server ): bool { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed
		// Only add CORS headers for our namespace.
		$route = $request->get_route();
		if ( ! str_starts_with( $route, '/' . self::API_NAMESPACE ) ) {
			return $served;
		}

		header( 'Access-Control-Allow-Origin: *' );
		header( 'Access-Control-Allow-Methods: GET, POST, DELETE, OPTIONS' );
		header( 'Access-Control-Allow-Headers: Authorization, Content-Type, Accept' );
		header( 'Access-Control-Max-Age: 86400' );

		return $served;
	}

	/**
	 * Check request permissions.
	 *
	 * @param \WP_REST_Request $request The REST request.
	 * @return bool|\WP_Error True if allowed, WP_Error otherwise.
	 */
	public function check_permissions( \WP_REST_Request $request ): bool|\WP_Error { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found
		$settings = get_option( 'bricks_mcp_settings', [] );

		// Check if plugin is enabled.
		if ( empty( $settings['enabled'] ) ) {
			return new \WP_Error(
				'bricks_mcp_disabled',
				__( 'The Bricks MCP server is currently disabled.', 'bricks-mcp' ),
				[ 'status' => 503 ]
			);
		}

		// Check if authentication is required.
		if ( ! empty( $settings['require_auth'] ) ) {
			if ( ! is_user_logged_in() ) {
				return new \WP_Error(
					'bricks_mcp_unauthorized',
					__( 'Authentication is required to access the MCP server.', 'bricks-mcp' ),
					[ 'status' => 401 ]
				);
			}

			// Check user capabilities.
			if ( ! current_user_can( 'manage_options' ) ) {
				return new \WP_Error(
					'bricks_mcp_forbidden',
					__( 'You do not have permission to access the MCP server.', 'bricks-mcp' ),
					[ 'status' => 403 ]
				);
			}
		}

		return true;
	}

	/**
	 * Get the router instance.
	 *
	 * @return Router The router instance.
	 */
	public function get_router(): Router {
		return $this->router;
	}

	/**
	 * Get the API namespace.
	 *
	 * @return string The API namespace.
	 */
	public function get_namespace(): string {
		return self::API_NAMESPACE;
	}
}
