# Drivko – urny

Objednávky uren s otiskem tlapky pro kremaci → https://kremacia.drivko.shop

Popis, spuštění a role: viz [CLAUDE.md](CLAUDE.md).

## Nasazení (poprvé)
1. Na serveru: databáze a uživatel v PostgreSQL, `/srv/drivko-kremacia/.env` podle `.env.example`
   (`APP_ENV=production`, `APP_DEBUG=false`, `DB_HOST=host.docker.internal`, `SESSION_SECURE_COOKIE=true`, `MAIL_MAILER=brevo` + `BREVO_API_KEY` stejný jako e-shop).
2. DNS: `kremacia.drivko.shop` → server; nginx podle `docker/nginx.host.example.conf`; `certbot --nginx -d kremacia.drivko.shop`.
3. Lokálně: `cp deploy/deploy.env.example deploy/deploy.env`, vyplnit, `./deploy.sh`.
   Při prvním běhu se vygeneruje `APP_KEY` – už ho neměňte (šifruje přístupové odkazy).
4. Na serveru: `docker exec kremacia_app php artisan db:seed --force` (číselníky)
   a `docker exec kremacia_app php artisan user:create vas@email "Dílna" --admin` – vypíše váš přístupový odkaz.
5. Účet a odkaz pro kremaci založíte v aplikaci v sekci Uživatelé. Výchozí dřevo (olše), fotky a slovenské názvy dřev
   nastavíte v sekci Dřeva.

## Další nasazení
Jen `./deploy.sh` – nahraje kód, přestaví Docker obraz, spustí migrace a obnoví cache.
Fotky (`storage/`) a `.env` na serveru zůstávají.

## Licence
MIT – viz [LICENSE](LICENSE).
