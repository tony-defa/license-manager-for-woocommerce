<?php

namespace LicenseManagerForWooCommerce\Enums;

defined( 'ABSPATH' ) || exit;

abstract class ColumnType {
	/**
	 * @var string
	 */
	const INT = 'INT';

	/**
	 * @var string
	 */
	const TINYINT = 'TINYINT';

	/**
	 * @var string
	 */
	const BIGINT = 'BIGINT';

	/**
	 * @var string
	 */
	const CHAR = 'CHAR';

	/**
	 * @var string
	 */
	const VARCHAR = 'VARCHAR';

	/**
	 * @var string
	 */
	const LONGTEXT = 'LONGTEXT';

	/**
	 * @var string
	 */
	const DATETIME = 'DATETIME';
}
