# Решение проблемы: показывается README вместо главной страницы

## Проблема

Вы видите текст "Основные возможности" вместо главной страницы приложения.

## Причина

Вы открываете **репозиторий GitHub**, а не **сайт GitHub Pages**.

## Решение

### 1. Откройте правильный URL

**Неправильно (репозиторий):**
```
https://github.com/egormoiseyonok/CRM
```
Это показывает README.md файл.

**Правильно (GitHub Pages сайт):**
```
https://egormoiseyonok.github.io/CRM/
```
Это должно показывать `index.html` из папки `docs/`.

### 2. Проверьте настройки GitHub Pages

1. Откройте: https://github.com/egormoiseyonok/CRM/settings/pages
2. Убедитесь, что:
   - **Source:** "Deploy from a branch"
   - **Branch:** `main` (или ваша основная ветка)
   - **Folder:** `/docs` ⚠️ **ВАЖНО!**
3. Нажмите **Save**

### 3. Подождите деплой

После сохранения GitHub начнет деплой. Обычно это занимает 1-2 минуты.

Вы увидите сообщение:
> "Your site is live at https://egormoiseyonok.github.io/CRM/"

### 4. Проверьте результат

Откройте URL сайта (не репозитория):
```
https://egormoiseyonok.github.io/CRM/
```

Вы должны увидеть:
- ✅ Интерфейс CRM с сайдбаром
- ✅ Панель управления
- ✅ Сообщение о необходимости локального запуска (если бэкенд недоступен)

## Если все еще не работает

### Проверка 1: Файлы в репозитории

Убедитесь, что файлы добавлены в git:

```bash
git status
git add docs/
git commit -m "Add frontend files"
git push origin main
```

### Проверка 2: Структура папки docs/

Убедитесь, что в папке `docs/` есть:
- ✅ `index.html`
- ✅ `login.html`
- ✅ `css/` (с файлами стилей)
- ✅ `js/` (с JavaScript файлами)

### Проверка 3: Логи деплоя

1. Откройте: https://github.com/egormoiseyonok/CRM/actions
2. Проверьте, есть ли ошибки в последнем деплое

### Проверка 4: Кэш браузера

Очистите кэш браузера или откройте в режиме инкогнито:
```
Ctrl+Shift+N (Chrome)
Ctrl+Shift+P (Firefox)
```

## Разница между репозиторием и сайтом

| Репозиторий GitHub | GitHub Pages сайт |
|-------------------|-------------------|
| `github.com/username/repo` | `username.github.io/repo` |
| Показывает README.md | Показывает index.html |
| Для разработчиков | Для пользователей |
| Исходный код | Работающее приложение |

## Полезные ссылки

- **Редактировать настройки Pages:** https://github.com/egormoiseyonok/CRM/settings/pages
- **Просмотреть сайт:** https://egormoiseyonok.github.io/CRM/
- **Просмотреть репозиторий:** https://github.com/egormoiseyonok/CRM

