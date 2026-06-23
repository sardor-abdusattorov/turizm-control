# Деплой turizm-control

Руководство по запуску проекта локально и на сервере.

- **Домен:** https://www.prcontrol.uz (+ `prcontrol.uz`, `office.prcontrol.uz`)
- **Путь на сервере:** `/home/www/sardor_projects/turizm-control`
- **База данных:** внешний MySQL 8 (на проде контейнера БД нет)

---

## Архитектура

```
Браузер ──HTTPS──> хостовый nginx (/etc/nginx, порты 80/443, TLS)
   www.prcontrol.uz/      → 127.0.0.1:8000   Docker nginx → PHP-FPM (app)
   www.prcontrol.uz/app   → 127.0.0.1:8080   Reverb (WebSocket)
   office.prcontrol.uz/   → 127.0.0.1:8082   OnlyOffice
```

Docker разбит на два файла:

| Файл | Назначение | Сервисы |
|------|------------|---------|
| `docker-compose.yml` | **прод** | nginx, app, queue, scheduler, reverb, onlyoffice |
| `docker-compose.override.yml` | **дев** (доп. поверх прод) | mysql, phpmyadmin, node (Vite) |

`docker compose` локально сам подмешивает override. На проде запускаем с
`-f docker-compose.yml`, поэтому dev-сервисы (MySQL/phpMyAdmin/Vite) **на сервере
никогда не стартуют** — БД там внешняя.

---

## Локальная разработка

```bash
docker compose up -d                 # поднимет ВСЁ: прод-сервисы + mysql/pma/vite
```

Первый запуск:

```bash
cp .env.example .env
docker compose up -d
docker compose exec app composer install
docker compose exec app php artisan key:generate
docker compose exec app php artisan project:init   # migrate:fresh + shield + сидеры + супер-админ
```

Адреса: http://localhost:8000 (приложение) · :8081 (phpMyAdmin) ·
:8082 (OnlyOffice) · :5173 (Vite).

Остановить: `docker compose down`. Тяжёлый OnlyOffice можно гасить, когда не нужен:
`docker compose stop onlyoffice node`.

---

## Production — пошагово

### 1. Клон и `.env`

```bash
cd /home/www/sardor_projects/turizm-control
cp .env.example .env
nano .env
```

Ключевые значения:

```env
APP_ENV=production
APP_DEBUG=false
APP_URL=https://www.prcontrol.uz
APP_KEY=                         # заполнит key:generate

DB_CONNECTION=mysql
DB_HOST=<внешний MySQL host>
DB_PORT=3306
DB_DATABASE=...
DB_USERNAME=...
DB_PASSWORD=...

QUEUE_CONNECTION=database

BROADCAST_CONNECTION=reverb
REVERB_APP_ID=<рандом>
REVERB_APP_KEY=<рандом>
REVERB_APP_SECRET=<рандом>
REVERB_HOST=reverb               # сервер публикует во внутренний контейнер
REVERB_PORT=8080
REVERB_SCHEME=http
VITE_REVERB_APP_KEY="${REVERB_APP_KEY}"
VITE_REVERB_HOST=www.prcontrol.uz   # браузер коннектится по домену (wss)
VITE_REVERB_PORT=443
VITE_REVERB_SCHEME=https

ONLYOFFICE_PUBLIC_URL=https://office.prcontrol.uz
ONLYOFFICE_INTERNAL_URL=http://onlyoffice
ONLYOFFICE_CALLBACK_HOST=http://nginx
ONLYOFFICE_JWT_SECRET=<сильный секрет>
```

> ⚠️ `VITE_REVERB_*` должны быть в `.env` **до сборки ассетов** — Vite зашивает их в JS.

### 2. Поднять прод-стек

```bash
docker compose -f docker-compose.yml up -d --remove-orphans
```

Первый `up` долгий — тянется образ OnlyOffice (~2–3 ГБ). Это разово, дальше из кэша.

### 3. Инициализация приложения

```bash
docker compose -f docker-compose.yml exec app composer install --no-dev --optimize-autoloader
docker compose -f docker-compose.yml exec app php artisan key:generate
docker compose -f docker-compose.yml exec app php artisan migrate --force
docker compose -f docker-compose.yml exec app php artisan shield:generate --all
docker compose -f docker-compose.yml exec app php artisan db:seed --force      # демо-данные (по желанию)
docker compose -f docker-compose.yml exec app php artisan filament:assets
docker compose -f docker-compose.yml exec app php artisan storage:link
```

> ⚠️ `db:seed` создаёт демо-договоры/контакты/платежи и супер-админа
> `mr.silverwind1998@gmail.com`. На «чистый» прод без демо — пропусти этот шаг и
> назначь админа вручную: `php artisan shield:super-admin --user=1`.

### 4. Сборка фронта (ассеты)

На проде нет Vite-контейнера. Вариант с node на сервере (нужен Node 20.19+ / 22.12+):

