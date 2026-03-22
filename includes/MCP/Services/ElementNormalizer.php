<?php
/**
 * Bricks element normalizer.
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
 * ElementNormalizer class.
 *
 * Normalizes Bricks element input from two supported formats into the native
 * Bricks flat array format. Handles simplified nested format by generating IDs
 * and managing parent/children linkage automatically.
 *
 * Supported input formats:
 * 1. Native flat array — every element has id, parent, children keys.
 * 2. Simplified nested format — elements have name + optional settings + optional children.
 *    IDs and parent/children linkage are generated automatically.
 */
class ElementNormalizer {

	/**
	 * HTML content settings keys (value will be sanitized with wp_kses_post).
	 *
	 * Keys whose values are expected to contain HTML markup.
	 *
	 * @var array<int, string>
	 */
	private const HTML_SETTINGS_KEYS = [
		'text',
		'content',
		'html',
		'innerHtml',
		'body',
		'excerpt',
		'description',
		'label',
		'caption',
	];

	/**
	 * Element ID generator instance.
	 *
	 * @var ElementIdGenerator
	 */
	private ElementIdGenerator $id_generator;

	/**
	 * Constructor.
	 *
	 * @param ElementIdGenerator $id_generator ID generator for simplified format conversion.
	 */
	public function __construct( ElementIdGenerator $id_generator ) {
		$this->id_generator = $id_generator;
	}

	/**
	 * Normalize element input to native Bricks flat array format.
	 *
	 * Entry point. Detects input format and returns flat array. If input is
	 * already in native flat format, returns as-is. If simplified format,
	 * converts by generating IDs and building parent/children linkage.
	 *
	 * @param array<int, array<string, mixed>> $input             Input elements (native or simplified format).
	 * @param array<int, array<string, mixed>> $existing_elements Existing elements for collision-free ID generation.
	 * @return array<int, array<string, mixed>> Normalized flat element array.
	 */
	public function normalize( array $input, array $existing_elements = [] ): array {
		if ( empty( $input ) ) {
			return [];
		}

		if ( $this->is_flat_format( $input ) ) {
			return $input;
		}

		return $this->simplified_to_flat( $input, $existing_elements );
	}

