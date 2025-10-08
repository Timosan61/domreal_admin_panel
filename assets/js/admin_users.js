// Глобальные переменные
let csrfToken = '';
let departments = [];
let currentEditUserId = null;

// Загрузка данных при загрузке страницы
document.addEventListener('DOMContentLoaded', function() {
    loadUsers();
    loadDepartments();
});

// Загрузка списка пользователей
async function loadUsers() {
    try {
        const response = await fetch('admin/api/users.php');
        const data = await response.json();

        if (data.success) {
            csrfToken = data.csrf_token;
            renderUsers(data.data);
        } else {
            showAlert('Ошибка загрузки пользователей', 'error');
        }
    } catch (error) {
        console.error('Error loading users:', error);
        showAlert('Ошибка загрузки пользователей', 'error');
    }
}

// Отображение пользователей в таблице
function renderUsers(users) {
    const tbody = document.getElementById('users-tbody');

    if (users.length === 0) {
        tbody.innerHTML = '<tr><td colspan="7" style="text-align: center; padding: 40px;">Пользователи не найдены</td></tr>';
        return;
    }

    tbody.innerHTML = users.map(user => `
        <tr>
            <td><strong>${escapeHtml(user.username)}</strong></td>
            <td>${escapeHtml(user.full_name || '—')}</td>
            <td>
                <span class="user-role-badge ${user.role === 'admin' ? 'user-role-admin' : 'user-role-user'}">
                    ${user.role === 'admin' ? 'Администратор' : 'Пользователь'}
                </span>
            </td>
            <td>
                <div class="departments-list">${user.departments || '—'}</div>
            </td>
            <td>
                <span class="user-status-badge ${user.is_active == 1 ? 'user-status-active' : 'user-status-inactive'}"></span>
                ${user.is_active == 1 ? 'Активен' : 'Неактивен'}
            </td>
            <td>${user.last_login ? formatDateTime(user.last_login) : 'Не входил'}</td>
            <td>
                <div class="user-actions">
                    <button class="btn-icon edit" onclick="editUser(${user.id})" title="Редактировать">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path>
                            <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path>
                        </svg>
                    </button>
                    <button class="btn-icon delete" onclick="deleteUser(${user.id}, '${escapeHtml(user.username)}')" title="Удалить">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <polyline points="3 6 5 6 21 6"></polyline>
                            <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path>
                        </svg>
                    </button>
                </div>
            </td>
        </tr>
    `).join('');
}

// Загрузка списка отделов
async function loadDepartments() {
    try {
        const response = await fetch('admin/api/departments.php');
        const data = await response.json();

        if (data.success) {
            departments = data.data;
        }
    } catch (error) {
        console.error('Error loading departments:', error);
    }
}

// Открыть модальное окно создания пользователя
function openCreateModal() {
    currentEditUserId = null;
    document.getElementById('modal-title').textContent = 'Создать пользователя';
    document.getElementById('user-form').reset();
    document.getElementById('user-id').value = '';
    document.getElementById('password').required = true;
    document.getElementById('password-optional').style.display = 'none';
    renderDepartmentCheckboxes([]);
    document.getElementById('user-modal').classList.add('active');
}

// Редактировать пользователя
async function editUser(userId) {
    currentEditUserId = userId;

    try {
        const response = await fetch(`admin/api/users.php?id=${userId}`);
        const data = await response.json();

        if (data.success) {
            const user = data.data;

            document.getElementById('modal-title').textContent = 'Редактировать пользователя';
            document.getElementById('user-id').value = user.id;
            document.getElementById('username').value = user.username;
            document.getElementById('password').value = '';
            document.getElementById('password').required = false;
            document.getElementById('password-optional').style.display = 'inline';
            document.getElementById('full_name').value = user.full_name || '';
            document.getElementById('role').value = user.role;
            document.getElementById('is_active').value = user.is_active;

            renderDepartmentCheckboxes(user.departments || []);
            document.getElementById('user-modal').classList.add('active');
        }
    } catch (error) {
        console.error('Error loading user:', error);
        showAlert('Ошибка загрузки данных пользователя', 'error');
    }
}

// Отображение чекбоксов отделов
function renderDepartmentCheckboxes(selectedDepartments) {
    const container = document.getElementById('departments-list');

    if (departments.length === 0) {
        container.innerHTML = '<div style="text-align: center; padding: 20px;">Отделы не найдены</div>';
        return;
    }

    container.innerHTML = departments.map(dept => {
        // Поддержка как объектов {value, label}, так и строк для обратной совместимости
        const deptValue = typeof dept === 'object' ? dept.value : dept;
        const deptLabel = typeof dept === 'object' ? dept.label : dept;

        return `
            <div class="checkbox-item">
                <input
                    type="checkbox"
                    id="dept-${escapeHtml(deptValue)}"
                    name="departments[]"
                    value="${escapeHtml(deptValue)}"
                    ${selectedDepartments.includes(deptValue) ? 'checked' : ''}
                >
                <label for="dept-${escapeHtml(deptValue)}">${escapeHtml(deptLabel)}</label>
            </div>
        `;
    }).join('');
}

