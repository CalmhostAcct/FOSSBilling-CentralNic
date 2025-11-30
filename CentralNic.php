<?php

use Symfony\Contracts\HttpClient\Exception\HttpExceptionInterface;

class Registrar_Adapter_CentralNic extends Registrar_AdapterAbstract
{
    public $config = [
        'username' => null,
        'password' => null,
        'sandbox'  => false,
    ];

    public function __construct($options)
    {
        if (!empty($options['username'])) {
            $this->config['username'] = $options['username'];
            unset($options['username']);
        } else {
            throw new Registrar_Exception(
                'The ":domain_registrar" registrar is not fully configured. Missing :missing',
                [':domain_registrar' => 'CentralNic', ':missing' => 'API username'],
                3001
            );
        }

        if (!empty($options['password'])) {
            $this->config['password'] = $options['password'];
            unset($options['password']);
        } else {
            throw new Registrar_Exception(
                'The ":domain_registrar" registrar is not fully configured. Missing :missing',
                [':domain_registrar' => 'CentralNic', ':missing' => 'API password'],
                3001
            );
        }

        $this->config['sandbox'] = !empty($options['sandbox']);
    }

    public static function getConfig()
    {
        return [
            'label' => 'CentralNic (RRPproxy)',
            'form'  => [
                'username' => ['text', [
                    'label'       => 'CentralNic Username',
                    'description' => 'Your RRPproxy account username',
                ]],
                'password' => ['password', [
                    'label'         => 'CentralNic Password',
                    'description'   => 'Your RRPproxy account password',
                    'renderPassword'=> true,
                ]],
                'sandbox' => ['checkbox', [
                    'label'       => 'Use Sandbox Environment',
                    'description' => 'Enable the OT&E (testing) system',
                ]],
            ],
        ];
    }

    private function apiBase()
    {
        return $this->config['sandbox']
            ? 'https://api-ote.rrpproxy.net/api/call'
            : 'https://api.rrpproxy.net/api/call';
    }

    /**
     * Generic CentralNic API request handler
     */
    private function call($command, $params = [])
    {
        $base = $this->apiBase();

        $params = array_merge($params, [
            'command'       => $command,
            's_login'       => $this->config['username'],
            's_pw'          => $this->config['password'],
            'output_format' => 'json',
        ]);

        $client = $this->getHttpClient()->withOptions([
            'verify_peer' => true,
            'verify_host' => true,
        ]);

        try {
            $response = $client->request('POST', $base, [
                'body' => $params,
            ]);

            $raw = $response->getContent();
        } catch (HttpExceptionInterface $e) {
            throw new Registrar_Exception("CentralNic API connection error: {$e->getMessage()}");
        }

        $data = json_decode($raw, true);

        if (!is_array($data)) {
            throw new Registrar_Exception("Invalid CentralNic API response: {$raw}");
        }

        if (!isset($data['code']) || $data['code'] != 200) {
            $msg = $data['description'] ?? 'Unknown CentralNic API error';
            throw new Registrar_Exception($msg);
        }

        return $data;
    }

    /**
     * -------------------------------
     * DOMAIN AVAILABILITY CHECK
     * -------------------------------
     */
    public function isDomainAvailable(Registrar_Domain $domain)
    {
        $res = $this->call('CheckDomain', [
            'domain' => $domain->getName(),
        ]);

        return isset($res['status'][$domain->getName()])
            && $res['status'][$domain->getName()] === 'available';
    }

    public function isDomaincanBeTransferred(Registrar_Domain $domain)
    {
        $res = $this->call('CheckDomainTransfer', [
            'domain' => $domain->getName(),
        ]);

        return isset($res['status']) && $res['status'] === 'transferable';
    }

    /**
     * -------------------------------
     * REGISTER DOMAIN
     * -------------------------------
     */
    public function registerDomain(Registrar_Domain $domain)
    {
        $contactId = $this->createOrUpdateContact($domain->getContactRegistrar());

        $nameservers = array_filter([
            $domain->getNs1(),
            $domain->getNs2(),
            $domain->getNs3(),
            $domain->getNs4(),
        ]);

        $params = [
            'domain' => $domain->getName(),
            'period' => $domain->getRegistrationPeriod() . 'Y',
            'ownercontact0' => $contactId,
            'admincontact0' => $contactId,
            'techcontact0'  => $contactId,
            'billingcontact0' => $contactId,
        ];

        foreach ($nameservers as $i => $ns) {
            $params["nameserver{$i}"] = $ns;
        }

        $res = $this->call('AddDomain', $params);

        return isset($res['code']) && $res['code'] == 200;
    }