	/**
	 * Detect if input is in native Bricks flat array format.
	 *
	 * Native flat format: every item is an associative array with 'id',
	 * 'parent', and 'children' keys all present. If ANY item lacks one of
	 * these keys, the input is treated as simplified format.
	 *
	 * @param array<int, array<string, mixed>> $elements Elements to check.
	 * @return bool True if native flat format, false if simplified format.
	 */
	public function is_flat_format( array $elements ): bool {
		foreach ( $elements as $element ) {
			if ( ! is_array( $element ) ) {
				return false;
			}

			if (
				! array_key_exists( 'id', $element ) ||
				! array_key_exists( 'parent', $element ) ||
				! array_key_exists( 'children', $element )
			) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Convert simplified nested format to Bricks native flat array.
	 *
	 * Simplified format example:
	 * [
	 *   {
	 *     "name": "section",
	 *     "settings": {"_padding": "40px"},
	 *     "children": [
	 *       {
	 *         "name": "container",
	 *         "settings": {},
	 *         "children": [
	 *           {"name": "heading", "settings": {"text": "Hello", "tag": "h1"}}
	 *         ]
	 *       }
	 *     ]
	 *   }
	 * ]
	 *
	 * Output: Bricks flat array with generated IDs, proper parent/children linkage.
	 * Parent elements come before children in output array (Bricks convention).
	 *
	 * @param array<int, array<string, mixed>> $tree              Simplified nested elements (top level).
	 * @param array<int, array<string, mixed>> $existing_elements Existing elements for collision-free ID generation.
	 * @param int|string                       $parent_id         Parent element ID (0 for root).
	 * @return array<int, array<string, mixed>> Flat array of normalized elements.
	 */
	public function simplified_to_flat( array $tree, array $existing_elements, int|string $parent_id = 0 ): array {
		$flat = [];

		foreach ( $tree as $node ) {
			if ( ! is_array( $node ) ) {
				continue;
			}

			$name     = $node['name'] ?? 'div';
			$settings = isset( $node['settings'] ) && is_array( $node['settings'] ) ? $node['settings'] : [];
			$children = isset( $node['children'] ) && is_array( $node['children'] ) ? $node['children'] : [];

			// Generate unique ID — pass all elements accumulated so far plus existing to avoid collisions.
			$all_existing = array_merge( $existing_elements, $flat );
			$element_id   = $this->id_generator->generate_unique( $all_existing );

			// Sanitize settings values.
			$sanitized_settings = $this->sanitize_settings( $settings );

			// Recursively convert children, passing parent_id as this element's ID.
			$child_flat   = $this->simplified_to_flat( $children, array_merge( $all_existing, [ [ 'id' => $element_id ] ] ), $element_id );
			$children_ids = array_map(
				static fn( array $el ) => $el['id'],
				array_filter(
					$child_flat,
					static fn( array $el ) => (string) $el['parent'] === (string) $element_id
				)
			);

			// Build the flat element (parent comes first in output — Bricks convention).
			$element = [
				'id'       => $element_id,
				'name'     => sanitize_text_field( $name ),
				'parent'   => $parent_id,
				'children' => array_values( $children_ids ),
				'settings' => $sanitized_settings,
			];

			// Parent element first, then its flattened descendants.
			$flat[] = $element;
			foreach ( $child_flat as $child_element ) {
				$flat[] = $child_element;
			}
		}

		return $flat;
	}

	/**
	 * Sanitize element settings values.
	 *
	 * Applies wp_kses_post() to HTML fields and sanitize_text_field() to all others.
	 * HTML fields are those whose value contains HTML tags; keys listed in
	 * HTML_SETTINGS_KEYS always use wp_kses_post() regardless.
	 *
	 * @param array<string, mixed> $settings Raw element settings.
	 * @return array<string, mixed> Sanitized settings.
	 */
	private function sanitize_settings( array $settings ): array {
		$sanitized = [];

		foreach ( $settings as $key => $value ) {
			if ( ! is_string( $key ) ) {
				continue;
			}

			if ( is_array( $value ) ) {
				// Recurse into nested settings arrays.
				$sanitized[ $key ] = $this->sanitize_settings( $value );
				continue;
			}

			if ( ! is_string( $value ) ) {
				// Pass through non-string values (integers, booleans, null) unchanged.
				$sanitized[ $key ] = $value;
				continue;
			}

			// Determine sanitization method.
			$is_html_key   = in_array( $key, self::HTML_SETTINGS_KEYS, true );
			$contains_html = wp_strip_all_tags( $value ) !== $value;

			if ( $is_html_key || $contains_html ) {
				$sanitized[ $key ] = wp_kses_post( $value );
			} else {
				$sanitized[ $key ] = sanitize_text_field( $value );
			}
		}

		return $sanitized;
	}

	/**
	 * Merge new elements into an existing flat array under a specified parent.
	 *
	 * Inserts new element(s) into existing flat array. Updates the parent's
	 * children array to include the new elements. Supports step-by-step editing.
	 *
	 * When $position is provided, inserts the new element at that index in the
	 * parent's children array (0-indexed). When null, appends at the end.
	 *
	 * @param array<int, array<string, mixed>> $existing     Existing flat element array.
	 * @param array<int, array<string, mixed>> $new_elements New elements to merge (flat array).
	 * @param string                           $parent_id    Parent element ID to insert into ('0' for root).
	 * @param int|null                         $position     Position in parent's children to insert at (null = append).
	 * @return array<int, array<string, mixed>> Merged flat element array.
	 */
	public function merge_elements( array $existing, array $new_elements, string $parent_id, ?int $position = null ): array {
		// Collect IDs of top-level new elements (those with parent matching parent_id).
		$new_child_ids = array_map(
			static fn( array $el ) => $el['id'],
			array_filter(
				$new_elements,
				static fn( array $el ) => (string) $el['parent'] === $parent_id
			)
		);

		// Update parent's children array if parent is not root (0).
		if ( '0' !== $parent_id ) {
			$existing = array_map(
				static function ( array $el ) use ( $parent_id, $new_child_ids, $position ) {
					if ( $el['id'] === $parent_id ) {
						if ( null === $position ) {
							// Append at end (default behavior).
							$el['children'] = array_values(
								array_unique( array_merge( $el['children'], $new_child_ids ) )
							);
						} else {
							// Remove any existing occurrences of new IDs to avoid duplicates.
							$children = array_values(
								array_filter(
									$el['children'],
									static fn( string $cid ) => ! in_array( $cid, $new_child_ids, true )
								)
							);
							// Insert at specified position.
							array_splice( $children, $position, 0, $new_child_ids );
							$el['children'] = array_values( array_unique( $children ) );
						}
					}
					return $el;
				},
				$existing
			);

			// Append new elements at end of flat array.
			return array_merge( $existing, $new_elements );
		}

		// Root-level insertion (parent_id === '0').
		if ( null !== $position ) {
			// Insert the new elements at the specified position in the flat array.
			array_splice( $existing, $position, 0, $new_elements );
			return $existing;
		}

		// Append new elements at end (default behavior for root).
		return array_merge( $existing, $new_elements );
	}
}
