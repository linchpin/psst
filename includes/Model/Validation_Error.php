<?php
/**
 * A validation failure, naming the field.
 *
 * A plain value object rather than WP_Error so the model stays WordPress-free
 * and unit-testable; the REST controller converts it.
 *
 * @package Linchpin\Psst\Model
 */

namespace Linchpin\Psst\Model;

/**
 * Class Validation_Error
 */
final class Validation_Error {

	/**
	 * Constructor.
	 *
	 * @param string $field   The offending request field.
	 * @param string $message Human-readable reason.
	 * @param string $code    Error code; the REST layer maps it to a status.
	 */
	public function __construct(
		public readonly string $field,
		public readonly string $message,
		public readonly string $code = 'psst_invalid_envelope',
	) {}
}
