# Агентство Недвижимости Жилфонд

Проект на Laravel 13 с Docker-окружением через Laravel Sail и базой данных MySQL.

## Требования

- Docker и Docker Compose

## Быстрый старт

1. Запустить контейнеры:

```bash
./vendor/bin/sail up -d
```

2. Применить миграции:

```bash
./vendor/bin/sail artisan migrate
```

3. Открыть приложение:

`http://localhost:8080`

## Основные команды

### Управление окружением

```bash
./vendor/bin/sail up -d      # поднять контейнеры
./vendor/bin/sail down       # остановить контейнеры
./vendor/bin/sail logs -f    # смотреть логи
```

### PHPStan (уровень 5)

Проверка статического анализа:

```bash
./vendor/bin/sail composer phpstan
```

Конфигурация находится в файле `phpstan.neon`.

### Code style (Laravel Pint)

Проверка стиля без изменений файлов:

```bash
./vendor/bin/sail composer lint
```

Автоматическое исправление стиля:

```bash
./vendor/bin/sail composer lint:fix
```

Конфигурация находится в файле `pint.json`.

## Архитектура уведомлений

- Используется табличная очередь в MySQL: `notification_deliveries`
- Основная сущность уведомления: `notifications`
- `payload` хранится как JSON и передается в канал доставки
- Каналы изолированы по классам и реализуют единый контракт `App\Contracts\NotificationChannel`
    - `EmailNotificationChannel`
    - `TelegramNotificationChannel`
- Выбор канала выполняет `NotificationChannelManager`, поэтому добавление нового канала не требует изменения существующих каналов
- Обработчик `notifications:work` берёт задачи из таблицы, отправляет, обновляет статусы и делает retry с backoff

## API уведомлений

### Создать уведомление

`POST /api/notifications`

Пример тела:

```json
{
  "user_id": 1,
  "channel": "email",
  "message": "Новое предложение по объекту",
  "payload": {
    "subject": "Жилфонд",
    "simulate_failure": false
  }
}
```

### Получить статус уведомления

`GET /api/notifications/{id}`

### История пользователя с фильтрами

`GET /api/users/{userId}/notifications?status=sent&channel=email`

## Воркер очереди уведомлений

Однократная обработка (удобно для локальной проверки):

```bash
./vendor/bin/sail artisan notifications:work --once
```

Постоянный режим:

```bash
./vendor/bin/sail artisan notifications:work
```
