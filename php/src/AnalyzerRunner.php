<?php

declare(strict_types=1);

namespace SilverstripeMCP;

use PhpParser\Node;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitor\NameResolver;
use PhpParser\NodeVisitorAbstract;
use PhpParser\ParserFactory;
use PhpParser\Error as ParseError;
use SilverstripeMCP\Contracts\ValidatorPluginInterface;
use SilverstripeMCP\Plugins\NamespaceValidatorPlugin;
use SilverstripeMCP\Plugins\BuildTaskValidatorPlugin;
use SilverstripeMCP\Plugins\DeprecatedConfigPlugin;
use SilverstripeMCP\Plugins\ExtensionHookVisibilityPlugin;
use SilverstripeMCP\Plugins\ElementalNamespacePlugin;
use SilverstripeMCP\Plugins\FormFieldValuePlugin;
use SilverstripeMCP\Plugins\RemovedMethodPlugin;
use SilverstripeMCP\Plugins\HookRenamePlugin;

/**
 * Orchestrates PHP code analysis using registered plugins.
 *
 * Plugins are organized into two categories:
 * - Core plugins: Always run on every analysis
 * - Auto plugins: Enabled based on code context (e.g., only run Extension plugin on Extension classes)
 */
class AnalyzerRunner
{
    /** @var ValidatorPluginInterface[] Core plugins that always run */
    private array $corePlugins = [];

    /** @var array<string, array{plugin: ValidatorPluginInterface, trigger: array<string, mixed>}> Auto plugins with triggers */
    private array $autoPlugins = [];

    /** @var array<string, mixed> */
    private array $config = [];

    public function __construct()
    {
        // Register built-in plugins
        $this->registerBuiltinPlugins();
    }

    /**
     * Register built-in plugins.
     */
    private function registerBuiltinPlugins(): void
    {
        // Core plugins - always run
        $this->registerCorePlugin(new NamespaceValidatorPlugin());
        $this->registerCorePlugin(new BuildTaskValidatorPlugin());
        $this->registerCorePlugin(new FormFieldValuePlugin());
        $this->registerCorePlugin(new RemovedMethodPlugin());
        $this->registerCorePlugin(new HookRenamePlugin());
        $this->registerCorePlugin(new DeprecatedConfigPlugin());

        // Auto plugins - enabled when relevant code detected
        $this->registerAutoPlugin(new ExtensionHookVisibilityPlugin(), [
            'trigger' => 'extends',
            'classes' => ['Extension', 'DataExtension', 'SiteTreeExtension'],
        ]);
        $this->registerAutoPlugin(new ElementalNamespacePlugin(), [
            'trigger' => 'import_prefix',
            'prefix' => 'DNADesign\\Elemental',
        ]);
    }

    /**
     * Register a core plugin (always runs).
     */
    public function registerCorePlugin(ValidatorPluginInterface $plugin): void
    {
        $this->corePlugins[$plugin->getName()] = $plugin;
    }

    /**
     * Register an auto plugin with context trigger.
     *
     * @param ValidatorPluginInterface $plugin The plugin instance
     * @param array<string, mixed> $triggerConfig Trigger configuration:
     *   - trigger: 'extends' - Enable when class extends one of the classes
     *     - classes: string[] - Class names to match (can be short or full)
     *   - trigger: 'import_prefix' - Enable when any import starts with prefix
     *     - prefix: string - The namespace prefix to match
     */
    public function registerAutoPlugin(ValidatorPluginInterface $plugin, array $triggerConfig): void
    {
        $this->autoPlugins[$plugin->getName()] = [
            'plugin' => $plugin,
            'trigger' => $triggerConfig,
        ];
    }

    /**
     * Legacy method for registering plugins (maps to core plugin).
     * @deprecated Use registerCorePlugin() instead
     */
    public function registerPlugin(ValidatorPluginInterface $plugin): void
    {
        $this->registerCorePlugin($plugin);
    }

