# Production security checklist

Ovaj projekat ima javni prikaz rasporeda i admin panel. Pre online pustanja
treba proveriti sledece stavke.

## Obavezno

1. Koristiti HTTPS za ceo domen.
2. Admin lozinka mora biti jaka i jedinstvena.
3. `config/env.php` mora ostati van Git-a i ne sme biti javno dostupan.
4. `data/`, `config/`, `lib/`, `.git/` i `_backup/` moraju biti blokirani na
   nivou web servera.
5. `data/schedule.db` ne sme vratiti HTTP 200 ni pod kojim uslovom.
6. Admin login mora imati CSRF zastitu, sigurne session cookie atribute i
   rate-limit za neuspesne pokusaje.
7. Backup baze mora biti van javnog web root-a ili server-level blokiran.
8. Deploy ne sme sadrzati `.DS_Store`, `.env`, logove, temp fajlove ili lokalne
   arhive.
9. Za online admin preporucen je drugi sloj zastite: `ADMIN_ACCESS_CODE_HASH`,
   HTTP Basic Auth ili IP allowlist.

## HTTPS i admin cache

Kada je `APP_ENV` podesen na `production`, admin deo automatski preusmerava
HTTP zahteve na HTTPS. Ako je aplikacija iza reverse proxy-ja, podesi:

```php
define('APP_TRUST_PROXY', true);
```

Admin stranice salju `Cache-Control: no-store`, `Pragma: no-cache` i
`Expires: 0`, da login i admin sadrzaj ne ostaju u browser/proxy cache-u.

## Drugi sloj za admin

Aplikacija podrzava opcioni admin pristupni kod. Kod se ne cuva u Git-u, vec
samo njegov hash u `config/env.php`.

Generisanje hash-a:

```bash
php -r 'echo password_hash("OVDE_UNESI_JAK_ADMIN_KOD", PASSWORD_BCRYPT), PHP_EOL;'
```

Zatim u `config/env.php`:

```php
define('ADMIN_ACCESS_CODE_HASH', 'HASH_IZ_PRETHODNE_KOMANDE');
```

Kada je hash podesen, login forma trazi korisnicko ime, lozinku i admin kod.
Ako je `ADMIN_ACCESS_CODE_HASH` prazan, dodatni kod je iskljucen.

## Backup baze

Pre backup-a, posle restore-a i pre deploy-a proveri SQLite stanje:

```bash
scripts/check-db-health.sh
```

Rucni backup:

```bash
scripts/backup-db.sh
```

Podrazumevano cuva gzipovan SQLite snapshot u `_backup/sqlite/` i brise backup
fajlove starije od 14 dana. Folder `_backup/` je ignorisan u Git-u i mora ostati
server-level blokiran.

Za cron na serveru:

```cron
15 3 * * * cd /path/to/raspored-casova && scripts/backup-db.sh >/dev/null
```

Ako server koristi drugi backup folder:

```bash
BACKUP_DIR=/secure/path/raspored-backups RETENTION_DAYS=30 scripts/backup-db.sh
```

Restore iz backup-a:

```bash
CONFIRM_RESTORE=YES scripts/restore-db.sh _backup/sqlite/schedule-YYYYMMDD-HHMMSS.db.gz
```

Restore prvo proverava SQLite integrity backup fajla, zatim pravi
`pre-restore-*.db.gz` snapshot trenutne baze i tek onda prepisuje `data/schedule.db`.
Za produkciju prvo zaustavi admin izmene, uradi restore, pa pokreni
`scripts/check-db-health.sh` i `scripts/check-production-security.sh`.

## Audit evidencija

Admin izmene se upisuju u `admin_audit_log`. Dashboard prikazuje poslednjih 10
akcija, a stranica `admin/audit.php` prikazuje poslednjih 100 akcija uz filtere
po akciji i tipu entiteta. Beleze se izmene rasporeda, termina, predmeta, Viber
sablona, podesavanja skole/razreda i promene admin lozinke.

Audit log nije zamena za backup. Koristi se za pregled sta se desilo, dok se
oporavak posle pogresne izmene radi iz backup-a.

## Minimalna provera posle deploy-a

Automatska provera:

```bash
scripts/check-production-security.sh https://example.com
```

Za lokalni mirror preko `127.0.0.1` i custom vhost header:

```bash
HOST_HEADER=rasporedcasova.test scripts/check-production-security.sh https://127.0.0.1
```

Rucna provera:

```bash
curl -I https://example.com/
curl -I https://example.com/admin/
curl -I https://example.com/data/schedule.db
curl -I https://example.com/config/env.php
```

Ocekivano:

- `/` vraca `200`.
- `/admin/` vraca login stranicu ili redirect ka loginu.
- `/data/schedule.db` vraca `403` ili `404`, nikad `200`.
- `/config/env.php` vraca `403` ili `404`, nikad `200`.
- HTTPS odgovor ima bar `Strict-Transport-Security`,
  `X-Content-Type-Options`, `X-Frame-Options` i `Referrer-Policy`.
- `/admin/` odgovor ima `Cache-Control: no-store`.

## Reference

- OWASP Authentication Cheat Sheet: https://cheatsheetseries.owasp.org/cheatsheets/Authentication_Cheat_Sheet.html
- OWASP Session Management Cheat Sheet: https://cheatsheetseries.owasp.org/cheatsheets/Session_Management_Cheat_Sheet.html
- OWASP CSRF Prevention Cheat Sheet: https://cheatsheetseries.owasp.org/cheatsheets/Cross-Site_Request_Forgery_Prevention_Cheat_Sheet.html
- PHP password hashing: https://www.php.net/manual/en/function.password-hash.php
- PHP PDO prepared statements: https://www.php.net/manual/en/pdo.prepared-statements.php
- Let's Encrypt: https://letsencrypt.org/getting-started/
