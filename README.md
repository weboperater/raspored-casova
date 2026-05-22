# Raspored Časova

**Raspored Časova** je mala PHP + SQLite aplikacija za školski raspored jednog
odeljenja. Napravljena je da razredna, profesori, učenici i roditelji imaju
jedan jednostavan link na telefonu: koji je danas dan, da li je aktivna A ili B
nedelja, koji je čas sada, šta je sledeće i kako izgleda ceo raspored.

Sistem je namenjen školama koje rade sa A/B nedeljama: raspored može imati
odvojene časove za Nedelju A i Nedelju B, a aktivna nedelja se menja iz admina.

Ovo nije komercijalni proizvod. Projekat je poklon školama, razrednim
starešinama, profesorima, učenicima, roditeljima i pojedincima koji žele da ga
koriste ili prilagode. Ako vam odgovara, koristite ga slobodno. Ako želite da se
igrate sa kodom, menjate ga ili napravite svoju verziju, možete i to.

Repo postoji da bi kod bio pregledan i održiv. Konkretna instalacija za jedno
odeljenje treba da ostane privatna i neindeksirana, jer je namenjena školi,
odeljenju i roditeljima, a ne Google pretrazi.

Kanonski GitHub repo projekta je:

```text
https://github.com/weboperater/raspored-casova
```

## Za profesore i razrednu

### Čemu služi

Aplikacija rešava jedan praktičan problem: raspored je uvek dostupan na jednom
linku, pregledan je na telefonu i može brzo da se podeli roditeljima ili
učenicima.

Javni deo vide učenici i roditelji. Admin deo koristi razredna ili osoba koja
održava raspored.

### Šta vide učenici i roditelji

- naziv škole i odeljenja
- trenutnu A/B nedelju
- raspored po danima
- oznaku današnjeg dana
- trenutni čas, ako je nastava u toku
- sledeći čas
- dugme za kopiranje rasporeda
- dugme za deljenje preko Vibera
- light/dark temu, koja može da prati sistemsku temu telefona

### Šta može admin

Admin panel služi za održavanje podataka bez ulaska u kod.

U adminu se može uređivati:

- naziv škole
- naziv odeljenja
- aktivna A ili B nedelja
- termini časova
- predmeti
- raspored za svaki dan
- Viber šabloni za slanje rasporeda
- admin lozinka

### Kratak walkthrough za admin

1. Otvoriti admin link:

```text
https://vas-domen/admin/
```

2. Prijaviti se korisničkim imenom i lozinkom koje su dobijene posebno.

3. Na dashboardu proveriti:

- koje je odeljenje aktivno
- koja je trenutna nedelja, A ili B
- da li je današnji raspored ispravan

4. Ako treba promeniti školu ili odeljenje, otvoriti podešavanja i upisati nove
   vrednosti.

5. Ako treba promeniti A/B nedelju, promeniti aktivnu nedelju u adminu. Javni
   prikaz će odmah pokazati novu nedelju.

6. Ako se menja raspored, otvoriti editor rasporeda, izabrati dan i nedelju, pa
   promeniti predmet po času.

7. Ako neki predmet ne postoji u listi, prvo ga dodati u predmete, pa se vratiti
   u raspored.

8. Ako se menja vreme časova, urediti termine časova. To utiče na prikaz
   trenutnog i sledećeg časa na javnoj strani.

9. Ako se šalje poruka roditeljima preko Vibera, urediti ili izabrati Viber
   šablon i koristiti share/kopiranje.

10. Kada se završi izmena, otvoriti javnu stranu i proveriti da je prikaz
    ispravan.

### Preporuka za svakodnevni rad

- Raspored menjati samo iz admin panela.
- Lozinku ne slati u zajedničke grupe.
- Ako više osoba ima pristup adminu, dogovoriti ko menja raspored.
- Posle veće izmene proveriti javnu stranu na telefonu.
- Pre početka školske godine napraviti backup baze.
- Ako se aplikacija postavlja online, proveriti da Google ne indeksira stranu.

## Privatnost i sigurnost

Ova aplikacija je zamišljena kao privatni raspored za odeljenje. Ne treba je
tretirati kao javni promotivni sajt škole.

Produkcijska instalacija treba da ima:

