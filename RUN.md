# Запуск проекта

Нужен только **Docker Desktop**. Больше ничего ставить не надо (PHP, Composer, MySQL, Node — всё внутри контейнеров).

---

## Первый запуск

```bash
# 1. Скопировать конфиг
cp .env.example .env

# 2. Поднять контейнеры (первый раз качает образы ~5-10 мин)
docker compose up -d

# 3. Зависимости
docker compose exec php composer install

# 4. Ключ приложения
docker compose exec php php artisan key:generate

# 5. БД + сидеры
docker compose exec php php artisan migrate --seed

# 6. Симлинк storage
docker compose exec php php artisan storage:link

# 7. Сборка фронтенда
docker compose run --rm node sh -c "npm install && npm run build"
```

Готово. Открыть **http://localhost:8000/admin**

Логин: `mr.silverwind1998@gmail.com` / `123456`

---

## Адреса

| Что | URL | Доступ |
|-----|-----|--------|
| Приложение | http://localhost:8000/admin | см. логин выше |
| phpMyAdmin | http://localhost:8081 | root / secret |
| OnlyOffice | http://localhost:8082 | — |

---

## Повседневные команды

```bash
docker compose up -d            # запустить
docker compose down             # остановить
docker compose ps               # статус контейнеров
docker compose logs -f php      # логи php
docker compose logs -f nginx    # логи nginx
```

Artisan и Composer — через контейнер `php`:

```bash
docker compose exec php php artisan migrate
docker compose exec php php artisan optimize:clear
docker compose exec php composer require <package>
```

Пересобрать фронтенд после правок темы/CSS:

```bash
docker compose run --rm node sh -c "npm run build"
```

Пересоздать БД с нуля:

```bash
docker compose exec php php artisan migrate:fresh --seed
```

---

## Если что-то не открывается

**Порт 33060 занят (OSPanel и т.п.)** — MySQL наружу проброшен на `33061`, конфликта быть не должно. Если занят другой порт — поменяй в `docker-compose.yml`.

**nginx рестартится / 502** — подожди пока поднимется `php`, проверь `docker compose ps`. nginx резолвит php динамически, должен сам подняться.

**Старый сайт из кэша браузера (service worker)** — открой в режиме инкогнито, либо F12 → Application → Service Workers → Unregister.

**Ассеты/тема не подгрузились** — выполни `docker compose run --rm node sh -c "npm run build"` и обнови страницу с Ctrl+Shift+R.

**OnlyOffice долго стартует** — после `up` ему нужно ~1-2 мин на первый запуск. Проверь http://localhost:8082 (должно быть "Document Server is running").

---

## Запуск без Docker (OSPanel / локальный PHP)

Если поднимаешь напрямую, а не в Docker — в `.env` поменяй:

```
DB_HOST=127.0.0.1
```

И БД/пользователя настрой под свой локальный MySQL.
