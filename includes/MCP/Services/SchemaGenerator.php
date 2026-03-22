<?php
/**
 * Bricks element schema generator service.
 *
 * @package BricksMCP
 */

declare(strict_types=1);

namespace BricksMCP\MCP\Services;

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * SchemaGenerator class.
 *
 * Converts Bricks element registry controls into JSON Schema format.
 * Caches results via WordPress transients with Bricks version in cache key.
 */
class SchemaGenerator {

	/**
	 * Cache key prefix for schema transients.
	 *
	 * Bricks version is appended to this prefix for cache invalidation.
	 *
	 * @var string
	 */
	private const CACHE_KEY_PREFIX = 'bricks_mcp_schemas_v';

	/**
	 * Cache duration in seconds.
	 *
	 * @var int
	 */
	private const CACHE_DURATION = DAY_IN_SECONDS;

	/**
	 * Get all element schemas from Bricks registry.
	 *
	 * Returns the full catalog of all registered element types with their
	 * JSON Schema definitions and minimal working examples.
	 * Results are cached via transients using the Bricks version as cache key.
	 *
	 * @return array<string, array<string, mixed>> Map of element name => schema data.
	 */
	public function get_all_schemas(): array {
		if ( ! class_exists( '\Bricks\Elements' ) ) {
			return [];
		}

		$cache_key = self::CACHE_KEY_PREFIX . $this->get_bricks_version();
		$cached    = get_transient( $cache_key );

		if ( false !== $cached && is_array( $cached ) ) {
			return $cached;
		}

		$schemas = [];

		// Bricks\Elements::$elements is the registered element registry.
		if ( ! isset( \Bricks\Elements::$elements ) || ! is_array( \Bricks\Elements::$elements ) ) {
			return [];
		}

		foreach ( \Bricks\Elements::$elements as $element_name => $element_entry ) {
			// Bricks stores elements as arrays with 'class' key or as class strings/objects.
			$element_class = is_array( $element_entry ) ? ( $element_entry['class'] ?? null ) : $element_entry;

			if ( null === $element_class ) {
				continue;
			}

			$element_obj = $this->get_element_object( $element_class );

			if ( null === $element_obj ) {
				continue;
			}

			$schemas[ $element_name ] = [
				'name'            => $element_name,
				'label'           => $this->get_element_label( $element_obj ),
				'category'        => $this->get_element_category( $element_obj ),
				'settings_schema' => $this->convert_to_json_schema( $element_obj ),
				'working_example' => $this->generate_working_example( $element_name, $element_obj ),
			];
		}

		set_transient( $cache_key, $schemas, self::CACHE_DURATION );

		return $schemas;
	}

	/**
	 * Get schema for a single element type.
	 *
	 * @param string $element_name The element type name (e.g., 'heading', 'section').
	 * @return array<string, mixed>|\WP_Error Schema data or WP_Error if not found.
	 */
	public function get_element_schema( string $element_name ): array|\WP_Error {
		$all_schemas = $this->get_all_schemas();

		if ( empty( $all_schemas ) && ! class_exists( '\Bricks\Elements' ) ) {
			return new \WP_Error(
				'bricks_not_active',
				__( 'Bricks Builder must be installed and active to retrieve element schemas.', 'bricks-mcp' )
			);
		}

		if ( ! isset( $all_schemas[ $element_name ] ) ) {
			// Suggest similar element names.
			$similar = $this->find_similar_element_names( $element_name, array_keys( $all_schemas ) );

			return new \WP_Error(
				'element_not_found',
				sprintf(
					/* translators: %s: Element type name */
					__( 'Element type "%s" not found in Bricks element registry.', 'bricks-mcp' ),
					$element_name
				),
				[
					'element_type' => $element_name,
					'suggestions'  => $similar,
				]
			);
		}

		return $all_schemas[ $element_name ];
	}

