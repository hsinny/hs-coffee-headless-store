<?php

if ( ! function_exists( 'create_rest_response' ) ) {
	function create_rest_response( $code, $message, $status ) {
		return new WP_REST_Response(
			array(
				'code'    => $code,
				'message' => $message,
				'data'    => array(
					'status' => $status,
				),
			),
			$status
		);
	}
}
