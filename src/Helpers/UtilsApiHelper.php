<?php

/**
 * Class that holds all api helpers used in classes.
 *
 * @package EightshiftFormsUtils\Helpers
 */

declare(strict_types=1);

namespace EightshiftFormsUtils\Helpers;

use EightshiftFormsUtils\Config\UtilsConfig;
use EightshiftLibs\Helpers\Components;

/**
 * UtilsApiHelper class.
 */
final class UtilsApiHelper
{
	/**
	 * Return API response array details.
	 *
	 * @param array<mixed> $response Response got from the API.
	 *
	 * @return array<string, mixed>
	 */
	/**
	 * Return API response array details.
	 *
	 * @param string $integration Integration name from settings.
	 * @param mixed $response API full reponse.
	 * @param string $url Url of the request.
	 * @param array<mixed> $params All params prepared for API.
	 * @param array<mixed> $files All files prepared for API.
	 * @param string $itemId List Id used for API (questions, form id, list id, item id).
	 * @param string $formId Internal form ID.
	 * @param boolean $isDisabled If integration is disabled.
	 * @param boolean $isCurl Used for some changed if native cURL is used.
	 *
	 * @return array<string, mixed>
	 */
	public static function getIntegrationApiReponseDetails(
		string $integration,
		$response,
		string $url,
		array $params = [],
		array $files = [],
		string $itemId = '',
		string $formId = '',
		bool $isDisabled = false,
		bool $isCurl = false
	): array {

		// Do regular stuff if this is not and WP_Error.
		if (!\is_wp_error($response)) {
			if ($isCurl) {
				$code = $response['status'] ?? 200;
				$body = $response;
			} else {
				$code = $response['response']['code'] ?? 200;
				$body = $response['body'] ?? '';

				if (Components::isJson($body)) {
					$body = \json_decode($body, true) ?? [];
				}
			}
		} else {
			// Mock response for WP_Error.
			$code = 404;
			$body = [
				'error' => $response->get_error_message(),
			];
			$response = [];
		}

		return [
			UtilsConfig::IARD_TYPE => Components::kebabToCamelCase($integration, '-'),
			UtilsConfig::IARD_PARAMS => $params,
			UtilsConfig::IARD_FILES => $files,
			UtilsConfig::IARD_RESPONSE => $response['response'] ?? [],
			UtilsConfig::IARD_CODE => $code,
			UtilsConfig::IARD_BODY => !\is_string($body) ? $body : [],
			UtilsConfig::IARD_URL => $url,
			UtilsConfig::IARD_ITEM_ID => $itemId,
			UtilsConfig::IARD_FORM_ID => $formId,
			UtilsConfig::IARD_IS_DISABLED => $isDisabled,
		];
	}

	/**
	 * Return Integration API error response array - in combination with getIntegrationApiReponseDetails response.
	 * 
	 * NOTE: Not for public response on API.
	 *
	 * @param array<string, mixed> $details Details provided by getIntegrationApiReponseDetails method.
	 * @param string $msg Message to output.
	 * @param array<string, mixed> $additional Additional array details to attach to the success output.
	 *
	 * @return array<string, mixed>
	 */
	public static function getIntegrationErrorOutput(array $details, string $msg, array $additional = []): array
	{
		unset($details[UtilsConfig::IARD_STATUS]);
		unset($details[UtilsConfig::IARD_MSG]);

		return \array_merge(
			[
				'status' => UtilsConfig::STATUS_ERROR,
				'message' => $msg,
			],
			$details,
			$additional
		);
	}

	/**
	 * Return Integration API success response array - in combination with getIntegrationApiReponseDetails response.
	 *
	 * NOTE: Not for public response on API.
	 *
	 * @param array<string, mixed> $details Details provided by getIntegrationApiReponseDetails method.
	 * @param array<string, mixed> $additional Additional array details to attach to the success output.
	 *
	 * @return array<string, mixed>
	 */
	public static function getIntegrationSuccessOutput(array $details, array $additional = []): array
	{
		$type = $details[UtilsConfig::IARD_TYPE] ?? '';

		unset($details[UtilsConfig::IARD_STATUS]);
		unset($details[UtilsConfig::IARD_MSG]);

		return \array_merge(
			[
				'status' => UtilsConfig::STATUS_SUCCESS,
				'message' => "{$type}Success",
			],
			$details,
			$additional
		);
	}

