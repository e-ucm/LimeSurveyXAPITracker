# AGENTS.md

## Architecture
- This is a LimeSurvey plugin (PHP) that sends xAPI statements to an LRS
- Entry point: LimeSurveyXAPITracker.php
- Key events: afterSurveyComplete, beforeSurveyPage, afterResponseSave
- Configuration stored in LimeSurvey's DB and config.php

## Configuration
- Global settings defined in LimeSurvey's `application/config/config.php` under `XAPITrackerSettings`
- Use `fixed` array to hardcode values (e.g., `baseUrlLRC`, `lrsEndpoint`)
- Use `hidden` array to hide settings from UI (e.g., credentials)
- Per-survey settings override global ones when `surveylrsendpoint` is enabled
- Debug mode enabled via `sBug` flag - shows payloads to respondents

## Development Flow
1. Install plugin by cloning into `<limesurvey_root>/plugins/LimeSurveyXAPITracker/`
2. Configure settings in `config.php` using the `XAPITrackerSettings` array format
3. Enable debug mode (`sBug`) to see xAPI payloads in the survey UI
4. Test with dummy LRS endpoint like `https://webhook.site`
5. Configure OAuth2 if needed: `OAuth2TokenEndpoint`, `OAuth2ClientId`
6. Disable debug mode before production deployment

## Testing
- No test suite in repo - test via live survey interactions
- Use debug mode (`sBug`) to inspect outgoing xAPI payloads
- Monitor PHP error logs for `XAPITracker` messages
- Use `https://webhook.site` to capture and verify HTTP requests

## Important Quirks
- OAuth2 tokens are cached in DB with `expire_at` and `refresh_expires_at` keys
- Survey responses are fetched via LimeSurvey's RemoteControl API
- Question types map to xAPI interaction types in `$limesurveyToXapiInteractionTypes`
- Multi-select questions require special handling with parent_qid relationships
- Always use `getGlobalSetting()` to retrieve config values (respects fixed/hidden)

## Deployment
- Plugin must be installed via LimeSurvey's plugin manager
- No build steps required - PHP files are executed directly
- No external dependencies - uses only LimeSurvey's API and PHP cURL

## Key Files
- `LimeSurveyXAPITracker.php` - Main plugin logic
- `README.md` - Configuration reference
- `application/config/config.php` - Production configuration location

## Avoid
- Don't modify LimeSurvey core files
- Don't hardcode credentials in plugin code - use config.php
- Don't leave debug mode enabled in production
- Don't assume survey structure - use LimeSurvey's API to fetch data
- Don't use `echo` for debugging - use `customLog()` (controlled by sBug)

## References
- https://github.com/e-ucm/LimeSurveyXAPITracker
- https://learninglocker.net/docs/xapi/
- https://manual.limesurvey.org/Plugin_development

