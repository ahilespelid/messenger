# Messenger

Laravel + Octane (FrankenPHP) + Reverb (WebSocket) + Percona + Redis, all run
via Docker Compose. There is no local PHP/Composer/Node setup — everything
happens inside containers.

## Workflow

- Edit PHP/Blade/JS files directly; nothing needs to be installed locally.
- To apply changes: `docker compose build app && docker compose up -d --force-recreate app`
  (swap `app` for `reverb`/`queue` if those layers changed).
- Logs: `docker compose logs -f app` (or `reverb`, `queue`, `db`).
- One-off artisan commands: `docker compose exec app php artisan ...`.
- `.env` is git-ignored (real secrets); `.env.example` is the committed template.

## Architecture notes

- `routes/channels.php` — `conversation.{token}` is a **presence** channel
  (not private). Broadcast events targeting it must use `PresenceChannel`,
  not `PrivateChannel`, or Reverb will silently drop the broadcast (no
  matching channel found, no error).
- Anonymous clients authenticate via a custom `client` guard
  (`app/Auth/ConversationGuest.php` + `ConversationGuestProvider`) so they
  can pass Laravel's private/presence broadcasting auth check without a real
  account — see `config/auth.php`.
- `REVERB_HOST/PORT/SCHEME` (internal, `reverb:8080`) are for server→Reverb
  traffic; `REVERB_CLIENT_HOST/PORT/SCHEME` (public domain) are what the
  browser uses. Do not conflate the two.
- Queued listeners must receive extra service dependencies via the
  constructor, not extra `handle()` parameters (the queue's listener caller
  only passes the event).

See [README.md](README.md) for the full deployment guide.
