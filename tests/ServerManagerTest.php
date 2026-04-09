<?php

declare(strict_types=1);

namespace Tests;

use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

class ServerManagerTest extends TestCase
{
    private \Server_Manager_OpenProvider $manager;

    protected function setUp(): void
    {
        $this->manager = $this->getMockBuilder(\Server_Manager_OpenProvider::class)
            ->setConstructorArgs([[
                'host'     => 'api.openprovider.eu',
                'secure'   => true,
                'username' => 'testuser',
                'password' => 'testpass',
            ]])
            ->onlyMethods(['getHttpClient', 'getLog'])
            ->getMock();

        $this->manager->method('getLog')->willReturn(new \Box_Log());
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    /** Queue HTTP responses; arrays are JSON-encoded, strings passed as-is. */
    private function queueResponses(array|string ...$responses): void
    {
        $mocks = array_map(
            fn ($r) => new MockResponse(is_array($r) ? json_encode($r) : $r),
            $responses
        );
        $client = new MockHttpClient($mocks, 'https://api.openprovider.eu');
        $this->manager->method('getHttpClient')->willReturn($client);
    }

    private function auth(): array
    {
        return ['code' => 0, 'data' => ['token' => 'test-bearer-token']];
    }

    private function ok(): array
    {
        return ['code' => 0, 'data' => []];
    }

    private function apiError(string $desc = 'error'): array
    {
        return ['code' => 1, 'desc' => $desc, 'data' => []];
    }

    private function makeAccount(
        string $domain = 'example.com',
        string $productType = 'ssl',
        ?string $ip = null,
        ?string $note = null
    ): \Server_Account {
        $pkg = (new \Server_Package())->setCustomValue('product_type', $productType);
        $account = (new \Server_Account())
            ->setDomain($domain)
            ->setPackage($pkg);
        if ($ip !== null) {
            $account->setIp($ip);
        }
        if ($note !== null) {
            $account->setNote($note);
        }
        return $account;
    }

    private function xmlReply(int $code = 0, array $extra = []): string
    {
        $extraXml = '';
        foreach ($extra as $k => $v) {
            $extraXml .= "<{$k}>{$v}</{$k}>";
        }
        return '<?xml version="1.0" encoding="UTF-8"?>'
             . '<openXML><reply>'
             . "<code>{$code}</code>"
             . '<desc>' . ($code === 0 ? 'Success' : 'Error') . '</desc>'
             . $extraXml
             . '</reply></openXML>';
    }

    // ── testConnection ────────────────────────────────────────────────────────

    public function testTestConnectionSuccess(): void
    {
        $this->queueResponses($this->auth());
        $this->assertTrue($this->manager->testConnection());
    }

    public function testTestConnectionFailure(): void
    {
        $this->queueResponses(['code' => 1, 'data' => []]);
        $this->expectException(\Server_Exception::class);
        $this->manager->testConnection();
    }

    // ── getLoginUrl ───────────────────────────────────────────────────────────

    public function testGetLoginUrl(): void
    {
        $url = $this->manager->getLoginUrl(null);
        $this->assertStringContainsString('openprovider.eu', $url);
    }

    public function testGetResellerLoginUrl(): void
    {
        $url = $this->manager->getResellerLoginUrl(null);
        $this->assertStringContainsString('openprovider.eu', $url);
    }

    // ── SSL createAccount ─────────────────────────────────────────────────────

    public function testCreateSslOrderWithExistingCsr(): void
    {
        $this->queueResponses($this->auth(), $this->ok());

        $pkg = (new \Server_Package())->setCustomValues([
            'product_type'  => 'ssl',
            'ssl_product_id'=> '123',
            'approver_email'=> 'admin@example.com',
            'period'        => '1',
        ]);
        $account = (new \Server_Account())
            ->setDomain('example.com')
            ->setPackage($pkg)
            ->setNote('-----BEGIN CERTIFICATE REQUEST-----...');

        $this->assertTrue($this->manager->createAccount($account));
    }

    public function testCreateSslOrderGeneratesCsrWhenNoteIsEmpty(): void
    {
        $this->queueResponses(
            $this->auth(),
            ['code' => 0, 'data' => ['csr' => '-----BEGIN CERTIFICATE REQUEST-----...']],
            $this->ok()
        );

        $pkg = (new \Server_Package())->setCustomValues([
            'product_type'  => 'ssl',
            'ssl_product_id'=> '123',
        ]);
        $account = (new \Server_Account())
            ->setDomain('example.com')
            ->setPackage($pkg);

        $this->assertTrue($this->manager->createAccount($account));
    }

    public function testCreateSslOrderFailsWhenCsrGenerationFails(): void
    {
        $this->queueResponses(
            $this->auth(),
            ['code' => 0, 'data' => ['csr' => '']]
        );

        $pkg     = (new \Server_Package())->setCustomValue('product_type', 'ssl');
        $account = (new \Server_Account())->setDomain('example.com')->setPackage($pkg);

        $this->expectException(\Server_Exception::class);
        $this->manager->createAccount($account);
    }

    public function testCreateSslOrderFailsOnApiError(): void
    {
        $this->queueResponses(
            $this->auth(),
            ['code' => 0, 'data' => ['csr' => '---csr---']],
            $this->apiError()
        );

        $pkg     = (new \Server_Package())->setCustomValue('product_type', 'ssl');
        $account = (new \Server_Account())->setDomain('example.com')->setPackage($pkg);

        $this->assertFalse($this->manager->createAccount($account));
    }

    // ── SSL cancelAccount ─────────────────────────────────────────────────────

    public function testCancelSslOrderSuccess(): void
    {
        $this->queueResponses(
            $this->auth(),
            ['code' => 0, 'data' => ['results' => [['id' => 77]]]],
            $this->ok()
        );

        $this->assertTrue($this->manager->cancelAccount($this->makeAccount()));
    }

    public function testCancelSslOrderWhenNotFoundReturnsTrue(): void
    {
        $this->queueResponses(
            $this->auth(),
            ['code' => 0, 'data' => ['results' => []]]
        );

        $this->assertTrue($this->manager->cancelAccount($this->makeAccount()));
    }

    // ── SSL synchronizeAccount ────────────────────────────────────────────────

    public function testSynchronizeSslAccountActive(): void
    {
        $this->queueResponses(
            $this->auth(),
            ['code' => 0, 'data' => ['results' => [['id' => 88]]]],
            ['code' => 0, 'data' => ['status' => 'ACT']]
        );

        $account = $this->makeAccount();
        $result  = $this->manager->synchronizeAccount($account);
        $this->assertFalse($result->getSuspended());
    }

    public function testSynchronizeSslAccountExpired(): void
    {
        $this->queueResponses(
            $this->auth(),
            ['code' => 0, 'data' => ['results' => [['id' => 88]]]],
            ['code' => 0, 'data' => ['status' => 'EXP']]
        );

        $account = $this->makeAccount();
        $result  = $this->manager->synchronizeAccount($account);
        $this->assertTrue($result->getSuspended());
    }

    public function testSynchronizeSslOrderNotFound(): void
    {
        $this->queueResponses(
            $this->auth(),
            ['code' => 0, 'data' => ['results' => []]]
        );

        $account = $this->makeAccount();
        $result  = $this->manager->synchronizeAccount($account);
        $this->assertNull($result->getSuspended());
    }

    // ── SSL changeAccountPackage (renew) ──────────────────────────────────────

    public function testChangeAccountPackageRenewsSslOrder(): void
    {
        $this->queueResponses(
            $this->auth(),
            ['code' => 0, 'data' => ['results' => [['id' => 55]]]],
            $this->ok()
        );

        $newPkg = (new \Server_Package())->setCustomValues(['product_type' => 'ssl', 'period' => '2']);
        $this->assertTrue($this->manager->changeAccountPackage($this->makeAccount(), $newPkg));
    }

    public function testChangeAccountPackageThrowsWhenSslOrderNotFound(): void
    {
        $this->queueResponses(
            $this->auth(),
            ['code' => 0, 'data' => ['results' => []]]
        );

        $newPkg = (new \Server_Package())->setCustomValue('product_type', 'ssl');
        $this->expectException(\Server_Exception::class);
        $this->manager->changeAccountPackage($this->makeAccount(), $newPkg);
    }

    // ── DNS zone ──────────────────────────────────────────────────────────────

    public function testCreateDnsZoneSuccess(): void
    {
        $this->queueResponses($this->auth(), $this->ok());
        $this->assertTrue($this->manager->createAccount($this->makeAccount('example.com', 'dns_zone')));
    }

    public function testCreateDnsZoneFailure(): void
    {
        $this->queueResponses($this->auth(), $this->apiError());
        $this->assertFalse($this->manager->createAccount($this->makeAccount('example.com', 'dns_zone')));
    }

    public function testCancelDnsZoneSuccess(): void
    {
        $this->queueResponses($this->auth(), $this->ok());
        $this->assertTrue($this->manager->cancelAccount($this->makeAccount('example.com', 'dns_zone')));
    }

    public function testSynchronizeDnsZoneActive(): void
    {
        $this->queueResponses($this->auth(), $this->ok());
        $account = $this->makeAccount('example.com', 'dns_zone');
        $result  = $this->manager->synchronizeAccount($account);
        $this->assertFalse($result->getSuspended());
    }

    public function testSynchronizeDnsZoneMissing(): void
    {
        $this->queueResponses($this->auth(), $this->apiError());
        $account = $this->makeAccount('example.com', 'dns_zone');
        $result  = $this->manager->synchronizeAccount($account);
        $this->assertTrue($result->getSuspended());
    }

    public function testSuspendDnsZoneDeletesIt(): void
    {
        $this->queueResponses($this->auth(), $this->ok());
        $this->assertTrue($this->manager->suspendAccount($this->makeAccount('example.com', 'dns_zone')));
    }

    public function testUnsuspendDnsZoneRecreatesIt(): void
    {
        $this->queueResponses($this->auth(), $this->ok());
        $this->assertTrue($this->manager->unsuspendAccount($this->makeAccount('example.com', 'dns_zone')));
    }

    // ── Licenze Plesk (XML-RPC) ───────────────────────────────────────────────

    public function testCreatePleskLicenseSuccess(): void
    {
        // XML-RPC uses credentials inline — no bearer auth call
        $this->queueResponses($this->xmlReply(0));
        $this->assertTrue($this->manager->createAccount($this->makeAccount('1.2.3.4', 'license_plesk', '1.2.3.4')));
    }

    public function testCreatePleskLicenseFailure(): void
    {
        $this->queueResponses($this->xmlReply(1));
        $this->assertFalse($this->manager->createAccount($this->makeAccount('1.2.3.4', 'license_plesk', '1.2.3.4')));
    }

    public function testCancelPleskLicenseSuccess(): void
    {
        $this->queueResponses($this->xmlReply(0));
        $this->assertTrue($this->manager->cancelAccount($this->makeAccount('1.2.3.4', 'license_plesk', '1.2.3.4')));
    }

    // ── Licenze CloudLinux (XML-RPC) ──────────────────────────────────────────

    public function testCreateCloudLinuxLicenseSuccess(): void
    {
        $this->queueResponses($this->xmlReply(0));
        $this->assertTrue($this->manager->createAccount($this->makeAccount('1.2.3.4', 'license_cloudlinux', '1.2.3.4')));
    }

    // ── Prodotti non supportati ───────────────────────────────────────────────

    public function testUnsupportedProductTypeThrowsOnCreate(): void
    {
        $this->queueResponses($this->auth());
        $this->expectException(\Server_Exception::class);
        $this->manager->createAccount($this->makeAccount('example.com', 'email_dmarc'));
    }

    public function testSuspendSslThrows(): void
    {
        $this->queueResponses($this->auth());
        $this->expectException(\Server_Exception::class);
        $this->manager->suspendAccount($this->makeAccount('example.com', 'ssl'));
    }

    // ── Operazioni non supportate ─────────────────────────────────────────────

    public function testChangePasswordThrows(): void
    {
        $this->expectException(\Server_Exception::class);
        $this->manager->changeAccountPassword($this->makeAccount(), 'newpass');
    }

    public function testChangeUsernameThrows(): void
    {
        $this->expectException(\Server_Exception::class);
        $this->manager->changeAccountUsername($this->makeAccount(), 'newuser');
    }

    public function testChangeDomainThrows(): void
    {
        $this->expectException(\Server_Exception::class);
        $this->manager->changeAccountDomain($this->makeAccount(), 'new.com');
    }

    public function testChangeIpThrows(): void
    {
        $this->expectException(\Server_Exception::class);
        $this->manager->changeAccountIp($this->makeAccount(), '9.9.9.9');
    }

    public function testChangePackageDnsZoneThrows(): void
    {
        $this->expectException(\Server_Exception::class);
        $newPkg = (new \Server_Package())->setCustomValue('product_type', 'dns_zone');
        $this->manager->changeAccountPackage($this->makeAccount('example.com', 'dns_zone'), $newPkg);
    }
}
