<?php
/**
 * Plugin state enumeration for Git Updater FSM.
 *
 * @package Git_Updater\Enums
 */

namespace Fragen\Git_Updater\Enums;

/**
 * Plugin state enumeration.
 * 
 * Defines all possible states a repository/plugin can be in within the Git Updater FSM.
 * Based on KISS SBI's PluginState enum but extended for Git Updater specific workflows.
 */
enum PluginState: string {
    // Core states from KISS SBI
    case UNKNOWN = 'unknown';
    case CHECKING = 'checking';
    case AVAILABLE = 'available';
    case NOT_PLUGIN = 'not_plugin';
    case ERROR = 'error';
    case INSTALLED_INACTIVE = 'installed_inactive';
    case INSTALLED_ACTIVE = 'installed_active';
    
    // Git Updater specific states
    case GIT_UPDATER_INSTALLING = 'git_updater_installing';
    case GIT_UPDATER_UPDATING = 'git_updater_updating';
    case GIT_UPDATER_MANAGED = 'git_updater_managed';
    case UPDATE_AVAILABLE = 'update_available';
    case BRANCH_SWITCHING = 'branch_switching';
    case INSTALLATION_FAILED = 'installation_failed';
    case UPDATE_FAILED = 'update_failed';
    
    /**
     * Get human-readable label for the state.
     *
     * @return string Human-readable state label.
     */
    public function getLabel(): string {
        return match($this) {
            self::UNKNOWN => __('Unknown', 'git-updater'),
            self::CHECKING => __('Checking...', 'git-updater'),
            self::AVAILABLE => __('Available for Installation', 'git-updater'),
            self::NOT_PLUGIN => __('Not a Plugin', 'git-updater'),
            self::ERROR => __('Error', 'git-updater'),
            self::INSTALLED_INACTIVE => __('Installed (Inactive)', 'git-updater'),
            self::INSTALLED_ACTIVE => __('Installed (Active)', 'git-updater'),
            self::GIT_UPDATER_INSTALLING => __('Installing via Git Updater...', 'git-updater'),
            self::GIT_UPDATER_UPDATING => __('Updating via Git Updater...', 'git-updater'),
            self::GIT_UPDATER_MANAGED => __('Managed by Git Updater', 'git-updater'),
            self::UPDATE_AVAILABLE => __('Update Available', 'git-updater'),
            self::BRANCH_SWITCHING => __('Switching Branch...', 'git-updater'),
            self::INSTALLATION_FAILED => __('Installation Failed', 'git-updater'),
            self::UPDATE_FAILED => __('Update Failed', 'git-updater'),
        };
    }
    
    /**
     * Get CSS class for the state.
     *
     * @return string CSS class for styling.
     */
    public function getCssClass(): string {
        return match($this) {
            self::UNKNOWN => 'state-unknown',
            self::CHECKING => 'state-checking',
            self::AVAILABLE => 'state-available',
            self::NOT_PLUGIN => 'state-not-plugin',
            self::ERROR => 'state-error',
            self::INSTALLED_INACTIVE => 'state-installed-inactive',
            self::INSTALLED_ACTIVE => 'state-installed-active',
            self::GIT_UPDATER_INSTALLING => 'state-git-installing',
            self::GIT_UPDATER_UPDATING => 'state-git-updating',
            self::GIT_UPDATER_MANAGED => 'state-git-managed',
            self::UPDATE_AVAILABLE => 'state-update-available',
            self::BRANCH_SWITCHING => 'state-branch-switching',
            self::INSTALLATION_FAILED => 'state-installation-failed',
            self::UPDATE_FAILED => 'state-update-failed',
        };
    }
    
    /**
     * Check if state represents an installed plugin.
     *
     * @return bool True if plugin is installed.
     */
    public function isInstalled(): bool {
        return in_array($this, [
            self::INSTALLED_INACTIVE,
            self::INSTALLED_ACTIVE,
            self::GIT_UPDATER_MANAGED,
            self::UPDATE_AVAILABLE,
        ], true);
    }
    
    /**
     * Check if state represents an active plugin.
     *
     * @return bool True if plugin is active.
     */
    public function isActive(): bool {
        return in_array($this, [
            self::INSTALLED_ACTIVE,
            self::GIT_UPDATER_MANAGED,
        ], true);
    }
    
    /**
     * Check if state represents an error condition.
     *
     * @return bool True if state is an error.
     */
    public function isError(): bool {
        return in_array($this, [
            self::ERROR,
            self::INSTALLATION_FAILED,
            self::UPDATE_FAILED,
        ], true);
    }
    
    /**
     * Check if state represents a processing/transitional state.
     *
     * @return bool True if state is transitional.
     */
    public function isProcessing(): bool {
        return in_array($this, [
            self::CHECKING,
            self::GIT_UPDATER_INSTALLING,
            self::GIT_UPDATER_UPDATING,
            self::BRANCH_SWITCHING,
        ], true);
    }
    
    /**
     * Check if state represents a Git Updater managed plugin.
     *
     * @return bool True if managed by Git Updater.
     */
    public function isGitUpdaterManaged(): bool {
        return in_array($this, [
            self::GIT_UPDATER_INSTALLING,
            self::GIT_UPDATER_UPDATING,
            self::GIT_UPDATER_MANAGED,
            self::UPDATE_AVAILABLE,
            self::BRANCH_SWITCHING,
        ], true);
    }
}
