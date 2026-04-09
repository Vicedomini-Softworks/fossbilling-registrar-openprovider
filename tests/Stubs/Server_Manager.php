<?php

use Symfony\Contracts\HttpClient\HttpClientInterface;

abstract class Server_Manager
{
    protected array $_config = [
        'ip'             => null,
        'host'           => null,
        'secure'         => false,
        'username'       => null,
        'password'       => null,
        'accesshash'     => null,
        'config'         => null,
        'port'           => null,
        'passwordLength' => null,
    ];

    public function __construct(array $options)
    {
        foreach (['ip', 'host', 'secure', 'username', 'password', 'accesshash', 'passwordLength', 'ssl', 'config', 'port'] as $key) {
            if (isset($options[$key])) {
                $this->_config[$key] = $options[$key];
            }
        }
        $this->init();
    }

    protected function init(): void {}

    public function getLog(): Box_Log
    {
        return new Box_Log();
    }

    public function getHttpClient(): HttpClientInterface
    {
        throw new \RuntimeException('getHttpClient() must be mocked in tests.');
    }

    abstract public function getLoginUrl(?Server_Account $account);
    abstract public function getResellerLoginUrl(?Server_Account $account);
    abstract public function testConnection();
    abstract public function createAccount(Server_Account $account);
    abstract public function synchronizeAccount(Server_Account $account);
    abstract public function suspendAccount(Server_Account $account);
    abstract public function unsuspendAccount(Server_Account $account);
    abstract public function cancelAccount(Server_Account $account);
    abstract public function changeAccountPassword(Server_Account $account, string $newPassword);
    abstract public function changeAccountUsername(Server_Account $account, string $newUsername);
    abstract public function changeAccountDomain(Server_Account $account, string $newDomain);
    abstract public function changeAccountIp(Server_Account $account, string $newIp);
    abstract public function changeAccountPackage(Server_Account $account, Server_Package $package);
}