- HTTPS
- `noindex, nofollow, noarchive`
- blokiran direktan pristup bazi i sistemskim folderima
- promenjenu admin lozinku
- backup baze
- po mogućnosti dodatnu zaštitu admin dela

U projektu već postoje zaštite:

- HTML `robots` meta tag
- HTTP `X-Robots-Tag` header
- `.htaccess` blokade za osetljive foldere
- admin sesije
- CSRF zaštita za izmene
- rate-limit za login
- PDO prepared statements
- anti-cache headeri za admin strane

I pored toga, server mora biti pravilno podešen. Najvažnija provera na live
serveru je da osetljive putanje ne vraćaju HTTP 200.

Primeri putanja koje ne smeju biti javno dostupne:

```text
/data/schedule.db
/config/env.php
/lib/db.php
/lib/security.php
```

Ako su dokumentacija, backup ili Git folder uploadovani na server, i oni moraju
biti blokirani:

```text
/.git/
/_backup/
/README.md
```

Najbolje je da se ti fajlovi uopšte ne šalju na produkcijski hosting.

## Šta se uploaduje na hosting

Za običan cPanel/shared hosting obično je dovoljno uploadovati aplikativni deo:

```text
admin/
assets/
config/
data/
lib/
.htaccess
index.php
```

Na server ne treba slati:

```text
.git/
_backup/
docs/
scripts/
README.md
LICENSE
.DS_Store
```

Ako hosting koristi Apache, LiteSpeed ili OpenLiteSpeed, `.htaccess` zaštite
treba da rade. Ako hosting koristi drugačiji web server, blokade za `data/`,
`config/` i `lib/` moraju se podesiti u server konfiguraciji.

## Za developere

### Tehnologije

- PHP 8+
- SQLite / PDO
- HTML
- CSS
- vanilla JavaScript
- Apache/LiteSpeed/OpenLiteSpeed `.htaccess`

Nema Node build procesa, nema front-end frameworka i nema posebnog database
servera.

### Struktura projekta

```text
admin/                  Admin login, dashboard i editori
assets/css/             Stilovi za javni i admin deo
assets/js/              Deljeni JavaScript
config/env.example.php  Template za produkcione/lokalne tajne vrednosti
data/schedule.db        SQLite baza
docs/                   Dokumentacija za produkcionu sigurnost
lib/db.php              SQLite konekcija, šema, migracije i seed helperi
lib/security.php        Sesije, CSRF, headeri, rate-limit i auth helperi
scripts/                Check, backup, restore i maintenance skripte
index.php               Javni prikaz rasporeda
```

### Konfiguracija

Za novi server kopirati template:

```bash
cp config/env.example.php config/env.php
```

`config/env.php` se ne commituje. U njemu se drže produkcione ili lokalne tajne
vrednosti:

```php
define('APP_ENV', 'production');
define('APP_TRUST_PROXY', false);
define('ADMIN_INITIAL_USERNAME', '');
define('ADMIN_INITIAL_PASSWORD', '');
define('ADMIN_ACCESS_CODE_HASH', '');
```

Ako se baza prvi put inicijalizuje na novom serveru, `ADMIN_INITIAL_USERNAME` i
`ADMIN_INITIAL_PASSWORD` moraju biti popunjeni pre prvog učitavanja aplikacije.
Posle inicijalizacije, realne vrednosti ne treba držati u repozitorijumu.

Za online admin preporučen je dodatni pristupni kod:

```bash
php -r 'echo password_hash("OVDE_UNESI_JAK_ADMIN_KOD", PASSWORD_BCRYPT), PHP_EOL;'
```

Dobijeni hash se upisuje u `ADMIN_ACCESS_CODE_HASH`.

### Lokalno pokretanje

U ovom workspace standardu projekat se lokalno služi kroz shared
OpenLiteSpeed/LiteSpeed mirror kao:

```text
https://rasporedcasova.test
```

Privremeni PHP server može da posluži samo za quick check:

```bash
php -S 127.0.0.1:8001
```

`php -S` ne primenjuje `.htaccess` zaštite i nije bezbednosni ekvivalent
produkciji.

`_docker/` i `_dashboard/` nisu deo ovog projekta. To je shared server-level
infrastruktura za ceo `htdocs` workspace.

### Provere

Sve project-level provere:

```bash
scripts/check-all.sh
```

Pojedinačne provere:

```bash
scripts/check-db-health.sh
scripts/check-public-render.sh
scripts/check-admin-render.sh
```

Production/local mirror security smoke-check:

```bash
scripts/check-production-security.sh https://example.com
```

Za lokalni mirror preko `127.0.0.1`:

```bash
HOST_HEADER=rasporedcasova.test scripts/check-production-security.sh https://127.0.0.1
```

### Backup i restore

Backup:

```bash
scripts/backup-db.sh
```

Podrazumevano čuva gzipovan SQLite snapshot u `_backup/sqlite/` i briše backup
fajlove starije od 14 dana. `_backup/` je ignorisan u Git-u.

Za produkciju je bolje staviti backup van public web root-a:

```bash
BACKUP_DIR=/secure/path/raspored-backups RETENTION_DAYS=30 scripts/backup-db.sh
```

Restore zahteva eksplicitnu potvrdu:

```bash
CONFIRM_RESTORE=YES scripts/restore-db.sh _backup/sqlite/schedule-YYYYMMDD-HHMMSS.db.gz
```

Restore prvo proverava SQLite integrity backup fajla, pravi `pre-restore` backup
trenutne baze, pa tek onda prepisuje target bazu.

### Runtime tragovi u bazi

`data/schedule.db` u repozitorijumu treba tretirati kao demo/seed SQLite bazu.
Može sadržati demo raspored i demo admin nalog za pokretanje projekta. Za
stvarnu instalaciju obavezno promeniti admin lozinku i ne objavljivati privatnu
runtime bazu sa stvarnim podacima škole.

Login pokušaji i audit tragovi mogu lokalno da promene Git status.

Pre public distribucije ili demo commita može se očistiti samo runtime deo:

```bash
scripts/clean-runtime-db.sh
```

Skripta prvo pravi backup, zatim briše samo:

- `login_attempts`
- `admin_audit_log`

Raspored, termini, predmeti, podešavanja i admin nalozi se ne diraju.

### Produkcioni deploy checklist

1. Podesiti domen i HTTPS.
2. Kopirati `config/env.example.php` u `config/env.php`.
3. Popuniti produkcione tajne vrednosti u `config/env.php`.
4. Promeniti admin lozinku.
5. Uključiti `ADMIN_ACCESS_CODE_HASH` ako se koristi dodatni admin kod.
6. Server-level blokirati `data/`, `config/`, `lib/`, `.git/` i `_backup/`.
7. Podesiti backup cron.
8. Pokrenuti:

```bash
scripts/check-db-health.sh
scripts/check-production-security.sh https://example.com
```

9. Proveriti da `/data/schedule.db`, `/config/env.php`, `/lib/db.php` i
   `/lib/security.php` ne vraćaju HTTP 200.

Detalji su u `docs/production-security.md`.

## Open-source napomena

Kod je besplatan i otvoren pod MIT licencom. Može se koristiti u školi,
odeljenju, privatno ili kao osnova za sopstvenu verziju.

Ako repo ide javno, pre toga treba ukloniti ili anonimizovati sve što ne treba
da bude javno:

- realne školske podatke
- realnu produkcionu bazu
- runtime login tragove
- backup fajlove
- lokalne konfiguracije
- privatne napomene

Za public distribuciju je najbolje imati anonimizovan demo seed.

## Licenca

MIT License. Vidi `LICENSE`.

---

# School Schedule PHP SQLite App

**School Schedule PHP SQLite App** is a small PHP + SQLite application for the
class schedule of one school class. It is built so the homeroom teacher,
teachers, students and parents can use one simple mobile-friendly link: what
day it is, whether Week A or Week B is active, which lesson is currently
running, what comes next and what the full schedule looks like.

The app is intended for schools that use an A/B week schedule: it can keep
separate lessons for Week A and Week B, and the active week is changed from the
admin area.

This is not a commercial product. The project is a gift for schools, homeroom
teachers, teachers, students, parents and individuals who want to use or adapt
it. If it fits your needs, use it freely. If you want to play with the code,
change it or create your own version, you can do that too.

The repository exists so the code can be reviewed and maintained. A real
installation for a specific class should remain private and non-indexed,
because it is intended for the school, the class and the parents, not for
Google Search.

