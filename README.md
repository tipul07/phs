## Welcome to MorPHSpiral

[![GitHub issues](https://img.shields.io/github/issues/tipul07/phs.svg)](https://github.com/tipul07/phs/issues/)
[![GitHub contributors](https://img.shields.io/github/contributors/tipul07/phs.svg)](https://GitHub.com/tipul07/phs/graphs/contributors/)
[![GitHub last commit (branch)](https://img.shields.io/github/last-commit/tipul07/phs/master?color=green)](https://github.com/tipul07/phs/graphs/commit-activity)
[![GitHub commit activity](https://img.shields.io/github/commit-activity/m/tipul07/phs?color=green)](https://github.com/tipul07/phs/graphs/commit-activity)
[![GitHub code size in bytes](https://img.shields.io/github/languages/code-size/tipul07/phs?color=green)](https://github.com/tipul07/phs/commits/master)
[![GitHub license](https://img.shields.io/github/license/tipul07/phs.svg)](https://github.com/tipul07/phs/commits/master)

Latest release [![GitHub release (latest by date)](https://img.shields.io/github/v/release/tipul07/phs?logo=GitHub)](https://github.com/tipul07/phs/releases)

**NOTE**: until 1.2.0.0 minimum PHP version required was 7.4+. Starting with 1.2.0.1, minimum required version is 8.1+.

**NOTE**: Added multi-tenant solution since 1.2.0.0. On next release, PHP 8.1.0 will be minimum required version.

Minimum PHP version required 7.4+ (required for class autloading)

**NOTE**: until 1.1.7.4 minimum PHP version required was 5.6+ (required in 3rd party register/login)

**NOTE**: until 1.1.4.5 minimum PHP version required was 5.4+.

**NOTE**: Using gitflow starting with 1.1.4.0.

Please, read our documentation (work in progress): [Here](https://github.com/tipul07/phs/wiki)

### Built-in functionalities:

1. Domain Driven Development using plugins

2. Support for front-application development
    - Bearer token authentication

3. Mobile applications integration
    - Register/login accounts using mobile API plugin
    - Send push notifications (GCM and APN) to registered devices/accounts

4. User management:
    - Predefined user roles: guest, member, operator, admin, super-admin and developer, however authorization is done using roles
    - TFA authentication available
    - Setup password validation rules such as force users to change passwords after x days, don't allow changing password to an already used passwords
    - Use nickname or email when authenticating users
    - Account activation (if required) and email validation

5. Register and login 3rd party services
    - Register and Login using Apple
    - Register and Login using Facebook

6. Roles system:
    - Role units give access to a user to specific functionalities
    - Role units are grouped in roles
    - Roles are assigned to users
    - An admin account can define custom roles which give users access to different sections of the site

7. Email sending
    - Send emails using SMTP
    - Send email using SendGrid

8. Built-in backup system
    - Create backups for database and uploaded content based on rules (daily, weekly, once x days etc.)
    - Delete old backups (older than x days)
    - Upload backup archives to a sFTP server (if required)

9. Built-in paginator for reports
    - Base model, columns, filters and actions definitions are required in order to present listings
    - Paginator handles CSV exports with no extra coding
    - Can use same action to generate JSON response when requesting action with an API call

10. Background tasks management
    - Agent jobs: actions exectuted once x seconds (depending on crontab setup)
    - Event based background actions

11. Internal messaging
    - Email like messaging system
    - Custom message types can be programmatically added

12. Other built-in features
    - Custom Captcha
    - Cookie acceptance notification
    - Simple BB editor
    - HubSpot integration
    - MailChimp integration