	/**
	 * Get simplified element catalog without full schemas.
	 *
	 * Returns only element name, label, and category for quick reference.
	 *
	 * @return array<int, array<string, string>> List of elements with name, label, category.
	 */
	public function get_element_catalog(): array {
		$all_schemas = $this->get_all_schemas();
		$catalog     = [];

		foreach ( $all_schemas as $element_name => $schema ) {
			$catalog[] = [
				'name'     => $element_name,
				'label'    => $schema['label'] ?? $element_name,
				'category' => $schema['category'] ?? 'general',
			];
		}

		// Sort by category then name.
		usort(
			$catalog,
			static function ( array $a, array $b ): int {
				$cat_cmp = strcmp( $a['category'], $b['category'] );
				if ( 0 !== $cat_cmp ) {
					return $cat_cmp;
				}
				return strcmp( $a['name'], $b['name'] );
			}
		);

		return $catalog;
	}

	/**
	 * Convert a Bricks element's controls to JSON Schema Draft 2020-12.
	 *
	 * Maps Bricks control types to JSON Schema types.
	 * Controls with responsive variants note the key format {key}:{breakpoint}:{pseudo}.
	 *
	 * @param object $element The Bricks element object.
	 * @return array<string, mixed> JSON Schema object for element settings.
	 */
	public function convert_to_json_schema( object $element ): array {
		$controls = [];

		if ( method_exists( $element, 'get_controls' ) ) {
			$controls = $element->get_controls();
		} elseif ( isset( $element->controls ) && is_array( $element->controls ) ) {
			$controls = $element->controls;
		}

		if ( empty( $controls ) || ! is_array( $controls ) ) {
			return [
				'type'                 => 'object',
				'properties'           => new \stdClass(),
				'additionalProperties' => true,
			];
		}

		$properties    = [];
		$required      = [];
		$control_count = 0;

		foreach ( $controls as $control_key => $control ) {
			if ( ! is_array( $control ) || ! isset( $control['type'] ) ) {
				continue;
			}

			// Skip group/section/tab separators.
			if ( in_array( $control['type'], [ 'group', 'section', 'tab', 'separator', 'data' ], true ) ) {
				continue;
			}

			++$control_count;
			if ( $control_count > 200 ) {
				// Prevent excessive iteration on complex elements.
				break;
			}

			$control_schema = $this->map_control_type_to_schema( $control );

			// Add description from control definition.
			if ( ! empty( $control['label'] ) ) {
				$control_schema['description'] = $control['label'];
			} elseif ( ! empty( $control['placeholder'] ) ) {
				$control_schema['description'] = $control['placeholder'];
			}

			// Note responsive/state support.
			if ( ! empty( $control['css'] ) ) {
				$existing_desc                 = $control_schema['description'] ?? '';
				$control_schema['description'] = trim( $existing_desc . ' Supports responsive variants: {key}:{breakpoint}:{pseudo} (e.g., ' . $control_key . ':tablet_portrait, ' . $control_key . ':mobile:hover). Use get_breakpoints tool for valid breakpoint names.' );
			}

			$properties[ $control_key ] = $control_schema;

			// Mark as required if explicitly set.
			if ( ! empty( $control['required'] ) && true === $control['required'] ) {
				$required[] = $control_key;
			}
		}

		$schema = [
			'type'                 => 'object',
			'properties'           => empty( $properties ) ? new \stdClass() : $properties,
			'additionalProperties' => true,
		];

		if ( ! empty( $required ) ) {
			$schema['required'] = $required;
		}

		return $schema;
	}