    /**
     * Legacy method for registering optional plugins (maps to auto plugin with no trigger).
     * @deprecated Use registerAutoPlugin() instead
     */
    public function registerOptionalPlugin(ValidatorPluginInterface $plugin): void
    {
        // Legacy optional plugins need explicit enablement
        $this->autoPlugins[$plugin->getName()] = [
            'plugin' => $plugin,
            'trigger' => ['trigger' => 'explicit'],
        ];
    }

    /**
     * Load configuration from a JSON file.
     *
     * @param string $configPath Path to silverstripe-mcp.json
     */
    public function loadConfigFile(string $configPath): void
    {
        if (!file_exists($configPath)) {
            return;
        }

        $json = file_get_contents($configPath);
        if ($json === false) {
            return;
        }

        $config = json_decode($json, true);
        if (!is_array($config)) {
            return;
        }

        // Resolve relative paths in customPlugins relative to config file
        $configDir = dirname($configPath);
        if (isset($config['customPlugins']) && is_array($config['customPlugins'])) {
            $config['customPlugins'] = array_map(
                fn(string $path) => $this->resolvePluginPath($path, $configDir),
                $config['customPlugins']
            );
        }

        $this->loadConfig($config);
    }

    /**
     * Resolve a plugin path relative to the config directory.
     */
    private function resolvePluginPath(string $path, string $configDir): string
    {
        // If path is relative, resolve it relative to config directory
        if (!str_starts_with($path, '/') && !preg_match('/^[A-Z]:/i', $path)) {
            return $configDir . DIRECTORY_SEPARATOR . $path;
        }
        return $path;
    }

    /**
     * Load configuration from array.
     *
     * @param array<string, mixed> $config
     */
    public function loadConfig(array $config): void
    {
        $this->config = array_merge($this->config, $config);

        // Configure plugins with their options
        $pluginConfigs = $config['plugins'] ?? [];

        // Configure core plugins
        foreach ($this->corePlugins as $name => $plugin) {
            $pluginConfig = $pluginConfigs[$name] ?? [];

            // Check if plugin is disabled
            if (isset($pluginConfig['enabled']) && $pluginConfig['enabled'] === false) {
                unset($this->corePlugins[$name]);
                continue;
            }

            $plugin->configure($pluginConfig);
        }

        // Configure auto plugins (and handle explicit enablement)
        foreach ($this->autoPlugins as $name => $entry) {
            $pluginConfig = $pluginConfigs[$name] ?? [];

            // Configure the plugin
            $entry['plugin']->configure($pluginConfig);

            // If explicitly disabled, remove from auto plugins
            if (isset($pluginConfig['enabled']) && $pluginConfig['enabled'] === false) {
                unset($this->autoPlugins[$name]);
                continue;
            }

            // If explicitly enabled, move to core plugins (always run)
            if (isset($pluginConfig['enabled']) && $pluginConfig['enabled'] === true) {
                $this->corePlugins[$name] = $entry['plugin'];
                unset($this->autoPlugins[$name]);
            }
        }

        // Load custom plugins
        $customPlugins = $config['customPlugins'] ?? [];
        foreach ($customPlugins as $pluginPath) {
            $this->loadCustomPlugin($pluginPath, $pluginConfigs);
        }
    }

    /**
     * Load a custom plugin from file path.
     *
     * @param string $path Path to plugin file
     * @param array<string, mixed> $pluginConfigs Plugin configurations
     */
    private function loadCustomPlugin(string $path, array $pluginConfigs = []): void
    {
        if (!file_exists($path)) {
            // Skip missing plugins silently
            return;
        }

        // Include the file
        $result = require_once $path;

        // If the file returns a class name, instantiate it
        if (is_string($result) && class_exists($result)) {
            $plugin = new $result();
            if ($plugin instanceof ValidatorPluginInterface) {
                // Configure the plugin if options exist
                $pluginConfig = $pluginConfigs[$plugin->getName()] ?? [];
                if (isset($pluginConfig['enabled']) && $pluginConfig['enabled'] === false) {
                    return;
                }
                $plugin->configure($pluginConfig);
                $this->registerCorePlugin($plugin);
            }
        }

        // If the file returns an instance, register it directly
        if ($result instanceof ValidatorPluginInterface) {
            $pluginConfig = $pluginConfigs[$result->getName()] ?? [];
            if (isset($pluginConfig['enabled']) && $pluginConfig['enabled'] === false) {
                return;
            }
            $result->configure($pluginConfig);
            $this->registerCorePlugin($result);
        }
    }

