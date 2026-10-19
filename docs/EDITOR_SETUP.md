# Editing the publication

1. Open the website’s `/admin/` address.
2. Select **Sign in with NTNET** and allow the login popup.
3. Use your NTNET username and password on the university login page. Web Operations must include you in this publication’s access list.
4. Choose Homepage, Associated Website Notice, or Issues. Change the fields and select Save.
5. Allow the GitHub Actions build to finish, then check the public website.

For a new issue, select New issue and enter an identifier such as `volume-2`. Add its title, a unique positive order number, PDF, and optional cover. Describe the cover for readers using assistive technology. Turn on Published when ready to display it. Turn Published off to hide an existing issue.

Uploads support PDFs and JPEG, PNG, GIF, or WebP images up to 15 MB. Uploading stores the file in the repository immediately; Save connects it to the entry. An abandoned upload can leave an unused file. This editor does not delete files or issues. Ask Web Operations to remove unused files when necessary.

A save commits to the repository. It is not a preview or scheduled draft. An unpublished issue stays out of the public list, but its JSON and uploaded files remain accessible in the public repository or deployed asset folders. Do not upload confidential material.

Your editing session lasts 30 minutes and stays in the current tab’s memory. Refreshing requires signing in again. If a request reports an expired session, sign in again; unsaved fields remain in the form. Signing out of the editor does not sign out of the university’s other applications.

If another person changed the same entry, keep a copy of your edits before reloading. Conflicts are reported rather than silently overwriting the newer version. If the public site does not update, ask Web Operations to inspect the Actions build; duplicate issue order numbers or missing media can fail validation.
