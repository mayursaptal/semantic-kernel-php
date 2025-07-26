<?php

declare(strict_types=1);

namespace SemanticKernel\Configuration;

use InvalidArgumentException;

/**
 * Kernel Configuration Management - Centralized settings for Semantic Kernel
 * 
 * Provides a centralized configuration system for the Semantic Kernel framework,
 * mirroring Microsoft's Semantic Kernel configuration patterns. Manages all
 * aspects of kernel behavior including AI services, memory stores, logging,
 * telemetry, and execution parameters.
 * 
 * Features:
 * - Hierarchical configuration with dot notation access
 * - Default configuration values with override capability
 * - Environment variable integration
 * - Configuration validation and type checking
 * - JSON serialization and file loading
 * - Fluent configuration API
 * - Runtime configuration updates
 * - Configuration schema validation
 * 
 * @package SemanticKernel\Configuration
 * @author  Mayur Saptal <mayursaptal@gmail.com>
 * @version 1.0.0
 * @since   1.0.0
 * 
 * @example
 * ```php
 * // Create configuration with defaults
 * $config = new KernelConfig();
 * 
 * // Set configuration values
 * $config->set('ai_services.default_service', 'openai')
 *        ->set('ai_services.timeout', 60)
 *        ->set('logging.enabled', true);
 * 
 * // Get configuration values
 * $service = $config->get('ai_services.default_service'); // 'openai'
 * $timeout = $config->get('ai_services.timeout', 30);     // 60
 * 
 * // Load from environment
 * $config->loadFromEnvironment();
 * 
 * // Validate configuration
 * $errors = $config->validate();
 * if (empty($errors)) {
 *     echo "Configuration is valid";
 * }
 * ```
 */
class KernelConfig
{
    /** @var array<string, mixed> Configuration data */
    private array $config = [];
    
    /** @var array<string, mixed> Default configuration values */
    private array $defaults = [
        'logging' => [
            'enabled' => false,
            'level' => 'info',
            'format' => 'json',
        ],
        'telemetry' => [
            'enabled' => true,
            'export_interval' => 30,
            'max_events' => 1000,
        ],
        'plugins' => [
            'auto_discovery' => true,
            'scan_paths' => [],
            'cache_enabled' => true,
        ],
        'ai_services' => [
            'default_service' => 'openai',
            'timeout' => 30,
            'retry_attempts' => 3,
            'retry_delay' => 1,
        ],
        'memory' => [
            'default_store' => 'volatile',
            'vector_dimensions' => 1536,
            'similarity_threshold' => 0.7,
        ],
        'planner' => [
            'max_steps' => 10,
            'timeout' => 300,
            'allow_loops' => false,
        ],
        'execution' => [
            'parallel_execution' => false,
            'max_concurrent_functions' => 5,
            'timeout' => 60,
        ],
    ];

    /**
     * Constructs a new KernelConfig instance
     * 
     * Initializes the configuration with default values and optionally
     * loads configuration from an array or file.
     * 
     * @param array<string, mixed>|null $config Initial configuration (optional)
     * 
     * @since 1.0.0
     */
    public function __construct(?array $config = null)
    {
        $this->config = $this->defaults;
        
        if ($config !== null) {
            $this->merge($config);
        }
    }

    /**
     * Gets a configuration value using dot notation
     * 
     * Retrieves configuration values using hierarchical dot notation.
     * Returns the default value if the key doesn't exist.
     * 
     * @param string $key     Configuration key (supports dot notation)
     * @param mixed  $default Default value if key doesn't exist
     * 
     * @return mixed Configuration value or default
     * @since 1.0.0
     * 
     * @example
     * ```php
     * $service = $config->get('ai_services.default_service');
     * $timeout = $config->get('ai_services.timeout', 30);
     * $logging = $config->get('logging'); // Gets entire logging section
     * ```
     */
    public function get(string $key, mixed $default = null): mixed
    {
        $keys = explode('.', $key);
        $value = $this->config;

        foreach ($keys as $k) {
            if (!is_array($value) || !array_key_exists($k, $value)) {
                return $default;
            }
            $value = $value[$k];
        }

        return $value;
    }