    /**
     * Detect code context from AST for auto-plugin activation.
     *
     * @param array<Node> $ast Parsed AST
     * @return array{imports: string[], extends: string|null, implements: string[]}
     */
    private function detectContext(array $ast): array
    {
        $context = [
            'imports' => [],
            'extends' => null,
            'implements' => [],
        ];

        $traverser = new NodeTraverser();
        $traverser->addVisitor(new class($context) extends NodeVisitorAbstract {
            /** @var array{imports: string[], extends: string|null, implements: string[]} */
            private array $context;

            /**
             * @param array{imports: string[], extends: string|null, implements: string[]} $context
             */
            public function __construct(array &$context)
            {
                $this->context = &$context;
            }

            public function enterNode(Node $node): ?int
            {
                // Collect use statements (imports)
                if ($node instanceof Node\Stmt\Use_) {
                    foreach ($node->uses as $use) {
                        $this->context['imports'][] = $use->name->toString();
                    }
                }

                // Collect class extends and implements
                if ($node instanceof Node\Stmt\Class_) {
                    if ($node->extends !== null) {
                        $this->context['extends'] = $node->extends->toString();
                    }

                    foreach ($node->implements as $interface) {
                        $this->context['implements'][] = $interface->toString();
                    }
                }

                return null;
            }
        });

        $traverser->traverse($ast);

        return $context;
    }

    /**
     * Check if an auto-plugin should be enabled based on code context.
     *
     * @param array<string, mixed> $triggerConfig Plugin's trigger configuration
     * @param array{imports: string[], extends: string|null, implements: string[]} $context Detected code context
     * @return bool
     */
    private function shouldEnableAutoPlugin(array $triggerConfig, array $context): bool
    {
        $trigger = $triggerConfig['trigger'] ?? null;

        switch ($trigger) {
            case 'extends':
                // Check if class extends any of the trigger classes
                if ($context['extends'] === null) {
                    return false;
                }
                $classes = $triggerConfig['classes'] ?? [];
                foreach ($classes as $class) {
                    if ($this->extendsClass($context['extends'], $class)) {
                        return true;
                    }
                }
                return false;

            case 'import_prefix':
                // Check if any import starts with the prefix
                $prefix = $triggerConfig['prefix'] ?? '';
                foreach ($context['imports'] as $import) {
                    if (str_starts_with($import, $prefix)) {
                        return true;
                    }
                }
                return false;

            case 'explicit':
                // Explicit plugins require config enablement (handled in loadConfig)
                return false;

            default:
                return false;
        }
    }

    /**
     * Check if a class name matches an expected class (handles short and full names).
     *
     * @param string $actual The actual class name from extends
     * @param string $expected The expected class name (can be short or full)
     */
    private function extendsClass(string $actual, string $expected): bool
    {
        // Exact match
        if ($actual === $expected) {
            return true;
        }

        // Short name match (e.g., "Extension" matches "SilverStripe\Core\Extension")
        $actualParts = explode('\\', $actual);
        $actualShort = end($actualParts);

        if ($actualShort === $expected) {
            return true;
        }

        // Also check if expected is a suffix (e.g., "Extension" matches "MyExtension")
        // This handles custom naming conventions
        if (str_ends_with($actualShort, $expected)) {
            return true;
        }

        return false;
    }

