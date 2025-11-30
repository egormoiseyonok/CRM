@echo off
REM Скрипт для автоматического бэкапа PostgreSQL базы данных
REM Использование: backup_database.bat

set PGPASSWORD=4568
set BACKUP_DIR=backups
set DB_NAME=CRM
set DB_USER=postgres
set DB_HOST=localhost
set DATE_STR=%date:~-4,4%%date:~-7,2%%date:~-10,2%_%time:~0,2%%time:~3,2%%time:~6,2%
set DATE_STR=%DATE_STR: =0%

REM Создать папку для бэкапов если её нет
if not exist "%BACKUP_DIR%" mkdir "%BACKUP_DIR%"

REM Путь к pg_dump в XAMPP
set PG_DUMP=C:\xampp\pgsql\bin\pg_dump.exe

REM Создать бэкап
"%PG_DUMP%" -h %DB_HOST% -U %DB_USER% -d %DB_NAME% -F c -f "%BACKUP_DIR%\CRM_backup_%DATE_STR%.backup"

if %ERRORLEVEL% EQU 0 (
    echo Бэкап успешно создан: %BACKUP_DIR%\CRM_backup_%DATE_STR%.backup
) else (
    echo ОШИБКА при создании бэкапа!
    exit /b 1
)

REM Удалить старые бэкапы (старше 30 дней)
forfiles /p "%BACKUP_DIR%" /m *.backup /d -30 /c "cmd /c del @path" 2>nul

echo Готово!



