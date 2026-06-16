# Деплой на Railway

Плагин «вшит» в Docker-образ WordPress, поэтому он переживает редеплои на
эфемерной файловой системе Railway — не нужно ставить его через wp-admin и
ничего не теряется при перезапуске.

## Что в репозитории для деплоя

- **`Dockerfile`** — `wordpress:6-php8.3-apache` с плагином, скопированным в
  исходники WordPress, включённым `mod_rewrite` и Apache, слушающим `$PORT`
  от Railway.
- **`railway.json`** — указывает Railway собирать образ из Dockerfile.
- **`bin/export-db.sh`** — делает дамп локальной БД, совместимый с MySQL 8.

## 1. Создать проект на Railway

1. **New Project → Deploy from GitHub repo** (сначала запушьте репозиторий) или
   **Empty Project** + Railway CLI (`railway up`).
2. Добавить базу: **New → Database → MySQL**. Railway поднимет её и отдаст
   переменные `MYSQLHOST`, `MYSQLPORT`, `MYSQLUSER`, `MYSQLPASSWORD`,
   `MYSQLDATABASE` (имя базы — `railway`).
3. Railway увидит `railway.json` / `Dockerfile` и соберёт сервис WordPress.

## 2. Переменные окружения (сервис WordPress → Variables)

Используйте reference-переменные Railway, чтобы приложение ходило в MySQL по
приватной сети:

| Переменная | Значение |
|---|---|
| `WORDPRESS_DB_HOST` | `${{MySQL.MYSQLHOST}}:${{MySQL.MYSQLPORT}}` |
| `WORDPRESS_DB_USER` | `${{MySQL.MYSQLUSER}}` |
| `WORDPRESS_DB_PASSWORD` | `${{MySQL.MYSQLPASSWORD}}` |
| `WORDPRESS_DB_NAME` | `${{MySQL.MYSQLDATABASE}}` |
| `WORDPRESS_CONFIG_EXTRA` | _(многострочное, вставьте PHP-блок ниже)_ |

```php
if ( ! empty( $_SERVER['HTTP_X_FORWARDED_PROTO'] ) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https' ) {
    $_SERVER['HTTPS'] = 'on';
}
if ( ! empty( $_SERVER['HTTP_HOST'] ) ) {
    $scheme = ( ! empty( $_SERVER['HTTPS'] ) && $_SERVER['HTTPS'] !== 'off' ) ? 'https' : 'http';
    define( 'WP_HOME', $scheme . '://' . $_SERVER['HTTP_HOST'] );
    define( 'WP_SITEURL', $scheme . '://' . $_SERVER['HTTP_HOST'] );
}
```

> Это заставляет адрес сайта следовать за тем доменом, который его отдаёт
> (домен Railway или ваш собственный), и считать запрос HTTPS за прокси Railway —
> тот же приём, что используется локально для ngrok. Поскольку `WP_HOME`/
> `WP_SITEURL` задаются на лету, URL-ы из перенесённой БД переписывать не нужно.

## 3. Сеть

- Сервис WordPress → **Settings → Networking → Generate Domain**.
- Укажите **target port `80`** (образ также слушает `$PORT`, так что подойдёт
  любой вариант).

## 4. Перенос базы данных

Данные плагина лежат в кастомных таблицах (`wp_naase_questions`,
`wp_naase_attempts`) плюс стандартные опции/страницы WP — полный дамп переносит
всё (банк вопросов, настройки, две страницы с шорткодами, админ-пользователь).

```bash
# 1. Экспорт локально (Docker должен быть запущен). Создаёт migration/wordpress-dump.sql
bin/export-db.sh

# 2. Импорт в MySQL на Railway по ПУБЛИЧНЫМ реквизитам подключения
#    (MySQL service → Connect → Public Network даёт host + port).
mysql --host <PROXY_HOST> --port <PROXY_PORT> \
      -u root -p<MYSQL_ROOT_PASSWORD> railway < migration/wordpress-dump.sql
```

После импорта **сделайте редеплой сервиса WordPress** (чтобы он стартовал уже с
заполненной БД), затем в wp-admin зайдите в **Настройки → Постоянные ссылки →
Сохранить** один раз — это сбросит правила rewrite для URL результата и
лидерборда.

### Опционально: удалить 26 демо-строк лидерборда

В локальной БД есть демо-попытки (`demo0…25@example.com`) для теста пагинации.
Чтобы начать с чистого листа, выполните до или после импорта:

```sql
DELETE FROM wp_naase_attempts WHERE email LIKE 'demo%@example.com';
DELETE FROM wp_options WHERE option_name = '_transient_naase_leaderboard_ranked';
```

## 5. Сохранение загрузок (опционально)

Ядро WordPress и плагины пересобираются из образа при каждом деплое (БД отдельно
и сохраняется). Если редакторы будут загружать медиа через wp-admin, добавьте
**Volume, примонтированный к `/var/www/html/wp-content/uploads`**, чтобы эти
файлы не пропадали. **Не** монтируйте Volume на весь `/var/www/html` — он
перекроет вшитый плагин и тот перестанет обновляться при редеплое.

## Примечания

- Zapier-вебхук — это обычный исходящий `wp_remote_post`, на Railway работает.
- Бейджи уровней — статические PNG в плагине (`assets/badges/`), поэтому
  расширение GD и серверная генерация картинок в рантайме не нужны.
