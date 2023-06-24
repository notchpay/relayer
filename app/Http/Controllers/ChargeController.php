<?php

namespace App\Http\Controllers;

use App\Services\MTNCMPayoutService;
use Illuminate\Encryption\Encrypter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class ChargeController extends Controller
{
    public function handle(Request $request, $token)
    {
        // authenticate

        $r = Http::acceptJson()->withOptions(['verify' => false])->post('https://mtn-relay-server.notch.africa/api/authenticate', [
            'token' => $token,
        ]);

        if ($r->ok()) {

            $body = $r->json();

            $encryptor = new Encrypter($token, config('app.cipher'));

            $data = json_decode($encryptor->decrypt($body['salt']), true);

            $gateway = new MTNCMPayoutService($data['keys']);

            $r = $gateway->charge($data['data']);

            if (is_array($r)) {
                return response()->json($r);
            }
            abort(419);
        }

        abort(401);
    }
}
