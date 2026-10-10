# Drivko – urny (kremacia.drivko.shop)

Interní aplikace pro objednávání uren s otiskem tlapky pro jednu kremaci.
Nahrazuje domluvu přes WhatsApp: zakázka = fotky tlapky + dřevo + objem urny (ml), volitelně vnitřní rozměry (čtverec; vnější = +2×8 mm, výška +8+15+8 mm); 3D náhled urny `public/js/urn-viewer.js` (tlapka a text se vypalují vždy na přední stěnu, ne na víko) (Three.js z CDN přes importmap, bez buildu); otisk tlapky je volitelný (`with_paw`), k tomu text na urnu po řádcích s velikostí písma (`urn_lines`, `Order::TEXT_SIZES`) a rozložení (`Order::LAYOUTS`, jen když je tlapka i text),
k ní vlákno zpráv, stav výroby a fotky hotového kusu (evidence dokončené práce).
Slouží i jako podklad pro budoucí informační systém kremace – proto se u zakázky
drží jejich číslo případu (`orders.case_number`).

Samostatný projekt, s e-shopem (`../drievko-eshop`) nic nesdílí.

## Stack
Laravel 13, PHP 8.3, PostgreSQL, Blade + Bootstrap 5 z CDN (žádný build frontendu).
Lokálně nemáme PHP – všechno přes Docker:

```sh
docker compose up -d                       # http://localhost:8081
docker exec kremacia_app php artisan migrate --seed
docker exec kremacia_app php artisan test
docker exec kremacia_app php artisan user:create jmeno@example.com "Jméno" [--admin]   # vypíše přístupový odkaz
docker exec kremacia_app php artisan user:link jmeno@example.com                   # nový odkaz, starý přestane platit
```

## Role
- `admin` – dílna Drivko: mění stavy, nahrává fotky hotového kusu, přehled, dřeva (s fotkou, výchozí volbou a slovenským názvem), uživatelé.
- `client` – kremace: zadává zakázky, píše zprávy, doplňuje fotky. Vidí všechny zakázky (je jediný zákazník).

## Přihlášení
Hlavně **přístupovým odkazem** `/vstup/{token}` – kremace má jeden sdílený účet a odkaz má uložený
v chatu, odkud ho otevírá pořád. Odkaz platí trvale, dokud ho dílna nezruší nebo nevydá nový.
- Přihlášení hledá podle SHA-256 tokenu (`users.access_token_hash`); token je navíc zašifrovaný APP_KEY
  (`access_token_encrypted`), aby ho dílna mohla v Uživatelích kdykoli znovu zobrazit a zkopírovat.
- Otevření přihlásí s remember cookie a přesměruje na `/` (token nezůstane v adrese).
- Nový odkaz / zrušení odkazu odhlásí všechna zařízení (`User::logoutEverywhere`).
- Roboti náhledů z chatu (Messenger, WhatsApp…) dostanou prázdnou stránku, nepřihlásí se.
- Heslo je volitelné (`password` nullable); `User::getAuthPassword` vrací '' – jinak by Laravel
  u účtu bez hesla nepřijal remember cookie.

## Cena
Dřevo má dvě ceny v eurech (`price_small_eur` do 1500 ml včetně, `price_large_eur` nad), k tomu
+10 € za gravírování (tlapka nebo text) – `Order::PRICE_SMALL_MAX_ML`, `Order::ENGRAVING_EUR`.
Cena se ukládá k zakázce (`orders.price_eur`) při zadání a přepočítá se jen při změně dřeva, objemu,
tlapky nebo textu – pozdější změna ceníku staré zakázky nemění. Přehled ukazuje součet za měsíc, CSV sloupec Cena.

## Zaplaceno
`orders.paid_at` – samostatný údaj mimo stavy výroby. Dílna zaškrtne v detailu (zapíše teď), datum jde opravit;
změna se zapíše do vlákna. Časové pásmo aplikace je Europe/Prague (`APP_TIMEZONE`).

## Jazyky (cs / sk)
Dílna mluví česky, kremace slovensky – každý si jazyk přepne v profilu (menu se jménem vpravo nahoře), dílna ho může nastavit i v Uživatelích.
- Texty v šablonách a kontrolerech přes `__('česká věta')` – čeština je klíč, překlad v `lang/sk.json`; popisky konstant
  (`Order::STATUSES`, `LAYOUTS`, `TEXT_SIZES`, `OrderController::FILTERS`, `User::ROLES`) se překládají až při zobrazení.
  Nový text = přidat ho i do `lang/sk.json`.
- `users.locale` → middleware `SetLocale`; nepřihlášený podle prohlížeče. E-maily jdou v jazyce příjemce (`Notifier`, `Mail::to()->locale()`).
- Dřevo má `name_sk`, zobrazovat `$wood->label` (do 3D náhledu jde český `name` kvůli barvě).
- Systémové zprávy ve vlákně: `OrderMessage::system($order, event, params)` – ukládá `meta`, zobrazuje `text()` v jazyce čtenáře.
- V UI se říká „dřevo“, ne „dřevina“ (přání zadavatele).

## Důležité
- Fotky leží na privátním disku `local` (storage/app/private), ven jen přes `PhotoController`
  přihlášeným. Originál se nemění, náhledy (WebP) dělá `App\Support\Photos`.
- Stav „Hotovo“ vyžaduje fotku hotového kusu. Časy stavů (`started_at`, `finished_at`,
  `handed_over_at`) jsou zdroj měsíčního přehledu – návrat do dřívějšího stavu je maže (`Order::moveTo`).
- E-maily: `App\Support\Notifier`, chyba odeslání se jen zaloguje.
- Testy běží na SQLite v paměti – v dotazech nepoužívat syntaxi jen pro PostgreSQL (např. `ilike`, použij `whereLike`).