	/**
	 * Return Integration API final response array depending on the status - in combination with getIntegrationApiReponseDetails response.
	 *
	 * @param array<string, mixed> $formDetails Data passed from the `getFormDetailsApi` function.
	 * @param string $msg Message to output.
	 *
	 * @return array<string, array<mixed>|int|string>
	 */
	public static function getIntegrationApiPublicOutput(array $formDetails, string $msg): array
	{
		$response = $formDetails[UtilsConfig::IARD_RESPONSE] ?? [];
		$status = $response[UtilsConfig::IARD_STATUS] ?? UtilsConfig::STATUS_ERROR;

		$additionalOutput = [];

		$allowedOutputKeys = UtilsHelper::getStateResponseOutputKeys();

		foreach ($allowedOutputKeys as $value) {
			if (isset($response[$value])) {
				$additionalOutput[$value] = $response[$value];
			}
		}

		if ($status === UtilsConfig::STATUS_SUCCESS) {
			return self::getApiSuccessPublicOutput(
				$msg,
				$additionalOutput,
				$response
			);
		}

		return self::getApiErrorPublicOutput(
			$msg,
			$additionalOutput,
			$response
		);
	}

	/**
	 * Return API error response array.
	 *
	 * @param string $msg Msg for the user.
	 * @param array<string, mixed> $additional Additonal data to attach to response.
	 * @param array<string, mixed> $debug Debug options.
	 *
	 * @return array<string, array<mixed>|int|string>
	 */
	public static function getApiErrorPublicOutput(string $msg, array $additional = [], array $debug = []): array
	{
		$output = [
			'status' => UtilsConfig::STATUS_ERROR,
			'code' => 400,
			'message' => $msg,
		];

		if ($additional) {
			$output['data'] = $additional;
		}

		if (UtilsDeveloperHelper::isDeveloperModeActive() && $debug) {
			$output['debug'] = $debug;
		}

		return $output;
	}

	/**
	 * Return API success response array - Generic.
	 *
	 * @param string $msg Msg for the user.
	 * @param array<int|string, mixed> $additional Additonal data to attach to response.
	 * @param array<string, mixed> $debug Debug options.
	 *
	 * @return array<string, array<mixed>|int|string>
	 */
	public static function getApiSuccessPublicOutput(string $msg, array $additional = [], array $debug = []): array
	{
		$output = [
			'status' => UtilsConfig::STATUS_SUCCESS,
			'code' => 200,
			'message' => $msg,
		];

		if ($additional) {
			$output['data'] = $additional;
		}

		if (UtilsDeveloperHelper::isDeveloperModeActive() && $debug) {
			$output['debug'] = $debug;
		}

		return $output;
	}

	/**
	 * Return API warning response array - Generic.
	 *
	 * @param string $msg Msg for the user.
	 * @param array<int|string, mixed> $additional Additonal data to attach to response.
	 * @param array<string, mixed> $debug Debug options.
	 *
	 * @return array<string, array<mixed>|int|string>
	 */
	public static function getApiWarningPublicOutput(string $msg, array $additional = [], array $debug = []): array
	{
		$output = [
			'status' => UtilsConfig::STATUS_WARNING,
			'code' => 200,
			'message' => $msg,
		];

		if ($additional) {
			$output['data'] = $additional;
		}

		if (UtilsDeveloperHelper::isDeveloperModeActive() && $debug) {
			$output['debug'] = $debug;
		}

		return $output;
	}

	/**
	 * Return API error response array for missing permissions.
	 *
	 * @return array<string, mixed>
	 */
	public static function getApiPermissionsErrorPublicOutput(): array
	{
		return self::getApiErrorPublicOutput(
			\esc_html__('You don\'t have enough permissions to perform this action!', 'eightshift-forms'),
		);
	}
}