    /**
     * Sets a configuration value using dot notation
     * 
     * Sets configuration values using hierarchical dot notation.
     * Creates nested arrays automatically if they don't exist.
     * 
     * @param string $key   Configuration key (supports dot notation)
     * @param mixed  $value Configuration value
     * 
     * @return self Configuration instance for method chaining
     * @since 1.0.0
     * 
     * @example
     * ```php
     * $config->set('ai_services.openai.api_key', 'sk-...')
     *        ->set('ai_services.openai.model', 'gpt-4')
     *        ->set('logging.enabled', true);
     * ```
     */
    public function set(string $key, mixed $value): self
    {
        $keys = explode('.', $key);
        $config = &$this->config;

        foreach ($keys as $i => $k) {
            if ($i === count($keys) - 1) {
                $config[$k] = $value;
            } else {
                if (!isset($config[$k]) || !is_array($config[$k])) {
                    $config[$k] = [];
                }
                $config = &$config[$k];
            }
        }

        return $this;
    }

    /**
     * Checks if a configuration key exists
     * 
     * @param string $key Configuration key (supports dot notation)
     * 
     * @return bool True if key exists, false otherwise
     * @since 1.0.0
     * 
     * @example
     * ```php
     * if ($config->has('ai_services.openai.api_key')) {
     *     echo "OpenAI API key is configured";
     * }
     * ```
     */
    public function has(string $key): bool
    {
        $keys = explode('.', $key);
        $value = $this->config;

        foreach ($keys as $k) {
            if (!is_array($value) || !array_key_exists($k, $value)) {
                return false;
            }
            $value = $value[$k];
        }

        return true;
    }

    /**
     * Removes a configuration key
     * 
     * @param string $key Configuration key (supports dot notation)
     * 
     * @return self Configuration instance for method chaining
     * @since 1.0.0
     * 
     * @example
     * ```php
     * $config->remove('ai_services.azure'); // Remove Azure config
     * ```
     */
    public function remove(string $key): self
    {
        $keys = explode('.', $key);
        $lastKey = array_pop($keys);
        $config = &$this->config;

        foreach ($keys as $k) {
            if (!is_array($config) || !array_key_exists($k, $config)) {
                return $this;
            }
            $config = &$config[$k];
        }

        unset($config[$lastKey]);
        return $this;
    }

    /**
     * Merges configuration data with existing configuration
     * 
     * Recursively merges the provided configuration with the existing
     * configuration, overriding existing values.
     * 
     * @param array<string, mixed> $config Configuration data to merge
     * 
     * @return self Configuration instance for method chaining
     * @since 1.0.0
     * 
     * @example
     * ```php
     * $config->merge([
     *     'ai_services' => [
     *         'openai' => ['api_key' => 'sk-...'],
     *         'timeout' => 60
     *     ],
     *     'logging' => ['enabled' => true]
     * ]);
     * ```
     */
    public function merge(array $config): self
    {
        $this->config = $this->deepMerge($this->config, $config);
        return $this;
    }

    /**
     * Performs a deep merge of two arrays, overwriting values instead of creating arrays
     * 
     * @param array $base   Base array
     * @param array $merge  Array to merge
     * 
     * @return array Merged array
     * @since 1.0.0
     * @internal
     */
    private function deepMerge(array $base, array $merge): array
    {
        foreach ($merge as $key => $value) {
            if (is_array($value) && isset($base[$key]) && is_array($base[$key])) {
                $base[$key] = $this->deepMerge($base[$key], $value);
            } else {
                $base[$key] = $value;
            }
        }
        return $base;
    }

    /**
     * Gets all configuration data
     * 
     * @return array<string, mixed> Complete configuration array
     * @since 1.0.0
     * 
     * @example
     * ```php
     * $allConfig = $config->all();
     * print_r($allConfig);
     * ```
     */
    public function all(): array
    {
        return $this->config;
    }

