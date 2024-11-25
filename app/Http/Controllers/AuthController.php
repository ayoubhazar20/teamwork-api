<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\Token;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class AuthController extends Controller
{
    public function redirectToHubSpot()
    {
        $clientId = env('HUBSPOT_CLIENT_ID');
        $redirectUri = urlencode(env('HUBSPOT_REDIRECT_URI'));
        $scopes = urlencode('crm.objects.contacts.read crm.objects.contacts.write crm.schemas.contacts.read crm.schemas.contacts.write crm.objects.users.read');

        $authorizationUrl = "https://app.hubspot.com/oauth/authorize?client_id={$clientId}&scope={$scopes}&redirect_uri={$redirectUri}";

        return redirect($authorizationUrl); // Redirect to HubSpot
    }
    public function handleHubSpotCallback(Request $request)
    {
        $code = $request->query('code');

        // Exchange the code for an access token
        $response = Http::asForm()->post('https://api.hubapi.com/oauth/v1/token', [
            'client_id' => env('HUBSPOT_CLIENT_ID'),
            'client_secret' => env('HUBSPOT_CLIENT_SECRET'),
            'redirect_uri' => env('HUBSPOT_REDIRECT_URI'),
            'grant_type' => 'authorization_code',
            'code' => $code,
        ]);

        if ($response->failed()) {
            return response()->json(['error' => 'Failed to get access token'], 500);
        }

        $data = $response->json();
        $accessToken = $data['access_token'];
        $refreshToken = $data['refresh_token'];

        // Fetch account details, including portal ID
        $accountInfoResponse = Http::withHeaders([
            'Authorization' => "Bearer {$accessToken}"
        ])->get('https://api.hubapi.com/account-info/v3/details'); // Fetch account information

        if ($accountInfoResponse->failed()) {
            return response()->json(['error' => 'Failed to fetch account information from HubSpot'], 500);
        }

        $accountInfo = $accountInfoResponse->json();
        $portalId = $accountInfo['portalId']; // Portal ID

        // Fetch all users associated with the HubSpot account
        $usersResponse = Http::withHeaders([
            'Authorization' => "Bearer {$accessToken}"
        ])->get('https://api.hubapi.com/settings/v3/users'); // Fetch users

        if ($usersResponse->failed()) {
            return response()->json(['error' => 'Failed to fetch users from HubSpot'], 500);
        }

        $usersData = $usersResponse->json();

        // Store users and link them with the portal ID
        foreach ($usersData['results'] as $userData) {
            // Check if a user already exists with the specified HubSpot ID and portal ID
            $existingUser = User::where('user_id', $userData['id'])
                ->where('portal_id', $portalId)
                ->first();

            if ($existingUser) {
                // Update the existing user if found
                $existingUser->update([
                    'email' => $userData['email'],
                    'first_name' => $userData['firstName'],
                    'last_name' => $userData['lastName'],
                    'super_admin' => $userData['superAdmin'], // Update super admin status
                    // Update any other fields as necessary
                ]);
            } else {


                // Create a new user if not found
                User::create([
                    // HubSpot user ID
                    'user_id' => $userData['id'], // HubSpot user ID
                    'email' => $userData['email'],
                    'first_name' => $userData['firstName'],
                    'last_name' => $userData['lastName'],
                    'portal_id' => $portalId, // Link with the portal ID
                    'super_admin' => $userData['superAdmin'], // Storing the super admin status
                    // Add any other fields as necessary
                ]);
            }
        }

        $existingCompany = Company::where('company_id', $portalId)->first(); // Assuming you have a Company model

        if (!$existingCompany) {
            // Step 6: Create the company in your database
            $company = new Company(); // Replace with your actual model name
            $company->company_id = $portalId; // Set the portal ID
            // Add any other necessary fields here
            $company->save(); // Save to the database
        }
        // Store the access token and refresh token in your database
        Token::updateOrCreate(
            ['portal_id' => $portalId], // Use the portal ID as a unique identifier
            [
                'access_token' => 1231,
                'refresh_token' => $refreshToken,
                'expires_at' => now(),
            ]
        );

        return response()->json([
            'message' => 'Authenticated successfully',
            'portal_id' => $portalId,
        ]);
    }



    public function fetchUsers(Request $request)
    {
        $userId = $request->query('user_id');
        $portalId = $request->query('portal_id');

        // Find the user by user_id and portal_id
        $user = User::where('user_id', $userId)
            ->where('portal_id', $portalId)
            ->first();

        if (!$user) {
            return response()->json(['error' => 'User not found'], 404);
        }
        // Retrieve the company associated with the portalId
        $company = Company::where('company_id', $portalId)->first();

        if (!$company) {
            return response()->json(['error' => 'Company not found'], 404);
        }

        // Check if access is granted
        if (!$company->access_granted) {
            return response()->json(['error' => 'Access denied for this company'], 403); // 403 Forbidden
        }


        // Retrieve the token for the portal
        $token = Token::where('portal_id', $user->portal_id)->first();
        if (!$token || !$token->access_token) {
            return response()->json(['error' => 'Access token not found'], 401);
        }

        // Check if the access token is expired
        if (now()->greaterThan($token->expires_at)) {
            // Token is expired, refresh it
            $refreshResponse = $this->refreshAccessToken(new Request(['portal_id' => $portalId]));

            // Check if the refresh was successful
            if ($refreshResponse->status() !== 200) {
                return response()->json(['error' => 'Failed to refresh access token'], $refreshResponse->status());
            }

            // Update the token variable with the new access token
            $token->access_token = $refreshResponse->json()['access_token'];
        }

        // Fetch users from HubSpot
        $response = Http::withHeaders([
            'Authorization' => "Bearer {$token->access_token}"
        ])->get('https://api.hubapi.com/settings/v3/users');

        if ($response->failed()) {
            return response()->json(['error' => 'Failed to fetch users'], 500);
        }

        return response()->json($response->json());
    }



    public function refreshAccessToken(Request $request)
    {
        $portalId = $request->query('portal_id');


        $token = Token::where('portal_id', $portalId)->first();
        if (!$token || !$token->refresh_token) {
            return response()->json(['error' => 'Refresh token not found'], 401);
        }

        $response = Http::asForm()->post('https://api.hubapi.com/oauth/v1/token', [
            'client_id' => env('HUBSPOT_CLIENT_ID'),
            'client_secret' => env('HUBSPOT_CLIENT_SECRET'),
            'grant_type' => 'refresh_token',
            'refresh_token' => $token->refresh_token,
        ]);

        if ($response->failed()) {
            return response()->json(['error' => 'Failed to refresh access token'], 500);
        }

        $data = $response->json();
        $newAccessToken = $data['access_token'];
        $newRefreshToken = $data['refresh_token'];

        // Update tokens in the database
        $token->update([
            'access_token' => $newAccessToken,
            'refresh_token' => $newRefreshToken,
            'expires_at' => now()->addSeconds($data['expires_in']),
        ]);

        return response()->json(['access_token' => $newAccessToken]);
    }
}