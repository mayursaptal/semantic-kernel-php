# Security Policy

## Supported Versions

We release patches for security vulnerabilities in the following versions:

| Version | Supported          |
| ------- | ------------------ |
| 1.0.x   | :white_check_mark: |

## Reporting a Vulnerability

We take the security of Semantic Kernel PHP seriously. If you believe you have found a security vulnerability, please report it to us as described below.

**Please do not report security vulnerabilities through public GitHub issues.**

Instead, please report them via email to: **mayursaptal@gmail.com**

You can also use GitHub's private vulnerability reporting feature if available.

### What to Include

Please include the following information in your report:

- Type of issue (e.g. buffer overflow, SQL injection, cross-site scripting, etc.)
- Full paths of source file(s) related to the manifestation of the issue
- The location of the affected source code (tag/branch/commit or direct URL)
- Any special configuration required to reproduce the issue
- Step-by-step instructions to reproduce the issue
- Proof-of-concept or exploit code (if possible)
- Impact of the issue, including how an attacker might exploit the issue

### Response Timeline

- **Initial Response**: Within 48 hours of receiving your report
- **Assessment**: Within 7 days, we'll provide an initial assessment
- **Fix Development**: Patches will be developed as quickly as possible
- **Release**: Security fixes will be released immediately upon completion
- **Disclosure**: We'll coordinate with you on public disclosure timing

### Security Best Practices

When using Semantic Kernel PHP:

1. **API Key Security**:
   - Never commit API keys to version control
   - Use environment variables for sensitive configuration
   - Rotate API keys regularly
   - Limit API key permissions to minimum required

2. **Input Validation**:
   - Validate all user inputs before processing
   - Sanitize inputs passed to semantic functions
   - Implement rate limiting for AI service calls

3. **Memory Store Security**:
   - Use proper authentication for Redis connections
   - Encrypt sensitive data before storing in memory
   - Implement access controls for memory collections

4. **Network Security**:
   - Use HTTPS for all external AI service calls
   - Validate SSL certificates
   - Implement proper timeout and retry mechanisms

5. **Error Handling**:
   - Don't expose sensitive information in error messages
   - Log security events for monitoring
   - Implement proper exception handling

### Security Updates

Security updates will be:
- Released as patch versions (e.g., 1.0.1, 1.0.2)
- Documented in the CHANGELOG.md
- Announced through GitHub releases
- Tagged with `security` label

### Acknowledgments

We appreciate the security research community and will acknowledge researchers who responsibly disclose vulnerabilities to us. With your permission, we will:

- Credit you in the security advisory
- Mention you in the release notes
- Include you in our security researchers hall of fame

### Contact

For any security-related questions or concerns, please contact:
- **Mayur Saptal**: mayursaptal@gmail.com
- **GitHub**: https://github.com/mayursaptal

Thank you for helping keep Semantic Kernel PHP and our users safe! 