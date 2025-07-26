# Contributing to Semantic Kernel PHP

We welcome contributions to the Semantic Kernel PHP project! This document provides guidelines for contributing.

## 🤝 How to Contribute

### Reporting Issues
- Use the [GitHub Issues](https://github.com/mayursaptal/semantic-kernel-php/issues) to report bugs
- Search existing issues before creating new ones
- Include clear descriptions, steps to reproduce, and expected vs actual behavior
- Add relevant labels and provide system information

### Feature Requests
- Open an issue with the "enhancement" label
- Describe the feature, its benefits, and use cases
- Discuss implementation approaches before starting work

### Code Contributions

#### Prerequisites
- PHP 8.1 or higher
- Composer
- Git

#### Development Setup
1. Fork the repository
2. Clone your fork:
   ```bash
   git clone https://github.com/your-username/semantic-kernel-php.git
   cd semantic-kernel-php
   ```
3. Install dependencies:
   ```bash
   composer install
   ```
4. Copy environment template:
   ```bash
   cp env.template .env
   ```
5. Configure your `.env` file with API keys for testing

#### Making Changes
1. Create a feature branch:
   ```bash
   git checkout -b feature/your-feature-name
   ```
2. Make your changes following our coding standards
3. Add tests for new functionality
4. Ensure all tests pass:
   ```bash
   composer test
   composer validate
   ```
5. Update documentation as needed

#### Coding Standards
- Follow PSR-4 autoloading standards
- Use PSR-12 coding style
- Write meaningful commit messages
- Add PHPDoc comments for public methods
- Maintain backward compatibility when possible
- Use type declarations for all parameters and return types

#### Testing
- Write unit tests for new features
- Ensure existing tests continue to pass
- Test with different PHP versions (8.1+)
- Include integration tests for complex features

#### Pull Request Process
1. Update the README.md if needed
2. Update the CHANGELOG.md with your changes
3. Ensure the PR description clearly describes the changes
4. Link related issues using "Fixes #issue-number"
5. Request review from maintainers

## 📁 Project Structure

```
semantic-kernel-php/
├── src/                          # Core library code
│   ├── AI/                      # AI service implementations
│   ├── Configuration/           # Configuration management
│   ├── Events/                  # Event system
│   ├── Memory/                  # Memory store implementations
│   ├── Planner/                 # AI planning components
│   └── Plugins/                 # Plugin system
├── tests/                       # Test suite
├── examples/                    # Usage examples
├── plugins/sample_plugins/      # Sample plugin implementations
└── docs/                        # Additional documentation
```

## 🔧 Development Guidelines

### Architecture Principles
- Maintain compatibility with Microsoft's Semantic Kernel
- Follow SOLID principles
- Use dependency injection
- Implement proper error handling
- Support extensibility through interfaces

### Adding New Features
1. **AI Services**: Implement `ChatServiceInterface`
2. **Memory Stores**: Implement `MemoryStoreInterface`
3. **Plugins**: Follow the plugin architecture patterns
4. **Events**: Use the existing event system
5. **Configuration**: Extend `KernelConfig` as needed

### Documentation
- Update README.md for new features
- Add examples to demonstrate usage
- Include inline code documentation
- Update the cookbook with new patterns

## 🚀 Release Process

1. Update version in composer.json
2. Update CHANGELOG.md
3. Tag the release
4. Create GitHub release with notes

## 🐛 Bug Reports

Include:
- PHP version
- Semantic Kernel PHP version
- Operating system
- Steps to reproduce
- Expected vs actual behavior
- Error messages and stack traces

## 💡 Feature Development

For major features:
1. Open an issue for discussion
2. Create a design document if needed
3. Break into smaller, reviewable PRs
4. Update tests and documentation
5. Consider backward compatibility

## 📞 Getting Help

- Check the [README.md](README.md) and [SemanticKernelCookbook.md](SemanticKernelCookbook.md)
- Browse existing [issues](https://github.com/mayursaptal/semantic-kernel-php/issues)
- Join discussions in the repository

## 🙏 Recognition

Contributors will be:
- Listed in the AUTHORS file
- Mentioned in release notes
- Recognized in the community

## 👨‍💻 Maintainer

This project is maintained by [Mayur Saptal](https://github.com/mayursaptal).

Thank you for contributing to Semantic Kernel PHP! 🚀 