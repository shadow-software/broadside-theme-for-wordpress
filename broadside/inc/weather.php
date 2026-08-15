<?php
/**
 * Live local weather for the masthead left ear.
 *
 * Pulls current conditions from Open-Meteo (no API key) for the publication's
 * city of record, caches the result in a transient, and formats it as two short
 * newspaper lines. When the fetch fails — or live weather is disabled — the
 * Customizer's static left-ear copy is used instead.
 *
 * @package Broadside
 * @since   1.4.1
 */

declare( strict_types = 1 );

defined( 'ABSPATH' ) || exit;

/**
 * How long a successful forecast is reused.
 */
const SHADOW_DIGEST_WEATHER_TTL = HOUR_IN_SECONDS;

/**
 * How long a failed fetch is remembered, so a down API does not stall every page.
 */
const SHADOW_DIGEST_WEATHER_FAIL_TTL = 15 * MINUTE_IN_SECONDS;

/**
 * Title + body for the left masthead ear, preferring live weather when enabled.
 *
 * @since 1.4.1
 * @return array{title: string, body: string}
 */
function shadow_digest_ear_left_content(): array {
	$title = (string) shadow_digest_get( 'shadow_digest_ear_left_title' );
	$body  = (string) shadow_digest_get( 'shadow_digest_ear_left_body' );

	if ( ! shadow_digest_get( 'shadow_digest_weather_enable' ) ) {
		return array(
			'title' => $title,
			'body'  => $body,
		);
	}

	$live = shadow_digest_weather_forecast();
	if ( null === $live ) {
		return array(
			'title' => $title,
			'body'  => $body,
		);
	}

	return $live;
}

/**
 * Fetch (or read from cache) a formatted forecast for the configured location.
 *
 * @since 1.4.1
 * @return array{title: string, body: string}|null
 */
function shadow_digest_weather_forecast(): ?array {
	$city = trim( (string) shadow_digest_get( 'shadow_digest_city' ) );
	$lat  = shadow_digest_weather_coord( 'shadow_digest_weather_lat' );
	$lon  = shadow_digest_weather_coord( 'shadow_digest_weather_lon' );
	$unit = (string) shadow_digest_get( 'shadow_digest_weather_units' );

	if ( '' === $city && ( null === $lat || null === $lon ) ) {
		return null;
	}

	$cache_key = 'shadow_digest_wx_' . md5(
		wp_json_encode(
			array(
				$city,
				$lat,
				$lon,
				$unit,
			)
		)
	);

	$cached = get_transient( $cache_key );
	if ( is_array( $cached ) && isset( $cached['title'], $cached['body'] ) ) {
		return array(
			'title' => (string) $cached['title'],
			'body'  => (string) $cached['body'],
		);
	}
	if ( is_array( $cached ) && isset( $cached['fail'] ) ) {
		return null;
	}

	$coords = shadow_digest_weather_resolve_coords( $city, $lat, $lon );
	if ( null === $coords ) {
		set_transient( $cache_key, array( 'fail' => 1 ), SHADOW_DIGEST_WEATHER_FAIL_TTL );
		return null;
	}

	$units = shadow_digest_weather_resolve_units( $unit, $coords['lon'] );
	$data  = shadow_digest_weather_fetch( $coords['lat'], $coords['lon'], $units );
	if ( null === $data ) {
		set_transient( $cache_key, array( 'fail' => 1 ), SHADOW_DIGEST_WEATHER_FAIL_TTL );
		return null;
	}

	$formatted = shadow_digest_weather_format( $data, $city !== '' ? $city : $coords['name'], $units );
	set_transient( $cache_key, $formatted, SHADOW_DIGEST_WEATHER_TTL );

	return $formatted;
}

/**
 * Read a latitude/longitude theme mod as a float, or null if unset/invalid.
 *
 * @since 1.4.1
 * @param string $key Theme mod key.
 * @return float|null
 */
function shadow_digest_weather_coord( string $key ): ?float {
	$raw = trim( (string) shadow_digest_get( $key ) );
	if ( '' === $raw || ! is_numeric( $raw ) ) {
		return null;
	}

	return (float) $raw;
}

/**
 * Resolve lat/lon, using the Customizer pair when both are set, else geocoding.
 *
 * @since 1.4.1
 * @param string     $city City of record label.
 * @param float|null $lat  Optional latitude override.
 * @param float|null $lon  Optional longitude override.
 * @return array{lat: float, lon: float, name: string}|null
 */
