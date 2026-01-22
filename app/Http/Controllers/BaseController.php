<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;

class BaseController extends Controller
{

    /**
     * success response method.
     *
     * @param $result
     * @param $message
     * @return JsonResponse
     */
    public function sendResponse($result, $message, $extra = [], $statusCode = 200): JsonResponse
    {
        $response = [
            'success' => true,
            'data' => $result,
            'message' => $message,
        ];
        if (!empty($extra)) {
            $response = array_merge($response, $extra);
        }
        return response()->json($response, $statusCode);
    }


    /**
     * return error response.
     *
     * @param $error
     * @param array $errorMessages
     * @return JsonResponse
     */
    public function sendError($error, $errorMessages = [], $errorCode = 200, $statusCode = 0): JsonResponse
    {
        $response = [
            'success' => false,
            'status_code' => $statusCode,
            'message' => $error,
        ];
        if (!empty($errorMessages)) {
            $response['data'] = $errorMessages;
        }
        return response()->json($response)->setStatusCode($errorCode);
    }

    /**
     * return failed response.
     *
     * @return JsonResponse
     */
    public function failedRequest()
    {
        $response = [
            'success' => false,
            'message' => 'Something Went Wrong',
        ];

        return response()->json($response);
    }
}
