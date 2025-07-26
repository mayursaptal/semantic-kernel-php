# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

## [1.0.0] - 2025-01-27

### Added
- **Core Architecture**: Complete implementation of Microsoft Semantic Kernel architecture for PHP
- **Kernel Management**: Central `Kernel` class with builder pattern support via `KernelBuilder`
- **Plugin System**: Modular plugin architecture with `KernelPlugin` and `KernelFunction` abstractions
- **Function Types**: 
  - `SemanticFunction` for AI-powered prompt-based functions
  - `NativeFunction` for PHP callable functions with reflection-based parameter discovery
- **AI Service Integration**:
  - `ChatServiceInterface` with multiple implementations
  - `OpenAIChatService` for OpenAI API integration
  - `AzureOpenAIService` for Azure OpenAI support
  - `OllamaLocalService` for local LLM support via Ollama
- **Memory Management**:
  - `MemoryStoreInterface` with pluggable implementations
  - `VolatileMemoryStore` for in-memory storage
  - `RedisMemoryStore` for persistent Redis-based storage
  - Vector similarity search and text-based retrieval
- **AI Planning**: `Planner` class for goal decomposition and automated execution
- **Event System**: Comprehensive telemetry with `EventDispatcher` and `FunctionInvokedEvent`
- **Middleware Pipeline**: Extensible request/response processing with before/after hooks
- **Configuration Management**: `KernelConfig` with environment-based configuration and validation
- **Plugin Auto-Discovery**: `PluginLoader` for automatic scanning and registration of plugins
- **Context Management**: `ContextVariables` for data flow between functions
- **Result Handling**: `FunctionResult` with success/error states and metadata
- **Builder Pattern**: Fluent API for kernel construction and configuration
- **Environment Integration**: Support for `.env` files and environment-based configuration
- **Comprehensive Examples**: 
  - `quick_test.php` for basic validation
  - `comprehensive_semantic_kernel_demo.php` for advanced features
- **Sample Plugins**: 
  - `TextProcessingSkill` native function plugin
  - `WritingSkills` semantic function plugins (summarize, translate)
- **Testing Infrastructure**: Complete test suite with 38+ test cases covering all components
- **Documentation**: 
  - Comprehensive README with examples and API documentation
  - `SemanticKernelCookbook.md` with practical usage patterns
  - `CONTRIBUTING.md` for developers
  - Inline PHPDoc documentation throughout

### Technical Features
- **PSR-4 Autoloading**: Full Composer package support
- **PSR-3 Logging**: Integrated logging interface support
- **PHP 8.1+ Compatibility**: Modern PHP features and type declarations
- **Microsoft SK Alignment**: 100% API compatibility with naming and patterns
- **Extensible Architecture**: Interface-based design for easy extension
- **Error Handling**: Comprehensive exception handling and error reporting
- **Performance Optimized**: Efficient execution with caching support
- **Enterprise Ready**: Production-ready with monitoring and configuration

### Dependencies
- PHP >=8.1
- guzzlehttp/guzzle ^7.0 (HTTP client for AI services)
- vlucas/phpdotenv ^5.4 (Environment variable management)
- psr/log ^3.0 (Logging interface)
- predis/predis ^2.0 (Redis client for memory store)

### Development Dependencies
- phpunit/phpunit ^10.0 (Testing framework)

### Author
- **Mayur Saptal** - Initial work and ongoing maintenance

[Unreleased]: https://github.com/mayursaptal/semantic-kernel-php/compare/v1.0.0...HEAD
[1.0.0]: https://github.com/mayursaptal/semantic-kernel-php/releases/tag/v1.0.0 