/**
 * Shared Workflow Tracking Functions
 * Used by both settings.php and index.php for Test Crawling feature
 */

// Constants
const DEFAULT_ESTIMATED_DURATION_MS = 60000; // Default 60 seconds
const POLL_INTERVAL_MS = 5000; // Poll every 5 seconds

/**
 * Set button loading state with visible status text
 */
function setButtonLoadingWithStatus(btn, isLoading) {
    if (!btn) return;
    
    if (isLoading) {
        // Store original button text as data attribute
        btn.dataset.originalText = btn.textContent.trim();
        if (!btn.querySelector('.btn-text')) {
            const span = document.createElement('span');
            span.className = 'btn-text';
            while (btn.firstChild) {
                span.appendChild(btn.firstChild);
            }
            btn.appendChild(span);
        }
        btn.classList.add('loading-with-status');
        btn.disabled = true;
    } else {
        btn.classList.remove('loading-with-status');
        btn.disabled = false;
        const span = btn.querySelector('.btn-text');
        if (span) {
            // Restore original button text from data attribute
            span.textContent = btn.dataset.originalText || '';
            while (span.firstChild) {
                btn.insertBefore(span.firstChild, span);
            }
            span.remove();
        }
        // Remove progress bar if exists
        removeProgressBar(btn);
    }
}

/**
 * Set button status text
 */
function setButtonStatusText(btn, text) {
    if (!btn) return;
    const textSpan = btn.querySelector('.btn-text');
    if (textSpan) {
        textSpan.textContent = text;
    }
}

/**
 * Create progress bar after button
 */
function createProgressBar(btn) {
    if (!btn) return;
    let container = btn.parentElement.querySelector('.workflow-progress-container');
    if (!container) {
        container = document.createElement('div');
        container.className = 'workflow-progress-container';
        // Calculate estimatedSeconds for initializing progress text with '0s / ~Xs' format
        const estimatedSeconds = Math.round((parseInt(btn.dataset.estimatedDuration) || DEFAULT_ESTIMATED_DURATION_MS) / 1000);
        container.innerHTML = `
            <div class="workflow-progress-bar">
                <div class="workflow-progress-fill"></div>
            </div>
            <span class="workflow-progress-text">0s / ~${estimatedSeconds}s</span>
        `;
        btn.parentElement.appendChild(container);
    }
    return container;
}

/**
 * Update progress bar
 */
function updateProgressBar(btn, percent, timeText) {
    const container = btn.parentElement.querySelector('.workflow-progress-container');
    if (container) {
        const fill = container.querySelector('.workflow-progress-fill');
        const text = container.querySelector('.workflow-progress-text');
        if (fill) fill.style.width = Math.min(percent, 100) + '%';
        if (text) text.textContent = timeText;
    }
}

/**
 * Remove progress bar
 */
function removeProgressBar(btn) {
    if (!btn) return;
    const container = btn.parentElement.querySelector('.workflow-progress-container');
    if (container) {
        container.remove();
    }
    // Clear interval stored on button
    if (btn.dataset.progressIntervalId) {
        clearInterval(parseInt(btn.dataset.progressIntervalId));
        delete btn.dataset.progressIntervalId;
    }
}

/**
 * Start progress animation
 */
function startProgressAnimation(btn) {
    createProgressBar(btn);
    // Clear any existing interval
    if (btn.dataset.progressIntervalId) {
        clearInterval(parseInt(btn.dataset.progressIntervalId));
    }
    
    const intervalId = setInterval(() => {
        const startTime = parseInt(btn.dataset.startTime) || Date.now();
        const estimatedDuration = parseInt(btn.dataset.estimatedDuration) || DEFAULT_ESTIMATED_DURATION_MS;
        const elapsed = Date.now() - startTime;
        const percent = (elapsed / estimatedDuration) * 100;
        const elapsedSec = Math.floor(elapsed / 1000);
        const estimatedSec = Math.floor(estimatedDuration / 1000);
        updateProgressBar(btn, percent, `${elapsedSec}s / ~${estimatedSec}s`);
    }, 500);
    btn.dataset.progressIntervalId = intervalId.toString();
}

/**
 * Track workflow status with polling
 * Uses btn.dataset.dispatchTime to only track runs that started after dispatch
 */
async function trackWorkflowStatus(btn, attempts = 0) {
    const maxAttempts = 60; // 60 attempts * POLL_INTERVAL_MS = 300 seconds (5 minutes)
    
    if (attempts >= maxAttempts) {
        setButtonStatusText(btn, __('workflow_status_unknown'));
        setTimeout(() => setButtonLoadingWithStatus(btn, false), 1500);
        return;
    }
    
    try {
        const result = await apiRequest('api/github.php', 'POST', {
            action: 'get_workflow_runs',
            workflow_id: 'crawler.yml'
        });
        
        const runs = result.data?.runs || [];
        const dispatchTime = parseInt(btn.dataset.dispatchTime) || 0;
        
        // Find a run that started after our dispatch time
        let targetRun = null;
        for (const run of runs) {
            const runCreatedAt = new Date(run.created_at).getTime();
            // Allow 10 second buffer before dispatch time to account for clock differences
            if (runCreatedAt >= dispatchTime - 10000) {
                targetRun = run;
                break;
            }
        }
        
        if (targetRun) {
            const status = targetRun.status;
            const conclusion = targetRun.conclusion;
            
            if (status === 'completed') {
                if (conclusion === 'success') {
                    setButtonStatusText(btn, __('workflow_status_success'));
                } else if (conclusion === 'failure') {
                    setButtonStatusText(btn, __('workflow_status_failure'));
                } else if (conclusion === 'cancelled') {
                    setButtonStatusText(btn, __('workflow_status_cancelled'));
                } else {
                    setButtonStatusText(btn, __('workflow_status_completed'));
                }
                setTimeout(() => setButtonLoadingWithStatus(btn, false), 2000);
                return;
            } else if (status === 'queued') {
                setButtonStatusText(btn, __('workflow_status_queued'));
            } else if (status === 'in_progress') {
                setButtonStatusText(btn, __('workflow_status_in_progress'));
                // Show progress bar when running
                if (!btn.parentElement.querySelector('.workflow-progress-container')) {
                    startProgressAnimation(btn);
                }
            } else {
                setButtonStatusText(btn, __('workflow_checking_status'));
            }
        } else {
            // New run hasn't appeared yet, keep waiting
            setButtonStatusText(btn, __('workflow_checking_status'));
        }
        
        // Continue polling
        setTimeout(() => trackWorkflowStatus(btn, attempts + 1), POLL_INTERVAL_MS);
    } catch (error) {
        console.error('Error tracking workflow:', error);
        setButtonStatusText(btn, __('workflow_status_unknown'));
        setTimeout(() => setButtonLoadingWithStatus(btn, false), 1500);
    }
}
