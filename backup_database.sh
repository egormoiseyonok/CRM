#!/bin/bash
# Скрипт для автоматического бэкапа PostgreSQL базы данных (для Linux)
# Использование: ./backup_database.sh

# Настройки
export PGPASSWORD="your_password_here"
BACKUP_DIR="backups"
DB_NAME="CRM"
DB_USER="postgres"
DB_HOST="localhost"
DATE_STR=$(date +%Y%m%d_%H%M%S)

# Создать папку для бэкапов если её нет
mkdir -p "$BACKUP_DIR"

# Создать бэкап
pg_dump -h "$DB_HOST" -U "$DB_USER" -d "$DB_NAME" -F c -f "$BACKUP_DIR/CRM_backup_$DATE_STR.backup"

if [ $? -eq 0 ]; then
    echo "Бэкап успешно создан: $BACKUP_DIR/CRM_backup_$DATE_STR.backup"
else
    echo "ОШИБКА при создании бэкапа!"
    exit 1
fi

# Удалить старые бэкапы (старше 30 дней)
find "$BACKUP_DIR" -name "*.backup" -mtime +30 -delete

echo "Готово!"




