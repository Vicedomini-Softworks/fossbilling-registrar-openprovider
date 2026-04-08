<?php

/**
 * @copyright Vicedomini Softworks (https://www.vicedominisoftworks.com)
 * @license   Apache-2.0
 *
 * This source file is subject to the Apache 2.0 License that is bundled
 * with this source code in the file LICENSE
 */

class Registrar_Adapter_OpenProvider extends Registrar_AdapterAbstract
{
    public array $config = [
        'Username' => null,
        'Password' => null,
        'ApiUrl'   => null,
    ];

    private ?string $accessToken = null;

    public function __construct($options)
    {
        if (!empty($options['Username'])) {
            $this->config['Username'] = $options['Username'];
        } else {
            throw new Registrar_Exception('The ":domain_registrar" domain registrar is not fully configured. Please configure the :missing', [':domain_registrar' => 'OpenProvider', ':missing' => 'Username'], 3001);
        }

        if (!empty($options['Password'])) {
            $this->config['Password'] = $options['Password'];
        } else {
            throw new Registrar_Exception('The ":domain_registrar" domain registrar is not fully configured. Please configure the :missing', [':domain_registrar' => 'OpenProvider', ':missing' => 'Password'], 3001);
        }

        if (!empty($options['ApiUrl'])) {
            $this->config['ApiUrl'] = rtrim($options['ApiUrl'], '/');
        } else {
            throw new Registrar_Exception('The ":domain_registrar" domain registrar is not fully configured. Please configure the :missing', [':domain_registrar' => 'OpenProvider', ':missing' => 'API URL'], 3001);
        }
    }

    public static function getConfig()
    {
        return [
            'label' => 'OpenProvider',
            'form'  => [
                'Username' => [
                    'text',
                    [
                        'label'    => 'Username',
                        'required' => true,
                    ],
                ],
                'Password' => [
                    'password',
                    [
                        'label'    => 'Password',
                        'required' => true,
                    ],
                ],
                'ApiUrl' => [
                    'text',
                    [
                        'label'       => 'API URL',
                        'description' => 'Live: https://api.openprovider.eu | Sandbox: http://api.sandbox.openprovider.nl:8480',
                        'required'    => true,
                    ],
                ],
            ],
        ];
    }

    public function isDomainAvailable(Registrar_Domain $domain)
    {
        $response = $this->_request('POST', '/domains/check', [
            'domains' => [
                [
                    'name'      => $domain->getSld(),
                    'extension' => $this->_stripTld($domain),
                ],
            ],
        ]);

        return ($response['data']['results'][0]['status'] ?? '') === 'free';
    }

    public function isDomaincanBeTransferred(Registrar_Domain $domain)
    {
        $response = $this->_request('POST', '/domains/check', [
            'domains' => [
                [
                    'name'      => $domain->getSld(),
                    'extension' => $this->_stripTld($domain),
                ],
            ],
        ]);

        return ($response['data']['results'][0]['status'] ?? '') === 'active';
    }

    public function registerDomain(Registrar_Domain $domain)
    {
        $customerHandle = $this->_getOrCreateCustomer($domain->getContactAdmin());

        $response = $this->_request('POST', '/domains', [
            'domain' => [
                'name'      => $domain->getSld(),
                'extension' => $this->_stripTld($domain),
            ],
            'period'         => $domain->getRegistrationPeriod(),
            'owner_handle'   => $customerHandle,
            'admin_handle'   => $customerHandle,
            'tech_handle'    => $customerHandle,
            'billing_handle' => $customerHandle,
            'ns_group'       => 'dns-openprovider',
            'autorenew'      => 'default',
        ]);

        return ($response['code'] ?? -1) === 0;
    }

    public function renewDomain(Registrar_Domain $domain)
    {
        $domainId = $this->_getDomainId($domain);

        $response = $this->_request('POST', "/domains/{$domainId}/renew", [
            'domain' => [
                'name'      => $domain->getSld(),
                'extension' => $this->_stripTld($domain),
            ],
            'period' => $domain->getRegistrationPeriod(),
        ]);

        return ($response['code'] ?? -1) === 0;
    }

    public function transferDomain(Registrar_Domain $domain)
    {
        $customerHandle = $this->_getOrCreateCustomer($domain->getContactAdmin());

        $response = $this->_request('POST', '/domains/transfer', [
            'domain' => [
                'name'      => $domain->getSld(),
                'extension' => $this->_stripTld($domain),
            ],
            'period'         => $domain->getRegistrationPeriod(),
            'owner_handle'   => $customerHandle,
            'admin_handle'   => $customerHandle,
            'tech_handle'    => $customerHandle,
            'billing_handle' => $customerHandle,
            'ns_group'       => 'dns-openprovider',
            'autorenew'      => 'default',
            'auth_code'      => $domain->getEpp(),
        ]);

        return ($response['code'] ?? -1) === 0;
    }

