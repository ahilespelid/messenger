# Messenger

Лёгкий саппорт-мессенджер для переписки с клиентами: клиент открывает персональную
ссылку без регистрации, вы отвечаете из единой панели оператора. Сообщения доставляются
мгновенно через WebSocket (Laravel Reverb), плюс push-уведомления в браузер, даже если
вкладка не открыта. Никаких сторонних сервисов, номеров и блокировок — всё крутится на
вашем сервере.

## Стек

- **Laravel 13** на **Octane + FrankenPHP** (сервер и HTTPS — это встроенный в FrankenPHP Caddy,
  сертификаты Let's Encrypt выпускаются автоматически)
- **Percona Server** (MySQL-совместимая СУБД)
- **Redis** — очередь и кеш
- **Laravel Reverb** — свой WebSocket-сервер (real-time сообщения, статус "в сети", read-статусы)
- **Web Push (VAPID)** — нативные браузерные уведомления без Firebase/сторонних сервисов
- Фронтенд: Blade + Alpine.js + Tailwind (без SPA-фреймворков — быстро и легко)
- Всё запускается через **Docker Compose**

## Как это устроено

- `/` — форма "Написать нам". Клиент вводит имя (и опционально email), получает
  персональную ссылку вида `/c/{token}`. Ссылка запоминается в браузере клиента
  (localStorage), так что при повторном заходе он попадает в тот же диалог.
- `/operator` — панель оператора (нужен логин). Список диалогов слева, переписка справа,
  всё обновляется в реальном времени без перезагрузки страницы.
- Онлайн-статус, набор сообщения и статусы прочтения (✓ / ✓✓) — через presence-канал Reverb.
- При новом сообщении получателю уходит браузерное push-уведомление (если он разрешил
  уведомления), даже если вкладка закрыта.

## Локальный запуск (для разработки/проверки)

1. Скопируйте `.env.example` в `.env` и сгенерируйте секреты:

   ```bash
   cp .env.example .env
   ```

   Сгенерируйте `APP_KEY` (32 случайных байта в base64), ключи Reverb и пароли БД —
   впишите их в `.env` вручную, либо (после первой сборки образа) выполните:

   ```bash
   docker compose run --rm app php artisan key:generate --show
   docker compose run --rm app php artisan webpush:vapid
   ```

   Скопируйте результат в `APP_KEY` / `VAPID_PUBLIC_KEY` / `VAPID_PRIVATE_KEY` в `.env`.
   Для `REVERB_APP_KEY`, `REVERB_APP_SECRET`, `DB_PASSWORD`, `DB_ROOT_PASSWORD`,
   `OPERATOR_PASSWORD` — впишите любые случайные строки.

   Для локальной проверки оставьте `APP_DOMAIN=localhost` — Caddy сам выпустит
   локальный самоподписанный сертификат (браузер покажет предупреждение — это нормально).

2. Соберите и запустите:

   ```bash
   docker compose up -d --build
   ```

   При первом старте контейнер `app` сам дождётся базы, накатит миграции и создаст
   аккаунт оператора из `OPERATOR_EMAIL` / `OPERATOR_PASSWORD`.

3. Откройте `https://localhost` — форма клиента, `https://localhost/login` — вход
   оператора.

## Деплой на сервер (США)

Подходит любой VPS с Docker: DigitalOcean, Vultr, Linode, AWS Lightsail и т.д. —
достаточно 1 vCPU / 1 ГБ RAM для начала.

### 1. Подготовка

- Купите/арендуйте VPS с американской локацией (регион `us-east`, `us-west` и т.п.).
- Направьте DNS A-запись вашего домена (или поддомена, например `chat.example.com`)
  на IP сервера.
- Установите Docker и Docker Compose на сервере:

  ```bash
  curl -fsSL https://get.docker.com | sh
  sudo usermod -aG docker $USER
  ```

  (переподключитесь по SSH, чтобы группа `docker` применилась)

- Откройте порты 80 и 443 (TCP и UDP для 443) в файрволе/security group облака —
  они нужны для HTTP→HTTPS редиректа и автоматического выпуска сертификата.

### 2. Установите GitHub CLI (если ещё не установлен) и опубликуйте репозиторий

```bash
winget install --id GitHub.cli
gh auth login
```

Дальше из папки проекта:

```bash
gh repo create <ваш-аккаунт>/messenger --private --source=. --remote=origin --push
```

(или создайте пустой репозиторий на github.com и выполните `git remote add origin <url>`,
`git push -u origin main` вручную).

### 3. Разверните на сервере

Автоматически (рекомендуется для чистого Debian 13 — ставит Docker, генерирует
все пароли/ключи, собирает и запускает всё одной командой):

```bash
git clone https://github.com/ahilespelid/messenger.git
cd messenger
sudo ./deploy.sh chat.example.com admin@example.com
```

В конце скрипт выведет email и пароль оператора (они же сохранены в `.env`).
Повторный запуск `sudo ./deploy.sh ...` на уже развёрнутом сервере просто
подтянет свежий код, пересоберёт образы и перезапустит контейнеры — `.env`
не трогает.

Либо вручную, теми же шагами, что описаны в разделе «Локальный запуск» выше,
только с `APP_DOMAIN=ваш-домен` и без `HTTP_PORT`/`HTTPS_PORT` (порты 80/443
по умолчанию):

```bash
git clone https://github.com/ahilespelid/messenger.git
cd messenger
cp .env.example .env
nano .env   # впишите APP_DOMAIN=ваш-домен, пароли, ключи (см. раздел выше)
docker compose up -d --build
```

Первый запуск: Caddy автоматически получит сертификат Let's Encrypt для `APP_DOMAIN`
(домен должен уже резолвиться на IP сервера). Через 10–30 секунд сайт будет доступен
по `https://ваш-домен`.

### 4. Проверка

```bash
docker compose ps        # все сервисы должны быть Up (db — healthy)
docker compose logs -f app
```

Откройте `https://ваш-домен` — форма клиента, `https://ваш-домен/login` — вход
оператора (email/пароль из `OPERATOR_EMAIL` / `OPERATOR_PASSWORD` в `.env`).

### Обновление после изменений в коде

```bash
git pull
docker compose up -d --build
```

Миграции применяются автоматически при каждом старте контейнера `app`.

## Структура сервисов (docker-compose.yml)

| Сервис   | Назначение                                                         |
|----------|---------------------------------------------------------------------|
| `app`    | Laravel Octane (FrankenPHP) — HTTP(S), отдаёт сайт, порты 80/443     |
| `reverb` | WebSocket-сервер для real-time сообщений и статусов                 |
| `queue`  | Обработка очереди (отправка push-уведомлений)                       |
| `db`     | Percona Server (MySQL-совместимая БД)                                |
| `redis`  | Кеш и очередь заданий                                                |

## Полезные команды

```bash
# логи конкретного сервиса
docker compose logs -f queue

# зайти в контейнер приложения
docker compose exec app sh

# создать/сменить пароль оператора
docker compose exec app php artisan tinker --execute="
  \App\Models\User::updateOrCreate(['email' => 'operator@example.com'], ['name' => 'Operator', 'password' => bcrypt('новый-пароль')]);
"

# остановить всё
docker compose down

# полная остановка с удалением данных (БД, Redis) — осторожно
docker compose down -v
```
