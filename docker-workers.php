<?php
/**
 * TrendRadarConsole - Docker Workers Management Page
 * Manage Docker workers (create, edit, delete, set public/private)
 * Only available in advanced mode
 */

session_start();
require_once 'includes/helpers.php';
require_once 'includes/auth.php';

if (!file_exists('config/config.php')) {
    header('Location: install.php');
    exit;
}

// Require login
Auth::requireLogin();
$userId = Auth::getUserId();

// Validate user ID is numeric
if (!is_numeric($userId) || $userId <= 0) {
    redirect('login.php');
}
$userId = (int)$userId;

$auth = new Auth();

// Check if advanced mode feature is enabled
if (!$auth->isFeatureEnabled('advanced_mode')) {
    setFlash('error', __('feature_disabled_by_admin'));
    header('Location: index.php');
    exit;
}

// Check if advanced mode is enabled - redirect if not
$isAdvancedMode = $auth->isAdvancedModeEnabled($userId);
if (!$isAdvancedMode) {
    redirect('docker.php');
}

// Check if user is admin (admins can view all containers on any worker)
$isAdmin = $auth->isAdmin($userId);

// Get user's Docker workers (admins get all workers)
if ($isAdmin) {
    $userWorkers = $auth->getAllDockerWorkers();
} else {
    $userWorkers = $auth->getUserDockerWorkers($userId);
}

$flash = getFlash();
$currentPage = 'docker-workers';

$csrfToken = generateCsrfToken();
$currentLang = getCurrentLanguage();
?>
<!DOCTYPE html>
<html lang="<?php echo $currentLang; ?>">
<head>
    <meta name="csrf-token" content="<?php echo $csrfToken; ?>">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php _e('app_name'); ?> - <?php _e('docker_workers_management'); ?></title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <div class="app-container">
        <?php include 'templates/sidebar.php'; ?>
        
        <main class="main-content">
            <div class="page-header">
                <h2>🖥️ <?php _e('docker_workers_management'); ?></h2>
                <p><?php _e('docker_workers_desc'); ?></p>
            </div>
            
            <?php if ($flash): ?>
            <div class="alert alert-<?php echo $flash['type'] === 'error' ? 'danger' : $flash['type']; ?>">
                <?php echo sanitize($flash['message']); ?>
            </div>
            <?php endif; ?>
            
            <!-- Add New Worker Card -->
            <div class="card">
                <div class="card-header">
                    <h3>➕ <?php _e('add_docker_worker'); ?></h3>
                </div>
                <div class="card-body">
                    <form id="add-worker-form">
                        <div class="row mb-3">
                            <div class="col-6">
                                <label class="form-label"><?php _e('worker_name'); ?> <span class="text-danger">*</span></label>
                                <input type="text" id="new-worker-name" class="form-control" 
                                       placeholder="<?php _e('worker_name_placeholder'); ?>">
                            </div>
                            <div class="col-3">
                                <label class="form-label"><?php _e('is_public'); ?></label>
                                <select id="new-worker-public" class="form-control">
                                    <option value="0"><?php _e('private_worker'); ?></option>
                                    <option value="1"><?php _e('public_worker'); ?></option>
                                </select>
                            </div>
                        </div>
                        <div class="row mb-3">
                            <div class="col-6">
                                <label class="form-label"><?php _e('ssh_host'); ?> <span class="text-danger">*</span></label>
                                <input type="text" id="new-ssh-host" class="form-control" 
                                       placeholder="192.168.1.100">
                            </div>
                            <div class="col-3">
                                <label class="form-label"><?php _e('ssh_port'); ?></label>
                                <input type="number" id="new-ssh-port" class="form-control" 
                                       value="22" placeholder="22">
                            </div>
                            <div class="col-3">
                                <label class="form-label"><?php _e('ssh_username'); ?> <span class="text-danger">*</span></label>
                                <input type="text" id="new-ssh-username" class="form-control" 
                                       value="trendradarsrv" placeholder="trendradarsrv" readonly>
                            </div>
                        </div>
                        <div class="row mb-3">
                            <div class="col-6">
                                <label class="form-label"><?php _e('ssh_password'); ?> <span class="text-danger">*</span></label>
                                <input type="password" id="new-ssh-password" class="form-control" 
                                       placeholder="<?php _e('ssh_password'); ?>">
                            </div>
                            <div class="col-6">
                                <label class="form-label"><?php _e('workspace_path'); ?></label>
                                <input type="text" id="new-workspace-path" class="form-control" 
                                       value="/srv/trendradar" readonly>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-12">
                                <button type="button" class="btn btn-success" onclick="addWorker()">
                                    ➕ <?php _e('add_worker'); ?>
                                </button>
                            </div>
                        </div>
                    </form>
                    
                    <!-- Setup Script Prompt -->
                    <div class="setup-prompt mt-4" style="background-color: #2d3748; border-radius: 8px; padding: 15px;">
                        <p style="color: #e2e8f0; margin-bottom: 10px;"><strong>💡 <?php _e('setup_worker_hint'); ?></strong></p>
                        <p style="color: #94a3b8; font-size: 14px; margin-bottom: 10px;"><?php _e('setup_worker_instructions'); ?></p>
                        <pre style="background-color: #1a202c; color: #68d391; padding: 12px; border-radius: 6px; font-family: 'Consolas', 'Monaco', monospace; font-size: 13px; overflow-x: auto; margin: 0;"><code>curl -O https://trendingnews.cn/scripts/setup-docker-worker.sh
