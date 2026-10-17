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
