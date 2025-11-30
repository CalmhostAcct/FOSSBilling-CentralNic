

# FOSSBilling-CentralNic

A fully-featured FOSSBilling registrar module for **CentralNic / RRPproxy**.

This module enables automated domain registration, renewals, transfers, contact syncing, and DNS/WHOIS management using the CentralNic (RRPproxy) reseller API.

---

## 🚀 Features

✔ Automatic domain registration
✔ Domain transfer support
✔ Domain renewals
✔ Nameserver updates
✔ WHOIS contact management
✔ EPP/auth code retrieval
✔ Domain lock / unlock
✔ ID protection toggle
✔ Live & Sandbox (OT&E) support
✔ Full compatibility with FOSSBilling’s registrar interface

---

## 📦 Requirements

* A **CentralNic / RRPproxy reseller account**

  * (Either production or OT&E sandbox)
* FOSSBilling installation (latest version recommended)
* PHP 8.1+
* cURL or Symfony HTTP Client support (default FOSSBilling requirement)

---

## ⚙️ Installation

1. Download the module files.
2. Place the module folder into:

```
/modules/Registrar/Adapter/CentralNic/
```

so that the main file is:

```
/modules/Registrar/Adapter/CentralNic.php
```

3. In the FOSSBilling admin panel, go to:

**Settings → Domain Registrars**

4. Enable **CentralNic**.

---

## 🔧 Configuration

You will need:

| Setting          | Description                          |
| ---------------- | ------------------------------------ |
| **Username**     | Your CentralNic / RRPproxy API login |
| **Password**     | Your API password                    |
| **Sandbox mode** | Use OT&E test environment            |

### API Endpoints

| Mode        | URL                                     |
| ----------- | --------------------------------------- |
| **Live**    | `https://api.rrpproxy.net/api/call`     |
| **Sandbox** | `https://api-ote.rrpproxy.net/api/call` |

The module automatically switches based on the “Sandbox” toggle.

---

## 🧪 Testing

If you want to test without affecting real domains:

1. Enable **Sandbox mode**
2. Use your **CentralNic OT&E** credentials
3. Try operations such as domain availability checks or registrations
4. Sandbox domains ending in `.test` or any TLD supported in OT&E can be used

No live changes will occur when sandbox is active.

---

## 📘 Supported Commands

This module uses the official CentralNic commands, including:

* `CheckDomain`
* `AddDomain`
* `RenewDomain`
* `TransferDomain`
* `StatusDomain`
* `ModifyDomain`
* `GetAuthCodeDomain`
* `SetDomainLock`
* `AddContact`

All responses are parsed using JSON (`output_format=json`).

---

## 🛠 Development Notes

* The module follows the same structure as the Freenom/Gandi adapters for easier maintenance.
* Error codes from the API are converted into standard FOSSBilling `Registrar_Exception`.
* Contact objects are automatically created if required by the TLD.

If you want to extend the module, you can add:

* DNS zone functions
* Error translation maps
* Contact caching
* Logging/debug mode

Open an issue or PR if you’d like these added upstream.

---

## 📝 License

Apache 2.0 License
Feel free to fork, modify, and contribute.


Just tell me!