function shadow_digest_weather_resolve_coords( string $city, ?float $lat, ?float $lon ): ?array {
	if ( null !== $lat && null !== $lon ) {
		return array(
			'lat'  => $lat,
			'lon'  => $lon,
			'name' => $city,
		);
	}

	if ( '' === $city ) {
		return null;
	}

	$url = add_query_arg(
		array(
			'name'     => $city,
			'count'    => 1,
			'language' => 'en',
			'format'   => 'json',
		),
		'https://geocoding-api.open-meteo.com/v1/search'
	);

	$response = wp_remote_get(
		$url,
		array(
			'timeout' => 3,
			'headers' => array( 'Accept' => 'application/json' ),
		)
	);

	if ( is_wp_error( $response ) || 200 !== (int) wp_remote_retrieve_response_code( $response ) ) {
		return null;
	}

	$payload = json_decode( (string) wp_remote_retrieve_body( $response ), true );
	if ( ! is_array( $payload ) || empty( $payload['results'][0] ) || ! is_array( $payload['results'][0] ) ) {
		return null;
	}

	$result = $payload['results'][0];
	if ( ! isset( $result['latitude'], $result['longitude'] ) ) {
		return null;
	}

	return array(
		'lat'  => (float) $result['latitude'],
		'lon'  => (float) $result['longitude'],
		'name' => isset( $result['name'] ) ? (string) $result['name'] : $city,
	);
}

/**
 * Pick metric vs imperial. "auto" uses longitude: west of Greenwich → imperial.
 *
 * @since 1.4.1
 * @param string $unit Requested unit mode.
 * @param float  $lon  Longitude of the station.
 * @return string 'metric' or 'imperial'.
 */
function shadow_digest_weather_resolve_units( string $unit, float $lon ): string {
	if ( 'metric' === $unit || 'imperial' === $unit ) {
		return $unit;
	}

	// Rough but practical for our two papers: US longitudes are west → °F / mph.
	return $lon < -30.0 ? 'imperial' : 'metric';
}

/**
 * Call the Open-Meteo forecast endpoint.
 *
 * @since 1.4.1
 * @param float  $lat   Latitude.
 * @param float  $lon   Longitude.
 * @param string $units 'metric' or 'imperial'.
 * @return array<string, mixed>|null
 */
function shadow_digest_weather_fetch( float $lat, float $lon, string $units ): ?array {
	$args = array(
		'latitude'      => $lat,
		'longitude'     => $lon,
		'current'       => 'temperature_2m,relative_humidity_2m,weather_code,wind_speed_10m,wind_direction_10m',
		'daily'         => 'weather_code,temperature_2m_max,temperature_2m_min',
		'timezone'      => 'auto',
		'forecast_days' => 1,
	);

	if ( 'imperial' === $units ) {
		$args['temperature_unit'] = 'fahrenheit';
		$args['wind_speed_unit']  = 'mph';
	}

	$url = add_query_arg( $args, 'https://api.open-meteo.com/v1/forecast' );

	$response = wp_remote_get(
		$url,
		array(
			'timeout' => 3,
			'headers' => array( 'Accept' => 'application/json' ),
		)
	);

	if ( is_wp_error( $response ) || 200 !== (int) wp_remote_retrieve_response_code( $response ) ) {
		return null;
	}

	$payload = json_decode( (string) wp_remote_retrieve_body( $response ), true );
	if ( ! is_array( $payload ) || empty( $payload['current'] ) || ! is_array( $payload['current'] ) ) {
		return null;
	}

	return $payload;
}

/**
 * Turn an Open-Meteo payload into masthead ear copy.
 *
 * @since 1.4.1
 * @param array<string, mixed> $data  API payload.
 * @param string               $city  Display name.
 * @param string               $units 'metric' or 'imperial'.
 * @return array{title: string, body: string}
 */
function shadow_digest_weather_format( array $data, string $city, string $units ): array {
	$current = is_array( $data['current'] ) ? $data['current'] : array();
	$code    = isset( $current['weather_code'] ) ? (int) $current['weather_code'] : 0;
	$temp    = isset( $current['temperature_2m'] ) ? (float) $current['temperature_2m'] : 0.0;
	$humid   = isset( $current['relative_humidity_2m'] ) ? (int) $current['relative_humidity_2m'] : 0;
	$wind    = isset( $current['wind_speed_10m'] ) ? (float) $current['wind_speed_10m'] : 0.0;
	$wind_dir = isset( $current['wind_direction_10m'] ) ? (float) $current['wind_direction_10m'] : 0.0;

	$high = null;
	$low  = null;
	if ( isset( $data['daily'] ) && is_array( $data['daily'] ) ) {
		if ( isset( $data['daily']['temperature_2m_max'][0] ) ) {
			$high = (float) $data['daily']['temperature_2m_max'][0];
		}
		if ( isset( $data['daily']['temperature_2m_min'][0] ) ) {
			$low = (float) $data['daily']['temperature_2m_min'][0];
		}
	}

	$deg   = 'imperial' === $units ? '°F' : '°C';
	$speed = 'imperial' === $units ? 'mph' : 'km/h';
	$cond  = shadow_digest_weather_condition( $code );
	$compass = shadow_digest_weather_compass( $wind_dir );

	$line1 = sprintf(
		/* translators: 1: condition label, 2: temperature number, 3: degree unit (°C/°F). */
		__( '%1$s, %2$s%3$s.', 'broadside' ),
		$cond,
		shadow_digest_weather_round_temp( $temp ),
		$deg
	);

	$line2_parts = array();
	$line2_parts[] = sprintf(
		/* translators: 1: compass point (e.g. NE), 2: wind speed number, 3: unit (mph/km/h). */
		__( 'Wind %1$s at %2$s %3$s', 'broadside' ),
		$compass,
		shadow_digest_weather_round_wind( $wind ),
		$speed
	);

	if ( null !== $high && null !== $low ) {
		$line2_parts[] = sprintf(
			/* translators: 1: high temp, 2: low temp, 3: degree unit. */
			__( 'High %1$s%3$s / Low %2$s%3$s', 'broadside' ),
			shadow_digest_weather_round_temp( $high ),
			shadow_digest_weather_round_temp( $low ),
			$deg
		);
	} elseif ( $humid > 0 ) {
		$line2_parts[] = sprintf(
			/* translators: %d: relative humidity percent. */
			__( 'Humidity %d%%', 'broadside' ),
			$humid
		);
	}

	$body = $line1 . "\n" . implode( ' · ', $line2_parts );

	$title = '' !== $city
		? sprintf(
			/* translators: %s: city of record. */
			__( '%s Weather', 'broadside' ),
			$city
		)
		: __( 'Today\'s Weather', 'broadside' );

	return array(
		'title' => $title,
		'body'  => $body,
	);
}

