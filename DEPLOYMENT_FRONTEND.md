# Инструкция по деплою фронтенда на GitHub Pages

## Быстрый старт

### Вариант 1: Деплой из папки frontend/

1. **Подготовка репозитория:**
   ```bash
   # Если еще не создали папку frontend, создайте её
   # Убедитесь, что все файлы фронтенда находятся в frontend/
   ```

2. **Настройка GitHub Pages:**
   - Перейдите в Settings → Pages вашего репозитория
   - В разделе "Source" выберите "Deploy from a branch"
   - Выберите ветку (обычно `main` или `master`)
   - В поле "Folder" укажите `/frontend` (или `/docs` если переименуете)
   - Нажмите Save

3. **Альтернатива через GitHub Actions:**
   Создайте файл `.github/workflows/deploy-frontend.yml`:
   ```yaml
   name: Deploy Frontend to GitHub Pages
   
   on:
     push:
       branches: [ main ]
       paths:
         - 'frontend/**'
   
   jobs:
     deploy:
       runs-on: ubuntu-latest
       steps:
         - uses: actions/checkout@v3
         - name: Deploy to GitHub Pages
           uses: peaceiris/actions-gh-pages@v3
           with:
             github_token: ${{ secrets.GITHUB_TOKEN }}
             publish_dir: ./frontend
   ```

### Вариант 2: Деплой в отдельную ветку gh-pages

1. **Создайте ветку gh-pages:**
   ```bash
   git checkout -b gh-pages
   git rm -rf .
   git checkout main -- frontend/
   # Переместите содержимое frontend/ в корень
   git mv frontend/* .
   git mv frontend/.* . 2>/dev/null || true
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
2. Откройте `http://localhost/CRM/frontend/`
3. Фронтенд автоматически подключится к PHP API на `http://localhost/CRM/api/`

## Обновление деплоя

После изменений в `frontend/`:

1. Закоммитьте изменения
2. Запушьте в репозиторий
3. GitHub Pages автоматически обновится (может занять несколько минут)

Или если используете GitHub Actions, просто запушьте изменения - workflow автоматически задеплоит.