    public function deleteDomain(Registrar_Domain $domain)
    {
        $domainId = $this->_getDomainId($domain);

        $response = $this->_request('DELETE', "/domains/{$domainId}", [
            'skip_soft_quarantine' => false,
            'force_delete'         => false,
            'type'                 => 'By user',
        ]);

        return ($response['code'] ?? -1) === 0;
    }

    public function getDomainDetails(Registrar_Domain $domain)
    {
        $domainId = $this->_getDomainId($domain);
        $response = $this->_request('GET', "/domains/{$domainId}");
        $opDomain = $response['data'];

        $domain->setRegistrationTime(strtotime($opDomain['creation_date']));
        $domain->setExpirationTime(strtotime($opDomain['expiration_date']));
        $domain->setPrivacyEnabled((bool) $opDomain['is_private_whois_enabled']);
        $domain->setLocked((bool) $opDomain['is_locked']);

        $nameservers = $opDomain['name_servers'] ?? [];
        $nsSetters = ['setNs1', 'setNs2', 'setNs3', 'setNs4'];
        foreach ($nsSetters as $i => $setter) {
            if (!empty($nameservers[$i]['name'])) {
                $domain->{$setter}($nameservers[$i]['name']);
            }
        }

        $adminHandle = $opDomain['admin_handle'] ?? $opDomain['owner_handle'] ?? null;
        if (!empty($adminHandle)) {
            $customer = $this->_getCustomer($adminHandle);
            $contact  = $this->_hydrateContact(new Registrar_Domain_Contact(), $customer);

            $domain->setContactRegistrar($contact);
            $domain->setContactAdmin($contact);
            $domain->setContactTech($contact);
            $domain->setContactBilling($contact);
        }

        return $domain;
    }

    public function getEpp(Registrar_Domain $domain)
    {
        $domainId = $this->_getDomainId($domain);
        $response = $this->_request('GET', "/domains/{$domainId}/authcode");

        return $response['data']['auth_code'] ?? '';
    }

    public function modifyNs(Registrar_Domain $domain)
    {
        $domainId = $this->_getDomainId($domain);

        $ns = [];
        foreach ([$domain->getNs1(), $domain->getNs2(), $domain->getNs3(), $domain->getNs4()] as $host) {
            if (!empty($host)) {
                $ns[] = ['name' => $host];
            }
        }

        $response = $this->_request('PUT', "/domains/{$domainId}", ['name_servers' => $ns]);

        return ($response['code'] ?? -1) === 0;
    }

    public function modifyContact(Registrar_Domain $domain)
    {
        $domainId       = $this->_getDomainId($domain);
        $customerHandle = $this->_getOrCreateCustomer($domain->getContactAdmin(), true);

        $response = $this->_request('PUT', "/domains/{$domainId}", [
            'owner_handle'   => $customerHandle,
            'admin_handle'   => $customerHandle,
            'tech_handle'    => $customerHandle,
            'billing_handle' => $customerHandle,
        ]);

        return ($response['code'] ?? -1) === 0;
    }

    public function lock(Registrar_Domain $domain)
    {
        $domainId = $this->_getDomainId($domain);
        $response = $this->_request('PUT', "/domains/{$domainId}", ['is_locked' => true]);

        return ($response['code'] ?? -1) === 0;
    }

    public function unlock(Registrar_Domain $domain)
    {
        $domainId = $this->_getDomainId($domain);
        $response = $this->_request('PUT', "/domains/{$domainId}", ['is_locked' => false]);

        return ($response['code'] ?? -1) === 0;
    }

    public function enablePrivacyProtection(Registrar_Domain $domain)
    {
        $domainId = $this->_getDomainId($domain);
        $response = $this->_request('PUT', "/domains/{$domainId}", ['is_private_whois_enabled' => true]);

        return ($response['code'] ?? -1) === 0;
    }

    public function disablePrivacyProtection(Registrar_Domain $domain)
    {
        $domainId = $this->_getDomainId($domain);
        $response = $this->_request('PUT', "/domains/{$domainId}", ['is_private_whois_enabled' => false]);

        return ($response['code'] ?? -1) === 0;
    }

    // -------------------------------------------------------------------------
    // Private helpers
    // -------------------------------------------------------------------------

    private function _stripTld(Registrar_Domain $domain): string
    {
        return $domain->getTld(false);
    }

