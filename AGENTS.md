# AGENTS.md - raspored-casova project rules

Ovaj fajl važi samo za `projects/raspored-casova/`.
Root `/Users/weboperater/htdocs/AGENTS.md` ostaje glavni autoritet za Git, Docker,
remote/deploy, shared infrastructure i opšta pravila.

Ako postoji konflikt, root `AGENTS.md` ima prioritet.

## Zašto ovaj projekat ima odvojeni `AGENTS.md`

Ovo nije običan prezentacioni sajt. Projekat ima SQLite bazu, admin panel,
CSRF/session sigurnost, production-security checklist i destruktivne backup/restore
skripte. Ta pravila moraju biti odvojena od opštih workspace pravila.

## Project scope

- Javni prikaz je `index.php`.
- Admin panel je u `admin/`.
- Core baza i sigurnost su u `lib/db.php` i `lib/security.php`.
- Konfiguracija je u `config/`.
- Runtime baza je `data/schedule.db`.
- Ne menjati root workspace, `_docker/`, `_dashboard/` ili druge projekte u okviru
  raspored taska.

## Data integrity

`data/schedule.db` je runtime baza sa korisničkim podacima. Ne tretirati je kao
test fixture.

Bez izričitog zahteva ne raditi:

- restore baze
- clean runtime baze
- bulk update rasporeda
- promenu schema/data seed-a
- brisanje audit/log istorije

## Security model

- Write akcije u adminu moraju ostati POST + CSRF.
- Login/session/rate-limit logiku ne slabiti radi lakšeg testiranja.
- Public deo mora ostati private/noindex.
- Sensitive putanje kao `data/`, `config/`, `lib/`, `.git`, `_backup/` i baza ne
  smeju biti javno dostupne.

## Provere

Koristiti postojeće skripte kad god odgovaraju izmeni:

```bash
scripts/check-all.sh
scripts/check-db-health.sh
scripts/check-public-render.sh
scripts/check-admin-render.sh
```

Za live hardening proveru koristiti `scripts/check-production-security.sh` samo kada
je target URL jasno zadat.

`scripts/restore-db.sh` i `scripts/clean-runtime-db.sh` su destruktivni/visoko
rizični tokovi i ne pokretati ih bez eksplicitnog zahteva.
