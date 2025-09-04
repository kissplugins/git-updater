<?php
/**
 * Git Updater Integration Service - Bridge between Git Updater and FSM.
 *
 * @package Git_Updater\Services
 */

namespace Fragen\Git_Updater\Services;

use Fragen\Git_Updater\FSM\GitUpdaterStateManager;
use Fragen\Git_Updater\Enums\PluginState;
use Fragen\Git_Updater\Install;
use Fragen\Singleton;
use Exception;

/**
 * Git Updater Integration Service.
 * 
 * This service bridges Git Updater's existing functionality with the new FSM system.
 * It wraps Git Updater operations with state management and real-time updates.
 */
class GitUpdaterIntegrationService {
    /**
     * Git Updater Install instance.
     *
     * @var Install
     */
    private Install $git_updater_install;

    /**
     * State manager instance.
     *
     * @var GitUpdaterStateManager
     */
    private GitUpdaterStateManager $state_manager;

    /**
     * Constructor.
     *
     * @param GitUpdaterStateManager $state_manager State manager instance.
     */
    public function __construct(GitUpdaterStateManager $state_manager) {
        $this->state_manager = $state_manager;
        
        // Access Git Updater via Singleton pattern (existing Git Updater code)
        $this->git_updater_install = Singleton::get_instance('Fragen\Git_Updater\Install', $this);
        
        // Initialize FSM event listeners
        $this->init_fsm_listeners();
    }

    /**
     * Initialize FSM event listeners.
     */
    private function init_fsm_listeners(): void {
        // Listen for FSM state changes and update Git Updater accordingly
        add_action('git_updater_state_changed', [$this, 'handle_state_change'], 10, 3);
        add_action('git_updater_installation_progress', [$this, 'broadcast_progress'], 10, 2);
    }

