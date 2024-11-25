<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class SettingsController extends Controller
{
    public function settingsButton(): JsonResponse
    {
        $iframeUrl = "https://www.hubdo.com/"; // The URL to display in the iframe
        return response()->json(['iframeUrl' => $iframeUrl]);
    }
}