    private function _getDomainId(Registrar_Domain $domain): int
    {
        $response = $this->_request('GET', '/domains', ['full_name' => $domain->getName()]);

        if (!empty($response['data']['results'])) {
            return (int) $response['data']['results'][0]['id'];
        }

        throw new Registrar_Exception('Domain not found in OpenProvider: ' . $domain->getName());
    }

    private function _getOrCreateCustomer(Registrar_Domain_Contact $contact, bool $updateExisting = false): string
    {
        $payload = [
            'email'        => $contact->getEmail(),
            'phone'        => [
                'country_code'      => $contact->getTelCc(),
                'area_code'         => '6',
                'subscriber_number' => $contact->getTel(),
            ],
            'company_name' => $contact->getCompany() ?? '',
            'address'      => [
                'street'  => $contact->getAddress1(),
                'zipcode' => $contact->getZip(),
                'city'    => $contact->getCity(),
                'state'   => $contact->getState(),
                'country' => $contact->getCountry(),
            ],
            'name'         => [
                'first_name' => $contact->getFirstName(),
                'last_name'  => $contact->getLastName(),
            ],
        ];

        $handle = $this->_findCustomerByEmail($contact->getEmail());

        if ($handle !== null) {
            if ($updateExisting) {
                $response = $this->_request('PUT', "/customers/{$handle}", $payload);
                if (($response['code'] ?? -1) !== 0) {
                    throw new Registrar_Exception('Failed to update customer: ' . ($response['desc'] ?? 'unknown error'));
                }
            }

            return $handle;
        }

        $response = $this->_request('POST', '/customers', $payload);

        if (isset($response['data']['handle'])) {
            return $response['data']['handle'];
        }

        throw new Registrar_Exception('Failed to create customer: ' . ($response['desc'] ?? 'unknown error'));
    }

    private function _findCustomerByEmail(string $email): ?string
    {
        $response = $this->_request('GET', '/customers', ['email_pattern' => $email]);

        if (!empty($response['data']['results'])) {
            return $response['data']['results'][0]['handle'];
        }

        return null;
    }

    private function _getCustomer(string $handle): array
    {
        $response = $this->_request('GET', "/customers/{$handle}");

        if (($response['code'] ?? -1) === 0 && !empty($response['data'])) {
            return $response['data'];
        }

        throw new Registrar_Exception('Failed to fetch customer: ' . $handle);
    }

    private function _hydrateContact(Registrar_Domain_Contact $contact, array $customer): Registrar_Domain_Contact
    {
        $contact->setFirstName($customer['name']['first_name'] ?? '');
        $contact->setLastName($customer['name']['last_name'] ?? '');
        $contact->setEmail($customer['email'] ?? '');
        $contact->setTelCc($customer['phone']['country_code'] ?? '');
        $contact->setTel($customer['phone']['subscriber_number'] ?? '');
        $contact->setAddress1($customer['address']['street'] ?? '');
        $contact->setCity($customer['address']['city'] ?? '');
        $contact->setState($customer['address']['state'] ?? '');
        $contact->setCountry($customer['address']['country'] ?? '');
        $contact->setZip($customer['address']['zipcode'] ?? '');
        $contact->setCompany($customer['company_name'] ?? '');

        return $contact;
    }

    private function _getAccessToken(): string
    {
        if ($this->accessToken !== null) {
            return $this->accessToken;
        }

        $client   = $this->getHttpClient();
        $response = $client->request('POST', $this->config['ApiUrl'] . '/v1beta/auth/login', [
            'json' => [
                'username' => $this->config['Username'],
                'password' => $this->config['Password'],
            ],
        ]);

        $data              = $response->toArray(false);
        $this->accessToken = $data['data']['token'] ?? null;

        if (empty($this->accessToken)) {
            throw new Registrar_Exception('OpenProvider authentication failed.');
        }

        return $this->accessToken;
    }

    private function _request(string $method, string $url, array $data = []): array
    {
        $token   = $this->_getAccessToken();
        $client  = $this->getHttpClient();
        $fullUrl = $this->config['ApiUrl'] . '/v1beta' . $url;

        $options = [
            'headers' => [
                'Authorization' => 'Bearer ' . $token,
                'Content-Type'  => 'application/json',
            ],
        ];

        if ($method === 'GET') {
            $options['query'] = $data;
        } else {
            $options['json'] = $data;
        }

        $response = $client->request($method, $fullUrl, $options);
        $result   = $response->toArray(false);

        $this->getLog()->info('OpenProvider API ' . $method . ' ' . $url . ' - response code: ' . ($result['code'] ?? 'n/a'));

        return $result;
    }
}
