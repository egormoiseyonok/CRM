# Инструкция по деплою фронтенда на GitHub Pages

## Быстрый старт

### Вариант 1: Деплой из папки docs/ (рекомендуется)

1. **Подготовка репозитория:**
   ```bash
   # Папка docs/ уже создана и содержит весь фронтенд
   # GitHub Pages автоматически поддерживает папку docs/
   ```

2. **Настройка GitHub Pages:**
   - Перейдите в Settings → Pages вашего репозитория
   - В разделе "Source" выберите "Deploy from a branch"
   - Выберите ветку (обычно `main` или `master`)
   - В поле "Folder" выберите `/docs`
   - Нажмите Save

3. **Альтернатива через GitHub Actions:**
   Создайте файл `.github/workflows/deploy-frontend.yml`:
   ```yaml
   name: Deploy Frontend to GitHub Pages
   
   on:
     push:
       branches: [ main ]
       paths:
         - 'docs/**'
   
   jobs:
     deploy:
       runs-on: ubuntu-latest
       steps:
         - uses: actions/checkout@v3
         - name: Deploy to GitHub Pages
           uses: peaceiris/actions-gh-pages@v3
           with:
             github_token: ${{ secrets.GITHUB_TOKEN }}
             publish_dir: ./docs
   ```

### Вариант 2: Деплой в отдельную ветку gh-pages

1. **Создайте ветку gh-pages:**
   ```bash
   git checkout -b gh-pages
   git rm -rf .
   git checkout main -- docs/
   # Переместите содержимое docs/ в корень
   git mv docs/* .
   git mv docs/.* . 2>/dev/null || true
   git commit -m "Deploy frontend to gh-pages"
   git push origin gh-pages
   ```

2. **Настройте GitHub Pages:**
   - В Settings → Pages выберите ветку `gh-pages`
   - Укажите папку `/ (root)`

## Структура после деплоя

После деплоя структура должна быть такой:

```
/
├── index.html
├── login.html
├── css/
│   ├── main.css
│   ├── layout.css
│   └── components.css
├── js/
│   ├── config.js
│   ├── api.js
│   ├── app.js
│   ├── layout.js
│   └── pages/
└── ...
```

## Важные замечания

1. **Относительные пути:** Все пути в HTML и JS должны быть относительными (начинаться с `/` или без префикса)

2. **API недоступен:** На GitHub Pages PHP бэкенд недоступен, поэтому:
   - Пользователи увидят сообщение о необходимости локального запуска
   - Интерфейс будет доступен для просмотра, но без функциональности

3. **CORS:** Если планируете подключать удаленный бэкенд, обновите `api/cors.php` с вашим доменом

## Проверка деплоя

После деплоя откройте:
- `https://ваш-username.github.io/ваш-репозиторий/` (если деплой из папки)
- `https://ваш-username.github.io/ваш-репозиторий/` (если деплой из корня gh-pages)

Вы должны увидеть интерфейс приложения с сообщением о необходимости локального запуска.

## Локальная разработка

Для разработки с полной функциональностью:

1. Запустите PHP сервер (XAMPP, PHP built-in server и т.д.)
2. Откройте `http://localhost/CRM/docs/`
3. Фронтенд автоматически подключится к PHP API на `http://localhost/CRM/api/`

## Обновление деплоя

После изменений в `docs/`:

1. Закоммитьте изменения
2. Запушьте в репозиторий
3. GitHub Pages автоматически обновится (может занять несколько минут)

Или если используете GitHub Actions, просто запушьте изменения - workflow автоматически задеплоит.

