# Psst Documentation

Pretty Secure Secret Transactions

This plugin is gives visitors a way to create one time secrets that expire after viewing.

By default all secrets are secured using WordPress salts and SHA256 encryption. Once a item is viewed the secret is 
deleted from the database. Once the secret expires it is deleted from the database.

**This plugin works most effectively when using a true cron and not specifically relying on WordPress built in scheduler**

## Crons

By default 3 cron events are scheduled
* 5min : Expire all secrets
* Hourly: Purge and Expired secrets
* Daily: Daily audit to purge any potentially missed secrets

## Changelog

Version: 1.0.2

* Cleaned Up Markup
* Fixed an issue where warning would show at incorrect times
* Fixed an issue with password protected secrets throwing a fatal error

Version: 1.0.0 Initial Release:
