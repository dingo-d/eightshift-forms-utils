<?php

/**
 * The class register route for Base endpoint used on all forms.
 *
 * @package EightshiftFormsUtils\Rest\Routes
 */

declare(strict_types=1);

namespace EightshiftFormsUtils\Rest\Routes;

use EightshiftFormsUtils\Config\UtilsConfig;
use EightshiftFormsUtils\Helpers\UtilsApiHelper;
use EightshiftFormsUtils\Helpers\UtilsGeneralHelper;
use EightshiftFormsUtils\Helpers\UtilsHelper;
use EightshiftFormsUtils\Helpers\UtilsUploadHelper;
use EightshiftLibs\Rest\CallableRouteInterface;
use EightshiftLibs\Rest\Routes\AbstractRoute;
use WP_REST_Request;

/**
 * Class AbstractUtilsBaseRoute
 */
abstract class AbstractUtilsBaseRoute extends AbstractRoute implements CallableRouteInterface
{
	/**
	 * Method that returns project Route namespace
	 *
	 * @return string Project namespace for REST route.
	 */
	protected function getNamespace(): string
	{
		return UtilsConfig::ROUTE_NAMESPACE;
	}

	/**
	 * Method that returns project route version
	 *
	 * @return string Route version as a string.
	 */
	protected function getVersion(): string
	{
		return UtilsConfig::ROUTE_VERSION;
	}

	/**
	 * Get callback arguments array
	 *
	 * @return array<string, mixed> Either an array of options for the endpoint, or an array of arrays for multiple methods.
	 */
	protected function getCallbackArguments(): array
	{
		return [
			'methods' => $this->getMethods(),
			'callback' => [$this, 'routeCallback'],
			'permission_callback' => [$this, 'permissionCallback'],
		];
	}

	/**
	 * Returns allowed methods for this route.
	 *
	 * @return string
	 */
	protected function getMethods(): string
	{
		return static::CREATABLE;
	}

	/**
	 * By default allow public access to route.
	 *
	 * @return bool
	 */
	public function permissionCallback(): bool
	{
		return true;
	}

	/**
	 * Toggle if this route requires nonce verification.
	 *
	 * @return bool
	 */
	protected function requiresNonceVerification(): bool
	{
		return false;
	}

	/**
	 * Extract params from request.
	 * Check if array then output only value that is not empty.
	 *
	 * @param WP_REST_Request $request $request Data got from endpoint url.
	 * @param string $type Request type.
	 *
	 * @return array<string, mixed>
	 */
	protected function getRequestParams(WP_REST_Request $request, string $type = self::CREATABLE): array
	{
		// Check type of request and extract params.
		switch ($type) {
			case self::CREATABLE:
				$params = $request->get_body_params();
				break;
			case self::READABLE:
				$params = $request->get_params();
				break;
			default:
				$params = [];
				break;
		}

		// Check if request maybe has json params usualy sent by the Block editor.
		if ($request->get_json_params()) {
			$params = \array_merge(
				$params,
				$request->get_json_params(),
			);
		}

		return $params;
	}

	/**
	 * Convert JS FormData object to usable data in php.
	 *
	 * @param WP_REST_Request $request $request Data got from endpoint url.
	 * @param string $type Request type.
	 *
	 * @return array<string, mixed>
	 */
	protected function prepareApiParams(WP_REST_Request $request, string $type = self::CREATABLE): array
	{
		// Get params.
		$params = $this->getRequestParams($request, $type);

		// Bailout if there are no params.
		if (!$params) {
			return [];
		}

		// Skip any manipulations if direct param is set.
		$paramsOutput = \array_map(
			static function ($item) {
				// Check if array then output only value that is not empty.
				if (\is_array($item)) {
					// Loop all items and decode.
					$inner = \array_map(
						static function ($item) {
							return \json_decode(\sanitize_text_field($item), true);
						},
						$item
					);

					// Find all items where value is not empty.
					$innerNotEmpty = \array_values(
						\array_filter(
							$inner,
							static function ($innerItem) {
								return !empty($innerItem['value']);
							}
						)
					);

					// Fallback if everything is empty.
					if (!$innerNotEmpty) {
						return $inner[0];
					}

					// If multiple values this is checkbox or select multiple.
					if (\count($innerNotEmpty) > 1) {
						$multiple = \array_values(
							\array_map(
								static function ($item) {
									return $item['value'];
								},
								$innerNotEmpty
							)
						);

						// Append values to the first value.
						$innerNotEmpty[0]['value'] = \implode(UtilsConfig::DELIMITER, $multiple);

						return $innerNotEmpty[0];
					}

					// If one item then this is probably radio.
					return $innerNotEmpty[0];
				}

				// Just decode value.
				return \json_decode(\sanitize_text_field($item), true);
			},
			$params
		);

		$output = [];

		// If this route is for public form prepare all params.
		foreach ($paramsOutput as $key => $value) {
			switch ($key) {
				// Used for direct import from settings.
				case UtilsHelper::getStateParam('direct'):
					$output['directImport'] = (bool) $value['value'];
					break;
				// Used for direct import from settings.
				case UtilsHelper::getStateParam('itemId'):
					$output['itemId'] = $value['value'];
					break;
				// Used for direct import from settings.
				case UtilsHelper::getStateParam('innerId'):
					$output['innerId'] = $value['value'];
					break;
				case UtilsHelper::getStateParam('formId'):
					$output['formId'] = $value['value'];
					$output['params'][$key] = $value;
					break;
				case UtilsHelper::getStateParam('postId'):
					$output['postId'] = $value['value'];
					$output['params'][$key] = $value;
					break;
				case UtilsHelper::getStateParam('type'):
					$output['type'] = $value['value'];
					$output['params'][$key] = $value;
					break;
				case UtilsHelper::getStateParam('action'):
					$output['action'] = $value['value'];
					$output['params'][$key] = $value;
					break;
				case UtilsHelper::getStateParam('captcha'):
					$output['captcha'] = $value['value'];
					$output['params'][$key] = $value;
					break;
				case UtilsHelper::getStateParam('actionExternal'):
					$output['actionExternal'] = $value['value'];
					$output['params'][$key] = $value;
					break;
				case UtilsHelper::getStateParam('settingsType'):
					$output['settingsType'] = $value['value'];
					$output['params'][$key] = $value;
					break;
				case UtilsHelper::getStateParam('storage'):
					$output['storage'] = $value['value'];
					$value['value'] = (!empty($value['value'])) ? \json_decode($value['value'], true) : [];
					$output['params'][$key] = $value;
					break;
				case UtilsHelper::getStateParam('additionalParam'):
					$value['value'] = (!empty($value['value'])) ? \json_decode($value['value'], true) : [];
					$output['params'][$key] = $value;
					break;
				case UtilsHelper::getStateParam('steps'):
					$output['apiSteps'] = [
						'fields' => $value['value'],
						'current' => $value['custom'],
					];
					break;
				default:
					// All other "normal" fields.
					$fieldType = $value['type'] ?? '';
					$fieldValue = $value['value'] ?? '';
					$fieldName = $value['name'] ?? '';

					if (!$fieldName) {
						break;
					}

					// File.
					if ($fieldType === 'file') {
						$output['files'][$key] = $fieldValue ? \array_merge(
							$value,
							[
								'value' => \array_map(
									function ($item) {
										return UtilsUploadHelper::getFilePath($item);
									},
									\explode(UtilsConfig::DELIMITER, $fieldValue)
								),
							]
						) : $value;
						break;
					}

					// Rating.
					if ($fieldType === 'rating' && $fieldValue === '0') {
						$value['value'] = '';
					}

					// Checkbox.
					if ($fieldType === 'checkbox') {
						$fieldValue = \explode(UtilsConfig::DELIMITER, $fieldValue);
					}

					$output['paramsRaw'][$fieldName] = $fieldValue;
					$output['params'][$key] = $value;

					break;
			}
		}

		return $output;
	}

	/**
	 * Convert JS FormData object to usable data in php.
	 *
	 * @param WP_REST_Request $request $request Data got from endpoint url.
	 * @param string $type Request type.
	 *
	 * @return array<string, mixed>
	 */
	protected function prepareSimpleApiParams(WP_REST_Request $request, string $type = self::CREATABLE): array
	{
		// Get params.
		$params = $this->getRequestParams($request, $type);

		// Bailout if there are no params.
		if (!$params) {
			return [];
		}

		return \array_map(
			static function ($item) {
				return \sanitize_text_field($item);
			},
			$params
		);
	}


	/**
	 * Prepare file from request for later usage. Attach custom data to file array.
	 *
	 * @param array<string, mixed> $file File array from reuqest.
	 * @param array<string, mixed> $params Params to use.
	 * @return array<string, mixed>
	 */
	protected function prepareFile(array $file, array $params): array
	{
		$file = $file['file'] ?? [];

		if (!$file) {
			return [];
		}

		return \array_merge(
			$file,
			[
				'id' => $params[UtilsHelper::getStateParam('fileId')]['value'] ?? '',
				'fieldName' => $params[UtilsHelper::getStateParam('name')]['value'] ?? '',
			]
		);
	}

	/**
	 * Check user permission for route action.
	 *
	 * @param string $permission Permission to check.
	 *
	 * @return array<string, mixed>
	 */
	protected function checkUserPermission(string $permission = UtilsConfig::CAP_SETTINGS): array
	{
		if (\current_user_can($permission)) {
			return [];
		}

		return UtilsApiHelper::getApiPermissionsErrorOutput();
	}

	/**
	 * Prepare array for later check like validation and etc...
	 *
	 * @param mixed $request Data got from endpoint url.
	 *
	 * @return array<string, mixed>
	 * 
	 * 
	 */
	protected function getFormDataReference($request): array
	{
		$formDataReference = [];

		// Get params from request.
		$params = $this->prepareApiParams($request);

		// Get form directImport from params.
		if (isset($params['directImport'])) {
			$formDataReference[UtilsConfig::FDR_DIRECT_IMPORT] = true;
			$formDataReference[UtilsConfig::FDR_ITEM_ID] = $params['itemId'] ?? '';
			$formDataReference[UtilsConfig::FDR_INNER_ID] = $params['innerId'] ?? '';
			$formDataReference[UtilsConfig::FDR_TYPE] = $params['type'] ?? '';
			$formDataReference[UtilsConfig::FDR_FORM_ID] = $params['formId'] ?? '';
			$formDataReference[UtilsConfig::FDR_POST_ID] = $params['postId'] ?? '';
			$formDataReference[UtilsConfig::FDR_PARAMS] = $params['params'] ?? [];
			$formDataReference[UtilsConfig::FDR_FILES] = $params['files'] ?? [];
		} else {
			// Get form id from params.
			$formId = $params['formId'] ?? '';

			// Get form type from params.
			$type = $params['type'] ?? '';

			// Get form settings for admin from params.
			$formSettingsType = $params['settingsType'] ?? '';

			// Manual populate output it admin settings our build it from form Id.
			if (
				$type === UtilsConfig::SETTINGS_TYPE_NAME ||
				$type === UtilsConfig::SETTINGS_GLOBAL_TYPE_NAME ||
				$type === UtilsConfig::FILE_UPLOAD_ADMIN_TYPE_NAME
			) {
				// This provides filter name for setting.
				$settingsName = \apply_filters(UtilsConfig::FILTER_SETTINGS_DATA, [])[$formSettingsType][$type] ?? '';

				$formDataReference = [
					UtilsConfig::FDR_FORM_ID => $formId,
					UtilsConfig::FDR_TYPE => $type,
					UtilsConfig::FDR_ITEM_ID => '',
					UtilsConfig::FDR_INNER_ID => '',
					UtilsConfig::FDR_FIELDS_ONLY => !empty($settingsName) ? \apply_filters($settingsName, $formId) : [],
				];
			} else {
				$formDataReference = UtilsGeneralHelper::getFormDetailsById($formId);
			}

			// Populare params.
			$formDataReference[UtilsConfig::FDR_PARAMS] = $params['params'] ?? [];

			// Populare params raw.
			$formDataReference[UtilsConfig::FDR_PARAMS_RAW] = $params['paramsRaw'] ?? [];

			// Populate files from uploaded ID.
			$formDataReference[UtilsConfig::FDR_FILES] = $params['files'] ?? [];

			// Populare files on upload. Only populated on file upload.
			$formDataReference[UtilsConfig::FDR_FILES_UPLOAD] = $this->prepareFile($request->get_file_params(), $params['params'] ?? []);

			// Populare action.
			$formDataReference[UtilsConfig::FDR_ACTION] = $params['action'] ?? '';

			// Populare action external.
			$formDataReference[UtilsConfig::FDR_ACTION_EXTERNAL] = $params['actionExternal'] ?? '';

			// Populare step fields.
			$formDataReference[UtilsConfig::FDR_API_STEPS] = $params['apiSteps'] ?? [];

			// Get form captcha from params.
			$formDataReference[UtilsConfig::FDR_CAPTCHA] = $params['captcha'] ?? [];

			// Get form post Id from params.
			$formDataReference[UtilsConfig::FDR_POST_ID] = $params['postId'] ?? '';

			// Get form storage from params.
			$formDataReference[UtilsConfig::FDR_STORAGE] = \json_decode($params['storage'] ?? '', true) ?? [];

			// Create placeholder for addon data.
			$formDataReference[UtilsConfig::FDR_ADDON_DATA] = [];
		}

		return $formDataReference;
	}
}
