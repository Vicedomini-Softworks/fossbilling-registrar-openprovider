<?php

namespace Tests;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

class OpenProviderTest extends TestCase
{
    /** @var \Registrar_Adapter_OpenProvider&MockObject */
    private \Registrar_Adapter_OpenProvider $adapter;

    /** @var HttpClientInterface&MockObject */
    private HttpClientInterface $httpClient;

    private const VALID_OPTIONS = [
        'Username' => 'testuser',
        'Password' => 'testpass',
        'ApiUrl'   => 'https://api.test',
    ];

    protected function setUp(): void
    {
        $this->httpClient = $this->createMock(HttpClientInterface::class);

        $this->adapter = $this->getMockBuilder(\Registrar_Adapter_OpenProvider::class)
            ->setConstructorArgs([self::VALID_OPTIONS])
            ->onlyMethods(['getHttpClient', 'getLog'])
            ->getMock();

        $this->adapter->method('getHttpClient')->willReturn($this->httpClient);
        $this->adapter->method('getLog')->willReturn($this->createMock(\Box_Log::class));
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function mockResponse(array $data): ResponseInterface
    {
        $response = $this->createMock(ResponseInterface::class);
        $response->method('toArray')->willReturn($data);
        return $response;
    }

    /**
     * Queue consecutive HTTP responses. The first is always the auth request.
     */
    private function queueResponses(array ...$apiResponses): void
    {
        $all = array_map(
            fn(array $data) => $this->mockResponse($data),
            $apiResponses
        );
        $this->httpClient->method('request')->willReturnOnConsecutiveCalls(...$all);
    }

    private function auth(): array
    {
        return ['data' => ['token' => 'test-bearer-token']];
    }

    private function domainIdResult(int $id = 42): array
    {
        return ['code' => 0, 'data' => ['results' => [['id' => $id]]]];
    }

    private function customerResult(string $handle = 'OP-HANDLE-1'): array
    {
        return ['data' => ['results' => [['handle' => $handle]]]];
    }

    private function ok(): array
    {
        return ['code' => 0];
    }

    private function apiError(string $desc = 'API error'): array
    {
        return ['code' => 1, 'desc' => $desc];
    }

    private function makeDomain(string $sld = 'example', string $tld = '.com'): \Registrar_Domain
    {
        $contact = (new \Registrar_Domain_Contact())
            ->setFirstName('John')
            ->setLastName('Doe')
            ->setEmail('john@example.com')
            ->setTelCc('+1')
            ->setTel('5551234567')
            ->setAddress1('123 Main St')
            ->setCity('Springfield')
            ->setState('IL')
            ->setCountry('US')
            ->setZip('62701')
            ->setCompany('ACME Corp');

        return (new \Registrar_Domain())
            ->setSld($sld)
            ->setTld($tld)
            ->setRegistrationPeriod(1)
            ->setEpp('transfer-epp-code')
            ->setContactAdmin($contact)
            ->setContactRegistrar($contact)
            ->setContactTech($contact)
            ->setContactBilling($contact);
    }

    // -------------------------------------------------------------------------
    // Constructor
    // -------------------------------------------------------------------------

    public function testConstructorAcceptsValidOptions(): void
    {
        $adapter = new \Registrar_Adapter_OpenProvider(self::VALID_OPTIONS);
        $this->assertInstanceOf(\Registrar_Adapter_OpenProvider::class, $adapter);
    }

    public function testConstructorThrowsWhenUsernameMissing(): void
    {
        $this->expectException(\Registrar_Exception::class);
        new \Registrar_Adapter_OpenProvider(['Password' => 'p', 'ApiUrl' => 'http://api']);
    }

    public function testConstructorThrowsWhenUsernameEmpty(): void
    {
        $this->expectException(\Registrar_Exception::class);
        new \Registrar_Adapter_OpenProvider(['Username' => '', 'Password' => 'p', 'ApiUrl' => 'http://api']);
    }

    public function testConstructorThrowsWhenPasswordMissing(): void
    {
        $this->expectException(\Registrar_Exception::class);
        new \Registrar_Adapter_OpenProvider(['Username' => 'u', 'ApiUrl' => 'http://api']);
    }

    public function testConstructorThrowsWhenApiUrlMissing(): void
    {
        $this->expectException(\Registrar_Exception::class);
        new \Registrar_Adapter_OpenProvider(['Username' => 'u', 'Password' => 'p']);
    }

    // -------------------------------------------------------------------------
    // getConfig
    // -------------------------------------------------------------------------

    public function testGetConfigReturnsRequiredFields(): void
    {
        $config = \Registrar_Adapter_OpenProvider::getConfig();

        $this->assertIsArray($config);
        $this->assertArrayHasKey('label', $config);
        $this->assertArrayHasKey('form', $config);
        $this->assertArrayHasKey('Username', $config['form']);
        $this->assertArrayHasKey('Password', $config['form']);
        $this->assertArrayHasKey('ApiUrl', $config['form']);
    }

    public function testGetConfigFormFieldsAreRequired(): void
    {
        $form = \Registrar_Adapter_OpenProvider::getConfig()['form'];

        $this->assertTrue($form['Username'][1]['required']);
        $this->assertTrue($form['Password'][1]['required']);
        $this->assertTrue($form['ApiUrl'][1]['required']);
    }

    // -------------------------------------------------------------------------
    // Authentication
    // -------------------------------------------------------------------------

    public function testThrowsWhenAuthTokenMissing(): void
    {
        $this->queueResponses(['data' => []]);

        $this->expectException(\Registrar_Exception::class);
        $this->adapter->isDomainAvailable($this->makeDomain());
    }

    public function testTokenIsCachedAcrossRequests(): void
    {
        // Two calls on the same adapter instance should trigger only one auth request.
        $this->httpClient
            ->expects($this->exactly(3))  // 1 auth + 2 domain checks
            ->method('request')
            ->willReturnOnConsecutiveCalls(
                $this->mockResponse($this->auth()),
                $this->mockResponse(['data' => ['results' => [['status' => 'free']]]]),
                $this->mockResponse(['data' => ['results' => [['status' => 'active']]]])
            );

        $this->assertTrue($this->adapter->isDomainAvailable($this->makeDomain()));
        $this->assertFalse($this->adapter->isDomainAvailable($this->makeDomain()));
    }

    // -------------------------------------------------------------------------
    // isDomainAvailable
    // -------------------------------------------------------------------------

    public function testIsDomainAvailableReturnsTrueWhenFree(): void
    {
        $this->queueResponses(
            $this->auth(),
            ['data' => ['results' => [['status' => 'free']]]]
        );

        $this->assertTrue($this->adapter->isDomainAvailable($this->makeDomain()));
    }

    public function testIsDomainAvailableReturnsFalseWhenActive(): void
    {
        $this->queueResponses(
            $this->auth(),
            ['data' => ['results' => [['status' => 'active']]]]
        );

        $this->assertFalse($this->adapter->isDomainAvailable($this->makeDomain()));
    }

    public function testIsDomainAvailableReturnsFalseOnEmptyResults(): void
    {
        $this->queueResponses(
            $this->auth(),
            ['data' => ['results' => []]]
        );

        $this->assertFalse($this->adapter->isDomainAvailable($this->makeDomain()));
    }

    // -------------------------------------------------------------------------
    // isDomaincanBeTransferred
    // -------------------------------------------------------------------------

    public function testIsDomainCanBeTransferredReturnsTrueWhenActive(): void
    {
        $this->queueResponses(
            $this->auth(),
            ['data' => ['results' => [['status' => 'active']]]]
        );

        $this->assertTrue($this->adapter->isDomaincanBeTransferred($this->makeDomain()));
    }

    public function testIsDomainCanBeTransferredReturnsFalseWhenFree(): void
    {
        $this->queueResponses(
            $this->auth(),
            ['data' => ['results' => [['status' => 'free']]]]
        );

        $this->assertFalse($this->adapter->isDomaincanBeTransferred($this->makeDomain()));
    }

    // -------------------------------------------------------------------------
    // registerDomain
    // -------------------------------------------------------------------------

    public function testRegisterDomainReturnsTrueWhenCustomerExists(): void
    {
        $this->queueResponses(
            $this->auth(),
            $this->customerResult(),
            $this->ok()
        );

        $this->assertTrue($this->adapter->registerDomain($this->makeDomain()));
    }

    public function testRegisterDomainCreatesNewCustomerWhenNotFound(): void
    {
        $this->queueResponses(
            $this->auth(),
            ['data' => ['results' => []]],
            ['code' => 0, 'data' => ['handle' => 'NEW-1']],
            $this->ok()
        );

        $this->assertTrue($this->adapter->registerDomain($this->makeDomain()));
    }

    public function testRegisterDomainReturnsFalseOnApiFailure(): void
    {
        $this->queueResponses(
            $this->auth(),
            $this->customerResult(),
            $this->apiError()
        );

        $this->assertFalse($this->adapter->registerDomain($this->makeDomain()));
    }

    public function testRegisterDomainThrowsWhenCustomerCreationFails(): void
    {
        $this->queueResponses(
            $this->auth(),
            ['data' => ['results' => []]],
            ['code' => 1, 'desc' => 'Invalid data']
        );

        $this->expectException(\Registrar_Exception::class);
        $this->adapter->registerDomain($this->makeDomain());
    }

    // -------------------------------------------------------------------------
    // renewDomain
    // -------------------------------------------------------------------------

    public function testRenewDomainReturnsTrueOnSuccess(): void
    {
        $this->queueResponses($this->auth(), $this->domainIdResult(), $this->ok());
        $this->assertTrue($this->adapter->renewDomain($this->makeDomain()));
    }

    public function testRenewDomainReturnsFalseOnApiFailure(): void
    {
        $this->queueResponses($this->auth(), $this->domainIdResult(), $this->apiError());
        $this->assertFalse($this->adapter->renewDomain($this->makeDomain()));
    }

    public function testRenewDomainThrowsWhenDomainNotFound(): void
    {
        $this->queueResponses(
            $this->auth(),
            ['code' => 0, 'data' => ['results' => []]]
        );

        $this->expectException(\Registrar_Exception::class);
        $this->adapter->renewDomain($this->makeDomain());
    }

    // -------------------------------------------------------------------------
    // transferDomain
    // -------------------------------------------------------------------------

    public function testTransferDomainReturnsTrueOnSuccess(): void
    {
        $this->queueResponses(
            $this->auth(),
            $this->customerResult(),
            $this->ok()
        );

        $this->assertTrue($this->adapter->transferDomain($this->makeDomain()));
    }

    public function testTransferDomainReturnsFalseOnApiFailure(): void
    {
        $this->queueResponses(
            $this->auth(),
            $this->customerResult(),
            $this->apiError()
        );

        $this->assertFalse($this->adapter->transferDomain($this->makeDomain()));
    }

    // -------------------------------------------------------------------------
    // deleteDomain
    // -------------------------------------------------------------------------

    public function testDeleteDomainReturnsTrueOnSuccess(): void
    {
        $this->queueResponses($this->auth(), $this->domainIdResult(), $this->ok());
        $this->assertTrue($this->adapter->deleteDomain($this->makeDomain()));
    }

    public function testDeleteDomainReturnsFalseOnApiFailure(): void
    {
        $this->queueResponses($this->auth(), $this->domainIdResult(), $this->apiError());
        $this->assertFalse($this->adapter->deleteDomain($this->makeDomain()));
    }

    // -------------------------------------------------------------------------
    // getDomainDetails
    // -------------------------------------------------------------------------

    public function testGetDomainDetailsPopulatesDomainObject(): void
    {
        $this->queueResponses(
            $this->auth(),
            $this->domainIdResult(99),
            [
                'code' => 0,
                'data' => [
                    'creation_date'            => '2020-03-15 10:00:00',
                    'expiration_date'          => '2026-03-15 10:00:00',
                    'is_private_whois_enabled' => true,
                    'is_locked'                => false,
                    'name_servers'             => [
                        ['name' => 'ns1.openprovider.nl'],
                        ['name' => 'ns2.openprovider.nl'],
                        ['name' => 'ns3.openprovider.nl'],
                    ],
                    'admin_handle' => 'OP-ADM-42',
                ],
            ],
            [
                'code' => 0,
                'data' => [
                    'name'         => ['first_name' => 'Jane', 'last_name' => 'Doe'],
                    'email'        => 'jane@openprovider.test',
                    'phone'        => ['country_code' => '+31', 'subscriber_number' => '612345678'],
                    'address'      => [
                        'street'  => 'Keizersgracht 1',
                        'city'    => 'Amsterdam',
                        'state'   => 'NH',
                        'country' => 'NL',
                        'zipcode' => '1015CJ',
                    ],
                    'company_name' => 'OpenProvider BV',
                ],
            ]
        );

        $domain = $this->adapter->getDomainDetails($this->makeDomain());

        $this->assertInstanceOf(\Registrar_Domain::class, $domain);
        $this->assertTrue($domain->getPrivacyEnabled());
        $this->assertFalse($domain->getLocked());

        // Nameservers are plain strings
        $this->assertSame('ns1.openprovider.nl', $domain->getNs1());
        $this->assertSame('ns2.openprovider.nl', $domain->getNs2());
        $this->assertSame('ns3.openprovider.nl', $domain->getNs3());
        $this->assertNull($domain->getNs4());

        // Dates are Unix timestamps
        $this->assertSame('2020-03-15', date('Y-m-d', $domain->getRegistrationTime()));
        $this->assertSame('2026-03-15', date('Y-m-d', $domain->getExpirationTime()));

        $admin = $domain->getContactAdmin();
        $this->assertSame('Jane', $admin->getFirstName());
        $this->assertSame('Doe', $admin->getLastName());
        $this->assertSame('jane@openprovider.test', $admin->getEmail());
        $this->assertSame('Amsterdam', $admin->getCity());
        $this->assertSame('NL', $admin->getCountry());
        $this->assertSame('OpenProvider BV', $admin->getCompany());
    }

    public function testGetDomainDetailsWithNoNameservers(): void
    {
        $this->queueResponses(
            $this->auth(),
            $this->domainIdResult(),
            [
                'code' => 0,
                'data' => [
                    'creation_date'            => '2021-01-01 00:00:00',
                    'expiration_date'          => '2027-01-01 00:00:00',
                    'is_private_whois_enabled' => false,
                    'is_locked'                => true,
                    'name_servers'             => [],
                    'admin_handle'             => 'OP-HANDLE-1',
                ],
            ],
            [
                'code' => 0,
                'data' => [
                    'name'    => ['first_name' => 'A', 'last_name' => 'B'],
                    'email'   => 'a@b.com',
                    'phone'   => ['country_code' => '+1', 'subscriber_number' => '555'],
                    'address' => ['street' => 'S', 'city' => 'C', 'state' => 'ST', 'country' => 'US', 'zipcode' => '00000'],
                ],
            ]
        );

        $domain = $this->adapter->getDomainDetails($this->makeDomain());
        $this->assertTrue($domain->getLocked());
        $this->assertFalse($domain->getPrivacyEnabled());
        $this->assertNull($domain->getNs1());
        $this->assertNull($domain->getNs2());
    }

    // -------------------------------------------------------------------------
    // getEpp
    // -------------------------------------------------------------------------

    public function testGetEppReturnsAuthCode(): void
    {
        $this->queueResponses(
            $this->auth(),
            $this->domainIdResult(),
            ['code' => 0, 'data' => ['auth_code' => 'SECRET-EPP-XYZ']]
        );

        $this->assertSame('SECRET-EPP-XYZ', $this->adapter->getEpp($this->makeDomain()));
    }

    public function testGetEppReturnsEmptyStringWhenMissing(): void
    {
        $this->queueResponses(
            $this->auth(),
            $this->domainIdResult(),
            ['code' => 0, 'data' => []]
        );

        $this->assertSame('', $this->adapter->getEpp($this->makeDomain()));
    }

    // -------------------------------------------------------------------------
    // modifyNs
    // -------------------------------------------------------------------------

    public function testModifyNsSendsNameserversAsStrings(): void
    {
        $this->queueResponses($this->auth(), $this->domainIdResult(), $this->ok());

        $domain = $this->makeDomain();
        $domain->setNs1('ns1.example.com');
        $domain->setNs2('ns2.example.com');

        $this->assertTrue($this->adapter->modifyNs($domain));
    }

    public function testModifyNsSkipsNullNameservers(): void
    {
        $this->queueResponses($this->auth(), $this->domainIdResult(), $this->ok());

        $domain = $this->makeDomain();
        $domain->setNs1('ns1.example.com');
        // ns2, ns3, ns4 intentionally left null

        $this->assertTrue($this->adapter->modifyNs($domain));
    }

    public function testModifyNsReturnsFalseOnApiFailure(): void
    {
        $this->queueResponses($this->auth(), $this->domainIdResult(), $this->apiError());
        $this->assertFalse($this->adapter->modifyNs($this->makeDomain()));
    }

    // -------------------------------------------------------------------------
    // modifyContact
    // -------------------------------------------------------------------------

    public function testModifyContactReturnsTrueOnSuccess(): void
    {
        $this->queueResponses(
            $this->auth(),
            $this->domainIdResult(),
            $this->customerResult(),    // find by email
            $this->ok(),               // PUT /customers (updateExisting=true)
            $this->ok()                // PUT /domains/{id}
        );

        $this->assertTrue($this->adapter->modifyContact($this->makeDomain()));
    }

    public function testModifyContactReturnsFalseOnDomainUpdateFailure(): void
    {
        $this->queueResponses(
            $this->auth(),
            $this->domainIdResult(),
            $this->customerResult(),
            $this->ok(),
            $this->apiError()
        );

        $this->assertFalse($this->adapter->modifyContact($this->makeDomain()));
    }

    // -------------------------------------------------------------------------
    // lock / unlock
    // -------------------------------------------------------------------------

    public function testLockReturnsTrueOnSuccess(): void
    {
        $this->queueResponses($this->auth(), $this->domainIdResult(), $this->ok());
        $this->assertTrue($this->adapter->lock($this->makeDomain()));
    }

    public function testLockReturnsFalseOnFailure(): void
    {
        $this->queueResponses($this->auth(), $this->domainIdResult(), $this->apiError());
        $this->assertFalse($this->adapter->lock($this->makeDomain()));
    }

    public function testUnlockReturnsTrueOnSuccess(): void
    {
        $this->queueResponses($this->auth(), $this->domainIdResult(), $this->ok());
        $this->assertTrue($this->adapter->unlock($this->makeDomain()));
    }

    public function testUnlockReturnsFalseOnFailure(): void
    {
        $this->queueResponses($this->auth(), $this->domainIdResult(), $this->apiError());
        $this->assertFalse($this->adapter->unlock($this->makeDomain()));
    }

    // -------------------------------------------------------------------------
    // Privacy protection
    // -------------------------------------------------------------------------

    public function testEnablePrivacyProtectionReturnsTrueOnSuccess(): void
    {
        $this->queueResponses($this->auth(), $this->domainIdResult(), $this->ok());
        $this->assertTrue($this->adapter->enablePrivacyProtection($this->makeDomain()));
    }

    public function testEnablePrivacyProtectionReturnsFalseOnFailure(): void
    {
        $this->queueResponses($this->auth(), $this->domainIdResult(), $this->apiError());
        $this->assertFalse($this->adapter->enablePrivacyProtection($this->makeDomain()));
    }

    public function testDisablePrivacyProtectionReturnsTrueOnSuccess(): void
    {
        $this->queueResponses($this->auth(), $this->domainIdResult(), $this->ok());
        $this->assertTrue($this->adapter->disablePrivacyProtection($this->makeDomain()));
    }

    public function testDisablePrivacyProtectionReturnsFalseOnFailure(): void
    {
        $this->queueResponses($this->auth(), $this->domainIdResult(), $this->apiError());
        $this->assertFalse($this->adapter->disablePrivacyProtection($this->makeDomain()));
    }

    // -------------------------------------------------------------------------
    // TLD stripping
    // -------------------------------------------------------------------------

    public function testTldWithLeadingDotIsStrippedBeforeSendingToApi(): void
    {
        $capturedOptions = null;
        $call = 0;

        $this->httpClient
            ->method('request')
            ->willReturnCallback(function (string $method, string $url, array $options) use (&$capturedOptions, &$call) {
                $r = $this->createMock(ResponseInterface::class);
                if ($call === 0) {
                    $r->method('toArray')->willReturn($this->auth());
                } else {
                    $capturedOptions = $options;
                    $r->method('toArray')->willReturn(['data' => ['results' => [['status' => 'free']]]]);
                }
                $call++;
                return $r;
            });

        $this->adapter->isDomainAvailable($this->makeDomain('example', '.com'));

        $this->assertSame('com', $capturedOptions['json']['domains'][0]['extension']);
    }

    public function testTldWithoutDotIsPassedThroughUnchanged(): void
    {
        $capturedOptions = null;
        $call = 0;

        $this->httpClient
            ->method('request')
            ->willReturnCallback(function (string $method, string $url, array $options) use (&$capturedOptions, &$call) {
                $r = $this->createMock(ResponseInterface::class);
                if ($call === 0) {
                    $r->method('toArray')->willReturn($this->auth());
                } else {
                    $capturedOptions = $options;
                    $r->method('toArray')->willReturn(['data' => ['results' => [['status' => 'free']]]]);
                }
                $call++;
                return $r;
            });

        $this->adapter->isDomainAvailable($this->makeDomain('example', 'co.uk'));

        $this->assertSame('co.uk', $capturedOptions['json']['domains'][0]['extension']);
    }
}