    /**
     * Analyze PHP code and return results.
     *
     * @param string $code PHP code to analyze
     * @param string $filename Optional filename for context
     * @param string $targetVersion Target Silverstripe version
     * @param bool $enableAllPlugins Force enable all plugins regardless of context
     */
    public function analyze(
        string $code,
        string $filename = '',
        string $targetVersion = '6.0',
        bool $enableAllPlugins = false
    ): AnalysisResult {
        // Create analysis context
        $context = new AnalysisContext(
            code: $code,
            filename: $filename,
            targetVersion: $targetVersion,
        );

        // Parse PHP code to AST
        $parser = (new ParserFactory())->createForNewestSupportedVersion();

        try {
            $ast = $parser->parse($code);
        } catch (ParseError $e) {
            return AnalysisResult::parseError(
                $e->getMessage(),
                $e->getStartLine()
            );
        }

        if ($ast === null) {
            return AnalysisResult::parseError('Failed to parse PHP code');
        }

        // Determine which plugins to run
        $activePlugins = $this->corePlugins;

        if ($enableAllPlugins) {
            // Add ALL auto plugins when enableAllPlugins is true
            foreach ($this->autoPlugins as $name => $entry) {
                $activePlugins[$name] = $entry['plugin'];
            }
        } else {
            // Smart detection - only add relevant auto plugins based on context
            $codeContext = $this->detectContext($ast);
            foreach ($this->autoPlugins as $name => $entry) {
                if ($this->shouldEnableAutoPlugin($entry['trigger'], $codeContext)) {
                    $activePlugins[$name] = $entry['plugin'];
                }
            }
        }

        // Run each active plugin's visitor against the AST
        foreach ($activePlugins as $plugin) {
            // Check if plugin applies to target version
            if (!$this->pluginMatchesVersion($plugin, $targetVersion)) {
                continue;
            }

            $traverser = new NodeTraverser();

            // Add name resolver first to resolve class names
            $traverser->addVisitor(new NameResolver());

            // Add the plugin's visitor
            $traverser->addVisitor($plugin->getVisitor($context));

            // Traverse the AST
            $traverser->traverse($ast);
        }

        return $context->getResult();
    }

    /**
     * Check if a plugin applies to the target version.
     */
    private function pluginMatchesVersion(ValidatorPluginInterface $plugin, string $targetVersion): bool
    {
        $targetVersions = $plugin->getTargetVersions();

        foreach ($targetVersions as $pattern) {
            // Match all versions
            if ($pattern === '*') {
                return true;
            }

            // Exact match
            if ($pattern === $targetVersion) {
                return true;
            }

            // Wildcard match (e.g., '6.*' matches '6.0', '6.1')
            if (str_ends_with($pattern, '.*')) {
                $major = substr($pattern, 0, -2);
                if (str_starts_with($targetVersion, $major . '.')) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Get list of core plugin names.
     *
     * @return string[]
     */
    public function getCorePluginNames(): array
    {
        return array_keys($this->corePlugins);
    }

    /**
     * Get list of auto plugin names.
     *
     * @return string[]
     */
    public function getAutoPluginNames(): array
    {
        return array_keys($this->autoPlugins);
    }

    /**
     * Legacy method for getting all enabled plugin names.
     * @deprecated Use getCorePluginNames() and getAutoPluginNames() instead
     *
     * @return string[]
     */
    public function getPluginNames(): array
    {
        return array_merge(
            array_keys($this->corePlugins),
            array_keys($this->autoPlugins)
        );
    }

    /**
     * Legacy method for getting optional plugin names.
     * @deprecated Use getAutoPluginNames() instead
     *
     * @return string[]
     */
    public function getOptionalPluginNames(): array
    {
        return array_keys($this->autoPlugins);
    }

    /**
     * Check if enableAllPlugins is set in config.
     */
    public function isEnableAllPluginsSet(): bool
    {
        return ($this->config['enableAllPlugins'] ?? false) === true;
    }
}
