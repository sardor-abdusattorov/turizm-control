# Деплой turizm-control

Руководство по запуску проекта локально и на сервере.

- **Домен:** https://www.prcontrol.uz (+ `prcontrol.uz`)
- **Путь на сервере:** `/home/www/sardor_projects/turizm-control`
- **База данных:** внешний MySQL 8
- **Docker не используется** — приложение крутится нативно на хостовом
  nginx + PHP-FPM 8.3.

---

## Архитектура

```
Браузер ──HTTPS──> nginx (/etc/nginx, порты 80/443, TLS)
                     └── fastcgi_pass → php8.3-fpm (unix-сокет)
                            root = .../turizm-control/public

systemd: turizm-queue.service   → php artisan queue:work   (Telegram-джобы)
cron:    * * * * *              → php artisan schedule:run (4 напоминания)
```

Фоновых сервисов ровно два: **воркер очереди** и **планировщик**. WebSocket
(Reverb) не нужен — в приложении нет ни одного `ShouldBroadcast`-события и ни
одного слушателя Echo; конфиг оставлен на будущее, но процесс поднимать не надо.

---

## Требования к серверу

| Компонент | Версия | Зачем |
|-----------|--------|-------|
| PHP | **8.3** | приложение и CLI |
| Composer | 2.x | зависимости |
| Node.js | 20.19+ / 22.12+ | сборка ассетов (Vite 7) |
| MySQL | 8.x | БД |
| nginx | любой актуальный | HTTP + TLS |

Расширения PHP:

```bash
sudo apt install -y php8.3-fpm php8.3-cli php8.3-mysql php8.3-mbstring \
  php8.3-xml php8.3-curl php8.3-zip php8.3-gd php8.3-intl php8.3-bcmath
```

`gd` + `intl` обязательны: первый — для превью и Excel-экспорта, второй — для
форматирования дат/валют в отчётах.

---

## Локальная разработка

```bash
cp .env.example .env
composer install
npm install
php artisan key:generate
php artisan project:init      # migrate:fresh + shield + сидеры + супер-админ
composer run dev              # сервер + очередь + логи + Vite одной командой
```

Приложение — http://localhost:8000, Vite — :5173.

`.env.example` по умолчанию смотрит на MySQL `127.0.0.1:3306` (база `turizm`).
Для быстрого старта без MySQL можно переключиться на SQLite:

```env
DB_CONNECTION=sqlite
```

```bash
touch database/database.sqlite
php artisan project:init
```

---

## Production — пошагово

### 1. Клон и `.env`

```bash
cd /home/www/sardor_projects
git clone <repo> turizm-control
cd turizm-control
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
DB_HOST=127.0.0.1                # MySQL на этом же сервере
DB_PORT=3306
DB_DATABASE=pr_control
DB_USERNAME=pr_control
DB_PASSWORD=...

QUEUE_CONNECTION=database
CACHE_STORE=database
SESSION_DRIVER=database

TELEGRAM_BOT_TOKEN=...
TELEGRAM_BOT_USERNAME=...
TELEGRAM_WEBHOOK_SECRET=<длинная случайная строка>
```

`BROADCAST_CONNECTION`/`REVERB_*`/`VITE_REVERB_*` трогать не нужно — они
неактивны, пока в приложении нет вещания.

### 2. Настройка PHP

`sudo nano /etc/php/8.3/fpm/conf.d/99-turizm.ini` (и такой же файл для
`/etc/php/8.3/cli/conf.d/`, иначе `php artisan` будет падать на больших
импортах):

```ini
memory_limit = 1G
upload_max_filesize = 50M
post_max_size = 60M
max_execution_time = 300
max_input_vars = 10000
date.timezone = Asia/Tashkent

opcache.enable = 1
opcache.enable_cli = 0
opcache.memory_consumption = 256
opcache.interned_strings_buffer = 16
opcache.max_accelerated_files = 20000
opcache.validate_timestamps = 0
opcache.jit_buffer_size = 128M
opcache.jit = tracing
```

> `opcache.validate_timestamps=0` означает, что новый код не подхватится, пока
> не перезагрузить FPM. Деплой это делает сам (`systemctl reload php8.3-fpm`).

Загрузка сканов договоров ограничена 25 МБ на файл в самой форме, но грузить
можно несколько сразу — отсюда `post_max_size` больше `upload_max_filesize`.