## For Teachers and Homeroom Teachers

### What it is for

The application solves one practical problem: the schedule is always available
through one link, it is easy to read on a phone, and it can be quickly shared
with parents or students.

Students and parents use the public view. The admin area is used by the
homeroom teacher or the person maintaining the schedule.

### What Students and Parents See

- school and class name
- current A/B week
- schedule by day
- today's day highlighted
- current lesson, if school is in progress
- next lesson
- button for copying the schedule
- button for sharing through Viber
- light/dark theme, with optional system theme detection

### What the Admin Can Do

The admin panel is used to maintain the schedule data without editing code.

In the admin area, you can edit:

- school name
- class name
- active A or B week
- lesson periods
- subjects
- schedule for each day
- Viber message templates
- admin password

### Short Admin Walkthrough

1. Open the admin link:

```text
https://your-domain/admin/
```

2. Log in with the username and password that were provided separately.

3. On the dashboard, check:

- which class is active
- which week is active, A or B
- whether today's schedule is correct

4. If the school or class name needs to be changed, open settings and enter the
   new values.

5. If the A/B week needs to be changed, update the active week in the admin
   area. The public view will immediately show the new week.

6. If the schedule needs to be changed, open the schedule editor, choose the day
   and week, then change the subject for the lesson period.

7. If a subject is missing from the list, add it under subjects first, then
   return to the schedule editor.

8. If lesson times need to be changed, edit lesson periods. This affects the
   current and next lesson display on the public page.

9. If a message needs to be sent to parents through Viber, edit or choose a
   Viber template and use share/copy.

10. After finishing the change, open the public page and verify that the display
    is correct.

### Recommended Everyday Use

- Edit the schedule only through the admin panel.
- Do not send the password to shared groups.
- If more than one person has admin access, agree who is responsible for
  schedule changes.
- After a larger change, check the public page on a phone.
- Before the school year starts, create a database backup.
- If the application is online, verify that Google does not index it.

## Privacy and Security

This application is intended as a private class schedule. It should not be
treated as a public promotional school website.

A production installation should have:

- HTTPS
- `noindex, nofollow, noarchive`
- direct access to the database and system folders blocked
- changed admin password
- database backups
- preferably additional protection for the admin area

The project already includes:

- HTML `robots` meta tag
- HTTP `X-Robots-Tag` header
- `.htaccess` blocks for sensitive folders
- admin sessions
- CSRF protection for mutations
- login rate limiting
- PDO prepared statements
- anti-cache headers for admin pages

Even with these protections, the server must be configured correctly. The most
important live-server check is that sensitive paths do not return HTTP 200.

Paths that must not be publicly accessible:

```text
/data/schedule.db
/config/env.php
/lib/db.php
/lib/security.php
```

If documentation, backup or Git folders are uploaded to the server, they must
also be blocked:

```text
/.git/
/_backup/
/README.md
```

The best option is not to upload those files to production hosting at all.

## What to Upload to Hosting

For common cPanel/shared hosting, it is usually enough to upload the application
part:

```text
admin/
assets/
config/
data/
lib/
.htaccess
index.php
```

Do not upload these to the server:

```text
.git/
_backup/
docs/
scripts/
README.md
LICENSE
.DS_Store
```

If the hosting uses Apache, LiteSpeed or OpenLiteSpeed, `.htaccess` protections
should work. If the hosting uses another web server, access blocks for `data/`,
`config/` and `lib/` must be configured at the server level.

## For Developers

### Technology

- PHP 8+
- SQLite / PDO
- HTML
- CSS
- vanilla JavaScript
- Apache/LiteSpeed/OpenLiteSpeed `.htaccess`

There is no Node build process, no front-end framework and no separate database
server.

### Project Structure

```text
admin/                  Admin login, dashboard and editors
assets/css/             Styles for public and admin views
assets/js/              Shared JavaScript
config/env.example.php  Template for production/local secrets
data/schedule.db        SQLite database
docs/                   Production security documentation
lib/db.php              SQLite connection, schema, migrations and seed helpers
lib/security.php        Sessions, CSRF, headers, rate-limit and auth helpers
scripts/                Check, backup, restore and maintenance scripts
index.php               Public schedule view
```

