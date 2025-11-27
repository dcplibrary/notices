<?php

namespace Dcplibrary\Notices\Services;

use Dcplibrary\Notices\Contracts\NotificationPlugin;
use Dcplibrary\Notices\Models\NotificationLog;
use InvalidArgumentException;

/**
 * Registry for notification channel plugins.
 *
 * Manages all registered plugins and routes verification requests to the appropriate plugin.
 */
class PluginRegistry
{
    /**
     * @var array<string, NotificationPlugin>
     */
    protected array $plugins = [];

    /**
     * @var array<int, NotificationPlugin> Mapping of delivery_option_id to plugin
     */
    protected array $deliveryMap = [];

    /**
     * Register a plugin.
     */
    public function register(NotificationPlugin $plugin): void
    {
        $name = $plugin->getName();

        if (isset($this->plugins[$name])) {
            throw new InvalidArgumentException("Plugin '{$name}' is already registered.");
        }

        $this->plugins[$name] = $plugin;

        // Map delivery option IDs to this plugin
        foreach ($plugin->getDeliveryOptionIds() as $deliveryOptionId) {
            $this->deliveryMap[$deliveryOptionId] = $plugin;
        }
    }

    /**
     * Get a plugin by name.
     */
    public function get(string $name): ?NotificationPlugin
    {
        return $this->plugins[$name] ?? null;
    }

    /**
     * Get all registered plugins.
     *
     * @return array<NotificationPlugin>
     */
    public function all(): array
    {
        return array_values($this->plugins);
    }

    /**
     * Get enabled plugins only.
     *
     * @return array<NotificationPlugin>
     */
    public function enabled(): array
    {
        return array_filter($this->all(), fn ($plugin) => $plugin->isEnabled());
    }

    /**
     * Get the plugin that handles a specific delivery option ID.
     */
    public function getByDeliveryOption(int $deliveryOptionId): ?NotificationPlugin
    {
        return $this->deliveryMap[$deliveryOptionId] ?? null;
    }

    /**
     * Find the appropriate plugin for verifying a notice.
     */
    public function findPluginForNotice(NotificationLog $log): ?NotificationPlugin
    {
        // First try direct mapping by delivery option ID
        $plugin = $this->getByDeliveryOption($log->delivery_option_id);
        if ($plugin && $plugin->canVerify($log)) {
            return $plugin;
        }

        // Fall back to checking each enabled plugin
        foreach ($this->enabled() as $plugin) {
            if ($plugin->canVerify($log)) {
                return $plugin;
            }
        }

        return null;
    }

    /**
     * Check if any plugin is registered.
     */
    public function hasPlugins(): bool
    {
        return count($this->plugins) > 0;
    }

    /**
     * Check if a specific plugin is registered.
     */
    public function has(string $name): bool
    {
        return isset($this->plugins[$name]);
    }

    /**
     * Get the count of registered plugins.
     */
    public function count(): int
    {
        return count($this->plugins);
    }
}