```bash
sudo systemctl restart php8.3-fpm
```

### 3. Установка приложения

```bash
composer install --no-interaction --no-dev --prefer-dist --optimize-autoloader
php artisan key:generate
php artisan migrate --force
# shield:generate запрещён в production (FilamentShield::prohibitDestructiveCommands),
# поэтому для разовой генерации прав переопредели окружение на эту команду:
APP_ENV=local php artisan shield:generate --all --panel=admin
php artisan storage:link
php artisan filament:assets
```

> **Демо-данные.** `php artisan db:seed --force` создаёт демо-договоры,
> контакты, платежи и супер-админа `mr.silverwind1998@gmail.com`. На «чистый»
> прод этот шаг пропусти и назначь админа вручную:
> `APP_ENV=local php artisan shield:super-admin --user=1 --panel=admin`.

Права на запись (nginx/FPM работают от `www-data`):

```bash
sudo chown -R www-data:www-data storage bootstrap/cache
sudo chmod -R 775 storage bootstrap/cache
```

### 4. Сборка фронта

```bash
npm ci --no-audit --no-fund
npm run build
```

### 5. Кэш

```bash
php artisan project:cache      # config + routes + views + events
```

### 6. nginx

`sudo nano /etc/nginx/sites-available/prcontrol.uz`:

```nginx
server {
    listen 80; listen [::]:80;
    server_name www.prcontrol.uz prcontrol.uz;

    root /home/www/sardor_projects/turizm-control/public;
    index index.php;

    client_max_body_size 60M;

    # Канонический хост: апекс → www. Иначе apex и www — разные origin, и
    # картинки из /storage (генерятся по APP_URL=www) ловят CORS на apex-странице.
    if ($host = prcontrol.uz) {
        return 301 https://www.prcontrol.uz$request_uri;
    }

    charset utf-8;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass unix:/run/php/php8.3-fpm.sock;
        fastcgi_index index.php;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        fastcgi_param DOCUMENT_ROOT $realpath_root;
        include fastcgi_params;

        # Долгие операции: экспорт в Excel, генерация документов.
        fastcgi_read_timeout 300;
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }

    location = /favicon.ico { access_log off; log_not_found off; }
    location = /robots.txt  { access_log off; log_not_found off; }

    error_page 404 /index.php;
}
```

```bash
sudo ln -s /etc/nginx/sites-available/prcontrol.uz /etc/nginx/sites-enabled/
sudo nginx -t && sudo systemctl reload nginx
```

### 7. HTTPS (Let's Encrypt)

```bash
sudo apt install -y certbot python3-certbot-nginx
sudo certbot --nginx -d www.prcontrol.uz -d prcontrol.uz
```

Certbot сам допишет 443 и редирект http→https.

### 8. Очередь (systemd)

Telegram-карточки согласования, напоминания и рассылки уходят через очередь
(`QUEUE_CONNECTION=database`), поэтому воркер обязателен — без него кнопки в
боте просто не придут.

`sudo nano /etc/systemd/system/turizm-queue.service`:

```ini
[Unit]
Description=turizm-control queue worker
After=network.target mysql.service

[Service]
User=www-data
Group=www-data
Restart=always
RestartSec=5
WorkingDirectory=/home/www/sardor_projects/turizm-control
ExecStart=/usr/bin/php artisan queue:work --sleep=3 --tries=3 --max-time=3600

[Install]
WantedBy=multi-user.target
```

```bash
sudo systemctl daemon-reload
sudo systemctl enable --now turizm-queue
sudo systemctl status turizm-queue
```

`--max-time=3600` заставляет воркер перезапускаться раз в час — так он не
копит утечки памяти и гарантированно берёт свежий код.

### 9. Планировщик (cron)

Четыре команды по расписанию: напоминания о согласовании (ежечасно), курс валют
(07:00), дедлайны проектов (08:30), напоминания об оплате (09:00) —
см. `routes/console.php`.

```bash
sudo crontab -u www-data -e
```

```cron
* * * * * cd /home/www/sardor_projects/turizm-control && php artisan schedule:run >> /dev/null 2>&1
```

### 10. Telegram webhook

```bash
php artisan telegram:webhook:install
```

Роут `/telegram/webhook` защищён `TELEGRAM_WEBHOOK_SECRET` и throttle —
секрет должен быть в `.env` **до** установки вебхука.