// Сохранить пользователя (создать или обновить)
async function saveUser(event) {
    event.preventDefault();

    const formData = new FormData(event.target);
    const userId = formData.get('user_id');

    // Собираем выбранные отделы
    const selectedDepartments = [];
    document.querySelectorAll('input[name="departments[]"]:checked').forEach(checkbox => {
        selectedDepartments.push(checkbox.value);
    });

    if (selectedDepartments.length === 0 && formData.get('role') === 'user') {
        showAlert('Выберите хотя бы один отдел для пользователя', 'error');
        return;
    }

    const userData = {
        username: formData.get('username'),
        password: formData.get('password'),
        full_name: formData.get('full_name'),
        role: formData.get('role'),
        is_active: formData.get('is_active'),
        departments: selectedDepartments,
        csrf_token: csrfToken
    };

    try {
        let url = 'admin/api/users.php';
        let method = 'POST';

        if (userId) {
            url += `?id=${userId}`;
            method = 'PUT';
            // Если пароль пустой при редактировании, не отправляем его
            if (!userData.password) {
                delete userData.password;
            }
        }

        const response = await fetch(url, {
            method: method,
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-Token': csrfToken
            },
            body: JSON.stringify(userData)
        });

        let data;
        try {
            data = await response.json();
        } catch (jsonError) {
            console.error('JSON parse error:', jsonError);
            showAlert('Ошибка обработки ответа сервера', 'error');
            return;
        }

        if (data.success) {
            showAlert(userId ? 'Пользователь успешно обновлен' : 'Пользователь успешно создан', 'success');
            closeModal();
            loadUsers();
        } else {
            // Детальное отображение ошибки
            let errorMessage = data.error || 'Ошибка сохранения пользователя';

            // Если есть SQL ошибка, добавляем ее в консоль для отладки
            if (data.sql_error) {
                console.error('SQL Error:', data.sql_error);
            }

            showAlert(errorMessage, 'error');
        }
    } catch (error) {
        console.error('Error saving user:', error);
        showAlert('Ошибка сохранения пользователя: ' + error.message, 'error');
    }
}

// Удалить пользователя
async function deleteUser(userId, username) {
    if (!confirm(`Вы уверены, что хотите удалить пользователя "${username}"?`)) {
        return;
    }

    try {
        const response = await fetch(`admin/api/users.php?id=${userId}`, {
            method: 'DELETE',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-Token': csrfToken
            },
            body: JSON.stringify({ csrf_token: csrfToken })
        });

        const data = await response.json();

        if (data.success) {
            showAlert('Пользователь успешно удален', 'success');
            loadUsers();
        } else {
            showAlert(data.error || 'Ошибка удаления пользователя', 'error');
        }
    } catch (error) {
        console.error('Error deleting user:', error);
        showAlert('Ошибка удаления пользователя', 'error');
    }
}

// Закрыть модальное окно
function closeModal() {
    document.getElementById('user-modal').classList.remove('active');
    document.getElementById('user-form').reset();
    currentEditUserId = null;
}

// Показать уведомление
function showAlert(message, type = 'success') {
    const alertContainer = document.getElementById('alert-container');
    const alertClass = type === 'success' ? 'alert-success' : 'alert-error';

    alertContainer.innerHTML = `
        <div class="alert ${alertClass}">
            ${escapeHtml(message)}
        </div>
    `;

    setTimeout(() => {
        alertContainer.innerHTML = '';
    }, 5000);
}

// Форматирование даты и времени
function formatDateTime(dateString) {
    const date = new Date(dateString);
    const day = String(date.getDate()).padStart(2, '0');
    const month = String(date.getMonth() + 1).padStart(2, '0');
    const year = date.getFullYear();
    const hours = String(date.getHours()).padStart(2, '0');
    const minutes = String(date.getMinutes()).padStart(2, '0');

    return `${day}.${month}.${year} ${hours}:${minutes}`;
}

// Экранирование HTML
function escapeHtml(text) {
    if (!text) return '';
    const map = {
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',
        "'": '&#039;'
    };
    return text.toString().replace(/[&<>"']/g, m => map[m]);
}

// Закрытие модального окна при клике вне его
document.addEventListener('click', function(event) {
    const modal = document.getElementById('user-modal');
    if (event.target === modal) {
        closeModal();
    }
});

// Закрытие модального окна по Escape
document.addEventListener('keydown', function(event) {
    if (event.key === 'Escape') {
        closeModal();
    }
});
