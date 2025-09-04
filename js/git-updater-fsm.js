/**
 * Git Updater FSM - Frontend State Management
 * 
 * Adapted from KISS SBI's repositoryFSM.ts for Git Updater integration.
 * Provides real-time state management and UI updates.
 */

(function($) {
    'use strict';

    /**
     * Git Updater FSM Class
     */
    class GitUpdaterFSM {
        constructor() {
            this.states = new Map();
            this.listeners = new Set();
            this.eventSource = null;
            this.sseEnabled = false;
            this.errorContexts = new Map();
            this.maxRetries = 3;
            this.retryDelayMs = 5000;
            
            this.init();
        }

        /**
         * Initialize the FSM system
         */
        init() {
            console.log('Git Updater FSM: Initializing...');
            
            // Initialize SSE connection
            this.initSSE();
            
            // Bind UI events
            this.bindEvents();
            
            // Initialize existing repository states
            this.initRepositoryStates();
            
            console.log('Git Updater FSM: Initialized successfully');
        }

        /**
         * Initialize Server-Sent Events for real-time updates
         */
        initSSE() {
            if (typeof EventSource === 'undefined') {
                console.warn('Git Updater FSM: SSE not supported');
                return;
            }

            const sseUrl = gitUpdaterFSM.sseUrl + '&nonce=' + gitUpdaterFSM.nonce;
            
            try {
                this.eventSource = new EventSource(sseUrl);
                
                this.eventSource.onopen = () => {
                    console.log('Git Updater FSM: SSE connected');
                    this.sseEnabled = true;
                };
                
                this.eventSource.onerror = (error) => {
                    console.error('Git Updater FSM: SSE error', error);
                    this.sseEnabled = false;
                };
                
                // Listen for state change events
                this.eventSource.addEventListener('git_updater_state_changed', (event) => {
                    const data = JSON.parse(event.data);
                    this.handleStateChange(data);
                });
                
                // Listen for installation progress
                this.eventSource.addEventListener('git_updater_progress', (event) => {
                    const data = JSON.parse(event.data);
                    this.handleProgress(data);
                });
                
                // Listen for installation success
                this.eventSource.addEventListener('git_updater_install_success', (event) => {
                    const data = JSON.parse(event.data);
                    this.handleInstallSuccess(data);
                });
                
                // Listen for errors
                this.eventSource.addEventListener('git_updater_install_error', (event) => {
                    const data = JSON.parse(event.data);
                    this.handleError(data);
                });
                
            } catch (error) {
                console.error('Git Updater FSM: Failed to initialize SSE', error);
            }
        }

        /**
         * Bind UI events
         */
        bindEvents() {
            // Install button clicks
            $(document).on('click', '.git-updater-install-btn', (e) => {
                e.preventDefault();
                const $btn = $(e.target);
                const repoUrl = $btn.data('repo-url');
                const branch = $btn.data('branch') || 'main';
                this.installRepository(repoUrl, branch);
            });
            
            // Update button clicks
            $(document).on('click', '.git-updater-update-btn', (e) => {
                e.preventDefault();
                const $btn = $(e.target);
                const pluginFile = $btn.data('plugin-file');
                this.updatePlugin(pluginFile);
            });
            
            // Branch switch clicks
            $(document).on('click', '.git-updater-switch-branch-btn', (e) => {
                e.preventDefault();
                const $btn = $(e.target);
                const pluginFile = $btn.data('plugin-file');
                const newBranch = $btn.data('new-branch');
                this.switchBranch(pluginFile, newBranch);
            });
        }

        /**
         * Initialize repository states from existing UI
         */
        initRepositoryStates() {
            $('.git-updater-repository-row').each((index, row) => {
                const $row = $(row);
                const repository = $row.data('repository');
                const currentState = $row.data('state');
                
                if (repository && currentState) {
                    this.setState(repository, currentState);
                }
            });
        }

        /**
         * Get current state for a repository
         */
        getState(repository) {
            return this.states.get(repository);
        }

        /**
         * Set state for a repository
         */
        setState(repository, state) {
            const prevState = this.states.get(repository);
            this.states.set(repository, state);
            
            console.log(`Git Updater FSM: ${repository}: ${prevState || '∅'} -> ${state}`);
            
            // Notify listeners
            this.listeners.forEach(listener => listener(repository, state));
            
            // Update UI
            this.updateRepositoryUI(repository, state);
        }

        /**
         * Add state change listener
         */
        onChange(listener) {
            this.listeners.add(listener);
            return () => this.listeners.delete(listener);
        }

        /**
         * Install repository via Git Updater
         */
        installRepository(repoUrl, branch = 'main') {
            console.log(`Git Updater FSM: Installing ${repoUrl} (${branch})`);
            
            // Update UI immediately
            this.setState(repoUrl, 'git_updater_installing');
            
            // Send AJAX request
            $.ajax({
                url: gitUpdaterFSM.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'git_updater_fsm_install',
                    nonce: gitUpdaterFSM.nonce,
                    repo_url: repoUrl,
                    branch: branch
                },
                success: (response) => {
                    if (response.success) {
                        console.log('Git Updater FSM: Installation request sent successfully');
                    } else {
                        console.error('Git Updater FSM: Installation request failed', response.data);
                        this.setState(repoUrl, 'installation_failed');
                    }
                },
                error: (xhr, status, error) => {
                    console.error('Git Updater FSM: AJAX error during installation', error);
                    this.setState(repoUrl, 'error');
                }
            });
        }

        /**
         * Update plugin via Git Updater
         */
        updatePlugin(pluginFile) {
            console.log(`Git Updater FSM: Updating ${pluginFile}`);
            
            // Update UI immediately
            this.setState(pluginFile, 'git_updater_updating');
            
            // Send AJAX request
            $.ajax({
                url: gitUpdaterFSM.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'git_updater_fsm_update',
                    nonce: gitUpdaterFSM.nonce,
                    plugin_file: pluginFile
                },
                success: (response) => {
                    if (response.success) {
                        console.log('Git Updater FSM: Update check completed');
                    } else {
                        console.error('Git Updater FSM: Update check failed', response.data);
                        this.setState(pluginFile, 'update_failed');
                    }
                },
                error: (xhr, status, error) => {
                    console.error('Git Updater FSM: AJAX error during update', error);
                    this.setState(pluginFile, 'error');
                }
            });
        }

        /**
         * Switch branch for plugin
         */
        switchBranch(pluginFile, newBranch) {
            console.log(`Git Updater FSM: Switching ${pluginFile} to ${newBranch}`);
            
            // Update UI immediately
            this.setState(pluginFile, 'branch_switching');
            
            // Send AJAX request
            $.ajax({
                url: gitUpdaterFSM.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'git_updater_fsm_switch_branch',
                    nonce: gitUpdaterFSM.nonce,
                    plugin_file: pluginFile,
                    new_branch: newBranch
                },
                success: (response) => {
                    if (response.success) {
                        console.log('Git Updater FSM: Branch switch completed');
                    } else {
                        console.error('Git Updater FSM: Branch switch failed', response.data);
                        this.setState(pluginFile, 'error');
                    }
                },
                error: (xhr, status, error) => {
                    console.error('Git Updater FSM: AJAX error during branch switch', error);
                    this.setState(pluginFile, 'error');
                }
            });
        }

        /**
         * Handle SSE state change events
         */
        handleStateChange(data) {
            console.log('Git Updater FSM: SSE state change', data);
            this.setState(data.repository, data.new_state);
        }

        /**
         * Handle SSE progress events
         */
        handleProgress(data) {
            console.log('Git Updater FSM: SSE progress', data);
            this.updateProgressUI(data.repository, data.progress);
        }

        /**
         * Handle SSE installation success
         */
        handleInstallSuccess(data) {
            console.log('Git Updater FSM: SSE install success', data);
            this.setState(data.repository, 'installed_inactive');
            this.showSuccessMessage(`Successfully installed ${data.repository}`);
        }

        /**
         * Handle SSE errors
         */
        handleError(data) {
            console.error('Git Updater FSM: SSE error', data);
            this.setState(data.repository, 'error');
            this.showErrorMessage(data.error || 'An error occurred');
        }

        /**
         * Update repository UI based on state
         */
        updateRepositoryUI(repository, state) {
            const $row = $(`.git-updater-repository-row[data-repository="${repository}"]`);
            if ($row.length === 0) return;
            
            // Update state class
            $row.removeClass((index, className) => {
                return (className.match(/(^|\s)state-\S+/g) || []).join(' ');
            });
            $row.addClass(`state-${state.replace(/_/g, '-')}`);
            
            // Update state label
            const stateLabel = gitUpdaterFSM.states[state]?.label || state;
            $row.find('.git-updater-state').text(stateLabel);
            
            // Update buttons based on state
            this.updateButtonStates($row, state);
        }

        /**
         * Update button states based on repository state
         */
        updateButtonStates($row, state) {
            const $installBtn = $row.find('.git-updater-install-btn');
            const $updateBtn = $row.find('.git-updater-update-btn');
            const $switchBtn = $row.find('.git-updater-switch-branch-btn');
            
            // Reset all buttons
            $installBtn.prop('disabled', false).text('Install');
            $updateBtn.prop('disabled', false).text('Update');
            $switchBtn.prop('disabled', false);
            
            switch (state) {
                case 'git_updater_installing':
                    $installBtn.prop('disabled', true).text('Installing...');
                    break;
                case 'git_updater_updating':
                    $updateBtn.prop('disabled', true).text('Updating...');
                    break;
                case 'branch_switching':
                    $switchBtn.prop('disabled', true);
                    break;
                case 'installed_active':
                case 'installed_inactive':
                    $installBtn.hide();
                    $updateBtn.show();
                    $switchBtn.show();
                    break;
                case 'available':
                    $installBtn.show();
                    $updateBtn.hide();
                    $switchBtn.hide();
                    break;
                case 'error':
                case 'installation_failed':
                case 'update_failed':
                    $installBtn.text('Retry');
                    break;
            }
        }

        /**
         * Update progress UI
         */
        updateProgressUI(repository, progress) {
            const $row = $(`.git-updater-repository-row[data-repository="${repository}"]`);
            const $progress = $row.find('.git-updater-progress');
            
            if ($progress.length === 0) {
                $row.append('<div class="git-updater-progress"><div class="progress-bar"></div></div>');
            }
            
            const percentage = progress.percentage || 0;
            $row.find('.progress-bar').css('width', `${percentage}%`);
        }

        /**
         * Show success message
         */
        showSuccessMessage(message) {
            // TODO: Implement success message UI
            console.log('Success:', message);
        }

        /**
         * Show error message
         */
        showErrorMessage(message) {
            // TODO: Implement error message UI
            console.error('Error:', message);
        }

        /**
         * Cleanup resources
         */
        destroy() {
            if (this.eventSource) {
                this.eventSource.close();
                this.eventSource = null;
            }
            this.listeners.clear();
            this.states.clear();
        }
    }

    // Initialize FSM when document is ready
    $(document).ready(function() {
        if (typeof gitUpdaterFSM !== 'undefined') {
            window.GitUpdaterFSM = new GitUpdaterFSM();
        }
    });

})(jQuery);