	/**
	 * Generate a minimal working example for an element.
	 *
	 * Returns an example element array with minimal valid settings.
	 *
	 * @param string $element_name The element type name.
	 * @param object $element      The Bricks element object.
	 * @return array<string, mixed> Minimal working example element.
	 */
	public function generate_working_example( string $element_name, object $element ): array {
		// Known element examples with sensible defaults.
		$known_examples = [
			'section'    => [ '_padding' => '40px 0' ],
			'container'  => [],
			'block'      => [],
			'div'        => [],
			'heading'    => [
				'text' => 'Heading Text',
				'tag'  => 'h2',
			],
			'text-basic' => [ 'text' => '<p>Paragraph text</p>' ],
			'text'       => [ 'text' => '<p>Paragraph text</p>' ],
			'button'     => [
				'text' => 'Click Me',
				'link' => [ 'url' => '#' ],
			],
			'image'      => [
				'image' => [
					'id'  => 0,
					'url' => 'https://placeholder.example/800x400',
				],
			],
			'video'      => [ 'videoUrl' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ' ],
			'divider'    => [],
			'icon'       => [],
			'icon-box'   => [
				'heading' => 'Icon Box Heading',
				'text'    => 'Icon box description.',
			],
			'list'       => [],
			'code'       => [ 'code' => '// Your code here' ],
			'map'        => [ 'address' => 'New York, NY, USA' ],
			'form'       => [
				'fields'           => [
					[
						'id'          => 'abc123',
						'type'        => 'text',
						'label'       => 'Name',
						'placeholder' => 'Your Name',
						'required'    => false,
						'width'       => 100,
					],
					[
						'id'          => 'def456',
						'type'        => 'email',
						'label'       => 'Email',
						'placeholder' => 'Your Email',
						'required'    => true,
						'width'       => 100,
					],
					[
						'id'          => 'ghi789',
						'type'        => 'textarea',
						'label'       => 'Message',
						'placeholder' => 'Your Message',
						'required'    => true,
						'width'       => 100,
					],
				],
				'actions'          => [ 'email' ],
				'successMessage'   => 'Message successfully sent. We will get back to you as soon as possible.',
				'emailSubject'     => 'Contact form request',
				'emailTo'          => 'admin_email',
				'htmlEmail'        => true,
				'submitButtonText' => 'Send',
			],
			'nav-menu'   => [],
			'posts'      => [],
			'pagination' => [],
			'logo'       => [],
			'search'     => [],
		];

		if ( isset( $known_examples[ $element_name ] ) ) {
			return $known_examples[ $element_name ];
		}

		// For unknown elements: use defaults from controls where available.
		$settings = [];

		$controls = [];
		if ( method_exists( $element, 'get_controls' ) ) {
			$controls = $element->get_controls();
		} elseif ( isset( $element->controls ) && is_array( $element->controls ) ) {
			$controls = $element->controls;
		}

		if ( ! is_array( $controls ) ) {
			return $settings;
		}

		$added = 0;
		foreach ( $controls as $control_key => $control ) {
			if ( ! is_array( $control ) || ! isset( $control['type'] ) ) {
				continue;
			}

			// Skip non-setting controls.
			if ( in_array( $control['type'], [ 'group', 'section', 'tab', 'separator', 'data' ], true ) ) {
				continue;
			}

			// Only include controls with explicit defaults or required fields.
			if ( isset( $control['default'] ) ) {
				$settings[ $control_key ] = $control['default'];
				++$added;
			} elseif ( ! empty( $control['required'] ) ) {
				// Use type-appropriate empty value for required fields.
				$settings[ $control_key ] = $this->get_empty_value_for_type( $control['type'] );
				++$added;
			}

			if ( $added >= 5 ) {
				// Keep examples minimal.
				break;
			}
		}

		return $settings;
	}

	/**
	 * Get the Bricks version string for cache key generation.
	 *
	 * @return string Bricks version or 'unknown' if not available.
	 */
	public function get_bricks_version(): string {
		if ( defined( 'BRICKS_VERSION' ) ) {
			return (string) BRICKS_VERSION;
		}
		return 'unknown';
	}

	/**
	 * Flush all schema transient caches.
	 *
	 * Called when Bricks version changes or plugins are updated.
	 *
	 * @return void
	 */
	public function flush_cache(): void {
		global $wpdb;

		// Delete all transients matching our prefix pattern.
		$prefix = '_transient_' . self::CACHE_KEY_PREFIX;
		$wpdb->query( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->prepare(
				"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
				$wpdb->esc_like( $prefix ) . '%',
				$wpdb->esc_like( '_transient_timeout_' . self::CACHE_KEY_PREFIX ) . '%'
			)
		);
	}

	/**
	 * Map a Bricks control definition to a JSON Schema type.
	 *
	 * @param array<string, mixed> $control Bricks control definition.
	 * @return array<string, mixed> JSON Schema type definition.
	 */
	private function map_control_type_to_schema( array $control ): array {
		$type = $control['type'] ?? 'text';

		switch ( $type ) {
			case 'text':
			case 'textarea':
			case 'code':
			case 'editor':
			case 'richtextEditor':
				return [ 'type' => 'string' ];

			case 'number':
				$schema = [ 'type' => 'number' ];
				if ( isset( $control['min'] ) ) {
					$schema['minimum'] = $control['min'];
				}
				if ( isset( $control['max'] ) ) {
					$schema['maximum'] = $control['max'];
				}
				return $schema;

			case 'checkbox':
			case 'toggle':
				return [ 'type' => 'boolean' ];

			case 'select':
				$schema = [ 'type' => 'string' ];
				if ( ! empty( $control['options'] ) && is_array( $control['options'] ) ) {
					$schema['enum'] = array_values( array_keys( $control['options'] ) );
				}
				return $schema;

			case 'color':
				return [
					'type'    => 'string',
					'pattern' => '^(#[a-fA-F0-9]{3,8}|rgba?\(|hsla?\(|var\()',
				];

			case 'dimensions':
				return [
					'type'       => 'object',
					'properties' => [
						'top'    => [ 'type' => 'string' ],
						'right'  => [ 'type' => 'string' ],
						'bottom' => [ 'type' => 'string' ],
						'left'   => [ 'type' => 'string' ],
					],
				];

			case 'typography':
				return [
					'type'       => 'object',
					'properties' => [
						'font-family'    => [ 'type' => 'string' ],
						'font-size'      => [ 'type' => 'string' ],
						'font-weight'    => [ 'type' => [ 'string', 'number' ] ],
						'line-height'    => [ 'type' => 'string' ],
						'letter-spacing' => [ 'type' => 'string' ],
						'text-transform' => [ 'type' => 'string' ],
						'font-style'     => [ 'type' => 'string' ],
					],
				];

			case 'image':
				return [
					'type'       => 'object',
					'properties' => [
						'id'  => [ 'type' => 'integer' ],
						'url' => [ 'type' => 'string' ],
					],
				];

			case 'gallery':
				return [
					'type'  => 'array',
					'items' => [
						'type'       => 'object',
						'properties' => [
							'id'  => [ 'type' => 'integer' ],
							'url' => [ 'type' => 'string' ],
						],
					],
				];

			case 'link':
				return [
					'type'       => 'object',
					'properties' => [
						'url'      => [ 'type' => 'string' ],
						'type'     => [ 'type' => 'string' ],
						'newTab'   => [ 'type' => 'boolean' ],
						'nofollow' => [ 'type' => 'boolean' ],
					],
				];

			case 'icon':
				return [
					'type'       => 'object',
					'properties' => [
						'library' => [ 'type' => 'string' ],
						'icon'    => [ 'type' => 'string' ],
						'svg'     => [
							'type'       => 'object',
							'properties' => [
								'id'  => [ 'type' => 'integer' ],
								'url' => [ 'type' => 'string' ],
							],
						],
					],
				];

			case 'repeater':
				// Recurse into repeater fields.
				$items_props = [];
				if ( ! empty( $control['fields'] ) && is_array( $control['fields'] ) ) {
					foreach ( $control['fields'] as $field_key => $field ) {
						if ( is_array( $field ) ) {
							$items_props[ $field_key ] = $this->map_control_type_to_schema( $field );
						}
					}
				}

				return [
					'type'  => 'array',
					'items' => [
						'type'                 => 'object',
						'properties'           => empty( $items_props ) ? new \stdClass() : $items_props,
						'additionalProperties' => true,
					],
				];

			case 'background':
				return [
					'type'       => 'object',
					'properties' => [
						'color'  => [
							'type'    => 'string',
							'pattern' => '^(#[a-fA-F0-9]{3,8}|rgba?\(|hsla?\(|var\()',
						],
						'image'  => [
							'type'       => 'object',
							'properties' => [
								'id'  => [ 'type' => 'integer' ],
								'url' => [ 'type' => 'string' ],
							],
						],
						'size'   => [ 'type' => 'string' ],
						'repeat' => [ 'type' => 'string' ],
					],
				];

			case 'border':
				return [
					'type'       => 'object',
					'properties' => [
						'width'  => [ 'type' => 'string' ],
						'style'  => [ 'type' => 'string' ],
						'color'  => [
							'type'    => 'string',
							'pattern' => '^(#[a-fA-F0-9]{3,8}|rgba?\(|hsla?\(|var\()',
						],
						'radius' => [ 'type' => 'string' ],
					],
				];

			case 'box-shadow':
				return [
					'type'       => 'object',
					'properties' => [
						'offsetX' => [ 'type' => 'string' ],
						'offsetY' => [ 'type' => 'string' ],
						'blur'    => [ 'type' => 'string' ],
						'spread'  => [ 'type' => 'string' ],
						'color'   => [
							'type'    => 'string',
							'pattern' => '^(#[a-fA-F0-9]{3,8}|rgba?\(|hsla?\(|var\()',
						],
					],
				];

			default:
				// Fallback: accept any string or object.
				return [
					'oneOf' => [
						[ 'type' => 'string' ],
						[ 'type' => 'number' ],
						[ 'type' => 'boolean' ],
						[ 'type' => 'object' ],
						[ 'type' => 'array' ],
					],
				];
		}
	}

	/**
	 * Get an element label from the element object.
	 *
	 * @param object $element The Bricks element object.
	 * @return string Element label.
	 */
	private function get_element_label( object $element ): string {
		if ( isset( $element->label ) && is_string( $element->label ) ) {
			return $element->label;
		}
		if ( method_exists( $element, 'get_label' ) ) {
			return (string) $element->get_label();
		}
		return '';
	}

	/**
	 * Get an element category from the element object.
	 *
	 * @param object $element The Bricks element object.
	 * @return string Element category slug.
	 */
	private function get_element_category( object $element ): string {
		if ( isset( $element->category ) && is_string( $element->category ) ) {
			return $element->category;
		}
		return 'general';
	}

	/**
	 * Instantiate a Bricks element object from a class name or object.
	 *
	 * @param string|object $element_class Class name or existing object.
	 * @return object|null Instantiated element or null on failure.
	 */
	private function get_element_object( string|object $element_class ): ?object {
		if ( is_object( $element_class ) ) {
			return $element_class;
		}

		if ( ! is_string( $element_class ) || ! class_exists( $element_class ) ) {
			return null;
		}

		try {
			return new $element_class( [] );
		} catch ( \Throwable $e ) {
			return null;
		}
	}

	/**
	 * Find element names similar to the requested name.
	 *
	 * Uses similar_text() to find closest matches.
	 *
	 * @param string        $requested     The requested element name.
	 * @param array<string> $element_names All available element names.
	 * @return array<string> List of similar element names (max 5).
	 */
	private function find_similar_element_names( string $requested, array $element_names ): array {
		$similar = [];

		foreach ( $element_names as $name ) {
			similar_text( $requested, $name, $percent );
			if ( $percent > 40 ) {
				$similar[ $name ] = $percent;
			}
		}

		// Sort by similarity descending.
		arsort( $similar );

		return array_slice( array_keys( $similar ), 0, 5 );
	}

	/**
	 * Get a type-appropriate empty value for a control type.
	 *
	 * Used when generating minimal examples for required fields.
	 *
	 * @param string $type The control type.
	 * @return mixed Type-appropriate empty value.
	 */
	private function get_empty_value_for_type( string $type ): mixed {
		return match ( $type ) {
			'number'                     => 0,
			'checkbox', 'toggle'         => false,
			'repeater', 'gallery'        => [],
			'image', 'link', 'icon',
			'dimensions', 'typography',
			'background', 'border',
			'box-shadow'                 => [],
			default                      => '',
		};
	}
}
