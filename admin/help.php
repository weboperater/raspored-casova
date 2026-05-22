<?php
require_once __DIR__ . '/_auth.php';
?>
<!DOCTYPE html>
<html lang="sr">
<head>
<?php renderAdminHead('Admin — Uputstvo za upotrebu'); ?>
</head>
<body class="min-h-screen">

<!-- NAV -->
<?php renderAdminTopNav(); ?>
<?php renderAdminSubnav(); ?>

<div class="max-w-4xl mx-auto px-4 py-8">
  <div class="grid grid-cols-1 lg:grid-cols-4 gap-6">

    <!-- TOC (sidebar) -->
    <div class="lg:col-span-1">
      <div class="card rounded-2xl p-4 sticky top-20">
        <p class="text-xs font-bold text-slate-400 uppercase tracking-widest mb-3">Sadržaj</p>
        <nav class="space-y-0.5">
          <a href="#uvod"      class="toc-link">📋 Uvod</a>
          <a href="#prijava"   class="toc-link">🔐 Prijava i lozinka</a>
          <a href="#dashboard" class="toc-link">🏠 Dashboard</a>
          <a href="#termini"   class="toc-link">⏰ Termini časova</a>
          <a href="#raspored"  class="toc-link">📅 Raspored</a>
          <a href="#predmeti"  class="toc-link">📚 Predmeti</a>
          <a href="#viber"     class="toc-link">💬 Viber</a>
          <a href="#izmene"    class="toc-link">🧾 Istorija izmena</a>
          <a href="#ucenici"   class="toc-link">🎓 Učenički prikaz</a>
          <a href="#faq"       class="toc-link">❓ Česta pitanja</a>
        </nav>
      </div>
    </div>

    <!-- CONTENT -->
    <div class="lg:col-span-3 prose space-y-2">

      <!-- UVOD -->
      <div id="uvod" class="card rounded-2xl p-6">
        <h1>📖 Uputstvo za razrednog starešinu</h1>
        <p class="text-slate-400 text-sm mt-1">Raspored Časova OG1 · Admin Panel</p>
        <hr>
        <p>Dobrodošli u admin panel za upravljanje rasporedom časova. Ovaj sistem omogućava vam da:</p>
        <ul>
          <li>Podešavate raspored po danima i smenjujućim nedeljama <strong>A</strong> i <strong>B</strong></li>
          <li>Upravljate bazom predmeta (naziv, nastavnik, boja, emoji)</li>
          <li>Definišete tačna vremena trajanja časova i odmora</li>
          <li>Šaljete obaveštenja roditeljima putem <strong>Viber</strong>-a</li>
        </ul>
        <div class="tip">
          <strong>💡 Učenički prikaz</strong> — Đaci vide raspored na glavnoj stranici <code>/</code>, automatski skrilovanu na današnji dan. Ne moraju da se prijavljuju.
        </div>
      </div>

      <!-- PRIJAVA -->
      <div id="prijava" class="card rounded-2xl p-6">
        <h2>🔐 Prijava i lozinka</h2>

        <h3>Kako se prijaviti</h3>
        <ol>
          <li>Otvorite <code>/admin/</code> u brauzeru</li>
          <li>Unesite korisničko ime i lozinku</li>
          <li>Kliknite <strong>Prijavi se</strong></li>
        </ol>
        <div class="info">
          Pristupne podatke dodeljuje administrator sistema.
        </div>
        <div class="warn">
          ⚠️ <strong>Odmah promenite lozinku</strong> pri prvoj prijavi — Dashboard → Promena lozinke.
        </div>

        <h3>Promena lozinke</h3>
        <ol>
          <li>Idite na <strong>Dashboard</strong></li>
          <li>Pronađite sekciju <em>Promena lozinke</em></li>
          <li>Unesite trenutnu lozinku, pa dvaput novu</li>
          <li>Kliknite <strong>Promeni lozinku</strong></li>
        </ol>

        <h3>Odjava</h3>
        <p>Kliknite <strong>Odjavi</strong> u gornjem desnom uglu bilo koje admin stranice. Sesija traje 8 sati od poslednje aktivnosti.</p>
      </div>

      <!-- DASHBOARD -->
      <div id="dashboard" class="card rounded-2xl p-6">
        <h2>🏠 Dashboard</h2>
        <p>Centralna kontrolna tabla. Prikazuje trenutnu i sledeću nedelju (A ili B) i sadrži tri sekcije za podešavanja.</p>

        <h3>Podešavanje naziva razreda i škole</h3>
        <ol>
          <li>U sekciji <em>Podešavanja</em> izmenite naziv razreda i škole</li>
          <li>Kliknite <strong>Sačuvaj</strong></li>
        </ol>
        <p>Naziv razreda se prikazuje u zaglavlju učeničkog prikaza i u Viber porukama kao varijabla <code>[RAZRED]</code>.</p>

        <h3>Naizmenična nedelja A/B</h3>
        <p>Sistem automatski određuje tip nedelje na osnovu ISO broja nedelje. Logika:</p>
        <ul>
          <li>Definiše se <strong>referentna nedelja</strong> — broj nedelje u godini i njen tip (A ili B)</li>
          <li>Svaka nedelja na parnom rastojanju od reference = isti tip</li>
          <li>Svaka nedelja na neparnom rastojanju = suprotni tip</li>
        </ul>
        <div class="tip">
          <strong>Primer:</strong> Referentna nedelja je 10/2026 = Nedelja A. Nedelja 11 = B, 12 = A, 13 = B...
        </div>
        <div class="warn">
          ⚠️ Ako se ritam naizmenjivanja promeni (raspust, vanredni dani), izmenite referentnu nedelju u Dashboard-u.
        </div>
      </div>

      <!-- TERMINI -->
      <div id="termini" class="card rounded-2xl p-6">
        <h2>⏰ Termini časova</h2>
        <p>Stranica <strong>Termini</strong> omogućava vam da definišete tačna vremena početka i kraja svakog časa.</p>

        <h3>Generator termina (preporučeno)</h3>
        <ol>
          <li>Unesite <strong>vreme prvog časa</strong> (npr. <code>08:30</code>)</li>
          <li>Izaberite <strong>broj časova</strong> u danu (1–10)</li>
          <li>Unesite <strong>trajanje časa</strong> u minutama (npr. <code>45</code>)</li>
          <li>Unesite <strong>trajanje malog odmora</strong> u minutama (npr. <code>5</code>)</li>
          <li>Dodajte <strong>velike odmore</strong> ako ih ima (do 2): kliknite <em>+ Dodaj veliki odmor</em>, izaberite posle kog časa i koliko traje</li>
          <li>Kliknite <strong>Generiši termine</strong> — sistem automatski izračunava sva vremena</li>
        </ol>
        <div class="tip">
          Pregled generisanog rasporeda prikazuje se desno u realnom vremenu dok menjate parametre.
        </div>

        <h3>Ručno podešavanje</h3>
        <p>Ispod generatora, u sekciji <em>Ručno podešavanje</em>, možete izmeniti vreme za svaki čas posebno. Kliknite na čas da biste otvorili polja za uređivanje, pa <strong>Sačuvaj</strong>.</p>

        <h3>Dvočas (dva spojena časa)</h3>
        <p>Dvočas se podešava u <strong>Rasporedu</strong>, ne ovde. Dvočas spaja dva uzastopna časa bez odmora između njih, a ukupno trajanje se prikazuje na učeničkom prikazu.</p>
        <div class="info">
          Kada uključite dvočas za N. čas, sistem automatski briše sadržaj (N+1). časa jer postaje nastavak dvočasa.
        </div>
      </div>

      <!-- RASPORED -->
      <div id="raspored" class="card rounded-2xl p-6">
        <h2>📅 Raspored</h2>
        <p>Uredite raspored za svaki dan, posebno za nedelju <strong>A</strong> i nedelju <strong>B</strong>. Koristite dugmad <code>A</code> / <code>B</code> u zaglavlju da prebacujete između nedelja.</p>

        <h3>Dodavanje časa</h3>
        <ol>
          <li>Pronađite kolonu sa željenim danom</li>
          <li>Kliknite <strong>+ Dodaj čas</strong> pored broja časa koji je prazan</li>
          <li>Počnite kucati naziv predmeta — pojaviće se predlozi</li>
          <li>Izaberite predmet, opciono unesite učionicu i napomenu</li>
          <li>Kliknite <strong>Sačuvaj</strong></li>
        </ol>

        <h3>Izmena postojećeg časa</h3>
        <ol>
          <li>Kliknite na karticu časa koji želite da izmenite</li>
          <li>U dijalogu izmenite predmet, učionicu ili napomenu</li>
          <li>Kliknite <strong>Sačuvaj</strong></li>
        </ol>

        <h3>Brisanje časa</h3>
        <ol>
          <li>Kliknite na karticu časa</li>
          <li>Kliknite <strong>Obriši čas</strong> (crveno dugme)</li>
          <li>Potvrdite u dijalogu</li>
        </ol>

        <h3>Promena redosleda (Drag &amp; Drop)</h3>
        <ol>
          <li>Uhvatite karticu časa levim tasterom miša (ili prstom na telefonu)</li>
          <li>Prevucite je na drugu poziciju unutar istog dana</li>
          <li>Otpustite — sistem automatski zamenjuje dva časa</li>
        </ol>
        <div class="warn">
          Drag &amp; drop radi samo unutar jednog dana. Za premještanje časa u drugi dan — obrišite ga na jednom mestu i dodajte ga na drugom.
        </div>

        <h3>Dvočas</h3>
        <ol>
          <li>Kliknite na čas koji treba da postane dvočas</li>
          <li>U dijalogu uključite prekidač <strong>Dvočas</strong></li>
          <li>Kliknite <strong>Sačuvaj</strong></li>
        </ol>
        <p>Na kartici časa pojaviće se oznaka <code>🔀 DVOČAS</code>, a sledeći čas će biti prikazan kao nastavak dvočasa. Na učeničkom prikazu dvočas se prikazuje kao jedna spojena kartica sa kombinovanim trajanjem.</p>
        <div class="info">
          Dvočas <strong>nije</strong> moguće postaviti na poslednji čas u danu (nema sledećeg časa).
        </div>
      </div>

      <!-- PREDMETI -->
      <div id="predmeti" class="card rounded-2xl p-6">
        <h2>📚 Predmeti</h2>
        <p>Ovde upravljate bazom svih predmeta koji se pojavljuju kao predlozi pri uređivanju rasporeda.</p>

        <h3>Dodavanje predmeta</h3>
        <ol>
          <li>Popunite formu levo: naziv, skraćenica, nastavnik, emoji, boja</li>
          <li>Za boju kliknite na color picker ili izaberite sa palete brzih boja</li>
          <li>Kliknite <strong>Dodaj predmet</strong></li>
        </ol>

        <h3>Izmena predmeta</h3>
        <ol>
          <li>Pronađite predmet u listi desno</li>
          <li>Kliknite ✏️ — forma levo se popuni podacima</li>
          <li>Izmenite šta je potrebno i kliknite <strong>Sačuvaj izmene</strong></li>
        </ol>
        <div class="tip">
          Izmena predmeta automatski ažurira sve časove u rasporedu koji koriste taj predmet (boja, naziv).
        </div>

        <h3>Brisanje predmeta</h3>
        <p>Kliknite 🗑️ pored predmeta. Ako je predmet <strong>zaključan</strong> (🔒), znači da se koristi u rasporedu — prvo ga uklonite iz rasporeda pa brišite.</p>

        <h3>Pretraga predmeta</h3>
        <p>Koristite polje <em>Pretraži...</em> u zaglavlju liste za brzo pronalaženje predmeta po nazivu ili imenu nastavnika.</p>
      </div>

      <!-- VIBER -->
      <div id="viber" class="card rounded-2xl p-6">
        <h2>💬 Viber</h2>
        <p>Brzo pisanje i slanje poruka roditeljima direktno na Viber grupu.</p>

        <h3>Pisanje poruke</h3>
        <ol>
          <li>Idite na stranicu <strong>Viber</strong></li>
          <li>U tekstualno polje ukucajte ili nalepite tekst poruke</li>
          <li>Koristite dugmad za brzo ubacivanje varijabli:<br>
            <code>[RAZRED]</code> — naziv razreda<br>
            <code>[NEDELJA_A/B]</code> — tip tekuće nedelje<br>
            <code>[DATUMI]</code> — datumi od–do tekuće nedelje<br>
            <code>[DATUM_DANAS]</code> — današnji datum
          </li>
          <li>Kliknite <strong>Pošalji na Viber</strong></li>
        </ol>
        <div class="info">
          <strong>Na mobilnom telefonu</strong> — otvara se Viber aplikacija odmah sa upisanom porukom. Samo izaberite grupu i pošaljite.<br><br>
          <strong>Na računaru</strong> — poruka se kopira u clipboard. Otvorite Viber na računaru, nalepite (<code>Ctrl+V</code>) i pošaljite.
        </div>

        <h3>Šabloni poruka</h3>
        <p>Šabloni su unapred napisane poruke koje možete brzo učitati u composer.</p>

        <h3>Kreiranje šablona</h3>
        <ol>
          <li>Napišite poruku u composeru</li>
          <li>Kliknite <strong>Sačuvaj kao šablon</strong></li>
          <li>Unesite naziv šablona i potvrdite</li>
        </ol>

        <h3>Korišćenje šablona</h3>
        <ol>
          <li>U listi šablona ispod composera kliknite <strong>Koristi</strong></li>
          <li>Tekst šablona se učitava u composer — možete ga dopuniti pre slanja</li>
        </ol>

        <h3>Brisanje šablona</h3>
        <p>Kliknite 🗑️ pored šablona i potvrdite brisanje.</p>
      </div>

      <!-- ISTORIJA IZMENA -->
      <div id="izmene" class="card rounded-2xl p-6">
        <h2>🧾 Istorija izmena</h2>
        <p>Stranica <strong>Izmene</strong> prikazuje poslednje admin akcije: izmene rasporeda, termina, predmeta, Viber šablona, podešavanja i lozinke.</p>
        <ul>
          <li>Filter <strong>Akcija</strong> sužava prikaz na konkretan tip izmene</li>
          <li>Filter <strong>Tip</strong> prikazuje samo izmene za raspored, predmet, termin ili podešavanje</li>
          <li>Prikazuje se do poslednjih 100 zapisa</li>
        </ul>
        <div class="info">
          Istorija izmena pomaže da vidite šta se dogodilo, ali ne vraća podatke sama. Za vraćanje baze koristi se backup/restore procedura.
        </div>
      </div>

      <!-- UCENICKI PRIKAZ -->
      <div id="ucenici" class="card rounded-2xl p-6">
        <h2>🎓 Učenički prikaz</h2>
        <p>Učenici koriste glavnu stranicu <code>/</code> — bez prijave.</p>
        <ul>
          <li><strong>Auto-skrol na današnji dan</strong> — stranica se automatski pomeri na dan koji je danas</li>
          <li><strong>Trenutni čas</strong> — prikazuje se progress bar i odbrojavanje do kraja časa</li>
          <li><strong>Sledeći čas</strong> — indicator koji čas sledi i kada počinje</li>
          <li><strong>Toggle A/B</strong> — dugme za pregled druge nedelje</li>
          <li><strong>Viber share</strong> — učenik može da podeli raspored dana sa prijateljem</li>
          <li><strong>Kopiraj raspored</strong> — kopira tekst rasporeda u clipboard</li>
        </ul>
        <div class="tip">
          Učenički prikaz je <strong>automatski u skladu</strong> sa svim izmenama koje napravite u admin panelu — nema potrebe za ručnim osvežavanjem.
        </div>
      </div>

      <!-- FAQ -->
      <div id="faq" class="card rounded-2xl p-6">
        <h2>❓ Česta pitanja</h2>

        <h3>Zaboravila sam lozinku, šta sad?</h3>
        <p>Lozinka je sačuvana u bazi. Kontaktirajte tehničku podršku koja može da resetuje lozinku direktno u bazi podataka ili u <code>lib/db.php</code> fajlu (seed podaci).</p>

        <h3>Slučajno sam obrisala čas, mogu li da ga vratim?</h3>
        <p>Nažalost, ne postoji <em>undo</em> funkcija. Čas morate ponovo dodati ručno — izaberite dan, period, predmet i učionicu kao ranije.</p>

        <h3>Raspored A i B nedelje su pobrkani, kako da popravim?</h3>
        <p>Idite na <strong>Dashboard</strong> → sekcija <em>Naizmenična nedelja</em>. Proverite i ispravite referentnu nedelju i njen tip (A ili B). Sistem odmah recalkuliše sve nedelje.</p>

        <h3>Viber ne otvara aplikaciju na telefonu?</h3>
        <p>Proverite da li je Viber instaliran na telefonu. Ako jeste a ne otvara, probajte da osvežite stranicu i ponovo kliknete na dugme. Na nekim Android uređajima može biti potrebno da potvrdite otvaranje Vibera u dijalogu.</p>

        <h3>Može li više razrednih starešina da se prijavi?</h3>
        <p>Trenutno sistem koristi jedan admin nalog. Za višekorisnički pristup potrebna je nadogradnja sistema.</p>

        <h3>Koliko podataka može sistem da čuva?</h3>
        <p>Baza podataka je SQLite fajl bez praktičnog ograničenja za ovu namenu. Raspored 5 dana × 8 časova × 2 nedelje = svega 80 slogova — sistem može lako da radi i sa 100× više podataka.</p>
      </div>

      <!-- FOOTER -->
      <div class="text-center text-slate-600 text-xs py-4">
        Raspored Časova OG1 · Admin Panel · v2.0<br>
        Vibe Divizija / Google Antigravity
      </div>

    </div><!-- /prose -->
  </div><!-- /grid -->
</div>

</body>
</html>