```bash
npm ci && npm run build
```

Либо разово через контейнер (если node на сервере старый/отсутствует):

```bash
docker run --rm -v "$PWD":/app -w /app node:24-alpine sh -c "npm ci && npm run build"
```

### 5. Кэш

```bash
docker compose -f docker-compose.yml exec app php artisan optimize
```

### 6. nginx (reverse proxy)

`sudo nano /etc/nginx/sites-available/prcontrol.uz`:

```nginx
server {
    listen 80; listen [::]:80;
    server_name www.prcontrol.uz prcontrol.uz;
    client_max_body_size 60M;

    location /app {                       # Reverb (WebSocket)
        proxy_pass http://127.0.0.1:8080;
        proxy_http_version 1.1;
        proxy_set_header Upgrade $http_upgrade;
        proxy_set_header Connection "upgrade";
        proxy_set_header Host $host;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto $scheme;
        proxy_read_timeout 3600s;
    }
    location / {                          # приложение
        proxy_pass http://127.0.0.1:8000;
        proxy_http_version 1.1;
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto $scheme;
        proxy_set_header X-Forwarded-Host $host;
    }
}

server {
    listen 80; listen [::]:80;
    server_name office.prcontrol.uz;
    client_max_body_size 100M;

    location / {                          # OnlyOffice
        proxy_pass http://127.0.0.1:8082;
        proxy_http_version 1.1;
        proxy_set_header Upgrade $http_upgrade;
        proxy_set_header Connection "upgrade";
        proxy_set_header Host $host;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto $scheme;
        proxy_read_timeout 3600s;
    }
}
```

```bash
sudo ln -s /etc/nginx/sites-available/prcontrol.uz /etc/nginx/sites-enabled/
sudo nginx -t && sudo systemctl reload nginx
```

### 7. HTTPS (Let's Encrypt)

```bash
sudo apt install -y certbot python3-certbot-nginx
sudo certbot --nginx -d www.prcontrol.uz -d prcontrol.uz -d office.prcontrol.uz
```

Certbot сам допишет 443 и редирект http→https.

### 8. Проверка

- https://www.prcontrol.uz → страница входа `/login`
- https://office.prcontrol.uz → «Document Server is running» (стартует ~30–60 сек)
- Открыть договор → грузится редактор OnlyOffice
- Колокольчик уведомлений обновляется в реальном времени

---

## CI/CD (автодеплой)

Workflow `.github/workflows/deploy.yml` срабатывает на push в `main`: собирает
ассеты, заливает на сервер, делает `git reset --hard`, поднимает прод-стек,
`composer install`, миграции и кэш.

В GitHub → **Settings → Secrets and variables → Actions** добавь:

| Secret | Значение |
|--------|----------|
| `SERVER_HOST` | IP сервера |
| `SERVER_USER` | `s_abdusattorov` |
| `SERVER_SSH_KEY` | приватный SSH-ключ |
| `SERVER_PORT` | `22` |
| `SERVER_APP_PATH` | `/home/www/sardor_projects/turizm-control` |

Дальше: merge в `main` → push → деплой автоматически.

---

## Обслуживание

```bash
# статус и логи
docker compose -f docker-compose.yml ps
docker compose -f docker-compose.yml logs -f app

# рестарт сервиса
docker compose -f docker-compose.yml restart app

# обновить вручную (если без CI)
git pull origin main
docker compose -f docker-compose.yml up -d
docker compose -f docker-compose.yml exec app composer install --no-dev --optimize-autoloader
docker compose -f docker-compose.yml exec app php artisan migrate --force
npm ci && npm run build
docker compose -f docker-compose.yml exec app php artisan optimize
```

---

## Несколько проектов на одном сервере

Имена сервисов (`app`, `queue`, …) изолированы по проекту — не конфликтуют.
Конфликтуют только **порты на хосте**. turizm занимает `8000`, `8080`, `8082`.
Если заняты — поменяй левую (хостовую) часть в `docker-compose.yml` и
соответствующие `proxy_pass` в nginx. Внутренние порты контейнеров не трогать.

---

## Заметки

- **Безопасность.** Чтобы `8000/8080/8082` не были доступны публично в обход TLS,
  можно привязать их к localhost в `docker-compose.yml`:
  `ports: ["127.0.0.1:8000:80"]` и т.д. Хостовый nginx достучится, снаружи — нет.
- **OnlyOffice** требует HTTPS-поддомен (`office.prcontrol.uz`), потому что
  редактор грузится в браузере, а сайт работает по HTTPS (mixed content).
- **Reverb.** Сервер публикует события во внутренний `reverb:8080`, браузер
  подключается по `wss://www.prcontrol.uz/app` (через nginx). Поэтому
  `REVERB_HOST=reverb`, а `VITE_REVERB_HOST=www.prcontrol.uz`.