    /**
     * -------------------------------
     * RENEW DOMAIN
     * -------------------------------
     */
    public function renewDomain(Registrar_Domain $domain)
    {
        $res = $this->call('RenewDomain', [
            'domain' => $domain->getName(),
            'period' => $domain->getRenewalPeriod() . 'Y',
        ]);

        return isset($res['code']) && $res['code'] == 200;
    }

    /**
     * -------------------------------
     * MODIFY NAMESERVERS
     * -------------------------------
     */
    public function modifyNs(Registrar_Domain $domain)
    {
        $params = [
            'domain' => $domain->getName(),
        ];

        $ns = [
            $domain->getNs1(),
            $domain->getNs2(),
            $domain->getNs3(),
            $domain->getNs4(),
        ];

        foreach ($ns as $i => $server) {
            if ($server) {
                $params["nameserver{$i}"] = $server;
            }
        }

        $res = $this->call('ModifyDomain', $params);

        return isset($res['code']) && $res['code'] == 200;
    }

    /**
     * -------------------------------
     * MODIFY CONTACT
     * -------------------------------
     */
    public function modifyContact(Registrar_Domain $domain)
    {
        $contact = $domain->getContactRegistrar();
        $contactId = $this->createOrUpdateContact($contact);

        $res = $this->call('ModifyDomain', [
            'domain' => $domain->getName(),
            'ownercontact0' => $contactId,
            'admincontact0' => $contactId,
            'techcontact0'  => $contactId,
            'billingcontact0' => $contactId,
        ]);

        return isset($res['code']) && $res['code'] == 200;
    }

    /**
     * -------------------------------
     * GET DOMAIN DETAILS
     * -------------------------------
     */
    public function getDomainDetails(Registrar_Domain $domain)
    {
        $res = $this->call('StatusDomain', [
            'domain' => $domain->getName(),
        ]);

        if (isset($res['expiration'])) {
            $domain->setExpirationTime(strtotime($res['expiration']));
        }

        return $domain;
    }

    /**
     * -------------------------------
     * TRANSFER DOMAIN
     * -------------------------------
     */
    public function transferDomain(Registrar_Domain $domain)
    {
        $contactId = $this->createOrUpdateContact($domain->getContactRegistrar());

        $res = $this->call('TransferDomain', [
            'domain' => $domain->getName(),
            'auth'   => $domain->getEpp(),
            'ownercontact0' => $contactId,
        ]);

        return isset($res['code']) && $res['code'] == 200;
    }

    public function getEpp(Registrar_Domain $domain)
    {
        $res = $this->call('GetAuthCodeDomain', [
            'domain' => $domain->getName(),
        ]);
        return $res['authcode'] ?? null;
    }

    public function lock(Registrar_Domain $domain)
    {
        $res = $this->call('SetDomainLock', [
            'domain' => $domain->getName(),
            'lock'   => 1,
        ]);
        return isset($res['code']) && $res['code'] == 200;
    }

    public function unlock(Registrar_Domain $domain)
    {
        $res = $this->call('SetDomainLock', [
            'domain' => $domain->getName(),
            'lock'   => 0,
        ]);
        return isset($res['code']) && $res['code'] == 200;
    }

    public function enablePrivacyProtection(Registrar_Domain $domain)
    {
        $res = $this->call('ModifyDomain', [
            'domain' => $domain->getName(),
            'idprotection' => 1,
        ]);
        return isset($res['code']) && $res['code'] == 200;
    }

    public function disablePrivacyProtection(Registrar_Domain $domain)
    {
        $res = $this->call('ModifyDomain', [
            'domain' => $domain->getName(),
            'idprotection' => 0,
        ]);
        return isset($res['code']) && $res['code'] == 200;
    }

    public function deleteDomain(Registrar_Domain $domain): never
    {
        throw new Registrar_Exception("CentralNic does not support registrar-side domain deletion.");
    }

    /**
     * -------------------------------
     * CONTACT MANAGEMENT
     * -------------------------------
     */
    private function createOrUpdateContact(Registrar_Domain_Contact $c)
    {
        // RRPproxy contact structure
        $params = [
            'firstname' => $c->getFirstName(),
            'lastname'  => $c->getLastName(),
            'street0'   => $c->getAddress1(),
            'city'      => $c->getCity(),
            'zip'       => $c->getZip(),
            'state'     => $c->getState() ?: 'NA',
            'country'   => $c->getCountry(),
            'email'     => $c->getEmail(),
            'phone'     => '+' . $c->getTelCc() . '.' . $c->getTel(),
            'type'      => 'person',
        ];

        $res = $this->call('AddContact', $params);

        return $res['contact'] ?? null;
    }
}
