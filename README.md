![Linchpin](https://github.com/linchpin/brand-assets/raw/master/github-opensource-banner.png)

# Psst Documentation

### Pretty Secure Secret Transactions

This plugin is gives visitors a way to create one time secrets that expire after viewing.

## How Does it work?

This project is a WordPress plugin, While it can be used in conjunction with your site, it may make sense to create a stand alone site as it has a specific purpose.

By default all secrets are secured using WordPress [defuse/php-encryption](https://github.com/defuse/php-encryption).
* Once a secret is viewed, the secret is deleted from the database.
* Once the secret expires it is deleted from the database.

## Dependencies

[defuse/php-encryption](https://github.com/defuse/php-encryption) This is used for encryting post data.

## Good to Haves

**This plugin works most effectively when using a true crontab and not specifically relying on WordPress built in scheduler** This will allow for better accuracy when doing general cleanup.

## Crons

By default 3 cron events are scheduled
* 5min : Expire all secrets
* Hourly: Purge and Expired secrets
* Daily: Daily audit to purge any potentially missed secrets

## Changelog

### Version: 1.0.2
 * Cleaned Up Markup
 * Fixed an issue where warning would show at incorrect times

### Version: 1.0.1
 * Fixed an issue with pass phase protected posts not being displayed.

### Version: 1.0.0 Initial Release:

