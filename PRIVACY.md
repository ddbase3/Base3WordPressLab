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
where enabled. The button is visible to anonymous visitors and administration
users. On open, the chatbot markup/scripts are inserted in the parent page
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
`BASE3_WORDPRESS_DEVELOPER_EMAILS`). If absent, the fixed developer list is
empty. Evaluating this membership reads usermanager-provided identity data;
the group does not query a separate identity store or transmit addresses.
Authorized administrators may add members through the host-owned BASE3 group
settings, but cannot delete code-defined identities through the UI. Revocation
of a bootstrap member requires updating the deployment configuration.

The lab settings service reads the effective BASE3 groups to select reduced or
full WordPress admin tabs. This is presentation-only; server-side native admin
and development checks independently secure the Groups/Features routes. The
lab service creates no new database or table, and persists no navigation
activity itself. Deployment backup/retention policy, consent/ePrivacy issues,
controller/legal basis, and repository access controls remain unverified.
