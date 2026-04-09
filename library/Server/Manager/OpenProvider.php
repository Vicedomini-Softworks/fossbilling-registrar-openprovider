<?php

/**
 * @copyright Vicedomini Softworks (https://www.vicedominisoftworks.com)
 * @license   Apache-2.0
 *
 * This source file is subject to the Apache 2.0 License that is bundled
 * with this source code in the file LICENSE
 *
 * FOSSBilling Server Manager for OpenProvider non-domain products.
 *
 * Supported product types (set via package custom field "product_type"):
 *   ssl               — SSL certificates      (REST /v1beta/ssl/...)
 *   license_plesk     — Plesk licences        (XML-RPC legacy API)
 *   license_cloudlinux— CloudLinux licences   (XML-RPC legacy API)
 *   dns_zone          — DNS zone hosting      (REST /v1beta/dns/...)
 *
 * Server config fields (standard FOSSBilling "Add Server" form):
 *   host     — API hostname, e.g. api.openprovider.eu
 *   username — OpenProvider username
 *   password — OpenProvider password
 *   secure   — true for HTTPS (recommended)
 */
class Server_Manager_OpenProvider extends Server_Manager
{
    private ?string $accessToken = null;

    public static function getForm(): array
    {
        return ['label' => 'OpenProvider'];
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function _baseUrl(): string
    {
        $scheme = $this->_config['secure'] ? 'https' : 'http';
        return rtrim($scheme . '://' . $this->_config['host'], '/');
    }

    private function _getAccessToken(): string
    {
        if ($this->accessToken !== null) {
            return $this->accessToken;
        }

        $client   = $this->getHttpClient();
        $response = $client->request('POST', $this->_baseUrl() . '/v1beta/auth/login', [
            'json' => [
                'username' => $this->_config['username'],
                'password' => $this->_config['password'],
            ],
        ]);

        $data              = $response->toArray(false);
        $this->accessToken = $data['data']['token'] ?? null;

        if (empty($this->accessToken)) {
            throw new Server_Exception('OpenProvider authentication failed.');
        }

        return $this->accessToken;
    }

    private function _request(string $method, string $url, array $data = []): array
    {
        $token   = $this->_getAccessToken();
        $client  = $this->getHttpClient();
        $fullUrl = $this->_baseUrl() . '/v1beta' . $url;

        $options = [
            'headers' => [
                'Authorization' => 'Bearer ' . $token,
                'Content-Type'  => 'application/json',
            ],
        ];

        if ($method === 'GET' || $method === 'DELETE') {
            $options['query'] = $data;
        } else {
            $options['json'] = $data;
        }

        $response = $client->request($method, $fullUrl, $options);
        return $response->toArray(false);
    }

    private function _xmlRpcRequest(string $command, array $fields): array
    {
        $fieldXml = '';
        foreach ($fields as $key => $value) {
            $fieldXml .= '<' . $key . '>' . htmlspecialchars((string) $value, ENT_XML1) . '</' . $key . '>';
        }

        $xml = '<?xml version="1.0" encoding="UTF-8"?>'
             . '<openXML>'
             . '<credentials>'
             . '<username>' . htmlspecialchars($this->_config['username'], ENT_XML1) . '</username>'
             . '<password>' . htmlspecialchars($this->_config['password'], ENT_XML1) . '</password>'
             . '</credentials>'
             . '<module>'
             . '<request type="' . htmlspecialchars($command, ENT_XML1) . '">'
             . $fieldXml
             . '</request>'
             . '</module>'
             . '</openXML>';

        $client   = $this->getHttpClient();
        $response = $client->request('POST', $this->_baseUrl() . '/', [
            'headers' => ['Content-Type' => 'text/xml'],
            'body'    => $xml,
        ]);

        $body = $response->getContent(false);

        libxml_use_internal_errors(true);
        $parsed = simplexml_load_string($body);
        $errors = libxml_get_errors();
        libxml_clear_errors();

        if ($parsed === false || !empty($errors)) {
            throw new Server_Exception('Invalid XML response from OpenProvider.');
        }

        return json_decode(json_encode($parsed), true) ?? [];
    }

    private function _productType(Server_Account $account): string
    {
        return $account->getPackage()->getCustomValue('product_type') ?? 'ssl';
    }

    private function _findSslOrderId(string $domain): ?int
    {
        $response = $this->_request('GET', '/ssl/orders', ['common_name' => $domain]);
        $results  = $response['data']['results'] ?? [];

        return !empty($results) ? (int) $results[0]['id'] : null;
    }

    // ── Abstract implementations ──────────────────────────────────────────────

    public function testConnection(): bool
    {
        $this->_getAccessToken();
        return true;
    }

    public function getLoginUrl(?Server_Account $account = null): string
    {
        return 'https://cp.openprovider.eu';
    }

    public function getResellerLoginUrl(?Server_Account $account = null): string
    {
        return 'https://cp.openprovider.eu';
    }

    public function createAccount(Server_Account $account): bool
    {
        return match ($this->_productType($account)) {
            'ssl'                => $this->_createSslOrder($account),
            'license_plesk'      => $this->_createLicense($account, 'createPleskLicenseRequest'),
            'license_cloudlinux' => $this->_createLicense($account, 'createCloudLinuxLicenseRequest'),
            'dns_zone'           => $this->_createDnsZone($account),
            default              => throw new Server_Exception('OpenProvider: unsupported product type: ' . $this->_productType($account)),
        };
    }

    public function cancelAccount(Server_Account $account): bool
    {
        return match ($this->_productType($account)) {
            'ssl'                => $this->_cancelSslOrder($account),
            'license_plesk'      => $this->_deleteLicense($account, 'deletePleskLicenseRequest'),
            'license_cloudlinux' => $this->_deleteLicense($account, 'deleteCloudLinuxLicenseRequest'),
            'dns_zone'           => $this->_deleteDnsZone($account),
            default              => throw new Server_Exception('OpenProvider: unsupported product type: ' . $this->_productType($account)),
        };
    }

    public function synchronizeAccount(Server_Account $account): Server_Account
    {
        $type = $this->_productType($account);

        if ($type === 'ssl') {
            $orderId = $this->_findSslOrderId($account->getDomain());
            if ($orderId !== null) {
                $response = $this->_request('GET', '/ssl/orders/' . $orderId);
                $status   = $response['data']['status'] ?? '';
                $account->setSuspended($status !== 'ACT');
            }
        } elseif ($type === 'dns_zone') {
            $response = $this->_request('GET', '/dns/zones/' . $account->getDomain());
            $account->setSuspended(($response['code'] ?? -1) !== 0);
        }

        return $account;
    }

    public function suspendAccount(Server_Account $account): bool
    {
        $type = $this->_productType($account);
        if ($type === 'dns_zone') {
            return $this->_deleteDnsZone($account);
        }
        throw new Server_Exception('OpenProvider: suspend not supported for product type: ' . $type);
    }

    public function unsuspendAccount(Server_Account $account): bool
    {
        $type = $this->_productType($account);
        if ($type === 'dns_zone') {
            return $this->_createDnsZone($account);
        }
        throw new Server_Exception('OpenProvider: unsuspend not supported for product type: ' . $type);
    }

    public function changeAccountPackage(Server_Account $account, Server_Package $package): bool
    {
        $type = $this->_productType($account);
        if ($type !== 'ssl') {
            throw new Server_Exception('OpenProvider: package change not supported for product type: ' . $type);
        }

        $orderId = $this->_findSslOrderId($account->getDomain());
        if ($orderId === null) {
            throw new Server_Exception('SSL order not found for domain: ' . $account->getDomain());
        }

        $response = $this->_request('POST', '/ssl/orders/' . $orderId . '/renew', [
            'period' => (int) ($package->getCustomValue('period') ?? 1),
        ]);

        return ($response['code'] ?? -1) === 0;
    }

    public function changeAccountPassword(Server_Account $account, string $newPassword): bool
    {
        throw new Server_Exception('OpenProvider: password change not supported.');
    }

    public function changeAccountUsername(Server_Account $account, string $newUsername): bool
    {
        throw new Server_Exception('OpenProvider: username change not supported.');
    }

    public function changeAccountDomain(Server_Account $account, string $newDomain): bool
    {
        throw new Server_Exception('OpenProvider: domain change not supported.');
    }

    public function changeAccountIp(Server_Account $account, string $newIp): bool
    {
        throw new Server_Exception('OpenProvider: IP change not supported.');
    }

    // ── SSL ───────────────────────────────────────────────────────────────────

    private function _createSslOrder(Server_Account $account): bool
    {
        $package   = $account->getPackage();
        $productId = (int) ($package->getCustomValue('ssl_product_id') ?? 0);
        $period    = (int) ($package->getCustomValue('period') ?? 1);
        $approver  = $package->getCustomValue('approver_email')
                  ?? $account->getClient()?->getEmail()
                  ?? '';
        $csr       = $account->getNote() ?? '';

        if (empty($csr)) {
            $csrResponse = $this->_request('POST', '/ssl/csr', [
                'domain'       => $account->getDomain(),
                'organization' => $account->getClient()?->getCompany()
                               ?? $account->getClient()?->getFullName()
                               ?? '',
                'country'      => $account->getClient()?->getCountry() ?? 'NL',
            ]);
            $csr = $csrResponse['data']['csr'] ?? '';
            if (empty($csr)) {
                throw new Server_Exception('Failed to generate CSR for SSL order.');
            }
        }

        $response = $this->_request('POST', '/ssl/orders', [
            'product_id'     => $productId,
            'period'         => $period,
            'domain'         => $account->getDomain(),
            'csr'            => $csr,
            'approver_email' => $approver,
            'software_type'  => 'other',
        ]);

        return ($response['code'] ?? -1) === 0;
    }

    private function _cancelSslOrder(Server_Account $account): bool
    {
        $orderId = $this->_findSslOrderId($account->getDomain());
        if ($orderId === null) {
            return true;
        }

        $response = $this->_request('POST', '/ssl/orders/' . $orderId, ['status' => 'cancelled']);
        return ($response['code'] ?? -1) === 0;
    }

    // ── Licenze (XML-RPC) ─────────────────────────────────────────────────────

    private function _createLicense(Server_Account $account, string $command): bool
    {
        $result = $this->_xmlRpcRequest($command, [
            'ip'   => $account->getIp() ?? $account->getDomain(),
            'type' => $account->getPackage()->getCustomValue('license_type') ?? '',
        ]);

        return isset($result['reply']['code']) && (int) $result['reply']['code'] === 0;
    }

    private function _deleteLicense(Server_Account $account, string $command): bool
    {
        $result = $this->_xmlRpcRequest($command, [
            'ip' => $account->getIp() ?? $account->getDomain(),
        ]);

        return isset($result['reply']['code']) && (int) $result['reply']['code'] === 0;
    }

    // ── DNS zone ──────────────────────────────────────────────────────────────

    private function _createDnsZone(Server_Account $account): bool
    {
        $response = $this->_request('POST', '/dns/zones', [
            'name' => $account->getDomain(),
            'type' => 'master',
        ]);

        return ($response['code'] ?? -1) === 0;
    }

    private function _deleteDnsZone(Server_Account $account): bool
    {
        $response = $this->_request('DELETE', '/dns/zones/' . $account->getDomain());
        return ($response['code'] ?? -1) === 0;
    }
}
