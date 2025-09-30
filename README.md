## xAPI Tracker for LimeSurvey Plugin 

This plugin sends xAPI (Experience API) statements to an external **LRS (Learning Record Store)** whenever participants interact with LimeSurvey. You can configure it globally or set **per-survey overrides** if needed.

## Installation
- go to [releases](https://github.com/e-ucm/LimeSurveyXAPITracker/releases) and download the latest release Zip archive
- for LimeSurvey 5.x and above: upload the Zip archive in the plugin manager
- configure the plugin in the plugin manager
- activate the plugin in the plugin manager

To test the latest development version `git clone` [this repository](https://github.com/e-ucm/LimeSurveyXAPITracker.git)
into `<limesurvey_root>/plugins/LimeSurveyXAPITracker/`.

# Configuration

Before activating the plugin open its configuration from the plugin manager or create your own configuration in `application/config/config.php` file

### 🛠 Global Plugin Settings

| Key | Type | Description |
|-----|------|-------------|
| `baseUrlLRC` | `string` | **Default LimeSurvey RemoteControl (JSON-RPC) URL**, used to fetch survey data if needed. |
| `usernameLRC` | `string` | **RemoteControl username** used for authentication. |
| `passwordLRC` | `string` | **RemoteControl password** used for authentication. |
| `sId` | `string` | **Survey ID filter.** Enter comma-separated survey IDs (e.g. `123456,234567`) or leave empty to apply to all surveys. |
| `surveylrsendpoint` | `checkbox` | **Enable per-survey endpoint mode.** If enabled, each survey can define its own `lrs-endpoint` value. If disabled, the global `lrsEndpoint` is always used. |
| `lrsEndpoint` | `string` | **Default LRS Endpoint URL**. You can use temporary endpoints like `https://webhook.site` for testing. |
| `actorHomepage` | `string` | **Homepage identifier** for the xAPI actor object. Typically your platform's URL. |
| `oAuthType` | `select` (`oauth1`, `oauth2`) | **Authentication method** used when sending xAPI data. |
| `usernameOAuth` | `string` | OAuth username (if used). |
| `passwordOAuth` | `string` | OAuth password (if used). |
| `OAuth2TokenEndpoint` | `string` | **OAuth2 token endpoint URL**. Required when using OAuth2. |
| `OAuth2LogoutEndpoint` | `string` | **OAuth2 logout endpoint** (optional, for cleanup). |
| `OAuth2ClientId` | `string` | **OAuth2 client ID** used to request the token. |
| `sBug` | `checkbox` | **Enable Debug Mode.** Displays outgoing xAPI payloads to the respondent — only enable during testing. |

---

### 🎛 Per-Survey Override Settings

If `surveylrsendpoint` is enabled globally, each survey gets its own configuration section:

| Key | Type | Description |
|-----|------|-------------|
| `lrs-endpoint` | `string` | **Override LRS endpoint for this specific survey.** If already set once, it becomes readonly. |
| _(Auto info panel)_ | `info` | Displays either **"LRS INFO"** or **"LRS INFO DEFINED GLOBALLY"** depending on whether the override mode is active. |

---

### ✅ Recommended Setup Workflow

1. **Set `lrsEndpoint` globally** and test with a dummy LRS like `https://webhook.site`.
2. **Enable `sBug` (debug mode temporarily)** to view the emitted xAPI statements.
3. **Configure OAuth if your LRS requires authentication.**
4. **Enable `surveylrsendpoint` if you want different LRS targets per survey.**
5. **Disable debug mode before going live.**

## Default of fixed configuration

You can set default configuration by array in config part of LimeSurvey config file.

The config are set at `WebhookSettings` key with array of settings by name. For fixed config part you use an array with settings name in `fixed` array. If you want to hide some element from gui, you can use `hidden` array.

For example :
```php
	'XAPITrackerSettings' => [
            'sBug' => '{{ .plugins.xapitracker.debug }}',
            'fixed' => [
                'baseUrlLRC' => 'https://limesurvey.example.com/admin/remotecontrol',
                'usernameLRC' => 'myadminuserLRC',
                'passwordLRC' => 'myadmin',
                'actorHomepage' => 'https://example.com',
                'surveylrsendpoint' => 'false',
                'lrsEndpoint' => '',
                'oAuthType' => 'oauth2',
                'usernameOAuth' => 'username',
                'passwordOAuth' => 'password',
                'OAuth2TokenEndpoint' => 'https://keycloak.example.com/protocol/openid-connect/token',
                'OAuth2LogoutEndpoint' => 'https://keycloak.example.com/protocol/openid-connect/logout',
                'OAuth2ClientId' => 'limesurvey',
                'sId'=> '',
            ],
            'hidden' => ['sToken', 'usernameLRC', 'passwordLRC', 'usernameOAuth', 'passwordOAuth'],
        ],
```

# Supported LimeSurvey Versions

This plugin was tested with

- A recent version v6.4.3 (PHP 8.1)
- the latest stable release v5.2.5

and should work with all version 5.x or newer.

The minimum required PHP version is 8.1.

## Usage
You are free to use/change/fork this code for your own products.