# Инструкция по добавлению проекта в Git

## Проблема: репозиторий пустой

Если вы запушили проект, но на GitHub нет кода, значит файлы не были добавлены в git.

## Решение

### 1. Проверьте статус

```bash
git status
```

### 2. Добавьте все файлы (кроме игнорируемых)

```bash
# Добавить все файлы
git add .

# Или добавить конкретные папки
git add docs/
git add api/
git add assets/
git add *.php
git add *.md
```

### 3. Проверьте, что файлы добавлены

```bash
git status
```

Вы должны увидеть список файлов, готовых к коммиту.

### 4. Создайте коммит

```bash
git commit -m "Add CRM project with microservices architecture"
```

### 5. Запушьте в репозиторий

```bash
git push origin main
```

(или `git push origin master` если ваша ветка называется master)

## Важные файлы для добавления

Убедитесь, что добавлены:

- ✅ `docs/` - весь фронтенд
- ✅ `api/` - PHP API endpoints
- ✅ `assets/` - CSS файлы
- ✅ `*.php` - PHP страницы
- ✅ `*.md` - документация
- ✅ `.htaccess` - конфигурация Apache

## Файлы, которые НЕ нужно добавлять

Следующие файлы игнорируются (в `.gitignore`):

- ❌ `config.php` - содержит пароли (используйте `config.example.php`)
- ❌ `vendor/` - зависимости Composer
- ❌ `*.log` - логи
- ❌ `.vscode/`, `.idea/` - настройки IDE

## Проверка после push

После push:

1. Обновите страницу на GitHub
2. Убедитесь, что файлы появились
3. Настройте GitHub Pages (см. `GITHUB_PAGES_SETUP.md`)

## Если файлы все еще не видны

1. Проверьте, что вы в правильной ветке:
   ```bash
   git branch
   ```

2. Проверьте remote:
   ```bash
   git remote -v
   ```
   Должен быть указан ваш репозиторий

3. Попробуйте force push (осторожно!):
   ```bash
   git push -f origin main
   ```