### Configuration

For a new server, copy the template:

```bash
cp config/env.example.php config/env.php
```

`config/env.php` is not committed. It stores production or local secret values:

```php
define('APP_ENV', 'production');
define('APP_TRUST_PROXY', false);
define('ADMIN_INITIAL_USERNAME', '');
define('ADMIN_INITIAL_PASSWORD', '');
define('ADMIN_ACCESS_CODE_HASH', '');
```

If the database is initialized for the first time on a new server,
`ADMIN_INITIAL_USERNAME` and `ADMIN_INITIAL_PASSWORD` must be filled in before
the first application load. After initialization, real values should not be kept
in the repository.

For online admin use, an additional access code is recommended:

```bash
php -r 'echo password_hash("ENTER_STRONG_ADMIN_CODE_HERE", PASSWORD_BCRYPT), PHP_EOL;'
```

The generated hash is stored in `ADMIN_ACCESS_CODE_HASH`.

### Local Development

In this workspace standard, the project is served locally through the shared
OpenLiteSpeed/LiteSpeed mirror as:

```text
https://rasporedcasova.test
```

A temporary PHP server can be used only for a quick check:

```bash
php -S 127.0.0.1:8001
```

`php -S` does not apply `.htaccess` protections and is not a security equivalent
to production.

`_docker/` and `_dashboard/` are not part of this project. They are shared
server-level infrastructure for the whole `htdocs` workspace.

### Checks

All project-level checks:

```bash
scripts/check-all.sh
```

Individual checks:

```bash
scripts/check-db-health.sh
scripts/check-public-render.sh
scripts/check-admin-render.sh
```

Production/local mirror security smoke-check:

```bash
scripts/check-production-security.sh https://example.com
```

For the local mirror through `127.0.0.1`:

```bash
HOST_HEADER=rasporedcasova.test scripts/check-production-security.sh https://127.0.0.1
```

### Backup and Restore

Backup:

```bash
scripts/backup-db.sh
```

By default, this stores a gzipped SQLite snapshot in `_backup/sqlite/` and
removes backup files older than 14 days. `_backup/` is ignored by Git.

For production, it is better to place backups outside the public web root:

```bash
BACKUP_DIR=/secure/path/raspored-backups RETENTION_DAYS=30 scripts/backup-db.sh
```

Restore requires explicit confirmation:

```bash
CONFIRM_RESTORE=YES scripts/restore-db.sh _backup/sqlite/schedule-YYYYMMDD-HHMMSS.db.gz
```

The restore script checks SQLite integrity for the backup file, creates a
`pre-restore` backup of the current database, and only then replaces the target
database.

### Runtime Database Traces

`data/schedule.db` in the repository should be treated as a demo/seed SQLite
database. It may contain a demo schedule and a demo admin account for starting
the project. For a real installation, always change the admin password and do
not publish a private runtime database with real school data.

Login attempts and audit traces can change Git status locally.

Before public distribution or a demo commit, only runtime data can be cleaned:

```bash
scripts/clean-runtime-db.sh
```

The script creates a backup first, then deletes only:

- `login_attempts`
- `admin_audit_log`

Schedule, periods, subjects, settings and admin accounts are not touched.

### Production Deploy Checklist

1. Configure domain and HTTPS.
2. Copy `config/env.example.php` to `config/env.php`.
3. Fill production secret values in `config/env.php`.
4. Change the admin password.
5. Enable `ADMIN_ACCESS_CODE_HASH` if an additional admin code is used.
6. Block `data/`, `config/`, `lib/`, `.git/` and `_backup/` at server level.
7. Configure backup cron.
8. Run:

```bash
scripts/check-db-health.sh
scripts/check-production-security.sh https://example.com
```

9. Verify that `/data/schedule.db`, `/config/env.php`, `/lib/db.php` and
   `/lib/security.php` do not return HTTP 200.

Details are in `docs/production-security.md`.

## Open-Source Note

The code is free and open under the MIT license. It can be used in a school,
class, privately or as a base for your own version.

If the repository becomes public, first remove or anonymize everything that
should not be public:

- real school data
- real production database
- runtime login traces
- backup files
- local configuration
- private notes

For public distribution, an anonymized demo seed is the best option.

## License

MIT License. See `LICENSE`.
