<?php
require_once 'config.php';
checkAuth();

$db = getDB();
$pageTitle = 'Календарь встреч';
$user = getCurrentUser();
$isAdmin = isAdmin();
$isManager = isManager();
$isAdminOrManager = isAdminOrManager();
$userFilter = $isAdminOrManager ? '' : " AND user_id = {$user['id']}";

// CRUD операции
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    
    if ($action === 'create' || $action === 'update') {
        $id = $_POST['id'] ?? null;
        $title = trim($_POST['title']);
        $description = trim($_POST['description'] ?? '');
        $start_time = $_POST['start_time'];
        $end_time = $_POST['end_time'];
        $location = trim($_POST['location'] ?? '');
        $company_id = !empty($_POST['company_id']) ? $_POST['company_id'] : null;
        $contact_id = !empty($_POST['contact_id']) ? $_POST['contact_id'] : null;
        $deal_id = !empty($_POST['deal_id']) ? $_POST['deal_id'] : null;
        
        if ($title && $start_time && $end_time) {
            if ($id) {
                // Проверка прав на редактирование
                $stmt = $db->prepare("SELECT user_id FROM meetings WHERE id = ?");
                $stmt->execute([$id]);
                $meeting = $stmt->fetch();
                
                if (!$meeting) {
                    setFlash('Встреча не найдена', 'danger');
                } elseif (!canEdit($meeting['user_id'])) {
                    setFlash('У вас нет прав на редактирование этой встречи', 'danger');
                } else {
                    $stmt = $db->prepare("
                        UPDATE meetings 
                        SET title=?, description=?, start_time=?, end_time=?, location=?, 
                            company_id=?, contact_id=?, deal_id=?, updated_at=CURRENT_TIMESTAMP 
                        WHERE id=? AND (user_id=? OR ?)
                    ");
                    $stmt->execute([$title, $description, $start_time, $end_time, $location, 
                        $company_id, $contact_id, $deal_id, $id, $user['id'], $isAdminOrManager]);
                    setFlash('Встреча обновлена', 'success');
                }
            } else {
                // Создание - автоматически присваивается текущему пользователю
                $stmt = $db->prepare("
                    INSERT INTO meetings (title, description, start_time, end_time, location, 
                        company_id, contact_id, deal_id, user_id) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([$title, $description, $start_time, $end_time, $location, 
                    $company_id, $contact_id, $deal_id, $_SESSION['user_id']]);
                setFlash('Встреча создана', 'success');
            }
        }
    }
    
    if ($action === 'delete' && isset($_POST['id'])) {
        // Проверка прав на удаление
        if (!canDelete()) {
            setFlash('У вас нет прав на удаление встреч', 'danger');
            header('Location: calendar.php');
            exit;
        }
        
        $stmt = $db->prepare("DELETE FROM meetings WHERE id = ? AND (user_id = ? OR ?)");
        $stmt->execute([$_POST['id'], $user['id'], $isAdminOrManager]);
        setFlash('Встреча удалена', 'success');
    }
    
    header('Location: calendar.php');
    exit;
}

// Получить встречи
$date = $_GET['date'] ?? date('Y-m-d');
$startOfMonth = date('Y-m-01', strtotime($date));
$endOfMonth = date('Y-m-t', strtotime($date));

$meetings = $db->query("
    SELECT m.*, c.name as company_name, 
           co.first_name || ' ' || co.last_name as contact_name,
           u.name as user_name
    FROM meetings m
    LEFT JOIN companies c ON m.company_id = c.id
    LEFT JOIN contacts co ON m.contact_id = co.id
    LEFT JOIN users u ON m.user_id = u.id
    WHERE m.start_time >= '$startOfMonth 00:00:00' 
    AND m.start_time <= '$endOfMonth 23:59:59'
    $userFilter
    ORDER BY m.start_time ASC
")->fetchAll();

// Группировка встреч по дням
$meetingsByDay = [];
foreach ($meetings as $meeting) {
    $day = date('Y-m-d', strtotime($meeting['start_time']));
    if (!isset($meetingsByDay[$day])) {
        $meetingsByDay[$day] = [];
    }
    $meetingsByDay[$day][] = $meeting;
}

// Получить компании и контакты для формы
$companies = $db->query("SELECT id, name FROM companies ORDER BY name")->fetchAll();
$contacts = $db->query("SELECT id, first_name, last_name FROM contacts ORDER BY first_name, last_name")->fetchAll();

ob_start();
?>

<div class="page-header">
    <div>
        <h1>Календарь встреч</h1>
        <p class="page-description">Управление встречами и событиями</p>
    </div>
    <div class="page-actions">
        <button class="btn btn-primary" onclick="openMeetingModal()">Создать встречу</button>
    </div>
</div>

<div class="grid grid-2 mb-20">
    <div class="card">
        <div class="card-header">
            <h3 class="card-title"><?= date('F Y', strtotime($date)) ?></h3>
            <div style="display: flex; gap: 8px;">
                <a href="calendar.php?date=<?= date('Y-m-d', strtotime($date . ' -1 month')) ?>" class="btn btn-sm btn-secondary">←</a>
                <a href="calendar.php?date=<?= date('Y-m-d') ?>" class="btn btn-sm btn-secondary">Сегодня</a>
                <a href="calendar.php?date=<?= date('Y-m-d', strtotime($date . ' +1 month')) ?>" class="btn btn-sm btn-secondary">→</a>
            </div>
        </div>
        <div class="card-body">
            <div class="calendar-grid">
                <?php
                $firstDay = date('w', strtotime($startOfMonth));
                $firstDay = $firstDay == 0 ? 7 : $firstDay; // Понедельник = 1
                $daysInMonth = date('t', strtotime($startOfMonth));
                $currentDay = 1;
                
                $weekDays = ['Пн', 'Вт', 'Ср', 'Чт', 'Пт', 'Сб', 'Вс'];
                ?>
                <div class="calendar-weekdays">
                    <?php foreach ($weekDays as $day): ?>
                        <div class="calendar-weekday"><?= $day ?></div>
                    <?php endforeach; ?>
                </div>
                <div class="calendar-days">
                    <?php for ($i = 1; $i < $firstDay; $i++): ?>
                        <div class="calendar-day empty"></div>
                    <?php endfor; ?>
                    
                    <?php while ($currentDay <= $daysInMonth): ?>
                        <?php
                        $dayDate = date('Y-m-d', strtotime($startOfMonth . " +" . ($currentDay - 1) . " days"));
                        $isToday = $dayDate == date('Y-m-d');
                        $dayMeetings = $meetingsByDay[$dayDate] ?? [];
                        ?>
                        <div class="calendar-day <?= $isToday ? 'today' : '' ?>">
                            <div class="calendar-day-number"><?= $currentDay ?></div>
                            <?php if (!empty($dayMeetings)): ?>
                                <div class="calendar-day-meetings">
                                    <?php foreach (array_slice($dayMeetings, 0, 3) as $meeting): ?>
                                        <div class="calendar-meeting" 
                                             onclick="openMeetingModal(<?= $meeting['id'] ?>)"
                                             title="<?= e($meeting['title']) ?>">
                                            <?= e(mb_substr($meeting['title'], 0, 20)) ?>
                                        </div>
                                    <?php endforeach; ?>
                                    <?php if (count($dayMeetings) > 3): ?>
                                        <div class="calendar-meeting-more">+<?= count($dayMeetings) - 3 ?></div>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                        <?php $currentDay++; ?>
                    <?php endwhile; ?>
                </div>
            </div>
        </div>
    </div>
    
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">Ближайшие встречи</h3>
        </div>
        <div class="card-body p-0">
            <?php
            $upcomingMeetings = $db->query("
                SELECT m.*, c.name as company_name, 
                       co.first_name || ' ' || co.last_name as contact_name
                FROM meetings m
                LEFT JOIN companies c ON m.company_id = c.id
                LEFT JOIN contacts co ON m.contact_id = co.id
                WHERE m.start_time >= CURRENT_TIMESTAMP
                $userFilter
                ORDER BY m.start_time ASC
                LIMIT 10
            ")->fetchAll();
            ?>
            <?php if ($upcomingMeetings): ?>
                <div class="card-list">
                    <?php foreach ($upcomingMeetings as $meeting): ?>
                        <div class="list-card" onclick="openMeetingModal(<?= $meeting['id'] ?>)">
                            <div class="list-card-content">
                                <div class="list-card-title"><?= e($meeting['title']) ?></div>
                                <div class="list-card-subtitle">
                                    <?= formatDateTime($meeting['start_time']) ?>
                                    <?php if ($meeting['company_name']): ?>
                                        • <?= e($meeting['company_name']) ?>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="empty-state">
                    <div class="empty-state-icon"></div>
                    <p>Нет предстоящих встреч</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Модальное окно для создания/редактирования встречи -->
<div class="modal-overlay" id="meetingModal">
    <div class="modal">
        <div class="modal-header">
            <h3 class="modal-title" id="meetingModalTitle">Создать встречу</h3>
            <button class="modal-close" onclick="closeMeetingModal()">&times;</button>
        </div>
        <div class="modal-body">
            <form method="POST" id="meetingForm">
                <input type="hidden" name="action" id="meetingAction" value="create">
                <input type="hidden" name="id" id="meetingId">
                
                <div class="form-group">
                    <label class="form-label">Название *</label>
                    <input type="text" name="title" class="form-control" required>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Описание</label>
                    <textarea name="description" class="form-control" rows="3"></textarea>
                </div>
                
                <div class="grid grid-2">
                    <div class="form-group">
                        <label class="form-label">Начало *</label>
                        <input type="datetime-local" name="start_time" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Окончание *</label>
                        <input type="datetime-local" name="end_time" class="form-control" required>
                    </div>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Место</label>
                    <input type="text" name="location" class="form-control" placeholder="Адрес или место встречи">
                </div>
                
                <div class="form-group">
                    <label class="form-label">Компания</label>
                    <select name="company_id" class="form-control">
                        <option value="">Выберите компанию</option>
                        <?php foreach ($companies as $company): ?>
                            <option value="<?= $company['id'] ?>"><?= e($company['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Контакт</label>
                    <select name="contact_id" class="form-control">
                        <option value="">Выберите контакт</option>
                        <?php foreach ($contacts as $contact): ?>
                            <option value="<?= $contact['id'] ?>"><?= e($contact['first_name'] . ' ' . $contact['last_name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">Сохранить</button>
                    <button type="button" class="btn btn-secondary" onclick="closeMeetingModal()">Отмена</button>
                </div>
            </form>
        </div>
    </div>
</div>

<style>
.calendar-grid {
    display: flex;
    flex-direction: column;
}

.calendar-weekdays {
    display: grid;
    grid-template-columns: repeat(7, 1fr);
    gap: 4px;
    margin-bottom: 8px;
}

.calendar-weekday {
    text-align: center;
    font-weight: 600;
    font-size: 12px;
    color: var(--text-secondary);
    padding: 8px;
    text-transform: uppercase;
}

.calendar-days {
    display: grid;
    grid-template-columns: repeat(7, 1fr);
    gap: 4px;
}

.calendar-day {
    min-height: 80px;
    border: 1px solid var(--border);
    border-radius: 4px;
    padding: 8px;
    background: var(--bg);
    transition: all 0.2s;
}

.calendar-day:hover {
    background: var(--bg-secondary);
}

.calendar-day.today {
    border-color: var(--primary);
    background: var(--bg-secondary);
}

.calendar-day.empty {
    background: transparent;
    border: none;
}

.calendar-day-number {
    font-weight: 600;
    font-size: 14px;
    margin-bottom: 4px;
    color: var(--text);
}

.calendar-day-meetings {
    display: flex;
    flex-direction: column;
    gap: 2px;
}

.calendar-meeting {
    font-size: 11px;
    padding: 2px 4px;
    background: var(--primary);
    color: white;
    border-radius: 2px;
    cursor: pointer;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.calendar-meeting-more {
    font-size: 10px;
    color: var(--text-secondary);
    text-align: center;
    padding: 2px;
}
</style>

<script>
let currentMeeting = null;

function openMeetingModal(id = null) {
    const modal = document.getElementById('meetingModal');
    const form = document.getElementById('meetingForm');
    
    if (id) {
        // Загрузить данные встречи
        fetch(`api/meeting.php?id=${id}`)
            .then(r => r.json())
            .then(data => {
                document.getElementById('meetingModalTitle').textContent = 'Редактировать встречу';
                document.getElementById('meetingAction').value = 'update';
                document.getElementById('meetingId').value = data.id;
                form.title.value = data.title;
                form.description.value = data.description || '';
                form.start_time.value = data.start_time.replace(' ', 'T').substring(0, 16);
                form.end_time.value = data.end_time.replace(' ', 'T').substring(0, 16);
                form.location.value = data.location || '';
                form.company_id.value = data.company_id || '';
                form.contact_id.value = data.contact_id || '';
                modal.classList.add('show');
            })
            .catch(() => {
                alert('Ошибка загрузки данных');
            });
    } else {
        document.getElementById('meetingModalTitle').textContent = 'Создать встречу';
        document.getElementById('meetingAction').value = 'create';
        document.getElementById('meetingId').value = '';
        form.reset();
        const now = new Date();
        const start = new Date(now.getTime() + 60 * 60 * 1000); // +1 час
        form.start_time.value = start.toISOString().slice(0, 16);
        form.end_time.value = new Date(start.getTime() + 60 * 60 * 1000).toISOString().slice(0, 16);
        modal.classList.add('show');
    }
}

function closeMeetingModal() {
    document.getElementById('meetingModal').classList.remove('show');
}
</script>

<?php
$content = ob_get_clean();
require_once 'includes/layout.php';
?>

