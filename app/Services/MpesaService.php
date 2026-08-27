<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class MpesaService
{
    protected $consumerKey;
    protected $consumerSecret;
    protected $passkey;
    protected $shortcode;
    protected $env;
    protected $baseUrl;

    public function __construct()
    {
        $this->consumerKey = env('MPESA_CONSUMER_KEY');
        $this->consumerSecret = env('MPESA_CONSUMER_SECRET');
        $this->passkey = env('MPESA_PASSKEY');
        $this->shortcode = env('MPESA_SHORTCODE');
        $this->env = env('MPESA_ENV', 'sandbox');
        
        $this->baseUrl = $this->env === 'live' 
            ? 'https://api.safaricom.co.ke' 
            : 'https://sandbox.safaricom.co.ke';
    }

    public function generateAccessToken()
    {
        $credentials = base64_encode($this->consumerKey . ':' . $this->consumerSecret);

        $response = Http::withHeaders([
            'Authorization' => 'Basic ' . $credentials,
        ])->get($this->baseUrl . '/oauth/v1/generate?grant_type=client_credentials');

        if ($response->successful()) {
            return $response->json('access_token');
        }

        Log::error('M-Pesa Access Token Error: ' . $response->body());
        throw new \Exception('Failed to generate M-Pesa Access Token');
    }

    public function initiateStkPush($phoneNumber, $amount, $reference, $description)
    {
        $accessToken = $this->generateAccessToken();
        
        // Format phone number to 2547XXXXXXXX
        if (str_starts_with($phoneNumber, '0')) {
            $phoneNumber = '254' . substr($phoneNumber, 1);
        } elseif (str_starts_with($phoneNumber, '+')) {
            $phoneNumber = substr($phoneNumber, 1);
        }

        $timestamp = date('YmdHis');
        // Password = base64_encode(Shortcode + Passkey + Timestamp)
        $password = base64_encode($this->shortcode . $this->passkey . $timestamp);

        $callbackUrl = env('APP_URL') . '/api/webhooks/mpesa/callback';

        // Safaricom strictly rejects 'localhost' or '127.0.0.1' in the CallBackURL.
        // If testing locally without Ngrok, we must pass a dummy valid URL so the request succeeds.
        if (str_contains($callbackUrl, 'localhost') || str_contains($callbackUrl, '127.0.0.1')) {
            $callbackUrl = 'https://mydummy-api-chapplus.com/api/webhooks/mpesa/callback';
        }

        $payload = [
            'BusinessShortCode' => $this->shortcode,
            'Password' => $password,
            'Timestamp' => $timestamp,
            'TransactionType' => 'CustomerPayBillOnline',
            'Amount' => round($amount), // Mpesa only accepts whole numbers
            'PartyA' => $phoneNumber,
            'PartyB' => $this->shortcode,
            'PhoneNumber' => $phoneNumber,
            'CallBackURL' => $callbackUrl,
            'AccountReference' => substr($reference, 0, 12),
            'TransactionDesc' => substr($description, 0, 12)
        ];

        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $accessToken,
            'Content-Type' => 'application/json'
        ])->post($this->baseUrl . '/mpesa/stkpush/v1/processrequest', $payload);

        if ($response->successful()) {
            return $response->json();
        }

        Log::error('M-Pesa STK Push Error: ' . $response->body());
        throw new \Exception('Failed to initiate STK push: ' . $response->body());
    }
}
