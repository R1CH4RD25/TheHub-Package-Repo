---
name: Package Submission
about: Submit a new package to The Hub Package Repository
title: '[PACKAGE] Package Name v1.0.0'
labels: package-submission, needs-review
assignees: ''

---

## Package Information

**Package ID:** `category-package-name`
**Display Name:** Your Package Name
**Version:** 1.0.0
**Author:** Your Name/Organization
**Category:** [ ] Reporting [ ] Forms [ ] Workflows [ ] Analytics [ ] Integrations

## Description

<!-- Provide a clear, concise description of what your package does (2-3 sentences) -->


## Features

<!-- List the main features of your package -->

- Feature 1
- Feature 2
- Feature 3

## System Requirements

- **The Hub Version:** v1.0.0+
- **PHP Version:** 8.2+
- **MySQL Version:** 10.11+
- **Special Requirements:** (list any PHP extensions, etc.)

## Installation Notes

<!-- Any special configuration or setup steps required after installation -->


## Permissions

<!-- Who should have access to this section? -->

- **View:** 
- **Create:** 
- **Edit:** 
- **Delete:** 

## Pre-Submission Checklist

### Validation
- [ ] Package passes all 10 validation checks in The Hub
- [ ] Tested in production-like environment
- [ ] No errors in browser console
- [ ] All fields function as expected

### Documentation
- [ ] README.md is complete with all required sections
- [ ] CHANGELOG.md documents all features
- [ ] At least 2 screenshots included
- [ ] Installation instructions are clear

### Security
- [ ] All user input is validated
- [ ] No SQL injection vulnerabilities
- [ ] XSS protection implemented
- [ ] File uploads properly restricted
- [ ] No hardcoded credentials or API keys

### Code Quality
- [ ] Package ID follows naming convention
- [ ] Semantic versioning used
- [ ] All required fields have validation rules
- [ ] Permissions follow least privilege principle
- [ ] No conflicts with existing packages

### Files Included
- [ ] `.hubpkg` file added to appropriate category folder
- [ ] `README.md` in package directory
- [ ] `CHANGELOG.md` in package directory
- [ ] Screenshots in `screenshots/` folder
- [ ] Main README.md updated with package entry

## Screenshots

<!-- Paste screenshots or links to images demonstrating your package -->


## Additional Notes

<!-- Any other information reviewers should know -->


## Pull Request

**PR Link:** (link to your pull request)

---

**By submitting this package, I confirm that:**
- [ ] I have read and agree to the [Contributing Guidelines](../CONTRIBUTING.md)
- [ ] This package is licensed under MIT License or compatible
- [ ] I will provide ongoing support and security updates
- [ ] This package contains no malicious code or security vulnerabilities
