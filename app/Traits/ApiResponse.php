<?php

namespace App\Traits;

trait ApiResponse
{
    /**
     * Send a success JSON response.
     */
    protected function success($data = null, $message = 'Success', $code = 200, $meta = [])
    {
        return response()->json([
            'success' => true,
            'message' => $message,
            'data'    => $data,
            'meta'    => (object) $meta, 
            'code'    => $code,
        ], $code);
    }

    /**
     * Send a generic error JSON response.
     */
    protected function error($message = 'Something went wrong', $code = 500, $data = null)
    {
        return response()->json([
            'success' => false,
            'message' => $message,
            'data'    => $data,
            'code'    => $code,
        ], $code);
    }

    /**
     * Send a validation error JSON response.
     */
    protected function validationError($errors = [], $message = 'Validation Error', $code = 422)
    {
        return response()->json([
            'success' => false,
            'message' => $message,
            'errors'  => $errors,
            'code'    => $code,
        ], $code);
    }

    /**
     * Send a bad request JSON response (for invalid inputs or logic errors).
     */
    protected function badRequest($message = 'Bad Request', $data = null, $code = 400)
    {
        return response()->json([
            'success' => false,
            'message' => $message,
            'data'    => $data,
            'code'    => $code,
        ], $code);
    }

    /**
     * Send an unauthorized response.
     */
    protected function unauthorized($message = 'Unauthorized', $code = 401)
    {
        return response()->json([
            'success' => false,
            'message' => $message,
            'code'    => $code,
        ], $code);
    }
}
