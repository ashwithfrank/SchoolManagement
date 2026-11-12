# LAN deployment (XAMPP)

Goal: the server computer runs XAMPP; other office computers use a browser. Not a public-internet deployment.

## Steps
1. **Fixed address.** Give the server a static IP (router DHCP reservation is easiest), e.g. `192.168.1.50`.
2. **Apache.** Default XAMPP listens on all interfaces, port 80. Open `http://192.168.1.50/AlphaOmegaSchoolManagement/public/` from another PC.
3. **Windows Firewall (server).** Allow inbound TCP **80** (and 443 if you set up HTTPS) from the **private/local network only**.
   Do **not** create a rule for TCP **3306**.
4. **MySQL stays local.** In `C:\xampp\mysql\bin\my.ini` under `[mysqld]` add `bind-address=127.0.0.1` (or `skip-networking` if nothing else needs it) and restart MySQL.
   The application connects through `127.0.0.1`, so remote DB access is never needed.
5. **XAMPP hardening.** Set a password for MySQL `root` (XAMPP ships without one), do not use `root` in the app, and remove or restrict
   phpMyAdmin from LAN access (`C:\xampp\apache\conf\extra\httpd-xampp.conf`, `Require local`).
6. **Setup page** works only from the server itself (127.0.0.1). Create the Admin there before other computers connect.
7. **HTTPS (recommended).** Passwords cross the LAN in clear text over plain HTTP. For a school network use a self-signed or internal-CA certificate in
   XAMPP's `httpd-ssl.conf` and use `https://`. The app marks cookies `Secure` automatically when HTTPS is detected.
8. **Backups.** Copy encrypted backups to another device regularly (USB drive or a synced folder). A backup on the same disk as the database does not protect against disk failure.
9. **Updates / power.** Use a UPS if possible; switch the server off via Windows shutdown, not the power button, to protect InnoDB.

## What is NOT provided
- No automatic internet/cloud sync, no remote access from outside the school, no multi-server clustering.
- If you port-forward this to the internet, you take on risks this guide does not cover. Put it behind a VPN instead.
