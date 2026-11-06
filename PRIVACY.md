# Base3WordPressLab: source-verified project configuration

The project defines the FileManager storage owner as `chatbot/default` and
provides endpoint URLs through `ILinkTargetService`. This project-settings
class does not process uploaded bytes or persist a separate user/file
registry. File operations are delegated to the BASE3 `IOutput` component and
the existing WordPress storage services.

The project configuration alone does not establish retention periods, a
controller, hosting location, processors, legal bases, backup deletion, or
cross-border transfer details. Operators must evaluate those for their actual
WordPress deployment. This routing change does not add data categories or
change the storage owner.

## Global chatbot composition

The project renders a sitewide launcher that consumes the shared ClientStack
`GlobalChatbotLauncher` ES module and CSS. Its only rendering responsibility is
to supply an existing generic `chatbotdisplay` URL, project translation labels,
and the shared launcher flags. The same `chatbot/default` identity is used in
both the button and the global administration configuration form; content
chatbots with other identities remain separate.

The launcher reads the presentation flags from the existing `ISettingsStore`
dataset `global-chatbot-launcher/default`. The ClientStack module uses browser
local/session storage to preserve sizes, selected modes and reopening state
where enabled. The feature `globalchatbotlauncherfeature` now controls whether the button is
rendered and whether its existing `chatbot/default` AJAX display load may run.
Its default expression permits the `authenticated` group only. No old
WordPressLab feature audience is migrated on the demo installation. The
active host `IAuthorizationService` evaluates current group/criterion metadata
and configured audience overrides from the existing
`base3_authorization_features/catalog` settings record. The `GlobalChatbot`
component checks before including its browser launcher or permitting the AJAX
load; it reuses the generic WordPress display-data decoder and returns HTTP 403
on denial. No new endpoint, storage, recipient, or processing category is
introduced. Stored feature overrides may contain user IDs, role names and
identity criteria; normal host settings retention and deletion apply. Direct
block/shortcode rendering and the general conversation endpoints are
independent of the launcher feature.

On permitted open, the chatbot markup/scripts are inserted in the parent page
rather than isolated in a separate iframe. Message content, conversations and
providers remain under the existing BASE3 Chatbot/Assistant contracts; the
project launcher does not directly store or transmit their data beyond calling
the same-origin display URL.

The applicable deployment's recipients, hosting location, retention/deletion,
backups, cookies and ePrivacy duties, consent, processor terms and permissions
are not verifiable from these sources alone. Public use, frontend themes and
accessible keyboard/screen-reader behavior require deployment checks.

## WordPress administrator bar and docked launcher

When the shared global chatbot sidebar is open, the WordPress integration
provides `#wpadminbar` as the host header. ClientStack temporarily adjusts
its inline layout on desktop so the header ends at the sidebar. The panel is displayed
above the administration bar. The previous header styles are restored on
close. This introduces no new request, tracking, storage or data recipient.

## BASE3 development bootstrap and navigation (2026-10-19)

The project-owned code group `Base3WordPressDevelopmentGroup` reads optional
explicit WordPress login names and full email addresses configured in
`wp-config.php` (`BASE3_WORDPRESS_DEVELOPER_USERNAMES` and
`BASE3_WORDPRESS_DEVELOPER_EMAILS`). The fixed project criteria also check the
user's native WordPress `manage_options` capability. A site administrator
therefore obtains BASE3 `development` membership even when both optional
identity lists are empty. Evaluating this membership uses the native WordPress
capability API and existing usermanager identity data; no separate store or
recipient is introduced.
Authorized administrators may add members through the host-owned BASE3 group
settings, but cannot delete code-defined identities through the UI. Revocation
of a bootstrap member requires updating the deployment configuration.

The project no longer owns a static settings catalog. ClientStack discovers
`ISettingsTab` instances through the BASE3 classmap. WordPress native
`manage_options`, BASE3 feature decisions, project `development` membership
(including native site administrators) and transport/session CSRF checks protect
the relevant actions. No new database, table or navigation log is
created by this configuration; audiences remain in the existing host settings. Deployment backup/retention policy, consent/ePrivacy issues,
controller/legal basis, and repository access controls remain unverified.

## Canonical service ownership

The project registers `chatbotConfigDisplayData` and a trusted FileManager owner
parameter with lazy service resolution. The global launcher presentation is owned
by ClientStack. The prior WordPressLab launcher feature declaration, static admin
configuration, and launcher assets are removed in the same patch. WordPress
private files, upload sessions and their directory layout are unchanged.
