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

            // Activate button clicks
            $(document).on('click', '.git-updater-activate-btn', (e) => {
                e.preventDefault();
                const $btn = $(e.target);
                const pluginFile = $btn.data('plugin-file');
                this.activatePlugin(pluginFile);
            });

            // Deactivate button clicks
            $(document).on('click', '.git-updater-deactivate-btn', (e) => {
                e.preventDefault();
                const $btn = $(e.target);
                const pluginFile = $btn.data('plugin-file');
                this.deactivatePlugin(pluginFile);
            });

            // Branch switch clicks
            $(document).on('click', '.git-updater-switch-branch-btn', (e) => {
                e.preventDefault();
                const $btn = $(e.target);
                const pluginFile = $btn.data('plugin-file');
                const newBranch = $btn.data('new-branch');
                this.switchBranch(pluginFile, newBranch);
            });

            // Refresh button clicks
            $(document).on('click', '.git-updater-refresh-btn', (e) => {
                e.preventDefault();
                const $btn = $(e.target);
                const repoUrl = $btn.data('repo-url');
                this.refreshRepository(repoUrl);
            });

            // Enhanced admin page events
            this.bindEnhancedAdminEvents();
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
         * Bind enhanced admin page events
         */
        bindEnhancedAdminEvents() {
            // Fetch repositories button
            $(document).on('click', '#git-updater-fetch-repos', (e) => {
                e.preventDefault();
                const organization = $('#git-updater-organization').val().trim();
                if (organization) {
                    this.fetchRepositories(organization);
                }
            });

            // Manual install button
            $(document).on('click', '#git-updater-manual-install', (e) => {
                e.preventDefault();
                const repoUrl = $('#git-updater-manual-repo').val().trim();
                const branch = $('#git-updater-manual-branch').val().trim() || 'main';
                if (repoUrl) {
                    this.installRepository(repoUrl, branch);
                }
            });

            // Batch operation buttons
            $(document).on('click', '#git-updater-batch-install', () => this.batchInstall());
            $(document).on('click', '#git-updater-batch-update', () => this.batchUpdate());
            $(document).on('click', '#git-updater-batch-activate', () => this.batchActivate());
            $(document).on('click', '#git-updater-batch-deactivate', () => this.batchDeactivate());
            $(document).on('click', '#git-updater-batch-refresh', () => this.batchRefresh());

            // Filter controls
            $(document).on('click', '#git-updater-apply-filters', () => this.applyFilters());
            $(document).on('click', '#git-updater-refresh-all', () => this.refreshAll());
            $(document).on('click', '#git-updater-check-updates', () => this.checkAllUpdates());

            // Checkbox selection handling
            $(document).on('change', 'input[name="repositories[]"]', () => this.updateBatchButtons());
            $(document).on('change', '#cb-select-all', () => this.toggleSelectAll());
        }

        /**
         * Fetch repositories from organization
         */
        fetchRepositories(organization) {
            console.log(`Git Updater FSM: Fetching repositories for ${organization}`);

            this.showLoading('Fetching repositories...');

            $.ajax({
                url: gitUpdaterFSM.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'git_updater_fetch_repositories',
                    nonce: gitUpdaterFSM.nonce,
                    organization: organization
                },
                success: (response) => {
                    this.hideLoading();
                    if (response.success) {
                        this.displayRepositoryList(response.data.repositories);
                        this.showSuccessMessage(`Found ${response.data.count} repositories`);
                    } else {
                        this.showErrorMessage(response.data || 'Failed to fetch repositories');
                    }
                },
                error: (xhr, status, error) => {
                    this.hideLoading();
                    this.showErrorMessage('Network error while fetching repositories');
                }
            });
        }

        /**
         * Activate plugin
         */
        activatePlugin(pluginFile) {
            console.log(`Git Updater FSM: Activating ${pluginFile}`);

            $.ajax({
                url: gitUpdaterFSM.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'git_updater_activate_plugin',
                    nonce: gitUpdaterFSM.nonce,
                    plugin_file: pluginFile
                },
                success: (response) => {
                    if (response.success) {
                        this.showSuccessMessage('Plugin activated successfully');
                    } else {
                        this.showErrorMessage(response.data || 'Activation failed');
                    }
                },
                error: (xhr, status, error) => {
                    this.showErrorMessage('Network error during activation');
                }
            });
        }

        /**
         * Deactivate plugin
         */
        deactivatePlugin(pluginFile) {
            console.log(`Git Updater FSM: Deactivating ${pluginFile}`);

            $.ajax({
                url: gitUpdaterFSM.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'git_updater_deactivate_plugin',
                    nonce: gitUpdaterFSM.nonce,
                    plugin_file: pluginFile
                },
                success: (response) => {
                    if (response.success) {
                        this.showSuccessMessage('Plugin deactivated successfully');
                    } else {
                        this.showErrorMessage(response.data || 'Deactivation failed');
                    }
                },
                error: (xhr, status, error) => {
                    this.showErrorMessage('Network error during deactivation');
                }
            });
        }

        /**
         * Refresh repository state
         */
        refreshRepository(repoUrl) {
            console.log(`Git Updater FSM: Refreshing ${repoUrl}`);

            $.ajax({
                url: gitUpdaterFSM.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'git_updater_refresh_repository',
                    nonce: gitUpdaterFSM.nonce,
                    repository: repoUrl
                },
                success: (response) => {
                    if (response.success) {
                        this.showSuccessMessage('Repository refreshed');
                    } else {
                        this.showErrorMessage(response.data || 'Refresh failed');
                    }
                },
                error: (xhr, status, error) => {
                    this.showErrorMessage('Network error during refresh');
                }
            });
        }

        /**
         * Display repository list
         */
        displayRepositoryList(repositories) {
            const $container = $('#git-updater-repository-list-container');
            const $list = $('#git-updater-repository-list');

            if (repositories.length === 0) {
                $list.html('<p>No repositories found.</p>');
                $container.show();
                return;
            }

            // Render repository table
            $.ajax({
                url: gitUpdaterFSM.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'git_updater_render_table',
                    nonce: gitUpdaterFSM.nonce,
                    repositories: repositories
                },
                success: (response) => {
                    if (response.success) {
                        $list.html(response.data.html);
                        $container.show();
                    } else {
                        this.showErrorMessage('Failed to render repository table');
                    }
                },
                error: (xhr, status, error) => {
                    this.showErrorMessage('Network error while rendering table');
                }
            });
        }

        /**
         * Show loading overlay
         */
        showLoading(message = 'Loading...') {
            const $overlay = $('#git-updater-loading-overlay');
            $overlay.find('p').text(message);
            $overlay.show();
        }

        /**
         * Hide loading overlay
         */
        hideLoading() {
            $('#git-updater-loading-overlay').hide();
        }

        /**
         * Update batch operation buttons based on selection
         */
        updateBatchButtons() {
            const selectedCount = $('input[name="repositories[]"]:checked').length;
            const $batchButtons = $('.git-updater-batch-buttons button');

            if (selectedCount > 0) {
                $batchButtons.prop('disabled', false);
            } else {
                $batchButtons.prop('disabled', true);
            }
        }

        /**
         * Toggle select all checkboxes
         */
        toggleSelectAll() {
            const isChecked = $('#cb-select-all').prop('checked');
            $('input[name="repositories[]"]').prop('checked', isChecked);
            this.updateBatchButtons();
        }

        /**
         * Batch install selected repositories
         */
        batchInstall() {
            const selected = this.getSelectedRepositories();
            if (selected.length === 0) return;

            console.log('Git Updater FSM: Batch installing', selected);
            this.performBatchOperation('git_updater_batch_install', selected, 'Installing selected repositories...');
        }

        /**
         * Batch update selected plugins
         */
        batchUpdate() {
            const selected = this.getSelectedRepositories();
            if (selected.length === 0) return;

            console.log('Git Updater FSM: Batch updating', selected);
            this.performBatchOperation('git_updater_batch_update', selected, 'Updating selected plugins...');
        }

        /**
         * Batch activate selected plugins
         */
        batchActivate() {
            const selected = this.getSelectedRepositories();
            if (selected.length === 0) return;

            console.log('Git Updater FSM: Batch activating', selected);
            this.performBatchOperation('git_updater_batch_activate', selected, 'Activating selected plugins...');
        }

        /**
         * Batch deactivate selected plugins
         */
        batchDeactivate() {
            const selected = this.getSelectedRepositories();
            if (selected.length === 0) return;

            console.log('Git Updater FSM: Batch deactivating', selected);
            this.performBatchOperation('git_updater_batch_deactivate', selected, 'Deactivating selected plugins...');
        }

        /**
         * Batch refresh selected repositories
         */
        batchRefresh() {
            const selected = this.getSelectedRepositories();
            if (selected.length === 0) return;

            console.log('Git Updater FSM: Batch refreshing', selected);
            this.performBatchOperation('git_updater_batch_refresh', selected, 'Refreshing selected repositories...');
        }

        /**
         * Get selected repository URLs
         */
        getSelectedRepositories() {
            const selected = [];
            $('input[name="repositories[]"]:checked').each(function() {
                selected.push($(this).val());
            });
            return selected;
        }

        /**
         * Perform batch operation
         */
        performBatchOperation(action, repositories, loadingMessage) {
            this.showLoading(loadingMessage);

            $.ajax({
                url: gitUpdaterFSM.ajaxUrl,
                type: 'POST',
                data: {
                    action: action,
                    nonce: gitUpdaterFSM.nonce,
                    repositories: repositories
                },
                success: (response) => {
                    this.hideLoading();
                    if (response.success) {
                        this.showSuccessMessage(response.data.message || 'Batch operation completed');
                    } else {
                        this.showErrorMessage(response.data || 'Batch operation failed');
                    }
                },
                error: (xhr, status, error) => {
                    this.hideLoading();
                    this.showErrorMessage('Network error during batch operation');
                }
            });
        }

        /**
         * Show success message
         */
        showSuccessMessage(message) {
            // Create or update success notice
            const $notice = $('<div class="notice notice-success is-dismissible"><p>' + message + '</p></div>');
            $('.wrap h1').after($notice);

            // Auto-dismiss after 5 seconds
            setTimeout(() => {
                $notice.fadeOut(() => $notice.remove());
            }, 5000);
        }

        /**
         * Show error message
         */
        showErrorMessage(message) {
            // Create or update error notice
            const $notice = $('<div class="notice notice-error is-dismissible"><p>' + message + '</p></div>');
            $('.wrap h1').after($notice);

            console.error('Git Updater FSM Error:', message);
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
