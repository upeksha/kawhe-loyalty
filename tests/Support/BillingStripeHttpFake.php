<?php

namespace Tests\Support;

use Stripe\HttpClient\ClientInterface;

class BillingStripeHttpFake implements ClientInterface
{
    public array $requests = [];

    public $respond;

    public function request($method, $absUrl, $headers, $params, $hasFile, $apiMode = 'v1', $maxNetworkRetries = null)
    {
        $path = parse_url($absUrl, PHP_URL_PATH);
        $this->requests[] = [$method, $path, $params];
        $response = $this->respond ? ($this->respond)($method, $path, $params) : null;
        if ($response !== null) {
            return $response;
        }
        if ($path === '/v1/customers/cus_deletion_test') {
            $body = ['id' => 'cus_deletion_test', 'object' => 'customer'];
            if ($method === 'delete') {
                $body['deleted'] = true;
            }
        } else {
            $body = ['object' => 'list', 'url' => $path, 'data' => [], 'has_more' => false];
        }

        return [json_encode($body), 200, []];
    }

    public function collection(array $data, string $path, bool $more = false): array
    {
        return [json_encode(['object' => 'list', 'url' => $path, 'data' => $data, 'has_more' => $more]), 200, []];
    }
}