    /**
     * Install plugin via Git Updater with FSM state management.
     *
     * @param string $repo_url Repository URL.
     * @param string $branch Branch name (default: 'main').
     * @param array $options Additional installation options.
     * @return bool Installation success.
     */
    public function install_via_git_updater(string $repo_url, string $branch = 'main', array $options = []): bool {
        try {
            // Parse repository URL
            $headers = $this->parse_repo_url($repo_url);
            
            // FSM: Transition to installing state with validation
            if (!$this->state_manager->transition($repo_url, PluginState::GIT_UPDATER_INSTALLING)) {
                throw new Exception('Cannot transition to installing state');
            }
            
            // Broadcast installation start
            $this->state_manager->broadcast('git_updater_installation_started', [
                'repository' => $repo_url,
                'branch' => $branch,
                'timestamp' => time()
            ]);
            
            // Prepare Git Updater configuration
            $config = [
                'git_updater_api' => $headers['api'], // github, gitlab, etc.
                'git_updater_repo' => $headers['owner_repo'],
                'git_updater_branch' => $branch,
                'git_updater_install_repo' => $headers['repo']
            ];
            
            // Merge additional options
            $config = array_merge($config, $options);
            
            // Execute installation via Git Updater (existing code)
            $result = $this->git_updater_install->install('plugin', $config);
            
            // FSM: Update state based on result with validation
            if ($result) {
                $this->state_manager->transition($repo_url, PluginState::INSTALLED_INACTIVE, [
                    'installation_method' => 'git_updater',
                    'branch' => $branch,
                    'installed_at' => time()
                ]);
                
                $this->state_manager->broadcast('git_updater_install_success', [
                    'repository' => $repo_url,
                    'method' => 'git_updater',
                    'branch' => $branch,
                    'timestamp' => time()
                ]);
            } else {
                $this->state_manager->transition($repo_url, PluginState::INSTALLATION_FAILED, [
                    'error' => 'Git Updater installation failed',
                    'attempted_at' => time()
                ]);
                
                $this->state_manager->broadcast('git_updater_install_error', [
                    'repository' => $repo_url,
                    'error' => 'Installation failed via Git Updater',
                    'timestamp' => time()
                ]);
            }
            
            return $result;
            
        } catch (Exception $e) {
            // FSM: Handle exceptions
            $this->state_manager->transition($repo_url, PluginState::ERROR, [
                'error' => $e->getMessage(),
                'error_at' => time()
            ]);
            
            $this->state_manager->broadcast('git_updater_install_error', [
                'repository' => $repo_url,
                'error' => 'Exception during installation: ' . $e->getMessage(),
                'timestamp' => time()
            ]);
            
            error_log('Git Updater FSM: Installation exception - ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Check for plugin updates with FSM integration.
     *
     * @param string $plugin_file Plugin file path.
     * @return bool True if update available.
     */
    public function check_for_updates(string $plugin_file): bool {
        try {
            // Use Git Updater's existing update checking
            $has_update = $this->git_updater_has_update($plugin_file);
            
            if ($has_update) {
                // FSM: Transition to update available state
                $this->state_manager->transition($plugin_file, PluginState::UPDATE_AVAILABLE, [
                    'update_detected_at' => time()
                ]);
                
                $this->state_manager->broadcast('git_updater_update_available', [
                    'plugin_file' => $plugin_file,
                    'timestamp' => time()
                ]);
            }
            
            return $has_update;
            
        } catch (Exception $e) {
            error_log('Git Updater FSM: Update check failed - ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Switch plugin branch with FSM integration.
     *
     * @param string $plugin_file Plugin file path.
     * @param string $new_branch Target branch.
     * @return bool Switch success.
     */
    public function switch_branch(string $plugin_file, string $new_branch): bool {
        try {
            // FSM: Transition to branch switching state
            if (!$this->state_manager->transition($plugin_file, PluginState::BRANCH_SWITCHING)) {
                throw new Exception('Cannot transition to branch switching state');
            }
            
            // Broadcast branch switch start
            $this->state_manager->broadcast('git_updater_branch_switch_started', [
                'plugin_file' => $plugin_file,
                'new_branch' => $new_branch,
                'timestamp' => time()
            ]);
            
            // Use Git Updater's existing branch switching
            $result = $this->git_updater_switch_branch($plugin_file, $new_branch);
            
            // FSM: Update state based on result
            if ($result) {
                $this->state_manager->transition($plugin_file, PluginState::INSTALLED_ACTIVE, [
                    'current_branch' => $new_branch,
                    'branch_switched_at' => time()
                ]);
                
                $this->state_manager->broadcast('git_updater_branch_switched', [
                    'plugin_file' => $plugin_file,
                    'new_branch' => $new_branch,
                    'timestamp' => time()
                ]);
            } else {
                $this->state_manager->transition($plugin_file, PluginState::ERROR, [
                    'error' => 'Branch switch failed',
                    'attempted_branch' => $new_branch,
                    'error_at' => time()
                ]);
                
                $this->state_manager->broadcast('git_updater_branch_switch_failed', [
                    'plugin_file' => $plugin_file,
                    'new_branch' => $new_branch,
                    'error' => 'Branch switch failed',
                    'timestamp' => time()
                ]);
            }
            
            return $result;
            
        } catch (Exception $e) {
            $this->state_manager->transition($plugin_file, PluginState::ERROR, [
                'error' => $e->getMessage(),
                'error_at' => time()
            ]);
            
            $this->state_manager->broadcast('git_updater_branch_switch_error', [
                'plugin_file' => $plugin_file,
                'error' => 'Exception during branch switch: ' . $e->getMessage(),
                'timestamp' => time()
            ]);
            
            error_log('Git Updater FSM: Branch switch exception - ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Handle FSM state changes and update Git Updater's internal state.
     *
     * @param string $repository Repository identifier.
     * @param string $old_state Previous state.
     * @param string $new_state New state.
     */
    public function handle_state_change(string $repository, string $old_state, string $new_state): void {
        // Bridge KISS SBI's FSM with Git Updater's existing state management
        
        switch ($new_state) {
            case PluginState::INSTALLED_ACTIVE->value:
                // Update Git Updater's options to reflect active state
                $this->update_git_updater_options($repository, 'active');
                break;
                
            case PluginState::INSTALLATION_FAILED->value:
                // Clean up any partial installation artifacts
                $this->cleanup_failed_installation($repository);
                break;
                
            case PluginState::UPDATE_AVAILABLE->value:
                // Trigger Git Updater's update notification system
                $this->trigger_update_notification($repository);
                break;
        }
    }

    /**
     * Broadcast installation/update progress via SSE.
     *
     * @param string $repository Repository identifier.
     * @param array $progress_data Progress information.
     */
    public function broadcast_progress(string $repository, array $progress_data): void {
        // Broadcast installation/update progress via SSE
        $this->state_manager->broadcast('git_updater_progress', [
            'repository' => $repository,
            'progress' => $progress_data,
            'timestamp' => time()
        ]);
    }

    /**
     * Parse repository URL to extract Git host and repository information.
     *
     * @param string $repo_url Repository URL.
     * @return array Parsed repository information.
     * @throws Exception If URL cannot be parsed.
     */
    private function parse_repo_url(string $repo_url): array {
        // Basic URL parsing for Git repositories
        $parsed = parse_url($repo_url);
        
        if (!$parsed || !isset($parsed['host'])) {
            throw new Exception('Invalid repository URL');
        }
        
        $host = strtolower($parsed['host']);
        $path = trim($parsed['path'] ?? '', '/');
        
        // Determine Git API type based on host
        $api = 'github'; // Default
        if (strpos($host, 'gitlab') !== false) {
            $api = 'gitlab';
        } elseif (strpos($host, 'bitbucket') !== false) {
            $api = 'bitbucket';
        }
        
        return [
            'api' => $api,
            'host' => $host,
            'owner_repo' => $path,
            'repo' => basename($path)
        ];
    }

    /**
     * Check if Git Updater has an update for the plugin.
     * This is a placeholder - implement based on Git Updater's actual API.
     *
     * @param string $plugin_file Plugin file path.
     * @return bool True if update available.
     */
    private function git_updater_has_update(string $plugin_file): bool {
        // TODO: Implement actual Git Updater update checking
        // This should interface with Git Updater's existing update system
        return false;
    }

    /**
     * Switch branch using Git Updater.
     * This is a placeholder - implement based on Git Updater's actual API.
     *
     * @param string $plugin_file Plugin file path.
     * @param string $new_branch Target branch.
     * @return bool Switch success.
     */
    private function git_updater_switch_branch(string $plugin_file, string $new_branch): bool {
        // TODO: Implement actual Git Updater branch switching
        // This should interface with Git Updater's existing branch system
        return false;
    }

    /**
     * Update Git Updater's internal options.
     *
     * @param string $repository Repository identifier.
     * @param string $status Status to set.
     */
    private function update_git_updater_options(string $repository, string $status): void {
        // TODO: Update Git Updater's internal state management
    }

    /**
     * Clean up failed installation artifacts.
     *
     * @param string $repository Repository identifier.
     */
    private function cleanup_failed_installation(string $repository): void {
        // TODO: Implement cleanup logic for failed installations
    }

    /**
     * Trigger Git Updater's update notification system.
     *
     * @param string $repository Repository identifier.
     */
    private function trigger_update_notification(string $repository): void {
        // TODO: Integrate with Git Updater's update notification system
    }
}
