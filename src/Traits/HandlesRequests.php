<?php

namespace Cangokdayi\WPFacades\Traits;

use WP_REST_Request;

/**
 * Helper methods for processing WP Rest API requests
 */
trait HandlesRequests
{
    /**
     * Returns a JSON error response template with the given values
     */
    public function errorTemplate(string $errorCode, string $message): array
    {
        return [
            'status' => 'ERROR',
            'error'  => [
                'type'    => $errorCode,
                'message' => $message
            ]
        ];
    }

    /**
     * Returns a JSON response template with the given arguments
     *
     * @param string|array $data Payload
     * @param string $status Status text - defaults to "OK"
     */
    public function responseTemplate($data, string $status = 'OK'): array
    {
        return [
            'status' => $status,
            'data'   => $data
        ];
    }

    /**
     * Processes and returns the payload of the given request
     * 
     * @return string|array
     */
    public function getPayload(WP_REST_Request $request)
    {
        return $request->is_json_content_type()
            ? $request->get_json_params()
            : $request->get_body();
    }
}