/**
 * Round a temperature for display (whole degrees).
 *
 * @since 1.4.1
 * @param float $temp Temperature.
 * @return string
 */
function shadow_digest_weather_round_temp( float $temp ): string {
	return (string) (int) round( $temp );
}

/**
 * Round a wind speed for display.
 *
 * @since 1.4.1
 * @param float $wind Wind speed.
 * @return string
 */
function shadow_digest_weather_round_wind( float $wind ): string {
	return (string) (int) round( $wind );
}

/**
 * Map a WMO weather code to a short English label.
 *
 * @since 1.4.1
 * @param int $code WMO code.
 * @return string
 */
function shadow_digest_weather_condition( int $code ): string {
	$map = array(
		0  => __( 'Clear', 'broadside' ),
		1  => __( 'Mainly clear', 'broadside' ),
		2  => __( 'Partly cloudy', 'broadside' ),
		3  => __( 'Overcast', 'broadside' ),
		45 => __( 'Fog', 'broadside' ),
		48 => __( 'Icy fog', 'broadside' ),
		51 => __( 'Light drizzle', 'broadside' ),
		53 => __( 'Drizzle', 'broadside' ),
		55 => __( 'Heavy drizzle', 'broadside' ),
		61 => __( 'Light rain', 'broadside' ),
		63 => __( 'Rain', 'broadside' ),
		65 => __( 'Heavy rain', 'broadside' ),
		71 => __( 'Light snow', 'broadside' ),
		73 => __( 'Snow', 'broadside' ),
		75 => __( 'Heavy snow', 'broadside' ),
		77 => __( 'Snow grains', 'broadside' ),
		80 => __( 'Rain showers', 'broadside' ),
		81 => __( 'Rain showers', 'broadside' ),
		82 => __( 'Heavy showers', 'broadside' ),
		85 => __( 'Snow showers', 'broadside' ),
		86 => __( 'Heavy snow showers', 'broadside' ),
		95 => __( 'Thunderstorm', 'broadside' ),
		96 => __( 'Thunderstorm', 'broadside' ),
		99 => __( 'Severe thunderstorm', 'broadside' ),
	);

	return $map[ $code ] ?? __( 'Mixed conditions', 'broadside' );
}

/**
 * Convert wind degrees to a 16-point compass label.
 *
 * @since 1.4.1
 * @param float $degrees Wind direction in degrees.
 * @return string
 */
function shadow_digest_weather_compass( float $degrees ): string {
	$points = array( 'N', 'NNE', 'NE', 'ENE', 'E', 'ESE', 'SE', 'SSE', 'S', 'SSW', 'SW', 'WSW', 'W', 'WNW', 'NW', 'NNW' );
	$index  = (int) round( $degrees / 22.5 ) % 16;

	return $points[ $index ];
}

/**
 * Sanitise the weather units select.
 *
 * @since 1.4.1
 * @param mixed $value Submitted value.
 * @return string
 */
function shadow_digest_sanitize_weather_units( $value ): string {
	$value = (string) $value;
	$allowed = array( 'auto', 'metric', 'imperial' );

	return in_array( $value, $allowed, true ) ? $value : 'auto';
}

/**
 * Sanitise a latitude/longitude text field (empty or numeric).
 *
 * @since 1.4.1
 * @param mixed $value Submitted value.
 * @return string
 */
function shadow_digest_sanitize_coord( $value ): string {
	$value = trim( (string) $value );
	if ( '' === $value ) {
		return '';
	}
	if ( ! is_numeric( $value ) ) {
		return '';
	}

	return (string) (float) $value;
}
