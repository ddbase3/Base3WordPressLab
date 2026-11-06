# Base3WordPressLab

WordPress project composition for BASE3. The host owns WordPress user,
authorization, private storage and HTTP/asset adapters; this project chooses
the concrete application services. Runtime instances are registered with
`IContainer` factories, never constructed during `init()`.

## Discoverable settings

The shared `ClientStack\Display\SettingsDisplay` now discovers
`UiFoundation\Api\ISettingsTab` contributions through the normal BASE3
classmap. This project no longer holds a static administration catalog.
The project injects `chatbotConfigDisplayData` for the existing
`Chatbot\SettingsTab\ChatbotconfigSettingsTab`, keeping `chatbot/default`.
The FileManager owner uses the same project-owned identity. WordPress
`manage_options`, BASE3 `IAuthorizationService` decisions and both nonce/CSRF
checks remain authoritative for protected operations.

## Floating chatbot

The WordPress `wp_footer` and `admin_footer` hooks use
`ClientStack\GlobalChatbotLauncher\GlobalChatbotLauncherBootstrap`; both
the module, stylesheet and window modes are owned by ClientStack. WordPress
only supplies the actual chatbot output URL, the `#wpadminbar` dock target and
the host feature/AJAX checks. A single feature declaration,
`ClientStack\GlobalChatbotLauncher\GlobalChatbotLauncherFeature`, is used.
Default audience: `authenticated`. This demo does not preserve the old public
launcher setting, which can be configured anew.

## Private files

Storage still uses `WP_CONTENT_DIR/base3-private/` by default and
`chatbot/default` in collection mode. No FileBridge, new storage backend,
file migration or change of existing WordPress private file protection is part
of this change.

## Access and verification

WordPress administrators with native `manage_options` automatically belong to
this project's BASE3 `development` group and can reach **BASE3 > Werkzeuge >
BASE3 > Features**, without editing `wp-config.php`. Optional exact login or
verified email allowlists can grant additional `development` membership, but
administration actions still require `manage_options`.

```sh
php modules/Base3WordPressLab/test/GlobalChatbotSmokeTest.php
node modules/Base3WordPressLab/test/GlobalChatbotDomSmokeTest.js
```

The installed WordPress 7.1.4 single-site Docker environment still needs
real browser and PHP/HTTP integration checks after deploying the patch.