chmod +x setup-docker-worker.sh
sudo ./setup-docker-worker.sh</code></pre>
                    </div>
                </div>
            </div>
            
            <!-- Existing Workers List -->
            <div class="card">
                <div class="card-header">
                    <h3>📋 <?php echo $isAdmin ? _e('all_docker_workers') : _e('your_docker_workers'); ?></h3>
                </div>
                <div class="card-body">
                    <?php if (empty($userWorkers)): ?>
                    <div class="empty-state">
                        <p><?php _e('no_workers_yet'); ?></p>
                    </div>
                    <?php else: ?>
                    <div class="table-responsive">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th><?php _e('worker_name'); ?></th>
                                    <?php if ($isAdmin): ?>
                                    <th><?php _e('owner'); ?></th>
                                    <?php endif; ?>
                                    <th><?php _e('ssh_host'); ?></th>
                                    <th><?php _e('ssh_port'); ?></th>
                                    <th><?php _e('visibility'); ?></th>
                                    <th><?php _e('status'); ?></th>
                                    <th><?php _e('actions'); ?></th>
                                </tr>
                            </thead>
                            <tbody id="workers-list">
                                <?php foreach ($userWorkers as $worker): ?>
                                <tr data-worker-id="<?php echo (int)$worker['id']; ?>">
                                    <td>
                                        <strong><?php echo sanitize($worker['name']); ?></strong>
                                    </td>
                                    <?php if ($isAdmin): ?>
                                    <td>
                                        <?php echo sanitize($worker['owner_username'] ?? 'Unknown'); ?>
                                        <?php if ($worker['user_id'] == $userId): ?>
                                        <span class="badge badge-primary" style="font-size: 0.7rem; margin-left: 5px;"><?php _e('you'); ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <?php endif; ?>
                                    <td>
                                        <code><?php echo sanitize($worker['ssh_host']); ?></code>
                                    </td>
                                    <td>
                                        <?php echo (int)$worker['ssh_port']; ?>
                                    </td>
                                    <td>
                                        <?php if ($worker['is_public']): ?>
                                        <span class="badge badge-success"><?php _e('public'); ?></span>
                                        <?php else: ?>
                                        <span class="badge badge-secondary"><?php _e('private'); ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($worker['is_active']): ?>
                                        <span class="badge badge-success"><?php _e('active'); ?></span>
                                        <?php else: ?>
                                        <span class="badge badge-secondary"><?php _e('inactive'); ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="btn-group">
                                            <?php if ($worker['user_id'] == $userId): ?>
                                            <button type="button" class="btn btn-sm btn-outline" 
                                                    onclick="editWorker(<?php echo (int)$worker['id']; ?>)">
                                                ✏️ <?php _e('edit'); ?>
                                            </button>
                                            <?php endif; ?>
                                            <button type="button" class="btn btn-sm btn-outline" 
                                                    onclick="testWorkerConnection(<?php echo (int)$worker['id']; ?>)">
                                                🔗 <?php _e('test'); ?>
                                            </button>
                                            <button type="button" class="btn btn-sm btn-info" 
                                                    onclick="selectAndViewContainers(<?php echo (int)$worker['id']; ?>)">
                                                📦 <?php _e('view_all_containers'); ?>
                                            </button>
                                            <?php if ($worker['user_id'] == $userId): ?>
                                            <button type="button" class="btn btn-sm btn-danger" 
                                                    onclick="deleteWorker(<?php echo (int)$worker['id']; ?>)">
                                                🗑️ <?php _e('delete'); ?>
                                            </button>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Container Status Section (only for owner and admins) -->
            <?php if (!empty($userWorkers) || $isAdmin): ?>
            <div class="card" id="container-status-card">
                <div class="card-header">
                    <h3>📦 <?php _e('container_status'); ?></h3>
                    <button type="button" class="btn btn-sm btn-outline" onclick="loadContainers()">
                        🔄 <?php _e('refresh_containers'); ?>
                    </button>
                </div>
                <div class="card-body">
                    <p class="text-muted mb-3"><?php _e('container_status_desc'); ?></p>
                    
                    <div id="container-status-loading" style="display: none; text-align: center; padding: 20px;">
                        <div class="spinner-border" role="status">
                            <span class="sr-only">Loading...</span>
                        </div>
                    </div>
                    
                    <div id="container-status-content">
                        <div class="alert alert-info">
                            <?php _e('select_worker_first'); ?>
                        </div>
                    </div>
                </div>
            </div>
            <?php endif; ?>
            
            <!-- Back to Docker Deployment -->
            <div class="mt-3">
                <a href="docker.php" class="btn btn-outline">← <?php _e('back_to_docker'); ?></a>
            </div>
        </main>
    </div>
    
    <!-- Edit Worker Modal -->
    <div id="edit-modal" class="modal" style="display: none;">
        <div class="modal-backdrop" onclick="closeEditModal()"></div>
        <div class="modal-content">
            <div class="modal-header">
                <h3>✏️ <?php _e('edit_docker_worker'); ?></h3>
                <button type="button" class="btn-close" onclick="closeEditModal()">×</button>
            </div>
            <div class="modal-body">
                <form id="edit-worker-form">
                    <input type="hidden" id="edit-worker-id">
                    <div class="form-group mb-3">
                        <label class="form-label"><?php _e('worker_name'); ?> <span class="text-danger">*</span></label>
                        <input type="text" id="edit-worker-name" class="form-control">
                    </div>
                    <div class="row mb-3">
                        <div class="col-6">
                            <label class="form-label"><?php _e('ssh_host'); ?> <span class="text-danger">*</span></label>
                            <input type="text" id="edit-ssh-host" class="form-control">
                        </div>
                        <div class="col-6">
                            <label class="form-label"><?php _e('ssh_port'); ?></label>
                            <input type="number" id="edit-ssh-port" class="form-control">
                        </div>
                    </div>
                    <div class="form-group mb-3">
                        <label class="form-label"><?php _e('ssh_password'); ?></label>
                        <input type="password" id="edit-ssh-password" class="form-control" 
                               placeholder="<?php _e('leave_empty_to_keep'); ?>">
                    </div>
                    <div class="form-group mb-3">
                        <label class="form-label"><?php _e('is_public'); ?></label>
                        <select id="edit-worker-public" class="form-control">
                            <option value="0"><?php _e('private_worker'); ?></option>
                            <option value="1"><?php _e('public_worker'); ?></option>
                        </select>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" onclick="closeEditModal()"><?php _e('cancel'); ?></button>
                <button type="button" class="btn btn-primary" onclick="saveWorkerEdit()"><?php _e('save_changes'); ?></button>
            </div>
        </div>
    </div>
    
    <script>var i18n = <?php echo getJsTranslations(); ?>;</script>
    <script src="assets/js/app.js"></script>
    <script>
        // Constants
        const SCROLL_ANIMATION_DELAY = 300; // ms - Time to wait for smooth scroll animation
        const CONTAINER_STATE_CLASSES = {
            'running': 'badge-success',
            'exited': 'badge-secondary',
            'paused': 'badge-warning',
            'restarting': 'badge-info',
            'created': 'badge-info'
        };
        
        // Workers data for editing
        const workersData = <?php echo json_encode($userWorkers); ?>;
        
        // Add new worker
        async function addWorker() {
            const name = document.getElementById('new-worker-name').value.trim();
            const host = document.getElementById('new-ssh-host').value.trim();
            const port = document.getElementById('new-ssh-port').value || 22;
            const username = document.getElementById('new-ssh-username').value.trim();
            const password = document.getElementById('new-ssh-password').value;
            const workspacePath = document.getElementById('new-workspace-path').value.trim();
            const isPublic = document.getElementById('new-worker-public').value;
            
            if (!name) {
                showToast('<?php _e('worker_name_required'); ?>', 'error');
                return;
            }
            if (!host) {
                showToast('<?php _e('ssh_host_required'); ?>', 'error');
                return;
            }
            if (!password) {
                showToast('<?php _e('ssh_password_required'); ?>', 'error');
                return;
            }
            
            try {
                await apiRequest('api/docker-workers.php', 'POST', {
                    action: 'create',
                    name: name,
                    ssh_host: host,
                    ssh_port: port,
                    ssh_username: username,
                    ssh_password: password,
                    workspace_path: workspacePath,
                    is_public: isPublic
                });
                
                showToast('<?php _e('worker_created'); ?>', 'success');
                setTimeout(() => location.reload(), 1000);
            } catch (error) {
                showToast('<?php _e('worker_create_failed'); ?>: ' + error.message, 'error');
            }
        }
        
        // Edit worker - open modal
        function editWorker(workerId) {
            const worker = workersData.find(w => w.id == workerId);
            if (!worker) return;
            
            document.getElementById('edit-worker-id').value = workerId;
            document.getElementById('edit-worker-name').value = worker.name;
            document.getElementById('edit-ssh-host').value = worker.ssh_host;
            document.getElementById('edit-ssh-port').value = worker.ssh_port;
            document.getElementById('edit-ssh-password').value = '';
            document.getElementById('edit-worker-public').value = worker.is_public ? '1' : '0';
            
            document.getElementById('edit-modal').style.display = 'flex';
        }
        
        // Close edit modal
        function closeEditModal() {
            document.getElementById('edit-modal').style.display = 'none';
        }
        
        // Save worker edit
        async function saveWorkerEdit() {
            const workerId = document.getElementById('edit-worker-id').value;
            const name = document.getElementById('edit-worker-name').value.trim();
            const host = document.getElementById('edit-ssh-host').value.trim();
            const port = document.getElementById('edit-ssh-port').value || 22;
            const password = document.getElementById('edit-ssh-password').value;
            const isPublic = document.getElementById('edit-worker-public').value;
            
            if (!name) {
                showToast('<?php _e('worker_name_required'); ?>', 'error');
                return;
            }
            if (!host) {
                showToast('<?php _e('ssh_host_required'); ?>', 'error');
                return;
            }
            
            try {
                await apiRequest('api/docker-workers.php', 'POST', {
                    action: 'update',
                    worker_id: workerId,
                    name: name,
                    ssh_host: host,
                    ssh_port: port,
                    ssh_password: password || null,
                    is_public: isPublic
                });
                
                showToast('<?php _e('worker_updated'); ?>', 'success');
                closeEditModal();
                setTimeout(() => location.reload(), 1000);
            } catch (error) {
                showToast('<?php _e('worker_update_failed'); ?>: ' + error.message, 'error');
            }
        }
        
        // Delete worker
        async function deleteWorker(workerId) {
            if (!confirm('<?php _e('confirm_delete_worker'); ?>')) return;
            
            try {
                await apiRequest('api/docker-workers.php', 'POST', {
                    action: 'delete',
                    worker_id: workerId
                });
                
                showToast('<?php _e('worker_deleted'); ?>', 'success');
                setTimeout(() => location.reload(), 1000);
            } catch (error) {
                showToast('<?php _e('worker_delete_failed'); ?>: ' + error.message, 'error');
            }
        }
        
        // Test worker connection
        async function testWorkerConnection(workerId) {
            try {
                const result = await apiRequest('api/docker-workers.php', 'POST', {
                    action: 'test',
                    worker_id: workerId
                });
                
                const data = result.data;
                if (data.ssh_connected && data.docker_available) {
                    showToast('<?php _e('connection_successful'); ?> - Docker: ' + data.docker_version, 'success');
                } else if (data.ssh_connected) {
                    showToast('<?php _e('ssh_connected'); ?> - ' + data.message, 'warning');
                }
            } catch (error) {
                showToast('<?php _e('connection_failed'); ?>: ' + error.message, 'error');
            }
        }
        
        // Load containers for selected worker
        async function loadContainers() {
            const loadingDiv = document.getElementById('container-status-loading');
            const contentDiv = document.getElementById('container-status-content');
            
            loadingDiv.style.display = 'block';
            contentDiv.style.display = 'none';
            
            try {
                const result = await apiRequest('api/docker.php', 'POST', {
                    action: 'list_all_containers'
                });
                
                const data = result.data;
                const containers = data.containers || [];
                
                if (containers.length === 0) {
                    contentDiv.innerHTML = '<div class="alert alert-info"><?php _e('no_containers_found'); ?></div>';
                } else {
                    let html = '<div class="table-responsive"><table class="table">';
                    html += '<thead><tr>';
                    html += '<th><?php _e('container_name'); ?></th>';
                    html += '<th><?php _e('username'); ?></th>';
                    html += '<th><?php _e('container_image'); ?></th>';
                    html += '<th><?php _e('container_state'); ?></th>';
                    html += '<th><?php _e('running_status'); ?></th>';
                    html += '<th><?php _e('container_created'); ?></th>';
                    html += '</tr></thead><tbody>';
                    
                    containers.forEach(container => {
                        // Safely check if state exists in mapping, defaulting to warning
                        const state = container.state || '';
                        // Use hasOwnProperty for safer property checking against prototype pollution
                        const stateClass = Object.prototype.hasOwnProperty.call(CONTAINER_STATE_CLASSES, state) ? 
                                         CONTAINER_STATE_CLASSES[state] : 'badge-warning';
                        
                        html += '<tr>';
                        html += '<td><code>' + sanitizeHtml(container.name) + '</code></td>';
                        html += '<td>' + (container.username ? sanitizeHtml(container.username) : '-') + '</td>';
                        html += '<td>' + sanitizeHtml(container.image) + '</td>';
                        html += '<td><span class="badge ' + stateClass + '">' + sanitizeHtml(localizeContainerState(state)) + '</span></td>';
                        html += '<td>' + sanitizeHtml(localizeDockerStatus(container.status)) + '</td>';
                        html += '<td>' + sanitizeHtml(container.created) + '</td>';
                        html += '</tr>';
                    });
                    
                    html += '</tbody></table></div>';
                    contentDiv.innerHTML = html;
                }
                
                showToast('<?php _e('containers_loaded'); ?>', 'success');
            } catch (error) {
                contentDiv.innerHTML = '<div class="alert alert-danger"><?php _e('containers_load_failed'); ?>: ' + sanitizeHtml(error.message) + '</div>';
                showToast('<?php _e('containers_load_failed'); ?>: ' + error.message, 'error');
            } finally {
                loadingDiv.style.display = 'none';
                contentDiv.style.display = 'block';
            }
        }
        
        // Helper function to sanitize HTML
        function sanitizeHtml(str) {
            const div = document.createElement('div');
            div.textContent = str;
            return div.innerHTML;
        }
        
        // Select worker and view containers
        async function selectAndViewContainers(workerId) {
            try {
                // First, select the worker
                await apiRequest('api/docker-workers.php', 'POST', {
                    action: 'select',
                    worker_id: workerId
                });
                
                // Scroll to container status section
                document.getElementById('container-status-card').scrollIntoView({ 
                    behavior: 'smooth', 
                    block: 'start' 
                });
                
                // Wait for smooth scroll animation to complete before loading containers
                setTimeout(() => {
                    loadContainers();
                }, SCROLL_ANIMATION_DELAY);
            } catch (error) {
                showToast('<?php _e('worker_select_failed'); ?>: ' + error.message, 'error');
            }
        }
    </script>
    
    <style>
        .modal {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            z-index: 1000;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .modal-backdrop {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
        }
        .modal-content {
            position: relative;
            background: #fff;
            border-radius: 8px;
            max-width: 500px;
            width: 90%;
            max-height: 90vh;
            overflow-y: auto;
        }
        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 15px 20px;
            border-bottom: 1px solid #eee;
        }
        .modal-header h3 {
            margin: 0;
        }
        .modal-body {
            padding: 20px;
        }
        .modal-footer {
            display: flex;
            justify-content: flex-end;
            gap: 10px;
            padding: 15px 20px;
            border-top: 1px solid #eee;
        }
        .btn-close {
            background: none;
            border: none;
            font-size: 24px;
            cursor: pointer;
            color: #666;
        }
        .btn-close:hover {
            color: #333;
        }
        @media (prefers-color-scheme: dark) {
            .modal-content {
                background: #1e293b;
            }
            .modal-header, .modal-footer {
                border-color: #334155;
            }
            .btn-close {
                color: #94a3b8;
            }
            .btn-close:hover {
                color: #e2e8f0;
            }
        }
    </style>
</body>
</html>
