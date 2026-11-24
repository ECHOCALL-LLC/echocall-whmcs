# Installing the EchoCall module

Version 2.0.0 for WHMCS 8.13 (LTS), 9.0 and 9.1 on PHP 8.2 to 8.4.

## 1. Get an API key

1. Sign in to your EchoCall partner account.
2. Open **Developer Portal** and create an API key. It starts with `eck_live_`.
3. Keep it at hand, it is shown once.

## 2. Upload the module

Download [echocall-whmcs.zip](https://github.com/ECHOCALL-LLC/echocall-whmcs/releases/latest/download/echocall-whmcs.zip)
from the latest release and extract it into the root folder of your WHMCS installation. It
creates these paths:

```
<whmcs>/modules/servers/echocall/echocall.php
<whmcs>/modules/servers/echocall/hooks.php
<whmcs>/modules/servers/echocall/lib/
<whmcs>/modules/servers/echocall/lang/
<whmcs>/modules/servers/echocall/templates/clientarea/main.tpl
<whmcs>/modules/servers/echocall/assets/
<whmcs>/modules/servers/echocall/wordpress-plugin/
```

The web server user needs read access. Nothing in the folder needs write access.

## 3. Create the product

1. **Configuration > System Settings > Products/Services**, create a product of type
   **Other**.
2. On the tab **Module Settings**, choose **EchoCall Voice & Chat**.
3. Paste the API key into setting 1 and save.
4. Reload the page. The **Plan** list now shows the packages of your partner account.
   Choose one, or keep pay as you go.
5. Set the other settings as you like (see the table in [README.md](README.md)) and choose
   **Automatically setup the product as soon as the first payment is received** or the
   option that fits your shop.
6. Save.

The module creates the custom field `EchoCall Service ID` on the product the first time a
service is created. It is admin only. Do not change or delete it.

## 4. Check the connection

Open any service of the product in the admin area and press **Test Connection**. A
success message means the key works and the API is reachable.

## 5. Recommended WHMCS settings

- **Configuration > System Settings > Automation Settings**: turn on **Enable Unsuspension**
  so a paid overdue invoice lifts the suspension. The module never lifts a suspension by
  itself.
- Make sure the WHMCS cron runs daily. It retries top ups that could not be booked.
- To link the sign in page in a product email, add `{$echocall_portal_url}` to the email
  template.

## 6. Test an order

1. Place a test order for the product with a test client and accept it.
2. The service shows the EchoCall customer id and balance in the admin area.
3. Sign in as the client and open the service. The overview shows the balance and,
   if setting 11 has amounts and the client pays in EUR, the top up buttons.

## Troubleshooting

| Symptom | What to check |
| --- | --- |
| Plan list stays empty | Save the API key, then reload. Check outbound HTTPS to hub.echocall.de. |
| Create fails with "does not belong to your partner account" | The email address is already registered on EchoCall by someone else. Use another address. |
| Client area says the service is not linked | Use **Relink By Email** or create the account again with **Create**. |
| Top up buttons missing | Setting 11 is empty, or the client's currency is not EUR. |
| Old pages or styles after an update | Clear the template cache under **Utilities > System > System Cleanup**. |

Failures are noted in **Utilities > Logs > Activity Log**. For the API calls themselves,
turn on module logging under **Utilities > Logs > Module Log**. The API key is masked there.
