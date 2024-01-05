<?php

/**
 * Class that holds all utils helpers.
 *
 * @package EightshiftFormsUtils\Helpers
 */

declare(strict_types=1);

namespace EightshiftFormsUtils\Helpers;

/**
 * UtilsHelper class.
 */
class UtilsHelper
{
		/**
	 * Return main manifest.json file.
	 *
	 * @return array<mixed>
	 */
	public static function getUtilsManifest(): array
	{
		$sep = \DIRECTORY_SEPARATOR;
		$filePath = dirname(__FILE__, 2) . "{$sep}manifest.json";

		return \json_decode(\implode(' ', (array)\file($filePath)), true);
	}

	/**
	 * Return utils icons from manifest.json.
	 *
	 * @param string $type Type to return.
	 *
	 * @return string
	 */
	public static function getUtilsIcons(string $type): string
	{
		return self::getUtilsManifest()['icons'][Helper::kebabToCamelCase($type)] ?? '';
	}

	/**
	 * Return selector admin enum values by name.
	 *
	 * @param string $name Name of the enum.
	 *
	 * @return string
	 */
	public static function getStateSelectorAdmin(string $name): string
	{
		return self::getUtilsManifest()['enums']['selectorsAdmin'][$name] ?? '';
	}

	/**
	 * Return selector enum values by name.
	 *
	 * @param string $name Name of the enum.
	 *
	 * @return string
	 */
	public static function getStateSelector(string $name): string
	{
		return self::getUtilsManifest()['enums']['selectors'][$name] ?? '';
	}

	/**
	 * Return attribute enum values by name.
	 *
	 * @param string $name Name of the enum.
	 *
	 * @return string
	 */
	public static function getStateAttribute(string $name): string
	{
		return self::getUtilsManifest()['enums']['attrs'][$name] ?? '';
	}

	/**
	 * Return all params enum values.
	 *
	 * @return array<string>
	 */
	public static function getStateParams(): array
	{
		return self::getUtilsManifest()['enums']['params'] ?? [];
	}

	/**
	 * Return param enum values by name.
	 *
	 * @param string $name Name of the enum.
	 *
	 * @return string
	 */
	public static function getStateParam(string $name): string
	{
		return self::getStateParams()[$name] ?? '';
	}
}