    /**
     * Resets configuration to defaults
     * 
     * @return self Configuration instance for method chaining
     * @since 1.0.0
     * 
     * @example
     * ```php
     * $config->reset(); // Back to default values
     * ```
     */
    public function reset(): self
    {
        $this->config = $this->defaults;
        return $this;
    }

    /**
     * Loads configuration from environment variables
     * 
     * Loads configuration from environment variables using a naming convention.
     * Environment variables should be prefixed with SK_ and use underscores
     * instead of dots.
     * 
     * @param string $prefix Environment variable prefix (default: 'SK_')
     * 
     * @return self Configuration instance for method chaining
     * @since 1.0.0
     * 
     * @example
     * ```php
     * // Environment variables:
     * // SK_AI_SERVICES_DEFAULT_SERVICE=openai
     * // SK_AI_SERVICES_TIMEOUT=60
     * // SK_LOGGING_ENABLED=true
     * 
     * $config->loadFromEnvironment('SK_');
     * ```
     */
    public function loadFromEnvironment(string $prefix = 'SK_'): self
    {
        foreach ($_ENV as $key => $value) {
            if (strpos($key, $prefix) === 0) {
                $configKey = strtolower(substr($key, strlen($prefix)));
                $configKey = str_replace('_', '.', $configKey);
                
                // Convert string values to appropriate types
                $typedValue = $this->convertEnvironmentValue($value);
                $this->set($configKey, $typedValue);
            }
        }

        return $this;
    }

    /**
     * Converts environment variable values to appropriate types
     * 
     * @param string $value Environment variable value
     * 
     * @return mixed Converted value
     * @since 1.0.0
     * @internal
     */
    private function convertEnvironmentValue(string $value): mixed
    {
        // Try to parse as JSON first (for arrays/objects)
        if ((str_starts_with($value, '[') && str_ends_with($value, ']')) ||
            (str_starts_with($value, '{') && str_ends_with($value, '}'))) {
            $decoded = json_decode($value, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                return $decoded;
            }
        }

        // Convert common boolean values
        if (in_array(strtolower($value), ['true', '1', 'yes', 'on'])) {
            return true;
        }
        if (in_array(strtolower($value), ['false', '0', 'no', 'off'])) {
            return false;
        }

        // Convert numeric values
        if (is_numeric($value)) {
            return strpos($value, '.') !== false ? (float) $value : (int) $value;
        }

        return $value;
    }

