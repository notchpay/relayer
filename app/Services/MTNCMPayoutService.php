<?php

namespace App\Services;

use GuzzleHttp\Client;
use Illuminate\Support\Facades\Http;

class MTNCMPayoutService
{
    protected $api_user;

    protected $api_key;

    protected $primary_key;

    public function __construct(array $keys)
    {
        $this->api_key = $keys['api_key'];
        $this->api_user = $keys['api_user'];
        $this->primary_key = $keys['primary_key'];
    }

    public function getToken()
    {

        $r = Http::withBasicAuth($this->api_user, $this->api_key)->withOptions(['verify' => false])->withHeaders([
            'Ocp-Apim-Subscription-Key' => $this->primary_key,
        ])->post('https://proxy.momoapi.mtn.com/disbursement/token/');

        if ($r->ok()) {
            return $r->json()['access_token'];
        }

        return false;
    }

    public function charge(array $data)
    {
        if ($token = $this->getToken()) {
            $_data = [
                'amount' => (string) $data['amount'],
                'currency' => 'XAF',
                'externalId' => isset($data['reference']) ? $data['reference'] : str()->random(12),
                'payer' => [
                    'partyIdType' => 'MSISDN',
                    'partyId' => '237'.$data['phone'],
                ],
                'payerMessage' => $data['description'],
                'payeeNote' => 'Notch Pay',
            ];

            $provider_token = (string) str()->orderedUuid();

            $client = new Client();
            $headers = [
                'Authorization' => 'Bearer '.$token,
                'X-Reference-Id' => $provider_token,
                'X-Target-Environment' => 'mtncameroon',
                'Ocp-Apim-Subscription-Key' => $this->primary_key,
                'X-Callback-Url' => 'https://api.notchpay.co/wk/p/cm.mtn',
                'Content-Type' => 'application/json',
            ];

            try {
                $res = Http::acceptJson()->withBasicAuth($this->api_user, $this->api_key)->withOptions(['verify' => true])->withHeaders($headers)->post('https://proxy.momoapi.mtn.com/disbursement/v2_0/deposit', $_data);
                if ($res->ok()) {
                    return $res->json();
                }
                ray($res->status());

                return false;

            } catch (\Exception $e) {
                \Log::debug($e);
                ray($e);

                return false;
            }
        }

        return false;
    }
}