### 11. Проверка

- https://www.prcontrol.uz → страница входа `/login`
- Открыть договор → цепочка согласования и вложения на месте, «Скачать
  документ» отдаёт `.docx`
- `sudo systemctl status turizm-queue` → `active (running)`
- `php artisan schedule:list` → четыре команды с ближайшим временем запуска

---

## CI/CD (автодеплой)

Workflow `.github/workflows/deploy.yml` срабатывает на push в `main`:

1. **test** — ставит PHP 8.3 (та же версия, что на проде), `composer install`,
   гоняет `php artisan test --compact` (sqlite в памяти, БД не нужна). Красные
   тесты блокируют деплой.
2. **deploy** — по SSH: `git reset --hard origin/main`, `composer install`,
   **сборка ассетов прямо на сервере**, `migrate --force`, `filament:assets`,
   кэш, `queue:restart`, `reload php8.3-fpm`.

> Ассеты собираются на сервере, а не в CI: Vite зашивает `VITE_*` в бандл на
> этапе сборки, а актуальные значения есть только в прод-`.env`. Поэтому на
> сервере должен стоять Node 20.19+ / 22.12+.

### Секреты GitHub

GitHub → **Settings → Secrets and variables → Actions** → *New repository secret*:

| Secret | Значение |
|--------|----------|
| `SERVER_HOST` | IP сервера |
| `SERVER_USER` | `s_abdusattorov` |
| `SERVER_SSH_KEY` | приватный SSH-ключ (целиком, с `-----BEGIN…`) |
| `SERVER_PORT` | `22` |
| `SERVER_APP_PATH` | `/home/www/sardor_projects/turizm-control` |

Деплой делает `sudo systemctl reload php8.3-fpm` — разреши это без пароля:

```bash
echo 's_abdusattorov ALL=(root) NOPASSWD: /bin/systemctl reload php8.3-fpm' \
  | sudo tee /etc/sudoers.d/turizm-deploy
```

### SSH-ключ для деплоя

На сервере сгенерируй пару ключей для GitHub Actions и разреши вход по ней:

```bash
ssh-keygen -t ed25519 -f ~/.ssh/github_deploy -N "" -C "github-actions"
cat ~/.ssh/github_deploy.pub >> ~/.ssh/authorized_keys
chmod 600 ~/.ssh/authorized_keys
cat ~/.ssh/github_deploy        # ← это значение целиком в секрет SERVER_SSH_KEY
```

Приватный ключ (`github_deploy`) — в секрет `SERVER_SSH_KEY`, публичный остаётся
на сервере. После этого: merge ветки в `main` → push → деплой пойдёт сам.

---

## Обслуживание

```bash
# логи приложения
tail -f storage/logs/laravel.log

# воркер очереди
sudo systemctl status turizm-queue
sudo journalctl -u turizm-queue -f
sudo systemctl restart turizm-queue

# обновить вручную (если без CI)
git pull origin main
composer install --no-interaction --no-dev --prefer-dist --optimize-autoloader
npm ci && npm run build
php artisan migrate --force
php artisan filament:assets
php artisan optimize:clear && php artisan project:cache
php artisan queue:restart
sudo systemctl reload php8.3-fpm
```

### Артизан-команды проекта

| Команда | Что делает | Где можно |
|---------|------------|-----------|
| `project:init` | **сносит БД** (`migrate:fresh`) + shield + сидеры + супер-админ | только dev — в production падает с ошибкой |
| `project:update` | `migrate` + `shield:generate --ignore-existing-policies` + кэш | прод |
| `project:cache` | config/route/view/event кэш | прод |

На проде обновление — это `project:update`, а не `project:init`.

---

## Заметки

- **Права на storage.** Если картинки/вложения не грузятся — почти всегда
  `storage/app` принадлежит не `www-data`. Проверяй после ручного `git pull`
  из-под другого пользователя.
- **OPcache.** При `validate_timestamps=0` правка файла на сервере без
  `reload php8.3-fpm` не даст эффекта — это не «кэш Laravel».
- **Reverb.** Конфиг есть, процесс не нужен: сейчас ничего не вещает. Если
  появится `ShouldBroadcast`-событие — подними `php artisan reverb:start`
  отдельным systemd-юнитом и проксируй `/app` на `127.0.0.1:8080`.
</content>