    /**
     * Loads configuration from a JSON file
     * 
     * @param string $filePath Path to JSON configuration file
     * 
     * @return self Configuration instance for method chaining
     * @throws InvalidArgumentException If file doesn't exist or is invalid JSON
     * @since 1.0.0
     * 
     * @example
     * ```php
     * $config->loadFromFile('./config/kernel.json');
     * ```
     */
    public function loadFromFile(string $filePath): self
    {
        if (!file_exists($filePath)) {
            throw new InvalidArgumentException("Configuration file not found: {$filePath}");
        }

        $extension = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));

        if ($extension === 'php') {
            // Handle PHP configuration files
            $data = include $filePath;
            if (!is_array($data)) {
                throw new InvalidArgumentException("PHP config file must return an array: {$filePath}");
            }
        } elseif ($extension === 'json') {
            // Handle JSON configuration files
            $content = file_get_contents($filePath);
            if ($content === false) {
                throw new InvalidArgumentException("Failed to read configuration file: {$filePath}");
            }

            $data = json_decode($content, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new InvalidArgumentException("Invalid JSON in config file: " . json_last_error_msg());
            }
        } else {
            throw new InvalidArgumentException("Unsupported config file format: {$extension}. Supported formats: json, php");
        }

        $this->merge($data);
        return $this;
    }

    /**
     * Saves configuration to a JSON file
     * 
     * @param string $filePath Path to save JSON configuration file
     * @param int    $flags    JSON encoding flags (default: JSON_PRETTY_PRINT)
     * 
     * @return self Configuration instance for method chaining
     * @throws InvalidArgumentException If file cannot be written
     * @since 1.0.0
     * 
     * @example
     * ```php
     * $config->saveToFile('./config/kernel.json');
     * ```
     */
    public function saveToFile(string $filePath, int $flags = JSON_PRETTY_PRINT): self
    {
        $json = json_encode($this->config, $flags);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new InvalidArgumentException("Failed to encode configuration as JSON: " . json_last_error_msg());
        }

        if (file_put_contents($filePath, $json) === false) {
            throw new InvalidArgumentException("Failed to write configuration file: {$filePath}");
        }

        return $this;
    }

    /**
     * Validates the current configuration
     * 
     * Validates the configuration against expected schemas and requirements.
     * Returns an array of validation errors, empty if valid.
     * 
     * @return array<string> Array of validation error messages
     * @since 1.0.0
     * 
     * @example
     * ```php
     * $errors = $config->validate();
     * if (empty($errors)) {
     *     echo "Configuration is valid";
     * } else {
     *     foreach ($errors as $error) {
     *         echo "Error: {$error}\n";
     *     }
     * }
     * ```
     */
    public function validate(): array
    {
        $errors = [];

        // Validate AI services
        $defaultService = $this->get('ai_services.default_service');
        if (!in_array($defaultService, ['openai', 'azure', 'ollama'])) {
            $errors[] = "Invalid default AI service: {$defaultService}";
        }

        // Validate timeouts
        $timeout = $this->get('ai_services.timeout');
        if (!is_int($timeout) || $timeout <= 0) {
            $errors[] = "AI service timeout must be greater than 0";
        }

        // Validate memory configuration
        $vectorDims = $this->get('memory.vector_dimensions');
        if (!is_int($vectorDims) || $vectorDims <= 0) {
            $errors[] = "Memory vector dimensions must be greater than 0";
        }

        $similarityThreshold = $this->get('memory.similarity_threshold');
        if (!is_numeric($similarityThreshold) || $similarityThreshold < 0 || $similarityThreshold > 1) {
            $errors[] = "Memory similarity threshold must be between 0 and 1";
        }

        // Validate planner configuration
        $maxSteps = $this->get('planner.max_steps');
        if (!is_int($maxSteps) || $maxSteps <= 0) {
            $errors[] = "Planner max steps must be greater than 0";
        }

        return $errors;
    }

    /**
     * Creates a configuration instance from environment
     * 
     * @param string $prefix Environment variable prefix (default: 'SK_')
     * 
     * @return self New configuration instance
     * @since 1.0.0
     * 
     * @example
     * ```php
     * $config = KernelConfig::fromEnvironment('SK_');
     * ```
     */
    public static function fromEnvironment(string $prefix = 'SK_'): self
    {
        $config = new self();
        $config->loadFromEnvironment($prefix);
        return $config;
    }

    /**
     * Creates a configuration instance from a JSON file
     * 
     * @param string $filePath Path to JSON configuration file
     * 
     * @return self New configuration instance
     * @since 1.0.0
     * 
     * @example
     * ```php
     * $config = KernelConfig::fromFile('./config/kernel.json');
     * ```
     */
    public static function fromFile(string $filePath): self
    {
        $config = new self();
        $config->loadFromFile($filePath);
        return $config;
    }

    /**
     * Magic method for debugging output
     * 
     * @return array<string, mixed> Debug information
     * @since 1.0.0
     */
    public function __debugInfo(): array
    {
        return [
            'config_keys' => array_keys($this->config),
            'validation_errors' => $this->validate(),
            'has_ai_service' => $this->has('ai_services.default_service'),
            'has_memory_config' => $this->has('memory.default_store'),
            'config_size' => count($this->config, COUNT_RECURSIVE)
        ];
    }
} 